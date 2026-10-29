/**
 * Pure date helpers for the date-range picker. They reproduce the subset of
 * dayjs 1.x (English locale, week starting on Sunday, local time) that the
 * recovered control used, on plain `Date` values, so the picker needs no date
 * library. An invalid date is `new Date( NaN )`.
 */

const PARSE_PATTERN =
	/^(\d{4})[-/]?(\d{1,2})?[-/]?(\d{0,2})[Tt\s]*(\d{1,2})?:?(\d{1,2})?:?(\d{1,2})?[.:]?(\d+)?$/;

export const DATE_FORMAT = 'YYYY-MM-DD';
export const DISPLAY_FORMAT = 'DD/MM/YYYY';

/**
 * Parse a value the way `dayjs( value )` does.
 *
 * @param {unknown} value `undefined` (now), `null` (invalid), a Date, a number or a string.
 * @return {Date} A new Date, invalid when the value cannot be parsed.
 */
export function parseDay( value ) {
	if ( null === value ) {
		return new Date( NaN );
	}
	if ( undefined === value ) {
		return new Date();
	}
	if ( value instanceof Date ) {
		return new Date( value );
	}
	if ( 'string' === typeof value && ! /Z$/i.test( value ) ) {
		const parts = value.match( PARSE_PATTERN );
		if ( parts ) {
			return new Date(
				parts[ 1 ],
				parts[ 2 ] - 1 || 0,
				parts[ 3 ] || 1,
				parts[ 4 ] || 0,
				parts[ 5 ] || 0,
				parts[ 6 ] || 0,
				( parts[ 7 ] || '0' ).substring( 0, 3 )
			);
		}
	}
	return new Date( value );
}

/**
 * @param {Date} date Date.
 * @return {boolean} Whether the date is valid.
 */
export function isValidDay( date ) {
	return ! Number.isNaN( date.getTime() );
}

/**
 * Format a date as `YYYY-MM-DD` or `DD/MM/YYYY`; `Invalid Date` when invalid.
 *
 * @param {Date}   date   Date.
 * @param {string} format `DATE_FORMAT` or `DISPLAY_FORMAT`.
 * @return {string} Formatted date.
 */
export function formatDay( date, format = DATE_FORMAT ) {
	if ( ! isValidDay( date ) ) {
		return 'Invalid Date';
	}
	const year = String( date.getFullYear() ).padStart( 4, '0' );
	const month = String( date.getMonth() + 1 ).padStart( 2, '0' );
	const day = String( date.getDate() ).padStart( 2, '0' );
	return DISPLAY_FORMAT === format
		? `${ day }/${ month }/${ year }`
		: `${ year }-${ month }-${ day }`;
}

/**
 * Number of days in the month of a date.
 *
 * @param {Date} date Date.
 * @return {number} Days in the month.
 */
function daysInMonth( date ) {
	return new Date( date.getFullYear(), date.getMonth() + 1, 0 ).getDate();
}

/**
 * Set the day of the month without clamping (dayjs `date()`).
 *
 * @param {Date}   date Date.
 * @param {number} day  Day of the month.
 * @return {Date} New date.
 */
export function setDayOfMonth( date, day ) {
	const next = new Date( date );
	next.setDate( day );
	return next;
}

/**
 * Set the month, clamping the day to the new month (dayjs `month()`).
 *
 * @param {Date}   date  Date.
 * @param {number} month Zero-based month.
 * @return {Date} New date.
 */
export function setMonth( date, month ) {
	const next = new Date( date );
	next.setDate( 1 );
	next.setMonth( month );
	next.setDate( Math.min( date.getDate(), daysInMonth( next ) ) );
	return next;
}

/**
 * Set the year, clamping the day to the new month (dayjs `year()`).
 *
 * @param {Date}   date Date.
 * @param {number} year Full year.
 * @return {Date} New date.
 */
export function setYear( date, year ) {
	const next = new Date( date );
	next.setDate( 1 );
	next.setFullYear( year );
	next.setDate( Math.min( date.getDate(), daysInMonth( next ) ) );
	return next;
}

/**
 * Add months, clamping the day (dayjs `add( n, "month" )`).
 *
 * @param {Date}   date   Date.
 * @param {number} months Months to add, negative to subtract.
 * @return {Date} New date.
 */
export function addMonths( date, months ) {
	return setMonth( date, date.getMonth() + months );
}

/**
 * Add days, keeping the time of day.
 *
 * @param {Date}   date Date.
 * @param {number} days Days to add, negative to subtract.
 * @return {Date} New date.
 */
export function addDays( date, days ) {
	return setDayOfMonth( date, date.getDate() + days );
}

/**
 * @param {Date} date Date.
 * @return {Date} Start of the Sunday-first week, at 00:00.
 */
export function startOfWeek( date ) {
	return new Date(
		date.getFullYear(),
		date.getMonth(),
		date.getDate() - date.getDay()
	);
}

/**
 * @param {Date} date Date.
 * @return {Date} End of the Sunday-first week, at 23:59:59.999.
 */
export function endOfWeek( date ) {
	return new Date(
		date.getFullYear(),
		date.getMonth(),
		date.getDate() + ( 6 - date.getDay() ),
		23,
		59,
		59,
		999
	);
}

/**
 * @param {Date} date Date.
 * @return {Date} First day of the month, at 00:00.
 */
export function startOfMonth( date ) {
	return new Date( date.getFullYear(), date.getMonth(), 1 );
}

/**
 * @param {Date} date Date.
 * @return {Date} Last day of the month, at 23:59:59.999.
 */
export function endOfMonth( date ) {
	return new Date(
		date.getFullYear(),
		date.getMonth() + 1,
		0,
		23,
		59,
		59,
		999
	);
}

/**
 * Local calendar-day key used to compare days; NaN when invalid.
 *
 * @param {Date} date Date.
 * @return {number} Midnight timestamp.
 */
function dayKey( date ) {
	return new Date(
		date.getFullYear(),
		date.getMonth(),
		date.getDate()
	).getTime();
}

/**
 * @param {Date} a Date.
 * @param {Date} b Date.
 * @return {boolean} Whether both fall on the same calendar day.
 */
export function isSameDay( a, b ) {
	return dayKey( a ) === dayKey( b );
}

/**
 * @param {Date} a Date.
 * @param {Date} b Date.
 * @return {boolean} Whether `a` is on a later calendar day than `b`.
 */
export function isAfterDay( a, b ) {
	return dayKey( b ) < dayKey( a );
}

/**
 * @param {Date} a Date.
 * @param {Date} b Date.
 * @return {boolean} Whether `a` is on an earlier calendar day than `b`.
 */
export function isBeforeDay( a, b ) {
	return dayKey( a ) < dayKey( b );
}

/**
 * Inclusive day range test (dayjs `isBetween( a, b, "day", "[]" )`), which
 * accepts the bounds in either order.
 *
 * @param {Date} date Date.
 * @param {Date} from Range start.
 * @param {Date} to   Range end.
 * @return {boolean} Whether the day lies in the range.
 */
export function isBetweenDays( date, from, to ) {
	return (
		( ! isBeforeDay( date, from ) && ! isAfterDay( date, to ) ) ||
		( ! isAfterDay( date, from ) && ! isBeforeDay( date, to ) )
	);
}

/**
 * Date range for a preset of the "Select a Preset" menu.
 *
 * @param {string} preset `today`, `this_week`, `last_7_days` or `this_month`.
 * @param {Date}   [now]  Reference date.
 * @return {Array} `[ start, end ]` as `YYYY-MM-DD`; both undefined for an unknown preset.
 */
export function getPresetRange( preset, now = new Date() ) {
	switch ( preset ) {
		case 'today':
			return [ formatDay( now ), formatDay( now ) ];
		case 'this_week':
			return [
				formatDay( startOfWeek( now ) ),
				formatDay( endOfWeek( now ) ),
			];
		case 'last_7_days':
			return [ formatDay( addDays( now, -6 ) ), formatDay( now ) ];
		case 'this_month':
			return [
				formatDay( startOfMonth( now ) ),
				formatDay( endOfMonth( now ) ),
			];
		default:
			return [ undefined, undefined ];
	}
}

/**
 * Label of the range button.
 *
 * @param {unknown} start       Range start.
 * @param {unknown} end         Range end.
 * @param {string}  placeholder Text shown when the range is incomplete.
 * @return {string} `DD/MM/YYYY - DD/MM/YYYY` or the placeholder.
 */
export function getRangeLabel( start, end, placeholder ) {
	if ( ! start || ! end ) {
		return placeholder;
	}
	const from = formatDay( parseDay( start ), DISPLAY_FORMAT );
	const to = formatDay( parseDay( end ), DISPLAY_FORMAT );
	return `${ from } - ${ to }`;
}

/**
 * Outcome of a click on a calendar day.
 *
 * @param {unknown} start  Pending range start.
 * @param {unknown} end    Pending range end.
 * @param {unknown} picked Value from the calendar.
 * @return {{type: string, start?: unknown, startDate?: unknown, endDate?: unknown}} Either a new
 *     range start, or the completed range ordered by time.
 */
export function pickDay( start, end, picked ) {
	if ( ! start || end ) {
		return { type: 'start', start: picked };
	}
	return parseDay( picked ).getTime() < parseDay( start ).getTime()
		? { type: 'range', startDate: picked, endDate: start }
		: { type: 'range', startDate: start, endDate: picked };
}

/**
 * Turn the calendar selection into the value reported by the picker.
 *
 * @param {{startDate: unknown, endDate: unknown}} selection Selection from the calendar.
 * @return {{close: boolean, value: Array, dates?: string[]}} `value` is
 *     `[ start, end ]` as `YYYY-MM-DD`, or `[ null, null ]` when cleared;
 *     `dates` is only set for a complete range.
 */
export function resolveSelection( { startDate, endDate } ) {
	if ( ! startDate || ! endDate ) {
		return { close: true, value: [ null, null ] };
	}
	const start = formatDay( parseDay( startDate ) );
	const end = formatDay( parseDay( endDate ) );
	return {
		close: !! ( start && end ),
		value: [ start, end ],
		dates: [ start, end ],
	};
}
