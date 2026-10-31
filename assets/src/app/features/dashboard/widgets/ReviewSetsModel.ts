import { invalidResponse, record } from '../model';

export interface ReviewSet {
	readonly id: number;
	readonly title: string;
	readonly status: string;
	/** Quiz editor route, or null when the user may not edit it. */
	readonly route: string | null;
}

export interface ReviewSetGroup {
	/** `class:<id>|skill:<id>`. */
	readonly key: string;
	readonly classInfo: {
		readonly id: number;
		/** Heatmap row key (`class:9`). */
		readonly key: string;
		readonly label: string;
		readonly learners: number;
	};
	readonly skill: {
		readonly id: number;
		readonly name: string;
		readonly code: string;
	};
	/** Learners of the class below Familiar on the skill. */
	readonly learners: number;
	readonly questions: {
		/** Different approved questions in the bank. */
		readonly available: number;
		readonly needed: number;
		/** Questions a review set would draw. */
		readonly draw: number;
		readonly enough: boolean;
	};
	/** The draft that already exists, or null. */
	readonly reviewSet: ReviewSet | null;
}

/** `GET /dashboard/widgets/review-sets`. */
export interface ReviewSets {
	readonly rowType: 'class' | 'course';
	readonly minGroup: number;
	readonly groups: readonly ReviewSetGroup[];
	/** Groups before the bound. */
	readonly total: number;
	/** Whether the user may create review set drafts (quiz authors, and school staff for their classes). */
	readonly canAssign: boolean;
}

export interface AssignResult {
	readonly created: boolean;
	readonly reviewSet: ReviewSet;
}

function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function whole( value: unknown ): number {
	if (
		'number' !== typeof value ||
		! Number.isSafeInteger( value ) ||
		value < 0
	) {
		throw invalidResponse();
	}
	return value;
}

function flag( value: unknown ): boolean {
	if ( 'boolean' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function parseReviewSet( value: unknown ): ReviewSet {
	const set = record( value );
	let route: string | null = null;
	if ( null !== set.route && undefined !== set.route ) {
		route = text( set.route );
		if ( ! /^\/quizzes\/\d+$/.test( route ) ) {
			throw invalidResponse();
		}
	}
	return {
		id: whole( set.id ),
		title: text( set.title ),
		status: text( set.status ),
		route,
	};
}

/**
 * Validate the `review-sets` widget data.
 *
 * @param value Decoded `data`.
 * @return The recommendations.
 */
export function parseReviewSets( value: unknown ): ReviewSets {
	const data = record( value );
	if ( 'class' !== data.row_type && 'course' !== data.row_type ) {
		throw invalidResponse();
	}
	if ( ! Array.isArray( data.groups ) ) {
		throw invalidResponse();
	}
	return {
		rowType: data.row_type,
		minGroup: whole( data.min_group ),
		total: whole( data.total ),
		canAssign: flag( data.can_assign ),
		groups: data.groups.map( ( item ): ReviewSetGroup => {
			const group = record( item );
			const classInfo = record( group.class );
			const skill = record( group.skill );
			const questions = record( group.questions );
			const key = text( group.key );
			const classKey = text( classInfo.key );
			if (
				! /^class:\d+\|skill:\d+$/.test( key ) ||
				! /^class:\d+$/.test( classKey )
			) {
				throw invalidResponse();
			}
			return {
				key,
				classInfo: {
					id: whole( classInfo.id ),
					key: classKey,
					label: text( classInfo.label ),
					learners: whole( classInfo.learners ),
				},
				skill: {
					id: whole( skill.id ),
					name: text( skill.name ),
					code: text( skill.code ),
				},
				learners: whole( group.learners ),
				questions: {
					available: whole( questions.available ),
					needed: whole( questions.needed ),
					draw: whole( questions.draw ),
					enough: flag( questions.enough ),
				},
				reviewSet:
					null === group.review_set || undefined === group.review_set
						? null
						: parseReviewSet( group.review_set ),
			};
		} ),
	};
}

/**
 * Validate the answer of `POST /dashboard/widgets/review-sets/assign`.
 *
 * @param value Decoded response.
 * @return Whether it was created now, and the review set.
 */
export function parseAssignResult( value: unknown ): AssignResult {
	const data = record( value );
	return {
		created: flag( data.created ),
		reviewSet: parseReviewSet( data.review_set ),
	};
}
