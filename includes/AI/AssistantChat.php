<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping.
/**
 * Bounded, server-owned administrator conversations.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\AI;

defined( 'ABSPATH' ) || exit;

/** Model tool calls never confer permission to execute a write. */
final class AssistantChat {
	/**
	 * Process a message or a decision under a per-administrator database lock.
	 *
	 * @param array $data Validated request body.
	 * @return array|\WP_Error
	 */
	public static function message( array $data ) {
		global $wpdb;
		$lock = 'oml_chat_' . md5( $wpdb->prefix . get_current_user_id() );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
			return new \WP_Error( 'assistant_busy', __( 'A reply is already running. Please wait.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		try {
			return self::turn( $data );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * Persist before any further provider call, including after approved writes.
	 *
	 * @param string $key Conversation cache key.
	 * @param array  $state Server state.
	 * @param string $error Optional safe error message.
	 * @return array
	 */
	private static function save( $key, array $state, $error = '' ) {
		set_transient( $key, $state, HOUR_IN_SECONDS );
		return array(
			'conversation_id' => $state['id'],
			'messages'        => $state['messages'],
			'pending'         => $state['pending'],
			'receipts'        => $state['receipts'],
			'error'           => $error,
		);
	}

	/**
	 * Continue one conversation. Clients cannot supply tool results or history.
	 *
	 * @param array $data Request body.
	 * @return array|\WP_Error
	 */
	private static function turn( array $data ) {
		$id = $data['conversation_id'] ?? '';
		if ( ! is_string( $id ) || ( $id && ! wp_is_uuid( $id ) ) ) {
			return new \WP_Error( 'assistant_id', __( 'Invalid conversation.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$key   = 'oml_chat_' . get_current_user_id() . '_' . $id;
		$state = $id ? get_transient( $key ) : false;
		if ( $id && ! $state ) {
			return new \WP_Error( 'assistant_expired', __( 'This conversation expired. Start a new conversation.', 'ohmylms' ), array( 'status' => 410 ) );
		}
		if ( ! $state ) {
			$id    = wp_generate_uuid4();
			$key   = 'oml_chat_' . get_current_user_id() . '_' . $id;
			$state = array(
				'id'       => $id,
				'input'    => array(),
				'messages' => array(),
				'pending'  => null,
				'receipts' => array(),
			);
		}
		$rate_key = 'oml_chat_rate_' . get_current_user_id();
		$rate     = (int) get_transient( $rate_key );
		if ( $rate >= 30 ) {
			return self::save( $key, $state, __( 'Conversation limit reached. Try again in ten minutes.', 'ohmylms' ) );
		}
		set_transient( $rate_key, $rate + 1, 10 * MINUTE_IN_SECONDS );
		if ( count( $state['messages'] ) >= 40 || strlen( wp_json_encode( $state['input'] ) ) > 200000 ) {
			return self::save( $key, $state, __( 'Start a new conversation to continue.', 'ohmylms' ) );
		}
		$decision = $data['decision'] ?? '';
		if ( $decision ) {
			if ( ! $state['pending'] || ! in_array( $decision, array( 'approve', 'reject' ), true ) || ( $data['action_id'] ?? '' ) !== $state['pending']['call_id'] ) {
				return self::save( $key, $state, __( 'This action is no longer awaiting review.', 'ohmylms' ) );
			}
			$pending             = $state['pending'];
			$result              = 'approve' === $decision ? AssistantMcpClient::call( $pending['name'], $pending['arguments'] ) : array(
				'isError' => true,
				'message' => 'Administrator declined. Nothing was saved.',
			);
			$result              = is_wp_error( $result ) ? array(
				'isError' => true,
				'message' => $result->get_error_message(),
			) : $result;
			$state['input'][]    = array(
				'type'    => 'function_call_output',
				'call_id' => $pending['call_id'],
				'output'  => wp_json_encode( $result ),
			);
			$state['receipts'][] = array(
				'name'    => $pending['name'],
				'success' => empty( $result['isError'] ),
				'result'  => $result,
			);
			$state['pending']    = null;
			// A provider outage must never leave an executed write available to replay.
			self::save( $key, $state );
		} else {
			$text  = $data['message'] ?? '';
			$files = AssistantAttachments::resolve( $data['attachments'] ?? array() );
			if ( is_wp_error( $files ) ) {
				return $files;
			}
			if ( $state['pending'] || ! is_string( $text ) || ( ! trim( $text ) && ! $files ) || mb_strlen( $text ) > 4000 ) {
				return self::save( $key, $state, __( 'Review the pending action, or enter a message of up to 4,000 characters.', 'ohmylms' ) );
			}
			$text    = sanitize_textarea_field( $text );
			$content = array(
				array(
					'type' => 'input_text',
					'text' => $text ? $text : __( 'Please review the attached files.', 'ohmylms' ),
				),
			);
			$names   = array();
			foreach ( $files as $file ) {
				$content[]            = array(
					'type'    => str_starts_with( $file['mime'], 'image/' ) ? 'input_image' : 'input_file',
					'file_id' => $file['file_id'],
				);
				$names[]              = array(
					'name'  => $file['name'],
					'bytes' => $file['bytes'],
				);
				$file['used']         = $id;
				$state['file_expiry'] = min( $state['file_expiry'] ?? $file['expires'], $file['expires'] );
				set_transient( 'oml_file_' . get_current_user_id() . '_' . $file['id'], $file, max( 1, $file['expires'] - time() ) );
			}
			$state['input'][]    = array(
				'role'    => 'user',
				'content' => $files ? $content : $text,
			);
			$state['messages'][] = array(
				'role'        => 'user',
				'text'        => $text,
				'attachments' => $names,
			);
		}
		if ( ! AssistantSettings::summary()['configured'] ) {
			return self::save( $key, $state, __( 'Configure and enable the OpenAI assistant first.', 'ohmylms' ) );
		}
		if ( ! empty( $state['file_expiry'] ) && $state['file_expiry'] <= time() ) {
			return self::save( $key, $state, __( 'The files in this conversation expired. Start a new chat and attach them again.', 'ohmylms' ) );
		}
		$context = is_string( $data['context'] ?? null ) ? substr( sanitize_text_field( $data['context'] ), 0, 300 ) : '';
		for ( $round = 0; $round < 3; ++$round ) {
			$output = AssistantOpenAI::respond( $state['input'], $context );
			if ( is_wp_error( $output ) ) {
				return self::save( $key, $state, $output->get_error_message() );
			}
			$state['input'] = array_merge( $state['input'], $output );
			$calls          = array();
			foreach ( $output as $item ) {
				if ( 'function_call' === ( $item['type'] ?? '' ) ) {
					$calls[] = $item;
				}
				if ( 'message' === ( $item['type'] ?? '' ) ) {
					foreach ( $item['content'] ?? array() as $content ) {
						if ( 'output_text' === ( $content['type'] ?? '' ) && is_string( $content['text'] ?? null ) ) {
							$state['messages'][] = array(
								'role' => 'assistant',
								'text' => $content['text'],
							);
						}
					}
				}
			}
			if ( ! $calls ) {
				return self::save( $key, $state );
			}
			foreach ( $calls as $call ) {
				$args  = json_decode( $call['arguments'] ?? '', true );
				$valid = AssistantTools::validate( $call['name'], $args );
				if ( count( $calls ) > 1 || is_wp_error( $valid ) ) {
					$result = array(
						'isError' => true,
						'message' => 'Use one supported tool with valid arguments.',
					);
				} elseif ( AssistantTools::definitions()[ $call['name'] ]['write'] ) {
					$state['pending'] = array(
						'call_id'   => $call['call_id'],
						'name'      => $call['name'],
						'arguments' => $args,
					);
					return self::save( $key, $state );
				} else {
					$result = AssistantMcpClient::call( $call['name'], $args );
					$result = is_wp_error( $result ) ? array(
						'isError' => true,
						'message' => $result->get_error_message(),
					) : $result;
					if ( strlen( wp_json_encode( $result ) ) > 24000 ) {
						$result = array(
							'isError' => true,
							'message' => 'Result too large. Search or request a smaller page.',
						);
					}
				}
				$state['input'][] = array(
					'type'    => 'function_call_output',
					'call_id' => $call['call_id'],
					'output'  => wp_json_encode( $result ),
				);
			}
			self::save( $key, $state );
		}
		return self::save( $key, $state, __( 'Tool limit reached for this turn. Ask a narrower follow-up.', 'ohmylms' ) );
	}
}
