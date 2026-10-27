import test from 'node:test';
import assert from 'node:assert/strict';
import {
	INTERACTIVE_TYPES,
	languageTiles,
	blankIds,
	interactiveDefaults,
	interactiveIssues,
	markerIds,
	nextId,
	parseAmounts,
	addCategory,
	removeCategory,
	removeBucket,
	removeItem,
	sequencesFromText,
	syncBlanks,
	syncSlots,
	tilesFromText,
} from '../../assets/src/features/question-editor/interactiveModel.mjs';
import {
	QUESTION_BLOCK_TYPES,
	questionPreviewIssues,
	questionPreviewModel,
	questionTypePatch,
} from '../../assets/src/features/question-editor/questionBlocks.mjs';
import { describeExpected } from '../../assets/src/features/quiz-reports/model.mjs';

test( 'markers are found once, in order', () => {
	assert.deepEqual( markerIds( 'a {1} b {x} c {1}' ), [ '1', 'x' ] );
	assert.deepEqual( markerIds( 'no markers { 1 } {}' ), [] );
} );

test( 'every interactive type is offered in the question type list', () => {
	const offered = QUESTION_BLOCK_TYPES.map( ( [ type ] ) => type );
	for ( const type of INTERACTIVE_TYPES ) {
		assert.ok( offered.includes( type ), type );
	}
} );

test( 'choosing a type creates its default settings and no answer rows', () => {
	for ( const type of INTERACTIVE_TYPES ) {
		const patch = questionTypePatch( { settings: {} }, type, 1 );
		assert.equal( patch.settings.type, type );
		assert.deepEqual( patch.questions, [] );
	}
	const dropdown = questionTypePatch(
		{ settings: {} },
		'dropdown-blanks',
		1
	);
	assert.deepEqual(
		dropdown.settings.slots.map( ( slot ) => slot.id ),
		[ '1', '2' ]
	);
} );

test( 'dropdowns follow the markers in the sentence', () => {
	const slots = interactiveDefaults( 'dropdown-blanks' ).slots;
	const synced = syncSlots( 'Only {2} and {3}', slots );
	assert.deepEqual(
		synced.map( ( slot ) => slot.id ),
		[ '2', '3' ]
	);
	assert.equal( synced[ 0 ], slots[ 1 ], 'existing dropdowns are kept' );
	assert.deepEqual( synced[ 1 ].choices, [ '', '' ] );
} );

test( 'multi-blank finds blanks in a sentence or in table cells', () => {
	assert.deepEqual( blankIds( { layout: 'inline', text: '{a} + {b}' } ), [
		'a',
		'b',
	] );
	assert.deepEqual(
		blankIds( {
			layout: 'table',
			text: '{ignored}',
			rows: [
				[ '1', '{x}' ],
				[ '2', '{y}' ],
			],
		} ),
		[ 'x', 'y' ]
	);
	const settings = {
		layout: 'inline',
		text: '{a}',
		blanks: { a: { kind: 'numerical', answer: 1 }, gone: { kind: 'text' } },
	};
	assert.deepEqual( Object.keys( syncBlanks( settings ) ), [ 'a' ] );
} );

test( 'removing a group or item keeps the answer key consistent', () => {
	const settings = {
		buckets: [
			{ id: 'b1', label: 'A' },
			{ id: 'b2', label: 'B' },
		],
		items: [
			{ id: 'i1', text: 'x' },
			{ id: 'i2', text: 'y' },
		],
		key: { i1: 'b1', i2: 'b2' },
	};
	assert.deepEqual( removeBucket( settings, 'b1' ).key, { i2: 'b2' } );
	assert.deepEqual( removeItem( settings, 'i2' ).key, { i1: 'b1' } );
	assert.equal( nextId( settings.buckets, 'b' ), 'b3' );
} );

test( 'tiles are typed as words and orders as lines', () => {
	assert.deepEqual( tilesFromText( ' 3  x + 4 ' ), [ '3', 'x', '+', '4' ] );
	assert.deepEqual( sequencesFromText( '2 + 3\n\n3 + 2\n' ), [
		[ '2', '+', '3' ],
		[ '3', '+', '2' ],
	] );
} );

test( 'authors are warned about incomplete questions', () => {
	assert.deepEqual(
		interactiveIssues(
			'dropdown-blanks',
			interactiveDefaults( 'dropdown-blanks' )
		),
		[]
	);
	assert.ok(
		interactiveIssues(
			'categorize',
			interactiveDefaults( 'categorize' )
		).includes( 'groups' )
	);
	assert.deepEqual(
		interactiveIssues(
			'multi-blank',
			interactiveDefaults( 'multi-blank' )
		),
		[]
	);
	assert.ok(
		interactiveIssues(
			'build-expression',
			interactiveDefaults( 'build-expression' )
		).includes( 'tiles' )
	);
	const question = {
		name: 'Pick',
		settings: { type: 'build-expression', correct: [ '1', '+', '2' ] },
	};
	assert.ok( ! questionPreviewIssues( question ).includes( 'tiles' ) );
	assert.equal(
		questionPreviewModel( question ).interactive.correct.length,
		3
	);
} );

test( 'reports describe the expected answer of each new type', () => {
	assert.equal(
		describeExpected( {
			settings: {
				type: 'dropdown-blanks',
				slots: [ { id: '1', answer: 'positive' } ],
			},
		} ),
		'{1} = positive'
	);
	assert.equal(
		describeExpected( {
			settings: { type: 'build-expression', correct: [ '3', 'x' ] },
		} ),
		'3 x'
	);
	assert.equal(
		describeExpected( {
			settings: {
				type: 'categorize',
				buckets: [ { id: 'b1', label: 'Polygon' } ],
				items: [ { id: 'i1', text: 'Triangle' } ],
				key: { i1: 'b1' },
			},
		} ),
		'Triangle → Polygon'
	);
} );

test( 'math expression questions start valid and warn when the answer is empty', () => {
	assert.ok( INTERACTIVE_TYPES.includes( 'expression' ) );
	const defaults = interactiveDefaults( 'expression' );
	assert.equal( defaults.form, 'any' );
	assert.deepEqual( interactiveIssues( 'expression', defaults ), [] );
	assert.deepEqual( interactiveIssues( 'expression', { answer: '  ' } ), [
		'answer',
	] );
	const patch = questionTypePatch( { settings: {} }, 'expression', 1 );
	assert.deepEqual( patch.questions, [] );
	assert.equal( patch.settings.answer, defaults.answer );
	assert.equal(
		interactiveDefaults( 'build-expression' ).equivalence,
		false
	);
	assert.deepEqual(
		interactiveIssues( 'multi-blank', {
			layout: 'inline',
			text: '{a}',
			blanks: { a: { kind: 'expression', answer: '' } },
		} ),
		[ 'answers' ]
	);
	assert.deepEqual(
		interactiveIssues( 'multi-blank', {
			layout: 'inline',
			text: '{a}',
			blanks: { a: { kind: 'expression', answer: 'x+1' } },
		} ),
		[]
	);
	assert.equal(
		describeExpected( {
			settings: {
				type: 'expression',
				answer: 'x=2',
				alternatives: [ 'x=-2' ],
			},
		} ),
		'x=2  or  x=-2'
	);
} );
test( 'every visual type starts valid and is warned about when broken', () => {
	const visual = [
		'number-line',
		'shade-model',
		'count-blocks',
		'set-clock',
		'make-amount',
		'fill-level',
		'build-chart',
		'grid-build',
	];
	for ( const type of visual ) {
		assert.ok( INTERACTIVE_TYPES.includes( type ), type );
		assert.deepEqual(
			interactiveIssues( type, interactiveDefaults( type ) ),
			[],
			type
		);
		const patch = questionTypePatch( { settings: {} }, type, 1 );
		assert.deepEqual( patch.questions, [], type );
		assert.equal( patch.settings.type, type );
	}
	assert.ok(
		interactiveIssues( 'number-line', {
			min: 0,
			max: 10,
			step: 1,
			target: 11,
		} ).includes( 'target' )
	);
	assert.ok(
		interactiveIssues( 'number-line', {
			min: 5,
			max: 5,
			step: 1,
			target: 5,
		} ).includes( 'range' )
	);
	assert.ok(
		interactiveIssues( 'number-line', {
			min: 0,
			max: 1000,
			step: 1,
			target: 5,
		} ).includes( 'step' )
	);
	assert.ok(
		interactiveIssues( 'shade-model', { parts: 4, answer: 5 } ).includes(
			'shaded'
		)
	);
	assert.ok(
		interactiveIssues( 'count-blocks', {
			places: [ 'hundreds' ],
			target: 243,
		} ).includes( 'target' )
	);
	assert.ok(
		interactiveIssues( 'count-blocks', { places: [], target: 5 } ).includes(
			'places'
		)
	);
	assert.ok(
		interactiveIssues( 'set-clock', { hour: 13, minute: 0 } ).includes(
			'time'
		)
	);
	assert.ok(
		interactiveIssues( 'make-amount', {
			denominations: [ 1000, 500 ],
			target: 750,
		} ).includes( 'target' )
	);
	assert.ok(
		interactiveIssues( 'build-chart', {
			categories: [ { id: 'a', label: 'A' } ],
			max: 10,
			step: 1,
			values: {},
		} ).includes( 'values' )
	);
	assert.ok(
		interactiveIssues( 'grid-build', {
			rows: 3,
			cols: 3,
			constraints: {},
		} ).includes( 'conditions' )
	);
	assert.ok(
		interactiveIssues( 'grid-build', {
			rows: 3,
			cols: 3,
			constraints: { area: 12 },
		} ).includes( 'area' )
	);
} );

test( 'amounts and chart bars are edited without losing consistency', () => {
	assert.deepEqual(
		parseAmounts( '5000, 1000 ;500 500 abc -3 2.5 100' ),
		[ 5000, 1000, 500, 100 ]
	);
	const added = addCategory( {
		categories: [ { id: 'a', label: 'A' } ],
		values: { a: 3 },
	} );
	assert.equal( added.categories.length, 2 );
	assert.equal( added.values[ added.categories[ 1 ].id ], 0 );
	const removed = removeCategory( { ...added }, 'a' );
	assert.deepEqual( Object.keys( removed.values ), [
		added.categories[ 1 ].id,
	] );
} );

test( 'reports describe the expected answer of every visual type', () => {
	const expected = ( settings ) => describeExpected( { settings } );
	assert.equal( expected( { type: 'number-line', target: 3.5 } ), '3.5' );
	assert.equal(
		expected( { type: 'fill-level', target: 750, unit: 'ml' } ),
		'750 ml'
	);
	assert.equal(
		expected( { type: 'shade-model', answer: 3, parts: 8 } ),
		'3 / 8'
	);
	assert.equal( expected( { type: 'count-blocks', target: 243 } ), '243' );
	assert.equal(
		expected( { type: 'set-clock', hour: 3, minute: 5 } ),
		'3:05'
	);
	assert.equal(
		expected( { type: 'make-amount', target: 3500, symbol: '₮' } ),
		'3500 ₮'
	);
	assert.equal(
		expected( {
			type: 'build-chart',
			categories: [ { id: 'a', label: 'Cats' } ],
			values: { a: 4 },
		} ),
		'Cats = 4'
	);
	assert.equal(
		expected( {
			type: 'grid-build',
			constraints: { area: 12, perimeter: 14, rectangle: true },
		} ),
		'area 12, perimeter 14, rectangle'
	);
} );
test( 'language tiles preserve repeated letters, graphemes and words', () => {
	assert.deepEqual( languageTiles( 'үүл', 'letters' ), [ 'ү', 'ү', 'л' ] );
	assert.deepEqual( languageTiles( 'e\u0301', 'letters' ), [ 'é' ] );
	assert.deepEqual( languageTiles( 'I see a cloud', 'words' ), [
		'I',
		'see',
		'a',
		'cloud',
	] );
	assert.equal( languageTiles( '👩‍🏫', 'letters' ).length, 1 );
} );
test( 'automatic text answers require bounded accepted text', () => {
	assert.deepEqual(
		interactiveIssues( 'text-entry', interactiveDefaults( 'text-entry' ) ),
		[]
	);
	for ( const accepted of [ [], [ '' ], [ {} ], Array( 51 ).fill( 'a' ) ] ) {
		assert.ok( interactiveIssues( 'text-entry', { accepted } ).length );
	}
} );
