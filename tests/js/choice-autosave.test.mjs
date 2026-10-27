import test from 'node:test';
import assert from 'node:assert/strict';
import {
	choiceReadyToSave,
	reconcileChoiceSave,
} from '../../assets/src/features/learning/choiceAutosave.mjs';

test( 'automatic saving waits for a complete prompt, options and answer key', () => {
	const question = {
		name: 'Question',
		settings: { type: 'single-choice' },
		questions: [ { answer: '4', is_correct: true }, { answer: '5' } ],
	};
	assert.equal( choiceReadyToSave( question ), true );
	assert.equal( choiceReadyToSave( { ...question, name: '' } ), false );
	assert.equal(
		choiceReadyToSave( {
			...question,
			questions: [ { answer: '4', is_correct: true } ],
		} ),
		false
	);
	assert.equal(
		choiceReadyToSave( {
			...question,
			questions: [ { answer: '4' }, { answer: '5' } ],
		} ),
		false
	);
	assert.equal(
		choiceReadyToSave( {
			...question,
			questions: [ { answer: '4', is_correct: true }, { answer: '' } ],
		} ),
		false
	);
} );

test( 'a save response preserves typing, settings and option reordering during the request', () => {
	const submitted = {
		name: 'Before',
		settings: { type: 'multiple-choice', required: false },
		questions: [
			{ id: 101, temp: true, answer: 'A' },
			{ id: 102, temp: true, answer: 'B' },
		],
	};
	const current = {
		...submitted,
		name: 'Still typing',
		settings: { ...submitted.settings, required: true },
		questions: [
			{ ...submitted.questions[ 1 ], answer: 'Edited B' },
			submitted.questions[ 0 ],
			{ id: 103, temp: true, answer: 'New option' },
		],
	};
	const next = reconcileChoiceSave( submitted, current, {
		id: 9,
		options: [ { id: 201 }, { id: 202 } ],
	} );
	assert.equal( next.name, 'Still typing' );
	assert.equal( next.settings.required, true );
	assert.deepEqual(
		next.questions.map( ( option ) => option.answer ),
		[ 'Edited B', 'A', 'New option' ]
	);
	assert.deepEqual(
		next.questions.map( ( option ) => option.id ),
		[ 202, 201, 103 ]
	);
	assert.deepEqual(
		next.questions.slice( 0, 2 ).map( ( option ) => option.clientKey ),
		[ 102, 101 ]
	);
} );

test( 'short and long answer questions save without choice options or a correct-answer key', () => {
	for ( const type of [ 'short-text', 'long-text' ] ) {
		const question = {
			name: 'Explain your answer',
			settings: { type },
			questions: [ { answer: '', is_correct: false } ],
		};
		assert.equal( choiceReadyToSave( question ), true );
		assert.equal( choiceReadyToSave( { ...question, name: '' } ), false );
	}
} );

test( 'reorder questions use their item order rather than correctness flags', () => {
	const question = {
		name: 'Arrange the stages',
		settings: { type: 'reorder' },
		questions: [
			{ answer: 'First', is_correct: false, order_number: 1 },
			{ answer: 'Second', is_correct: false, order_number: 2 },
		],
	};
	assert.equal( choiceReadyToSave( question ), true );
	assert.equal(
		choiceReadyToSave( {
			...question,
			questions: [ { answer: 'First' } ],
		} ),
		false
	);
	assert.equal(
		choiceReadyToSave( {
			...question,
			questions: [ { answer: 'First' }, { answer: '' } ],
		} ),
		false
	);
} );

test( 'native visual questions save settings without requiring choice options', async () => {
	const { questionTypePatch } =
		await import( '../../assets/src/features/question-editor/questionBlocks.mjs' );
	for ( const type of [
		'number-line',
		'shade-model',
		'count-blocks',
		'set-clock',
		'make-amount',
		'fill-level',
		'build-chart',
		'grid-build',
		'labeling',
		'hotspot',
		'draw',
	] ) {
		const draft = {
			name: 'Visual question',
			...questionTypePatch( { settings: {} }, type ),
		};
		if ( [ 'labeling', 'hotspot' ].includes( type ) ) {
			assert.equal( choiceReadyToSave( draft ), false );
			draft.settings.image_url = 'https://example.com/diagram.png';
		}
		assert.equal( choiceReadyToSave( draft ), true, type );
		assert.equal(
			choiceReadyToSave( { ...draft, name: '' } ),
			false,
			type
		);
	}
	const invalid = {
		name: 'Scale',
		...questionTypePatch( { settings: {} }, 'number-line' ),
	};
	invalid.settings.target = invalid.settings.max + 1;
	assert.equal( choiceReadyToSave( invalid ), false );
} );

test( 'numerical autosave accepts zero and alternatives but waits for a finite key', () => {
	const ready = ( settings ) =>
		choiceReadyToSave( {
			name: 'Calculate',
			settings: { type: 'numerical', ...settings },
			questions: [],
		} );
	assert.equal( ready( {} ), false );
	for ( const answer of [ '', ' ', null, Infinity, NaN ] )
		assert.equal( ready( { answer } ), false );
	assert.equal( ready( { answer: 0 } ), true );
	assert.equal( ready( { answers: [ 2, 3 ] } ), true );
	assert.equal( ready( { answer: 2, answers: [] } ), false );
	assert.equal( ready( { answer: 2, tolerance: -1 } ), false );
} );

test( 'structured autosave waits for valid part identities, marks and answer keys', () => {
	const ready = ( parts ) =>
		choiceReadyToSave( {
			name: 'Solve each part',
			settings: { type: 'structured', parts },
			questions: [],
		} );
	const number = { id: 'a', kind: 'numerical', marks: 2, answer: 0 };
	const text = { id: 'b', kind: 'text', marks: 1, accepted: [ 'yes' ] };
	assert.equal( ready( [] ), false );
	assert.equal(
		ready( [ number, text, { id: 'c', kind: 'written', marks: 3 } ] ),
		true
	);
	assert.equal( ready( [ { ...number, answer: undefined } ] ), false );
	assert.equal( ready( [ { ...text, accepted: [] } ] ), false );
	assert.equal( ready( [ { ...number, marks: -1 } ] ), false );
	assert.equal( ready( [ number, { ...text, id: 'a' } ] ), false );
} );

test( 'graphing autosave validates its existing graph settings without choice rows', async () => {
	const { questionTypePatch } =
		await import( '../../assets/src/features/question-editor/questionBlocks.mjs' );
	const draft = {
		name: 'Plot the point',
		...questionTypePatch( { settings: {} }, 'graphing' ),
	};
	assert.equal( choiceReadyToSave( draft ), true );
	assert.equal( choiceReadyToSave( { ...draft, name: '' } ), false );
	draft.settings.points = [];
	assert.equal( choiceReadyToSave( draft ), false );
} );

test( 'a randomized numerical question may use variables for its answer, alternatives and tolerance', () => {
	const make = ( settings ) => ( {
		name: 'Question',
		settings: { type: 'numerical', ...settings },
		questions: [],
	} );
	const template = { variables: [ { name: 'a', type: 'int', min: 1, max: 9 } ] };
	assert.equal( choiceReadyToSave( make( { answer: '{{a*2}}', template } ) ), true );
	assert.equal(
		choiceReadyToSave( make( { answer: 4, answers: [ '{{a}}', 5 ], tolerance: '{{a}}', template } ) ),
		true
	);
	// Without variables a formula is still not a number.
	assert.equal( choiceReadyToSave( make( { answer: '{{a*2}}' } ) ), false );
	assert.equal( choiceReadyToSave( make( { answer: 'abc', template } ) ), false );
} );
