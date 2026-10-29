import { get } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import { buildTermQuery, parseTerm, parseTermDetail } from './model';
import type { TaxonomyKind, TermQuery } from './types';

const base = ( kind: TaxonomyKind ) => `/ohmylms/v1/${ kind }`;

export function listTerms(
	kind: TaxonomyKind,
	query: TermQuery,
	signal?: AbortSignal
) {
	return getCollection( base( kind ), parseTerm, {
		signal,
		query: buildTermQuery( query ),
	} );
}

/**
 * Load one term with its linked courses. The `edit` context needs `manage_options`.
 *
 * @param kind   Taxonomy.
 * @param id     Term ID.
 * @param signal Cancels the request.
 */
export async function getTermDetail(
	kind: TaxonomyKind,
	id: number,
	signal?: AbortSignal
) {
	if ( ! Number.isSafeInteger( id ) || id < 1 ) {
		throw new RangeError( 'A term ID must be a positive integer.' );
	}
	return parseTermDetail(
		await get< unknown >( `${ base( kind ) }/${ id }`, {
			signal,
			query: { context: 'edit' },
		} )
	);
}
