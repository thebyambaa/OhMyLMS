import test from 'node:test';
import assert from 'node:assert/strict';
import { connectActivitySkills } from '../../assets/src/features/courses/activitySkills.mjs';

test( 'a syllabus tag creates a course outcome and connects the selected activity atomically', () => {
	const draft = {
		skill_names: {},
		skill_codes: {},
		program: {
			mode: 'traditional',
			outcomes: [],
			groups: [ { id: 'legacy' } ],
			items: [
				{
					id: 'lesson',
					chapter_id: 7,
					group_id: 'legacy',
					skill_ids: [],
				},
				{ id: 'quiz', skill_ids: [] },
			],
		},
	};
	const row = { term_id: 12, code: 'SAT.1', name: 'Linear equations' };
	const next = connectActivitySkills(
		draft,
		'lesson',
		[ 12, 12 ],
		[ row, row ]
	);
	assert.deepEqual( next.program.outcomes, [
		{ term_id: 12, target: 'proficient', required: false, chapter_id: 7 },
	] );
	assert.deepEqual( next.program.items[ 0 ].skill_ids, [ 12 ] );
	assert.deepEqual( next.program.items[ 1 ], draft.program.items[ 1 ] );
	assert.deepEqual( next.program.groups, draft.program.groups );
	assert.equal( next.skill_names[ 12 ], 'Linear equations' );
	assert.equal( next.skill_codes[ 12 ], 'SAT.1' );
	assert.deepEqual( draft.program.outcomes, [] );
} );

test( 'removing an activity tag keeps course requirements and other activity links', () => {
	const outcome = {
		term_id: 12,
		target: 'mastered',
		required: true,
		chapter_id: 8,
	};
	const draft = {
		skill_names: { 12: 'Linear equations' },
		skill_codes: {},
		program: {
			outcomes: [ outcome ],
			items: [
				{ id: 'lesson', skill_ids: [ 12 ] },
				{ id: 'quiz', skill_ids: [ 12 ] },
			],
		},
	};
	const linked = connectActivitySkills(
		draft,
		'lesson',
		[ 12 ],
		[ { term_id: 12, name: 'Linear equations' } ]
	);
	assert.deepEqual( linked.program.outcomes, [ outcome ] );
	const removed = connectActivitySkills( linked, 'lesson', [], [] );
	assert.deepEqual( removed.program.outcomes, [ outcome ] );
	assert.deepEqual( removed.program.items[ 0 ].skill_ids, [] );
	assert.deepEqual( removed.program.items[ 1 ].skill_ids, [ 12 ] );
} );
