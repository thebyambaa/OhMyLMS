import test from 'node:test';
import assert from 'node:assert/strict';
import {
	answerSpace,
	columnsLayout,
	limitMinutes,
	paginate,
	sectionTotals,
	paperQuestion,
	paperSettings,
	questionLabel,
	questionLabels,
	questionMarks,
	sheetMetrics,
} from '../../assets/src/features/paper/paperModel.mjs';

test( 'paper settings fall back to defaults and clamp unsafe values', () => {
	assert.deepEqual( paperSettings(), {
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
	} );
	const odd = paperSettings( {
		size: 'a0',
		margin: 99,
		font_size: '3',
		font: 'comic',
		numbering: 'roman',
		show_marks: '0',
		lines_per_mark: 'many',
		default_view: 1,
	} );
	assert.equal( odd.size, 'a4' );
	assert.equal( odd.margin, 30 );
	assert.equal( odd.font_size, 9 );
	assert.equal( odd.font, 'sans' );
	assert.equal( odd.numbering, 'number' );
	assert.equal( odd.show_marks, false );
	assert.equal( odd.lines_per_mark, 2 );
	assert.equal( odd.default_view, true );
	assert.equal( paperSettings( null ).size, 'a4' );
} );

test( 'sheet metrics convert millimetres to CSS pixels', () => {
	const a4 = sheetMetrics( paperSettings() );
	assert.equal( Math.round( a4.width ), 794 );
	assert.equal( Math.round( a4.height ), 1123 );
	assert.equal( Math.round( a4.margin ), 76 );
	const letter = sheetMetrics( paperSettings( { size: 'letter' } ) );
	assert.equal( Math.round( letter.width ), 816 );
} );

test( 'marks come from the score, or the parts of a structured question', () => {
	assert.equal(
		questionMarks( {
			settings: {
				type: 'single-choice',
				score: { enabled: true, value: 2 },
			},
		} ),
		2
	);
	assert.equal(
		questionMarks( {
			settings: {
				type: 'structured',
				parts: [ { marks: 2 }, { marks: '3' } ],
			},
		} ),
		5
	);
	assert.equal(
		questionMarks( {
			settings: { type: 'statement', score: { value: 1 } },
		} ),
		0
	);
	assert.equal(
		questionMarks( {
			settings: {
				type: 'long-text',
				score: { enabled: false, value: 4 },
			},
		} ),
		0
	);
} );

test( 'question labels follow the numbering setting', () => {
	assert.equal( questionLabel( 0, paperSettings() ), '1' );
	assert.equal(
		questionLabel( 2, paperSettings( { numbering: 'q' } ) ),
		'Q3'
	);
} );

const choice = {
	settings: { type: 'single-choice', score: { enabled: true, value: 1 } },
	questions: [
		{ answer: '0.7', is_correct: false, order_number: 2 },
		{ answer: '0.75', is_correct: true, order_number: 1 },
	],
};

test( 'a student paper never carries the answer key', () => {
	const types = [
		choice,
		{
			settings: {
				type: 'numerical',
				answer: '6',
				tolerance: 0.5,
				unit: 'cm',
			},
		},
		{
			settings: { type: 'short-text' },
			questions: [ { answer: 'Paris', is_correct: true } ],
		},
		{
			settings: {
				type: 'structured',
				parts: [
					{
						id: 'p1',
						label: 'a',
						kind: 'numerical',
						marks: 1,
						answer: 4,
						rubric: 'B1',
					},
				],
			},
		},
	];
	for ( const question of types ) {
		const text = JSON.stringify( paperQuestion( question ) );
		for ( const secret of [
			'correct',
			'answers',
			'rubric',
			'Paris',
			'B1',
			'"6',
		] ) {
			assert.ok(
				! text.includes( secret ),
				`${ question.settings.type } leaks ${ secret }`
			);
		}
	}
} );

test( 'choice questions print lettered options in their saved order', () => {
	const paper = paperQuestion( choice );
	assert.equal( paper.layout, 'choice' );
	assert.equal( paper.multiple, false );
	assert.deepEqual(
		paper.options.map( ( option ) => [ option.letter, option.text ] ),
		[
			[ 'A', '0.75' ],
			[ 'B', '0.7' ],
		]
	);
	const key = paperQuestion( choice, { showAnswers: true } );
	assert.deepEqual(
		key.options.map( ( option ) => option.correct ),
		[ true, false ]
	);
	assert.equal(
		paperQuestion( {
			...choice,
			settings: { ...choice.settings, type: 'multiple-choice' },
		} ).multiple,
		true
	);
} );

test( 'written answers get ruled lines in proportion to marks', () => {
	const long = ( value, lines ) =>
		paperQuestion(
			{
				settings: {
					type: 'long-text',
					score: { enabled: true, value },
				},
			},
			{ settings: paperSettings( { lines_per_mark: lines } ) }
		).lines;
	assert.equal( long( 4, 2 ), 8 );
	assert.equal( long( 1, 1 ), 3 );
} );

test( 'numerical and short answers print an answer line, with the key on request', () => {
	const numerical = {
		settings: {
			type: 'numerical',
			answer: '6',
			tolerance: 0.5,
			unit: 'cm',
		},
	};
	const paper = paperQuestion( numerical );
	assert.equal( paper.layout, 'answer-line' );
	assert.equal( paper.unit, 'cm' );
	assert.deepEqual(
		paperQuestion( numerical, { showAnswers: true } ).answers,
		[ '6 ± 0.5' ]
	);
	assert.deepEqual(
		paperQuestion(
			{
				settings: { type: 'short-text' },
				questions: [ { answer: 'Paris' }, { answer: ' ' } ],
			},
			{ showAnswers: true }
		).answers,
		[ 'Paris' ]
	);
} );

test( 'structured questions print each part with its own marks and answer space', () => {
	const paper = paperQuestion(
		{
			settings: {
				type: 'structured',
				parts: [
					{
						id: 'p1',
						label: 'a',
						prompt: 'Work out x.',
						kind: 'numerical',
						marks: 1,
						unit: 'cm',
						answer: 4,
					},
					{
						id: 'p2',
						label: 'b',
						prompt: 'Explain.',
						kind: 'written',
						marks: 3,
						rubric: 'M1 A1',
					},
				],
			},
		},
		{ showAnswers: true }
	);
	assert.equal( paper.layout, 'parts' );
	assert.equal( paper.marks, 4 );
	assert.deepEqual(
		paper.parts.map( ( part ) => [
			part.label,
			part.layout,
			part.lines,
			part.unit,
		] ),
		[
			[ 'a', 'answer-line', 0, 'cm' ],
			[ 'b', 'lines', 6, '' ],
		]
	);
	assert.deepEqual( paper.parts[ 0 ].answers, [ '4' ] );
	assert.equal( paper.parts[ 1 ].rubric, 'M1 A1' );
} );

test( 'bracketed part labels from imported papers are not bracketed twice', () => {
	const paper = paperQuestion( {
		settings: {
			type: 'structured',
			parts: [
				{ id: 'p1', label: '(a)', kind: 'text', marks: 1 },
				{ id: 'p2', label: 'b', kind: 'text', marks: 1 },
			],
		},
	} );
	assert.deepEqual(
		paper.parts.map( ( part ) => part.label ),
		[ 'a', 'b' ]
	);
} );

test( 'statements are not numbered and numbering continues after them', () => {
	assert.deepEqual(
		questionLabels(
			[ 'single-choice', 'statement', 'long-text' ],
			paperSettings( { numbering: 'q' } )
		),
		[ 'Q1', '', 'Q2' ]
	);
} );

test( 'fill-in-the-blank answers become blanks on the student paper', () => {
	const question = {
		settings: { type: 'fill-in-the-blank', score: { value: 2 } },
	};
	const prompt = '<p>The capital of France is {Paris}.</p>';
	const paper = paperQuestion( question, { prompt } );
	assert.ok( ! paper.prompt.includes( 'Paris' ) );
	assert.ok( paper.prompt.includes( '__________' ) );
	assert.ok(
		paperQuestion( question, {
			prompt,
			showAnswers: true,
		} ).prompt.includes( 'Paris' )
	);
} );

test( 'types without a printed form are flagged as unsupported', () => {
	const paper = paperQuestion(
		{ settings: { type: 'draw', score: { value: 1 } } },
		{ prompt: '<p>Draw a house.</p>' }
	);
	assert.equal( paper.layout, 'unsupported' );
	assert.equal( paper.prompt, '<p>Draw a house.</p>' );
} );

test( 'pagination moves a block that does not fit to the next sheet', () => {
	const sheet = { height: 100, margin: 10, gap: 20 };
	// Usable height 80; second sheet content starts at 120 + 10 = 130.
	const result = paginate( [ 30, 30, 30 ], sheet );
	assert.deepEqual( result.spacers, [ 0, 0, 60 ] );
	assert.equal( result.pages, 2 );
	assert.deepEqual( result.overflow, [ false, false, false ] );
} );

test( 'pagination keeps an oversized block in place and counts the sheets it covers', () => {
	const sheet = { height: 100, margin: 10, gap: 20 };
	const result = paginate( [ 10, 200 ], sheet );
	assert.deepEqual( result.spacers, [ 0, 0 ] );
	assert.deepEqual( result.overflow, [ false, true ] );
	assert.equal( result.pages, 2 );
	// A block ending in the margin or gap pushes the next one to a new sheet.
	const after = paginate( [ 80, 5 ], sheet );
	assert.deepEqual( after.spacers, [ 0, 40 ] );
	assert.equal( after.pages, 2 );
	assert.equal( paginate( [], sheet ).pages, 1 );
} );

test( 'a page break sends the next block to a new sheet', () => {
	const sheet = { height: 100, margin: 10, gap: 20 };
	const result = paginate( [ 10, 5, 10 ], sheet, [ false, true, false ] );
	// The break stays after the first block; the third block starts on sheet 2 at 130.
	assert.deepEqual( result.spacers, [ 0, 0, 105 ] );
	assert.equal( result.pages, 2 );
	// As in print, a break at the top of a sheet still starts the next block on a new sheet.
	const top = paginate( [ 5, 10 ], sheet, [ true, false ] );
	assert.deepEqual( top.spacers, [ 0, 115 ] );
	assert.equal(
		paginate( [ 80, 5, 10 ], sheet, [ false, false, false ] ).pages,
		2
	);
} );

test( 'section totals add the marks up to the next section', () => {
	assert.deepEqual(
		sectionTotals( [
			{ kind: 'question', marks: 9 },
			{ kind: 'section', key: 'a' },
			{ kind: 'question', marks: 2 },
			{ kind: 'question', marks: '3' },
			{ kind: 'section', key: 'b' },
			{ kind: 'section', key: 'c' },
			{ kind: 'question', marks: 4 },
		] ),
		{ a: 5, b: 0, c: 4 }
	);
} );

test( 'paper block attributes are normalized', () => {
	assert.equal( limitMinutes( { value: 1.5, type: 'hours' } ), 90 );
	assert.equal( limitMinutes( { value: 45 } ), 45 );
	assert.equal( limitMinutes( null ), 0 );
	assert.deepEqual( answerSpace(), { style: 'lines', lines: 6 } );
	assert.deepEqual( answerSpace( { style: 'grid', lines: 99 } ), {
		style: 'grid',
		lines: 30,
	} );
	assert.deepEqual( columnsLayout( { columns: 7, flow: 'x', rule: 1 } ), {
		columns: 3,
		flow: 'flow',
		rule: true,
	} );
	assert.equal( columnsLayout().columns, 2 );
} );
