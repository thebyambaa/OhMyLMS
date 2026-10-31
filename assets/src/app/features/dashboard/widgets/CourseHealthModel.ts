import { invalidResponse, positiveId, record } from '../model';

export interface CourseDropOff {
	readonly contentId: number;
	readonly title: string;
	/** `lesson`, `quiz`, `assignment` or `content`. */
	readonly type: string;
	/** Position among the course's published items, from 1. */
	readonly position: number;
	readonly lost: number;
	/** Share of enrolled learners who stop there, 0-100. */
	readonly percent: number;
}

export interface CourseHealthRow {
	readonly id: number;
	readonly title: string;
	readonly enrolled: number;
	readonly completed: number;
	/** 0-100. */
	readonly completion: number;
	readonly rating: {
		readonly average: number;
		readonly count: number;
	} | null;
	/** 0-100, or null without enrolments. */
	readonly medianProgress: number | null;
	readonly dropOff: CourseDropOff | null;
	/** Course report route the user may open, or null. */
	readonly report: string | null;
}

export interface CourseHealth {
	readonly courses: readonly CourseHealthRow[];
	/** Courses of the scope with learners (the list holds at most `limit`). */
	readonly total: number;
	readonly limit: number;
}

function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function whole( value: unknown, max = Number.MAX_SAFE_INTEGER ): number {
	if (
		'number' !== typeof value ||
		! Number.isSafeInteger( value ) ||
		value < 0 ||
		value > max
	) {
		throw invalidResponse();
	}
	return value;
}

function parseRating( value: unknown ): CourseHealthRow[ 'rating' ] {
	if ( null === value || undefined === value ) {
		return null;
	}
	const item = record( value );
	const average = item.average;
	if (
		'number' !== typeof average ||
		! Number.isFinite( average ) ||
		average < 1 ||
		average > 5
	) {
		throw invalidResponse();
	}
	return { average, count: whole( item.count ) };
}

function parseDropOff( value: unknown ): CourseDropOff | null {
	if ( null === value || undefined === value ) {
		return null;
	}
	const item = record( value );
	return {
		contentId: positiveId( item.content_id ),
		title: text( item.title ),
		type: text( item.type ),
		position: whole( item.position ),
		lost: whole( item.lost ),
		percent: whole( item.percent, 100 ),
	};
}

function parseRow( value: unknown ): CourseHealthRow {
	const item = record( value );
	const report =
		null === item.report || undefined === item.report
			? null
			: text( item.report );
	if ( null !== report && ! report.startsWith( '/' ) ) {
		throw invalidResponse();
	}
	return {
		id: positiveId( item.id ),
		title: text( item.title ),
		enrolled: whole( item.enrolled ),
		completed: whole( item.completed ),
		completion: whole( item.completion, 100 ),
		rating: parseRating( item.rating ),
		medianProgress:
			null === item.median_progress
				? null
				: whole( item.median_progress, 100 ),
		dropOff: parseDropOff( item.drop_off ),
		report,
	};
}

/**
 * Validate the `course-health` widget data.
 *
 * @param value Decoded `data`.
 * @return The courses.
 */
export function parseCourseHealth( value: unknown ): CourseHealth {
	const data = record( value );
	if ( ! Array.isArray( data.courses ) ) {
		throw invalidResponse();
	}
	return {
		courses: data.courses.map( parseRow ),
		total: whole( data.total ),
		limit: whole( data.limit ),
	};
}

/** Health band of a course from its completion rate (and rating, when there is one). */
export type HealthBand = 'good' | 'fair' | 'poor';

/**
 * Band used for the colour and the text label of a row: poor below 30% completion or a rating under 3,
 * good from 60% completion with no rating under 4, fair otherwise.
 *
 * @param row Course.
 * @return Band.
 */
export function healthBand( row: CourseHealthRow ): HealthBand {
	const rating = row.rating?.average ?? null;
	if ( row.completion < 30 || ( null !== rating && rating < 3 ) ) {
		return 'poor';
	}
	if ( row.completion >= 60 && ( null === rating || rating >= 4 ) ) {
		return 'good';
	}
	return 'fair';
}
