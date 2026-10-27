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
	addQuestionsToQuiz,
	pinQuestion,
	approveQuestion,
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
	await searchBank( { search: 'frac', page: 2 }, ( args ) => {
		call = args;
		return Promise.resolve( {} );
	} );
	assert.equal( call.path, '/ohmylms/v1/question-bank?search=frac&page=2' );
	await addQuestionsToQuiz( 4, [ 7, 8 ], true, ( args ) => {
		call = args;
		return Promise.resolve( {} );
	} );
	assert.deepEqual( call, {
		path: '/ohmylms/v1/quiz/4/questions',
		method: 'POST',
		data: { question_ids: [ 7, 8 ], pin: true },
	} );
	await pinQuestion( 4, 7, null, ( args ) => {
		call = args;
		return Promise.resolve( {} );
	} );
	assert.deepEqual( call.data, { version_id: 0 } );
	await approveQuestion( 7, undefined, ( args ) => {
		call = args;
		return Promise.resolve( {} );
	} );
	assert.deepEqual( call.data, {} );
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
	linkSkillPosts,
	searchLinkTargets,
	linkTargetTitles,
} from '../../assets/src/features/question-bank/api.mjs';
test( 'skills link lessons and courses through one route and look up titles', async () => {
	let call;
	const fetch = ( args ) => {
		call = args;
		return Promise.resolve( [] );
	};
	await linkSkillPosts( 3, 'courses', [ 10, 11 ], fetch );
	assert.deepEqual( call, {
		path: '/ohmylms/v1/skills/3/courses',
		method: 'PUT',
		data: { course_ids: [ 10, 11 ] },
	} );
	await linkSkillPosts( 3, 'lessons', [], fetch );
	assert.deepEqual( call.data, { lesson_ids: [] } );
	await searchLinkTargets( 'course', 'алгебр', fetch );
	assert.equal(
		call.path,
		'/ohmylms/v1/skills/link-targets?type=course&search=%D0%B0%D0%BB%D0%B3%D0%B5%D0%B1%D1%80'
	);
	await linkTargetTitles( 'lesson', [ 4, 5 ], fetch );
	assert.equal(
		call.path,
		'/ohmylms/v1/skills/link-targets?type=lesson&include=4%2C5'
	);
	call = null;
	assert.deepEqual( await linkTargetTitles( 'lesson', [], fetch ), [] );
	assert.equal( call, null );
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
	assert.deepEqual( call, {
		path: '/ohmylms/v1/question',
		method: 'POST',
		data: payload,
	} );
} );
