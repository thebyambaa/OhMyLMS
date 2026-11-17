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
	const VERSION         = 1;
	const MAX_FUNCTIONS   = 12;
	const MAX_PARAMS      = 6;
	const COLORS          = array( '#2563eb', '#dc2626', '#16a34a', '#9333ea', '#ea580c', '#0891b2' );
	const ACTIVITIES      = array( 'function-graph', 'number-line', 'fractions', 'clock', 'converter', 'chart', 'geometry' );
	const CLOCK_SNAPS     = array( 1, 5, 15 );
	const CHART_KINDS     = array( 'bar', 'pie', 'line' );
	const SHAPES          = array( 'triangle', 'quadrilateral', 'circle', 'angle' );
	const FRACTION_SHAPES = array( 'bar', 'circle' );
	/** Unit symbols per quantity; the sizes live in assets/src/blocks/interactive-activity/activities.mjs. */
	const UNITS               = array(
		'length' => array( 'mm', 'cm', 'm', 'km', 'in', 'ft', 'mi' ),
		'mass'   => array( 'mg', 'g', 'kg', 't', 'oz', 'lb' ),
		'time'   => array( 'ms', 's', 'min', 'h', 'day', 'week' ),
		'volume' => array( 'ml', 'l', 'tsp', 'cup', 'gal' ),
	);
	const DEFAULT_UNITS       = array(
		'length' => array( 'm', 'cm' ),
		'mass'   => array( 'kg', 'g' ),
		'time'   => array( 'h', 'min' ),
		'volume' => array( 'l', 'ml' ),
	);
	const DEFAULT_CHART_ITEMS = array(
		array(
			'label' => 'Red',
			'value' => 4,
		),
		array(
			'label' => 'Blue',
			'value' => 7,
		),
		array(
			'label' => 'Green',
			'value' => 3,
		),
	);

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
	 * Whether a value is a number or a numeric string, like the JavaScript contract.
	 *
	 * @param mixed $value Candidate.
	 * @return bool
	 */
	private static function numeric( $value ) {
		return is_int( $value ) || is_float( $value ) || ( is_string( $value ) && '' !== trim( $value ) && is_numeric( $value ) );
	}

	/**
	 * Whole number in range.
	 *
	 * @param mixed $value    Candidate.
	 * @param int   $fallback Used when the candidate is not numeric.
	 * @param int   $min      Lower limit.
	 * @param int   $max      Upper limit.
	 * @return int
	 */
	private static function whole( $value, $fallback, $min, $max ) {
		return (int) round( self::number( $value, $fallback, $min, $max ) );
	}

	/**
	 * One of the allowed strings.
	 *
	 * @param mixed    $value    Candidate.
	 * @param string[] $allowed  Accepted values.
	 * @param string   $fallback Used otherwise.
	 * @return string
	 */
	private static function choice( $value, array $allowed, $fallback ) {
		return is_string( $value ) && in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Plain text of at most a given length.
	 *
	 * @param mixed $value Candidate.
	 * @param int   $max   Maximum length.
	 * @return string
	 */
	private static function plain( $value, $max ) {
		return is_string( $value ) ? mb_substr( preg_replace( '/<[^>]*>/', '', $value ), 0, $max ) : '';
	}

	/**
	 * Apply the contract of an activity other than the function graph; mirrors
	 * assets/src/blocks/interactive-activity/activities.mjs (tests/fixtures/interactive-activity-contract.json).
	 *
	 * @param string $activity Activity type.
	 * @param mixed  $input    Stored configuration.
	 * @return array Safe configuration.
	 */
	public static function sanitize_activity( $activity, $input ) {
		$input = is_array( $input ) ? $input : array();
		switch ( $activity ) {
			case 'number-line':
				$min = self::number( $input['min'] ?? null, 0, -1e6, 1e6 );
				$max = self::number( $input['max'] ?? null, 10, -1e6, 1e6 );
				if ( $min >= $max ) {
					$min = 0.0;
					$max = 10.0;
				}
				$step   = min( $max - $min, self::number( $input['step'] ?? null, 1, 1e-6, 1e6 ) );
				$points = array();
				foreach ( is_array( $input['points'] ?? null ) ? $input['points'] : array() as $point ) {
					if ( self::numeric( $point ) ) {
						$points[] = self::number( $point, $min, $min, $max );
					}
				}
				$points = array_slice( $points, 0, 4 );
				return array(
					'version' => self::VERSION,
					'min'     => $min,
					'max'     => $max,
					'step'    => $step,
					'points'  => $points ? $points : array( $min ),
					'snap'    => false !== ( $input['snap'] ?? true ),
				);
			case 'fractions':
				$parts = self::whole( $input['parts'] ?? null, 4, 1, 24 );
				return array(
					'version'    => self::VERSION,
					'shape'      => self::choice( $input['shape'] ?? null, self::FRACTION_SHAPES, 'bar' ),
					'parts'      => $parts,
					'shaded'     => self::whole( $input['shaded'] ?? null, 1, 0, $parts ),
					'allowParts' => false !== ( $input['allowParts'] ?? true ),
				);
			case 'clock':
				$snap = $input['snap'] ?? null;
				return array(
					'version'     => self::VERSION,
					'hour'        => self::whole( $input['hour'] ?? null, 3, 0, 23 ),
					'minute'      => self::whole( $input['minute'] ?? null, 0, 0, 59 ),
					'snap'        => self::numeric( $snap ) && in_array( $snap + 0, self::CLOCK_SNAPS, true ) ? (int) $snap : 5,
					'showDigital' => false !== ( $input['showDigital'] ?? true ),
					'format24'    => true === ( $input['format24'] ?? false ),
				);
			case 'converter':
				$quantity = self::choice( $input['quantity'] ?? null, array_keys( self::UNITS ), 'length' );
				return array(
					'version'       => self::VERSION,
					'quantity'      => $quantity,
					'from'          => self::choice( $input['from'] ?? null, self::UNITS[ $quantity ], self::DEFAULT_UNITS[ $quantity ][0] ),
					'to'            => self::choice( $input['to'] ?? null, self::UNITS[ $quantity ], self::DEFAULT_UNITS[ $quantity ][1] ),
					'value'         => self::number( $input['value'] ?? null, 1, -1e9, 1e9 ),
					'allowQuantity' => false !== ( $input['allowQuantity'] ?? true ),
				);
			case 'chart':
				$items = array();
				foreach ( array_slice( is_array( $input['items'] ?? null ) ? $input['items'] : array(), 0, 12 ) as $item ) {
					$item    = is_array( $item ) ? $item : array();
					$items[] = array(
						'label' => self::plain( $item['label'] ?? null, 40 ),
						'value' => self::number( $item['value'] ?? null, 0, 0, 1e6 ),
					);
				}
				return array(
					'version'   => self::VERSION,
					'kind'      => self::choice( $input['kind'] ?? null, self::CHART_KINDS, 'bar' ),
					'title'     => self::plain( $input['title'] ?? null, 80 ),
					'items'     => count( $items ) >= 2 ? $items : self::DEFAULT_CHART_ITEMS,
					'allowEdit' => false !== ( $input['allowEdit'] ?? true ),
				);
			case 'geometry':
				return array(
					'version'      => self::VERSION,
					'shape'        => self::choice( $input['shape'] ?? null, self::SHAPES, 'triangle' ),
					'snap'         => false !== ( $input['snap'] ?? true ),
					'showMeasures' => false !== ( $input['showMeasures'] ?? true ),
				);
			default:
				return self::sanitize_config( $input );
		}
	}

	/**
	 * Interface text of every activity, in the site language.
	 *
	 * @return string[]
	 */
	private static function labels() {
		return array(
			'graph'         => __( 'Graph', 'ohmylms' ),
			'equations'     => __( 'Equations', 'ohmylms' ),
			'equation'      => __( 'Equation', 'ohmylms' ),
			'addFunction'   => __( 'Add function', 'ohmylms' ),
			'remove'        => __( 'Remove function', 'ohmylms' ),
			'parameters'    => __( 'Parameters', 'ohmylms' ),
			'invalid'       => __( 'Cannot graph this expression yet.', 'ohmylms' ),
			'numberLine'    => __( 'Number line', 'ohmylms' ),
			'point'         => __( 'Point', 'ohmylms' ),
			'value'         => __( 'Value', 'ohmylms' ),
			'fractions'     => __( 'Fraction', 'ohmylms' ),
			'parts'         => __( 'Equal parts', 'ohmylms' ),
			'shaded'        => __( 'Shaded', 'ohmylms' ),
			'part'          => __( 'Part', 'ohmylms' ),
			'simplified'    => __( 'Simplest form', 'ohmylms' ),
			'decimal'       => __( 'Decimal', 'ohmylms' ),
			'percent'       => __( 'Percent', 'ohmylms' ),
			'clock'         => __( 'Clock', 'ohmylms' ),
			'hours'         => __( 'Hours', 'ohmylms' ),
			'minutes'       => __( 'Minutes', 'ohmylms' ),
			'converter'     => __( 'Unit converter', 'ohmylms' ),
			'quantity'      => __( 'Quantity', 'ohmylms' ),
			'amount'        => __( 'Amount', 'ohmylms' ),
			'from'          => __( 'From', 'ohmylms' ),
			'to'            => __( 'To', 'ohmylms' ),
			'length'        => __( 'Length', 'ohmylms' ),
			'mass'          => __( 'Mass', 'ohmylms' ),
			'time'          => __( 'Time', 'ohmylms' ),
			'volume'        => __( 'Volume', 'ohmylms' ),
			'chart'         => __( 'Chart', 'ohmylms' ),
			'label'         => __( 'Label', 'ohmylms' ),
			'total'         => __( 'Total', 'ohmylms' ),
			'mean'          => __( 'Mean', 'ohmylms' ),
			'median'        => __( 'Median', 'ohmylms' ),
			'mode'          => __( 'Mode', 'ohmylms' ),
			'noMode'        => __( 'none', 'ohmylms' ),
			'range'         => __( 'Range', 'ohmylms' ),
			'geometry'      => __( 'Geometry', 'ohmylms' ),
			'side'          => __( 'Side', 'ohmylms' ),
			'perimeter'     => __( 'Perimeter', 'ohmylms' ),
			'area'          => __( 'Area', 'ohmylms' ),
			'angle'         => __( 'Angle', 'ohmylms' ),
			'radius'        => __( 'Radius', 'ohmylms' ),
			'circumference' => __( 'Circumference', 'ohmylms' ),
			'noArea'        => __( 'The sides cross, so there is no single area.', 'ohmylms' ),
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
		if ( 'function-graph' === $activity ) {
			$stored = ! empty( $stored['version'] ) ? $stored : array(
				'functions' => array(
					array(
						'id'    => 'f1',
						'latex' => 'x^2',
					),
				),
			);
		}
		$config = self::sanitize_activity( $activity, $stored );
		$labels = self::labels();
		return '<div ' . get_block_wrapper_attributes( array( 'class' => 'ohmylms-activity' ) )
			. ' data-ohmylms-activity="' . esc_attr( $activity ) . '"'
			. ' data-config="' . esc_attr( wp_json_encode( $config ) ) . '"'
			. ' data-labels="' . esc_attr( wp_json_encode( $labels ) ) . '"'
			. ' data-fonts="' . esc_url( plugins_url( 'assets/dist/mathlive/fonts', OHMYLMS_FILE ) ) . '">'
			. '<noscript>' . esc_html__( 'This interactive activity needs JavaScript.', 'ohmylms' ) . '</noscript></div>';
	}
}
