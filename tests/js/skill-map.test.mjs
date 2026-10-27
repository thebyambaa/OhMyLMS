import test from 'node:test';
import assert from 'node:assert/strict';
import {
	band,
	cellsOf,
	chainOf,
	chipClass,
	compareGroups,
	coverageOf,
	includedBy,
	levelOf,
	skillsById,
} from '../../assets/src/features/curriculum/skillMap.mjs';

const map = {
	skills: [
		{
			id: 1,
			domain: 'algebra',
			grade_from: 7,
			grade_to: 7,
			prerequisites: [],
		},
		{
			id: 2,
			domain: 'algebra',
			grade_from: 8,
			grade_to: 9,
			prerequisites: [ 1 ],
		},
		{
			id: 3,
			domain: 'algebra',
			grade_from: 9,
			grade_to: 9,
			prerequisites: [ 2 ],
			includes: [ { id: 1, weight: 0.5 } ],
		},
		{
			id: 4,
			domain: 'geometry',
			grade_from: 7,
			grade_to: 7,
			prerequisites: [],
		},
		{ id: 5, domain: '', grade_from: 0, grade_to: 0, prerequisites: [] },
	],
	student: { levels: { 1: 'mastered', 2: 'familiar', 9: 'bogus' } },
	syllabus: { covered: { 2: [ { id: 20, name: 'Objective' } ] } },
};

test( 'skills are grouped by domain and first grade; unplaced skills are left out', () => {
	const cells = cellsOf( map );
	assert.deepEqual( Object.keys( cells ), [ 'algebra', 'geometry' ] );
	assert.deepEqual(
		cells.algebra[ 9 ].map( ( skill ) => skill.id ),
		[ 3 ]
	);
	assert.deepEqual( cellsOf( null ), {} );
} );

test( 'a selection reaches every skill before and after it', () => {
	const skills = skillsById( map );
	const middle = chainOf( skills, 2 );
	assert.deepEqual( [ ...middle.before ], [ 1 ] );
	assert.deepEqual( [ ...middle.after ], [ 3 ] );
	const first = chainOf( skills, 1 );
	assert.deepEqual( [ ...first.after ].sort(), [ 2, 3 ] );
	assert.deepEqual( includedBy( skills, 1 ), [
		{ skill: skills.get( 3 ), weight: 0.5 },
	] );
} );

test( 'levels and coverage come from the overlays', () => {
	assert.equal( levelOf( map, 1 ), 'mastered' );
	assert.equal( levelOf( map, 4 ), 'not-started' );
	assert.equal( levelOf( map, 9 ), 'not-started' );
	assert.equal( coverageOf( map, 2 ).length, 1 );
	assert.deepEqual( coverageOf( map, 3 ), [] );
	assert.equal( coverageOf( { skills: [] }, 2 ), null );
} );

test( 'chips show level, coverage and their place in the selected chain', () => {
	const skills = skillsById( map );
	const selected = { id: 2, chain: chainOf( skills, 2 ) };
	assert.equal(
		chipClass( map, 2, selected ),
		'ohmylms-map-cell-skill ohmylms-map-familiar is-covered is-selected'
	);
	assert.match( chipClass( map, 1, selected ), /is-before/ );
	assert.match( chipClass( map, 3, selected ), /is-uncovered is-after/ );
	assert.match( chipClass( map, 4, selected ), /is-dimmed/ );
	assert.equal(
		chipClass( { skills: [] }, 4, null ),
		'ohmylms-map-cell-skill'
	);
} );

test( 'grade bands read as one grade or a range', () => {
	assert.equal( band( { grade_from: 7, grade_to: 7 } ), '7' );
	assert.equal( band( { grade_from: 8, grade_to: 9 } ), '8–9' );
	assert.equal( band( { grade_from: 0 } ), '' );
	const levels = [
		{ value: 1, label: 'A1' },
		{ value: 2, label: 'A2' },
	];
	assert.equal( band( { grade_from: 1, grade_to: 2 }, levels ), 'A1–A2' );
	assert.equal( band( { grade_from: 2, grade_to: 2 }, levels ), 'A2' );
} );

test( 'comparison rows split into both, only A and only B with objective names', () => {
	const groups = compareGroups( {
		a: { objectives: [ { id: 101, name: 'A one', code: '1' } ] },
		b: { objectives: [ { id: 201, name: 'B one', code: 'x' } ] },
		skills: { 1: { id: 1, name: 'Shared' }, 2: { id: 2, name: 'Only B' } },
		rows: [
			{ skill: 1, a: { 101: 'equivalent' }, b: { 201: 'broader' } },
			{ skill: 2, a: [], b: { 201: 'narrower' } },
		],
	} );
	assert.equal( groups.both.length, 1 );
	assert.equal( groups.onlyA.length, 0 );
	assert.equal( groups.onlyB[ 0 ].skill.name, 'Only B' );
	assert.deepEqual( groups.both[ 0 ].a, [
		{ id: 101, name: 'A one', code: '1', relation: 'equivalent' },
	] );
	assert.equal( groups.both[ 0 ].b[ 0 ].relation, 'broader' );
	assert.deepEqual( compareGroups( null ), {
		both: [],
		onlyA: [],
		onlyB: [],
	} );
} );
