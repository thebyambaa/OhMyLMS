<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Visual / manipulative question types. Each one answers with a few numbers that the
 * learner sets by dragging or tapping; the key and tolerances never leave the server.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

defined( 'ABSPATH' ) || exit;

/**
 * Visual / manipulative question types. Each one answers with a few numbers that the
 * learner sets by dragging or tapping; the key and tolerances never leave the server.
 *
 * number-line   min, max, step, snap, target, tolerance        answer { value }
 * shade-model   shape bar|grid|circle, parts, cols, answer     answer { c0, c3, ... } (shaded cells)
 * count-blocks  places, max_per_place, target, canonical       answer { hundreds, tens, ones }
 * set-clock     hour, minute, tolerance, snap                  answer { h, m }
 * make-amount   denominations, symbol, target                  answer { d1000: 2, d500: 1 }
 * fill-level    min, max, step, unit, target, tolerance        answer { value }
 * build-chart   categories, max, step, values, show_table      answer { <category id>: n }
 * grid-build    rows, cols, constraints                        answer { r0c1, r2c3, ... } (chosen squares)
 */
final class Visual {
	const TYPES = array( 'number-line', 'shade-model', 'count-blocks', 'set-clock', 'make-amount', 'fill-level', 'build-chart', 'grid-build' );

	const PLACES = array(
		'thousands' => 1000,
		'hundreds'  => 100,
		'tens'      => 10,
		'ones'      => 1,
	);

	/** Settings keys a learner must never receive. */
	const PRIVATE_KEYS = array(
		'number-line'  => array( 'target', 'tolerance' ),
		'shade-model'  => array( 'answer' ),
		'count-blocks' => array( 'target', 'canonical' ),
		'set-clock'    => array( 'hour', 'minute', 'tolerance' ),
		'make-amount'  => array( 'target' ),
		'fill-level'   => array( 'target', 'tolerance' ),
		'build-chart'  => array( 'values' ),
		'grid-build'   => array( 'constraints' ),
	);

	/**
	 * Handles for Visual.
	 *
	 * @param mixed $type Type.
	 */
	public static function handles( $type ) {
		return in_array( $type, self::TYPES, true );
	}

	/**
	 * Private keys for Visual.
	 *
	 * @param mixed $type Type.
	 */
	public static function private_keys( $type ) {
		return self::PRIVATE_KEYS[ $type ] ?? array();
	}

	/** Does the settings array still hold the key of this type?
	 *
	 * @param mixed $type Type.
	 * @param array $settings Configuration settings.
	 */
	public static function has_key( $type, array $settings ) {
		foreach ( self::private_keys( $type ) as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				return true; }
		}
		return false;
	}

	/**
	 * Num for Visual.
	 *
	 * @param mixed $value Value to process.
	 * @param mixed $default Default.
	 */
	private static function num( $value, $default = 0.0 ) {
		return is_numeric( $value ) && is_finite( (float) $value ) ? (float) $value : (float) $default;
	}

	/** An answer value as a finite number; fractions such as 3/4 are accepted.
	 *
	 * @param array  $answer Answer.
	 * @param string $key Lookup key.
	 */
	private static function value( array $answer, $key = 'value' ) {
		return NumericAnswer::parse( $answer[ $key ] ?? '' );
	}

	/** Cell indices of a shade-model answer: keys c0, c3, ... within range.
	 *
	 * @param array $answer Answer.
	 * @param mixed $parts Parts.
	 */
	private static function cells( array $answer, $parts ) {
		$cells = array();
		foreach ( $answer as $key => $on ) {
			if ( preg_match( '/^c(\d{1,3})$/', (string) $key, $m ) && (int) $m[1] < $parts && ! in_array( (string) $on, array( '', '0' ), true ) ) {
				$cells[ (int) $m[1] ] = true; }
		}
		return array_keys( $cells );
	}

	/** Grid squares of a grid-build answer: keys r2c3 within the grid.
	 *
	 * @param array $answer Answer.
	 * @param mixed $rows Rows.
	 * @param mixed $cols Cols.
	 */
	private static function squares( array $answer, $rows, $cols ) {
		$squares = array();
		foreach ( $answer as $key => $on ) {
			if ( preg_match( '/^r(\d{1,2})c(\d{1,2})$/', (string) $key, $m ) && (int) $m[1] < $rows && (int) $m[2] < $cols && ! in_array( (string) $on, array( '', '0' ), true ) ) {
				$squares[ $m[1] . ',' . $m[2] ] = array( (int) $m[1], (int) $m[2] ); }
		}
		return array_values( $squares );
	}

	/* ---------- Learner-safe settings ---------- */

	/**
	 * Public view for Visual.
	 *
	 * @param mixed $type Type.
	 * @param array $s S.
	 */
	public static function public_view( $type, array $s ) {
		switch ( $type ) {
			case 'number-line':
				$step = max( 0.000001, self::num( $s['step'] ?? 1, 1 ) );
				return array(
					'min'  => self::num( $s['min'] ?? 0 ),
					'max'  => self::num( $s['max'] ?? 10, 10 ),
					'step' => $step,
					'snap' => max( 0.000001, self::num( $s['snap'] ?? $step, $step ) ),
				);
			case 'shade-model':
				$shape = in_array( $s['shape'] ?? '', array( 'bar', 'grid', 'circle' ), true ) ? $s['shape'] : 'bar';
				$parts = max( 1, min( 100, (int) ( $s['parts'] ?? 8 ) ) );
				return array(
					'shape' => $shape,
					'parts' => $parts,
					'cols'  => max( 1, min( $parts, (int) ( $s['cols'] ?? min( $parts, 10 ) ) ) ),
				);
			case 'count-blocks':
				$places = array_values( array_intersect( array_keys( self::PLACES ), (array) ( $s['places'] ?? array( 'hundreds', 'tens', 'ones' ) ) ) );
				return array(
					'places'        => $places ? $places : array( 'hundreds', 'tens', 'ones' ),
					'max_per_place' => max( 1, min( 50, (int) ( $s['max_per_place'] ?? 20 ) ) ),
				);
			case 'set-clock':
				$snap = (int) ( $s['snap'] ?? 1 );
				return array(
					'snap'         => in_array( $snap, array( 1, 5, 15 ), true ) ? $snap : 1,
					'show_digital' => ! empty( $s['show_digital'] ),
				);
			case 'make-amount':
				$denominations = array_values(
					array_unique(
						array_filter(
							array_map( 'intval', (array) ( $s['denominations'] ?? array() ) ),
							static function ( $d ) {
								return 0 < $d;
							}
						)
					)
				);
				rsort( $denominations );
				return array(
					'denominations' => $denominations,
					'symbol'        => (string) ( $s['symbol'] ?? '₮' ),
				);
			case 'fill-level':
				$step = max( 0.000001, self::num( $s['step'] ?? 50, 50 ) );
				return array(
					'min'  => self::num( $s['min'] ?? 0 ),
					'max'  => self::num( $s['max'] ?? 1000, 1000 ),
					'step' => $step,
					'unit' => (string) ( $s['unit'] ?? 'ml' ),
				);
			case 'build-chart':
				$categories = array();
				foreach ( (array) ( $s['categories'] ?? array() ) as $category ) {
					$categories[] = array(
						'id'    => preg_replace( '/[^a-z0-9_-]/i', '', (string) ( $category['id'] ?? '' ) ),
						'label' => (string) ( $category['label'] ?? '' ),
					); }
				$view = array(
					'categories' => $categories,
					'max'        => self::num( $s['max'] ?? 10, 10 ),
					'step'       => max( 0.000001, self::num( $s['step'] ?? 1, 1 ) ),
					'unit'       => (string) ( $s['unit'] ?? '' ),
				);
				// The author may show the data table that the learner has to turn into bars.
				if ( ! empty( $s['show_table'] ) ) {
					$view['table'] = array_map(
						static function ( $category ) use ( $s ) {
							return array(
								'label' => $category['label'],
								'value' => self::num( $s['values'][ $category['id'] ] ?? 0 ),
							); },
						$categories
					); }
				return $view;
			case 'grid-build':
				return array(
					'rows'          => max( 1, min( 20, (int) ( $s['rows'] ?? 6 ) ) ),
					'cols'          => max( 1, min( 20, (int) ( $s['cols'] ?? 6 ) ) ),
					'show_measures' => ! empty( $s['show_measures'] ),
				);
		}
		return array();
	}

	/* ---------- Grading ---------- */

	/**
	 * Grade for Visual.
	 *
	 * @return array{correct:bool,fraction:float,manual:bool,items:bool[]}
	 * @param mixed $type Type.
	 * @param array $answer Answer.
	 * @param array $s S.
	 */
	public static function grade( $type, array $answer, array $s ) {
		$items = array( false );
		switch ( $type ) {
			case 'number-line':
			case 'fill-level':
				$step      = max( 0.000001, self::num( $s['step'] ?? 1, 1 ) );
				$tolerance = isset( $s['tolerance'] ) ? max( 0.0, self::num( $s['tolerance'] ) ) : ( 'number-line' === $type ? $step / 2 : $step );
				$items     = array( NumericAnswer::matches( self::value( $answer ), self::num( $s['target'] ?? null, NAN ), $tolerance ) );
				break;
			case 'shade-model':
				$parts = max( 1, (int) ( $s['parts'] ?? 8 ) );
				$items = array( count( self::cells( $answer, $parts ) ) === (int) ( $s['answer'] ?? -1 ) );
				break;
			case 'count-blocks':
				$max   = max( 1, (int) ( $s['max_per_place'] ?? 20 ) );
				$total = 0;
				$valid = true;
				foreach ( self::public_view( $type, $s )['places'] as $place ) {
					$count = (string) ( $answer[ $place ] ?? '0' );
					if ( ! preg_match( '/^\d{1,3}$/', $count ) || (int) $count > $max || ( ! empty( $s['canonical'] ) && (int) $count > 9 ) ) {
						$valid = false;
						break; }
					$total += (int) $count * self::PLACES[ $place ];
				}
				$items = array( $valid && (int) ( $s['target'] ?? -1 ) === $total );
				break;
			case 'set-clock':
				$hour      = $answer['h'] ?? '';
				$minute    = $answer['m'] ?? '';
				$tolerance = max( 0, (int) ( $s['tolerance'] ?? 1 ) );
				if ( is_numeric( $hour ) && is_numeric( $minute ) && (int) $hour >= 0 && (int) $hour <= 12 && (int) $minute >= 0 && (int) $minute <= 59 ) {
					$given  = ( (int) $hour % 12 ) * 60 + (int) $minute;
					$target = ( (int) ( $s['hour'] ?? -1 ) % 12 ) * 60 + (int) ( $s['minute'] ?? 0 );
					$gap    = abs( $given - $target ) % 720;
					$items  = array( min( $gap, 720 - $gap ) <= $tolerance );
				}
				break;
			case 'make-amount':
				$allowed = self::public_view( $type, $s )['denominations'];
				$total   = 0;
				$valid   = true;
				foreach ( $answer as $key => $count ) {
					if ( ! preg_match( '/^d(\d{1,7})$/', (string) $key, $m ) || ! in_array( (int) $m[1], $allowed, true ) || ! is_numeric( $count ) || (int) $count < 0 || (int) $count > 99 ) {
						$valid = false;
						break; }
					$total += (int) $m[1] * (int) $count;
				}
				$items = array( $valid && (int) ( $s['target'] ?? -1 ) === $total );
				break;
			case 'build-chart':
				$items = array();
				foreach ( self::public_view( $type, $s )['categories'] as $category ) {
					$given                    = NumericAnswer::parse( $answer[ $category['id'] ] ?? '' );
					$items[ $category['id'] ] = NumericAnswer::matches( $given, self::num( $s['values'][ $category['id'] ] ?? null, NAN ), 0.0 );
				}
				$items = $items ? $items : array( false );
				break;
			case 'grid-build':
				$view    = self::public_view( $type, $s );
				$squares = self::squares( $answer, $view['rows'], $view['cols'] );
				$items   = array( self::grid_ok( $squares, (array) ( $s['constraints'] ?? array() ) ) );
				break;
		}
		$all = count( $items ) > 0 && count( array_filter( $items ) ) === count( $items );
		return array(
			'correct'  => $all,
			'fraction' => $all ? 1.0 : 0.0,
			'manual'   => false,
			'items'    => $items,
		);
	}

	/** Area, perimeter, rectangle and connectedness of the chosen squares.
	 *
	 * @param array $squares Squares.
	 * @param array $c C.
	 */
	private static function grid_ok( array $squares, array $c ) {
		if ( ! $squares ) {
			return false; }
		$set = array();
		foreach ( $squares as $q ) {
			$set[ $q[0] . ',' . $q[1] ] = true; }
		$perimeter = 0;
		foreach ( $squares as $q ) {
			foreach ( array( array( -1, 0 ), array( 1, 0 ), array( 0, -1 ), array( 0, 1 ) ) as $d ) {
				if ( empty( $set[ ( $q[0] + $d[0] ) . ',' . ( $q[1] + $d[1] ) ] ) ) {
					++$perimeter; }
			}
		}
		if ( isset( $c['area'] ) && count( $squares ) !== (int) $c['area'] ) {
			return false; }
		if ( isset( $c['perimeter'] ) && $perimeter !== (int) $c['perimeter'] ) {
			return false; }
		if ( ! empty( $c['rectangle'] ) ) {
			$rows = array_column( $squares, 0 );
			$cols = array_column( $squares, 1 );
			if ( ( max( $rows ) - min( $rows ) + 1 ) * ( max( $cols ) - min( $cols ) + 1 ) !== count( $squares ) ) {
				return false; }
		}
		if ( ! empty( $c['connected'] ) ) {
			$seen  = array();
			$queue = array( $squares[0] );
			$seen[ $squares[0][0] . ',' . $squares[0][1] ] = true;
			while ( $queue ) {
				$q = array_shift( $queue );
				foreach ( array( array( -1, 0 ), array( 1, 0 ), array( 0, -1 ), array( 0, 1 ) ) as $d ) {
					$k = ( $q[0] + $d[0] ) . ',' . ( $q[1] + $d[1] );
					if ( ! empty( $set[ $k ] ) && empty( $seen[ $k ] ) ) {
						$seen[ $k ] = true;
						$queue[]    = array( $q[0] + $d[0], $q[1] + $d[1] ); }
				}
			}
			if ( count( $seen ) !== count( $squares ) ) {
				return false; }
		}
		return true;
	}

	/* ---------- Authoring validation ---------- */

	/** True or a message.
	 *
	 * @param mixed $type Type.
	 * @param array $s S.
	 */
	public static function validate_settings( $type, array $s ) {
		switch ( $type ) {
			case 'number-line':
			case 'fill-level':
				$min  = self::num( $s['min'] ?? 0 );
				$max  = self::num( $s['max'] ?? null, NAN );
				$step = self::num( $s['step'] ?? null, 0 );
				if ( ! is_finite( $max ) || $max <= $min ) {
					return __( 'The maximum must be greater than the minimum.', 'ohmylms' ); }
				if ( 0 >= $step || ( $max - $min ) / $step > 200 ) {
					return __( 'The step must be positive and give at most 200 ticks.', 'ohmylms' ); }
				if ( ! isset( $s['target'] ) || ! is_numeric( $s['target'] ) || (float) $s['target'] < $min || (float) $s['target'] > $max ) {
					return __( 'The correct value must be between the minimum and maximum.', 'ohmylms' ); }
				if ( isset( $s['tolerance'] ) && ( ! is_numeric( $s['tolerance'] ) || (float) $s['tolerance'] < 0 ) ) {
					return __( 'Tolerance must be zero or more.', 'ohmylms' ); }
				return true;
			case 'shade-model':
				$parts = (int) ( $s['parts'] ?? 0 );
				if ( 2 > $parts || 100 < $parts ) {
					return __( 'A model needs between 2 and 100 parts.', 'ohmylms' ); }
				if ( ! isset( $s['answer'] ) || ! is_numeric( $s['answer'] ) || (int) $s['answer'] < 0 || (int) $s['answer'] > $parts ) {
					return __( 'The number of shaded parts must be between 0 and the number of parts.', 'ohmylms' ); }
				return true;
			case 'count-blocks':
				$places = array_values( array_intersect( array_keys( self::PLACES ), (array) ( $s['places'] ?? array() ) ) );
				if ( ! $places ) {
					return __( 'Choose at least one place value.', 'ohmylms' ); }
				$target = $s['target'] ?? null;
				if ( ! is_numeric( $target ) || (int) $target < 1 || (int) $target > 99999 ) {
					return __( 'Enter a whole number to build.', 'ohmylms' ); }
				$smallest = min(
					array_map(
						static function ( $p ) {
							return self::PLACES[ $p ];
						},
						$places
					)
				);
				if ( 0 !== (int) $target % $smallest ) {
					return __( 'The number cannot be made from the chosen places.', 'ohmylms' ); }
				return true;
			case 'set-clock':
				if ( ! isset( $s['hour'], $s['minute'] ) || (int) $s['hour'] < 1 || (int) $s['hour'] > 12 || (int) $s['minute'] < 0 || (int) $s['minute'] > 59 ) {
					return __( 'Enter an hour from 1 to 12 and minutes from 0 to 59.', 'ohmylms' ); }
				return true;
			case 'make-amount':
				$view = self::public_view( $type, $s );
				if ( count( $view['denominations'] ) < 2 ) {
					return __( 'Add at least two bills or coins.', 'ohmylms' ); }
				$target = $s['target'] ?? null;
				if ( ! is_numeric( $target ) || (int) $target < 1 ) {
					return __( 'Enter the amount to make.', 'ohmylms' ); }
				if ( (int) $target % min( $view['denominations'] ) !== 0 ) {
					return __( 'The amount cannot be made from the chosen bills and coins.', 'ohmylms' ); }
				return true;
			case 'build-chart':
				$categories = self::public_view( $type, $s )['categories'];
				$ids        = array_column( $categories, 'id' );
				if ( ! $categories || count( array_unique( $ids ) ) !== count( $ids ) || in_array( '', $ids, true ) || in_array( '', array_column( $categories, 'label' ), true ) ) {
					return __( 'Each bar needs a unique ID and a label.', 'ohmylms' ); }
				$max = self::num( $s['max'] ?? null, NAN );
				if ( ! is_finite( $max ) || 0 >= $max || self::num( $s['step'] ?? 1, 1 ) <= 0 ) {
					return __( 'The chart needs a positive maximum and step.', 'ohmylms' ); }
				foreach ( $ids as $id ) {
					$v = $s['values'][ $id ] ?? null;
					if ( ! is_numeric( $v ) || (float) $v < 0 || (float) $v > $max ) {
						return __( 'Every bar needs a value between 0 and the maximum.', 'ohmylms' ); }
				}
				return true;
			case 'grid-build':
				$rows = (int) ( $s['rows'] ?? 0 );
				$cols = (int) ( $s['cols'] ?? 0 );
				if ( 1 > $rows || 20 < $rows || 1 > $cols || 20 < $cols ) {
					return __( 'The grid can be 1 to 20 squares in each direction.', 'ohmylms' ); }
				$c = (array) ( $s['constraints'] ?? array() );
				if ( ! isset( $c['area'] ) && ! isset( $c['perimeter'] ) && empty( $c['rectangle'] ) && empty( $c['connected'] ) ) {
					return __( 'Add at least one condition, such as an area.', 'ohmylms' ); }
				foreach ( array( 'area', 'perimeter' ) as $name ) {
					if ( isset( $c[ $name ] ) && ( ! is_numeric( $c[ $name ] ) || (int) $c[ $name ] < 1 ) ) {
						return __( 'Area and perimeter must be positive whole numbers.', 'ohmylms' ); }
				}
				if ( isset( $c['area'] ) && (int) $c['area'] > $rows * $cols ) {
					return __( 'The area does not fit on the grid.', 'ohmylms' ); }
				return true;
		}
		return true;
	}

	/** Readable correct answer, revealed after a response has been graded.
	 *
	 * @param mixed $type Type.
	 * @param array $s S.
	 */
	public static function expected( $type, array $s ) {
		switch ( $type ) {
			case 'number-line':
			case 'fill-level':
				return array( (string) ( $s['target'] ?? '' ) . ( ! empty( $s['unit'] ) ? ' ' . $s['unit'] : '' ) );
			case 'shade-model':
				return array( sprintf( '%d / %d', (int) ( $s['answer'] ?? 0 ), (int) ( $s['parts'] ?? 0 ) ) );
			case 'count-blocks':
				return array( (string) ( $s['target'] ?? '' ) );
			case 'set-clock':
				return array( sprintf( '%d:%02d', (int) ( $s['hour'] ?? 0 ), (int) ( $s['minute'] ?? 0 ) ) );
			case 'make-amount':
				return array( (string) ( $s['target'] ?? '' ) . ' ' . (string) ( $s['symbol'] ?? '' ) );
			case 'build-chart':
				$out = array();
				foreach ( self::public_view( $type, array_merge( $s, array( 'show_table' => false ) ) )['categories'] as $category ) {
					$out[] = $category['label'] . ' = ' . ( $s['values'][ $category['id'] ] ?? '' ); }
				return $out;
			case 'grid-build':
				$c   = (array) ( $s['constraints'] ?? array() );
				$out = array();
				foreach ( array( 'area', 'perimeter' ) as $name ) {
					if ( isset( $c[ $name ] ) ) {
						$out[] = $name . ' = ' . (int) $c[ $name ]; }
				}
				if ( ! empty( $c['rectangle'] ) ) {
					$out[] = 'rectangle'; }
				if ( ! empty( $c['connected'] ) ) {
					$out[] = 'connected'; }
				return $out;
		}
		return array();
	}
}
