<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Dashboard period: the last 7, 30 or 90 days and the equally long window before.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

use DateInterval;
use DateTimeImmutable;
use OhMyLMS\Analytics\DateRange;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * The last N site-local days ending today, with the previous N days for comparison and one bucket per day
 * for sparklines. Wraps `Analytics\DateRange` (`last_7_days`, `last_30_days`, `last_90_days`).
 */
final class Period {

	/**
	 * Allowed lengths in days.
	 */
	const DAYS = array( 7, 30, 90 );

	/**
	 * Length when the request names none.
	 */
	const DEFAULT_DAYS = 30;

	/**
	 * Current window.
	 *
	 * @var DateRange
	 */
	private $range;

	/**
	 * Length in days.
	 *
	 * @var int
	 */
	private $days;

	/**
	 * Constructor.
	 *
	 * @param int                    $days 7, 30 or 90 (other values fall back to 30).
	 * @param DateTimeImmutable|null $now  Current time (tests); site time by default.
	 */
	public function __construct( $days = self::DEFAULT_DAYS, $now = null ) {
		$days        = in_array( (int) $days, self::DAYS, true ) ? (int) $days : self::DEFAULT_DAYS;
		$now         = $now ? $now : new DateTimeImmutable( 'now', wp_timezone() );
		$this->days  = $days;
		$this->range = DateRange::from_preset( 'last_' . $days . '_days', '', '', $now );
	}

	/**
	 * The period a REST request asks for (`period`).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return Period
	 */
	public static function from_request( $request ) {
		return new self( (int) ( $request['period'] ? $request['period'] : self::DEFAULT_DAYS ) );
	}

	/**
	 * The `period` REST argument.
	 *
	 * @return array
	 */
	public static function arg() {
		return array(
			'description' => __( 'Length of the period in days, ending today.', 'ohmylms' ),
			'type'        => 'integer',
			'enum'        => self::DAYS,
			'default'     => self::DEFAULT_DAYS,
		);
	}

	/**
	 * Length in days.
	 *
	 * @return int
	 */
	public function days() {
		return $this->days;
	}

	/**
	 * Current window.
	 *
	 * @return DateRange
	 */
	public function range() {
		return $this->range;
	}

	/**
	 * The equally long window before.
	 *
	 * @return DateRange
	 */
	public function previous() {
		return $this->range->previous();
	}

	/**
	 * The last seven days of a window (both windows end on their last day).
	 *
	 * @param DateRange $window Window.
	 * @return array{0:string,1:string} Start and end as MySQL datetimes.
	 */
	public static function last_week( DateRange $window ) {
		$end   = new DateTimeImmutable( $window->end );
		$start = $end->sub( new DateInterval( 'P6D' ) );
		return array( $start->format( 'Y-m-d' ) . ' 00:00:00', $window->end_sql() );
	}

	/**
	 * Day keys of the current window (`Y-m-d`), oldest first.
	 *
	 * @return string[]
	 */
	public function day_keys() {
		return $this->range->bucket_keys();
	}

	/**
	 * Cache key fragment.
	 *
	 * @return string
	 */
	public function cache_key() {
		return $this->days . 'd:' . $this->range->end;
	}

	/**
	 * Response representation.
	 *
	 * @return array{days:int,start:string,end:string,previous_start:string,previous_end:string}
	 */
	public function to_array() {
		$previous = $this->previous();
		return array(
			'days'           => $this->days,
			'start'          => $this->range->start,
			'end'            => $this->range->end,
			'previous_start' => $previous->start,
			'previous_end'   => $previous->end,
		);
	}
}
