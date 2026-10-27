<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 mapping.
/**
 * Declarative, executable-code-free figure contract.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\ExamImport;

defined( 'ABSPATH' ) || exit;

/** Only these objects and bounded expression trees may reach JSXGraph. */
final class FigureSpec {

	/**
	 * Validate and canonicalize a version-one figure.
	 *
	 * @param mixed $value Candidate specification.
	 * @return array|\WP_Error Safe specification.
	 */
	public static function validate( $value ) {
		if ( ! is_array( $value ) || 1 !== ( $value['version'] ?? null ) || array_diff( array_keys( $value ), array( 'version', 'bounds', 'objects' ) ) || ! self::numbers( $value['bounds'] ?? null, 4 ) || ! is_array( $value['objects'] ?? null ) || ! $value['objects'] || count( $value['objects'] ) > 100 || array_values( $value['objects'] ) !== $value['objects'] ) {
			return self::error();
		}
		$bounds = $value['bounds'];
		if ( $bounds[0] >= $bounds[2] || $bounds[1] <= $bounds[3] ) {
			return self::error();
		}
		$objects           = array();
		$points            = array();
		$ids               = array();
		$expression_budget = 200;
		$functions         = 0;
		$axes              = 0;
		foreach ( $value['objects'] as $object ) {
			if ( ! is_array( $object ) || ! is_string( $object['id'] ?? null ) || ! preg_match( '/^[a-zA-Z][a-zA-Z0-9_-]{0,39}$/D', $object['id'] ) || isset( $ids[ $object['id'] ] ) ) {
				return self::error();
			}
			$type    = $object['type'] ?? '';
			$allowed = array( 'id', 'type', 'label', 'color', 'dashed' );
			$clean   = array(
				'id'   => $object['id'],
				'type' => $type,
			);
			switch ( $type ) {
				case 'point':
					$allowed = array_merge( $allowed, array( 'x', 'y', 'marker' ) );
					if ( isset( $object['marker'] ) && ! is_bool( $object['marker'] ) ) {
						return self::error();
					}
					$clean['marker'] = $object['marker'] ?? false;
					if ( ! self::numbers( array( $object['x'] ?? null, $object['y'] ?? null ), 2 ) ) {
						return self::error();
					}
					$clean['x']              = $object['x'];
					$clean['y']              = $object['y'];
					$points[ $object['id'] ] = true;
					break;
				case 'segment':
				case 'polygon':
				case 'angle':
					$allowed[]  = 'points';
					$references = $object['points'] ?? null;
					$count      = is_array( $references ) ? count( $references ) : 0;
					$expected   = 'segment' === $type ? 2 : 3;
					if ( ! $count || array_values( $references ) !== $references || ( 'polygon' === $type ? ( $count < 3 || $count > 20 ) : $count !== $expected ) || count( array_filter( $references, 'is_string' ) ) !== $count || count( array_unique( $references ) ) !== $count ) {
						return self::error();
					}
					foreach ( $references as $reference ) {
						if ( ! isset( $points[ $reference ] ) ) {
							return self::error();
						}
					}
					$clean['points'] = $references;
					break;
				case 'circle':
					$allowed = array_merge( $allowed, array( 'center', 'radius' ) );
					if ( ! is_string( $object['center'] ?? null ) || ! isset( $points[ $object['center'] ] ) || ! self::numbers( array( $object['radius'] ?? null ), 1 ) || $object['radius'] <= 0 ) {
						return self::error();
					}
					$clean['center'] = $object['center'];
					$clean['radius'] = $object['radius'];
					break;
				case 'axes':
					if ( ++$axes > 1 ) {
						return self::error();
					}
					break;
				case 'function':
					$allowed[] = 'expression';
					if ( ++$functions > 8 || ! self::expression( $object['expression'] ?? null, 0, $expression_budget ) ) {
						return self::error();
					}
					$clean['expression'] = $object['expression'];
					break;
				default:
					return self::error();
			}
			if ( array_diff( array_keys( $object ), $allowed ) || ! self::text( $object['label'] ?? '' ) || ( isset( $object['color'] ) && ( ! is_string( $object['color'] ) || ! preg_match( '/^#[0-9a-f]{6}$/iD', $object['color'] ) ) ) || ( isset( $object['dashed'] ) && ! is_bool( $object['dashed'] ) ) ) {
				return self::error();
			}
			$clean['label']       = $object['label'] ?? '';
			$clean['color']       = $object['color'] ?? '#202020';
			$clean['dashed']      = $object['dashed'] ?? false;
			$ids[ $object['id'] ] = true;
			$objects[]            = $clean;
		}
		return array(
			'version' => 1,
			'bounds'  => $bounds,
			'objects' => $objects,
		);
	}

	/**
	 * Validate a finite bounded number tuple.
	 *
	 * @param mixed $values Tuple.
	 * @param int   $count Expected count.
	 * @return bool Valid.
	 */
	private static function numbers( $values, $count ) {
		if ( ! is_array( $values ) || count( $values ) !== $count || array_values( $values ) !== $values ) {
			return false;
		}
		foreach ( $values as $number ) {
			if ( ! ( is_int( $number ) || is_float( $number ) ) || ! is_finite( (float) $number ) || abs( $number ) > 1000000 ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Validate plain text, never HTML, URLs, or executable markup.
	 *
	 * @param mixed $text Label or description.
	 * @return bool Valid.
	 */
	public static function text( $text ) {
		return is_string( $text ) && mb_strlen( $text ) <= 1000 && ! preg_match( '~[<>]|https?://|javascript:~i', $text );
	}

	/**
	 * Validate a restricted expression tree without interpreting JavaScript.
	 *
	 * @param mixed $node Number, x, or {op,args}.
	 * @param int   $depth Tree depth.
	 * @param int   $budget Remaining nodes.
	 * @return bool Valid.
	 */
	private static function expression( $node, $depth, &$budget ) {
		if ( $depth > 8 || --$budget < 0 ) {
			return false;
		}
		if ( 'x' === $node || self::numbers( array( $node ), 1 ) ) {
			return true;
		}
		if ( ! is_array( $node ) || array_diff( array_keys( $node ), array( 'op', 'args' ) ) || ! is_string( $node['op'] ?? null ) || ! is_array( $node['args'] ?? null ) ) {
			return false;
		}
		$binary = in_array( $node['op'], array( '+', '-', '*', '/', '^' ), true );
		if ( ! $binary && ! in_array( $node['op'], array( 'sin', 'cos', 'tan', 'sqrt', 'abs', 'exp', 'log' ), true ) ) {
			return false;
		}
		if ( count( $node['args'] ) !== ( $binary ? 2 : 1 ) || array_values( $node['args'] ) !== $node['args'] ) {
			return false;
		}
		foreach ( $node['args'] as $child ) {
			if ( ! self::expression( $child, $depth + 1, $budget ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Safe validation failure.
	 *
	 * @return \WP_Error Error.
	 */
	private static function error() {
		return new \WP_Error( 'exam_figure_spec', __( 'The figure specification has unsupported or invalid objects. Keep the original or regenerate it.', 'ohmylms' ), array( 'status' => 400 ) );
	}
}
