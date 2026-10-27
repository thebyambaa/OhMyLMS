<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Pure xAPI statement construction.
 *
 * @package OhMyLMS\XApi
 */

namespace OhMyLMS\XApi;

defined( 'ABSPATH' ) || exit;

/**
 * Assembles statement arrays from values the caller has already resolved.
 *
 * Nothing here reads WordPress state or the database, so every rule (score limits, duration
 * format, omitted empty keys) is covered by the unit tests without a site.
 */
final class StatementBuilder {

	const PLATFORM = 'OhMyLMS';

	/**
	 * Build a statement.
	 *
	 * @param array $parts id, actor, verb (key), object, timestamp (unix), and optional result, context.
	 * @return array
	 */
	public static function build( array $parts ) {
		$statement = array(
			'id'        => $parts['id'],
			'actor'     => $parts['actor'],
			'verb'      => Vocabulary::verb( $parts['verb'] ),
			'object'    => $parts['object'],
			'timestamp' => self::timestamp( $parts['timestamp'] ),
		);
		$result    = self::prune( isset( $parts['result'] ) ? $parts['result'] : array() );
		if ( $result ) {
			$statement['result'] = $result;
		}
		$context = self::prune( isset( $parts['context'] ) ? $parts['context'] : array() );
		if ( $context ) {
			$statement['context'] = $context;
		}

		return $statement;
	}

	/**
	 * Result object.
	 *
	 * Score limits follow the xAPI rules: scaled is -1..1, and raw must sit between min and max
	 * when those are given. A raw score outside its own range (for example bonus marks above the
	 * quiz maximum) is sent alone rather than as a statement the LRS would reject.
	 *
	 * @param array $args raw, max, min, scaled, success (bool|null), completion (bool|null), response, seconds.
	 * @return array
	 */
	public static function result( array $args ) {
		$result = array();
		$score  = array();
		$raw    = isset( $args['raw'] ) ? (float) $args['raw'] : null;
		$min    = isset( $args['min'] ) ? (float) $args['min'] : 0.0;
		$max    = isset( $args['max'] ) ? (float) $args['max'] : null;

		if ( null !== $raw ) {
			$score['raw'] = round( $raw, 4 );
			if ( null !== $max && $max > $min && $raw >= $min && $raw <= $max ) {
				$score['min']    = $min;
				$score['max']    = round( $max, 4 );
				$score['scaled'] = round( ( $raw - $min ) / ( $max - $min ), 4 );
			}
		}
		if ( ! isset( $score['scaled'] ) && isset( $args['scaled'] ) ) {
			$scaled = (float) $args['scaled'];
			if ( $scaled >= -1 && $scaled <= 1 ) {
				$score['scaled'] = round( $scaled, 4 );
			}
		}
		if ( $score ) {
			$result['score'] = $score;
		}
		foreach ( array( 'success', 'completion' ) as $flag ) {
			if ( isset( $args[ $flag ] ) && null !== $args[ $flag ] ) {
				$result[ $flag ] = (bool) $args[ $flag ];
			}
		}
		if ( isset( $args['response'] ) && '' !== (string) $args['response'] ) {
			$result['response'] = (string) $args['response'];
		}
		if ( isset( $args['seconds'] ) && $args['seconds'] >= 0 ) {
			$result['duration'] = self::duration( $args['seconds'] );
		}

		return $result;
	}

	/**
	 * Context object shared by course-scoped statements.
	 *
	 * @param array $args registration, parent (activity), grouping (activity), language, extensions.
	 * @return array
	 */
	public static function context( array $args ) {
		$context = array(
			'platform' => self::PLATFORM,
		);
		if ( ! empty( $args['registration'] ) ) {
			$context['registration'] = $args['registration'];
		}
		if ( ! empty( $args['language'] ) ) {
			$context['language'] = $args['language'];
		}
		$activities = array();
		foreach ( array( 'parent', 'grouping' ) as $relation ) {
			if ( ! empty( $args[ $relation ] ) ) {
				$activities[ $relation ] = array( $args[ $relation ] );
			}
		}
		if ( $activities ) {
			$context['contextActivities'] = $activities;
		}
		if ( ! empty( $args['extensions'] ) ) {
			$context['extensions'] = $args['extensions'];
		}

		return $context;
	}

	/**
	 * ISO 8601 UTC timestamp.
	 *
	 * @param int $unix Unix time.
	 * @return string
	 */
	public static function timestamp( $unix ) {
		return gmdate( 'Y-m-d\TH:i:s\Z', (int) $unix );
	}

	/**
	 * ISO 8601 duration with centisecond precision, for example PT1H2M3.5S.
	 *
	 * @param float $seconds Elapsed seconds.
	 * @return string
	 */
	public static function duration( $seconds ) {
		$centis = (int) round( max( 0, (float) $seconds ) * 100 );
		$hours  = intdiv( $centis, 360000 );
		$rest   = $centis - $hours * 360000;
		$mins   = intdiv( $rest, 6000 );
		$secs   = ( $rest - $mins * 6000 ) / 100;

		$out = 'PT';
		if ( $hours ) {
			$out .= $hours . 'H';
		}
		if ( $mins ) {
			$out .= $mins . 'M';
		}
		if ( $secs || ( ! $hours && ! $mins ) ) {
			$out .= rtrim( rtrim( number_format( $secs, 2, '.', '' ), '0' ), '.' ) . 'S';
		}

		return $out;
	}

	/**
	 * Remove null and empty-array entries recursively, keeping false, 0 and "0".
	 *
	 * @param array $values Nested values.
	 * @return array
	 */
	public static function prune( array $values ) {
		foreach ( $values as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = self::prune( $value );
			}
			if ( null === $value || array() === $value ) {
				unset( $values[ $key ] );
			} else {
				$values[ $key ] = $value;
			}
		}

		return $values;
	}
}
