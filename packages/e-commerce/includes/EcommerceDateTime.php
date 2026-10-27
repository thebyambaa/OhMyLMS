<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

/**
 * Class EcommerceDateTime
 *
 * A custom DateTime class for the e-commerce module that handles UTC offsets and localizes dates.
 *
 * @package OhMyLMS
 * @link https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/class-wc-datetime.php
 */

namespace OhMyLMS\Commerce;

use DateTime;
use DateTimeZone;

/**
 * Plugin ecommerce date time implementation.
 */
class EcommerceDateTime extends DateTime {

	/**
	 * UTC Offset, if needed. Only used when a timezone is not set. When
	 * timezones are used this will equal 0.
	 *
	 * @var integer
	 */
	protected $utc_offset = 0;

	/**
	 * Output an ISO 8601 date string in local (WordPress) timezone.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public function __toString() {
		return $this->format( DATE_ATOM );
	}

	/**
	 * Set UTC offset - this is a fixed offset instead of a timezone.
	 *
	 * @param int $offset Offset.
	 */
	public function set_utc_offset( $offset ) {
		$this->utc_offset = intval( $offset );
	}

	/**
	 * Get UTC offset if set, or default to the DateTime object's offset.
	 */
	#[\ReturnTypeWillChange]
	/**
	 * Get offset for Ecommerce date time.
	 */
	public function getOffset() {
		return $this->utc_offset ?: parent::getOffset();
	}

	/**
	 * Set timezone.
	 *
	 * @param DateTimeZone $timezone DateTimeZone instance.
	 * @return DateTime
	 */
	#[\ReturnTypeWillChange]
	/**
	 * Set timezone for Ecommerce date time.
	 *
	 * @param mixed $timezone Timezone.
	 */
	public function setTimezone( $timezone ) {
		$this->utc_offset = 0;
		return parent::setTimezone( $timezone );
	}

	/**
	 * Missing in PHP 5.2 so just here so it can be supported consistently.
	 *
	 * @since  1.0.0
	 * @return int
	 */
	#[\ReturnTypeWillChange]
	/**
	 * Get timestamp for Ecommerce date time.
	 */
	public function getTimestamp() {
		return method_exists( 'DateTime', 'getTimestamp' ) ? parent::getTimestamp() : $this->format( 'U' );
	}

	/**
	 * Get the timestamp with the WordPress timezone offset added or subtracted.
	 *
	 * @since  1.0.0
	 * @return int
	 */
	public function getOffsetTimestamp() {
		return $this->getTimestamp() + $this->getOffset();
	}

	/**
	 * Format a date based on the offset timestamp.
	 *
	 * @since  1.0.0
	 * @param string $format Date format.
	 * @return string
	 */
	public function date( $format ) {
		return gmdate( $format, $this->getOffsetTimestamp() );
	}

	/**
	 * Return a localised date based on offset timestamp. Wrapper for date_i18n function.
	 *
	 * @since  1.0.0
	 * @param string $format Date format.
	 * @return string
	 */
	public function date_i18n( $format = 'Y-m-d' ) {
		return date_i18n( $format, $this->getOffsetTimestamp() );
	}
}
