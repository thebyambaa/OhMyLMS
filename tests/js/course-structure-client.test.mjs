import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const source = fs.readFileSync( new URL( '../../assets/src/features/courses/structureClient.js', import.meta.url ), 'utf8' );
const { createStructureMiddleware } = await import( `data:text/javascript;base64,${ Buffer.from( source ).toString( 'base64' ) }` );
const reply = ( data, revision = 'first', course = '10', status = 200 ) => new Response( status === 204 ? null : JSON.stringify( data ), {
	status,
	headers: { 'Content-Type': 'application/json', 'X-OhMyLMS-Structure-Revision': revision, 'X-OhMyLMS-Structure-Course': course },
} );

test( 'apiFetch rejected raw responses retain parsed error details', async () => {
	const middleware = createStructureMiddleware();
	const failure = { code: 'ohmylms_program_stale', message: 'Reload before saving.', data: { status: 409 } };
	await assert.rejects( middleware( { path: '/ohmylms/v1/courses/10/chapters', method: 'POST' }, async () => {
		throw reply( failure, 'newer', '10', 409 );
	} ), failure );
	const response = reply( failure, 'newer', '10', 409 );
	await assert.rejects( middleware( { path: '/ohmylms/v1/courses/10/chapters', method: 'POST', parse: false }, async () => {
		throw response;
	} ), ( error ) => error === response );
} );

test( 'a course read supplies unit-write tokens and successful writes advance them', async () => {
	const middleware = createStructureMiddleware();
	const read = await middleware( { path: '/ohmylms/v1/courses/10' }, async () => reply( { id: 10, chapters: [ { id: 20 } ] } ) );
	assert.equal( read.id, 10 );
	await middleware( { path: '/ohmylms/v1/chapters/20/content', method: 'POST', data: [] }, async ( options ) => {
		assert.equal( options.headers[ 'x-ohmylms-structure-revision' ], 'first' );
		assert.equal( options.parse, false );
		return reply( { status: 'success' }, 'second' );
	} );
	await middleware( { path: '/ohmylms/v1/courses/10/chapters', method: 'POST', data: [] }, async ( options ) => {
		assert.equal( options.headers[ 'x-ohmylms-structure-revision' ], 'second' );
		return reply( { status: 'success' }, 'third' );
	} );
} );

test( 'stale writes are not retried or silently rebased', async () => {
	const middleware = createStructureMiddleware();
	await middleware( { path: '/ohmylms/v1/courses/10/structure' }, async () => reply( { units: [ { id: 20 } ] } ) );
	let calls = 0;
	await assert.rejects( middleware( { path: '/ohmylms/v1/chapters/20', method: 'PUT', data: { name: 'Old edit' } }, async () => {
		++calls;
		return reply( { message: 'Reload before saving.' }, 'newer', '10', 409 );
	} ), { message: 'Reload before saving.' } );
	assert.equal( calls, 1 );
	await middleware( { path: '/ohmylms/v1/chapters/20', method: 'PUT' }, async ( options ) => {
		assert.equal( options.headers[ 'x-ohmylms-structure-revision' ], 'first' );
		return reply( {} );
	} );
} );

test( 'explicit snapshots, raw responses and unrelated requests retain their contracts', async () => {
	const middleware = createStructureMiddleware();
	await middleware( { path: '/ohmylms/v1/courses/10/learning' }, async () => reply( { chapters: [ { id: 20 } ] } ) );
	await middleware( { path: '/ohmylms/v1/courses/10/learning', method: 'PUT', data: { revision: 'explicit' } }, async ( options ) => {
		assert.equal( options.headers[ 'x-ohmylms-structure-revision' ], undefined );
		return reply( {} );
	} );
	const raw = await middleware( { path: '/ohmylms/v1/courses/10', parse: false }, async () => reply( { id: 10 } ) );
	assert.ok( raw instanceof Response );
	const unrelated = { path: '/ohmylms/v1/question-bank', method: 'POST', data: {} };
	await middleware( unrelated, async ( options ) => { assert.equal( options, unrelated ); return true; } );
} );

test( 'an unread course is not assigned a guessed token and tabs keep separate snapshots', async () => {
	const first = createStructureMiddleware();
	const second = createStructureMiddleware();
	await first( { path: '/ohmylms/v1/courses/10' }, async () => reply( { chapters: [] } ) );
	await second( { path: '/ohmylms/v1/courses/10/chapters', method: 'POST' }, async ( options ) => {
		assert.equal( options.headers[ 'x-ohmylms-structure-revision' ], undefined );
		return reply( {} );
	} );
} );

test( 'new courses and units seed tokens without an extra request; empty responses stay empty', async () => {
	const middleware = createStructureMiddleware();
	await middleware( { path: '/ohmylms/v1/courses', method: 'POST' }, async () => reply( { id: 10 } ) );
	await middleware( { path: '/ohmylms/v1/chapters', method: 'POST', data: { course_id: 10 } }, async ( options ) => {
		assert.equal( options.headers[ 'x-ohmylms-structure-revision' ], 'first' );
		return reply( { id: 20 }, 'second' );
	} );
	const result = await middleware( { path: '/ohmylms/v1/chapters/20', method: 'DELETE' }, async ( options ) => {
		assert.equal( options.headers[ 'x-ohmylms-structure-revision' ], 'second' );
		return new Response( null, { status: 204 } );
	} );
	assert.equal( result, null );
} );
