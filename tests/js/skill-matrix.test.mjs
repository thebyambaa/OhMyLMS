import assert from 'node:assert/strict';
import test from 'node:test';
import {
	averageKey,
	cellView,
	legendLevels,
	levelCssClass,
	levelLetter,
	matrixPath,
	unitColumns,
} from '../../assets/src/features/assessment/skillMatrix.mjs';
import {
	loadSkillMatrix,
	loadStudentSkills,
} from '../../assets/src/features/assessment/api.mjs';

test( 'report path carries the course, class and unit filters', () => {
	assert.equal( matrixPath( {} ), 'reports/skills' );
	assert.equal(
		matrixPath( { courseId: 119 } ),
		'reports/skills?course_id=119'
	);
	assert.equal(
		matrixPath( { courseId: 119, chapterId: '7' } ),
		'reports/skills?course_id=119&chapter_id=7'
	);
	assert.equal(
		matrixPath( { classId: 4, courseId: 119, chapterId: 0 } ),
		'reports/skills?class_id=4&course_id=119'
	);
} );

test( 'api requests keep their old shape and add the unit', async () => {
	const seen = [];
	const fetch = ( args ) => {
		seen.push( args.path );
		return Promise.resolve( {} );
	};
	await loadSkillMatrix( 9, fetch );
	await loadSkillMatrix( 9, fetch, '12' );
	await loadStudentSkills( 3, 9, fetch );
	await loadStudentSkills( 3, 9, fetch, '12' );
	assert.deepEqual( seen, [
		'/ohmylms/v1/reports/skills?course_id=9',
		'/ohmylms/v1/reports/skills?course_id=9&chapter_id=12',
		'/ohmylms/v1/reports/skills/students/3?course_id=9',
		'/ohmylms/v1/reports/skills/students/3?course_id=9&chapter_id=12',
	] );
} );

test( 'every Khan level has a letter and a class, and unknown levels read as Not started', () => {
	assert.equal( levelLetter( 'mastered' ), 'M' );
	assert.equal( levelLetter( 'proficient' ), 'P' );
	assert.equal( levelLetter( 'familiar' ), 'F' );
	assert.equal( levelLetter( 'attempted' ), 'A' );
	assert.equal( levelLetter( 'not-started' ), '–' );
	assert.equal( levelLetter( 'developing' ), '–' );
	assert.equal( levelCssClass( 'familiar' ), 'ohmylms-mx-familiar' );
	assert.equal( levelCssClass( undefined ), 'ohmylms-mx-not-started' );
} );

test( 'the legend lists the five levels highest first with their names', () => {
	const legend = legendLevels( { mastered: 'Beherrscht' } );
	assert.deepEqual(
		legend.map( ( level ) => level.key ),
		[ 'mastered', 'proficient', 'familiar', 'attempted', 'not-started' ]
	);
	assert.equal( legend[ 0 ].label, 'Beherrscht' );
	assert.equal( legend[ 1 ].label, 'Proficient' );
} );

test( 'a cell shows text as well as colour and keeps the review marker', () => {
	const view = cellView(
		{
			display: 'familiar',
			review_due: true,
			evidence: 4,
			independent_correct: 2,
		},
		{ familiar: 'Vertraut' }
	);
	assert.equal( view.key, 'familiar' );
	assert.equal( view.letter, 'F' );
	assert.equal( view.label, 'Vertraut' );
	assert.equal( view.reviewDue, true );
	assert.equal( view.evidence, 4 );
	const empty = cellView( undefined );
	assert.equal( empty.key, 'not-started' );
	assert.equal( empty.label, 'Not started' );
	assert.equal( empty.reviewDue, false );
} );

test( 'skills are grouped under their unit headers in the server order', () => {
	const matrix = {
		skills: [
			{ id: 1, name: 'Add' },
			{ id: 2, name: 'Halve' },
			{ id: 3, name: 'Stray' },
		],
		groups: [
			{ id: 10, name: 'Unit A', skills: [ 2, 1 ] },
			{ id: 0, name: 'Other skills', skills: [ 3, 99 ] },
			{ id: 20, name: 'Empty', skills: [] },
		],
	};
	const columns = unitColumns( matrix );
	assert.deepEqual(
		columns.map( ( group ) => group.name ),
		[ 'Unit A', 'Other skills' ]
	);
	assert.deepEqual(
		columns[ 0 ].skills.map( ( skill ) => skill.id ),
		[ 2, 1 ]
	);
	assert.deepEqual(
		columns[ 1 ].skills.map( ( skill ) => skill.id ),
		[ 3 ]
	);
	// An older server sends no groups: one headerless group of everything.
	const flat = unitColumns( { skills: matrix.skills } );
	assert.equal( flat.length, 1 );
	assert.equal( flat[ 0 ].name, '' );
	assert.deepEqual( unitColumns( { skills: [] } ), [] );
} );

test( 'a class average is only a level when the server could show it', () => {
	const matrix = {
		average: { 1: { key: 'proficient' }, 2: null },
	};
	assert.equal( averageKey( matrix, 1 ), 'proficient' );
	assert.equal( averageKey( matrix, 2 ), null );
	assert.equal( averageKey( matrix, 3 ), null );
	assert.equal( averageKey( undefined, 1 ), null );
} );
