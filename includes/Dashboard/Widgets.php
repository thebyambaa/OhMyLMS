<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Registry of dashboard widget endpoints.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

use OhMyLMS\Dashboard\Widgets\NeedsAttention;
use OhMyLMS\Dashboard\Widgets\Pulse;

defined( 'ABSPATH' ) || exit;

/**
 * Widgets served under `/dashboard/widgets/<id>`: the built-in ones plus those added with the
 * `ohmylms_dashboard_widgets` filter (id => {@see Widget} instance or class name).
 */
final class Widgets {

	/**
	 * Valid widget id.
	 */
	const ID_PATTERN = '[a-z][a-z0-9-]{0,40}';

	/**
	 * Resolved widgets.
	 *
	 * @var array<string,Widget>|null
	 */
	private static $widgets = null;

	/**
	 * Forget the resolved list (tests, after adding a filter).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$widgets = null;
	}

	/**
	 * Every widget by id.
	 *
	 * @return array<string,Widget>
	 */
	public static function all() {
		if ( null !== self::$widgets ) {
			return self::$widgets;
		}
		$built_in = array(
			'needs-attention' => NeedsAttention::class,
			'pulse'           => Pulse::class,
		);
		/**
		 * Filters the dashboard widget endpoints. Add `'my-widget' => My_Widget::class` (a subclass of
		 * `OhMyLMS\Dashboard\Widget` whose id() is the key).
		 *
		 * @param array<string,string|Widget> $widgets Widget id => class name or instance.
		 */
		$listed  = (array) apply_filters( 'ohmylms_dashboard_widgets', $built_in );
		$widgets = array();
		foreach ( $listed as $id => $widget ) {
			if ( is_string( $widget ) && class_exists( $widget ) ) {
				$widget = new $widget();
			}
			if ( $widget instanceof Widget && $widget->id() === $id && preg_match( '/^' . self::ID_PATTERN . '$/', (string) $id ) ) {
				$widgets[ $id ] = $widget;
			}
		}
		self::$widgets = $widgets;
		return $widgets;
	}

	/**
	 * One widget.
	 *
	 * @param string $id Widget id.
	 * @return Widget|null
	 */
	public static function get( $id ) {
		return self::all()[ (string) $id ] ?? null;
	}
}
