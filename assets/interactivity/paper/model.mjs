/**
 * Pure logic of the learner's Paper View and the print page: flags, navigation, timer warnings and
 * the submit check. Nothing here grades; the server decides every mark.
 */

/**
 * Flagged question IDs from storage. Anything unreadable flags nothing.
 *
 * @param {string|null} raw Stored JSON list of question IDs.
 * @return {Object<string, true>} Flags by question ID.
 */
export function parseFlags( raw ) {
	let list;
	try {
		list = JSON.parse( raw || '[]' );
	} catch {
		return {};
	}
	if ( ! Array.isArray( list ) ) {
		return {};
	}
	const flags = {};
	for ( const value of list ) {
		const id = Number( value );
		if ( Number.isInteger( id ) && id > 0 ) {
			flags[ String( id ) ] = true;
		}
	}
	return flags;
}

/**
 * Flags as stored: a sorted JSON list of question IDs.
 *
 * @param {Object<string, boolean>} flags Flags by question ID.
 * @return {string} JSON.
 */
export function serializeFlags( flags ) {
	return JSON.stringify(
		Object.keys( flags || {} )
			.filter( ( id ) => flags[ id ] )
			.map( Number )
			.sort( ( a, b ) => a - b )
	);
}

/**
 * Flags with one question switched.
 *
 * @param {Object<string, true>} flags Flags by question ID.
 * @param {number|string}        id    Question ID.
 * @return {Object<string, true>} New flags.
 */
export function toggleFlag( flags, id ) {
	const key = String( id );
	const next = { ...flags };
	if ( next[ key ] ) {
		delete next[ key ];
	} else {
		next[ key ] = true;
	}
	return next;
}

/**
 * Number of flagged questions.
 *
 * @param {Object<string, boolean>} flags Flags by question ID.
 * @return {number} Count.
 */
export const countFlags = ( flags ) =>
	Object.values( flags || {} ).filter( Boolean ).length;

/**
 * Replace the first `%s` or `%d` of a translated string.
 *
 * @param {string}        template Translated string.
 * @param {string|number} value    Value.
 * @return {string} Text.
 */
export const format = ( template, value ) =>
	String( template || '' ).replace( /%[sd]/, String( value ) );

/**
 * The warning threshold the timer has just passed, in seconds.
 *
 * @param {number|null} previous   Seconds left at the last tick, null before the first.
 * @param {number}      remaining  Seconds left now.
 * @param {number[]}    thresholds Warning points in seconds.
 * @return {number} The threshold crossed, or 0.
 */
export function timeWarning( previous, remaining, thresholds = [ 300, 60 ] ) {
	if ( previous === null || previous === undefined ) {
		return 0;
	}
	return (
		thresholds.find(
			( threshold ) => previous > threshold && remaining <= threshold
		) || 0
	);
}

/**
 * Spoken text of a timer warning.
 *
 * @param {number} seconds Threshold in seconds.
 * @param {Object} strings Translated strings: minutesLeft (%d) and oneMinuteLeft.
 * @return {string} Text.
 */
export function warningText( seconds, strings ) {
	const minutes = Math.round( seconds / 60 );
	return minutes <= 1
		? strings.oneMinuteLeft
		: format( strings.minutesLeft, minutes );
}

/**
 * What to confirm before a final submit. The quiz player already confirms unanswered questions,
 * so flags are confirmed only when every question has an answer.
 *
 * @param {Object} counts           Counts.
 * @param {number} counts.questions Questions in the attempt.
 * @param {number} counts.answered  Questions with an answer.
 * @param {number} counts.flagged   Flagged questions.
 * @return {''|'blank'|'flags'} Confirmation needed.
 */
export function submitCheck( { questions, answered, flagged } ) {
	if ( Math.max( 0, questions - answered ) > 0 ) {
		return 'blank';
	}
	return flagged > 0 ? 'flags' : '';
}

/**
 * The navigator item a key moves to.
 *
 * @param {number} count Items.
 * @param {number} index Focused item, -1 for none.
 * @param {string} key   KeyboardEvent.key.
 * @return {number} Item to focus, or -1 when the key does not move.
 */
export function stepIndex( count, index, key ) {
	if ( count < 1 ) {
		return -1;
	}
	switch ( key ) {
		case 'ArrowRight':
		case 'ArrowDown':
			return index < 0 ? 0 : ( index + 1 ) % count;
		case 'ArrowLeft':
		case 'ArrowUp':
			return index < 0 ? count - 1 : ( index - 1 + count ) % count;
		case 'Home':
			return 0;
		case 'End':
			return count - 1;
		default:
			return -1;
	}
}

/**
 * Screen reader status of a navigator item.
 *
 * @param {Object}  status          Item state.
 * @param {boolean} status.answered Whether the question has an answer.
 * @param {boolean} status.flagged  Whether it is flagged.
 * @param {Object}  strings         Translated words: answeredText, blankText, flagText.
 * @return {string} Status after the question number.
 */
export function itemStatus( { answered, flagged }, strings ) {
	return `, ${ answered ? strings.answeredText : strings.blankText }${
		flagged ? `, ${ strings.flagText }` : ''
	}`;
}

/**
 * Whether text holds math the math runtime typesets, as the math loader decides.
 *
 * @param {string} text Text content.
 * @return {boolean} Has math.
 */
export const hasMath = ( text ) =>
	String( text || '' ).includes( '[[ohmylms-math:latex:' ) ||
	/[$\\]/.test( String( text || '' ) );

/**
 * Resolve once a check passes, or false after the timeout.
 *
 * @param {() => unknown} check             Condition.
 * @param {Object}        options           Options.
 * @param {number}        options.timeout   Longest wait in milliseconds.
 * @param {number}        options.interval  Polling interval in milliseconds.
 * @param {Function}      options.setTimer  Timer function, for tests.
 * @return {Promise<boolean>} Whether the check passed.
 */
export function waitFor(
	check,
	{ timeout = 10000, interval = 100, setTimer = setTimeout } = {}
) {
	return new Promise( ( resolve ) => {
		let waited = 0;
		const poll = () => {
			if ( check() ) {
				resolve( true );
			} else if ( waited >= timeout ) {
				resolve( false );
			} else {
				waited += interval;
				setTimer( poll, interval );
			}
		};
		poll();
	} );
}
