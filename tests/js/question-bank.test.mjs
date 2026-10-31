import test from 'node:test';
import assert from 'node:assert/strict';
import {
	cleanFilters,
	versionLabel,
	skillTree,
	flattenTree,
	createsCycle,
	normalizeSkillMap,
	setPrimarySkill,
	toggleSupportingSkill,
	selectableResults,
	appendLinkedQuestions,
} from '../../assets/src/features/question-bank/model.mjs';
import {
	searchBank,
	loadBankQuestion,
	loadVersion,
	pinQuestion,
	approveQuestion,
	duplicateQuestion,
	saveQuestionAttributes,
} from '../../assets/src/features/question-bank/api.mjs';
test( 'filters drop empty values and API paths carry them', async () => {
	assert.deepEqual(
		cleanFilters( {
			search: 'x',
			type: '',
			skill: 0,
			bank: '0',
			secure: false,
		} ),
		{ search: 'x', skill: 0, bank: '0' }
	);
	let call;
	const search = await searchBank(
		{ search: 'frac', page: 2, status: '', secure: 0, exclude_quiz: 4 },
		( args ) => {
			call = args;
			return Promise.resolve( [
				{ id: 3, bank: { bank_id: 2 }, permissions: { can_edit: true } },
			] );
		}
	);
	assert.equal(
		call.path,
		'/ohmylms/v1/questions?search=frac&page=2&status=active&secure=false&exclude_quiz=4'
	);
	assert.equal( search.items[ 0 ].bank_id, 2 );
	assert.equal( search.items[ 0 ].can_edit, true );
	assert.equal( search.items[ 0 ].can_approve, false );
	const calls = [];
	await pinQuestion( 4, 7, null, ( args ) => {
		calls.push( args );
		return Promise.resolve( { revision: 'abc' } );
	} );
	assert.deepEqual( calls, [
		{ path: '/ohmylms/v1/quizzes/4/questions' },
		{
			path: '/ohmylms/v1/quizzes/4/questions/7/pin',
			method: 'PUT',
			data: { version_id: null, revision: 'abc' },
		},
	] );
	await approveQuestion( 7, undefined, ( args ) => {
		call = args;
		return Promise.resolve( {} );
	} );
	assert.deepEqual( call, {
		path: '/ohmylms/v1/questions/7/approve',
		method: 'POST',
		data: {},
	} );
	await approveQuestion( 7, '31', ( args ) => {
		call = args;
		return Promise.resolve( {} );
	} );
	assert.deepEqual( call.data, { version_id: 31 } );
	await duplicateQuestion( 7, ( args ) => {
		call = args;
		return Promise.resolve( {} );
	} );
	assert.deepEqual( call, {
		path: '/ohmylms/v1/questions/7/duplicate',
		method: 'POST',
	} );
	await loadVersion( 7, 31, ( args ) => {
		call = args;
		return Promise.resolve( {} );
	} );
	assert.equal( call.path, '/ohmylms/v1/questions/7/versions/31' );
} );
test( 'question reads add the flat fields the native editors use', async () => {
	const view = await loadBankQuestion( 9, () =>
		Promise.resolve( {
			id: 9,
			bank: { bank_id: 0, difficulty: 'easy', secure: false },
			skills: { p1: { primary: 4, supporting: [] } },
			options: [ { id: 1, answer: 'A', image_id: 12 } ],
			permissions: { can_edit: true, can_approve: true },
		} )
	);
	assert.equal( view.difficulty, 'easy' );
	assert.deepEqual( view.skill_map, { p1: { primary: 4, supporting: [] } } );
	assert.equal( view.options[ 0 ].thumbnail_id, 12 );
	assert.equal( view.can_approve, true );
} );
test( 'question writes send typed options and drop fields the route refuses', async () => {
	let call;
	await saveQuestionAttributes(
		9,
		{
			status: 'draft',
			name: 'Halves',
			settings: { type: 'single-choice' },
			questions: [
				{ id: 5, answer: '2', is_correct: 1, thumbnail_id: 8 },
				{
					id: 1730000000000,
					temp: true,
					clientKey: 'x',
					answer: '4',
					is_correct: '0',
					order_number: 2,
				},
			],
			base_modified: '2026-10-30T13:05:12Z',
		},
		( args ) => {
			call = args;
			return Promise.resolve( {} );
		}
	);
	assert.deepEqual( call, {
		path: '/ohmylms/v1/questions/9',
		method: 'PATCH',
		data: {
			name: 'Halves',
			settings: { type: 'single-choice' },
			base_modified: '2026-10-30T13:05:12Z',
			options: [
				{
					answer: '2',
					is_correct: true,
					order_number: 1,
					id: 5,
					image_id: 8,
				},
				{ answer: '4', is_correct: false, order_number: 2 },
			],
		},
	} );
} );
test( 'version labels distinguish draft, approved and newer drafts', () => {
	assert.equal(
		versionLabel( { version: 2, approved_version_id: 0 } ),
		'v2 · draft'
	);
	assert.equal(
		versionLabel( {
			version: 2,
			approved_version_id: 5,
			approved_is_current: true,
		} ),
		'v2 · approved'
	);
	assert.equal(
		versionLabel( {
			version: 3,
			approved_version_id: 5,
			approved_is_current: false,
		} ),
		'v3 · newer than approved'
	);
} );
test( 'skill tree nests children, flattens with depth and detects prerequisite cycles', () => {
	const skills = [
		{ id: 1, name: 'Number', parent: 0, prerequisites: [] },
		{ id: 2, name: 'Fractions', parent: 1, prerequisites: [] },
		{ id: 3, name: 'Equations', parent: 0, prerequisites: [ 2 ] },
	];
	const flat = flattenTree( skillTree( skills ) );
	assert.deepEqual(
		flat.map( ( s ) => [ s.id, s.depth ] ),
		[
			[ 3, 0 ],
			[ 1, 0 ],
			[ 2, 1 ],
		]
	);
	assert.equal( createsCycle( skills, 2, 3 ), true );
	assert.equal( createsCycle( skills, 3, 1 ), false );
	assert.equal( createsCycle( skills, 2, 2 ), true );
} );
test( 'skill maps keep one primary per part and never duplicate it as supporting', () => {
	let map = setPrimarySkill( {}, 'p1', 5 );
	map = toggleSupportingSkill( map, 'p1', 6 );
	map = toggleSupportingSkill( map, 'p1', 5 );
	assert.deepEqual( map, { p1: { primary: 5, supporting: [ 6 ] } } );
	assert.deepEqual( toggleSupportingSkill( map, 'p1', 6 ), {
		p1: { primary: 5, supporting: [] },
	} );
	assert.deepEqual(
		normalizeSkillMap( {
			p1: { primary: 0, supporting: [] },
			p2: { primary: '4', supporting: [ '4', '7', '7' ] },
		} ),
		{ p2: { primary: 4, supporting: [ 7 ] } }
	);
} );
test( 'picker marks existing questions and appends without overwriting local edits', () => {
	assert.deepEqual(
		selectableResults( [ { id: 1 }, { id: 2 } ], [ 2 ] ).map(
			( i ) => i.alreadyInQuiz
		),
		[ false, true ]
	);
	const local = [ { id: 1, name: 'Edited locally' } ];
	const merged = appendLinkedQuestions(
		local,
		[
			{ id: 1, name: 'Server' },
			{ id: 9, name: 'From bank' },
		],
		[ 9, 1 ]
	);
	assert.deepEqual(
		merged.map( ( q ) => q.name ),
		[ 'Edited locally', 'From bank' ]
	);
} );
import {
	emptyDraft,
	changeDraftType,
	setOptionCorrect,
	validateDraft,
	draftToPayload,
} from '../../assets/src/features/question-bank/model.mjs';
import { createBankQuestion } from '../../assets/src/features/question-bank/api.mjs';
test( 'new bank questions validate per type and post without a quiz', async () => {
	let draft = emptyDraft();
	assert.deepEqual( validateDraft( draft ), [ 'name', 'options-empty' ] );
	draft = {
		...draft,
		name: 'Halves',
		description: '\(\frac{1}{2}\) of 8?',
		marks: '2',
		options: [
			{ answer: '4', correct: false },
			{ answer: '2', correct: true },
		],
	};
	draft = setOptionCorrect( draft, 0, true );
	assert.deepEqual(
		draft.options.map( ( o ) => o.correct ),
		[ true, false ]
	);
	assert.deepEqual( validateDraft( draft ), [] );
	const payload = draftToPayload( draft );
	assert.equal( payload.quiz_id, undefined );
	assert.deepEqual( payload.settings, {
		type: 'single-choice',
		required: false,
		score: { enabled: true, value: 2 },
	} );
	assert.deepEqual( payload.questions, [
		{ answer: '4', is_correct: 1, order_number: 1 },
		{ answer: '2', is_correct: 0, order_number: 2 },
	] );
	const multiple = changeDraftType( draft, 'multiple-choice' );
	assert.equal( multiple.name, 'Halves' );
	assert.equal( multiple.options.length, 2 );
	assert.deepEqual(
		setOptionCorrect( multiple, 1, true ).options.map( ( o ) => o.correct ),
		[ true, true ]
	);
	assert.deepEqual( validateDraft( setOptionCorrect( multiple, 0, false ) ), [
		'options-correct',
	] );
	const trueFalse = changeDraftType( draft, 'true-false' );
	assert.deepEqual(
		draftToPayload( trueFalse ).questions.map( ( q ) => [
			q.answer,
			q.is_correct,
		] ),
		[
			[ 'True', 1 ],
			[ 'False', 0 ],
		]
	);
	const numerical = changeDraftType( draft, 'numerical' );
	assert.deepEqual( validateDraft( numerical ), [ 'numerical-answer' ] );
	assert.deepEqual(
		validateDraft( {
			...numerical,
			settings: { answer: 0.5, tolerance: 0.01 },
		} ),
		[]
	);
	assert.deepEqual(
		draftToPayload( { ...numerical, settings: { answer: 0.5 } } ).questions,
		[]
	);
	const structured = changeDraftType( draft, 'structured' );
	assert.equal( structured.settings.parts.length, 1 );
	const parts = [
		{ id: 'a', kind: 'numerical', marks: 2, answer: 3 },
		{ id: 'b', kind: 'written', marks: 3 },
	];
	assert.equal(
		draftToPayload( { ...structured, settings: { parts } } ).settings.score
			.value,
		5
	);
	assert.deepEqual(
		validateDraft( { ...structured, settings: { parts: [] } } ),
		[ 'parts' ]
	);
	assert.deepEqual(
		validateDraft( changeDraftType( draft, 'long-text' ) ),
		[]
	);
	let call;
	await createBankQuestion( payload, ( args ) => {
		call = args;
		return Promise.resolve( { id: 9 } );
	} );
	const { questions, ...fields } = payload;
	assert.deepEqual( call, {
		path: '/ohmylms/v1/questions',
		method: 'POST',
		data: {
			...fields,
			options: questions.map( ( option ) => ( {
				...option,
				is_correct: Boolean( option.is_correct ),
			} ) ),
		},
	} );
} );
