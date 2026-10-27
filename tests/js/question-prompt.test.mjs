import test from 'node:test';
import assert from 'node:assert/strict';
import {
	questionPrompt,
	questionPromptPatch,
	questionAnswerPatch,
} from '../../assets/src/features/question-editor/questionPrompt.mjs';
import { questionTypePatch } from '../../assets/src/features/question-editor/questionBlocks.mjs';
import {
	changeDraftType,
	validateDraft,
	emptyDraft,
} from '../../assets/src/features/question-bank/model.mjs';

test( 'one question field preserves legacy title and rich description', () => {
	assert.equal(
		questionPrompt( { name: 'A < B', description: '<p>Explain why.</p>' } ),
		'<p>A &lt; B</p><p>Explain why.</p>'
	);
} );
test( 'authoring generates a database code and hides the automatic name from the prompt', () => {
	const original = {
		id: 12,
		order_number: 3,
		settings: { type: 'single-choice' },
	};
	const patch = questionPromptPatch( original, '<p>What is 2 + 2?</p>' );
	assert.equal( patch.name, 'Question 3' );
	assert.equal( patch.settings.question_code, 'Q-12' );
	assert.equal( questionPrompt( patch ), '<p>What is 2 + 2?</p>' );
} );
test( 'fill blanks retain the prompt expected by the existing grader when changing type', () => {
	const question = {
		order_number: 3,
		name: 'Question 3',
		description: '<p>Two plus two is {four}.</p>',
		settings: { type: 'single-choice', question_code: 'Q-12' },
	};
	const patch = questionTypePatch( question, 'fill-in-the-blank' );
	assert.equal( patch.name, 'Two plus two is {four}.' );
	assert.equal( patch.settings.question_code, 'Q-12' );
} );
test( 'bank type changes preserve the code and require authored question content', () => {
	const draft = {
		...emptyDraft(),
		name: 'Question 1',
		description: '<p>Question?</p>',
		settings: { question_code: 'Q-12' },
	};
	const changed = changeDraftType( draft, 'short-text' );
	assert.equal( changed.settings.question_code, 'Q-12' );
	assert.equal( validateDraft( changed ).includes( 'name' ), false );
	assert.equal(
		validateDraft( { ...changed, description: '<p><br></p>' } ).includes(
			'name'
		),
		true
	);
} );

test( 'dropdown main statement preserves old content and synchronizes answer slots', () => {
	const question = { name: 'Untitled', description: '<p>Choose carefully.</p>', settings: {
		type: 'dropdown-blanks', text: 'Slope {1}.', slots: [{ id: '1', choices: ['positive'], answer: 'positive' }],
	} };
	assert.equal( questionPrompt( question ), '<p>Choose carefully.</p><p>Slope {1}.</p>' );
	const patch = questionPromptPatch( question, '<p>Slope {1} &lt; {2}.</p>' );
	assert.equal( patch.description, '' );
	assert.equal( patch.settings.text, 'Slope {1} < {2}.' );
	assert.deepEqual( patch.settings.slots[0], question.settings.slots[0] );
	assert.equal( patch.settings.slots[1].id, '2' );
	assert.equal( questionPrompt( patch ), '<p>Slope {1} &lt; {2}.</p>' );
	const deleted = questionPromptPatch( patch, '<p>Only {2}.</p>' );
	assert.deepEqual( deleted.settings.slots.map( slot => slot.id ), ['2'] );
} );

test( 'main inline blanks keep expression forms, numerical tolerances and math tokens', () => {
	const question = { settings: { type: 'multi-blank', text: '{a} {b}', blanks: {
		a: { kind: 'expression', answer: '2x', form: 'expanded' }, b: { kind: 'numerical', answer: 4, tolerance: 0.1 },
	} } };
	const source = '<p>[[ohmylms-math:latex:inline]]\\frac{{{x}}}{2}[[/ohmylms-math]] {a} {b}</p>';
	const patch = questionPromptPatch( question, source );
	assert.deepEqual( patch.settings.blanks, question.settings.blanks );
	assert.equal( patch.settings.text, source.slice(3,-4) );
	assert.equal( questionPrompt( patch ), source );
} );

test( 'passage main editor round trips line breaks and allows bank saving', () => {
	const question = { type: 'passage', settings: { type: 'passage', passage: 'Line 1\nLine 2', parts: [] } };
	const patch = questionPromptPatch( question, questionPrompt( question ) );
	assert.equal( patch.settings.passage, 'Line 1\nLine 2' );
	assert.equal( questionPrompt( patch ), '<p>Line 1<br>Line 2</p>' );
	assert.equal( validateDraft( { ...emptyDraft('passage'), ...patch } ).includes('name'), false );
} );

test( 'changing type or blank layout preserves the main statement', () => {
	const question = questionPromptPatch( { settings: { type: 'multi-blank', layout: 'inline', blanks: { a: { kind: 'text', accepted: ['4'] } } } }, '<p>2 + 2 = {a}</p>' );
	const changed = questionTypePatch( question, 'short-text' );
	assert.equal( changed.description, '<p>2 + 2 = {a}</p>' );
	const dropdown = questionTypePatch( question, 'dropdown-blanks' );
	assert.equal( questionPrompt( { ...question, ...dropdown } ), '<p>2 + 2 = {a}</p>' );
	assert.equal( dropdown.settings.slots[0].id, 'a' );
	const table = questionAnswerPatch( question, { settings: { ...question.settings, layout: 'table', rows: [['{a}']] } } );
	assert.equal( questionPrompt( { ...question, ...table } ), '<p>2 + 2 = {a}</p>' );
	const inline = questionAnswerPatch( { ...question, ...table }, { settings: { ...table.settings, layout: 'inline' } } );
	assert.equal( inline.settings.text, '2 + 2 = {a}' );
	assert.deepEqual( inline.settings.blanks.a, question.settings.blanks.a );
} );
