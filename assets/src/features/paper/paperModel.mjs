import { jumble, typedPaper } from './paperTypes.mjs';

/**
 * Printable paper layout: settings, the printed form of each question and page breaks.
 * Pure functions shared by the editor's paper canvas and the print page.
 */

export const PAPER_SIZES = {
	a4: { width: 210, height: 297 },
	letter: { width: 215.9, height: 279.4 },
};

export const PAPER_DEFAULTS = {
	size: 'a4',
	margin: 20,
	font_size: 11,
	font: 'sans',
	numbering: 'number',
	show_marks: true,
	lines_per_mark: 2,
	page_numbers: true,
	default_view: false,
	versions: 1,
};

/** Question types with a printed form in this release. */
export const PAPER_TYPES = [
	'single-choice',
	'multiple-choice',
	'true-false',
	'short-text',
	'text-entry',
	'long-text',
	'numerical',
	'structured',
	'statement',
	'matching',
	'reorder',
	'fill-in-the-blank',
	'dropdown-blanks',
	'multi-blank',
	'categorize',
	'build-expression',
	'expression',
	'crossword',
	'wordsearch',
	'number-line',
	'shade-model',
	'count-blocks',
	'set-clock',
	'make-amount',
	'fill-level',
	'build-chart',
	'grid-build',
];

const MM_TO_PX = 96 / 25.4;

const clamp = ( value, min, max, fallback ) => {
	const number = Number( value );
	return Number.isFinite( number )
		? Math.min( max, Math.max( min, number ) )
		: fallback;
};

const pick = ( value, allowed, fallback ) =>
	allowed.includes( value ) ? value : fallback;

/**
 * Normalize saved paper settings. Mirrors OhMyLMS\Exams\PaperSettings::sanitize().
 *
 * @param {Object} raw Saved settings, possibly partial or from an older version.
 * @return {Object} Complete paper settings.
 */
export function paperSettings( raw = {} ) {
	const value = raw && typeof raw === 'object' ? raw : {};
	const flag = ( key ) =>
		value[ key ] === undefined
			? PAPER_DEFAULTS[ key ]
			: ! [ false, 0, '0', '' ].includes( value[ key ] );
	return {
		size: pick( value.size, Object.keys( PAPER_SIZES ), 'a4' ),
		margin: Math.round( clamp( value.margin, 10, 30, 20 ) ),
		font_size: Math.round( clamp( value.font_size, 9, 14, 11 ) ),
		font: pick( value.font, [ 'sans', 'serif' ], 'sans' ),
		numbering: pick( value.numbering, [ 'number', 'q' ], 'number' ),
		show_marks: flag( 'show_marks' ),
		lines_per_mark: Math.round( clamp( value.lines_per_mark, 1, 4, 2 ) ),
		page_numbers: flag( 'page_numbers' ),
		default_view: flag( 'default_view' ),
		versions: Math.round( clamp( value.versions, 1, 4, 1 ) ),
	};
}

/**
 * Sheet dimensions in CSS pixels at 96 dpi.
 *
 * @param {Object} settings Normalized paper settings.
 * @return {{width: number, height: number, margin: number}} Sheet metrics.
 */
export function sheetMetrics( settings ) {
	const size = PAPER_SIZES[ settings.size ] || PAPER_SIZES.a4;
	return {
		width: size.width * MM_TO_PX,
		height: size.height * MM_TO_PX,
		margin: settings.margin * MM_TO_PX,
	};
}

/**
 * Marks a question is worth on paper.
 *
 * @param {Object} question Editor question with settings.
 * @return {number} Marks, 0 when ungraded.
 */
export function questionMarks( question ) {
	const settings = question?.settings || {};
	if ( settings.type === 'structured' ) {
		return ( settings.parts || [] ).reduce(
			( total, part ) => total + ( Number( part.marks ) || 0 ),
			0
		);
	}
	if ( settings.type === 'statement' || settings.score?.enabled === false ) {
		return 0;
	}
	return Number( settings.score?.value ) || 0;
}

/**
 * Printed question label.
 *
 * @param {number} index    Zero-based position among numbered questions.
 * @param {Object} settings Normalized paper settings.
 * @return {string} Label such as "3" or "Q3".
 */
export function questionLabel( index, settings ) {
	return settings.numbering === 'q' ? `Q${ index + 1 }` : String( index + 1 );
}

/**
 * Printed labels for a sequence of question types; statements are not numbered.
 *
 * @param {string[]} types    Question types in document order.
 * @param {Object}   settings Normalized paper settings.
 * @return {string[]} Labels, empty for statements.
 */
export function questionLabels( types, settings ) {
	let index = 0;
	return types.map( ( type ) =>
		type === 'statement' ? '' : questionLabel( index++, settings )
	);
}

const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

const accepted = ( values ) =>
	values
		.flat()
		.map( ( value ) => String( value ?? '' ).trim() )
		.filter( Boolean );

/**
 * The printed form of one structured part.
 *
 * @param {Object}  part        Saved part.
 * @param {Object}  settings    Normalized paper settings.
 * @param {boolean} showAnswers Whether to include the answer key.
 * @return {Object} Part model.
 */
function paperPart( part, settings, showAnswers ) {
	const marks = Number( part.marks ) || 0;
	const written = ! [ 'numerical', 'text' ].includes( part.kind );
	const model = {
		id: String( part.id ?? '' ),
		// Imported papers store labels with brackets, such as "(a)"; the paper adds its own.
		label: String( part.label ?? '' )
			.trim()
			.replace( /^\((.*)\)$/, '$1' ),
		prompt: String( part.prompt ?? '' ),
		marks,
		layout: written ? 'lines' : 'answer-line',
		lines: written ? Math.max( 2, marks * settings.lines_per_mark ) : 0,
		unit: part.kind === 'numerical' ? String( part.unit ?? '' ) : '',
	};
	if ( showAnswers ) {
		model.answers = accepted( [
			part.answer ?? [],
			part.answers ?? [],
			part.accepted ?? [],
		] );
		model.rubric = String( part.rubric ?? '' );
	}
	return model;
}

/**
 * The printed form of a question. Answer fields are present only when showAnswers is true,
 * so a student paper never carries the answer key, even hidden.
 *
 * @param {Object}  question            Editor question: settings and options (questions).
 * @param {Object}  options             Rendering options.
 * @param {Object}  options.settings    Normalized paper settings.
 * @param {boolean} options.showAnswers Whether to include the answer key.
 * @param {string}  options.prompt      Statement HTML.
 * @param {string}  options.version     Paper version letter; B to D shuffle choice options.
 * @return {Object} Paper question model.
 */
export function paperQuestion(
	question,
	{
		settings = paperSettings(),
		showAnswers = false,
		prompt = '',
		version = 'A',
	} = {}
) {
	const type = question?.settings?.type || '';
	const marks = questionMarks( question );
	const model = {
		type,
		// A fill-in-the-blank statement holds its answers in braces: print blanks instead.
		prompt:
			type === 'fill-in-the-blank' && ! showAnswers
				? String( prompt ).replace( /\{[^{}<>]+\}/g, '__________' )
				: prompt,
		marks,
		layout: 'unsupported',
	};
	if ( ! PAPER_TYPES.includes( type ) ) {
		return model;
	}
	const seed = questionSeed( question.id, version );
	const typed = typedPaper( question, model, showAnswers, seed );
	if ( typed ) {
		return typed;
	}
	let rows = [ ...( question.questions || question.options || [] ) ].sort(
		( a, b ) => ( a.order_number || 0 ) - ( b.order_number || 0 )
	);
	if (
		[ 'single-choice', 'multiple-choice', 'true-false' ].includes( type )
	) {
		if ( version !== 'A' && type !== 'true-false' ) {
			// Other versions reorder the options; true or false keeps its order.
			rows = jumble(
				rows.map( ( row ) => String( row.answer ?? '' ) ),
				seed,
				{ avoidIdentity: true }
			).map( ( index ) => rows[ index ] );
		}
		model.layout = 'choice';
		model.multiple = type === 'multiple-choice';
		model.options = rows.map( ( row, index ) => ( {
			letter: LETTERS[ index ] || String( index + 1 ),
			text: String( row.answer ?? '' ),
			image: String( row.image_url ?? '' ),
			...( showAnswers
				? {
						correct: ! [ false, 0, '0', '' ].includes(
							row.is_correct
						),
					}
				: {} ),
		} ) );
	} else if ( type === 'long-text' ) {
		model.layout = 'lines';
		model.lines = Math.max( 3, marks * settings.lines_per_mark );
	} else if ( type === 'numerical' ) {
		model.layout = 'answer-line';
		model.unit = String( question.settings.unit ?? '' );
		if ( showAnswers ) {
			const tolerance = Number( question.settings.tolerance ) || 0;
			model.answers = accepted( [ question.settings.answer ?? [] ] ).map(
				( answer ) =>
					tolerance ? `${ answer } ± ${ tolerance }` : answer
			);
		}
	} else if ( [ 'short-text', 'text-entry' ].includes( type ) ) {
		model.layout = 'answer-line';
		if ( showAnswers ) {
			model.answers = accepted( [
				rows.map( ( row ) => row.answer ),
				question.settings.answers ?? [],
				// Type-the-answer questions keep accepted answers in their settings.
				question.settings.accepted ?? [],
			] );
		}
	} else if ( type === 'structured' ) {
		model.layout = 'parts';
		model.parts = ( question.settings.parts || [] ).map( ( part ) =>
			paperPart( part, settings, showAnswers )
		);
	} else if ( type === 'statement' ) {
		model.layout = 'text';
	}
	return model;
}

/**
 * Place blocks on sheets. A block that does not fit in the rest of a page moves to the next
 * one, as the browser does when printing with `break-inside: avoid`; a block taller than a
 * whole page stays where it starts and overflows.
 *
 * Positions are measured from the top of the first sheet. Sheets are separated by `gap`.
 * A page break block keeps its place and sends the next block to a new sheet.
 *
 * @param {number[]}  heights      Block heights including the space after each block.
 * @param {Object}    sheet        Sheet metrics in pixels.
 * @param {number}    sheet.height Sheet height.
 * @param {number}    sheet.margin Top and bottom margin.
 * @param {number}    sheet.gap    Space between sheets on screen.
 * @param {boolean[]} breaks       Which blocks are page breaks.
 * @return {{spacers: number[], pages: number, overflow: boolean[]}} Extra space to add before
 * each block, the sheet count and which blocks are taller than a page.
 */
export function paginate( heights, { height, margin, gap }, breaks = [] ) {
	const pitch = height + gap;
	const usable = height - margin * 2;
	const top = ( page ) => page * pitch + margin;
	const bottom = ( page ) => page * pitch + height - margin;
	let y = top( 0 );
	let last = 0;
	const spacers = [];
	const overflow = [];
	heights.forEach( ( raw, index ) => {
		const size = Math.max( 0, Number( raw ) || 0 );
		const page = Math.floor( y / pitch );
		let start = y;
		if ( breaks[ index - 1 ] && y > top( page ) ) {
			start = top( page + 1 );
		} else if ( y >= bottom( page ) ) {
			// The previous block ended in this sheet's bottom margin or in the gap.
			start = top( page + 1 );
		} else if ( y + size > bottom( page ) && size <= usable ) {
			start = top( page + 1 );
		}
		spacers.push( start - y );
		overflow.push( size > usable );
		y = start + size;
		// A block taller than the space left runs on, across the next sheet edge.
		last = Math.max( last, Math.floor( Math.max( start, y - 1 ) / pitch ) );
	} );
	return { spacers, pages: last + 1, overflow };
}

/**
 * Marks under each section heading: the questions after it, up to the next section.
 *
 * @param {Array<{kind: string, key: string, marks: number}>} entries Sections and questions in
 *                                                                    document order.
 * @return {Object<string, number>} Total marks by section key.
 */
export function sectionTotals( entries ) {
	const totals = {};
	let current = null;
	for ( const entry of entries ) {
		if ( entry.kind === 'section' ) {
			current = entry.key;
			totals[ current ] = 0;
		} else if ( current !== null ) {
			totals[ current ] += Number( entry.marks ) || 0;
		}
	}
	return totals;
}

/**
 * Minutes allowed for an exam, from the quiz time limit.
 *
 * @param {Object} limit Time limit: value and type (minutes, hours or days).
 * @return {number} Minutes, 0 for no limit.
 */
export function limitMinutes( limit ) {
	const value = Math.max( 0, Number( limit?.value ) || 0 );
	const factor = { hours: 60, days: 1440 }[ limit?.type ] || 1;
	return Math.round( value * factor );
}

/** Answer space heights, in lines of 8 mm, and the space styles. */
export const ANSWER_SPACE_STYLES = [ 'lines', 'grid', 'blank' ];

/**
 * Normalize answer space attributes.
 *
 * @param {Object} attributes Block attributes.
 * @return {{style: string, lines: number}} Answer space.
 */
export function answerSpace( attributes = {} ) {
	return {
		style: ANSWER_SPACE_STYLES.includes( attributes.style )
			? attributes.style
			: 'lines',
		lines: Math.round(
			Math.min( 30, Math.max( 1, Number( attributes.lines ) || 6 ) )
		),
	};
}

/**
 * Normalize columns section attributes.
 *
 * @param {Object} attributes Block attributes.
 * @return {{columns: number, flow: string, rule: boolean}} Columns section.
 */
export function columnsLayout( attributes = {} ) {
	return {
		columns: Math.round(
			Math.min( 3, Math.max( 1, Number( attributes.columns ) || 2 ) )
		),
		flow: attributes.flow === 'grid' ? 'grid' : 'flow',
		rule: Boolean( attributes.rule ),
	};
}

/** Paper version letters; version A is the paper as laid out in the editor. */
export const PAPER_VERSIONS = [ 'A', 'B', 'C', 'D' ];

/**
 * A version letter the paper offers, else A.
 *
 * @param {string} value    Requested version.
 * @param {Object} settings Normalized paper settings.
 * @return {string} Version letter.
 */
export function paperVersion( value, settings ) {
	const index = PAPER_VERSIONS.indexOf( String( value || '' ).toUpperCase() );
	return index > 0 && index < settings.versions
		? PAPER_VERSIONS[ index ]
		: 'A';
}

/**
 * Jumble seed of a question in a paper version. OhMyLMS\Exams\PrintPaper::seed() is identical.
 *
 * @param {number|string} id      Question ID.
 * @param {string}        version Version letter.
 * @return {string} Seed.
 */
export function questionSeed( id, version = 'A' ) {
	return version === 'A' ? String( id ?? '' ) : `${ id ?? '' }:${ version }`;
}

/**
 * Print items in a paper version. Versions B to D shuffle each run of consecutive questions in
 * place: headers, sections, statements, page breaks, wide items in columns and other blocks stay
 * where the author put them, and questions never move into another section or column.
 *
 * @param {Array}  items   Print items.
 * @param {string} version Version letter.
 * @return {Array} Items in printed order.
 */
export function versionItems( items, version ) {
	if ( version === 'A' ) {
		return items;
	}
	let run = 0;
	const movable = ( item ) =>
		item.kind === 'question' &&
		item.question?.settings?.type !== 'statement' &&
		! item.span;
	const visit = ( list ) => {
		const out = [];
		let group = [];
		const flush = () => {
			if ( group.length > 1 ) {
				const order = jumble(
					group.map( ( item ) => String( item.question.id ?? '' ) ),
					`${ version }:${ run++ }`,
					{ avoidIdentity: true }
				);
				out.push( ...order.map( ( index ) => group[ index ] ) );
			} else {
				out.push( ...group );
			}
			group = [];
		};
		for ( const item of list ) {
			if ( movable( item ) ) {
				group.push( item );
				continue;
			}
			flush();
			out.push(
				[ 'paper', 'layout' ].includes( item.kind ) &&
					item.items?.length
					? { ...item, items: visit( item.items ) }
					: item
			);
		}
		flush();
		return out;
	};
	return visit( items );
}
