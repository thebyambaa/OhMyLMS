import { createBankQuestion, saveSkillMap } from './api.mjs';

/**
 * Retry linking the same question when mapping fails, without creating another draft.
 * @param skillId
 * @param fetch
 */
export function questionCreator(
	skillId,
	fetch = ( options ) => window.wp.apiFetch( options )
) {
	let created;
	let parts;
	return async ( payload ) => {
		if ( ! created ) {
			created = await createBankQuestion( payload, fetch );
			parts = payload.settings?.parts || [ { id: 'p1' } ];
		}
		if ( skillId ) {
			const map = Object.fromEntries(
				parts.map( ( part ) => [
					part.id,
					{ primary: skillId, supporting: [] },
				] )
			);
			await saveSkillMap( created.id, map, fetch );
		}
		return created;
	};
}
