import test from 'node:test';
import assert from 'node:assert/strict';
import {
	newQuestionCard,
	prepareQuizPayload,
	moveOption,
} from '../../assets/src/features/quizzes/model.mjs';

test( 'new form question has a usable choice type and independent answer rows', () => {
	const card = newQuestionCard( {}, 100 );
	assert.equal( card.settings.type, 'single-choice' );
	assert.equal( card.questions.length, 2 );
	assert.equal( card.settings.required, false );
	assert.equal(
		prepareQuizPayload( { id: 1 }, [ card ] ).content[ 0 ].id,
		undefined
	);
} );
test( 'duplicate keeps content and grading but removes shared identity and option ownership', () => {
	const original = {
		id: 8,
		readonly: true,
		modified: 'old',
		pinned_version_id: 1,
		settings: {
			type: 'structured',
			required: true,
			parts: [ { id: 'p1', marks: 2 } ],
		},
		name: 'Prompt',
		description: '<p>Body</p>',
		questions: [
			{ id: 17, question_id: 8, answer: 'A', is_correct: true },
		],
	};
	const copy = newQuestionCard( original, 200 );
	copy.settings.parts[ 0 ].marks = 3;
	assert.equal( original.settings.parts[ 0 ].marks, 2 );
	assert.equal( copy.readonly, undefined );
	assert.equal( copy.pinned_version_id, undefined );
	assert.equal( copy.settings.required, true );
	assert.equal( copy.questions[ 0 ].question_id, undefined );
	const payload = prepareQuizPayload( { id: 1 }, [ copy ] );
	assert.equal( payload.content[ 0 ].questions[ 0 ].id, undefined );
	assert.equal( copy.description, original.description );
} );
test( 'moving cards preserves question identities and renumbers placement', () => {
	const cards = [
		{ id: 8, order_number: 1 },
		{ id: 9, order_number: 2 },
	];
	assert.deepEqual( moveOption( cards, 1, 0 ), [
		{ id: 9, order_number: 1 },
		{ id: 8, order_number: 2 },
	] );
	assert.equal( cards[ 0 ].id, 8 );
} );
