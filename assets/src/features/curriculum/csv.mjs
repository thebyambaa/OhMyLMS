/**
 * CSV for syllabus import, in the browser. The file is read here (so the administrator can check the
 * column mapping before anything is sent), turned into rows, and the server then validates and plans
 * every row again. Pure functions, no DOM, so they can be tested on their own.
 *
 * A syllabus row has up to eight parts:
 *   content_code, content      a topic or chapter (a path such as "Paper 1 > Number" nests)
 *   group_code, group          a skill group (a section, a skill family)
 *   skill_code, skill          one skill (a learning objective)
 *   description                notes or examples for the skill
 *   category                   Core, Extended, Advanced, or a custom skill category
 */

export const ROLES = [
	{
		id: 'content_code',
		label: 'Folder path code',
		hint: 'e.g. 1 or 11.1',
	},
	{
		id: 'content',
		label: 'Folder path',
		hint: 'e.g. Number',
	},
	{
		id: 'group_code',
		label: 'Skill folder code',
		hint: 'e.g. C1.1',
	},
	{
		id: 'group',
		label: 'Skill folder name',
		hint: 'e.g. Types of number',
	},
	{
		id: 'skill_code',
		label: 'Skill code',
		hint: 'e.g. C1.1.1',
	},
	{
		id: 'skill',
		label: 'Skill',
		hint: 'the learning objective',
	},
	{
		id: 'description',
		label: 'Notes',
		hint: 'notes or examples',
	},
	{
		id: 'category',
		label: 'Category',
		hint: 'Core, Extended, Advanced, or a custom category',
	},
];
export const ROLE_IDS = ROLES.map( ( role ) => role.id );
// Retain the legacy notes import role for older files; new files omit notes.
export const CSV_HEADER = ROLE_IDS.filter( ( role ) => role !== 'description' );

/** Words in a header that point to a role. Whole normalized headers are tried before partial matches. */
const ALIASES = {
	content_code: [
		'folder path code',
		'folder code',
		'content code',
		'chapter code',
		'topic code',
		'unit code',
		'module code',
		'strand code',
		'chapter id',
		'topic id',
		'unit id',
		'chapter no',
		'topic no',
		'chapter number',
		'topic number',
	],
	content: [
		'folder path',
		'folder',
		'folder name',
		'content',
		'chapter',
		'topic',
		'unit',
		'module',
		'strand',
		'theme',
		'content name',
		'chapter name',
		'topic name',
		'unit name',
	],
	group_code: [
		'skill folder code',
		'group code',
		'skill group code',
		'section code',
		'ref',
		'reference',
		'ref id',
		'group id',
		'skill group id',
		'section id',
		'subtopic code',
		'sub topic code',
	],
	group: [
		'skill folder',
		'skill folder name',
		'group',
		'skill group',
		'group name',
		'skill group name',
		'section',
		'section name',
		'subtopic',
		'sub topic',
		'skill family',
		'skill set',
	],
	skill_code: [
		'skill code',
		'skill id',
		'objective code',
		'objective id',
		'outcome code',
		'code',
		'id',
		'skill no',
		'skill number',
	],
	skill: [
		'skill',
		'skill name',
		'objective',
		'learning objective',
		'atomic learning objective',
		'learning outcome',
		'outcome',
		'statement',
		'skill statement',
		'name',
		'title',
	],
	category: [ 'category', 'skill category', 'level', 'tier', 'ангилал' ],
	description: [
		'description',
		'notes',
		'note',
		'notes and examples',
		'examples',
		'comments',
		'details',
		'skill description',
	],
};
const normalizeHeader = ( text ) =>
	String( text ?? '' )
		.normalize( 'NFKC' )
		.toLowerCase()
		.replace( /[^\p{L}\p{N}]+/gu, ' ' )
		.trim();

/**
 * Parse CSV text into records of cells. Understands quotes (with "" for a quote), line breaks and
 * the delimiter inside quotes, a byte order mark, and CR, LF or CRLF line ends.
 * @param text
 * @param delimiter
 */
export function parseRecords( text, delimiter ) {
	const source = String( text ?? '' ).replace( /^\uFEFF/, '' );
	const records = [];
	let record = [];
	let cell = '';
	let quoted = false;
	let started = false;
	for ( let i = 0; i < source.length; i++ ) {
		const char = source[ i ];
		if ( quoted ) {
			if ( char === '"' ) {
				if ( source[ i + 1 ] === '"' ) {
					cell += '"';
					i++;
				} else {
					quoted = false;
				}
			} else {
				cell += char;
			}
			continue;
		}
		if ( char === '"' && cell === '' ) {
			quoted = true;
			started = true;
		} else if ( char === delimiter ) {
			record.push( cell );
			cell = '';
			started = true;
		} else if ( char === '\n' || char === '\r' ) {
			if ( char === '\r' && source[ i + 1 ] === '\n' ) {
				i++;
			}
			record.push( cell );
			records.push( record );
			record = [];
			cell = '';
			started = false;
		} else {
			cell += char;
			started = true;
		}
	}
	if ( started || cell !== '' || record.length ) {
		record.push( cell );
		records.push( record );
	}
	return records;
}
const isBlankRecord = ( record ) =>
	record.every( ( cell ) => String( cell ).trim() === '' );

/**
 * Choose the delimiter (comma, semicolon or tab) that splits the first lines into the most even columns.
 * @param text
 */
export function detectDelimiter( text ) {
	let best = ',';
	let bestScore = -1;
	for ( const candidate of [ ',', ';', '\t' ] ) {
		const sample = parseRecords( text, candidate )
			.filter( ( record ) => ! isBlankRecord( record ) )
			.slice( 0, 25 );
		if ( ! sample.length ) {
			continue;
		}
		const widths = new Map();
		for ( const record of sample ) {
			widths.set(
				record.length,
				( widths.get( record.length ) || 0 ) + 1
			);
		}
		const [ width, count ] = [ ...widths.entries() ].sort(
			( a, b ) => b[ 1 ] - a[ 1 ] || b[ 0 ] - a[ 0 ]
		)[ 0 ];
		const score = width > 1 ? count * 1000 + width : 0;
		if ( score > bestScore ) {
			best = candidate;
			bestScore = score;
		}
	}
	return best;
}

/**
 * Read a CSV file's text. Returns the delimiter, the width and the records (blank lines dropped,
 * short records padded), or an error key when there is nothing usable.
 * @param text
 */
export function parseCsv( text ) {
	const delimiter = detectDelimiter( text );
	const records = parseRecords( text, delimiter ).filter(
		( record ) => ! isBlankRecord( record )
	);
	if ( ! records.length ) {
		return {
			error: 'empty',
			delimiter,
			width: 0,
			records: [],
		};
	}
	const width = Math.max( ...records.map( ( record ) => record.length ) );
	const padded = records.map( ( record ) => [
		...record,
		...Array( width - record.length ).fill( '' ),
	] );
	return {
		delimiter,
		width,
		records: padded,
		replaced: String( text ).includes( '�' ),
	};
}

/**
 * Guess which role each column plays from its header. Each role is given to one column at most, the
 * best match first, so a "Code" column next to a "Group code" column does not claim both.
 * @param headers
 */
export function guessRoles( headers ) {
	const normalized = headers.map( normalizeHeader );
	const roles = headers.map( () => '' );
	const take = ( role, index ) => {
		if ( index >= 0 && ! roles[ index ] && ! roles.includes( role ) ) {
			roles[ index ] = role;
		}
	};
	// Whole-header matches first, most specific roles first.
	for ( const role of [
		'content_code',
		'group_code',
		'skill_code',
		'content',
		'group',
		'description',
		'category',
		'skill',
	] ) {
		for ( const alias of ALIASES[ role ] ) {
			take( role, normalized.indexOf( alias ) );
		}
	}
	// Then headers that merely contain the idea.
	const contains = ( index, ...words ) =>
		words.every( ( word ) =>
			normalized[ index ].split( ' ' ).includes( word )
		);
	normalized.forEach( ( header, index ) => {
		if ( roles[ index ] || ! header ) {
			return;
		}
		if (
			contains( index, 'group' ) &&
			( contains( index, 'code' ) || contains( index, 'id' ) )
		) {
			take( 'group_code', index );
		} else if (
			contains( index, 'skill' ) &&
			( contains( index, 'code' ) || contains( index, 'id' ) )
		) {
			take( 'skill_code', index );
		} else if (
			contains( index, 'objective' ) ||
			contains( index, 'outcome' ) ||
			contains( index, 'skill' )
		) {
			take( 'skill', index );
		} else if (
			contains( index, 'chapter' ) ||
			contains( index, 'topic' )
		) {
			take( 'content', index );
		} else if (
			contains( index, 'group' ) ||
			contains( index, 'section' )
		) {
			take( 'group', index );
		} else if (
			contains( index, 'notes' ) ||
			contains( index, 'examples' )
		) {
			take( 'description', index );
		}
	} );
	return roles;
}

/**
 * Does the first record look like column titles rather than data?
 * @param record
 */
export function looksLikeHeader( record ) {
	const cells = record
		.map( ( cell ) => String( cell ).trim() )
		.filter( Boolean );
	if ( ! cells.length ) {
		return false;
	}
	const known = record.filter( ( cell ) =>
		Object.values( ALIASES ).some( ( aliases ) =>
			aliases.includes( normalizeHeader( cell ) )
		)
	).length;
	return known >= 1 || cells.every( ( cell ) => ! /\d/.test( cell ) );
}
const clean = ( value ) =>
	String( value ?? '' )
		.replace( /\s+/g, ' ' )
		.trim();

/**
 * Turn records into import rows using a column => role mapping (`roles[i]` is a role id or '').
 * `line` is the row's number in the sheet, counting the header. Merged-cell exports leave repeated
 * content and group cells empty: with `fillDown` an empty one takes the value above, but only on a
 * row that carries a skill, and a group only continues within the same content.
 * @param records
 * @param roles
 * @param root0
 * @param root0.header
 * @param root0.fillDown
 */
export function buildRows(
	records,
	roles,
	{ header = true, fillDown = true } = {}
) {
	const start = header ? 1 : 0;
	const rows = [];
	let previous = {};
	for ( let index = start; index < records.length; index++ ) {
		const row = {
			line: index + 1,
		};
		roles.forEach( ( role, column ) => {
			if ( ! role ) {
				return;
			}
			const value =
				role === 'description'
					? String( records[ index ][ column ] ?? '' )
							.replace( /\r\n?/g, '\n' )
							.trim()
					: clean( records[ index ][ column ] );
			row[ role ] = row[ role ]
				? `${ row[ role ] } ${ value }`.trim()
				: value;
		} );
		for ( const role of ROLE_IDS ) {
			row[ role ] ??= '';
		}
		const hasSkill = row.skill !== '' || row.skill_code !== '';
		if ( fillDown && hasSkill ) {
			for ( const role of [ 'content_code', 'content' ] ) {
				if ( row[ role ] === '' && previous[ role ] ) {
					row[ role ] = previous[ role ];
				}
			}
			const sameContent =
				row.content === ( previous.content ?? '' ) &&
				row.content_code === ( previous.content_code ?? '' );
			if ( sameContent ) {
				for ( const role of [ 'group_code', 'group' ] ) {
					if ( row[ role ] === '' && previous[ role ] ) {
						row[ role ] = previous[ role ];
					}
				}
			}
		}
		if ( ROLE_IDS.some( ( role ) => row[ role ] !== '' ) ) {
			rows.push( row );
			previous = {
				...row,
			};
		}
	}
	return rows;
}

/**
 * Which roles the mapping still lacks that an import needs: at least a skill column.
 * @param roles
 */
export function mappingProblems( roles ) {
	const problems = [];
	if ( ! roles.includes( 'skill' ) ) {
		problems.push( 'skill' );
	}
	const seen = new Set();
	for ( const role of roles ) {
		if ( ! role ) {
			continue;
		}
		if ( seen.has( role ) ) {
			problems.push( `duplicate:${ role }` );
		}
		seen.add( role );
	}
	return problems;
}
export function csvCell( value, delimiter = ',' ) {
	const text = String( value ?? '' );
	return /["\r\n]/.test( text ) ||
		text.includes( delimiter ) ||
		text !== text.trim()
		? `"${ text.replace( /"/g, '""' ) }"`
		: text;
}
export function toCsv( records, delimiter = ',' ) {
	return (
		records
			.map( ( record ) =>
				record
					.map( ( cell ) => csvCell( cell, delimiter ) )
					.join( delimiter )
			)
			.join( '\r\n' ) + '\r\n'
	);
}

/** A small example showing the three levels, for the "Download template" button. */
export function templateCsv() {
	return toCsv( [
		CSV_HEADER,
		[
			'1',
			'Number',
			'C1.1',
			'Types of number',
			'C1.1.1',
			'Identify and use natural numbers',
			'Core',
		],
		[
			'1',
			'Number',
			'C1.1',
			'Types of number',
			'C1.1.2',
			'Identify and use prime numbers',
			'Core',
		],
		[
			'1',
			'Number',
			'C1.2',
			'Sets',
			'C1.2.1',
			'Use set language and Venn diagrams',
			'Core',
		],
		[
			'2',
			'Algebra and graphs',
			'C2.1',
			'Algebraic manipulation',
			'C2.1.1',
			'Simplify expressions',
			'Core',
		],
	] );
}

/**
 * The syllabus as CSV in the same layout the importer reads, so an export can be edited and imported
 * again. Content below the first level is written as a "A > B" path.
 * @param outline
 */
export function outlineCsv( outline ) {
	const byId = new Map(
		outline.contents.map( ( content ) => [ content.id, content ] )
	);
	const rootId = outline.syllabus.id;
	const pathOf = ( content ) => {
		const names = [];
		let current = content;
		while ( current && current.id !== rootId ) {
			names.unshift( current.name );
			current = byId.get( current.parent_id );
		}
		return names.join( ' > ' );
	};
	const records = [ CSV_HEADER ];
	for ( const content of outline.contents ) {
		const path = content.id === rootId ? '' : pathOf( content );
		const code = content.id === rootId ? '' : content.code;
		if ( ! content.groups.length && content.id !== rootId ) {
			records.push( [ code, path, '', '', '', '', '' ] );
		}
		for ( const group of content.groups ) {
			if ( ! group.skills.length ) {
				records.push( [
					code,
					path,
					group.code,
					group.name,
					'',
					'',
					'',
				] );
			}
			for ( const skill of group.skills ) {
				records.push( [
					code,
					path,
					group.code,
					group.name,
					skill.code,
					skill.name,
					skill.category || '',
				] );
			}
		}
	}
	return toCsv( records );
}
