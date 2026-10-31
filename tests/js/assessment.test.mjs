import test from 'node:test';
import assert from 'node:assert/strict';
import {
	utcToLocalInput,
	localInputToUtc,
	assignQuestion,
	sectionOf,
	setSlotMarks,
	accommodationRows,
	accommodationMap,
	quizIdFromHash,
} from '../../assets/src/features/assessment/model.mjs';
import {
	loadPools,
	loadQuizQuestions,
	saveAssessmentSettings,
	savePools,
} from '../../assets/src/features/assessment/api.mjs';
test( 'quiz questions and pools come from the typed quiz questions route', async () => {
	const body = {
		revision: 'r1',
		questions: [
			{ question_id: 5, name: 'Halves', question_marks: 2, marks: 3 },
		],
		pools: [
			{
				id: 'pool-1',
				title: 'Warm-up',
				skill: { id: 7, name: 'Fractions' },
				count: 3,
				marks: 2,
				difficulty: '',
				bank_id: 0,
				include_secure: false,
			},
		],
	};
	const paths = [];
	const fetch = ( args ) => {
		paths.push( args );
		return Promise.resolve( body );
	};
	assert.deepEqual( await loadQuizQuestions( 4, fetch ), [
		{ id: 5, name: 'Halves', marks: 2 },
	] );
	const loaded = await loadPools( 4, fetch );
	assert.deepEqual( loaded, {
		pools: [
			{
				title: 'Warm-up',
				term_id: 7,
				count: 3,
				marks: 2,
				difficulty: '',
				bank_id: 0,
				include_secure: false,
			},
		],
		revision: 'r1',
	} );
	await savePools( 4, loaded.pools, loaded.revision, fetch );
	assert.deepEqual( paths.at( -1 ), {
		path: '/ohmylms/v1/quizzes/4/questions/pools',
		method: 'PUT',
		data: {
			pools: [
				{
					title: 'Warm-up',
					skill_id: 7,
					count: 3,
					marks: 2,
					difficulty: '',
					include_secure: false,
				},
			],
			revision: 'r1',
		},
	} );
	assert.equal( paths[ 0 ].path, '/ohmylms/v1/quizzes/4/questions' );
} );
test( 'UTC server times round-trip through local datetime inputs', () => {
	const local = utcToLocalInput( '2026-10-02 08:30:00' );
	assert.match( local, /^\d{4}-\d\d-\d\dT\d\d:\d\d$/ );
	assert.equal( localInputToUtc( local ), '2026-10-02T08:30:00Z' );
	assert.equal( utcToLocalInput( '' ), '' );
	assert.equal( localInputToUtc( '' ), '' );
} );
test( 'a question belongs to one section and its slot marks move with it', () => {
	let sections = [
		{ title: 'A', questions: [], marks: {} },
		{ title: 'B', questions: [], marks: {} },
	];
	sections = assignQuestion( sections, 5, 0 );
	sections = setSlotMarks( sections, 5, '3.5' );
	assert.deepEqual( sections[ 0 ], {
		title: 'A',
		questions: [ 5 ],
		marks: { 5: 3.5 },
	} );
	sections = assignQuestion( sections, 5, 1 );
	assert.equal( sectionOf( sections, 5 ), 1 );
	assert.deepEqual( sections[ 0 ].marks, {} );
	sections = assignQuestion( sections, 5, -1 );
	assert.equal( sectionOf( sections, 5 ), -1 );
	assert.equal( setSlotMarks( sections, 5, '2' ), sections );
} );
test( 'accommodations convert minutes to seconds and drop incomplete rows', () => {
	assert.deepEqual(
		accommodationMap( [
			{ userId: '7', minutes: '15' },
			{ userId: '', minutes: '5' },
			{ userId: '8', minutes: '0' },
		] ),
		{ 7: 900 }
	);
	assert.deepEqual( accommodationRows( { 7: 900 } ), [
		{ userId: '7', minutes: '15' },
	] );
	assert.equal( quizIdFromHash( '#/quiz-edit/791' ), 791 );
	assert.equal( quizIdFromHash( '#/quizzes' ), 0 );
} );
test( 'settings save targets the assessment-settings route', async () => {
	let call;
	await saveAssessmentSettings( 4, { kind: 'exam' }, ( args ) => {
		call = args;
		return Promise.resolve( {} );
	} );
	assert.deepEqual( call, {
		path: '/ohmylms/v1/quiz/4/assessment-settings',
		method: 'PUT',
		data: { kind: 'exam' },
	} );
} );
import {
	levelShort,
	levelClass,
	inlineShortcode,
} from '../../assets/src/features/assessment/model.mjs';
test( 'skill level display and inline authoring code', () => {
	assert.equal( levelShort( 'mastered' ), 'M' );
	assert.equal( levelShort( undefined ), '·' );
	assert.equal( levelClass( 'proficient' ), 'ohmylms-skill-proficient' );
	assert.equal( levelClass( '' ), '' );
	assert.equal( inlineShortcode( 'abc' ), '[ohmylms_question uuid="abc"]' );
} );
import {
	addPart,
	removePart,
	updatePart,
	listToNumbers,
	numbersToList,
} from '../../assets/src/features/question-bank/model.mjs';
test( 'structured part editing keeps stable unique IDs; numeric lists parse safely', () => {
	let parts = addPart( [] );
	parts = addPart( parts );
	assert.deepEqual(
		parts.map( ( p ) => p.id ),
		[ 'a', 'b' ]
	);
	parts = removePart( parts, 0 );
	parts = addPart( parts );
	assert.equal( new Set( parts.map( ( p ) => p.id ) ).size, 2 );
	assert.equal( updatePart( parts, 0, { marks: 3 } )[ 0 ].marks, 3 );
	assert.deepEqual( listToNumbers( '1, x, 2.5,' ), [ 1, 2.5 ] );
	assert.equal( listToNumbers( 'x' ), undefined );
	assert.equal( numbersToList( [ 1, 2 ] ), '1, 2' );
} );
test( 'moving questions between sections keeps each section page break', () => {
	const sections = [
		{ title: 'A', questions: [ 1 ], marks: {}, new_page: false },
		{ title: 'B', questions: [], marks: {}, new_page: true },
	];
	const next = assignQuestion( sections, 1, 1 );
	assert.deepEqual(
		next.map( ( s ) => [ s.questions, s.new_page ] ),
		[
			[ [], false ],
			[ [ 1 ], true ],
		]
	);
} );
