// @ts-check
/**
 * Pure helpers of the `ohmylms/video` store: watched intervals, watched share, resume position,
 * time labels, the save cadence and the keyboard shortcuts. No DOM, no runtime: tests/js/video.test.mjs
 * drives them in node. The server repeats the interval arithmetic
 * (OhMyLMS\Learning\VideoCompletion::merge_intervals()) and stays the authority for completion.
 */

/** @typedef {[number, number]} Interval Watched span `[start, end]` in seconds, start < end. */

/** Gap (seconds) under which two watched spans are joined. */
export const JOIN_GAP = 0.25;

/** Most spans sent in one report (the server refuses more). */
export const MAX_INTERVALS = 500;

/** Playback speeds offered by the custom controls. */
export const RATES = Object.freeze( [ 0.5, 0.75, 1, 1.25, 1.5, 2 ] );

const finite = ( value ) =>
	'number' === typeof value && Number.isFinite( value );

/**
 * Round to hundredths, the precision the progress table stores.
 *
 * @param {number} value Seconds.
 * @return {number} Rounded seconds.
 */
export const round2 = ( value ) => Math.round( value * 100 ) / 100;

/**
 * Sorted, disjoint copy of a list of spans: invalid ones dropped, overlapping or nearly touching
 * ones (gap up to `JOIN_GAP`) joined.
 *
 * @param {ReadonlyArray<unknown>} list Spans (`[start, end]` pairs).
 * @param {number}                 gap  Join gap in seconds.
 * @return {Interval[]} Merged spans.
 */
export function mergeIntervals( list, gap = JOIN_GAP ) {
	/** @type {Interval[]} */
	const spans = [];
	for ( const item of Array.isArray( list ) ? list : [] ) {
		if ( ! Array.isArray( item ) ) {
			continue;
		}
		const start = Math.max( 0, Number( item[ 0 ] ) );
		const end = Number( item[ 1 ] );
		if ( finite( start ) && finite( end ) && end > start ) {
			spans.push( [ start, end ] );
		}
	}
	spans.sort( ( a, b ) => a[ 0 ] - b[ 0 ] || a[ 1 ] - b[ 1 ] );
	/** @type {Interval[]} */
	const merged = [];
	for ( const span of spans ) {
		const last = merged[ merged.length - 1 ];
		if ( last && span[ 0 ] <= last[ 1 ] + gap ) {
			last[ 1 ] = Math.max( last[ 1 ], span[ 1 ] );
		} else {
			merged.push( [ span[ 0 ], span[ 1 ] ] );
		}
	}
	return merged;
}

/**
 * Add one span to a merged list.
 *
 * @param {ReadonlyArray<Interval>} list Merged spans.
 * @param {Interval}                span Span to add.
 * @return {Interval[]} New merged list.
 */
export const addInterval = ( list, span ) =>
	mergeIntervals( [ ...list, span ] );

/**
 * Seconds covered by merged spans.
 *
 * @param {ReadonlyArray<Interval>} list Merged spans.
 * @return {number} Seconds.
 */
export const measure = ( list ) =>
	list.reduce( ( total, [ start, end ] ) => total + ( end - start ), 0 );

/**
 * Spans limited to `[0, total]` (a player may report a little past the end).
 *
 * @param {ReadonlyArray<Interval>} list  Merged spans.
 * @param {number}                  total Duration; 0 or less leaves them as they are.
 * @return {Interval[]} Clamped spans.
 */
export function clampIntervals( list, total ) {
	if ( ! ( total > 0 ) ) {
		return list.map( ( [ start, end ] ) => [ start, end ] );
	}
	return mergeIntervals(
		list.map( ( [ start, end ] ) => [
			Math.min( start, total ),
			Math.min( end, total ),
		] )
	);
}

/**
 * Watched share in percent, 0 to 100 with two decimals.
 *
 * @param {number} watched Watched seconds.
 * @param {number} total   Duration in seconds.
 * @return {number} Percent.
 */
export function percentOf( watched, total ) {
	if ( ! ( total > 0 ) || ! ( watched > 0 ) ) {
		return 0;
	}
	return round2( Math.min( 100, ( watched / total ) * 100 ) );
}

/**
 * Where to resume: the saved position unless it is the very start, the end, or the video was
 * already completed (a completed video starts over).
 *
 * @param {number}  saved     Saved position in seconds.
 * @param {number}  duration  Duration in seconds, 0 when unknown yet.
 * @param {boolean} completed Whether the server counts the video as watched.
 * @return {number} Position to seek to, 0 to start from the beginning.
 */
export function resumeAt( saved, duration = 0, completed = false ) {
	const position = Number( saved );
	if ( completed || ! finite( position ) || position <= 1 ) {
		return 0;
	}
	if ( duration > 0 && position >= duration - 3 ) {
		return 0;
	}
	return position;
}

/**
 * Clock label as the old player printed it: `m:ss`, or `h:mm:ss` from one hour.
 *
 * @param {number} seconds Seconds.
 * @return {string} Label.
 */
export function formatTime( seconds ) {
	const total = finite( seconds ) && seconds > 0 ? Math.round( seconds ) : 0;
	const h = Math.floor( total / 3600 );
	const m = Math.floor( ( total % 3600 ) / 60 );
	const s = total % 60;
	const pad = ( value ) => String( value ).padStart( 2, '0' );
	return h > 0
		? `${ h }:${ pad( m ) }:${ pad( s ) }`
		: `${ m }:${ pad( s ) }`;
}

/**
 * Spoken value of the seek slider, e.g. "1:05 of 10:00".
 *
 * @param {number} time     Current time.
 * @param {number} duration Duration.
 * @param {string} template Translated template with `%1$s` (time) and `%2$s` (duration).
 * @return {string} Text.
 */
export function timeText( time, duration, template = '%1$s of %2$s' ) {
	return template
		.replace( '%1$s', formatTime( time ) )
		.replace( '%2$s', duration > 0 ? formatTime( duration ) : '--:--' );
}

/**
 * Watched-span log of one page view. A span is counted only between two consecutive positions
 * that played continuously: the media advanced by no more than the wall-clock time at the
 * playback speed (plus a tolerance), so seeking ahead never counts as watching.
 *
 * @param {Object} [options]           Options.
 * @param {number} [options.tolerance] Allowed lag in seconds between media and wall clock.
 * @return {{tick: (time: number, at: number, rate?: number) => boolean, jump: (time: number, at: number) => void, stop: () => void, intervals: () => Interval[], watched: () => number}} Log.
 */
export function createWatchLog( { tolerance = 1.5 } = {} ) {
	/** @type {Interval[]} */
	let spans = [];
	/** @type {{time: number, at: number}|null} */
	let last = null;
	return {
		tick( time, at, rate = 1 ) {
			if ( ! finite( time ) || ! finite( at ) ) {
				return false;
			}
			let added = false;
			if ( last ) {
				const media = time - last.time;
				const wall = Math.max( 0, at - last.at ) / 1000;
				const speed = finite( rate ) && rate > 0 ? rate : 1;
				if ( media > 0 && media <= wall * speed + tolerance ) {
					spans = addInterval( spans, [ last.time, time ] );
					added = true;
				}
			}
			last = { time, at };
			return added;
		},
		jump( time, at ) {
			last = finite( time ) && finite( at ) ? { time, at } : null;
		},
		stop() {
			last = null;
		},
		intervals: () => spans.map( ( [ start, end ] ) => [ start, end ] ),
		watched: () => measure( spans ),
	};
}

/**
 * Fields of `ohmylms_save_video_progress`. `segments` is what the server merges with what it
 * already has; `watched_duration` (this page view only) is kept for older servers.
 *
 * @param {Object}                  report           Report.
 * @param {number}                  report.lessonId  Lesson.
 * @param {number}                  report.courseId  Course (the server derives its own).
 * @param {number}                  report.duration  Duration reported by the player.
 * @param {number}                  report.position  Current position.
 * @param {ReadonlyArray<Interval>} report.intervals Watched spans of this page view.
 * @return {Object<string, string|number>} POST fields.
 */
export function progressFields( {
	lessonId,
	courseId,
	duration,
	position,
	intervals,
} ) {
	const spans = clampIntervals( intervals, duration )
		.slice( -MAX_INTERVALS )
		.map( ( [ start, end ] ) => [ round2( start ), round2( end ) ] );
	return {
		lesson_id: lessonId,
		course_id: courseId,
		watched_duration: round2( measure( spans ) ),
		total_duration: round2( duration > 0 ? duration : 0 ),
		last_position: round2(
			Math.max(
				0,
				Math.min( position, duration > 0 ? duration : position )
			)
		),
		segments: JSON.stringify( spans ),
	};
}

/**
 * Save cadence while playing: at most one periodic save per `interval`, and only when something
 * new was watched or the position moved by a second or more since the last save.
 *
 * @param {number} interval Milliseconds between periodic saves (`due()` may pass a shorter one).
 * @return {{due: (at: number, watched: number, position: number, every?: number) => boolean, saved: (at: number, watched: number, position: number) => void, changed: (watched: number, position: number) => boolean}} Clock.
 */
export function createSaveClock( interval = 15000 ) {
	let lastAt = -Infinity;
	let lastWatched = 0;
	let lastPosition = -Infinity;
	const changed = ( watched, position ) =>
		watched - lastWatched >= 0.5 ||
		Math.abs( position - lastPosition ) >= 1;
	return {
		changed,
		due: ( at, watched, position, every = interval ) =>
			at - lastAt >= every && changed( watched, position ),
		saved( at, watched, position ) {
			lastAt = at;
			lastWatched = watched;
			lastPosition = position;
		},
	};
}

/**
 * Keyboard shortcut of the custom player for a keydown on its root, `null` when the key is not
 * one, or belongs to the focused control (sliders, menus, buttons and form fields keep theirs).
 *
 * @param {{key: string, ctrlKey?: boolean, metaKey?: boolean, altKey?: boolean}} event  Keydown.
 * @param {{tag?: string, role?: string|null, editable?: boolean}}                target Focused element.
 * @return {null|{action: string, value?: number}} Shortcut.
 */
export function shortcut( event, target = {} ) {
	if ( event.ctrlKey || event.metaKey || event.altKey ) {
		return null;
	}
	const tag = String( target.tag || '' ).toUpperCase();
	const role = target.role || '';
	if (
		target.editable ||
		[ 'INPUT', 'TEXTAREA', 'SELECT' ].includes( tag ) ||
		[ 'slider', 'menu', 'menuitemradio', 'menuitem' ].includes( role )
	) {
		return null;
	}
	const onButton = 'BUTTON' === tag || 'A' === tag || 'button' === role;
	switch ( event.key ) {
		case ' ':
		case 'Spacebar':
			return onButton ? null : { action: 'toggle' };
		case 'k':
		case 'K':
			return { action: 'toggle' };
		case 'ArrowLeft':
		case 'j':
		case 'J':
			return { action: 'seekBy', value: -5 };
		case 'ArrowRight':
		case 'l':
		case 'L':
			return { action: 'seekBy', value: 5 };
		case 'ArrowUp':
			return { action: 'volumeBy', value: 0.1 };
		case 'ArrowDown':
			return { action: 'volumeBy', value: -0.1 };
		case 'm':
		case 'M':
			return { action: 'mute' };
		case 'f':
		case 'F':
			return { action: 'fullscreen' };
		case 'c':
		case 'C':
			return { action: 'captions' };
		case 'Home':
			return { action: 'seekTo', value: 0 };
		case 'End':
			return { action: 'seekPercent', value: 100 };
		default:
			return /^[0-9]$/.test( event.key )
				? { action: 'seekPercent', value: Number( event.key ) * 10 }
				: null;
	}
}

/**
 * New value of a slider for a key: arrows step, Page keys step ten times, Home/End go to the ends.
 *
 * @param {string} key   Key.
 * @param {number} value Current value.
 * @param {number} max   Maximum (minimum is 0).
 * @param {number} step  Arrow step.
 * @return {number|null} New value, null when the key does not move the slider.
 */
export function sliderValue( key, value, max, step ) {
	const clamp = ( next ) => Math.max( 0, Math.min( max, next ) );
	switch ( key ) {
		case 'ArrowRight':
		case 'ArrowUp':
			return clamp( value + step );
		case 'ArrowLeft':
		case 'ArrowDown':
			return clamp( value - step );
		case 'PageUp':
			return clamp( value + step * 10 );
		case 'PageDown':
			return clamp( value - step * 10 );
		case 'Home':
			return 0;
		case 'End':
			return max;
		default:
			return null;
	}
}

/**
 * Share of a horizontal track under the pointer, 0 to 1.
 *
 * @param {number}                        clientX Pointer x.
 * @param {{left: number, width: number}} rect    Track box.
 * @return {number} Ratio.
 */
export function pointerRatio( clientX, rect ) {
	if ( ! rect || ! ( rect.width > 0 ) ) {
		return 0;
	}
	return Math.max( 0, Math.min( 1, ( clientX - rect.left ) / rect.width ) );
}

/**
 * Next menu item index for a key in a vertical menu (wraps), null for other keys.
 *
 * @param {string} key     Key.
 * @param {number} current Current index.
 * @param {number} count   Items.
 * @return {number|null} Index.
 */
export function menuIndex( key, current, count ) {
	if ( count < 1 ) {
		return null;
	}
	switch ( key ) {
		case 'ArrowDown':
			return ( current + 1 ) % count;
		case 'ArrowUp':
			return ( current - 1 + count ) % count;
		case 'Home':
			return 0;
		case 'End':
			return count - 1;
		default:
			return null;
	}
}
