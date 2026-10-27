import test from 'node:test';
import assert from 'node:assert/strict';
import {
	acceptedSkill,
	generatedSource,
	placeSaved,
	provenanceBadge,
	suggestionsFor,
} from '../../assets/src/features/courses/provenance.mjs';

test( 'provenance badges flag archived, rejected and pending skills', () => {
	assert.equal( provenanceBadge(), null );
	assert.equal(
		provenanceBadge( { review: 'approved', status: 'active' } ),
		null
	);
	assert.deepEqual(
		provenanceBadge( { status: 'archived', review: 'approved' } ),
		{
			kind: 'archived',
			warning: true,
		}
	);
	assert.equal( provenanceBadge( { review: 'rejected' } ).kind, 'rejected' );
	assert.equal(
		provenanceBadge( { review: 'pending', source: 'course-content' } ).kind,
		'ai-pending'
	);
	assert.equal(
		provenanceBadge( { review: 'pending', source: 'manual' } ).kind,
		'pending'
	);
	assert.equal( generatedSource( 'ai' ), true );
	assert.equal( generatedSource( 'syllabus' ), false );
} );

test( 'suggestions are shown in the place they were made for', () => {
	const rows = [
		{ id: 1, status: 'pending', target: { unit_id: 5, group_id: 'a' } },
		{ id: 2, status: 'pending', target: { unit_id: 5, group_id: '' } },
		{ id: 3, status: 'accepted', target: { unit_id: 5, group_id: 'a' } },
		{ id: 4, status: 'pending', target: { unit_id: 6, group_id: 'a' } },
	];
	assert.deepEqual(
		suggestionsFor( rows, { unitId: 5, groupId: 'a' } ).map(
			( row ) => row.id
		),
		[ 1 ]
	);
	assert.deepEqual(
		suggestionsFor( rows, { unitId: '5', groupId: '' } ).map(
			( row ) => row.id
		),
		[ 2 ]
	);
} );

test( 'accepted suggestions become the skill to link', () => {
	assert.equal( acceptedSkill( { result_id: 0 } ), null );
	assert.deepEqual(
		acceptedSkill( { result_id: 9, shared: { id: 9, name: 'Main idea' } } ),
		{
			termId: 9,
			name: 'Main idea',
			meta: { source: 'manual', review: 'approved', status: 'active' },
		}
	);
	const created = acceptedSkill(
		{
			result_id: 12,
			source: 'course-content',
			confidence: 0.8,
			proposal: { name: 'Scan for detail', meta: { model: 'm' } },
		},
		{ name: 'Scan a text for detail' }
	);
	assert.equal( created.name, 'Scan a text for detail' );
	assert.deepEqual( created.meta, {
		source: 'course-content',
		review: 'pending',
		status: 'active',
		model: 'm',
		confidence: 0.8,
	} );
} );

test( 'a source can read only saved units and lessons', () => {
	const baseline = JSON.stringify( {
		units: [ { id: 5 }, { id: 'new-uuid' } ],
		program: { groups: [ { id: 'a' } ] },
	} );
	assert.equal( placeSaved( baseline, { unitId: 5, groupId: 'a' } ), true );
	assert.equal( placeSaved( baseline, { unitId: 5, groupId: '' } ), true );
	assert.equal( placeSaved( baseline, { unitId: 5, groupId: 'b' } ), false );
	assert.equal(
		placeSaved( baseline, { unitId: 'new-uuid', groupId: '' } ),
		false
	);
	assert.equal( placeSaved( 'broken', { unitId: 5 } ), false );
} );
