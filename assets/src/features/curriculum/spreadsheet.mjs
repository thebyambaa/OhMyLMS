import { parseRecords } from './csv.mjs';

export const SHEET_IDENTITY = {
	item: 'contentId',
	group: 'groupId',
	skill: 'termId',
};
const KIND_COLUMN = { item: 'unit', group: 'chapter', skill: 'topic' };
const COLUMN_KIND = {
	number: 'item',
	unit: 'item',
	chapter: 'group',
	topic: 'skill',
};
export const sheetFieldColumn = ( kind, field ) =>
	field === 'code' ? 'number' : KIND_COLUMN[ kind ];

export const SHEET_COLUMNS = [ 'number', 'unit', 'chapter', 'topic' ];
const text = ( value ) => String( value ?? '' ).trim();

/**
 * Flatten the existing hierarchy without replacing placement or library identities.
 * @param {Object} outline Syllabus outline.
 * @return {Array} Editable rows.
 */
export function syllabusSheetRows( outline ) {
	const paths = new Map();
	const rows = [];
	let number = 0;
	for ( const content of outline.contents ) {
		const root = content.id === outline.syllabus.id;
		const parent = paths.get( content.parent_id ) || '';
		const unit = root
			? ''
			: [ parent, content.name ].filter( Boolean ).join( ' > ' );
		paths.set( content.id, unit );
		if ( ! root ) {
			number++;
		}
		const base = {
			contentId: content.id,
			parentId: content.parent_id,
			root,
			number: root ? '' : String( number ),
			unitCode: content.code,
			unit,
		};
		if ( ! content.groups.length && ! root ) {
			rows.push( {
				...base,
				key: `c${ content.id }`,
				chapter: '',
				topic: '',
			} );
		}
		for ( const group of content.groups ) {
			const entry = { ...base, groupId: group.id, chapter: group.name };
			if ( ! group.skills.length ) {
				rows.push( { ...entry, key: `g${ group.id }`, topic: '' } );
			}
			for ( const skill of group.skills ) {
				rows.push( {
					...entry,
					key: `g${ group.id }s${ skill.term_id }`,
					termId: skill.term_id,
					skill,
					topic: skill.name,
				} );
			}
		}
	}
	return rows;
}

/**
 * Update every occurrence of a shared unit, chapter or skill name.
 * @param {Array}  rows   Rows.
 * @param {number} index  Edited row.
 * @param {string} column Edited column.
 * @param {string} value  New text.
 * @return {Array} Updated rows.
 */
export function editSheetCell( rows, index, column, value ) {
	const source = rows[ index ];
	const identity = SHEET_IDENTITY[ COLUMN_KIND[ column ] ];
	const oldUnit = source.unit;
	return rows.map( ( row, position ) => {
		if (
			position === index ||
			( source[ identity ] && source[ identity ] === row[ identity ] )
		) {
			return { ...row, [ column ]: value };
		}
		// A renamed ancestor remains part of every descendant's folder path.
		if (
			column === 'unit' &&
			source.contentId &&
			row.unit.startsWith( `${ oldUnit } > ` )
		) {
			return { ...row, unit: value + row.unit.slice( oldUnit.length ) };
		}
		return row;
	} );
}

/**
 * Read the four-column, merged-cell-style layout copied from a spreadsheet.
 * @param {string} source Tab-separated cells.
 * @return {Array} New rows with inherited unit and chapter cells.
 */
export function pastedSheetRows( source ) {
	const records = parseRecords( source, '\t' );
	if (
		records[ 0 ]?.[ 1 ]?.trim().toLowerCase() === 'unit' &&
		records[ 0 ]?.[ 3 ]?.trim().toLowerCase() === 'topic'
	) {
		records.shift();
	}
	let previous = { number: '', unit: '', chapter: '' };
	return records
		.filter( ( cells ) => cells.some( ( cell ) => text( cell ) ) )
		.map( ( cells, index ) => {
			const [ number = '', unit = '', chapter = '', topic = '' ] =
				cells.map( text );
			const changedUnit = !! unit && unit !== previous.unit;
			const row = {
				key: `paste-${ Date.now() }-${ index }`,
				number: number || ( changedUnit ? '' : previous.number ),
				unit: unit || previous.unit,
				chapter: chapter || ( changedUnit ? '' : previous.chapter ),
				topic,
			};
			previous = row;
			return row;
		} );
}

/**
 * Plan identity-preserving renames and additive imports; no linked object is deleted.
 * @param {Array} baseline Saved rows.
 * @param {Array} rows     Edited rows.
 * @return {Object} Changes and new import rows.
 */
export function sheetChanges( baseline, rows ) {
	const before = new Map( baseline.map( ( row ) => [ row.key, row ] ) );
	const changes = new Map();
	const additions = [];
	const codeFor = ( row ) =>
		rows.find( ( entry ) => entry.contentId && entry.unit === row.unit )
			?.unitCode ?? text( row.number );
	for ( const row of rows ) {
		const original = before.get( row.key );
		if ( ! original ) {
			if (
				SHEET_COLUMNS.every( ( column ) => ! text( row[ column ] ) )
			) {
				continue;
			}
			if (
				! text( row.unit ) ||
				! text( row.chapter ) ||
				! text( row.topic )
			) {
				throw new Error(
					'Complete Unit, Chapter and Topic for each new row.'
				);
			}
			additions.push( {
				content_code: codeFor( row ),
				content: text( row.unit ),
				group: text( row.chapter ),
				skill: text( row.topic ),
			} );
			continue;
		}
		if ( ! row.termId && text( row.topic ) ) {
			if ( ! text( row.chapter ) ) {
				throw new Error( 'Complete Chapter for each topic.' );
			}
			additions.push( {
				content: text( row.unit ),
				group: text( row.chapter ),
				skill: text( row.topic ),
			} );
		}
		for ( const column of SHEET_COLUMNS ) {
			if ( column === 'number' ) {
				continue;
			}
			if (
				( column === 'topic' && ! row.termId ) ||
				( column === 'chapter' && ! row.groupId )
			) {
				continue;
			}
			if ( text( row[ column ] ) === text( original[ column ] ) ) {
				continue;
			}
			if ( column !== 'number' && ! text( row[ column ] ) ) {
				throw new Error( 'Existing names cannot be empty.' );
			}
			const kind = COLUMN_KIND[ column ];
			const id = row[ SHEET_IDENTITY[ kind ] ];
			if ( ! id || ( kind === 'item' && row.root ) ) {
				throw new Error(
					'Use Add rows to add topics or chapters to an empty folder.'
				);
			}
			const key = `${ kind }:${ id }`;
			const change = changes.get( key ) || {
				kind,
				id,
				fields: {},
				previous: {},
			};
			const field = column === 'number' ? 'code' : 'name';
			if ( column === 'unit' ) {
				const parent = rows.find(
					( entry ) => entry.contentId === row.parentId
				);
				const prefix =
					parent && ! parent.root ? `${ parent.unit } > ` : '';
				if (
					! row.unit.startsWith( prefix ) ||
					row.unit.slice( prefix.length ).includes( ' > ' )
				) {
					throw new Error(
						'Rename units here; use the outline to move a unit into another folder.'
					);
				}
			}
			// Paths are display context; the item's parent and identity stay intact.
			change.fields[ field ] =
				column === 'unit'
					? text( row.unit.split( ' > ' ).at( -1 ) )
					: text( row[ column ] );
			change.previous[ field ] =
				column === 'unit'
					? text( original.unit.split( ' > ' ).at( -1 ) )
					: text( original[ column ] );
			if ( change.fields[ field ] !== change.previous[ field ] ) {
				changes.set( key, change );
			}
		}
	}
	return { changes: [ ...changes.values() ], additions };
}

/**
 * Save through the existing capability-checked syllabus endpoints.
 * @param {Object}                   options         Save context.
 * @param {number}                   options.id      Syllabus ID.
 * @param {Object}                   options.plan    Planned changes.
 * @param {Object}                   options.api     Curriculum API.
 * @param {(change: Object) => void} options.onSaved Advance the retry baseline after each successful operation.
 */
export async function saveSheetChanges( { id, plan, api, onSaved } ) {
	const latest = syllabusSheetRows( await api.loadSyllabus( id ) );
	for ( const change of plan.changes ) {
		const identity = SHEET_IDENTITY[ change.kind ];
		const row = latest.find( ( entry ) => entry[ identity ] === change.id );
		for ( const [ field, previous ] of Object.entries( change.previous ) ) {
			const column = sheetFieldColumn( change.kind, field );
			let current = row?.[ column ] ?? null;
			if ( row && column === 'unit' ) {
				current = row.unit.split( ' > ' ).at( -1 );
			}
			if ( current !== previous ) {
				throw new Error(
					'This syllabus changed in another editor. Reopen the spreadsheet before saving these cells.'
				);
			}
		}
	}
	// Validate all pasted rows before making any changes.
	if ( plan.additions.length ) {
		const checked = await api.importRows( id, plan.additions, true );
		if ( ! checked.report?.valid ) {
			throw new Error(
				checked.report?.errors
					?.map( ( entry ) => entry.message )
					.join( ' ' ) || 'The pasted rows could not be validated.'
			);
		}
	}
	for ( const change of plan.changes ) {
		if ( change.kind === 'item' ) {
			await api.updateItem( change.id, change.fields );
		} else if ( change.kind === 'group' ) {
			await api.updateGroup( id, change.id, change.fields );
		} else {
			await api.updateSkill( id, change.id, change.fields );
		}
		onSaved( change );
	}
	if ( plan.additions.length ) {
		const result = await api.importRows( id, plan.additions, false );
		if ( ! result.report?.applied ) {
			throw new Error(
				result.report?.errors
					?.map( ( entry ) => entry.message )
					.join( ' ' ) || 'The new rows could not be saved.'
			);
		}
	}
}
