import test from 'node:test';
import assert from 'node:assert/strict';
import {
	choiceModePatch,
	questionWorkspaceKey,
} from '../../assets/src/features/question-editor/choiceModel.mjs';
const question = {
	settings: { type: 'multiple-choice', partial_credit: true, required: true },
	questions: [
		{ id: 1, answer: 'A', is_correct: true, image_url: 'image.png' },
		{ id: 2, answer: 'B', is_correct: true },
		{ id: 3, answer: 'C', is_correct: false },
	],
};
test( 'changing to single selection preserves content and retains only the first correct option', () => {
	const patch = choiceModePatch( question, false );
	assert.equal( patch.settings.type, 'single-choice' );
	assert.equal( patch.settings.partial_credit, false );
	assert.equal( patch.settings.required, true );
	assert.deepEqual(
		patch.questions.map( ( item ) => item.is_correct ),
		[ true, false, false ]
	);
	assert.equal( patch.questions[ 0 ].image_url, 'image.png' );
	assert.equal( question.questions[ 1 ].is_correct, true );
} );
test( 'enabling multiple answers preserves answer IDs, text and metadata without silently enabling partial credit', () => {
	const single = {
		...question,
		settings: { type: 'single-choice', required: true },
		questions: question.questions.slice( 0, 1 ),
	};
	const patch = choiceModePatch( single, true );
	assert.equal( patch.settings.type, 'multiple-choice' );
	assert.equal( patch.settings.partial_credit, false );
	assert.deepEqual( patch.questions, single.questions );
} );

test( 'choice mode switches retain workspace identity while different questions and response families reset it', () => {
	assert.equal(
		questionWorkspaceKey( 12, 'single-choice' ),
		questionWorkspaceKey( 12, 'multiple-choice' )
	);
	assert.notEqual(
		questionWorkspaceKey( 12, 'single-choice' ),
		questionWorkspaceKey( 13, 'single-choice' )
	);
	assert.notEqual(
		questionWorkspaceKey( 12, 'multiple-choice' ),
		questionWorkspaceKey( 12, 'matching' )
	);
} );
