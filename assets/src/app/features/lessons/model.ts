import {
	invalid,
	record,
	text,
	positiveId,
} from '../../lib/authoring-validation';

export interface Resource {
	name: string;
	url: string;
	size: string;
}
export interface LessonSummary {
	id: number;
	title: string;
	status: string;
}

export const DRIP_TYPES = [
	'enrollment-from-x-days',
	'specific-date',
	'cohort-start',
	'cohort-from-x-days',
] as const;
export type DripType = ( typeof DRIP_TYPES )[ number ];

/** When the lesson unlocks for a learner. `date` is `YYYY-MM-DD`, `time` is `HH:MM`, both site time. */
export interface Drip {
	enable: boolean;
	type: DripType;
	days: number;
	date: string;
	time: string;
}

/** Content a learner completes before the lesson opens. */
export interface Prerequisites {
	enable: boolean;
	items: { id: number; title: string }[];
}

/** Media library attachment fields of a lesson; 0 means none. */
export const MEDIA_FIELDS = [
	'cover_image_id',
	'video_id',
	'audio_id',
] as const;
export type MediaField = ( typeof MEDIA_FIELDS )[ number ];

export const VIDEO_PROVIDERS = [
	'self-hosted',
	'youtube',
	'vimeo',
	'bunny',
] as const;
export type VideoProvider = ( typeof VIDEO_PROVIDERS )[ number ];

/** Player settings and the completion rule of a video lesson. `duration` is in seconds; 0 is unknown. */
export interface LessonVideo {
	provider: VideoProvider;
	duration: number;
	completion: 'none' | 'watch_percent';
	watch_percent: number;
	poster_id: number;
	autoplay: boolean;
	loop: boolean;
	controls: boolean;
	aspect_ratio: string;
	/** Display URL of the poster, read only. */
	poster_url: string;
}

/** Bank status of an inline question as the server resolved it for the saved content. */
export type InlineStatus =
	'approved' | 'not_approved' | 'exam_only' | 'missing';

/** An inline question check in the saved lesson content. */
export interface InlineQuestion {
	position: number;
	kind: 'block' | 'shortcode';
	question_id: number;
	uuid: string;
	title: string;
	status: InlineStatus;
}

export interface Lesson extends LessonSummary, Record< MediaField, number > {
	revision: string;
	content: string;
	type: string;
	external_url: string;
	preview_enable: boolean;
	enable_comments: boolean;
	resources: Resource[];
	native_editor_url: string;
	/** A registered extension lesson type, edited through its `lesson-editor` entry. */
	custom_type: boolean;
	drip: Drip;
	prerequisites: Prerequisites;
	/** Display URLs of the attachments, read only. */
	media_urls: Record< MediaField, string >;
	video: LessonVideo;
	/** Inline question checks of the saved content, read only. */
	inline_questions: InlineQuestion[];
}

function flag( value: unknown ): boolean {
	return typeof value === 'boolean' ? value : invalid();
}

function parseVideo( value: unknown ): LessonVideo {
	const row = record( value );
	const provider = VIDEO_PROVIDERS.find( ( item ) => item === row.provider );
	const completion =
		'none' === row.completion || 'watch_percent' === row.completion
			? row.completion
			: null;
	if ( ! provider || ! completion ) {
		return invalid();
	}
	return {
		provider,
		duration: count( row.duration ),
		completion,
		watch_percent: count( row.watch_percent ),
		poster_id: count( row.poster_id ),
		autoplay: flag( row.autoplay ),
		loop: flag( row.loop ),
		controls: flag( row.controls ),
		aspect_ratio: text( row.aspect_ratio ),
		poster_url: text( row.poster_url ),
	};
}

const INLINE_STATUSES: InlineStatus[] = [
	'approved',
	'not_approved',
	'exam_only',
	'missing',
];

function parseInline( value: unknown ): InlineQuestion {
	const row = record( value );
	const status = INLINE_STATUSES.find( ( item ) => item === row.status );
	if ( ! status || ( 'block' !== row.kind && 'shortcode' !== row.kind ) ) {
		return invalid();
	}
	return {
		position: positiveId( row.position ),
		kind: row.kind,
		question_id: count( row.question_id ),
		uuid: text( row.uuid ),
		title: text( row.title ),
		status,
	};
}

export function parseSummary( value: unknown ): LessonSummary {
	const row = record( value );
	return {
		id: positiveId( row.id ),
		title: text( row.title ),
		status: text( row.status ),
	};
}

function count( value: unknown ): number {
	return Number.isSafeInteger( value ) && Number( value ) >= 0
		? Number( value )
		: invalid();
}

function parseDrip( value: unknown ): Drip {
	const row = record( value );
	const type = DRIP_TYPES.find( ( item ) => item === row.type );
	if ( typeof row.enable !== 'boolean' || ! type ) {
		return invalid();
	}
	return {
		enable: row.enable,
		type,
		days: count( row.days ),
		date: text( row.date ),
		time: text( row.time ),
	};
}

function parsePrerequisites( value: unknown ): Prerequisites {
	const row = record( value );
	if ( typeof row.enable !== 'boolean' || ! Array.isArray( row.items ) ) {
		return invalid();
	}
	return {
		enable: row.enable,
		items: row.items.map( ( item ) => {
			const entry = record( item );
			return { id: positiveId( entry.id ), title: text( entry.title ) };
		} ),
	};
}

export function parseLesson( incoming: unknown ): Lesson {
	const row = record( incoming );
	if (
		text( row.native_editor_url ) !== '' &&
		! /^https?:\/\//.test( text( row.native_editor_url ) )
	) {
		return invalid();
	}
	if (
		! text( row.revision ) ||
		typeof row.preview_enable !== 'boolean' ||
		typeof row.enable_comments !== 'boolean' ||
		typeof row.custom_type !== 'boolean' ||
		! Array.isArray( row.resources ) ||
		! Array.isArray( row.inline_questions )
	) {
		return invalid();
	}
	const urls = record( row.media_urls );
	return {
		...parseSummary( row ),
		revision: text( row.revision ),
		content: text( row.content ),
		type: text( row.type ),
		external_url: text( row.external_url ),
		preview_enable: row.preview_enable,
		enable_comments: row.enable_comments,
		native_editor_url: text( row.native_editor_url ),
		custom_type: row.custom_type,
		drip: parseDrip( row.drip ),
		prerequisites: parsePrerequisites( row.prerequisites ),
		cover_image_id: count( row.cover_image_id ),
		video_id: count( row.video_id ),
		audio_id: count( row.audio_id ),
		media_urls: {
			cover_image_id: text( urls.cover_image_id ),
			video_id: text( urls.video_id ),
			audio_id: text( urls.audio_id ),
		},
		video: parseVideo( row.video ),
		inline_questions: row.inline_questions.map( parseInline ),
		resources: row.resources.map( ( value ) => {
			const resource = record( value );
			return {
				name: text( resource.name ),
				url: text( resource.url ),
				size: text( resource.size ?? '' ),
			};
		} ),
	};
}

export function savePayload( lesson: Lesson ) {
	const {
		revision,
		title,
		content,
		type,
		external_url: externalUrl,
		preview_enable: preview,
		enable_comments: comments,
		resources,
		drip,
		prerequisites,
		cover_image_id: cover,
		video_id: video,
		audio_id: audio,
	} = lesson;
	return {
		revision,
		title,
		content,
		type,
		external_url: externalUrl,
		preview_enable: preview,
		enable_comments: comments,
		resources,
		drip,
		prerequisites: {
			enable: prerequisites.enable,
			ids: prerequisites.items.map( ( item ) => item.id ),
		},
		cover_image_id: cover,
		video_id: video,
		audio_id: audio,
		video: videoPayload( lesson.video ),
	};
}

/**
 * The writable video settings (the poster URL is display only).
 *
 * @param video Video settings.
 * @return The payload.
 */
export function videoPayload( video: LessonVideo ) {
	return {
		provider: video.provider,
		duration: video.duration,
		completion: video.completion,
		watch_percent: video.watch_percent,
		poster_id: video.poster_id,
		autoplay: video.autoplay,
		loop: video.loop,
		controls: video.controls,
		aspect_ratio: video.aspect_ratio,
	};
}

/**
 * Format seconds as `m:ss` or `h:mm:ss`.
 *
 * @param seconds Duration.
 * @return Text; empty for 0.
 */
export function formatDuration( seconds: number ): string {
	if ( seconds <= 0 ) {
		return '';
	}
	const h = Math.floor( seconds / 3600 );
	const m = Math.floor( ( seconds % 3600 ) / 60 );
	const s = seconds % 60;
	const pad = ( value: number ) => String( value ).padStart( 2, '0' );
	return h ? `${ h }:${ pad( m ) }:${ pad( s ) }` : `${ m }:${ pad( s ) }`;
}

export const RELATIONS = [ 'teaches', 'practices', 'assesses' ] as const;
export type Relation = ( typeof RELATIONS )[ number ];

/** A skill linked to the lesson. `managed` skills come from an objective mapping and leave with it. */
export interface LinkedSkill {
	id: number;
	name: string;
	code: string;
	managed: boolean;
}

/** A syllabus objective with its live wording. */
export interface ObjectiveRef {
	syllabus_id: number;
	term_id: number;
	name: string;
	code: string;
	objective: string;
	topic: string;
}

/** A mapping of the lesson to a syllabus objective. */
export interface ObjectiveMapping extends ObjectiveRef {
	relation: Relation;
	syllabus_name: string;
	/** The objective is no longer in its syllabus. */
	missing: boolean;
}

/** An objective one of the lesson's courses targets. */
export interface CourseTarget extends ObjectiveRef {
	syllabus_name: string;
	course_id: number;
	course_title: string;
}

export interface LessonObjectives {
	id: number;
	revision: string;
	skills: LinkedSkill[];
	mappings: ObjectiveMapping[];
	course_targets: CourseTarget[];
}

export interface SyllabusChoice {
	id: number;
	name: string;
	code: string;
	version: string;
	grade: string;
	subject: string;
}

export interface OptionPage< T > {
	items: T[];
	total: number;
	pages: number;
}

function list( value: unknown ): unknown[] {
	return Array.isArray( value ) ? value : invalid();
}

function objectiveRef( row: Record< string, unknown > ): ObjectiveRef {
	return {
		syllabus_id: positiveId( row.syllabus_id ),
		term_id: positiveId( row.term_id ),
		name: text( row.name ),
		code: text( row.code ),
		objective: text( row.objective ),
		topic: text( row.topic ),
	};
}

export function parseSkill( value: unknown ): LinkedSkill {
	const row = record( value );
	return {
		id: positiveId( row.id ),
		name: text( row.name ),
		code: text( row.code ),
		managed: flag( row.managed ),
	};
}

export function parseObjectiveRef( value: unknown ): ObjectiveRef {
	return objectiveRef( record( value ) );
}

export function parseObjectives( value: unknown ): LessonObjectives {
	const row = record( value );
	return {
		id: positiveId( row.id ),
		revision: text( row.revision ),
		skills: list( row.skills ).map( parseSkill ),
		mappings: list( row.mappings ).map( ( item ) => {
			const mapping = record( item );
			const relation = RELATIONS.find(
				( entry ) => entry === mapping.relation
			);
			return relation
				? {
						...objectiveRef( mapping ),
						relation,
						syllabus_name: text( mapping.syllabus_name ),
						missing: flag( mapping.missing ),
					}
				: invalid();
		} ),
		course_targets: list( row.course_targets ).map( ( item ) => {
			const target = record( item );
			return {
				...objectiveRef( target ),
				syllabus_name: text( target.syllabus_name ),
				course_id: positiveId( target.course_id ),
				course_title: text( target.course_title ),
			};
		} ),
	};
}

export function parseSyllabus( value: unknown ): SyllabusChoice {
	const row = record( value );
	return {
		id: positiveId( row.id ),
		name: text( row.name ),
		code: text( row.code ),
		version: text( row.version ),
		grade: text( row.grade ),
		subject: text( row.subject ),
	};
}

export function parseOptionPage< T >(
	value: unknown,
	parse: ( item: unknown ) => T
): OptionPage< T > {
	const row = record( value );
	return {
		items: list( row.items ).map( parse ),
		total: count( row.total ),
		pages: count( row.pages ),
	};
}

/**
 * The objectives save payload: explicit skills only (managed ones follow their mappings).
 *
 * @param state Edited objectives.
 * @return The request body.
 */
export function objectivesPayload( state: LessonObjectives ) {
	return {
		revision: state.revision,
		skill_ids: state.skills
			.filter( ( skill ) => ! skill.managed )
			.map( ( skill ) => skill.id ),
		mappings: state.mappings.map( ( mapping ) => ( {
			syllabus_id: mapping.syllabus_id,
			term_id: mapping.term_id,
			relation: mapping.relation,
		} ) ),
	};
}
