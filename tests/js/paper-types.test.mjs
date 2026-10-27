import test from 'node:test';
import assert from 'node:assert/strict';
import {
	paperQuestion,
	paperSettings,
	paperVersion,
	questionSeed,
	versionItems,
} from '../../assets/src/features/paper/paperModel.mjs';
import {
	fnv1a,
	jumble,
	learnerSettings,
	splitMarkers,
} from '../../assets/src/features/paper/paperTypes.mjs';

const marks = { enabled: true, value: 2 };
const leaks = ( model, secrets ) => {
	const text = JSON.stringify( model, ( key, value ) =>
		value instanceof Set ? [ ...value ] : value
	);
	return secrets.filter( ( secret ) => text.includes( secret ) );
};

test( 'jumbling is a fixed hash order shared with the server', () => {
	// Reference values; OhMyLMS\Exams\PrintPaper::hash() and jumble() must return the same.
	assert.equal( fnv1a( '' ), 0x811c9dc5 );
	assert.equal( fnv1a( 'a' ), 0xe40c292c );
	assert.equal( fnv1a( '7|Égalité' ), fnv1a( '7|Égalité' ) );
	const texts = [ 'egg', 'larva', 'pupa', 'butterfly' ];
	const order = jumble( texts, '42' );
	assert.deepEqual( [ ...order ].sort(), [ 0, 1, 2, 3 ] );
	assert.deepEqual( jumble( texts, '42' ), order, 'Same seed, same order' );
	// An order that would match the answer order is rotated.
	for ( let seed = 0; seed < 200; seed++ ) {
		const pair = jumble( [ 'a', 'b' ], String( seed ), {
			avoidIdentity: true,
		} );
		assert.deepEqual( pair, [ 1, 0 ] );
	}
} );

test( 'type-the-answer keys list the accepted answers from the settings', () => {
	const question = {
		settings: {
			type: 'text-entry',
			score: marks,
			accepted: [ 'H2O', 'h2o' ],
		},
	};
	assert.equal( paperQuestion( question ).answers, undefined );
	assert.deepEqual(
		paperQuestion( question, { showAnswers: true } ).answers,
		[ 'H2O', 'h2o' ]
	);
} );

test( 'markers split into text and blanks', () => {
	assert.deepEqual( splitMarkers( 'x = {a} cm, y = {b}' ), [
		{ text: 'x = ' },
		{ id: 'a' },
		{ text: ' cm, y = ' },
		{ id: 'b' },
	] );
} );

const matching = {
	id: 9,
	settings: { type: 'matching', score: marks },
	questions: [
		{
			answer: 'France',
			order_number: 1,
			matching_data: { label: 'Paris' },
		},
		{ answer: 'Italy', order_number: 2, matching_data: { label: 'Rome' } },
		{
			answer: 'Spain',
			order_number: 3,
			matching_data: { label: 'Madrid' },
		},
	],
};

test( 'matching prints two lettered columns and the key pairs them', () => {
	const paper = paperQuestion( matching );
	assert.equal( paper.layout, 'matching' );
	assert.deepEqual(
		paper.left.map( ( item ) => item.text ),
		[ 'France', 'Italy', 'Spain' ]
	);
	assert.deepEqual( [ ...paper.right.map( ( item ) => item.text ) ].sort(), [
		'Madrid',
		'Paris',
		'Rome',
	] );
	assert.ok(
		paper.right.some(
			( item, index ) =>
				item.text !== [ 'Paris', 'Rome', 'Madrid' ][ index ]
		),
		'Answers are not printed beside their pairs'
	);
	assert.equal( paper.key, undefined );
	const key = paperQuestion( matching, { showAnswers: true } );
	for ( const [ index, line ] of key.key.entries() ) {
		const letter = line.split( ' → ' )[ 1 ];
		const right = key.right.find( ( item ) => item.letter === letter );
		assert.equal(
			right.text,
			[ 'Paris', 'Rome', 'Madrid' ][ index ],
			'Key letters point at the right answers'
		);
	}
	// The print page gets the right column already jumbled by the server, in the same order.
	const student = paperQuestion( {
		id: 9,
		public: true,
		settings: {
			type: 'matching',
			score: marks,
			match_right: paper.right.map( ( item ) => item.text ),
		},
		questions: [
			{ answer: 'France' },
			{ answer: 'Italy' },
			{ answer: 'Spain' },
		],
	} );
	assert.deepEqual( student.right, paper.right );
	assert.equal( student.key, undefined );
} );

const reorder = {
	id: 5,
	settings: { type: 'reorder', score: marks },
	questions: [ 'egg', 'larva', 'pupa', 'butterfly' ].map(
		( answer, index ) => ( { answer, order_number: index + 1 } )
	),
};

test( 'reorder prints jumbled lettered steps and the key gives their order', () => {
	const paper = paperQuestion( reorder );
	assert.equal( paper.layout, 'reorder' );
	const printed = paper.items.map( ( item ) => item.text );
	assert.notDeepEqual( printed, [ 'egg', 'larva', 'pupa', 'butterfly' ] );
	const key = paperQuestion( reorder, { showAnswers: true } )
		.key[ 0 ].split( ', ' )
		.map(
			( letter ) =>
				paper.items.find( ( item ) => item.letter === letter ).text
		);
	assert.deepEqual( key, [ 'egg', 'larva', 'pupa', 'butterfly' ] );
} );

test( 'dropdown blanks print numbered blanks with their choices', () => {
	const question = {
		id: 3,
		settings: {
			type: 'dropdown-blanks',
			score: marks,
			text: 'Water boils at {1} degrees.',
			slots: [
				{ id: '1', choices: [ '100', '50', '0' ], answer: '100' },
			],
		},
	};
	const prompt = '<p>Water boils at {1} degrees.</p>';
	const paper = paperQuestion( question, { prompt } );
	assert.equal( paper.layout, 'dropdown' );
	assert.ok( paper.prompt.includes( 'ohmylms-print-blank">(1)</span>' ) );
	assert.deepEqual( [ ...paper.slots[ 0 ].choices ].sort(), [
		'0',
		'100',
		'50',
	] );
	assert.deepEqual( leaks( paper, [ 'answers' ] ), [] );
	assert.deepEqual(
		paperQuestion( question, { prompt, showAnswers: true } ).key,
		[ '(1) 100' ]
	);
} );

test( 'multi-blank questions print inline blanks or a table, with units', () => {
	const inline = {
		id: 4,
		settings: {
			type: 'multi-blank',
			score: marks,
			text: 'Speed {a}, time {b}',
			blanks: {
				a: { kind: 'numerical', answer: 12, unit: 'm/s' },
				b: { kind: 'text', accepted: [ 'ten seconds' ] },
			},
		},
	};
	const paper = paperQuestion( inline, {
		prompt: '<p>Speed {a}, time {b}</p>',
	} );
	assert.equal( paper.layout, 'multi-blank' );
	assert.equal( paper.units.a, 'm/s' );
	assert.equal( paper.table, null );
	assert.deepEqual( leaks( paper, [ 'ten seconds', '12' ] ), [] );
	assert.deepEqual( paperQuestion( inline, { showAnswers: true } ).key, [
		'(a) 12',
		'(b) ten seconds',
	] );
	const table = paperQuestion( {
		id: 4,
		settings: {
			type: 'multi-blank',
			layout: 'table',
			columns: [ 'x', 'y' ],
			rows: [ [ '1', '{a}' ] ],
			blanks: { a: { kind: 'numerical', answer: 3 } },
		},
	} );
	assert.deepEqual( table.table.rows[ 0 ][ 1 ], [ { id: 'a' } ] );
} );

test( 'sorting prints lettered items and a table of groups', () => {
	const question = {
		id: 8,
		settings: {
			type: 'categorize',
			score: marks,
			buckets: [
				{ id: 'm', label: 'Mammals' },
				{ id: 'b', label: 'Birds' },
			],
			items: [
				{ id: 'i1', text: 'Dog' },
				{ id: 'i2', text: 'Eagle' },
				{ id: 'i3', text: 'Cat' },
			],
			key: { i1: 'm', i2: 'b', i3: 'm' },
		},
	};
	const paper = paperQuestion( question );
	assert.equal( paper.layout, 'categorize' );
	assert.deepEqual(
		paper.groups.map( ( group ) => group.label ),
		[ 'Mammals', 'Birds' ]
	);
	const key = paperQuestion( question, { showAnswers: true } ).key;
	const letterOf = ( text ) =>
		paper.items.find( ( item ) => item.text === text ).letter;
	assert.equal(
		key[ 0 ],
		`Mammals: ${ [ letterOf( 'Dog' ), letterOf( 'Cat' ) ].sort().join( ', ' ) }`
	);
	assert.equal( key[ 1 ], `Birds: ${ letterOf( 'Eagle' ) }` );
	// The print page receives the learner view; items keep the same order.
	const student = paperQuestion( {
		id: 8,
		public: true,
		settings: {
			type: 'categorize',
			score: marks,
			buckets: question.settings.buckets,
			items: [ ...question.settings.items ].reverse(),
		},
	} );
	assert.deepEqual( student.items, paper.items );
} );

test( 'tiles and expressions print an answer space, with the key on request', () => {
	const tiles = {
		id: 2,
		settings: {
			type: 'build-expression',
			score: marks,
			correct: [ '2', 'x', '+', '3' ],
			distractors: [ '-' ],
		},
	};
	const paper = paperQuestion( tiles );
	assert.equal( paper.layout, 'tiles' );
	assert.equal( paper.tiles.length, 5 );
	assert.deepEqual( paperQuestion( tiles, { showAnswers: true } ).key, [
		'2 x + 3',
	] );
	const expression = {
		id: 1,
		settings: { type: 'expression', score: marks, answer: '3x+9' },
	};
	assert.equal( paperQuestion( expression ).layout, 'answer-line' );
	assert.deepEqual( leaks( paperQuestion( expression ), [ '3x+9' ] ), [] );
	assert.deepEqual(
		paperQuestion( expression, { showAnswers: true } ).answers,
		[ '$3x+9$' ]
	);
} );

const crossword = {
	id: 6,
	settings: {
		type: 'crossword',
		score: marks,
		size: 5,
		entries: [
			{
				id: 'w1',
				clue: 'Feline',
				answer: 'CAT',
				row: 0,
				col: 0,
				direction: 'across',
			},
			{
				id: 'w2',
				clue: 'Not cold',
				answer: 'COLD',
				row: 0,
				col: 0,
				direction: 'down',
			},
		],
	},
};

test( 'crosswords print the grid, numbers and clues, and the key fills them', () => {
	const paper = paperQuestion( crossword );
	assert.equal( paper.layout, 'crossword' );
	assert.equal( paper.size, 5 );
	assert.ok( paper.cells.has( 'r0c2' ) && paper.cells.has( 'r3c0' ) );
	assert.equal( paper.numbers.r0c0, 1 );
	assert.deepEqual( paper.across, [
		{ number: 1, text: 'Feline', length: 3 },
	] );
	assert.deepEqual( paper.letters, {} );
	assert.deepEqual( leaks( paper, [ 'CAT', 'COLD' ] ), [] );
	const key = paperQuestion( crossword, { showAnswers: true } );
	assert.equal( key.letters.r0c2, 'T' );
	assert.equal( key.letters.r3c0, 'D' );
	// The learner view from the server prints the same grid.
	const student = paperQuestion( {
		id: 6,
		public: true,
		settings: {
			score: marks,
			...learnerSettings( 'crossword', crossword.settings, false ),
			entries: undefined,
			type: 'crossword',
		},
	} );
	assert.deepEqual( [ ...student.cells ], [ ...paper.cells ] );
	assert.deepEqual( student.across, paper.across );
} );

test( 'wordsearches print the letter grid and word list; the key marks the words', () => {
	const question = {
		id: 7,
		settings: {
			type: 'wordsearch',
			score: marks,
			size: 4,
			with_clues: false,
			grid: [
				[ 'C', 'A', 'T', 'X' ],
				[ 'Q', 'W', 'E', 'R' ],
				[ 'D', 'O', 'G', 'Z' ],
				[ 'M', 'N', 'B', 'V' ],
			],
			entries: [
				{
					id: 'w1',
					clue: '',
					answer: 'CAT',
					row: 0,
					col: 0,
					direction: 'across',
				},
				{
					id: 'w2',
					clue: '',
					answer: 'DOG',
					row: 2,
					col: 0,
					direction: 'across',
				},
			],
		},
	};
	const paper = paperQuestion( question );
	assert.equal( paper.layout, 'wordsearch' );
	assert.deepEqual( paper.words, [ 'CAT', 'DOG' ] );
	assert.equal( paper.marked.size, 0 );
	const key = paperQuestion( question, { showAnswers: true } );
	assert.ok( key.marked.has( 'r0c1' ) && key.marked.has( 'r2c2' ) );
	assert.equal( key.marked.size, 6 );
} );

const item = ( id, type = 'single-choice' ) => ( {
	kind: 'question',
	question: { id, settings: { type } },
} );
const ids = ( items ) =>
	items.flatMap( ( entry ) =>
		entry.kind === 'question'
			? [ entry.question.id ]
			: [ `[${ entry.name || entry.kind }]`, ...ids( entry.items || [] ) ]
	);

test( 'versions shuffle each run of questions in place', () => {
	const items = [
		{ kind: 'paper', name: 'header', items: [] },
		item( 1 ),
		item( 2 ),
		item( 3 ),
		{ kind: 'paper', name: 'section', items: [] },
		item( 4 ),
		item( 5, 'statement' ),
		item( 6 ),
		item( 7 ),
		{
			kind: 'paper',
			name: 'columns',
			items: [ item( 8 ), item( 9 ), { ...item( 10 ), span: true } ],
		},
	];
	assert.equal( versionItems( items, 'A' ), items );
	const b = ids( versionItems( items, 'B' ) );
	// Anchors stay; each run keeps its questions.
	assert.equal( b[ 0 ], '[header]' );
	assert.deepEqual( [ ...b.slice( 1, 4 ) ].sort(), [ 1, 2, 3 ] );
	assert.notDeepEqual( b.slice( 1, 4 ), [ 1, 2, 3 ] );
	assert.deepEqual( b.slice( 4, 7 ), [ '[section]', 4, 5 ] );
	assert.deepEqual( [ ...b.slice( 7, 9 ) ].sort(), [ 6, 7 ] );
	assert.deepEqual( b.slice( 7, 9 ), [ 7, 6 ] );
	assert.equal( b[ 9 ], '[columns]' );
	assert.deepEqual( b.slice( 10 ), [ 9, 8, 10 ] );
	assert.deepEqual( ids( versionItems( items, 'B' ) ), b, 'Repeatable' );
	assert.notDeepEqual( ids( versionItems( items, 'C' ) ), b );
} );

test( 'version letters and seeds', () => {
	const two = paperSettings( { versions: 2 } );
	assert.equal( paperVersion( 'b', two ), 'B' );
	assert.equal(
		paperVersion( 'C', two ),
		'A',
		'Only versions the paper offers'
	);
	assert.equal( paperVersion( 'x', two ), 'A' );
	assert.equal( questionSeed( 12, 'A' ), '12' );
	assert.equal( questionSeed( 12, 'C' ), '12:C' );
} );

test( 'other versions reorder choice options and keep the key right', () => {
	const choice = {
		id: 31,
		settings: { type: 'single-choice', score: marks },
		questions: [ 'Venus', 'Mars', 'Jupiter', 'Mercury' ].map(
			( answer, index ) => ( {
				answer,
				is_correct: answer === 'Mars',
				order_number: index + 1,
			} )
		),
	};
	const a = paperQuestion( choice, { showAnswers: true } );
	const b = paperQuestion( choice, { showAnswers: true, version: 'B' } );
	assert.deepEqual(
		a.options.map( ( option ) => option.text ),
		[ 'Venus', 'Mars', 'Jupiter', 'Mercury' ]
	);
	assert.notDeepEqual(
		b.options.map( ( option ) => option.text ),
		a.options.map( ( option ) => option.text )
	);
	assert.equal( b.options.find( ( option ) => option.correct ).text, 'Mars' );
	assert.deepEqual(
		b.options.map( ( option ) => option.letter ),
		[ 'A', 'B', 'C', 'D' ]
	);
	const trueFalse = paperQuestion(
		{
			id: 32,
			settings: { type: 'true-false', score: marks },
			questions: [
				{ answer: 'True', order_number: 1 },
				{ answer: 'False', order_number: 2 },
			],
		},
		{ version: 'C' }
	);
	assert.deepEqual(
		trueFalse.options.map( ( option ) => option.text ),
		[ 'True', 'False' ]
	);
	// Matching and other jumbled lists differ between versions too.
	assert.notDeepEqual(
		paperQuestion( reorder, { version: 'B' } ).items,
		paperQuestion( reorder ).items
	);
} );
