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
		! Array.isArray( row.resources )
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
	};
}
