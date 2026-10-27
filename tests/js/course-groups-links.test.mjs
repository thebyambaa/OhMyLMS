import test from 'node:test';
import assert from 'node:assert/strict';
import {
	addGroup,
	assignItem,
	deleteGroup,
	groupsOf,
	itemsIn,
	moveGroup,
	moveItemToUnit,
	prepareGroups,
	renameGroup,
} from '../../assets/src/features/courses/groups.mjs';
import {
	addLink,
	linkPayload,
	moveLink,
	removeLink,
	saveLinks,
	searchCourses,
} from '../../assets/src/features/courses/courseLinks.mjs';

const base = () => ( {
	mode: 'traditional',
	custom: 'kept',
	outcomes: [],
	items: [
		{ id: 'a', type: 'lesson', content_id: 1, chapter_id: 10 },
		{ id: 'b', type: 'quiz', content_id: 2, chapter_id: 10 },
		{ id: 'c', type: 'lesson', content_id: 3, chapter_id: 11 },
	],
} );

test( 'groups are added per unit, renamed, ordered and deleted without losing items', () => {
	let program = addGroup( base(), 10, '  Basics  ', 'g1' );
	program = addGroup( program, 11, 'Other', 'g2' );
	program = addGroup( program, 10, 'Advanced', 'g3' );
	assert.deepEqual(
		groupsOf( program, 10 ).map( ( group ) => group.title ),
		[ 'Basics', 'Advanced' ]
	);
	assert.equal( addGroup( program, 10, '   ', 'x' ), program );
	program = moveGroup( program, 'g3', -1 );
	assert.deepEqual(
		groupsOf( program, 10 ).map( ( group ) => group.id ),
		[ 'g3', 'g1' ]
	);
	assert.equal( moveGroup( program, 'g3', -1 ), program );
	program = assignItem( program, 'a', 'g1' );
	assert.deepEqual(
		itemsIn( program, 10, 'g1' ).map( ( i ) => i.id ),
		[ 'a' ]
	);
	assert.deepEqual(
		itemsIn( program, 10 ).map( ( i ) => i.id ),
		[ 'b' ]
	);
	// A group of another unit is refused.
	assert.equal(
		assignItem( program, 'c', 'g1' ).items[ 2 ].group_id,
		undefined
	);
	program = renameGroup( program, 'g1', 'Intro' );
	assert.equal( groupsOf( program, 10 )[ 1 ].title, 'Intro' );
	program = deleteGroup( program, 'g1' );
	assert.equal( program.items[ 0 ].group_id, undefined );
	assert.equal( program.items.length, 3 );
	assert.equal( program.custom, 'kept' );
	assert.equal(
		deleteGroup( deleteGroup( program, 'g2' ), 'g3' ).groups,
		undefined
	);
} );

test( 'moving an item to another unit takes it out of its group', () => {
	let program = addGroup( base(), 10, 'Basics', 'g1' );
	program = assignItem( program, 'a', 'g1' );
	const moved = moveItemToUnit( program, 'a', 11 );
	assert.equal( moved.items[ 0 ].chapter_id, 11 );
	assert.equal( moved.items[ 0 ].group_id, undefined );
	assert.equal( program.items[ 0 ].group_id, 'g1' );
} );

test( 'prepareGroups trims titles and reports unnamed groups', () => {
	let program = addGroup( base(), 10, 'Basics', 'g1' );
	program = renameGroup( program, 'g1', '   ' );
	assert.deepEqual( prepareGroups( program ).invalid, [ 'g1' ] );
	program = renameGroup( program, 'g1', ' Intro  to  it ' );
	const { program: clean, invalid } = prepareGroups( program );
	assert.deepEqual( invalid, [] );
	assert.equal( clean.groups[ 0 ].title, 'Intro to it' );
} );

test( 'course links reject duplicates and self links, reorder and serialise', async () => {
	let links = addLink( [], 5, {
		course_id: 7,
		type: 'prerequisite',
		title: 'Algebra',
	} );
	links = addLink( links, 5, {
		course_id: 8,
		type: 'get_ready',
		title: 'Arithmetic',
	} );
	assert.equal(
		addLink( links, 5, { course_id: 7, type: 'prerequisite' } ),
		links
	);
	assert.equal(
		addLink( links, 5, { course_id: 5, type: 'subcourse' } ),
		links
	);
	assert.equal(
		addLink( links, 5, { course_id: 7, type: 'subcourse' } ).length,
		3
	);
	links = moveLink( links, '8:get_ready', -1 );
	assert.deepEqual( linkPayload( links ), [
		{ course_id: 8, type: 'get_ready' },
		{ course_id: 7, type: 'prerequisite' },
	] );
	assert.deepEqual(
		removeLink( links, '8:get_ready' ).map( ( l ) => l.course_id ),
		[ 7 ]
	);
	let call;
	await saveLinks( 5, links, async ( options ) => ( call = options ) );
	assert.equal( call.method, 'PUT' );
	assert.equal( call.path, '/ohmylms/v1/courses/5/links' );
	assert.equal( call.data.links.length, 2 );
	const found = await searchCourses( 5, 'alg', async ( options ) => {
		assert.match( options.path, /exclude=5/ );
		return [ { id: 7, name: 'Algebra', status: 'publish' } ];
	} );
	assert.deepEqual( found, [
		{ id: 7, title: 'Algebra', status: 'publish' },
	] );
} );
