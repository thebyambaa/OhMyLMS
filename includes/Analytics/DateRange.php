<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Date range for analytics requests.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Analytics;

use DateInterval;
use DateTimeImmutable;
use WP_Error;
use WP_REST_Request;

/**
 * An inclusive range of site-local calendar days, its equally long previous period and the
 * chart granularity. Shared by every analytics route (see docs/api/analytics.md).
 *
 * @since 1.0.0
 */
final class DateRange {

	/**
	 * Preset keys. `custom` needs `start` and `end`.
	 */
	const PRESETS = array(
		'today',
		'last_7_days',
		'last_30_days',
		'last_90_days',
		'this_month',
		'last_month',
		'this_year',
		'last_12_months',
		'custom',
	);

	/**
	 * Preset used when the request names none.
	 */
	const DEFAULT_PRESET = 'last_30_days';

	/**
	 * Longest custom range in days (about two years). Bounds the chart and every query.
	 */
	const MAX_SPAN_DAYS = 731;

	/**
	 * Ranges up to this many days are charted per day, longer ones per month.
	 */
	const DAILY_LIMIT_DAYS = 92;

	/**
	 * Preset key.
	 *
	 * @var string
	 */
	public $preset;

	/**
	 * First day, `Y-m-d`.
	 *
	 * @var string
	 */
	public $start;

	/**
	 * Last day, `Y-m-d`.
	 *
	 * @var string
	 */
	public $end;

	/**
	 * Constructor.
	 *
	 * @param string $preset Preset key.
	 * @param string $start  First day, `Y-m-d`.
	 * @param string $end    Last day, `Y-m-d`.
	 */
	public function __construct( $preset, $start, $end ) {
		$this->preset = $preset;
		$this->start  = $start;
		$this->end    = $end;
	}

	/**
	 * REST argument definitions shared by every analytics route.
	 *
	 * @return array<string,array>
	 */
	public static function args() {
		return array(
			'range' => array(
				'description' => __( 'Date range preset. "custom" needs start and end.', 'ohmylms' ),
				'type'        => 'string',
				'default'     => self::DEFAULT_PRESET,
				'enum'        => self::PRESETS,
			),
			'start' => array(
				'description'       => __( 'First day (YYYY-MM-DD, site time) of a custom range.', 'ohmylms' ),
				'type'              => 'string',
				'validate_callback' => array( __CLASS__, 'validate_date_arg' ),
			),
			'end'   => array(
				'description'       => __( 'Last day (YYYY-MM-DD, site time) of a custom range.', 'ohmylms' ),
				'type'              => 'string',
				'validate_callback' => array( __CLASS__, 'validate_date_arg' ),
			),
		);
	}

	/**
	 * Validate the shape of a date argument (the calendar check happens in from_request()).
	 *
	 * @param mixed $value Value.
	 * @return true|WP_Error
	 */
	public static function validate_date_arg( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return new WP_Error( 'rest_invalid_param', __( 'Dates must use the YYYY-MM-DD format.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Parse a day string strictly.
	 *
	 * @param string $value Candidate `Y-m-d`.
	 * @return DateTimeImmutable|null
	 */
	private static function parse_day( $value ) {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $value, wp_timezone() );
		if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
			return null;
		}
		return $date;
	}

	/**
	 * Build the range of a request.
	 *
	 * @param WP_REST_Request        $request Request.
	 * @param DateTimeImmutable|null $now     Current time (tests); site time by default.
	 * @return DateRange|WP_Error
	 */
	public static function from_request( $request, $now = null ) {
		$preset = (string) ( $request['range'] ? $request['range'] : self::DEFAULT_PRESET );
		$now    = $now ? $now : new DateTimeImmutable( 'now', wp_timezone() );
		return self::from_preset( $preset, (string) $request['start'], (string) $request['end'], $now );
	}

	/**
	 * Build a range from a preset.
	 *
	 * @param string            $preset Preset key.
	 * @param string            $start  Custom start.
	 * @param string            $end    Custom end.
	 * @param DateTimeImmutable $now    Current time in site time.
	 * @return DateRange|WP_Error
	 */
	public static function from_preset( $preset, $start, $end, DateTimeImmutable $now ) {
		$today = $now->setTime( 0, 0, 0 );

		switch ( $preset ) {
			case 'today':
				$range = array( $today, $today );
				break;
			case 'last_7_days':
				$range = array( $today->sub( new DateInterval( 'P6D' ) ), $today );
				break;
			case 'last_30_days':
				$range = array( $today->sub( new DateInterval( 'P29D' ) ), $today );
				break;
			case 'last_90_days':
				$range = array( $today->sub( new DateInterval( 'P89D' ) ), $today );
				break;
			case 'this_month':
				$range = array( $today->modify( 'first day of this month' ), $today );
				break;
			case 'last_month':
				$range = array( $today->modify( 'first day of last month' ), $today->modify( 'last day of last month' ) );
				break;
			case 'this_year':
				$range = array( $today->setDate( (int) $today->format( 'Y' ), 1, 1 ), $today );
				break;
			case 'last_12_months':
				$range = array( $today->modify( 'first day of this month' )->sub( new DateInterval( 'P11M' ) ), $today );
				break;
			case 'custom':
				$from = self::parse_day( $start );
				$to   = self::parse_day( $end );
				if ( ! $from || ! $to ) {
					return new WP_Error( 'ohmylms_analytics_invalid_range', __( 'A custom range needs a valid start and end date (YYYY-MM-DD).', 'ohmylms' ), array( 'status' => 400 ) );
				}
				if ( $from > $to ) {
					return new WP_Error( 'ohmylms_analytics_invalid_range', __( 'The start date must not be after the end date.', 'ohmylms' ), array( 'status' => 400 ) );
				}
				if ( (int) $from->diff( $to )->days + 1 > self::MAX_SPAN_DAYS ) {
					return new WP_Error( 'ohmylms_analytics_invalid_range', __( 'The date range is too long.', 'ohmylms' ), array( 'status' => 400 ) );
				}
				$range = array( $from, $to );
				break;
			default:
				return new WP_Error( 'ohmylms_analytics_invalid_range', __( 'Unknown date range.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		return new self( $preset, $range[0]->format( 'Y-m-d' ), $range[1]->format( 'Y-m-d' ) );
	}

	/**
	 * Number of days in the range.
	 *
	 * @return int
	 */
	public function days() {
		$from = new DateTimeImmutable( $this->start );
		$to   = new DateTimeImmutable( $this->end );
		return (int) $from->diff( $to )->days + 1;
	}

	/**
	 * Chart granularity.
	 *
	 * @return string `day` or `month`.
	 */
	public function granularity() {
		return $this->days() <= self::DAILY_LIMIT_DAYS ? 'day' : 'month';
	}

	/**
	 * The equally long period right before this one.
	 *
	 * @return DateRange
	 */
	public function previous() {
		$days = $this->days();
		$end  = ( new DateTimeImmutable( $this->start ) )->sub( new DateInterval( 'P1D' ) );
		$from = $end->sub( new DateInterval( 'P' . ( $days - 1 ) . 'D' ) );
		return new self( 'previous', $from->format( 'Y-m-d' ), $end->format( 'Y-m-d' ) );
	}

	/**
	 * Start as a MySQL datetime (site time).
	 *
	 * @return string
	 */
	public function start_sql() {
		return $this->start . ' 00:00:00';
	}

	/**
	 * End as a MySQL datetime (site time).
	 *
	 * @return string
	 */
	public function end_sql() {
		return $this->end . ' 23:59:59';
	}

	/**
	 * Bucket keys of the chart in order: `Y-m-d` per day or `Y-m` per month.
	 *
	 * @return string[]
	 */
	public function bucket_keys() {
		$keys = array();
		if ( 'day' === $this->granularity() ) {
			$cursor = new DateTimeImmutable( $this->start );
			$last   = new DateTimeImmutable( $this->end );
			while ( $cursor <= $last ) {
				$keys[] = $cursor->format( 'Y-m-d' );
				$cursor = $cursor->add( new DateInterval( 'P1D' ) );
			}
			return $keys;
		}
		$cursor = ( new DateTimeImmutable( $this->start ) )->modify( 'first day of this month' );
		$last   = new DateTimeImmutable( $this->end );
		while ( $cursor <= $last ) {
			$keys[] = $cursor->format( 'Y-m' );
			$cursor = $cursor->add( new DateInterval( 'P1M' ) );
		}
		return $keys;
	}

	/**
	 * Length of a bucket key in a MySQL date string (10 for days, 7 for months).
	 *
	 * @return int
	 */
	public function bucket_length() {
		return 'day' === $this->granularity() ? 10 : 7;
	}

	/**
	 * Response representation.
	 *
	 * @return array
	 */
	public function to_array() {
		$previous = $this->previous();
		return array(
			'preset'         => $this->preset,
			'start'          => $this->start,
			'end'            => $this->end,
			'days'           => $this->days(),
			'granularity'    => $this->granularity(),
			'previous_start' => $previous->start,
			'previous_end'   => $previous->end,
		);
	}
}
