import { record } from '../../lib/validation';
import type {
	BankAssignment,
	BankAssignments,
	BankTerm,
	BankTerms,
} from './types';

const BAD = 'The server sent folders or tags this screen cannot read.';

const whole = ( value: unknown ) =>
	Number.isSafeInteger( value ) && Number( value ) >= 0
		? Number( value )
		: null;

/**
 * Read one folder or tag.
 *
 * @param value Decoded JSON.
 */
export function parseTerm( value: unknown ): BankTerm {
	const row = record( value, BAD );
	const id = whole( row.id );
	const parent = whole( row.parent );
	if ( ! id || null === parent || 'string' !== typeof row.name ) {
		throw new TypeError( BAD );
	}
	return { id, name: row.name, parent };
}

/**
 * Read the folder and tag lists.
 *
 * @param value Decoded JSON.
 */
export function parseTerms( value: unknown ): BankTerms {
	const row = record( value, BAD );
	if ( ! Array.isArray( row.folders ) || ! Array.isArray( row.tags ) ) {
		throw new TypeError( BAD );
	}
	return {
		folders: row.folders.map( parseTerm ),
		tags: row.tags.map( parseTerm ),
	};
}

const ids = ( value: unknown ) =>
	Array.isArray( value )
		? value.map( whole ).filter( ( id ): id is number => Boolean( id ) )
		: [];

/**
 * Read the folders and tags of each item.
 *
 * @param value Decoded JSON, keyed by item ID.
 */
export function parseAssignments( value: unknown ): BankAssignments {
	const row = Array.isArray( value ) ? {} : record( value, BAD );
	return Object.fromEntries(
		Object.entries( row ).map( ( [ id, item ] ) => {
			const entry = record( item, BAD );
			return [
				Number( id ),
				{ folders: ids( entry.folders ), tags: ids( entry.tags ) },
			];
		} )
	);
}

/**
 * Folder names with their parents, for pickers: "Algebra / Linear".
 *
 * @param folders All folders.
 * @return Folders in tree order, each with its path as the label.
 */
export function folderPaths(
	folders: readonly BankTerm[]
): Array< { id: number; label: string } > {
	const byId = new Map( folders.map( ( folder ) => [ folder.id, folder ] ) );
	const pathOf = ( folder: BankTerm ): string => {
		const seen = new Set< number >( [ folder.id ] );
		const names = [ folder.name ];
		let parent = byId.get( folder.parent );
		while ( parent && ! seen.has( parent.id ) ) {
			seen.add( parent.id );
			names.unshift( parent.name );
			parent = byId.get( parent.parent );
		}
		return names.join( ' / ' );
	};
	return folders
		.map( ( folder ) => ( { id: folder.id, label: pathOf( folder ) } ) )
		.sort( ( a, b ) => a.label.localeCompare( b.label ) );
}

/**
 * Names of the folders and tags an item has, for a list column.
 *
 * @param terms      All folders and tags.
 * @param assignment The item's IDs, if loaded.
 */
export function organizationLabels(
	terms: BankTerms,
	assignment?: BankAssignment
) {
	if ( ! assignment ) {
		return { folders: [] as string[], tags: [] as string[] };
	}
	const paths = new Map(
		folderPaths( terms.folders ).map( ( folder ) => [
			folder.id,
			folder.label,
		] )
	);
	const tags = new Map( terms.tags.map( ( tag ) => [ tag.id, tag.name ] ) );
	return {
		folders: assignment.folders
			.map( ( id ) => paths.get( id ) )
			.filter( Boolean ) as string[],
		tags: assignment.tags
			.map( ( id ) => tags.get( id ) )
			.filter( Boolean ) as string[],
	};
}

/**
 * Turn a bulk action value into a request.
 *
 * Values are `add-folder:ID`, `add-tag:ID`, `clear-folders` and `clear-tags`.
 *
 * @param action  Chosen bulk action.
 * @param itemIds Selected item IDs.
 * @return The request, or null for an unknown action.
 */
export function bulkRequest( action: string, itemIds: number[] ) {
	const [ name, rawId ] = action.split( ':' );
	const id = Number( rawId );
	if ( 'add-folder' === name && id > 0 ) {
		return { ids: itemIds, folder_ids: [ id ], mode: 'add' as const };
	}
	if ( 'add-tag' === name && id > 0 ) {
		return { ids: itemIds, tag_ids: [ id ], mode: 'add' as const };
	}
	if ( 'clear-folders' === name ) {
		return { ids: itemIds, folder_ids: [], mode: 'replace' as const };
	}
	if ( 'clear-tags' === name ) {
		return { ids: itemIds, tag_ids: [], mode: 'replace' as const };
	}
	return null;
}
