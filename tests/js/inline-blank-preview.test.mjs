import test from 'node:test';
import assert from 'node:assert/strict';
import {
	parseInlineBlankPrompt,
	placeBlankToken,
} from '../../assets/src/features/question-editor/inlineBlanks.mjs';
import { canLeaveQuestion } from '../../assets/src/features/quiz-editor/model.mjs';

test( 'curly brackets create ordered drop positions and independent repeated tokens', () => {
	const parsed = parseInlineBlankPrompt(
		'Use {one}, then {two}, then {one}.'
	);
	assert.deepEqual(
		parsed.answers.map( ( answer ) => answer.text ),
		[ 'one', 'two', 'one' ]
	);
	assert.equal(
		new Set( parsed.answers.map( ( answer ) => answer.id ) ).size,
		3
	);
	assert.deepEqual(
		parsed.parts
			.filter( ( part ) => part.blank )
			.map( ( part ) => part.index ),
		[ 0, 1, 2 ]
	);
} );
test( 'moving an answer clears its old position and returns any displaced answer to the bank', () => {
	const before = [ '0', '1', null ];
	assert.deepEqual( placeBlankToken( before, '0', 1 ), [ null, '0', null ] );
	assert.deepEqual( before, [ '0', '1', null ] );
	assert.equal( placeBlankToken( before, '0', -1 ), before );
} );
test( 'empty braces remain literal question text', () => {
	const parsed = parseInlineBlankPrompt(
		'Keep {} and { } but replace {yes}.'
	);
	assert.equal( parsed.answers.length, 1 );
	assert.equal( parsed.parts[ 0 ].text, 'Keep {} and { } but replace ' );
} );
test( 'inline blanks can autosave without legacy separate answer options', () => {
	const question = {
		name: 'The capital is {Paris}.',
		settings: { type: 'fill-in-the-blank', score: { value: 1 } },
		questions: [],
	};
	assert.equal(
		canLeaveQuestion( question, () => ( { isValid: false } ) ),
		true
	);
	assert.equal(
		canLeaveQuestion(
			{
				...question,
				settings: { ...question.settings, score: { value: -1 } },
			},
			() => ( { isValid: true } )
		),
		false
	);
} );
