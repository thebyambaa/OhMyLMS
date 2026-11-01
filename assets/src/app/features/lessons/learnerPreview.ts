import { post } from '../../lib/api';
import {
	invalid,
	positiveId,
	record,
	text,
} from '../../lib/authoring-validation';

/** Signed "Preview as learner" links (`POST /ohmylms/v1/lessons/:id/learner-preview`). */
export interface LearnerPreview {
	id: number;
	url: string;
	bypass_url: string;
	/** Unix time after which both links are refused. */
	expires: number;
	status: string;
}

function httpUrl( value: unknown ): string {
	const url = text( value );
	return /^https?:\/\//.test( url ) ? url : invalid();
}

export function parseLearnerPreview( value: unknown ): LearnerPreview {
	const row = record( value );
	return {
		id: positiveId( row.id ),
		url: httpUrl( row.url ),
		bypass_url: httpUrl( row.bypass_url ),
		expires: positiveId( row.expires ),
		status: text( row.status ),
	};
}

/**
 * Mint the preview links of a lesson for the current user.
 *
 * @param id     Lesson ID.
 * @param signal Cancels the request.
 * @return The validated links.
 */
export async function createLearnerPreview( id: number, signal?: AbortSignal ) {
	const result = parseLearnerPreview(
		await post< unknown >(
			`/ohmylms/v1/lessons/${ id }/learner-preview`,
			undefined,
			{ signal }
		)
	);
	return result.id === id ? result : invalid();
}
