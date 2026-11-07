import { get, post } from '../../lib/api';
import { record } from '../../lib/validation';
import { parseAssignments, parseTerm, parseTerms } from './model';
import type { BankTerm, BankTerms, OrganizeRequest } from './types';

const BASE = '/ohmylms/v1/content-bank';
const BAD = 'The server sent folders or tags this screen cannot read.';

/**
 * Every folder and tag.
 *
 * @param signal Cancels the request.
 */
export async function listBankTerms(
	signal?: AbortSignal
): Promise< BankTerms > {
	return parseTerms( await get< unknown >( `${ BASE }/terms`, { signal } ) );
}

/**
 * Create a folder (optionally inside another) or a tag.
 *
 * @param kind   `folder` or `tag`.
 * @param name   Display name.
 * @param parent Parent folder ID; 0 for the top level.
 * @param signal Cancels the request.
 */
export async function createBankTerm(
	kind: 'folder' | 'tag',
	name: string,
	parent: number,
	signal?: AbortSignal
): Promise< BankTerm > {
	return parseTerm(
		await post< unknown >(
			`${ BASE }/terms`,
			{ kind, name, ...( 'folder' === kind ? { parent } : {} ) },
			{ signal }
		)
	);
}

/**
 * Folder and tag IDs of the given items.
 *
 * @param ids    Item IDs.
 * @param signal Cancels the request.
 */
export async function readAssignments(
	ids: readonly number[],
	signal?: AbortSignal
) {
	return parseAssignments(
		await get< unknown >( `${ BASE }/assignments`, {
			signal,
			query: { ids: ids.join( ',' ) },
		} )
	);
}

/**
 * Add, replace or remove folders and tags on items.
 *
 * @param change What to apply.
 * @param signal Cancels the request.
 * @return IDs updated and skipped.
 */
export async function organizeItems(
	change: OrganizeRequest,
	signal?: AbortSignal
) {
	const row = record(
		await post< unknown >( `${ BASE }/organize`, change, { signal } ),
		BAD
	);
	return {
		updated: Array.isArray( row.updated ) ? row.updated.map( Number ) : [],
		skipped: Array.isArray( row.skipped ) ? row.skipped.length : 0,
	};
}
