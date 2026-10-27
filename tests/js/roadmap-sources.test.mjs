import test from 'node:test';
import assert from 'node:assert/strict';
import { roadmapSourceWrite } from '../../assets/src/features/curriculum/roadmap-sources.mjs';

test( 'source-only changes retain folder assignments, order and revision', () => {
	const state = {
		name: 'Mathematics roadmap',
		syllabus_ids: [ 1, 6 ],
		revision: 'existing-revision',
		skills: [ { id: 12 }, { id: 9 }, { id: 25 } ],
		folders: [
			{ id: 10, name: 'Algebra', skill_ids: [ 12, 9 ] },
			{ id: 11, name: 'Geometry', skill_ids: [ 25 ] },
		],
	};
	const sources = [ 6, 8 ];
	const payload = roadmapSourceWrite( state, sources );
	assert.deepEqual( payload, {
		name: state.name,
		syllabus_ids: [ 6, 8 ],
		skill_ids: [ 12, 9, 25 ],
		folders: state.folders,
		expected_revision: state.revision,
	} );
	payload.folders[ 0 ].skill_ids.push( 99 );
	payload.syllabus_ids.push( 42 );
	assert.deepEqual( state.folders[ 0 ].skill_ids, [ 12, 9 ] );
	assert.deepEqual( sources, [ 6, 8 ] );
} );

test( 'an empty roadmap remains empty when choosing sources', () => {
	const payload = roadmapSourceWrite(
		{
			name: 'Draft',
			revision: 'draft-revision',
			skills: [],
			folders: [ { id: 10, name: 'Steps', skill_ids: [] } ],
		},
		[ 2 ]
	);
	assert.deepEqual( payload.skill_ids, [] );
	assert.deepEqual( payload.folders[ 0 ].skill_ids, [] );
} );
