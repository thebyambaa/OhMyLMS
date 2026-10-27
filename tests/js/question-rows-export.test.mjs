import test from 'node:test';
import assert from 'node:assert/strict';
import { exportQuestionRows } from '../../assets/src/features/content-hub/api.mjs';

test( 'a quiz export asks for that quiz in the chosen format', async () => {
	let path = '';
	await exportQuestionRows(
		{ quizId: 12, format: 'csv', label: 'Midterm' },
		( options ) => {
			path = options.path;
			return Promise.resolve( {} );
		}
	);
	const url = new URL( path, 'http://site.test' );
	assert.equal( url.pathname, '/ohmylms/v1/question-bank/export' );
	assert.equal( url.searchParams.get( 'quiz_id' ), '12' );
	assert.equal( url.searchParams.get( 'format' ), 'csv' );
	assert.equal( url.searchParams.get( 'label' ), 'Midterm' );
	assert.equal( url.searchParams.has( 'ids' ), false );
} );

test( 'chosen questions are sent as a comma-separated list, JSON by default', async () => {
	let path = '';
	await exportQuestionRows( { ids: [ 5, 6, 7 ] }, ( options ) => {
		path = options.path;
		return Promise.resolve( {} );
	} );
	const url = new URL( path, 'http://site.test' );
	assert.equal( url.searchParams.get( 'ids' ), '5,6,7' );
	assert.equal( url.searchParams.get( 'format' ), 'json' );
	assert.equal( url.searchParams.has( 'quiz_id' ), false );
} );
