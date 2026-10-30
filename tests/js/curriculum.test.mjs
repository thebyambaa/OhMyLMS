import test from 'node:test';
import assert from 'node:assert/strict';
import {
	ancestorIds,
	buildTree,
	collapseAll,
	createViewState,
	depthOf,
	descendantIds,
	draftFrom,
	ensureOpen,
	expandAll,
	filterTree,
	flattenTree,
	heightOf,
	isDirty,
	isOpen,
	moveTargets,
	needsConfirmation,
	normalizeType,
	payloadFrom,
	pathNames,
	pruneState,
	resetOverrides,
	revealBranch,
	searchItems,
	siblingMove,
	syllabusBreadcrumbs,
	toggleOpen,
	validateDraft,
	validateMove,
	withoutTopics,
} from '../../assets/src/features/curriculum/model.mjs';

test( 'collapsed branch shortcuts show each nested syllabus once with its complete path', () => {
	const items = [
		{ id: 1, name: 'Cambridge', parent_id: 0, position: 0 },
		{ id: 2, name: 'IGCSE', parent_id: 1, position: 0 },
		{ id: 3, name: 'Math', parent_id: 2, position: 0 },
		{
			id: 4,
			name: '0580 Math',
			parent_id: 3,
			position: 1,
			is_syllabus: true,
		},
		{
			id: 5,
			name: '0606 Math',
			parent_id: 3,
			position: 0,
			is_syllabus: true,
		},
		{
			id: 6,
			name: 'Roadmap',
			parent_id: 0,
			position: 1,
			is_syllabus: true,
		},
	];
	const tree = buildTree( items );
	const shortcuts = syllabusBreadcrumbs( items, tree[ 0 ].children );
	assert.deepEqual(
		shortcuts.map( ( entry ) => entry.item.id ),
		[ 5, 4 ]
	);
	assert.deepEqual( shortcuts[ 1 ].path, [
		'Cambridge',
		'IGCSE',
		'Math',
		'0580 Math',
	] );
	assert.deepEqual(
		syllabusBreadcrumbs( items, tree[ 0 ].children[ 0 ].children ),
		shortcuts
	);
	const filtered = filterTree( tree, searchItems( items, '0580' ) );
	assert.deepEqual(
		syllabusBreadcrumbs( items, filtered[ 0 ].children ).map(
			( entry ) => entry.item.id
		),
		[ 4 ]
	);
	assert.deepEqual( syllabusBreadcrumbs( items, [] ), [] );
} );

test( 'breadcrumb parents expand their complete branch without opening unrelated branches', () => {
	const items = [
		{ id: 1, parent_id: 0 },
		{ id: 2, parent_id: 1 },
		{ id: 3, parent_id: 2 },
		{ id: 4, parent_id: 3 },
		{ id: 5, parent_id: 0 },
	];
	const state = collapseAll();
	const search = searchItems( items, '' );
	const revealed = revealBranch( state, search, items, 3 );
	for ( const id of [ 1, 2, 3 ] ) {
		assert.equal( isOpen( revealed, search, id ), true );
	}
	assert.equal( isOpen( revealed, search, 4 ), false );
	assert.equal( isOpen( revealed, search, 5 ), false );
	assert.equal( state.expanded.size, 0 );
	const active = { active: true, reveal: new Set(), matches: new Set() };
	const overridden = revealBranch( state, active, items, 3 );
	assert.equal( isOpen( overridden, active, 3 ), true );
	assert.equal( state.overrides.size, 0 );
} );

test( 'hidden topics promote descendants without changing stored parents or losing sibling branches', () => {
	const items = [
		{ id: 1, parent_id: 0, item_type: 'syllabus', position: 0 },
		{ id: 2, parent_id: 1, item_type: 'topic', position: 0 },
		{ id: 3, parent_id: 2, item_type: 'topic', position: 0 },
		{ id: 4, parent_id: 3, item_type: 'custom', position: 0 },
		{ id: 5, parent_id: 1, item_type: 'custom', position: 1 },
	];
	const visible = withoutTopics( items );
	assert.deepEqual(
		visible.map( ( { id, parent_id, child_count } ) => [
			id,
			parent_id,
			child_count,
		] ),
		[
			[ 1, 0, 2 ],
			[ 4, 1, 0 ],
			[ 5, 1, 0 ],
		]
	);
	assert.equal( items[ 3 ].parent_id, 3 );
	assert.deepEqual(
		buildTree( visible )[ 0 ].children.map( ( node ) => node.item.id ),
		[ 4, 5 ]
	);
} );
import * as api from '../../assets/src/features/curriculum/api.mjs';

// Names are made up for the tests: they illustrate shapes, not real syllabus definitions.
const item = ( id, parent, position, name, extra = {} ) => ( {
	id,
	parent_id: parent,
	position,
	name,
	item_type: 'custom',
	description: '',
	code: '',
	version: '',
	...extra,
} );
const items = [
	item( 1, 0, 0, 'Board A' ),
	item( 2, 1, 0, 'Level one' ),
	item( 3, 2, 0, 'Subject', { code: 'S-100', version: '2025' } ),
	item( 4, 3, 0, 'Syllabus', { item_type: 'syllabus', code: '0001' } ),
	item( 5, 1, 1, 'Level two' ),
	item( 6, 0, 1, 'Test B' ),
	item( 7, 6, 0, 'Reading section' ),
	item( 8, 6, 1, 'Math section' ),
];

test( 'tree is ordered by sibling position and nests to any depth', () => {
	const shuffled = [ ...items ].reverse();
	const tree = buildTree( shuffled );
	assert.deepEqual(
		tree.map( ( node ) => node.item.name ),
		[ 'Board A', 'Test B' ]
	);
	assert.deepEqual(
		tree[ 0 ].children.map( ( node ) => node.item.name ),
		[ 'Level one', 'Level two' ]
	);
	assert.equal(
		tree[ 0 ].children[ 0 ].children[ 0 ].children[ 0 ].item.name,
		'Syllabus'
	);
	assert.equal(
		tree[ 0 ].children[ 0 ].children[ 0 ].children[ 0 ].depth,
		3
	);
	assert.deepEqual(
		flattenTree( tree ).map( ( node ) => node.item.id ),
		[ 1, 2, 3, 4, 5, 6, 7, 8 ]
	);
} );

test( 'orphans surface as roots and loops are broken without hiding any item', () => {
	const orphan = buildTree( [ item( 1, 99, 0, 'Orphan' ) ] );
	assert.deepEqual(
		orphan.map( ( node ) => node.item.id ),
		[ 1 ]
	);
	const loop = buildTree( [
		item( 1, 2, 0, 'A' ),
		item( 2, 1, 0, 'B' ),
		item( 3, 0, 0, 'C' ),
	] );
	const ids = flattenTree( loop )
		.map( ( node ) => node.item.id )
		.sort();
	assert.deepEqual( ids, [ 1, 2, 3 ] );
} );

test( 'ancestors, descendants, depth, height and paths', () => {
	assert.deepEqual( ancestorIds( items, 4 ), [ 3, 2, 1 ] );
	assert.deepEqual( descendantIds( items, 1 ).sort(), [ 2, 3, 4, 5 ] );
	assert.equal( depthOf( items, 4 ), 4 );
	assert.equal( heightOf( items, 1 ), 4 );
	assert.equal( heightOf( items, 4 ), 1 );
	assert.deepEqual( pathNames( items, 4 ), [
		'Board A',
		'Level one',
		'Subject',
	] );
	assert.deepEqual(
		ancestorIds( [ item( 1, 2, 0, 'A' ), item( 2, 1, 0, 'B' ) ], 1 ),
		[ 2 ]
	);
} );

test( 'moves are refused under the item itself, its descendants, a missing parent or past the depth limit', () => {
	assert.deepEqual( validateMove( items, 1, 1 ), {
		ok: false,
		reason: 'self',
	} );
	assert.deepEqual( validateMove( items, 1, 4 ), {
		ok: false,
		reason: 'cycle',
	} );
	assert.deepEqual( validateMove( items, 2, 3 ), {
		ok: false,
		reason: 'cycle',
	} );
	assert.deepEqual( validateMove( items, 4, 99 ), {
		ok: false,
		reason: 'missing-parent',
	} );
	assert.deepEqual( validateMove( items, 99, 1 ), {
		ok: false,
		reason: 'missing',
	} );
	assert.deepEqual( validateMove( items, 4, 0 ), { ok: true } );
	assert.deepEqual( validateMove( items, 5, 6 ), { ok: true } );
	// Board A has four levels; under Test B (depth 1) it would reach depth 5.
	assert.equal( validateMove( items, 1, 6, 4 ).reason, 'depth' );
	assert.equal( validateMove( items, 1, 6, 5 ).ok, true );
} );

test( 'move targets exclude the item, its subtree and anything beyond the depth limit', () => {
	// Item 2's own subtree (3, 4) and itself are excluded; its sibling 5 is a legal new parent.
	const targets = moveTargets( items, 2 ).map( ( target ) => target.id );
	assert.deepEqual( targets, [ 1, 5, 6, 7, 8 ] );
	assert.equal(
		moveTargets( items, 1, 4 ).some( ( target ) => target.id === 6 ),
		false
	);
	assert.deepEqual(
		moveTargets( items, 5 ).map( ( target ) => [
			target.id,
			target.depth,
		] ),
		[
			[ 1, 0 ],
			[ 2, 1 ],
			[ 3, 2 ],
			[ 4, 3 ],
			[ 6, 0 ],
			[ 7, 1 ],
			[ 8, 1 ],
		]
	);
} );

test( 'sibling steps give the final index and stop at the edges', () => {
	assert.deepEqual( siblingMove( items, 5, -1 ), {
		parentId: 1,
		position: 0,
	} );
	assert.equal( siblingMove( items, 2, -1 ), null );
	assert.deepEqual( siblingMove( items, 2, 1 ), {
		parentId: 1,
		position: 1,
	} );
	assert.equal( siblingMove( items, 5, 1 ), null );
	assert.deepEqual( siblingMove( items, 6, -1 ), {
		parentId: 0,
		position: 0,
	} );
	assert.equal( siblingMove( items, 99, 1 ), null );
} );

test( 'search matches names, codes, versions, types and descriptions and reveals ancestors', () => {
	const byCode = searchItems( items, 's-100' );
	assert.deepEqual( [ ...byCode.matches ], [ 3 ] );
	assert.deepEqual( [ ...byCode.reveal ].sort(), [ 1, 2 ] );
	assert.deepEqual( [ ...searchItems( items, 'syllabus' ).matches ], [ 4 ] );
	assert.deepEqual( [ ...searchItems( items, '2025' ).matches ], [ 3 ] );
	assert.equal( searchItems( items, '   ' ).active, false );
	assert.deepEqual(
		[ ...searchItems( items, 'SECTION' ).matches ].sort(),
		[ 7, 8 ]
	);
	assert.equal( searchItems( items, 'section math' ).matches.has( 8 ), true );
	assert.equal( searchItems( items, 'nothing like this' ).matches.size, 0 );
	const cyrillic = [
		item( 1, 0, 0, 'Үндэсний хөтөлбөр' ),
		item( 2, 1, 0, 'Математик' ),
	];
	assert.deepEqual(
		[ ...searchItems( cyrillic, 'математик' ).matches ],
		[ 2 ]
	);
} );

test( 'filtering keeps each hit inside its ancestors and hides the rest', () => {
	const search = searchItems( items, 'syllabus' );
	const tree = filterTree( buildTree( items ), search );
	assert.deepEqual(
		flattenTree( tree ).map( ( node ) => node.item.id ),
		[ 1, 2, 3, 4 ]
	);
	assert.equal(
		filterTree( buildTree( items ), searchItems( items, '' ) ).length,
		2
	);
} );

test( 'open and closed branches are kept apart from the data and survive edits', () => {
	const none = searchItems( items, '' );
	let state = createViewState();
	state = toggleOpen( state, none, 1 );
	state = toggleOpen( state, none, 2 );
	assert.equal( isOpen( state, none, 1 ), true );
	// An edit changes the data (rename, add child) but not the view state.
	const edited = items
		.map( ( entry ) =>
			entry.id === 3 ? { ...entry, name: 'Renamed' } : entry
		)
		.concat( item( 9, 3, 1, 'New child' ) );
	assert.equal( pruneState( state, edited ), state );
	assert.equal( isOpen( pruneState( state, edited ), none, 2 ), true );
	// Deleting item 2 forgets only that branch.
	const deleted = pruneState(
		state,
		items.filter( ( entry ) => entry.id !== 2 )
	);
	assert.equal( isOpen( deleted, none, 1 ), true );
	assert.equal( isOpen( deleted, none, 2 ), false );
	// Toggling does not mutate the previous state.
	const closed = toggleOpen( state, none, 1 );
	assert.equal( isOpen( state, none, 1 ), true );
	assert.equal( isOpen( closed, none, 1 ), false );
	assert.equal( ensureOpen( state, 1 ), state );
	assert.equal( isOpen( ensureOpen( state, 6 ), none, 6 ), true );
} );

test( 'searching reveals branches without changing the saved expansion', () => {
	const none = searchItems( items, '' );
	let state = toggleOpen( createViewState(), none, 6 );
	const search = searchItems( items, 'syllabus' );
	assert.equal( isOpen( state, search, 1 ), true );
	assert.equal( isOpen( state, search, 6 ), false );
	state = toggleOpen( state, search, 1 );
	assert.equal( isOpen( state, search, 1 ), false );
	assert.equal( state.expanded.has( 1 ), false );
	// Clearing the query drops the temporary overrides; the original choices return.
	state = resetOverrides( state );
	assert.equal( isOpen( state, none, 6 ), true );
	assert.equal( isOpen( state, none, 1 ), false );
	assert.equal( resetOverrides( state ), state );
} );

test( 'expand and collapse all', () => {
	const everything = expandAll( items );
	assert.deepEqual( [ ...everything.expanded ].sort(), [ 1, 2, 3, 6 ] );
	assert.equal( collapseAll().expanded.size, 0 );
} );

test( 'type slugs are normalized and drafts validated like the server', () => {
	assert.equal( normalizeType( '  IB Programme ' ), 'ib-programme' );
	assert.equal( normalizeType( 'Grade_9!' ), 'grade_9' );
	assert.deepEqual( validateDraft( { name: '', item_type: 'custom' } ), {
		name: 'required',
	} );
	assert.deepEqual( validateDraft( { name: 'x'.repeat( 191 ) } ), {
		name: 'too-long',
	} );
	assert.deepEqual( validateDraft( { name: 'ok', item_type: '9lives' } ), {
		item_type: 'invalid',
	} );
	assert.deepEqual(
		validateDraft( {
			name: 'ok',
			code: 'c'.repeat( 61 ),
			version: 'v'.repeat( 61 ),
			description: 'd'.repeat( 2001 ),
		} ),
		{ code: 'too-long', version: 'too-long', description: 'too-long' }
	);
	assert.deepEqual(
		validateDraft( {
			name: 'Fine',
			item_type: 'ib-programme',
			code: '',
			description: '',
		} ),
		{}
	);
} );

test( 'drafts bundle unsaved changes and produce trimmed payloads', () => {
	const source = item( 3, 2, 0, 'Subject', {
		item_type: 'subject',
		code: 'S-100',
		version: '2025',
		description: 'About',
	} );
	const draft = draftFrom( source );
	assert.equal( isDirty( draft, source ), false );
	assert.equal( isDirty( { ...draft, name: 'Subject two' }, source ), true );
	assert.equal( isDirty( { ...draft, name: 'Subject ' }, source ), false );
	assert.deepEqual(
		payloadFrom( {
			...draft,
			name: ' Subject two ',
			item_type: 'Sub ject',
			code: ' X ',
		} ),
		{
			name: 'Subject two',
			item_type: 'sub-ject',
			description: 'About',
			code: 'X',
			version: '2025',
		}
	);
	assert.equal(
		payloadFrom( { name: 'A', item_type: '' } ).item_type,
		'custom'
	);
} );

test( 'deletion needs confirmation when children, links or bundles would be affected', () => {
	const none = {
		children: 0,
		links: 0,
		own_links: 0,
		bundles: 0,
		own_bundles: 0,
	};
	assert.equal( needsConfirmation( none, '' ), false );
	assert.equal( needsConfirmation( { ...none, own_links: 2 }, '' ), true );
	assert.equal(
		needsConfirmation( { ...none, children: 2 }, 'promote' ),
		true
	);
	assert.equal(
		needsConfirmation(
			{ ...none, links: 3, own_links: 0, children: 0 },
			'promote'
		),
		false
	);
	assert.equal(
		needsConfirmation(
			{ ...none, links: 3, own_links: 0, children: 1 },
			'delete'
		),
		true
	);
} );

test( 'the skill picker reads the curriculum tree and a syllabus outline', async () => {
	const calls = [];
	const fetch = ( args ) => {
		calls.push( args );
		return Promise.resolve( {} );
	};
	await api.loadTree( fetch );
	await api.loadSyllabus( 5, fetch );
	assert.deepEqual( calls, [
		{ path: '/ohmylms/v1/curriculum/tree' },
		{ path: '/ohmylms/v1/curriculum/items/5/syllabus' },
	] );
} );
