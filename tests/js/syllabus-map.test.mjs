import test from 'node:test';
import assert from 'node:assert/strict';
import {
	layoutSyllabusMap,
	MAP_CARD,
} from '../../assets/src/features/curriculum/syllabusMap.mjs';
import { buildOutline } from '../../assets/src/features/curriculum/workspace.mjs';

const outline = {
	contents: [
		{ id: 1, depth: 0, name: 'Mathematics', groups: [] },
		{
			id: 2,
			parent_id: 1,
			depth: 1,
			name: 'Algebra',
			groups: [
				{
					id: 10,
					name: 'Equations',
					skills: [
						{ term_id: 100, name: 'Solve equations' },
						{ term_id: 101, name: 'Form equations' },
					],
				},
			],
		},
		{
			id: 3,
			parent_id: 1,
			depth: 1,
			name: 'Geometry',
			groups: [
				{
					id: 11,
					name: 'Shapes',
					skills: [ { term_id: 100, name: 'Solve equations' } ],
				},
			],
		},
	],
};
const label = ( node ) => ( node.content || node.group || node.skill ).name;
const tree = buildOutline( outline );
const layout = ( collapsed = new Set(), query = '' ) =>
	layoutSyllabusMap( tree.root, collapsed, query, label );

test( 'map retains placement identities and produces non-overlapping cards inside canvas bounds', () => {
	const before = JSON.stringify( outline );
	const map = layout();
	assert.equal( map.nodes.length, tree.index.size );
	assert.equal( map.edges.length, map.nodes.length - 1 );
	assert.equal(
		map.nodes.filter( ( entry ) => entry.node.id === 100 ).length,
		2
	);
	assert.equal(
		new Set( map.nodes.map( ( entry ) => entry.node.key ) ).size,
		map.nodes.length
	);
	for ( const first of map.nodes ) {
		assert.ok( first.x >= 0 && first.y >= 0 );
		assert.ok( first.x + MAP_CARD.width <= map.width );
		assert.ok( first.y + MAP_CARD.height <= map.height );
		for ( const second of map.nodes ) {
			if ( first !== second && first.x === second.x ) {
				assert.ok( Math.abs( first.y - second.y ) >= MAP_CARD.height );
			}
		}
	}
	assert.equal( JSON.stringify( outline ), before );
} );

test( 'collapse removes only descendants and search reveals full ancestor paths without changing collapse state', () => {
	const collapsed = new Set( [ 'c:2' ] );
	assert.deepEqual(
		layout( collapsed ).nodes.map( ( entry ) => entry.node.key ),
		[ 'c:1', 'c:2', 'c:3', 'g:11', 's:11:100' ]
	);
	assert.deepEqual(
		layout( collapsed, '  FORM EQUATIONS ' ).nodes.map(
			( entry ) => entry.node.key
		),
		[ 'c:1', 'c:2', 'g:10', 's:10:101' ]
	);
	assert.deepEqual( [ ...collapsed ], [ 'c:2' ] );
	assert.equal( layout( new Set( [ 'c:1' ] ) ).nodes.length, 1 );
} );

test( 'empty roots, unmatched search and leaf-only syllabuses have valid bounds', () => {
	assert.deepEqual(
		layoutSyllabusMap( null, new Set(), '', label ).nodes,
		[]
	);
	assert.equal( layout( new Set(), 'unmatched' ).nodes.length, 0 );
	const only = layoutSyllabusMap(
		{ key: 'root', name: 'Root', children: [] },
		new Set(),
		'',
		( node ) => node.name
	);
	assert.equal( only.nodes.length, 1 );
	assert.equal( only.edges.length, 0 );
	assert.ok( only.height >= MAP_CARD.height );
} );

test( 'saved connections remain attached to shared skill placements and enlarged cards do not overlap', () => {
	const connected = structuredClone( outline );
	connected.contents[ 1 ].groups[ 0 ].skills[ 0 ].connections = [
		{
			id: 1,
			specific_id: 200,
			shared_id: 100,
			relation: 'broader',
			specific: { id: 200, name: 'Source objective' },
		},
	];
	const connectedTree = buildOutline( connected );
	const map = layoutSyllabusMap( connectedTree.root, new Set(), '', label );
	assert.ok( map.cardHeight > MAP_CARD.height );
	assert.equal(
		connectedTree.index.get( 's:10:100' ).skill.connections[ 0 ].specific
			.name,
		'Source objective'
	);
	for ( const first of map.nodes ) {
		assert.ok( first.y + map.cardHeight <= map.height );
		for ( const second of map.nodes ) {
			if ( first !== second && first.x === second.x ) {
				assert.ok( Math.abs( first.y - second.y ) >= map.cardHeight );
			}
		}
	}
} );
