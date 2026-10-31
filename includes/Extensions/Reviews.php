<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class file; see docs/CODING-STANDARDS.md.
/**
 * Course review form on the Interactivity API (`ohmylms/reviews`, batch L5): star rating and the
 * "rating and review both are required" check. The form keeps posting to wp-comments-post.php.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Extensions;

defined( 'ABSPATH' ) || exit;

/** Decorates the course review form, including theme overrides with the pre-L5 link stars. */
final class Reviews {

	const ROOT = array(
		'data-wp-interactive'         => 'ohmylms/reviews',
		'data-wp-context'             => '{"rating":0,"hasComment":false,"attempted":false,"submitting":false}',
		'data-wp-init'                => 'callbacks.restore',
		'data-wp-on--change'          => 'actions.change',
		'data-wp-on--input'           => 'actions.change',
		'data-wp-on--submit'          => 'actions.submit',
		'data-wp-on-window--pageshow' => 'actions.reset',
	);

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'ohmylms_template_html', array( __CLASS__, 'template' ), 16, 2 );
	}

	/**
	 * Decorate a rendered template.
	 *
	 * @param string $html     Template HTML.
	 * @param string $template Template name.
	 * @return string
	 */
	public static function template( $html, $template ) {
		if ( ! is_string( $html ) || 0 === strpos( (string) $template, 'emails/' ) ) {
			return $html;
		}
		return self::decorate( $html );
	}

	/**
	 * Add `ohmylms/reviews` directives to `.ohmylms-course-reviews-form` and its stars, notice and
	 * submit button. Elements that already carry them (the plugin template) are kept; pre-L5 link
	 * stars (`a[data-rating]` with `select#rating`) get the `pick` action.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function decorate( $html ) {
		if ( false === strpos( $html, 'ohmylms-course-reviews-form' ) ) {
			return $html;
		}
		$tags  = new \WP_HTML_Tag_Processor( $html );
		$found = false;
		$depth = 0;
		$root  = '';
		while ( $tags->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			$tag = $tags->get_tag();
			if ( $root && $tag === $root ) {
				$depth += $tags->is_tag_closer() ? -1 : 1;
				if ( 0 === $depth ) {
					$root = '';
					continue;
				}
			}
			if ( $tags->is_tag_closer() ) {
				continue;
			}
			if ( ! $root ) {
				if ( $tags->has_class( 'ohmylms-course-reviews-form' ) ) {
					$root  = $tag;
					$depth = 1;
					$found = true;
					if ( null === $tags->get_attribute( 'data-wp-interactive' ) ) {
						self::set( $tags, self::ROOT );
					}
				}
				continue;
			}
			self::part( $tags );
		}
		if ( $found ) {
			Interactivity::enqueue( 'ohmylms/reviews' );
		}
		return $tags->get_updated_html();
	}

	/**
	 * Children of the review form root.
	 *
	 * @param \WP_HTML_Tag_Processor $tags Processor on an opening tag inside the root.
	 */
	private static function part( $tags ) {
		$tag = $tags->get_tag();
		if ( $tags->has_class( 'ohmylms-stars' ) ) {
			self::add( $tags, array( 'data-wp-class--selected' => 'state.rated' ) );
		} elseif ( 'A' === $tag && null !== $tags->get_attribute( 'data-rating' ) ) {
			$rating = absint( $tags->get_attribute( 'data-rating' ) );
			self::add(
				$tags,
				array(
					'data-wp-on--click'     => 'actions.pick',
					'data-wp-context'       => (string) wp_json_encode( array( 'star' => $rating ) ),
					'data-wp-class--active' => 'state.isStar',
					'role'                  => 'button',
				)
			);
			if ( null === $tags->get_attribute( 'aria-label' ) ) {
				/* translators: %d: number of stars. */
				$tags->set_attribute( 'aria-label', sprintf( _n( '%d star', '%d stars', $rating, 'ohmylms' ), $rating ) );
			}
		} elseif ( $tags->has_class( 'required-notice' ) ) {
			if ( self::add( $tags, array( 'data-wp-style--display' => 'state.noticeDisplay' ) ) ) {
				$tags->set_attribute( 'role', 'alert' );
			}
		} elseif ( 'INPUT' === $tag && 'submit' === strtolower( (string) $tags->get_attribute( 'type' ) ) ) {
			self::add(
				$tags,
				array(
					'data-wp-on--click'      => 'actions.validate',
					'data-wp-bind--disabled' => 'context.submitting',
				)
			);
		} elseif ( 'BUTTON' === $tag && 'button' !== strtolower( (string) $tags->get_attribute( 'type' ) ) ) {
			self::add(
				$tags,
				array(
					'data-wp-on--click'      => 'actions.validate',
					'data-wp-bind--disabled' => 'context.submitting',
				)
			);
		}
	}

	/**
	 * Add attributes unless the element already has the first one.
	 *
	 * @param \WP_HTML_Tag_Processor $tags       Processor.
	 * @param array                  $attributes Attributes; the first key is the marker.
	 * @return bool Whether the attributes were added.
	 */
	private static function add( $tags, array $attributes ) {
		if ( null !== $tags->get_attribute( (string) array_key_first( $attributes ) ) ) {
			return false;
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
