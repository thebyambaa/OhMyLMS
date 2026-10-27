import test from 'node:test';
import assert from 'node:assert/strict';
import {
	prepareQuizPayload,
	canLeaveQuestion,
	moveOption,
} from '../../assets/src/features/quizzes/model.mjs';
test( 'quiz save preserves persisted IDs and extension data without mutating editor state', () => {
	const quiz = { id: 4, settings: { allow_attempts: 3 } };
	const questions = [
		{
			id: 8,
			name: 'Saved',
			settings: { type: 'example-number', expected: 42 },
			questions: [
				{ id: 9, answer: 'A' },
				{ id: 123, temp: true, answer: 'B' },
			],
		},
		{ id: 124, temp: true, name: 'New', questions: [] },
	];
	const before = JSON.stringify( questions );
	const payload = prepareQuizPayload( quiz, questions );
	assert.equal( payload.content[ 0 ].id, 8 );
	assert.equal( payload.content[ 0 ].questions[ 0 ].id, 9 );
	assert.equal( payload.content[ 0 ].questions[ 1 ].id, undefined );
	assert.equal( payload.content[ 1 ].id, undefined );
	assert.equal( payload.content[ 0 ].settings.expected, 42 );
	assert.equal( JSON.stringify( questions ), before );
} );
test( 'invalid current questions block navigation while an empty editor remains navigable', () => {
	assert.equal(
		canLeaveQuestion( null, () => ( { isValid: false } ) ),
		true
	);
	assert.equal(
		canLeaveQuestion( { settings: { type: 'single-choice' } }, () => ( {
			isValid: false,
		} ) ),
		false
	);
} );
test( 'option drag reorders and renumbers without mutating saved answer objects', () => {
	const options = [
		Object.freeze( { id: 1, order_number: 1 } ),
		Object.freeze( { id: 2, order_number: 2 } ),
	];
	assert.deepEqual( moveOption( options, 0, 1 ), [
		{ id: 2, order_number: 1 },
		{ id: 1, order_number: 2 },
	] );
	assert.equal( options[ 0 ].order_number, 1 );
	assert.equal( moveOption( options, null, 1 ), options );
} );
import {
	mergeSavedQuiz,
	mergeSavedQuestions,
	failedQuestion,
} from '../../assets/src/features/quizzes/model.mjs';
import { removeQuestionFromQuiz } from '../../assets/src/features/quizzes/api.mjs';
test( 'duplicated questions never send option IDs that belong to the original question', () => {
	const payload = prepareQuizPayload(
		{ id: 4, modified: '2026-10-01 10:00:00' },
		[
			{
				id: 77,
				temp: true,
				modified: 'x',
				usage: {},
				questions: [
					{ id: 9, answer: 'A' },
					{ id: 10, answer: 'B' },
				],
			},
			{
				id: 8,
				modified: '2026-10-01 09:00:00',
				questions: [ { id: 11, answer: 'C' } ],
			},
		]
	);
	assert.deepEqual(
		payload.content[ 0 ].questions.map( ( o ) => o.id ),
		[ undefined, undefined ]
	);
	assert.equal( payload.content[ 0 ].base_modified, undefined );
	assert.equal( payload.content[ 1 ].questions[ 0 ].id, 11 );
	assert.equal( payload.content[ 1 ].base_modified, '2026-10-01 09:00:00' );
	assert.equal( payload.base_modified, '2026-10-01 10:00:00' );
	assert.equal( 'modified' in payload, false );
} );
test( 'edits made while a save is pending survive the server response', () => {
	const submitted = [
		{ id: 5, name: 'A', modified: 't1' },
		{ id: 900, temp: true, name: 'New' },
	];
	const current = [
		{ id: 5, name: 'A edited', modified: 't1' },
		{ id: 900, temp: true, name: 'New' },
		{ id: 901, temp: true, name: 'Added later' },
	];
	const saved = [
		{ id: 5, name: 'A', modified: 't2' },
		{ id: 6, name: 'New', modified: 't2' },
	];
	const merged = mergeSavedQuestions( saved, submitted, current, [ 5, 6 ] );
	assert.deepEqual(
		merged.map( ( q ) => q.id ),
		[ 5, 6, 901 ]
	);
	assert.equal( merged[ 0 ].name, 'A edited' );
	assert.equal( merged[ 0 ].modified, 't2' );
	assert.equal( merged[ 1 ].temp, undefined );
	assert.equal( merged[ 2 ].temp, true );
	const quiz = mergeSavedQuiz(
		{ id: 1, name: 'Saved', modified: 't2', content: [], saved_ids: [] },
		{ id: 1, name: 'Old', settings: { a: 1 } },
		{ id: 1, name: 'Old', settings: { a: 2 }, modified: 't1' }
	);
	assert.deepEqual( quiz, {
		id: 1,
		name: 'Saved',
		modified: 't2',
		settings: { a: 2 },
	} );
} );
test( 'a failed batch save identifies the question to show', () => {
	const submitted = [ { id: 1 }, { id: 2 } ];
	assert.equal(
		failedQuestion( { data: { errors: [ { index: 1 } ] } }, submitted ),
		submitted[ 1 ]
	);
	assert.equal( failedQuestion( { message: 'x' }, submitted ), null );
} );
test( 'removing a question targets the quiz relationship, not the question', async () => {
	let call;
	await removeQuestionFromQuiz( 4, 9, ( args ) => {
		call = args;
		return Promise.resolve( {} );
	} );
	assert.deepEqual( call, {
		path: '/ohmylms/v1/quiz/4/questions/9',
		method: 'DELETE',
	} );
} );
