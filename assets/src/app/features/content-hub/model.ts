import { __, _n, sprintf } from '@wordpress/i18n';
import {
	invalid,
	positiveId,
	record,
	text,
} from '../../lib/authoring-validation';
import type {
	AreaId,
	AreaSummary,
	CountKey,
	Overview,
	RecentArea,
	RecentItem,
} from './types';

/** Every area in display order, with the app route that opens it and the capability that route needs. */
export const AREAS: readonly {
	id: AreaId;
	path: string;
	capability: string;
}[] = [
	{ id: 'courses', path: '/courses', capability: 'edit_posts' },
	{
		id: 'lessons',
		path: '/content-hub/lessons',
		capability: 'manage_options',
	},
	{ id: 'quizzes', path: '/quizzes', capability: 'edit_posts' },
	{ id: 'exams', path: '/exams', capability: 'edit_posts' },
	{
		id: 'question-bank',
		path: '/content-hub/question-bank',
		capability: 'edit_posts',
	},
	{ id: 'assignments', path: '/assignments', capability: 'edit_posts' },
	{ id: 'skills', path: '/content-hub/skills', capability: 'edit_posts' },
	{
		id: 'curriculum',
		path: '/content-hub/curriculum',
		capability: 'manage_options',
	},
	{
		id: 'bundles',
		path: '/content-hub/bundles',
		capability: 'manage_options',
	},
];

/** Figures each area may report, in display order. */
const AREA_COUNTS: Record< AreaId, readonly CountKey[] > = {
	courses: [ 'published', 'drafts' ],
	lessons: [ 'published', 'drafts' ],
	quizzes: [ 'published', 'drafts', 'to_grade' ],
	exams: [ 'published', 'drafts' ],
	'question-bank': [ 'published', 'drafts' ],
	assignments: [ 'published', 'drafts' ],
	skills: [ 'archived' ],
	curriculum: [ 'syllabuses' ],
	bundles: [ 'published', 'drafts' ],
};

const RECENT_AREAS: readonly RecentArea[] = [
	'courses',
	'lessons',
	'quizzes',
	'exams',
	'question-bank',
	'assignments',
];

const RECENT_STATUSES: readonly RecentItem[ 'status' ][] = [
	'publish',
	'draft',
	'future',
	'private',
];

function count( value: unknown ): number {
	return Number.isSafeInteger( value ) && Number( value ) >= 0
		? Number( value )
		: invalid();
}

function oneOf< T extends string >( value: unknown, allowed: readonly T[] ): T {
	const found = allowed.find( ( item ) => item === value );
	return undefined === found ? invalid() : found;
}

function list( value: unknown ): unknown[] {
	return Array.isArray( value ) ? value : invalid();
}

function parseArea( value: unknown ): AreaSummary {
	const row = record( value );
	const id = oneOf(
		row.id,
		AREAS.map( ( area ) => area.id )
	);
	const raw = record( row.counts );
	const counts: AreaSummary[ 'counts' ] = {};
	for ( const key of AREA_COUNTS[ id ] ) {
		if ( undefined !== raw[ key ] ) {
			counts[ key ] = count( raw[ key ] );
		}
	}
	return { id, total: count( row.total ), counts };
}

function parseRecent( value: unknown ): RecentItem {
	const row = record( value );
	const updated = row.updated_at;
	if (
		null !== updated &&
		( 'string' !== typeof updated || Number.isNaN( Date.parse( updated ) ) )
	) {
		return invalid();
	}
	return {
		area: oneOf( row.area, RECENT_AREAS ),
		id: positiveId( row.id ),
		title: text( row.title ),
		status: oneOf( row.status, RECENT_STATUSES ),
		updated_at: updated,
	};
}

/**
 * Validate `GET /content-hub/overview`.
 *
 * @param value Parsed JSON.
 * @return Areas (each id once) and recent items.
 */
export function parseOverview( value: unknown ): Overview {
	const body = record( value );
	const areas = list( body.areas ).map( parseArea );
	if ( new Set( areas.map( ( area ) => area.id ) ).size !== areas.length ) {
		return invalid();
	}
	return { areas, recent: list( body.recent ).map( parseRecent ) };
}

/**
 * Translated name of an area.
 *
 * @param id Area id.
 * @return Label.
 */
export function areaLabel( id: AreaId ): string {
	return {
		courses: __( 'Courses', 'ohmylms' ),
		lessons: __( 'Lessons', 'ohmylms' ),
		quizzes: __( 'Quizzes', 'ohmylms' ),
		exams: __( 'Exams', 'ohmylms' ),
		'question-bank': __( 'Question bank', 'ohmylms' ),
		assignments: __( 'Assignments', 'ohmylms' ),
		skills: __( 'Skills', 'ohmylms' ),
		curriculum: __( 'Curriculum', 'ohmylms' ),
		bundles: __( 'Course bundles', 'ohmylms' ),
	}[ id ];
}

/**
 * The details of an area as short phrases, such as "3 published".
 *
 * @param area Area summary.
 * @return Phrases in display order; figures the server did not send are left out.
 */
export function countPhrases( area: AreaSummary ): string[] {
	const phrases: string[] = [];
	for ( const key of AREA_COUNTS[ area.id ] ) {
		const value = area.counts[ key ];
		if ( undefined === value ) {
			continue;
		}
		const phrase = {
			/* translators: %d: number of published items. */
			published: _n( '%d published', '%d published', value, 'ohmylms' ),
			/* translators: %d: number of drafts. */
			drafts: _n( '%d draft', '%d drafts', value, 'ohmylms' ),
			/* translators: %d: number of quiz attempts waiting for grading. */
			to_grade: _n(
				'%d attempt to grade',
				'%d attempts to grade',
				value,
				'ohmylms'
			),
			/* translators: %d: number of archived skills. */
			archived: _n( '%d archived', '%d archived', value, 'ohmylms' ),
			/* translators: %d: number of syllabuses. */
			syllabuses: _n( '%d syllabus', '%d syllabuses', value, 'ohmylms' ),
		}[ key ];
		phrases.push( sprintf( phrase, value ) );
	}
	return phrases;
}

/**
 * App route of a recent item's editor.
 *
 * @param item Recent item.
 * @return Route such as `/quizzes/12`.
 */
export function recentPath( item: RecentItem ): string {
	return {
		courses: `/courses/${ item.id }`,
		lessons: `/content-hub/lessons/${ item.id }`,
		quizzes: `/quizzes/${ item.id }`,
		exams: `/exams/${ item.id }`,
		'question-bank': `/content-hub/question-bank/${ item.id }`,
		assignments: `/assignments/${ item.id }`,
	}[ item.area ];
}

/**
 * Translated publication status of a recent item.
 *
 * @param status Status.
 * @return Label.
 */
export function statusLabel( status: RecentItem[ 'status' ] ): string {
	return {
		publish: __( 'Published', 'ohmylms' ),
		draft: __( 'Draft', 'ohmylms' ),
		future: __( 'Scheduled', 'ohmylms' ),
		private: __( 'Private', 'ohmylms' ),
	}[ status ];
}

/**
 * Format a UTC time for display in the browser's locale.
 *
 * @param iso UTC ISO 8601, or null.
 * @return Date and time, or a dash.
 */
export function formatTime( iso: string | null ): string {
	if ( ! iso ) {
		return '—';
	}
	return new Intl.DateTimeFormat( undefined, {
		dateStyle: 'medium',
		timeStyle: 'short',
	} ).format( new Date( iso ) );
}
