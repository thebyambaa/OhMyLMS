import assert from 'node:assert/strict';
import test from 'node:test';
import { openBankQuestion } from '../../assets/src/features/question-bank/api.mjs';

test( 'reads of one question while the editor opens share a request', async () => {
	const calls = [];
	const fetch = async ( options ) => {
		calls.push( options.path );
		return { id: 7, name: 'Shared' };
	};
	const [ first, second ] = await Promise.all( [
		openBankQuestion( 7, fetch, 1000 ),
		openBankQuestion( 7, fetch, 3000 ),
	] );
	assert.equal( calls.length, 1 );
	assert.equal( first.name, 'Shared' );
	assert.equal( first, second );

	// Another question, and the same one later, are read again.
	await openBankQuestion( 8, fetch, 3000 );
	await openBankQuestion( 7, fetch, 7000 );
	assert.deepEqual( calls, [
		'/ohmylms/v1/questions/7',
		'/ohmylms/v1/questions/8',
		'/ohmylms/v1/questions/7',
	] );
} );

test( 'a failed read is not kept', async () => {
	let attempts = 0;
	const fetch = async () => {
		attempts++;
		if ( 1 === attempts ) {
			throw new Error( 'offline' );
		}
		return { id: 9 };
	};
	await assert.rejects( openBankQuestion( 9, fetch, 1000 ), /offline/ );
	assert.equal( ( await openBankQuestion( 9, fetch, 1200 ) ).id, 9 );
	assert.equal( attempts, 2 );
} );

test( 'a question embedded in the page replaces its first request only', async () => {
	const calls = [];
	const fetch = async ( options ) => {
		calls.push( options.path );
		return { id: 11, name: 'Fetched' };
	};
	globalThis.ohmylmsEditorQuestions = {
		11: { id: 11, name: 'Embedded', permissions: { can_edit: true } },
	};
	const first = await openBankQuestion( 11, fetch, 1000 );
	assert.equal( first.name, 'Embedded' );
	assert.equal( first.can_edit, true );
	assert.deepEqual( calls, [] );
	assert.deepEqual( globalThis.ohmylmsEditorQuestions, {} );
	const later = await openBankQuestion( 11, fetch, 9000 );
	assert.equal( later.name, 'Fetched' );
	delete globalThis.ohmylmsEditorQuestions;
} );
