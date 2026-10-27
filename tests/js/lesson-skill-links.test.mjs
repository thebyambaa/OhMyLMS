import test from 'node:test';
import assert from 'node:assert/strict';
import {
	lessonSkillIds,
	linkLessonSkill,
	unlinkLessonSkill,
} from '../../assets/src/features/courses/lessonSkills.mjs';
import {
	addOutlinePractice,
	removeOutlineLesson,
	removeOutlineSkills,
	outlineLessonReadiness,
	dropOutlineLesson,
} from '../../assets/src/features/courses/outline.mjs';

const original = () => ( {
	groups: [
		{ id: 'intro', chapter_id: 1, title: 'Introduce' },
		{ id: 'review', chapter_id: 2, title: 'Review' },
	],
	outcomes: [
		{
			term_id: 10,
			chapter_id: 1,
			group_id: 'intro',
			target: 'mastered',
			required: true,
		},
	],
	items: [
		{
			id: 'reading',
			type: 'lesson',
			content_id: 20,
			chapter_id: 1,
			group_id: 'intro',
			skill_ids: [ 10 ],
			status: 'publish',
		},
	],
} );

test( 'a skill links to lessons in different units without duplicating its completion target', () => {
	const source = original();
	const next = linkLessonSkill( source, 10, 2, 'review' );
	assert.deepEqual( lessonSkillIds( next, 'intro' ), [ 10 ] );
	assert.deepEqual( lessonSkillIds( next, 'review' ), [ 10 ] );
	assert.deepEqual( next.outcomes, source.outcomes );
	assert.equal( source.groups[ 1 ].skill_ids, undefined );
	assert.deepEqual(
		outlineLessonReadiness( next )[ 1 ].missingTeaching,
		[ 10 ]
	);
} );

test( 'practice can repeat in another lesson but not twice in the same lesson', () => {
	const intro = addOutlinePractice( original(), {
		termId: 10,
		unitId: 1,
		groupId: 'intro',
		id: 'p1',
	} );
	const review = addOutlinePractice( intro, {
		termId: 10,
		unitId: 2,
		groupId: 'review',
		id: 'p2',
	} );
	assert.equal( review.outcomes.length, 1 );
	assert.equal(
		review.items.filter( ( row ) => row.type === 'practice' ).length,
		2
	);
	assert.equal(
		addOutlinePractice( review, {
			termId: 10,
			unitId: 2,
			groupId: 'review',
			id: 'p3',
		} ),
		null
	);
} );

test( 'unlinking one lesson preserves the shared skill, completion and other lesson coverage', () => {
	const linked = linkLessonSkill( original(), 10, 2, 'review' );
	const next = unlinkLessonSkill( linked, 'intro', 10 );
	assert.deepEqual( lessonSkillIds( next, 'intro' ), [] );
	assert.deepEqual( lessonSkillIds( next, 'review' ), [ 10 ] );
	assert.deepEqual( next.items[ 0 ].skill_ids, [] );
	assert.equal( next.items[ 0 ].content_id, 20 );
	assert.equal( next.outcomes[ 0 ].group_id, 'review' );
	assert.equal( next.outcomes[ 0 ].required, true );
	assert.equal( next.outcomes[ 0 ].target, 'mastered' );
} );

test( 'moving or deleting a lesson retains other skill links; course removal clears all links', () => {
	const linked = linkLessonSkill( original(), 10, 2, 'review' );
	const moved = dropOutlineLesson( linked, 'review', { unitId: 3 } );
	assert.deepEqual( lessonSkillIds( moved, 'intro' ), [ 10 ] );
	assert.deepEqual( lessonSkillIds( moved, 'review' ), [ 10 ] );
	assert.deepEqual(
		lessonSkillIds( removeOutlineLesson( moved, 'intro' ), 'review' ),
		[ 10 ]
	);
	const removed = removeOutlineSkills( linked, [ 10 ] );
	assert.deepEqual( lessonSkillIds( removed, 'intro' ), [] );
	assert.deepEqual( lessonSkillIds( removed, 'review' ), [] );
} );
