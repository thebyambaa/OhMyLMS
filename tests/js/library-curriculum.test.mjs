import test from 'node:test';
import assert from 'node:assert/strict';
import { libraryCurriculum } from '../../assets/src/features/content-hub/libraryCurriculum.mjs';

const items = [
	{ id: 3, parent_id: 2, name: 'Algebra', position: 0 },
	{ id: 1, parent_id: 0, name: 'Cambridge', position: 1 },
	{ id: 2, parent_id: 1, name: 'Mathematics', position: 0 },
	{ id: 4, parent_id: 2, name: 'Geometry', position: 1 },
	{ id: 5, parent_id: 0, name: 'College Board', position: 0 },
];

test( 'both libraries share stable curriculum ancestry and sibling order', () => {
	const roots = libraryCurriculum( items );
	assert.deepEqual(
		roots.map( ( node ) => node.id ),
		[ 5, 1 ]
	);
	assert.deepEqual(
		roots[ 1 ].children[ 0 ].children.map( ( node ) => node.id ),
		[ 3, 4 ]
	);
	assert.equal( items[ 0 ].children, undefined );
} );

test( 'category search retains ancestors and the descendants of matched categories', () => {
	const algebra = libraryCurriculum( items, 'algebra' );
	assert.equal( algebra[ 0 ].name, 'Cambridge' );
	assert.deepEqual(
		algebra[ 0 ].children[ 0 ].children.map( ( node ) => node.id ),
		[ 3 ]
	);
	assert.equal(
		libraryCurriculum( items, 'Mathematics' )[ 0 ].children[ 0 ].children
			.length,
		2
	);
	assert.deepEqual( libraryCurriculum( items, 'missing' ), [] );
} );
