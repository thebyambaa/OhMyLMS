<?php
/**
 * Notice functions.
 *
 * @package OhMyLMS
 */


namespace OhMyLMS\Commerce;

/**
 * Add and store a notice
 *
 * @param string $message The notice message.
 * @param string $notice_type The type of notice (e.g., 'success', 'error').
 * @param array  $data Additional data for the notice.
 * @return void
 *
 * @since 1.0.0
 */
function ohmylmse_add_notice( $message, $notice_type = 'success', $data = array() ) {
	if ( ! did_action( 'ohmylms_after_init' ) ) {
		return;
	}

	$notices = ecommerce()->session->get( 'ohmylmse_notices', array() );

	// Backward compatibility.
	if ( 'success' === $notice_type ) {
		$message = apply_filters( 'ohmylms_add_message', $message );
	}

	$message = apply_filters( 'ohmylms_add_' . $notice_type, $message );

	if ( ! empty( $message ) ) {
		$notices[ $notice_type ][] = array(
			'notice' => $message,
			'data'   => $data,
		);
	}

	ecommerce()->session->set( 'ohmylmse_notices', $notices );
}

/**
 * Get the count of notices of a specific type.
 *
 * @param string $notice_type The type of notice to count.
 * @return int The count of notices.
 *
 * @since 1.0.0
 */
function ohmylmse_notice_count( $notice_type = 'error' ) {
	if ( ! did_action( 'ohmylms_after_init' ) ) {
		return 0;
	}

	$notice_count = 0;
	$all_notices  = ecommerce()->session->get( 'ohmylmse_notices', array() );
	if ( isset( $all_notices[ $notice_type ] ) && is_array( $all_notices[ $notice_type ] ) ) {
		$notice_count = count( $all_notices[ $notice_type ] );
	} elseif ( empty( $notice_type ) ) {
		foreach ( $all_notices as $notices ) {
			if ( is_array( $notices ) ) {
				$notice_count += count( $notices );
			}
		}
	}

	return $notice_count;
}

/**
 * Check if a specific notice exists.
 *
 * @param string $message The notice message to check.
 * @param string $notice_type The type of notice.
 * @return bool True if the notice exists, false otherwise.
 *
 * @since 1.0.0
 */
function ohmylmse_has_notice( $message, $notice_type = 'success' ) {
	if ( ! did_action( 'ohmylms_after_init' ) ) {
		return false;
	}

	$notices = ecommerce()->session->get( 'ohmylmse_notices', array() );
	$notices = isset( $notices[ $notice_type ] ) ? $notices[ $notice_type ] : array();
	return array_search( $message, wp_list_pluck( $notices, 'notice' ), true ) !== false;
}

/**
 * Set the notices.
 *
 * @param array $notices The notices to set.
 * @return void
 *
 * @since 1.0.0
 */
function ohmylmse_set_notices( $notices ) {
	if ( ! did_action( 'ohmylms_after_init' ) ) {
		return;
	}
	ecommerce()->session->set( 'ohmylmse_notices', $notices );
}

/**
 * Clear all notices.
 *
 * @return void
 *
 * @since 1.0.0
 */
function ohmylmse_clear_notices() {
	if ( ! did_action( 'ohmylms_after_init' ) ) {
		return;
	}
	ecommerce()->session->set( 'ohmylmse_notices', null );
}

/**
 * Print all notices.
 *
 * @param bool $return Whether to return the notices instead of printing them.
 * @return string|null The notices if $return is true, null otherwise.
 *
 * @since 1.0.0
 */
function ohmylmse_print_notices( $return = false ) {

	if ( ! did_action( 'ohmylms_after_init' ) ) {
		return;
	}

	$session = ecommerce()->session;

	// If the session handler has not initialized, there will be no notices for us to read.
	if ( null === $session ) {
		return;
	}

	$all_notices  = $session->get( 'ohmylmse_notices', array() );
	$notice_types = apply_filters( 'ohmylmse_notice_types', array( 'error', 'success', 'notice' ) );

	// Buffer output.
	ob_start();

	foreach ( $notice_types as $notice_type ) {
		if ( ohmylmse_notice_count( $notice_type ) > 0 ) {
			$messages = array();

			foreach ( $all_notices[ $notice_type ] as $notice ) {
				$messages[] = isset( $notice['notice'] ) ? $notice['notice'] : $notice;
			}

			ohmylms_get_template(
				"notices/{$notice_type}.php",
				array(
					'messages' => array_filter( $messages ),
					'notices'  => array_filter( $all_notices[ $notice_type ] ),
				)
			);
		}
	}

	ohmylmse_clear_notices();

	$notices = ohmylmse_kses_notice( ob_get_clean() );

	if ( $return ) {
		return $notices;
	}

	echo $notices; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Print a single notice.
 *
 * @param string $message The notice message.
 * @param string $notice_type The type of notice.
 * @param array  $data Additional data for the notice.
 * @param bool   $return Whether to return the notice instead of printing it.
 * @return string|null The notice if $return is true, null otherwise.
 *
 * @since 1.0.0
 */
function ohmylms_print_notice( $message, $notice_type = 'success', $data = array(), $return = false ) {
	if ( 'success' === $notice_type ) {
		$message = apply_filters( 'ohmylms_add_message', $message );
	}

	$message = apply_filters( 'ohmylms_add_' . $notice_type, $message );

	// Buffer output.
	ob_start();

	ohmylms_get_template(
		"notices/{$notice_type}.php",
		array(
			'messages' => array( $message ), // @deprecated 3.9.0
			'notices'  => array(
				array(
					'notice' => $message,
					'data'   => $data,
				),
			),
		)
	);

	$notice = ohmylmse_kses_notice( ob_get_clean() );

	if ( $return ) {
		return $notice;
	}

	echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Get all notices or notices of a specific type.
 *
 * @param string $notice_type The type of notice to get.
 * @return array The notices.
 *
 * @since 1.0.0
 */
function ohmylms_get_notices( $notice_type = '' ) {
	if ( ! did_action( 'ohmylms_after_init' ) ) {
		return array();
	}

	$all_notices = ecommerce()->session->get( 'ohmylmse_notices', array() );

	if ( empty( $notice_type ) ) {
		$notices = $all_notices;
	} elseif ( isset( $all_notices[ $notice_type ] ) ) {
		$notices = $all_notices[ $notice_type ];
	} else {
		$notices = array();
	}

	return $notices;
}

/**
 * Add WordPress error notices.
 *
 * @param \WP_Error $errors The WordPress error object.
 * @return void
 *
 * @since 1.0.0
 */
function ohmylmse_add_wp_error_notices( $errors ) {
	if ( is_wp_error( $errors ) && $errors->get_error_messages() ) {
		foreach ( $errors->get_error_messages() as $error ) {
			ohmylmse_add_notice( $error, 'error' );
		}
	}
}

/**
 * Sanitize a notice message.
 *
 * @param string $message The notice message.
 * @return string The sanitized notice message.
 *
 * @since 1.0.0
 */
function ohmylmse_kses_notice( $message ) {
	$allowed_tags = array_replace_recursive(
		wp_kses_allowed_html( 'post' ),
		array(
			'a' => array(
				'tabindex' => true,
			),
		)
	);
	return wp_kses( $message, apply_filters( 'ohmylms_kses_notice_allowed_tags', $allowed_tags ) );
}

/**
 * Get data attributes for a notice.
 *
 * @param array $notice The notice data.
 * @return string|null The data attributes as a string, or null if no data.
 *
 * @since 1.0.0
 */
function ohmylmse_get_notice_data_attr( $notice ) {
	if ( empty( $notice['data'] ) ) {
		return null;
	}

	$attr = '';

	foreach ( $notice['data'] as $key => $value ) {
		$attr .= sprintf(
			' data-%1$s="%2$s"',
			sanitize_title( $key ),
			esc_attr( $value )
		);
	}

	return $attr;
}
