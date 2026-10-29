import { __ } from '@wordpress/i18n';
import { ApiError } from '../../lib/api';
import type { QueryParams } from '../../lib/api';
import type {
	LinkedCourse,
	TaxonomyKind,
	Term,
	TermDetail,
	TermQuery,
} from './types';

export const PER_PAGE = 20;
export const EMPTY_QUERY: TermQuery = { page: 1, search: '' };

export interface TaxonomyInfo {
	kind: TaxonomyKind;
	title: string;
	/** What the taxonomy is now, and where it is edited. */
	replacedBy: string;
	/** Relative admin URL of the screen that edits it. */
	manageUrl: string;
	manageLabel: string;
}

/**
 * The taxonomies in tab order, with the screen that now owns each.
 *
 * @return Translated descriptors.
 */
export function getTaxonomies(): TaxonomyInfo[] {
	return [
		{
			kind: 'categories',
			title: __( 'Categories', 'ohmylms' ),
			replacedBy: __(
				'Course categories are now curriculum items. This list is read-only.',
				'ohmylms'
			),
			manageUrl: 'admin.php?page=ohmylms#/extensions/curriculum',
			manageLabel: __( 'Manage the curriculum', 'ohmylms' ),
		},
		{
			kind: 'tags',
			title: __( 'Tags', 'ohmylms' ),
			replacedBy: __(
				'Course tags are now course bundles. This list is read-only.',
				'ohmylms'
			),
			manageUrl: 'admin.php?page=ohmylms#/extensions/bundles',
			manageLabel: __( 'Manage course bundles', 'ohmylms' ),
		},
	];
}

export function invalidResponse(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __( 'The server returned invalid taxonomy data.', 'ohmylms' ),
	} );
}

function record( value: unknown ): Record< string, unknown > {
	if ( ! value || 'object' !== typeof value || Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value as Record< string, unknown >;
}
function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}
function whole( value: unknown, minimum: number ): number {
	if (
		'number' !== typeof value ||
		! Number.isSafeInteger( value ) ||
		value < minimum
	) {
		throw invalidResponse();
	}
	return value;
}

/**
 * Validate one term from the server. Unknown properties (such as `_links`) are dropped.
 *
 * @param value Decoded JSON.
 * @return The term.
 */
export function parseTerm( value: unknown ): Term {
	const item = record( value );
	return {
		id: whole( item.id, 1 ),
		name: text( item.name ),
		slug: text( item.slug ),
		description: text( item.description ),
		parent: whole( item.parent, 0 ),
		count: whole( item.count, 0 ),
	};
}

function parseLinkedCourse( value: unknown ): LinkedCourse {
	const item = record( value );
	return { id: whole( item.id, 1 ), title: text( item.title ) };
}

/**
 * Validate one term with its linked courses (`context=edit`).
 *
 * @param value Decoded JSON.
 * @return The term including its courses.
 */
export function parseTermDetail( value: unknown ): TermDetail {
	const item = record( value );
	if ( ! Array.isArray( item.courses ) ) {
		throw invalidResponse();
	}
	return {
		...parseTerm( value ),
		courses: item.courses.map( parseLinkedCourse ),
	};
}

/**
 * Query parameters for a term list. Blank searches are omitted.
 *
 * @param query List state.
 * @return Parameters for `GET /categories` or `GET /tags`.
 */
export function buildTermQuery( query: TermQuery ): QueryParams {
	const search = query.search.trim();
	return {
		page: query.page,
		per_page: PER_PAGE,
		orderby: 'name',
		order: 'asc',
		...( search ? { search } : {} ),
	};
}

/**
 * Name of the parent of a term, for display.
 *
 * @param term  Term whose parent to describe.
 * @param known Terms already loaded.
 * @return A name, "Top level", or the parent ID when it is not on the current page.
 */
export function parentLabel( term: Term, known: readonly Term[] ): string {
	if ( 0 === term.parent ) {
		return __( 'Top level', 'ohmylms' );
	}
	return (
		known.find( ( candidate ) => candidate.id === term.parent )?.name ??
		`#${ term.parent }`
	);
}
