import test from 'node:test';
import assert from 'node:assert/strict';
import {
	moveOutlineRow,
	orderedOutlineItems,
	removeOutlineLesson,
	outlineLessonReadiness,
	outlineStatus,
	missingOutlineSkills,
	removeOutlineSkills,
	practicePlacement,
	addOutlinePractice,
	outlineUnitSummary,
	dropOutlineUnit,
	dropOutlineLesson,
	dropOutlineActivity,
	lessonsFromSkills,
} from '../../assets/src/features/courses/outline.mjs';

test( 'lesson checklist isolates teaching and assessment coverage by lesson and unit', () => {
	const program = {
		groups: [
			{ id: 'one', chapter_id: 1, title: 'One' },
			{ id: 'two', chapter_id: 2, title: 'Two' },
		],
		outcomes: [
			{ term_id: 10, chapter_id: 1, group_id: 'one' },
			{ term_id: 20, chapter_id: 2, group_id: 'two' },
		],
		items: [
			{
				type: 'lesson',
				chapter_id: 1,
				group_id: 'one',
				skill_ids: [ 10, 20 ],
				status: 'publish',
			},
			{
				type: 'quiz',
				chapter_id: 1,
				group_id: 'one',
				skill_ids: [ 10 ],
				status: 'publish',
			},
			{
				type: 'lesson',
				chapter_id: 2,
				group_id: 'one',
				skill_ids: [ 10 ],
				status: 'draft',
			},
			{
				type: 'quiz',
				chapter_id: 2,
				skill_ids: [ 20 ],
				status: 'publish',
			},
		],
	};
	const before = JSON.stringify( program );
	const [ one, two ] = outlineLessonReadiness( program );
	assert.equal( one.complete, true );
	assert.equal( one.items, 2 );
	assert.deepEqual( two.missingTeaching, [ 20 ] );
	assert.deepEqual( two.missingAssessment, [ 20 ] );
	assert.equal( two.items, 0 );
	assert.equal( JSON.stringify( program ), before );
} );

test( 'practice covers its own skill, while drafts and absent skill links remain visible', () => {
	const program = {
		groups: [
			{ id: 'one', chapter_id: 1 },
			{ id: 'empty', chapter_id: 1 },
		],
		outcomes: [ { term_id: 10, chapter_id: 1, group_id: 'one' } ],
		items: [
			{
				type: 'practice',
				content_id: 10,
				chapter_id: 1,
				group_id: 'one',
			},
			{
				type: 'lesson',
				skill_ids: [ 10 ],
				chapter_id: 1,
				group_id: 'one',
				status: 'draft',
			},
		],
	};
	const [ one, empty ] = outlineLessonReadiness( program );
	assert.deepEqual( one.missingAssessment, [] );
	assert.deepEqual( one.missingTeaching, [] );
	assert.equal( one.drafts, 1 );
	assert.equal( one.complete, false );
	assert.equal( empty.complete, false );
	assert.deepEqual( empty.skills, [] );
} );

test( 'unscoped resources and quizzes do not count as teaching', () => {
	const [ row ] = outlineLessonReadiness( {
		groups: [ { id: 'one', chapter_id: 1 } ],
		outcomes: [ { term_id: 10, chapter_id: 1, group_id: 'one' } ],
		items: [
			{
				type: 'lesson',
				chapter_id: 1,
				group_id: 'one',
				status: 'publish',
			},
			{
				type: 'quiz',
				skill_ids: [ 10 ],
				chapter_id: 1,
				group_id: 'one',
				status: 'publish',
			},
		],
	} );
	assert.deepEqual( row.missingTeaching, [ 10 ] );
	assert.deepEqual( row.missingAssessment, [] );
	assert.equal( row.complete, false );
} );

test( 'moving units or lessons preserves IDs, data and the original array', () => {
	const rows = [
		{ id: 'first', title: 'First' },
		{ id: 'second', custom: true },
	];
	assert.deepEqual( moveOutlineRow( rows, 'second', -1 ), [
		rows[ 1 ],
		rows[ 0 ],
	] );
	assert.equal( rows[ 0 ].id, 'first' );
	assert.deepEqual( moveOutlineRow( rows, 'first', -1 ), rows );
} );

test( 'display serialization follows units and lessons, with terminal unit tests and challenge', () => {
	const items = [
		{ id: 'challenge', chapter_id: 0, style: 'course-challenge' },
		{ id: 'test', chapter_id: 2, style: 'unit-test' },
		{ id: 'loose', chapter_id: 2 },
		{ id: 'b', chapter_id: 2, group_id: 'lesson-b' },
		{ id: 'a', chapter_id: 2, group_id: 'lesson-a' },
		{ id: 'first-unit', chapter_id: 'new-unit' },
	];
	const program = {
		items,
		groups: [
			{ id: 'lesson-a', chapter_id: 2 },
			{ id: 'lesson-b', chapter_id: 2 },
		],
	};
	assert.deepEqual(
		orderedOutlineItems( program, [ { id: 'new-unit' }, { id: 2 } ] ).map(
			( row ) => row.id
		),
		[ 'first-unit', 'a', 'b', 'loose', 'test', 'challenge' ]
	);
	assert.equal( items[ 0 ].id, 'challenge' );
} );

test( 'unknown legacy placements survive serialization for explicit repair rather than disappearing', () => {
	const orphan = { id: 'orphan', chapter_id: 999, custom: 'keep' };
	assert.deepEqual(
		orderedOutlineItems( { items: [ orphan ], groups: [] }, [] ),
		[ orphan ]
	);
} );

test( 'removing a lesson keeps its activities, skill outcomes and extension fields', () => {
	const source = {
		groups: [ { id: 'old' }, { id: 'keep' } ],
		items: [
			{ id: 'activity', group_id: 'old', chapter_id: 4, custom: 'kept' },
		],
		outcomes: [ { term_id: 7, group_id: 'old', required: true } ],
	};
	const next = removeOutlineLesson( source, 'old' );
	assert.deepEqual( next.groups, [ { id: 'keep' } ] );
	assert.deepEqual( next.items, [
		{ id: 'activity', chapter_id: 4, custom: 'kept' },
	] );
	assert.deepEqual( next.outcomes, [ { term_id: 7, required: true } ] );
	assert.equal( source.items[ 0 ].group_id, 'old' );
} );

test( 'outline status prefers saving, then unsaved edits, then publication state', () => {
	assert.equal( outlineStatus( { saving: true, dirty: true } ), 'saving' );
	assert.equal(
		outlineStatus( { dirty: true, unpublished: false } ),
		'unsaved'
	);
	assert.equal( outlineStatus( { unpublished: true } ), 'unpublished' );
	assert.equal( outlineStatus( { unpublished: false } ), 'published' );
} );

test( 'missing skills are removed with their practice and coverage links', () => {
	const program = {
		outcomes: [
			{ term_id: 1, chapter_id: 5 },
			{ term_id: 2, chapter_id: 5 },
		],
		items: [
			{ id: 'p', type: 'practice', content_id: 2 },
			{ id: 'r', type: 'lesson', content_id: 9, skill_ids: [ 1, 2 ] },
		],
	};
	assert.deepEqual( missingOutlineSkills( program, { 1: 'Known' } ), [ 2 ] );
	const next = removeOutlineSkills( program, [ 2 ] );
	assert.deepEqual(
		next.outcomes.map( ( row ) => row.term_id ),
		[ 1 ]
	);
	assert.deepEqual(
		next.items.map( ( row ) => row.id ),
		[ 'r' ]
	);
	assert.deepEqual( next.items[ 0 ].skill_ids, [ 1 ] );
	assert.equal( program.outcomes.length, 2 );
} );

test( 'adding practice links the skill to the lesson in the same step', () => {
	const program = {
		groups: [
			{ id: 'a', chapter_id: 5 },
			{ id: 'b', chapter_id: 5 },
		],
		outcomes: [
			{ term_id: 1, chapter_id: 5, group_id: 'b' },
			{ term_id: 2, chapter_id: 5 },
			{ term_id: 3, chapter_id: 5, group_id: 'a' },
		],
		items: [],
	};
	const place = { unitId: 5, groupId: 'a' };
	assert.equal( practicePlacement( program, 1, place ), 'new' );
	assert.equal( practicePlacement( program, 2, place ), 'new' );
	assert.equal( practicePlacement( program, 3, place ), 'linked' );
	assert.equal( practicePlacement( program, 4, place ), 'new' );
	assert.equal(
		addOutlinePractice( program, { termId: 1, ...place, id: 'x' } ).outcomes
			.length,
		3
	);
	const fresh = addOutlinePractice( program, {
		termId: 4,
		...place,
		id: 'x',
	} );
	assert.deepEqual( fresh.outcomes.at( -1 ), {
		term_id: 4,
		required: false,
		target: 'proficient',
		chapter_id: 5,
		group_id: 'a',
	} );
	assert.deepEqual( fresh.items[ 0 ], {
		id: 'x',
		content_id: 4,
		type: 'practice',
		style: 'practice',
		required: false,
		chapter_id: 5,
		group_id: 'a',
	} );
	const moved = addOutlinePractice( program, {
		termId: 2,
		...place,
		id: 'y',
	} );
	assert.equal( moved.outcomes[ 1 ].group_id, 'a' );
	assert.equal( practicePlacement( moved, 2, place ), 'placed' );
} );

test( 'unit summary counts lessons, skills and activities in that unit', () => {
	assert.deepEqual(
		outlineUnitSummary(
			{
				groups: [ { chapter_id: 5 }, { chapter_id: 6 } ],
				outcomes: [
					{ term_id: 1, chapter_id: 5 },
					{ term_id: 2, chapter_id: 5 },
				],
				items: [ { chapter_id: 5 }, { chapter_id: 0 } ],
			},
			5
		),
		{ lessons: 1, skills: 2, activities: 1 }
	);
} );

test( 'dragging reorders units and moves lessons with their activities and skills', () => {
	assert.deepEqual(
		dropOutlineUnit( [ { id: 1 }, { id: 2 }, { id: 3 } ], 3, 1 ).map(
			( row ) => row.id
		),
		[ 3, 1, 2 ]
	);
	assert.deepEqual(
		dropOutlineUnit( [ { id: 1 }, { id: 2 } ], 1, '' ).map(
			( row ) => row.id
		),
		[ 2, 1 ]
	);
	const program = {
		groups: [
			{ id: 'a', chapter_id: 1 },
			{ id: 'b', chapter_id: 2 },
			{ id: 'c', chapter_id: 2 },
		],
		items: [ { id: 'x', chapter_id: 1, group_id: 'a' } ],
		outcomes: [ { term_id: 5, chapter_id: 1, group_id: 'a' } ],
	};
	const moved = dropOutlineLesson( program, 'a', {
		unitId: 2,
		beforeId: 'c',
	} );
	assert.deepEqual(
		moved.groups.map( ( row ) => row.id ),
		[ 'b', 'a', 'c' ]
	);
	assert.equal( moved.groups[ 1 ].chapter_id, 2 );
	assert.equal( moved.items[ 0 ].chapter_id, 2 );
	assert.equal( moved.outcomes[ 0 ].chapter_id, 2 );
	const appended = dropOutlineLesson( program, 'a', {
		unitId: 2,
		beforeId: '',
	} );
	assert.deepEqual(
		appended.groups.map( ( row ) => row.id ),
		[ 'b', 'c', 'a' ]
	);
} );

test( 'dragging an activity places it in the target lesson or unit and keeps checkpoints fixed', () => {
	const program = {
		groups: [ { id: 'a', chapter_id: 1 } ],
		outcomes: [],
		items: [
			{ id: 'x', chapter_id: 1, group_id: 'a', skill_ids: [ 4 ] },
			{ id: 'y', chapter_id: 1, group_id: 'a' },
			{ id: 'z', chapter_id: 1 },
			{ id: 't', chapter_id: 1, style: 'unit-test' },
		],
	};
	const before = dropOutlineActivity( program, 'y', {
		unitId: 1,
		groupId: 'a',
		beforeId: 'x',
	} );
	assert.deepEqual(
		before.items.map( ( row ) => row.id ),
		[ 'y', 'x', 'z', 't' ]
	);
	const out = dropOutlineActivity( program, 'x', {
		unitId: 1,
		groupId: '',
		beforeId: '',
	} );
	assert.deepEqual(
		out.items.map( ( row ) => row.id ),
		[ 'y', 'z', 'x', 't' ]
	);
	assert.equal( 'group_id' in out.items[ 2 ], false );
	assert.deepEqual( out.items[ 2 ].skill_ids, [ 4 ] );
	assert.equal(
		dropOutlineActivity( program, 't', {
			unitId: 1,
			groupId: 'a',
			beforeId: '',
		} ),
		program
	);
} );

test( 'loose unit skills become lessons with their practice', () => {
	const program = {
		groups: [],
		outcomes: [
			{ term_id: 1, chapter_id: 5 },
			{ term_id: 2, chapter_id: 5 },
			{ term_id: 3, chapter_id: 5 },
			{ term_id: 4, chapter_id: 6 },
		],
		items: [ { id: 'p', type: 'practice', content_id: 2, chapter_id: 5 } ],
	};
	const names = { 1: 'One', 2: 'Two', 4: 'Four' };
	let next = 0;
	const newId = () => `id-${ ++next }`;
	const each = lessonsFromSkills( program, {
		unitId: 5,
		names,
		title: '',
		newId,
	} );
	assert.deepEqual(
		each.groups.map( ( row ) => row.title ),
		[ 'One', 'Two' ]
	);
	assert.equal( each.outcomes[ 0 ].group_id, each.groups[ 0 ].id );
	assert.equal( each.outcomes[ 2 ].group_id, undefined );
	assert.equal( each.outcomes[ 3 ].group_id, undefined );
	assert.equal( each.items[ 0 ].group_id, each.groups[ 1 ].id );
	assert.equal( each.items.length, 2 );
	assert.equal( each.items[ 1 ].content_id, 1 );
	next = 0;
	const one = lessonsFromSkills( program, {
		unitId: 5,
		names,
		title: 'Basics',
		newId,
	} );
	assert.deepEqual( one.groups, [
		{ id: 'id-1', chapter_id: 5, title: 'Basics' },
	] );
	assert.equal( one.outcomes[ 1 ].group_id, 'id-1' );
	assert.equal(
		lessonsFromSkills( program, { unitId: 7, names, title: '', newId } ),
		program
	);
} );
