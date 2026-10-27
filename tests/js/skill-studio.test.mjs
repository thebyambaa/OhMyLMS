import test from 'node:test';
import assert from 'node:assert/strict';
import {
	loadStudioLinks,
	studioObjectives,
	studioWrite,
} from '../../assets/src/features/curriculum/skill-studio.mjs';

test( 'focused connections include later pages without loading unrelated objectives', async () => {
	const calls = [];
	const read = async ( route, filters ) => {
		calls.push( { route, filters } );
		return {
			total: 31,
			items:
				filters.page === 1
					? Array.from( { length: 30 }, ( _, index ) => ( {
							term_id: index + 1,
						} ) )
					: [ { term_id: 31 } ],
		};
	};
	const groups = await loadStudioLinks(
		[ { id: 7, name: 'Cambridge' } ],
		88,
		read
	);
	assert.equal( groups[ 0 ].items.length, 31 );
	assert.deepEqual( calls, [
		{ route: 'objectives/7', filters: { shared_id: 88, page: 1 } },
		{ route: 'objectives/7', filters: { shared_id: 88, page: 2 } },
	] );
} );

test( 'one objective appearing in multiple sources keeps both memberships', () => {
	const row = { term_id: 22, name: 'Solve equations', maps_to: [] };
	const rows = studioObjectives( [
		{ source: { id: 3, name: 'Core' }, items: [ row ] },
		{ source: { id: 4, name: 'Extended' }, items: [ row ] },
	] );
	assert.equal( rows.length, 1 );
	assert.deepEqual( rows[ 0 ].source_names, [ 'Core', 'Extended' ] );
	assert.equal( rows[ 0 ].source_id, 3 );
} );

test( 'reconnection retains the original revision and cannot overwrite a grouped mapping', () => {
	const proposal = {
		row: { term_id: 10, source_id: 3 },
		mapping: { revision: 'original-revision', group_uuid: '' },
		replacement: { term_id: 11, source_id: 4 },
		relation: 'narrower',
		note: 'Core scope only.',
	};
	assert.deepEqual( studioWrite( proposal, 80 ), {
		route: 'mapping/reconnect',
		method: 'POST',
		data: {
			specific_id: 11,
			old_specific_id: 10,
			shared_id: 80,
			source_id: 4,
			relation: 'narrower',
			note: 'Core scope only.',
			expected_revision: 'original-revision',
		},
	} );
	assert.equal(
		studioWrite(
			{
				...proposal,
				mapping: { ...proposal.mapping, group_uuid: 'combined-group' },
			},
			80
		),
		null
	);
} );

test( 'new connections explicitly require an absent pair and stable shared identity', () => {
	const proposal = { row: { term_id: 10 }, relation: 'related', note: '' };
	assert.equal( studioWrite( proposal, 80 ).data.expected_revision, '' );
	assert.equal( studioWrite( proposal, 0 ), null );
	assert.equal( studioWrite( null, 80 ), null );
} );
