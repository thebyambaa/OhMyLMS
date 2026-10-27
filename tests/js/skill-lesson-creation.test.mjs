import test from 'node:test';
import assert from 'node:assert/strict';
import { skillLessonCreator } from '../../assets/src/features/curriculum/skillLessonCreation.mjs';

test( 'creates a text draft and links only that lesson to the selected skill before returning', async () => {
	const calls = [];
	const create = skillLessonCreator( 42, async ( options ) => {
		calls.push( options );
		return options.method === 'POST' ? { id: 123 } : { skill_ids: [ 42 ] };
	} );
	assert.deepEqual( await create( '  New lesson  ' ), { id: 123 } );
	assert.deepEqual( calls, [
		{
			path: '/ohmylms/v1/lessons',
			method: 'POST',
			data: { name: 'New lesson', status: 'draft', type: 'text' },
		},
		{
			path: '/ohmylms/v1/content-hub/lessons/123/skills',
			method: 'PUT',
			data: { skill_ids: [ 42 ] },
		},
	] );
} );

test( 'a failed link keeps the draft and retries linking without creating another lesson', async () => {
	let creations = 0;
	let links = 0;
	const create = skillLessonCreator( 42, async ( options ) => {
		if ( options.method === 'POST' ) {
			creations++;
			return { id: 123 };
		}
		if ( ++links === 1 ) throw new Error( 'Link failed' );
		return { skill_ids: [ 42 ] };
	} );
	await assert.rejects( create( 'New lesson' ), /Link failed/ );
	assert.deepEqual( await create( 'New lesson' ), { id: 123 } );
	assert.equal( creations, 1 );
	assert.equal( links, 2 );
} );

test( 'a failed creation never attempts linking and can be retried', async () => {
	let attempts = 0;
	let links = 0;
	const create = skillLessonCreator( 42, async ( options ) => {
		if ( options.method === 'POST' ) {
			if ( ++attempts === 1 ) throw new Error( 'Creation failed' );
			return { id: 123 };
		}
		links++;
		return { skill_ids: [ 42 ] };
	} );
	await assert.rejects( create( 'New lesson' ), /Creation failed/ );
	assert.equal( links, 0 );
	await create( 'New lesson' );
	assert.equal( attempts, 2 );
	assert.equal( links, 1 );
} );
