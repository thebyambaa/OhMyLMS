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
export interface Lesson extends LessonSummary {
	revision: string;
	content: string;
	type: string;
	external_url: string;
	preview_enable: boolean;
	enable_comments: boolean;
	resources: Resource[];
	native_editor_url: string;
}

export function parseSummary( value: unknown ): LessonSummary {
	const row = record( value );
	return {
		id: positiveId( row.id ),
		title: text( row.title ),
		status: text( row.status ),
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
		! Array.isArray( row.resources )
	) {
		return invalid();
	}
	return {
		...parseSummary( row ),
		revision: text( row.revision ),
		content: text( row.content ),
		type: text( row.type ),
		external_url: text( row.external_url ),
		preview_enable: row.preview_enable,
		enable_comments: row.enable_comments,
		native_editor_url: text( row.native_editor_url ),
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
	};
}
