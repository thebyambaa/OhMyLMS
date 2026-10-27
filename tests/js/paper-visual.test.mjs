import test from 'node:test';
import assert from 'node:assert/strict';
import { paperQuestion } from '../../assets/src/features/paper/paperModel.mjs';
import {
	scaleTicks,
	visualFigure,
} from '../../assets/src/features/paper/paperVisual.mjs';

const figure = ( type, settings, showAnswers = false ) =>
	paperQuestion(
		{ id: 1, settings: { type, score: { value: 1 }, ...settings } },
		{ showAnswers }
	).figure;

const PRIVATE = {
	'number-line': { target: 7, tolerance: 0.5 },
	'shade-model': { answer: 3 },
	'count-blocks': { target: 243, canonical: true },
	'set-clock': { hour: 3, minute: 45, tolerance: 1 },
	'make-amount': { target: 3500 },
	'fill-level': { target: 500, tolerance: 50 },
	'build-chart': { values: { a: 4, b: 7 } },
	'grid-build': { constraints: { area: 12, perimeter: 14 } },
};
const PUBLIC = {
	'number-line': { min: 0, max: 10, step: 1 },
	'shade-model': { shape: 'grid', parts: 12, cols: 4 },
	'count-blocks': { places: [ 'hundreds', 'tens', 'ones' ] },
	'set-clock': { snap: 5, show_digital: true },
	'make-amount': { denominations: [ 100, 5000, 500, 1000 ], symbol: '₮' },
	'fill-level': { min: 0, max: 1000, step: 100, unit: 'ml' },
	'build-chart': {
		categories: [
			{ id: 'a', label: 'Apples' },
			{ id: 'b', label: 'Bananas' },
		],
		max: 10,
		step: 1,
	},
	'grid-build': { rows: 6, cols: 8 },
};

test( 'every visual type prints a figure, never its answer on the student paper', () => {
	for ( const [ type, open ] of Object.entries( PUBLIC ) ) {
		const paper = paperQuestion( {
			id: 1,
			settings: {
				type,
				score: { value: 1 },
				...open,
				...PRIVATE[ type ],
			},
		} );
		assert.equal( paper.layout, 'visual', type );
		assert.equal( paper.figure.figure, type );
		for ( const key of [
			'target',
			'shaded',
			'counts',
			'hour',
			'minute',
			'values',
			'constraints',
			'key',
		] ) {
			assert.equal(
				paper.figure[ key ],
				undefined,
				`${ type } leaks ${ key }`
			);
		}
		// The learner view the server sends draws the same figure.
		assert.deepEqual(
			paperQuestion( {
				id: 1,
				public: true,
				settings: { type, score: { value: 1 }, ...open },
			} ).figure,
			paper.figure,
			type
		);
	}
} );

test( 'scales place ticks on step values and label about ten of them', () => {
	assert.deepEqual( scaleTicks( 0, 10, 1 ).values.length, 11 );
	assert.equal( scaleTicks( 0, 10, 1 ).labelEvery, 1 );
	const many = scaleTicks( 0, 1000, 1 );
	assert.ok( many.values.length <= 101 );
	assert.ok( many.values.every( ( value ) => Number.isInteger( value ) ) );
	assert.equal( many.values.at( -1 ), 1000 );
	assert.deepEqual( scaleTicks( 10, 0, 5 ).values, [ 0, 5, 10 ] );
} );

test( 'answer keys draw the target on each figure', () => {
	const settings = ( type ) => ( { ...PUBLIC[ type ], ...PRIVATE[ type ] } );
	assert.equal(
		figure( 'number-line', settings( 'number-line' ), true ).target,
		7
	);
	assert.equal(
		figure( 'shade-model', settings( 'shade-model' ), true ).shaded,
		3
	);
	assert.deepEqual(
		figure( 'count-blocks', settings( 'count-blocks' ), true ).counts,
		{ hundreds: 2, tens: 4, ones: 3 }
	);
	const clock = figure( 'set-clock', settings( 'set-clock' ), true );
	assert.deepEqual(
		[ clock.hour, clock.minute, clock.key[ 0 ] ],
		[ 3, 45, '3:45' ]
	);
	const money = figure( 'make-amount', settings( 'make-amount' ), true );
	assert.deepEqual( money.denominations, [ 5000, 1000, 500, 100 ] );
	assert.deepEqual( money.counts, { 1000: 3, 500: 1 } );
	assert.equal(
		figure( 'fill-level', settings( 'fill-level' ), true ).target,
		500
	);
	assert.deepEqual(
		figure( 'build-chart', settings( 'build-chart' ), true ).values,
		{ a: 4, b: 7 }
	);
	assert.deepEqual(
		figure( 'grid-build', settings( 'grid-build' ), true ).constraints,
		{ area: 12, perimeter: 14, rectangle: false, connected: false }
	);
	// A zero answer is still an answer.
	assert.equal(
		figure( 'shade-model', { parts: 4, answer: 0 }, true ).shaded,
		0
	);
	assert.equal( figure( 'set-clock', { hour: 0, minute: 0 }, true ).hour, 0 );
} );

test( 'a chart may show its data table, from full settings or the learner view', () => {
	const full = figure( 'build-chart', {
		...PUBLIC[ 'build-chart' ],
		...PRIVATE[ 'build-chart' ],
		show_table: true,
	} );
	assert.deepEqual( full.table, [
		{ label: 'Apples', value: 4 },
		{ label: 'Bananas', value: 7 },
	] );
	assert.deepEqual(
		visualFigure( 'build-chart', {
			...PUBLIC[ 'build-chart' ],
			table: [ { label: 'Apples', value: 4 } ],
		} ).table,
		[ { label: 'Apples', value: 4 } ]
	);
} );
