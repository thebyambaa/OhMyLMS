<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class file; see docs/CODING-STANDARDS.md.
/**
 * Learner account pages on the Interactivity API (batch L4 of docs/LEARNER-FRONTEND.md): cover and
 * profile photo, the membership "View plan" dialog, membership cancellation and invoice printing
 * (`ohmylms/account`), the my-courses tabs (`ohmylms/tabs`) and the notification switches.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Extensions;

defined( 'ABSPATH' ) || exit;

/** Decorates trusted profile templates, including theme overrides, with account directives. */
final class Account {

	const STORE = 'ohmylms/account';

	const PROFILE_IMAGES = array(
		'data-wp-interactive'    => self::STORE,
		'data-wp-class--is-busy' => 'state.isBusy',
	);

	const MEMBERSHIP_CARD = array(
		'data-wp-interactive' => self::STORE,
		'data-wp-context'     => '{"planOpen":false}',
	);

	const PLAN_TRIGGER = array(
		'data-wp-on--click'           => 'actions.openPlan',
		'data-wp-bind--aria-expanded' => 'context.planOpen',
		'aria-haspopup'               => 'dialog',
		'aria-expanded'               => 'false',
	);

	const PLAN_MODAL = array(
		'data-wp-style--display'    => 'state.planDisplay',
		'data-wp-bind--aria-hidden' => '!context.planOpen',
		'data-wp-on--click'         => 'actions.planBackdrop',
		'data-wp-watch'             => 'callbacks.planFocus',
	);

	const CANCEL_LINK = array(
		'data-wp-interactive'         => self::STORE,
		'data-wp-on--click'           => 'actions.cancelMembership',
		'data-wp-bind--aria-disabled' => 'context.busy',
		'role'                        => 'button',
	);

	const PRINT_BUTTON = array(
		'data-wp-interactive' => self::STORE,
		'data-wp-on--click'   => 'actions.printInvoice',
	);

	const TABS_ROOT = 'ohmylms-my-courses-tab-nav';

	/** Switches of `profile/notifications.php`; `FormHandler::save_account_notification()` stores a posted value, "off" when missing. */
	const NOTIFICATION_SWITCHES = array( 'notification_chapter', 'notification_new_course_content', 'notification_direct_messages', 'notification_comments_replies' );

	/**
	 * Plan dialogs rendered in this request, for unique ids.
	 *
	 * @var int
	 */
	private static $plans = 0;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'ohmylms_template_html', array( __CLASS__, 'template' ), 15, 2 );
	}

	/**
	 * Decorate a rendered template.
	 *
	 * @param string $html     Template HTML.
	 * @param string $template Template name.
	 * @return string
	 */
	public static function template( $html, $template ) {
		if ( ! is_string( $html ) || '' === $html || 0 === strpos( (string) $template, 'emails/' ) ) {
			return $html;
		}
		return self::tabs( self::decorate( $html ) );
	}

	/**
	 * Context of the profile image area for the current learner.
	 *
	 * @param \OhMyLMS\Data\Student|null $student Learner (default: current user).
	 * @return array
	 */
	public static function profile_context( $student = null ) {
		$student = $student ? $student : new \OhMyLMS\Data\Student( get_current_user_id() );
		return array(
			'cover'    => esc_url_raw( (string) $student->get_cover_image() ),
			'hasCover' => (bool) $student->has_cover_image(),
			'avatar'   => esc_url_raw( (string) $student->get_profile_image() ),
			'busy'     => '',
		);
	}

	/**
	 * Load `ohmylms/account` and its state. Features add the nonces they post.
	 *
	 * @param string[] $features `profile` (image uploads) and/or `cancel` (membership cancellation).
	 */
	public static function enqueue( array $features = array() ) {
		Interactivity::enqueue( self::STORE );
		Chrome::enqueue_ui();
		$state = array();
		if ( Interactivity::once( 'account-state' ) ) {
			$state['messages'] = self::messages();
		}
		if ( in_array( 'profile', $features, true ) && Interactivity::once( 'account-profile-state' ) ) {
			$state['nonces']['coverUpload']  = wp_create_nonce( 'student_profile_cover_image' );
			$state['nonces']['coverDelete']  = wp_create_nonce( 'student_profile_cover_image_delete' );
			$state['nonces']['avatarUpload'] = wp_create_nonce( 'student_profile_image_upload' );
			$state['maxUpload']              = (int) wp_max_upload_size();
		}
		if ( in_array( 'cancel', $features, true ) && Interactivity::once( 'account-cancel-state' ) ) {
			$state['nonces']['cancelMembership'] = wp_create_nonce( 'cancel_membership_nonce' );
		}
		if ( $state ) {
			wp_interactivity_state( self::STORE, $state );
		}
	}

	/**
	 * Translated messages for the store.
	 *
	 * @return array
	 */
	public static function messages() {
		return array(
			'imageType'     => __( 'Choose an image file (JPEG, PNG, GIF or WebP).', 'ohmylms' ),
			/* translators: %s: maximum upload size, e.g. "8 MB". */
			'imageSize'     => sprintf( __( 'The image is too large. The maximum size is %s.', 'ohmylms' ), size_format( wp_max_upload_size() ) ),
			'uploadFailed'  => __( 'The image could not be uploaded. Please try again.', 'ohmylms' ),
			'coverSaved'    => __( 'Cover image updated.', 'ohmylms' ),
			'avatarSaved'   => __( 'Profile photo updated.', 'ohmylms' ),
			'coverDeleted'  => __( 'Cover image removed.', 'ohmylms' ),
			'deleteFailed'  => __( 'The cover image could not be removed. Please try again.', 'ohmylms' ),
			'cancelTitle'   => __( 'Are you sure you want to cancel this subscription?', 'ohmylms' ),
			'cancelMessage' => __( 'Cancelling this subscription will remove your access to all associated courses at the end of the billing period.', 'ohmylms' ),
			'cancelConfirm' => __( 'Confirm Cancel', 'ohmylms' ),
			'cancelKeep'    => __( 'Keep subscription', 'ohmylms' ),
			'cancelled'     => __( 'Your subscription has been cancelled.', 'ohmylms' ),
			'cancelFailed'  => __( 'The subscription could not be cancelled. Please try again.', 'ohmylms' ),
		);
	}

	/**
	 * Add `ohmylms/account` directives and fix the notification switch values. Elements that
	 * already carry the directives (current templates) are kept as they are.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function decorate( $html ) {
		$needles = array( 'student-profile-edit', 'ohmylms-dashboard-single-membership', 'do-membership-cancel', 'ohmylms-membership-invoice', 'single-notification-settings' );
		$match   = false;
		foreach ( $needles as $needle ) {
			$match = $match || false !== strpos( $html, $needle );
		}
		if ( ! $match ) {
			return $html;
		}
		$tags     = new \WP_HTML_Tag_Processor( $html );
		$features = array();
		$plan     = '';
		$invoice  = false !== strpos( $html, 'ohmylms-membership-invoice' );
		$switches = false !== strpos( $html, 'single-notification-settings' );
		while ( $tags->next_tag() ) {
			$tag = $tags->get_tag();
			if ( $tags->has_class( 'student-profile-edit' ) ) {
				$features['profile'] = true;
				if ( null === $tags->get_attribute( 'data-wp-interactive' ) ) {
					self::set( $tags, self::PROFILE_IMAGES + array( 'data-wp-context' => wp_json_encode( self::profile_context() ) ) );
				}
			} elseif ( isset( $features['profile'] ) && self::profile_part( $tags ) ) {
				continue;
			} elseif ( $tags->has_class( 'ohmylms-dashboard-single-membership' ) ) {
				$features['plan'] = true;
				$plan             = 'ohmylms-plan-' . ( ++self::$plans );
				self::root( $tags, self::MEMBERSHIP_CARD );
			} elseif ( $plan && $tags->has_class( 'view-plan' ) ) {
				if ( self::add( $tags, self::PLAN_TRIGGER ) ) {
					$tags->set_attribute( 'aria-controls', $plan );
					if ( 'A' === $tag ) {
						$tags->set_attribute( 'href', '#' . $plan );
					}
				}
			} elseif ( $plan && $tags->has_class( 'ohmylms-membership-modal' ) ) {
				if ( self::add( $tags, self::PLAN_MODAL ) && null === $tags->get_attribute( 'id' ) ) {
					$tags->set_attribute( 'id', $plan );
				}
			} elseif ( $plan && $tags->has_class( 'ohmylms-membership-modal-inner' ) && null === $tags->get_attribute( 'role' ) ) {
				$tags->set_attribute( 'role', 'dialog' );
				$tags->set_attribute( 'aria-modal', 'true' );
				$tags->set_attribute( 'tabindex', '-1' );
				$tags->set_attribute( 'aria-labelledby', $plan . '-title' );
			} elseif ( $plan && $tags->has_class( 'membership-modal-title' ) && null === $tags->get_attribute( 'id' ) ) {
				$tags->set_attribute( 'id', $plan . '-title' );
			} elseif ( $plan && $tags->has_class( 'close-modal' ) ) {
				if ( self::add( $tags, array( 'data-wp-on--click' => 'actions.closePlan' ) ) && null === $tags->get_attribute( 'aria-label' ) ) {
					$tags->set_attribute( 'aria-label', __( 'Close', 'ohmylms' ) );
				}
			} elseif ( $tags->has_class( 'do-membership-cancel' ) ) {
				$features['cancel'] = true;
				if ( null === $tags->get_attribute( 'data-wp-interactive' ) ) {
					// Pre-L4 overrides keep the subscription id on the confirm button of their inline alert.
					self::set( $tags, self::CANCEL_LINK + array( 'data-wp-context' => '{"busy":false}' ) );
				}
			} elseif ( $invoice && $tags->has_class( 'print-button' ) ) {
				$features['print'] = true;
				self::root( $tags, self::PRINT_BUTTON );
			} elseif ( $switches && 'INPUT' === $tag && 'checkbox' === $tags->get_attribute( 'type' ) && in_array( $tags->get_attribute( 'name' ), self::NOTIFICATION_SWITCHES, true ) ) {
				// A checked switch must post "on"; pre-L4 templates printed the stored value, which a script then fixed.
				$tags->set_attribute( 'value', 'on' );
			}
		}
		if ( $features ) {
			self::enqueue( array_keys( $features ) );
		}
		return $tags->get_updated_html();
	}

	/**
	 * Children of the profile image area.
	 *
	 * @param \WP_HTML_Tag_Processor $tags Processor on an opening tag inside `.student-profile-edit`.
	 * @return bool Whether the tag belongs to the image area.
	 */
	private static function profile_part( $tags ) {
		$id = (string) $tags->get_attribute( 'id' );
		if ( 'student-cover-image' === $id ) {
			self::add( $tags, array( 'data-wp-bind--src' => 'context.cover' ) );
		} elseif ( 'cover_image_input' === $id ) {
			self::add( $tags, array( 'data-wp-on--change' => 'actions.uploadCover' ) );
		} elseif ( 'profile_photo_input' === $id ) {
			self::add( $tags, array( 'data-wp-on--change' => 'actions.uploadAvatar' ) );
		} elseif ( 'cover-image-delete' === $id ) {
			self::add(
				$tags,
				array(
					'data-wp-on--click'      => 'actions.deleteCover',
					'data-wp-style--display' => 'state.coverDeleteDisplay',
					'data-wp-bind--disabled' => 'state.isBusy',
				)
			);
		} elseif ( 'LABEL' === $tags->get_tag() && ( $tags->has_class( 'cover-edit' ) || $tags->has_class( 'edit-avatar' ) ) ) {
			self::add( $tags, array( 'data-wp-on--keydown' => 'actions.pickKey' ) );
		} elseif ( 'IMG' === $tags->get_tag() && $tags->has_class( 'student-profile-photo' ) ) {
			self::add(
				$tags,
				array(
					'data-wp-bind--src'    => 'context.avatar',
					'data-wp-bind--hidden' => 'state.avatarHidden',
				)
			);
		} else {
			return false;
		}
		return true;
	}

	/**
	 * Drive `.ohmylms-my-courses-tab-nav` with the public `ohmylms/tabs` store: the first tab
	 * (or the one marked active) is selected, arrow keys, Home and End move between tabs.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function tabs( $html ) {
		if ( false === strpos( $html, self::TABS_ROOT ) ) {
			return $html;
		}
		// Tab keys in order, from each button's `data-target="#panel-id"`.
		$keys   = array();
		$active = '';
		$scan   = new \WP_HTML_Tag_Processor( $html );
		while ( $scan->next_tag( 'BUTTON' ) ) {
			$target = (string) $scan->get_attribute( 'data-target' );
			if ( 'tab' === $scan->get_attribute( 'role' ) && 0 === strpos( $target, '#' ) ) {
				$keys[] = substr( $target, 1 );
				if ( ! $active && 'true' === $scan->get_attribute( 'aria-selected' ) ) {
					$active = substr( $target, 1 );
				}
			}
		}
		if ( ! $keys ) {
			return $html;
		}
		$active = $active ? $active : $keys[0];
		$tags   = new \WP_HTML_Tag_Processor( $html );
		$tab    = 0;
		$found  = false;
		while ( $tags->next_tag() ) {
			$tag = $tags->get_tag();
			if ( $tags->has_class( self::TABS_ROOT ) ) {
				$found = true;
				self::root(
					$tags,
					array(
						'data-wp-interactive' => 'ohmylms/tabs',
						'data-wp-context'     => wp_json_encode( array( 'activeTab' => $active ) ),
					)
				);
			} elseif ( ! $found ) {
				continue;
			} elseif ( 'LI' === $tag && 'presentation' === $tags->get_attribute( 'role' ) && isset( $keys[ $tab ] ) ) {
				self::add(
					$tags,
					array(
						'data-wp-context'       => wp_json_encode( array( 'tabKey' => $keys[ $tab++ ] ) ),
						'data-wp-class--active' => 'state.selected',
					)
				);
			} elseif ( 'BUTTON' === $tag && 'tab' === $tags->get_attribute( 'role' ) ) {
				self::add(
					$tags,
					array(
						'data-wp-on--click'           => 'actions.select',
						'data-wp-on--keydown'         => 'actions.key',
						'data-wp-bind--aria-selected' => 'state.selected',
						'data-wp-bind--tabindex'      => 'state.tabIndex',
					)
				);
			} elseif ( 'tabpanel' === $tags->get_attribute( 'role' ) && in_array( (string) $tags->get_attribute( 'id' ), $keys, true ) ) {
				self::add(
					$tags,
					array(
						'data-wp-context'       => wp_json_encode( array( 'tabKey' => $tags->get_attribute( 'id' ) ) ),
						'data-wp-class--active' => 'state.selected',
					)
				);
			}
		}
		if ( $found ) {
			Interactivity::enqueue( 'ohmylms/tabs' );
		}
		return $tags->get_updated_html();
	}

	/**
	 * Decorate a store root unless it already belongs to a store.
	 *
	 * @param \WP_HTML_Tag_Processor $tags       Processor.
	 * @param array                  $attributes Attributes.
	 */
	private static function root( $tags, array $attributes ) {
		if ( null === $tags->get_attribute( 'data-wp-interactive' ) ) {
			self::set( $tags, $attributes );
		}
	}

	/**
	 * Add attributes unless the element already has the first directive of the set.
	 *
	 * @param \WP_HTML_Tag_Processor $tags       Processor.
	 * @param array                  $attributes Attributes; the first `data-wp-*` key is the marker.
	 * @return bool Whether the attributes were added.
	 */
	private static function add( $tags, array $attributes ) {
		foreach ( array_keys( $attributes ) as $name ) {
			if ( 0 === strpos( $name, 'data-wp-' ) && 'data-wp-interactive' !== $name ) {
				if ( null !== $tags->get_attribute( $name ) ) {
					return false;
				}
				break;
			}
		}
		self::set( $tags, $attributes );
		return true;
	}

	/**
	 * Set attributes.
	 *
	 * @param \WP_HTML_Tag_Processor $tags       Processor.
	 * @param array                  $attributes Attributes.
	 */
	private static function set( $tags, array $attributes ) {
		foreach ( $attributes as $name => $value ) {
			$tags->set_attribute( $name, $value );
		}
	}
}
