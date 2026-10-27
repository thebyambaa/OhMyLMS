<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Extensions;

/**
 * Plugin slots implementation.
 */
final class Slots {
	/**
	 * Init for Slots.
	 */
	public static function init() {
		$hooks = array(
			'ohmylms_before_single_course_content' => 'student.course.before',
			'ohmylms_after_single_course_content'  => 'student.course.after',
			'ohmylms_checkout_after_billing'       => 'checkout.fields.after',
			'ohmylms_checkout_after_order_review'  => 'checkout.summary.after',
		);
		foreach ( $hooks as $hook => $slot ) {
			add_action(
				$hook,
				static function () use ( $slot ) {
					self::render( $slot, array( 'contentId' => get_the_ID() ) );
				}
			);
		}
	}
	/**
	 * Render for Slots.
	 *
	 * @param mixed $name Name.
	 * @param array $context Context.
	 */
	public static function render( $name, array $context = array() ) {
		do_action( 'ohmylms_render_slot', $name, $context );
		if ( ! defined( 'OHMYLMS_SOURCE_ASSETS' ) || ! OHMYLMS_SOURCE_ASSETS ) {
			return; }
		printf( '<div data-ohmylms-slot="%s" data-ohmylms-context="%s"></div>', esc_attr( $name ), esc_attr( wp_json_encode( $context ) ) );
		if ( strpos( $name, 'checkout.' ) === 0 ) {
			printf( '<div data-ohmylms-slot="%s" data-ohmylms-kind="checkout-field" data-ohmylms-context="%s"></div>', esc_attr( $name ), esc_attr( wp_json_encode( $context ) ) );
		}
	}
}
