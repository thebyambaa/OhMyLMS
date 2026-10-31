import { jumble, typedPaper } from './paperTypes.mjs';

/**
 * Printable paper layout: settings, the printed form of each question and page breaks.
 * Pure functions of the editor's paper canvas. OhMyLMS\Exams\PaperModel mirrors them for the print page
 * and the learner's Paper View; tests/fixtures/paper-models.json keeps both equal.
 */

/**
 * Saved JSON: question settings, option rows and block attributes. The fields depend on the
 * question type, block and saved version, so they are not typed; every value is converted
 * (String, Number, Boolean) or checked where it is read.
 * @typedef {Record<string, any>} SavedFields
 */

/**
 * A question as the editor holds it.
 * @typedef {Object} PaperSourceQuestion
 * @property {number|string} [id]               Question ID.
 * @property {SavedFields}   settings           Settings, including the type.
 * @property {SavedFields[]} [questions]        Option rows.
 * @property {SavedFields[]} [options]          Option rows, as other screens name them.
 * @property {boolean}       [public]           Whether the settings are already the learner view.
 * @property {unknown}       [figure_part_html] Set when part prompts are HTML.
 */

/**
 * Normalized paper settings (OhMyLMS\Exams\PaperSettings).
 * @typedef {Object} PaperSettings
 * @property {'a4' | 'letter'}  size           Paper size.
 * @property {number}           margin         Margin in mm.
 * @property {number}           font_size      Font size in pt.
 * @property {'sans' | 'serif'} font           Font.
 * @property {'number' | 'q'}   numbering      Question numbers.
 * @property {boolean}          show_marks     Whether marks print.
 * @property {number}           lines_per_mark Answer lines per mark.
 * @property {boolean}          page_numbers   Whether page numbers print.
 * @property {boolean}          default_view   Whether the exam opens in paper view.
 * @property {number}           versions       Paper versions, 1 to 4.
 */

/**
 * Fields of every printed question.
 * @typedef {Object} PaperQuestionBase
 * @property {string}   type      Question type.
 * @property {string}   prompt    Statement HTML.
 * @property {number}   marks     Marks.
 * @property {string}   layout    Printed layout.
 * @property {string[]} [answers] Answer key: accepted answers.
 * @property {string[]} [key]     Answer key lines.
 */

/**
 * @typedef {Object} PaperOption
 * @property {string}  letter    Option letter.
 * @property {string}  text      Option HTML.
 * @property {string}  image     Image URL, '' for none.
 * @property {boolean} [correct] Answer key: whether the option is correct.
 */

/**
 * Printed form of one structured part.
 * @typedef {Object} PaperPartModel
 * @property {string}                  id        Part ID.
 * @property {string}                  label     Label without brackets.
 * @property {string}                  prompt    Prompt.
 * @property {number}                  marks     Marks.
 * @property {'lines' | 'answer-line'} layout    Answer space.
 * @property {number}                  lines     Ruled lines, 0 for an answer line.
 * @property {string}                  unit      Unit after the answer line.
 * @property {string[]}                [answers] Answer key: accepted answers.
 * @property {string}                  [rubric]  Answer key: marking notes.
 */

/** @typedef {PaperQuestionBase & { layout: 'choice', multiple: boolean, options: PaperOption[] }} PaperChoiceModel */
/** @typedef {PaperQuestionBase & { layout: 'lines', lines: number }} PaperLinesModel */
/** @typedef {PaperQuestionBase & { layout: 'answer-line', unit?: string }} PaperAnswerLineModel */
/** @typedef {PaperQuestionBase & { layout: 'parts', parts: PaperPartModel[] }} PaperPartsModel */
/** @typedef {PaperQuestionBase & { layout: 'text' | 'unsupported' }} PaperTextModel */

/**
 * Printed form of a question, by layout.
 * @typedef {PaperChoiceModel | PaperLinesModel | PaperAnswerLineModel | PaperPartsModel | PaperTextModel | import('./paperTypes.mjs').PaperTypedModel} PaperQuestionModel
 */

/**
 * An item of the print page: a question, a block, or a section or columns with their items.
 * @typedef {Object} PrintItem
 * @property {string}              kind       question, paper, layout or another block kind.
 * @property {PaperSourceQuestion} [question] The question of a question item.
 * @property {unknown}             [span]     Whether the item spans every column.
 * @property {PrintItem[]}         [items]    Nested items.
 */

/** @type {Record<'a4' | 'letter', { width: number, height: number }>} */
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

/**
 * @param {unknown} value    Saved value.
 * @param {number}  min      Lowest value.
 * @param {number}  max      Highest value.
 * @param {number}  fallback Value when it is not a finite number.
 * @return {number} Number between min and max.
 */
const clamp = ( value, min, max, fallback ) => {
	const number = Number( value );
	return Number.isFinite( number )
		? Math.min( max, Math.max( min, number ) )
		: fallback;
};

/**
 * @template {string} T
 * @param {unknown} value    Saved value.
 * @param {T[]}     allowed  Allowed values.
 * @param {T}       fallback Value when it is not allowed.
 * @return {T} An allowed value.
 */
const pick = ( value, allowed, fallback ) =>
	/** @type {unknown[]} */ ( allowed ).includes( value )
		? /** @type {T} */ ( value )
		: fallback;

/**
 * Normalize saved paper settings. Mirrors OhMyLMS\Exams\PaperSettings::sanitize().
 *
 * @param {unknown} [raw] Saved settings, possibly partial or from an older version.
 * @return {PaperSettings} Complete paper settings.
 */
export function paperSettings( raw = {} ) {
	const value = /** @type {SavedFields} */ (
		raw && typeof raw === 'object' ? raw : {}
	);
	/** @param {'show_marks' | 'page_numbers' | 'default_view'} key */
	const flag = ( key ) =>
		value[ key ] === undefined
			? PAPER_DEFAULTS[ key ]
			: ! [ false, 0, '0', '' ].includes( value[ key ] );
	return {
		size: pick(
			value.size,
			/** @type {Array<'a4' | 'letter'>} */ (
				Object.keys( PAPER_SIZES )
			),
			'a4'
		),
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
 * @param {PaperSettings} settings Normalized paper settings.
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
 * @param {PaperSourceQuestion|null|undefined} question Editor question with settings.
 * @return {number} Marks, 0 when ungraded.
 */
export function questionMarks( question ) {
	/** @type {SavedFields} */
	const settings = question?.settings || {};
	if ( settings.type === 'structured' ) {
		return /** @type {SavedFields[]} */ ( settings.parts || [] ).reduce(
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
 * @param {number}        index    Zero-based position among numbered questions.
 * @param {PaperSettings} settings Normalized paper settings.
 * @return {string} Label such as "3" or "Q3".
 */
export function questionLabel( index, settings ) {
	return settings.numbering === 'q' ? `Q${ index + 1 }` : String( index + 1 );
}

/**
 * Printed labels for a sequence of question types; statements are not numbered.
 *
 * @param {string[]}      types    Question types in document order.
 * @param {PaperSettings} settings Normalized paper settings.
 * @return {string[]} Labels, empty for statements.
 */
export function questionLabels( types, settings ) {
	let index = 0;
	return types.map( ( type ) =>
		type === 'statement' ? '' : questionLabel( index++, settings )
	);
}

const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

/**
 * @param {unknown[]} values Saved answers and lists of answers.
 * @return {string[]} Non-empty answers.
 */
const accepted = ( values ) =>
	values
		.flat()
		.map( ( value ) => String( value ?? '' ).trim() )
		.filter( Boolean );

/**
 * The printed form of one structured part.
 *
 * @param {SavedFields}   part        Saved part.
 * @param {PaperSettings} settings    Normalized paper settings.
 * @param {boolean}       showAnswers Whether to include the answer key.
 * @return {PaperPartModel} Part model.
 */
function paperPart( part, settings, showAnswers ) {
	const marks = Number( part.marks ) || 0;
	const written = ! [ 'numerical', 'text' ].includes( part.kind );
	/** @type {PaperPartModel} */
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
 * @param {PaperSourceQuestion} question              Editor question: settings and options (questions).
 * @param {Object}              [options]             Rendering options.
 * @param {PaperSettings}       [options.settings]    Normalized paper settings.
 * @param {boolean}             [options.showAnswers] Whether to include the answer key.
 * @param {string}              [options.prompt]      Statement HTML.
 * @param {string}              [options.version]     Paper version letter; B to D shuffle choice options.
 * @return {PaperQuestionModel} Paper question model.
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
	/** @type {PaperQuestionBase & { multiple?: boolean, options?: PaperOption[], lines?: number, unit?: string, parts?: PaperPartModel[] }} */
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
		return /** @type {PaperTextModel} */ ( model );
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
		model.parts = /** @type {SavedFields[]} */ (
			question.settings.parts || []
		).map( ( part ) => paperPart( part, settings, showAnswers ) );
	} else if ( type === 'statement' ) {
		model.layout = 'text';
	}
	// The branch above set the fields of the layout it chose.
	return /** @type {PaperQuestionModel} */ ( model );
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
	/** @param {number} page */
	const top = ( page ) => page * pitch + margin;
	/** @param {number} page */
	const bottom = ( page ) => page * pitch + height - margin;
	let y = top( 0 );
	let last = 0;
	/** @type {number[]} */
	const spacers = [];
	/** @type {boolean[]} */
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
 * @param {Array<{kind: string, key?: string, marks?: number}>} entries Sections and questions in
 *                                                                      document order.
 * @return {Object<string, number>} Total marks by section key.
 */
export function sectionTotals( entries ) {
	/** @type {Object<string, number>} */
	const totals = {};
	let current = null;
	for ( const entry of entries ) {
		if ( entry.kind === 'section' ) {
			// Section entries always carry their block's key.
			current = /** @type {string} */ ( entry.key );
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
 * @param {{value?: unknown, type?: unknown}|null|undefined} limit Time limit: value and type (minutes, hours or days).
 * @return {number} Minutes, 0 for no limit.
 */
export function limitMinutes( limit ) {
	const value = Math.max( 0, Number( limit?.value ) || 0 );
	/** @type {Record<string, number>} */
	const factors = { hours: 60, days: 1440 };
	const factor = factors[ String( limit?.type ) ] || 1;
	return Math.round( value * factor );
}

/**
 * Answer space heights, in lines of 8 mm, and the space styles.
 * @type {Array<'lines'|'grid'|'blank'>}
 */
export const ANSWER_SPACE_STYLES = [ 'lines', 'grid', 'blank' ];

/**
 * Normalize answer space attributes.
 *
 * @param {SavedFields} [attributes] Block attributes.
 * @return {{style: 'lines'|'grid'|'blank', lines: number}} Answer space.
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
 * @param {SavedFields} [attributes] Block attributes.
 * @return {{columns: number, flow: 'grid'|'flow', rule: boolean}} Columns section.
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
 * @param {unknown}       value    Requested version.
 * @param {PaperSettings} settings Normalized paper settings.
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
 * @param {number|string|undefined} id        Question ID.
 * @param {string}                  [version] Version letter.
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
 * @param {PrintItem[]} items   Print items.
 * @param {string}      version Version letter.
 * @return {PrintItem[]} Items in printed order.
 */
export function versionItems( items, version ) {
	if ( version === 'A' ) {
		return items;
	}
	let run = 0;
	/** @param {PrintItem} item */
	const movable = ( item ) =>
		item.kind === 'question' &&
		item.question?.settings?.type !== 'statement' &&
		! item.span;
	/**
	 * @param {PrintItem[]} list
	 * @return {PrintItem[]} Items in printed order.
	 */
	const visit = ( list ) => {
		/** @type {PrintItem[]} */
		const out = [];
		/** @type {PrintItem[]} */
		let group = [];
		const flush = () => {
			if ( group.length > 1 ) {
				const order = jumble(
					group.map( ( item ) =>
						String(
							/** @type {PaperSourceQuestion} */ ( item.question )
								.id ?? ''
						)
					),
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
