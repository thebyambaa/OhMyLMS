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
import { saveAssessmentSettings } from '../../assets/src/features/assessment/api.mjs';
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
import { loadSkillMatrix } from '../../assets/src/features/assessment/api.mjs';
test( 'skill level display and inline authoring code', async () => {
	assert.equal( levelShort( 'mastered' ), 'M' );
	assert.equal( levelShort( undefined ), '·' );
	assert.equal( levelClass( 'proficient' ), 'ohmylms-skill-proficient' );
	assert.equal( levelClass( '' ), '' );
	assert.equal( inlineShortcode( 'abc' ), '[ohmylms_question uuid="abc"]' );
	let call;
	await loadSkillMatrix( 9, ( args ) => {
		call = args;
		return Promise.resolve( {} );
	} );
	assert.equal( call.path, '/ohmylms/v1/reports/skills?course_id=9' );
} );
import {
	describeExpected,
	structuredRows,
} from '../../assets/src/features/quiz-reports/model.mjs';
import {
	addPart,
	removePart,
	updatePart,
	listToNumbers,
	numbersToList,
} from '../../assets/src/features/question-bank/model.mjs';
test( 'report helpers describe numerical and structured answers for teachers', () => {
	assert.equal(
		describeExpected( {
			settings: {
				type: 'numerical',
				answer: 0.75,
				answers: [ 3 / 4 ],
				tolerance: 0.01,
				unit: 'm',
			},
		} ),
		'0.75 or 0.75 ± 0.01 m'
	);
	assert.equal(
		describeExpected( {
			settings: { type: 'single-choice' },
			questions: [
				{ answer: 'A', is_correct: '1' },
				{ answer: 'B', is_correct: '0' },
			],
		} ),
		'A'
	);
	const rows = structuredRows( {
		given_answer: { a: '15' },
		settings: {
			parts: [
				{
					id: 'a',
					label: '(a)',
					kind: 'numerical',
					answer: 15,
					unit: 'cm²',
				},
				{ id: 'c', kind: 'written', rubric: 'method marks' },
			],
		},
	} );
	assert.deepEqual(
		rows.map( ( r ) => [ r.id, r.given, r.expected ] ),
		[
			[ 'a', '15', '15 cm²' ],
			[ 'c', '', 'method marks' ],
		]
	);
} );
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
import { setPartMark } from '../../assets/src/features/quiz-reports/model.mjs';
test( 'per-part marks update the question total and are clamped to the part maximum', () => {
	const attempt = {
		report: {
			questions: [
				{
					id: 5,
					achive_mark: 3,
					parts: {
						a: { max: 2, awarded: 2 },
						b: { max: 1, awarded: 1 },
						c: { max: 3, awarded: null },
					},
				},
				{ id: 6, achive_mark: 1 },
			],
		},
	};
	let next = setPartMark( attempt, 5, 'c', '2.5' );
	assert.deepEqual( next.report.questions[ 0 ].part_marks, { c: 2.5 } );
	assert.equal( next.report.questions[ 0 ].achive_mark, 5.5 );
	next = setPartMark( next, 5, 'a', '9' );
	assert.equal( next.report.questions[ 0 ].part_marks.a, 2 );
	next = setPartMark( next, 5, 'b', '-1' );
	assert.equal( next.report.questions[ 0 ].achive_mark, 4.5 );
	assert.equal( attempt.report.questions[ 0 ].part_marks, undefined );
	assert.equal( next.report.questions[ 1 ].achive_mark, 1 );
	const rows = structuredRows( {
		...next.report.questions[ 0 ],
		settings: {
			parts: [
				{ id: 'a', kind: 'numerical', answer: 1 },
				{ id: 'c', kind: 'written' },
			],
		},
	} );
	assert.deepEqual(
		rows.map( ( r ) => [ r.id, r.awarded, r.max ] ),
		[
			[ 'a', 2, 2 ],
			[ 'c', 2.5, 3 ],
		]
	);
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
