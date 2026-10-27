import test from 'node:test';
import assert from 'node:assert/strict';
import { mappingPreview, readComparison, writeComparison } from '../../assets/src/features/curriculum/comparison.mjs';

test( 'explicit previews deduplicate memberships by identity and retain current revisions', () => {
	const a = { term_id: 10, maps_to: [ { shared_id: 80, revision: 'current' } ] };
	const b = { term_id: 11, maps_to: [] };
	assert.deepEqual( mappingPreview( [ a, b, a ], { id: 80 }, 'related', 'Narrower scope' ), [
		{ specific_id: 10, shared_id: 80, relation: 'related', note: 'Narrower scope', expected_revision: 'current' },
		{ specific_id: 11, shared_id: 80, relation: 'related', note: 'Narrower scope', expected_revision: '' },
	] );
	assert.deepEqual( mappingPreview( [ a ], null, 'equivalent', '' ), [] );
} );

test( 'comparison reads encode filters and writes require an explicit call', async () => {
	const calls = [];
	const fetch = async ( options ) => { calls.push( options ); return { items: [] }; };
	await readComparison( 'objectives/4', { search: 'a > b & c', page: 2 }, fetch );
	assert.equal( calls[ 0 ].path, '/ohmylms/v1/syllabus-comparison/objectives/4?search=a+%3E+b+%26+c&page=2' );
	await writeComparison( 'mapping', { specific_id: 10, shared_id: 80 }, 'PUT', fetch );
	assert.equal( calls[ 1 ].method, 'PUT' );
	assert.deepEqual( calls[ 1 ].data, { specific_id: 10, shared_id: 80 } );
} );
