import { setLessonSkills } from '../content-hub/api.mjs';

/**
 * One dialog's creation operation. Keep the draft if linking fails so Retry never creates a duplicate.
 * @param skillId
 * @param fetch
 */
export function skillLessonCreator(
	skillId,
	fetch = ( options ) => window.wp.apiFetch( options )
) {
	let draft;
	return async ( title ) => {
		if ( ! draft ) {
			draft = await fetch( {
				path: '/ohmylms/v1/lessons',
				method: 'POST',
				data: { name: title.trim(), status: 'draft', type: 'text' },
			} );
		}
		// Change only the new lesson's tags, preserving every existing lesson linked to the skill.
		await setLessonSkills( draft.id, [ skillId ], fetch );
		return draft;
	};
}
