import {
	addMonths,
	endOfMonth,
	isAfterDay,
	isBeforeDay,
	isBetweenDays,
	isSameDay,
	isValidDay,
	parseDay,
	setDayOfMonth,
	setMonth,
	setYear,
	startOfMonth,
} from './date-range-model.mjs';

/*
 * The calendar is the `DatePicker` of `@wordpress/components`, which cannot
 * show a range. The picker therefore reads the date of every day button from the
 * rendered DOM and toggles the classes `has-endpoint`, `is-selected` and
 * `is-hover-range` on it. These helpers hold that DOM logic, taking the document
 * as an argument so they can be run against a stand-in.
 */

export const DAY_SELECTOR = '.components-datetime__date__day';
export const CALENDAR_SELECTOR = '.components-datetime__date';
export const MONTH_YEAR_SELECTOR = '.components-datetime__date__month-year';
export const RANGE_CLASSES = [
	'is-selected',
	'has-endpoint',
	'is-hover-range',
];

const DIGITS = /^\d{1,2}$/;

/**
 * Date of a day button read from its attributes, or null when it has none.
 *
 * @param {Element}  cell Day button.
 * @param {Document} doc  Document holding the calendar.
 * @return {Date|null} Date, possibly invalid, or null.
 */
export function readCellDate( cell, doc ) {
	const direct = cell.getAttribute( 'data-date' );
	if ( direct ) {
		return parseDay( direct );
	}
	const source = cell.querySelector( 'button' ) || cell;
	const attribute =
		source.getAttribute( 'data-date' ) || source.getAttribute( 'value' );
	if ( attribute ) {
		return parseDay( attribute );
	}
	const text = cell.textContent?.trim();
	if (
		text &&
		DIGITS.test( text ) &&
		doc.querySelector( MONTH_YEAR_SELECTOR )?.textContent
	) {
		const table = cell.closest( 'table' );
		if (
			table &&
			table.previousElementSibling?.querySelector( MONTH_YEAR_SELECTOR )
		) {
			return setDayOfMonth( parseDay(), parseInt( text, 10 ) );
		}
	}
	const label = cell.getAttribute( 'aria-label' );
	if ( label ) {
		const numbers = label.match( /\d+/g );
		if ( numbers && numbers.length >= 2 ) {
			const day = parseInt( numbers[ 0 ], 10 );
			const year = numbers.find(
				( number ) => parseInt( number, 10 ) > 1900
			);
			if ( day && year ) {
				const now = parseDay();
				return setDayOfMonth(
					setMonth(
						setYear( now, parseInt( year, 10 ) ),
						now.getMonth()
					),
					day
				);
			}
		}
	}
	return null;
}

/**
 * Guess the date of a day button from its number when the attributes gave none.
 *
 * @param {Element}   cell          Day button.
 * @param {Date|null} current       Date read from the attributes.
 * @param {Date}      anchor        Month the calendar is showing.
 * @param {boolean}   requireDigits Only guess inside the calendar element.
 * @return {Date|null} The guess, or `current` when no guess is possible.
 */
export function guessCellDate( cell, current, anchor, requireDigits ) {
	const text = cell.textContent?.trim();
	if ( ! text || ! DIGITS.test( text ) ) {
		return current;
	}
	if ( requireDigits && ! cell.closest( CALENDAR_SELECTOR ) ) {
		return current;
	}
	const day = parseInt( text, 10 );
	const firstOfRow = cell
		.closest( 'tr' )
		?.querySelector( DAY_SELECTOR )?.textContent;
	if ( day < 15 && firstOfRow && firstOfRow.includes( '1' ) ) {
		return setDayOfMonth( addMonths( startOfMonth( anchor ), -1 ), day );
	}
	if ( day > 15 && cell.closest( 'tr:last-child' ) ) {
		return setDayOfMonth( addMonths( endOfMonth( anchor ), 1 ), day );
	}
	return setDayOfMonth( anchor, day );
}

/**
 * Classes a day button gets for the pending range.
 *
 * @param {Date}      date  Date of the button.
 * @param {unknown}   start Range start.
 * @param {unknown}   end   Range end.
 * @param {Date|null} hover Day under the pointer.
 * @return {string[]} Classes to add.
 */
export function getRangeClasses( date, start, end, hover ) {
	const classes = [];
	if ( start && isSameDay( date, parseDay( start ) ) ) {
		classes.push( 'has-endpoint' );
	}
	if ( end && isSameDay( date, parseDay( end ) ) ) {
		classes.push( 'has-endpoint' );
	}
	if (
		start &&
		end &&
		isAfterDay( date, parseDay( start ) ) &&
		isBeforeDay( date, parseDay( end ) )
	) {
		classes.push( 'is-selected' );
	}
	if ( start && ! end && hover && ! isSameDay( hover, parseDay( start ) ) ) {
		const from = parseDay( start );
		const [ first, last ] =
			hover.getTime() > from.getTime()
				? [ from, hover ]
				: [ hover, from ];
		if ( isBetweenDays( date, first, last ) ) {
			classes.push( 'is-hover-range' );
		}
	}
	return classes;
}

/**
 * Resolve the date of a day button, guessing when the attributes give none.
 *
 * @param {Element}  cell          Day button.
 * @param {Document} doc           Document holding the calendar.
 * @param {Date}     anchor        Month the calendar is showing.
 * @param {boolean}  requireDigits Only guess inside the calendar element.
 * @return {Date|null} Valid date, or null/invalid when it cannot be resolved.
 */
function resolveCell( cell, doc, anchor, requireDigits ) {
	const read = readCellDate( cell, doc );
	if ( ! read || ! isValidDay( read ) ) {
		return guessCellDate( cell, read, anchor, requireDigits );
	}
	return read;
}

/**
 * Mark the day buttons of the calendar that fall in the pending range.
 *
 * @param {Document}  doc   Document holding the calendar.
 * @param {unknown}   start Range start.
 * @param {unknown}   end   Range end.
 * @param {Date|null} hover Day under the pointer.
 * @return {void}
 */
export function paintRange( doc, start, end, hover ) {
	const anchor = parseDay( start || end || undefined );
	doc.querySelectorAll( DAY_SELECTOR ).forEach( ( cell ) => {
		const date = resolveCell( cell, doc, anchor, true );
		if ( date && isValidDay( date ) ) {
			cell.classList.remove( ...RANGE_CLASSES );
			getRangeClasses( date, start, end, hover ).forEach( ( name ) =>
				cell.classList.add( name )
			);
		}
	} );
}

const hoverHandlers = new WeakMap();

/**
 * Report the day under the pointer. A button keeps a single pair of listeners,
 * replaced whenever the calendar is rendered again.
 *
 * @param {Document}                    doc      Document holding the calendar.
 * @param {unknown}                     start    Range start.
 * @param {unknown}                     end      Range end.
 * @param {( date: Date|null ) => void} setHover Receives a Date on enter and null on leave.
 * @return {void}
 */
export function bindHover( doc, start, end, setHover ) {
	const anchor = parseDay( start || end || undefined );
	doc.querySelectorAll( DAY_SELECTOR ).forEach( ( cell ) => {
		const date = resolveCell( cell, doc, anchor, false );
		if ( ! date || ! isValidDay( date ) ) {
			return;
		}
		const previous = hoverHandlers.get( cell );
		if ( previous ) {
			cell.removeEventListener( 'mouseenter', previous.enter );
			cell.removeEventListener( 'mouseleave', previous.leave );
		}
		const handlers = {
			enter: () => setHover( new Date( date ) ),
			leave: () => setHover( null ),
		};
		hoverHandlers.set( cell, handlers );
		cell.addEventListener( 'mouseenter', handlers.enter );
		cell.addEventListener( 'mouseleave', handlers.leave );
	} );
}

/**
 * Keep the highlights and hover listeners in step with the calendar.
 *
 * @param {Document}                    doc      Document holding the calendar.
 * @param {unknown}                     start    Range start.
 * @param {unknown}                     end      Range end.
 * @param {Date|null}                   hover    Day under the pointer.
 * @param {( date: Date|null ) => void} setHover Receives the hovered Date or null.
 * @return {( () => void )|undefined} Cleanup, or nothing when no calendar is shown.
 */
export function watchCalendar( doc, start, end, hover, setHover ) {
	const calendar = doc.querySelector( CALENDAR_SELECTOR );
	if ( ! calendar ) {
		return undefined;
	}
	const refresh = () => {
		paintRange( doc, start, end, hover );
		bindHover( doc, start, end, setHover );
	};
	refresh();
	const observer = new window.MutationObserver( refresh );
	observer.observe( calendar, { childList: true, subtree: true } );
	return () => observer.disconnect();
}
