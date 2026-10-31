import { __, sprintf } from '@wordpress/i18n';
import type { CsvRow } from '../../../features/curriculum/csv.mjs';
import type { SyllabusOutline } from './model';

/** Columns of the spreadsheet, left to right. `path` is read-only context. */
export const SHEET_COLUMNS = [
	'path',
	'unit',
	'unitCode',
	'group',
	'groupCode',
	'skillCode',
	'skill',
] as const;
export type SheetColumn = ( typeof SHEET_COLUMNS )[ number ];

/** Most cells one save may change (fixed by the server). */
export const MAX_SHEET_CHANGES = 500;

const LIMITS = { name: 190, item: 60, group: 60, skill: 40 } as const;

export interface SheetRow {
	key: string;
	contentId: number;
	root: boolean;
	path: string;
	unit: string;
	unitCode: string;
	groupId: number | null;
	group: string;
	groupCode: string;
	termId: number | null;
	skill: string;
	skillCode: string;
}

export interface CellRef {
	kind: 'item' | 'group' | 'skill';
	id: number;
	field: 'name' | 'code';
}

export interface SheetChange extends CellRef {
	previous: string;
	value: string;
}

/**
 * Text as the server compares it: control characters and repeated spaces folded, trimmed.
 * @param value
 */
export function line( value: string ): string {
	return value
		.replace( /[\u0000-\u001f\u007f]+/g, ' ' )
		.replace( /\s+/g, ' ' )
		.trim();
}

/**
 * One row per placed skill, plus a row for each empty group and each content item without groups, in
 * the outline's order. Identities (item, group, term) are kept so edits never re-create anything.
 *
 * @param outline Syllabus outline.
 * @return Rows.
 */
export function sheetRows( outline: SyllabusOutline ): SheetRow[] {
	const names = new Map< number, string >();
	const paths = new Map< number, string >();
	const rows: SheetRow[] = [];
	for ( const content of outline.contents ) {
		const root = content.id === outline.syllabus.id;
		const parentPath = paths.get( content.parent_id );
		const parentName = names.get( content.parent_id );
		const path = root
			? ''
			: [ parentPath, parentName ].filter( Boolean ).join( ' › ' );
		names.set( content.id, root ? '' : content.name );
		paths.set( content.id, path );
		const base = {
			contentId: content.id,
			root,
			path,
			unit: root ? '' : content.name,
			unitCode: root ? '' : ( content.code ?? '' ),
			groupId: null,
			group: '',
			groupCode: '',
			termId: null,
			skill: '',
			skillCode: '',
		};
		if ( ! content.groups.length && ! root ) {
			rows.push( { ...base, key: `c${ content.id }` } );
		}
		for ( const group of content.groups ) {
			const entry = {
				...base,
				groupId: group.id,
				group: group.name,
				groupCode: group.code,
			};
			if ( ! group.skills.length ) {
				rows.push( { ...entry, key: `g${ group.id }` } );
			}
			for ( const skill of group.skills ) {
				rows.push( {
					...entry,
					key: `g${ group.id }s${ skill.term_id }`,
					termId: skill.term_id,
					skill: skill.name,
					skillCode: skill.code,
				} );
			}
		}
	}
	return rows;
}

/**
 * The stored value a cell edits, or null for a read-only cell.
 *
 * @param row    Row.
 * @param column Column.
 * @return Cell identity.
 */
export function cellRef( row: SheetRow, column: SheetColumn ): CellRef | null {
	switch ( column ) {
		case 'unit':
		case 'unitCode':
			return row.root
				? null
				: {
						kind: 'item',
						id: row.contentId,
						field: 'unit' === column ? 'name' : 'code',
					};
		case 'group':
		case 'groupCode':
			return null === row.groupId
				? null
				: {
						kind: 'group',
						id: row.groupId,
						field: 'group' === column ? 'name' : 'code',
					};
		case 'skill':
		case 'skillCode':
			return null === row.termId
				? null
				: {
						kind: 'skill',
						id: row.termId,
						field: 'skill' === column ? 'name' : 'code',
					};
		default:
			return null;
	}
}

export const refKey = ( ref: CellRef ) =>
	`${ ref.kind }:${ ref.id }:${ ref.field }`;

/** Edits by cell identity: the same group or unit edited on one row shows on all its rows. */
export type SheetEdits = Readonly< Record< string, string > >;

export function cellValue(
	row: SheetRow,
	column: SheetColumn,
	edits: SheetEdits
): string {
	const ref = cellRef( row, column );
	const edited = ref ? edits[ refKey( ref ) ] : undefined;
	return undefined === edited ? row[ column ] : edited;
}

/**
 * A problem with a cell value, or null.
 *
 * @param ref   Cell identity.
 * @param value Value.
 * @return Message.
 */
export function cellProblem( ref: CellRef, value: string ): string | null {
	const text = line( value );
	if ( 'name' === ref.field ) {
		if ( ! text ) {
			return __( 'A name is required.', 'ohmylms' );
		}
		return text.length > LIMITS.name
			? sprintf(
					/* translators: %d: most characters. */
					__( 'Use at most %d characters.', 'ohmylms' ),
					LIMITS.name
				)
			: null;
	}
	return text.length > LIMITS[ ref.kind ]
		? sprintf(
				/* translators: %d: most characters. */
				__( 'Use at most %d characters.', 'ohmylms' ),
				LIMITS[ ref.kind ]
			)
		: null;
}

/**
 * Cells whose edited value differs from the saved one, each with the value it was edited from.
 *
 * @param rows  Saved rows.
 * @param edits Edits.
 * @return Changes in row order, one per cell identity.
 */
export function planChanges(
	rows: readonly SheetRow[],
	edits: SheetEdits
): SheetChange[] {
	const changes = new Map< string, SheetChange >();
	for ( const row of rows ) {
		for ( const column of SHEET_COLUMNS ) {
			const ref = cellRef( row, column );
			if ( ! ref ) {
				continue;
			}
			const key = refKey( ref );
			const edited = edits[ key ];
			if (
				undefined === edited ||
				changes.has( key ) ||
				line( edited ) === line( row[ column ] )
			) {
				continue;
			}
			changes.set( key, {
				...ref,
				previous: line( row[ column ] ),
				value: line( edited ),
			} );
		}
	}
	return [ ...changes.values() ];
}

/**
 * Skill codes used by more than one skill after the edits (compared as the server does).
 *
 * @param rows  Saved rows.
 * @param edits Edits.
 * @return Term IDs whose code is taken twice.
 */
export function duplicateCodes(
	rows: readonly SheetRow[],
	edits: SheetEdits
): Set< number > {
	const codes = new Map< number, string >();
	for ( const row of rows ) {
		if ( null !== row.termId ) {
			codes.set(
				row.termId,
				line( cellValue( row, 'skillCode', edits ) ).toLocaleLowerCase()
			);
		}
	}
	const owners = new Map< string, number[] >();
	for ( const [ term, code ] of codes ) {
		if ( code ) {
			owners.set( code, [ ...( owners.get( code ) ?? [] ), term ] );
		}
	}
	return new Set(
		[ ...owners.values() ].filter( ( terms ) => terms.length > 1 ).flat()
	);
}

/**
 * Rows pasted from a spreadsheet: Unit path, Group, Skill code, Skill (tab separated). Empty unit and
 * group cells repeat the row above, as merged cells copy. A header row naming the columns is skipped.
 *
 * @param source Pasted text.
 * @return Import rows for the syllabus CSV import (dry run first).
 */
export function pastedRows( source: string ): CsvRow[] {
	const lines = source
		.replace( /\r\n?/g, '\n' )
		.split( '\n' )
		.map( ( text ) => text.split( '\t' ).map( line ) );
	const header =
		lines[ 0 ]?.[ 0 ]?.toLowerCase() === 'unit' &&
		lines[ 0 ]?.[ 3 ]?.toLowerCase() === 'skill';
	let unit = '';
	let group = '';
	const rows: CsvRow[] = [];
	lines.forEach( ( cells, index ) => {
		if ( ( header && 0 === index ) || ! cells.some( Boolean ) ) {
			return;
		}
		const [ path = '', groupCell = '', code = '', skill = '' ] = cells;
		if ( path && path !== unit ) {
			unit = path;
			group = '';
		}
		group = groupCell || group;
		rows.push( {
			line: index + 1,
			content: unit.replace( /\s*›\s*/g, ' > ' ),
			group,
			skill_code: code,
			skill,
		} );
	} );
	return rows;
}

/**
 * Move within the grid, staying inside it.
 * @param position
 * @param position.row
 * @param position.column
 * @param key
 * @param size
 * @param size.rows
 * @param size.columns
 */
export function moveCell(
	position: { row: number; column: number },
	key: string,
	size: { rows: number; columns: number }
): { row: number; column: number } | null {
	const deltas: Record< string, readonly [ number, number ] > = {
		ArrowUp: [ -1, 0 ],
		ArrowDown: [ 1, 0 ],
		ArrowLeft: [ 0, -1 ],
		ArrowRight: [ 0, 1 ],
		Home: [ 0, -size.columns ],
		End: [ 0, size.columns ],
	};
	const delta = deltas[ key ];
	if ( ! delta ) {
		return null;
	}
	const [ rows, columns ] = delta;
	return {
		row: Math.min( size.rows - 1, Math.max( 0, position.row + rows ) ),
		column: Math.min(
			size.columns - 1,
			Math.max( 0, position.column + columns )
		),
	};
}
