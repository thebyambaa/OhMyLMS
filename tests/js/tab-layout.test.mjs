import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {
	normalizeLayout,
	assignTab,
	moveTab,
	moveBlock,
	layoutBlocks,
} from '../../assets/src/features/navigation/tabLayout.mjs';

const ids = [ 'catalog', 'courses', 'quizzes', 'skills' ];
const group = {
	id: 'learning',
	name: 'Learning',
	background: '#123456',
	text: '#ffffff',
	collapsed: false,
};
const grouped = () =>
	normalizeLayout(
		{
			order: ids,
			groups: [ group ],
			assignments: { courses: 'learning', quizzes: 'learning' },
		},
		ids
	);

test( 'layout recovery preserves saved order and adds new tabs without duplicates or removed tabs', () => {
	const recovered = normalizeLayout(
		{
			order: [ 'skills', 'deleted', 'skills', 'courses' ],
			groups: [
				null,
				{ id: '!invalid' },
				{ ...group, background: 'bad' },
				group,
			],
			assignments: { courses: 'missing' },
		},
		ids
	);
	assert.deepEqual( recovered.order, [
		'skills',
		'courses',
		'catalog',
		'quizzes',
	] );
	assert.equal( recovered.groups.length, 1 );
	assert.equal( recovered.groups[ 0 ].background, '#ede7f6' );
	assert.deepEqual( recovered.assignments, {} );
	assert.deepEqual( normalizeLayout( null, ids ).order, ids );
} );

test( 'tabs move in both directions and can join, leave and reorder within a group', () => {
	let layout = moveTab( grouped(), 'skills', 'catalog' );
	assert.deepEqual( layout.order, [
		'skills',
		'catalog',
		'courses',
		'quizzes',
	] );
	layout = moveTab( layout, 'skills', 'quizzes', true );
	assert.deepEqual( layout.order, [
		'catalog',
		'courses',
		'quizzes',
		'skills',
	] );
	assert.equal( layout.assignments.skills, 'learning' );
	layout = moveTab( layout, 'skills', 'courses' );
	assert.deepEqual( layout.order, [
		'catalog',
		'skills',
		'courses',
		'quizzes',
	] );
	layout = moveTab( layout, 'skills', null );
	assert.equal( layout.assignments.skills, undefined );
	assert.deepEqual( layout.order, ids );
} );

test( 'group dragging moves all members together and retains their colors and collapse state', () => {
	const layout = { ...grouped(), groups: [ { ...group, collapsed: true } ] };
	const moved = moveBlock( layout, 'group:learning', 'tab:skills', true );
	assert.deepEqual( moved.order, [
		'catalog',
		'skills',
		'courses',
		'quizzes',
	] );
	assert.deepEqual( moved.groups, layout.groups );
	assert.deepEqual( layoutBlocks( moved ).at( -1 ).tabs, [
		'courses',
		'quizzes',
	] );
	assert.equal(
		moveBlock( layout, 'group:learning', 'group:learning' ),
		layout
	);
} );

test( 'group assignment keeps members contiguous and removing a group retains every tab', () => {
	let layout = assignTab( grouped(), 'catalog', 'learning' );
	assert.deepEqual(
		layoutBlocks( layout ).map( ( block ) => block.tabs ),
		[ [ 'courses', 'quizzes', 'catalog' ], [ 'skills' ] ]
	);
	layout = assignTab( layout, 'quizzes', '' );
	assert.equal( layout.assignments.quizzes, undefined );
	assert.equal( new Set( layout.order ).size, ids.length );
	const removed = normalizeLayout( { ...layout, groups: [] }, ids );
	assert.deepEqual( removed.assignments, {} );
	assert.deepEqual( new Set( removed.order ), new Set( ids ) );
} );

test( 'queued saves keep only the latest layout, survive remounts, and expose retryable errors', async () => {
	const source = fs
		.readFileSync(
			'assets/src/features/navigation/tabPreferences.js',
			'utf8'
		)
		.replace( /^import .*;\r?\n/gm, '' )
		.replace( 'export function', 'function' );
	const calls = [];
	const pending = [];
	const apiFetch = ( request ) => {
		calls.push( request );
		return new Promise( ( resolve, reject ) =>
			pending.push( { resolve, reject } )
		);
	};
	const preferences = new Function(
		'apiFetch',
		'normalizeLayout',
		'window',
		`${ source }; return tabPreferences;`
	)( apiFetch, normalizeLayout, {
		ohmylmsTabPreferences: { 'content-hub': grouped() },
	} );
	const store = preferences( 'content-hub', ids );
	assert.deepEqual( store.read().layout, grouped() );
	store.save( moveTab( grouped(), 'skills', 'catalog' ) );
	store.save( moveTab( grouped(), 'catalog', 'skills', true ) );
	const flush = () => new Promise( ( resolve ) => setImmediate( resolve ) );
	await flush();
	assert.equal( calls.length, 1 );
	assert.equal( calls[ 0 ].data.order.at( -1 ), 'catalog' );
	assert.equal( preferences( 'content-hub', ids ), store );
	pending.shift().reject( new Error( 'Offline' ) );
	await flush();
	assert.equal( store.read().status, 'error' );
	store.save( store.read().layout );
	await flush();
	pending.shift().resolve( {} );
	await flush();
	assert.equal( store.read().status, 'saved' );
	const membership = preferences( 'memberships', [ 'plans', 'orders' ] );
	store.save( grouped() );
	membership.save(
		normalizeLayout( { order: [ 'orders', 'plans' ] }, [
			'plans',
			'orders',
		] )
	);
	await flush();
	assert.equal(
		calls.length,
		3,
		'sections serialize writes to shared user metadata'
	);
	pending.shift().resolve( {} );
	await flush();
	assert.equal(
		calls.at( -1 ).path,
		'/ohmylms/v1/tab-preferences/memberships'
	);
	pending.shift().resolve( {} );
	await flush();
	assert.deepEqual( membership.read().layout.order, [ 'orders', 'plans' ] );
	assert.deepEqual( store.read().layout.order, ids );
} );
