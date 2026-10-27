import test from 'node:test';
import assert from 'node:assert/strict';
import { questionCreator } from '../../assets/src/features/question-bank/questionCreation.mjs';

test( 'new skill questions map every part to the current skill', async () => {
	const calls = [];
	const create = questionCreator( 42, async ( options ) => {
		calls.push( options );
		return { id: 9 };
	} );
	await create( {
		name: 'A',
		settings: { parts: [ { id: 'a' }, { id: 'b' } ] },
	} );
	assert.deepEqual( calls[ 1 ].data.skill_map, {
		a: { primary: 42, supporting: [] },
		b: { primary: 42, supporting: [] },
	} );
} );
test( 'failed skill mapping retries the same question instead of creating another', async () => {
	let creations = 0,
		maps = 0;
	const create = questionCreator( 42, async ( options ) => {
		if ( options.path.endsWith( '/skills' ) ) {
			maps++;
			if ( maps === 1 ) throw Error( 'offline' );
			return {};
		}
		creations++;
		return { id: 9 };
	} );
	await assert.rejects( create( { name: 'A' } ), /offline/ );
	await create( { name: 'A' } );
	assert.equal( creations, 1 );
	assert.equal( maps, 2 );
} );
