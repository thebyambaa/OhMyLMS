<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Interactive try-and-learn activities for the block editor.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Blocks;

defined( 'ABSPATH' ) || exit;

/** Server-rendered host for client-side activities; learners' experiments are never stored. */
final class InteractiveActivityBlock {
	const VERSION       = 1;
	const MAX_FUNCTIONS = 12;
	const MAX_PARAMS    = 6;
	const COLORS        = array( '#2563eb', '#dc2626', '#16a34a', '#9333ea', '#ea580c', '#0891b2' );
	const ACTIVITIES    = array( 'function-graph' );

	/** Register the block. */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/** Register the built editor and view scripts, then the block. */
	public static function register() {
		$editor = OHMYLMS_DIR . '/assets/dist/admin/interactive-activity-block.asset.php';
		$view   = OHMYLMS_DIR . '/assets/dist/admin/interactive-activity-view.asset.php';
		if ( ! is_file( $editor ) || ! is_file( $view ) ) {
			return;
		}
		$config = array( 'fonts' => plugins_url( 'assets/dist/mathlive/fonts', OHMYLMS_FILE ) );
		\OhMyLMS\Assessment\Interactive::register_jsxgraph();
		foreach ( array(
			'ohmylms-interactive-activity-block' => array( 'interactive-activity-block', $editor ),
			'ohmylms-interactive-activity-view'  => array( 'interactive-activity-view', $view ),
		) as $handle => $script ) {
			$asset = require $script[1];
			wp_register_script( $handle, plugins_url( 'assets/dist/admin/' . $script[0] . '.js', OHMYLMS_FILE ), $asset['dependencies'], $asset['version'], true );
		}
		wp_add_inline_script( 'ohmylms-interactive-activity-block', 'window.ohmylmsActivity = ' . wp_json_encode( $config ) . ';', 'before' );
		wp_register_style( 'ohmylms-interactive-activity', plugins_url( 'assets/css/interactive-activity.css', OHMYLMS_FILE ), array( 'ohmylms-jsxgraph' ), (string) filemtime( OHMYLMS_DIR . '/assets/css/interactive-activity.css' ) );
		register_block_type( OHMYLMS_DIR . '/assets/src/blocks/interactive-activity', array( 'render_callback' => array( __CLASS__, 'render' ) ) );
	}

	/**
	 * Clamp a value to a finite number in range.
	 *
	 * @param mixed $value    Candidate.
	 * @param float $fallback Used when the candidate is not numeric.
	 * @param float $min      Lower limit.
	 * @param float $max      Upper limit.
	 * @return float
	 */
	private static function number( $value, $fallback, $min, $max ) {
		return is_numeric( $value ) ? (float) min( $max, max( $min, (float) $value ) ) : (float) $fallback;
	}

	/**
	 * Apply the activity contract to saved attributes; mirrors assets/src/blocks/interactive-activity/config.mjs.
	 *
	 * @param mixed $input Stored configuration.
	 * @return array Safe configuration.
	 */
	public static function sanitize_config( $input ) {
		$input = is_array( $input ) ? $input : array();
		$view  = is_array( $input['view'] ?? null ) ? $input['view'] : array();
		$x_min = self::number( $view['xMin'] ?? null, -10, -1e6, 1e6 );
		$x_max = self::number( $view['xMax'] ?? null, 10, -1e6, 1e6 );
		$y_min = self::number( $view['yMin'] ?? null, -7, -1e6, 1e6 );
		$y_max = self::number( $view['yMax'] ?? null, 7, -1e6, 1e6 );
		if ( $x_min >= $x_max ) {
			$x_min = -10.0;
			$x_max = 10.0;
		}
		if ( $y_min >= $y_max ) {
			$y_min = -7.0;
			$y_max = 7.0;
		}
		$params = array();
		$seen   = array();
		foreach ( is_array( $input['params'] ?? null ) ? $input['params'] : array() as $param ) {
			$name = is_array( $param ) && is_string( $param['name'] ?? null ) ? $param['name'] : '';
			if ( ! preg_match( '/^[a-df-wyzA-Z]$/', $name ) || isset( $seen[ $name ] ) || count( $params ) >= self::MAX_PARAMS ) {
				continue;
			}
			$seen[ $name ] = true;
			$min           = self::number( $param['min'] ?? null, -5, -1e6, 1e6 );
			$max           = self::number( $param['max'] ?? null, 5, -1e6, 1e6 );
			if ( $min >= $max ) {
				$min = -5.0;
				$max = 5.0;
			}
			$params[] = array(
				'name'  => $name,
				'min'   => $min,
				'max'   => $max,
				'step'  => self::number( $param['step'] ?? null, 0.1, 1e-6, 1e6 ),
				'value' => self::number( $param['value'] ?? null, 1, $min, $max ),
			);
		}
		$functions = array();
		foreach ( is_array( $input['functions'] ?? null ) ? $input['functions'] : array() as $index => $item ) {
			if ( count( $functions ) >= self::MAX_FUNCTIONS ) {
				break;
			}
			$item        = is_array( $item ) ? $item : array();
			$id          = is_string( $item['id'] ?? null ) && preg_match( '/^[\w-]{1,24}$/', $item['id'] ) ? $item['id'] : 'f' . ( count( $functions ) + 1 );
			$color       = is_string( $item['color'] ?? null ) ? sanitize_hex_color( $item['color'] ) : '';
			$color       = $color && 7 === strlen( $color ) ? $color : self::COLORS[ $index % count( self::COLORS ) ];
			$functions[] = array(
				'id'    => $id,
				'latex' => is_string( $item['latex'] ?? null ) ? substr( wp_strip_all_tags( $item['latex'] ), 0, 500 ) : '',
				'color' => $color,
			);
		}
		return array(
			'version'   => self::VERSION,
			'view'      => array(
				'xMin' => $x_min,
				'xMax' => $x_max,
				'yMin' => $y_min,
				'yMax' => $y_max,
			),
			'params'    => $params,
			'functions' => $functions,
			'allowAdd'  => false !== ( $input['allowAdd'] ?? true ),
			'allowEdit' => false !== ( $input['allowEdit'] ?? true ),
		);
	}

	/**
	 * Render the host element; the view script mounts the activity from its data attributes.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Block markup.
	 */
	public static function render( $attributes ) {
		$activity = in_array( $attributes['activity'] ?? '', self::ACTIVITIES, true ) ? $attributes['activity'] : 'function-graph';
		$stored   = $attributes['config'] ?? array();
		$config   = self::sanitize_config(
			! empty( $stored['version'] ) ? $stored : array(
				'functions' => array(
					array(
						'id'    => 'f1',
						'latex' => 'x^2',
					),
				),
			)
		);
		$labels   = array(
			'graph'       => __( 'Graph', 'ohmylms' ),
			'equations'   => __( 'Equations', 'ohmylms' ),
			'equation'    => __( 'Equation', 'ohmylms' ),
			'addFunction' => __( 'Add function', 'ohmylms' ),
			'remove'      => __( 'Remove function', 'ohmylms' ),
			'parameters'  => __( 'Parameters', 'ohmylms' ),
			'invalid'     => __( 'Cannot graph this expression yet.', 'ohmylms' ),
		);
		return '<div ' . get_block_wrapper_attributes( array( 'class' => 'ohmylms-activity' ) )
			. ' data-ohmylms-activity="' . esc_attr( $activity ) . '"'
			. ' data-config="' . esc_attr( wp_json_encode( $config ) ) . '"'
			. ' data-labels="' . esc_attr( wp_json_encode( $labels ) ) . '"'
			. ' data-fonts="' . esc_url( plugins_url( 'assets/dist/mathlive/fonts', OHMYLMS_FILE ) ) . '">'
			. '<noscript>' . esc_html__( 'This interactive activity needs JavaScript.', 'ohmylms' ) . '</noscript></div>';
	}
}
