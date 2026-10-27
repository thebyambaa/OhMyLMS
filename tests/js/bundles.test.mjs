import test from 'node:test';
import assert from 'node:assert/strict';
import {
	addMember,
	detailsDirty,
	draftFrom,
	memberKey,
	memberPayload,
	moveMember,
	publishBlocker,
	removeMember,
	sameMembers,
	validateBundle,
} from '../../assets/src/features/bundles/model.mjs';
import * as api from '../../assets/src/features/bundles/api.mjs';

const course = ( id ) => ( { type: 'course', id, title: `Course ${ id }` } );
const syllabus = ( id ) => ( {
	type: 'curriculum',
	id,
	title: `Item ${ id }`,
} );

test( 'the same course or syllabus can be a member once, and a course and an item can share an ID', () => {
	let members = addMember( [], course( 7 ) );
	members = addMember( members, course( 7 ) );
	assert.equal( members.length, 1 );
	members = addMember( members, syllabus( 7 ) );
	assert.deepEqual( members.map( memberKey ), [
		'course:7',
		'curriculum:7',
	] );
} );

test( 'members can be removed and reordered without mutating the original list', () => {
	const original = [ course( 1 ), course( 2 ), syllabus( 3 ) ];
	const moved = moveMember( original, 2, -1 );
	assert.deepEqual( moved.map( memberKey ), [
		'course:1',
		'curriculum:3',
		'course:2',
	] );
	assert.deepEqual( original.map( memberKey ), [
		'course:1',
		'course:2',
		'curriculum:3',
	] );
	assert.equal( moveMember( original, 0, -1 ), original );
	assert.equal( moveMember( original, 2, 1 ), original );
	assert.equal( moveMember( original, 9, 1 ), original );
	assert.deepEqual( removeMember( original, 'course:2' ).map( memberKey ), [
		'course:1',
		'curriculum:3',
	] );
	assert.equal( removeMember( original, 'course:99' ).length, 3 );
} );

test( 'order changes count as unsaved edits', () => {
	const saved = [ course( 1 ), course( 2 ) ];
	assert.equal( sameMembers( saved, [ course( 1 ), course( 2 ) ] ), true );
	assert.equal( sameMembers( saved, [ course( 2 ), course( 1 ) ] ), false );
	assert.equal( sameMembers( saved, [ course( 1 ) ] ), false );
} );

test( 'bundle details validate like the server and detect unsaved text', () => {
	assert.deepEqual( validateBundle( { title: '  ' } ), { title: 'required' } );
	assert.deepEqual( validateBundle( { title: 'x'.repeat( 191 ) } ), {
		title: 'too-long',
	} );
	assert.deepEqual(
		validateBundle( {
			title: 'English Exam Preparation',
			description: 'd'.repeat( 2001 ),
		} ),
		{ description: 'too-long' }
	);
	assert.deepEqual(
		validateBundle( { title: 'English Exam Preparation', description: '' } ),
		{}
	);
	const bundle = { title: 'Bundle', description: 'About' };
	const draft = draftFrom( bundle );
	assert.equal( detailsDirty( draft, bundle ), false );
	assert.equal( detailsDirty( { ...draft, title: 'Bundle ' }, bundle ), false );
	assert.equal(
		detailsDirty( { ...draft, description: 'More' }, bundle ),
		true
	);
} );

test( 'publishing is blocked until changes are saved and the bundle has members', () => {
	const bundle = { title: 'Bundle' };
	assert.equal( publishBlocker( bundle, [ course( 1 ) ], true ), 'unsaved' );
	assert.equal( publishBlocker( bundle, [], false ), 'empty' );
	assert.equal(
		publishBlocker( { title: ' ' }, [ course( 1 ) ], false ),
		'title'
	);
	assert.equal( publishBlocker( bundle, [ course( 1 ) ], false ), null );
} );

test( 'the members payload carries only type and ID, in order', () => {
	assert.deepEqual( memberPayload( [ course( 4 ), syllabus( 2 ) ] ), [
		{ type: 'course', id: 4 },
		{ type: 'curriculum', id: 2 },
	] );
} );

test( 'API calls use the bundle routes with the documented payloads', async () => {
	const calls = [];
	const fetch = ( args ) => {
		calls.push( args );
		return Promise.resolve( {} );
	};
	await api.listBundles( fetch );
	await api.loadBundle( 3, fetch );
	await api.createBundle( { title: 'T', description: '' }, fetch );
	await api.updateBundle( 3, { title: 'U' }, fetch );
	await api.setMembers(
		3,
		[ { type: 'course', id: 1 } ],
		'2026-01-01 00:00:00',
		fetch
	);
	await api.setMembers( 3, [], undefined, fetch );
	await api.publishBundle( 3, true, fetch );
	await api.publishBundle( 3, false, fetch );
	await api.deleteBundle( 3, false, fetch );
	await api.deleteBundle( 3, true, fetch );
	await api.searchMembers( 'curriculum', 'math', fetch );
	assert.deepEqual(
		calls,
		[
			{ path: '/ohmylms/v1/bundles' },
			{ path: '/ohmylms/v1/bundles/3' },
			{
				path: '/ohmylms/v1/bundles',
				method: 'POST',
				data: { title: 'T', description: '' },
			},
			{
				path: '/ohmylms/v1/bundles/3',
				method: 'PUT',
				data: { title: 'U' },
			},
			{
				path: '/ohmylms/v1/bundles/3/items',
				method: 'PUT',
				data: {
					items: [ { type: 'course', id: 1 } ],
					expected_updated_at: '2026-01-01 00:00:00',
				},
			},
			{
				path: '/ohmylms/v1/bundles/3/items',
				method: 'PUT',
				data: { items: [] },
			},
			{
				path: '/ohmylms/v1/bundles/3/publish',
				method: 'POST',
				data: { published: true },
			},
			{
				path: '/ohmylms/v1/bundles/3/publish',
				method: 'POST',
				data: { published: false },
			},
			{
				path: '/ohmylms/v1/bundles/3',
				method: 'DELETE',
				data: { force: false },
			},
			{
				path: '/ohmylms/v1/bundles/3',
				method: 'DELETE',
				data: { force: true },
			},
			{
				path: '/ohmylms/v1/bundles/targets?type=curriculum&search=math',
				method: undefined,
			},
		].map( ( call ) =>
			call.method === undefined ? { path: call.path } : call
		)
	);
} );
