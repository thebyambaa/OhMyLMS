import test from 'node:test';
import assert from 'node:assert/strict';
import { exportQuestionRows } from '../../assets/src/features/content-hub/api.mjs';

test( 'a quiz export asks the typed export route for that quiz in the chosen format', async () => {
	let call;
	await exportQuestionRows(
		{ quizId: 12, format: 'csv', label: 'Midterm' },
		( options ) => {
			call = options;
			return Promise.resolve( {} );
		}
	);
	assert.deepEqual( call, {
		path: '/ohmylms/v1/questions/export',
		method: 'POST',
		data: { format: 'csv', quiz_id: 12, label: 'Midterm' },
	} );
} );

test( 'chosen questions are sent as an ID list, JSON by default', async () => {
	let call;
	await exportQuestionRows( { ids: [ 5, 6, 7 ] }, ( options ) => {
		call = options;
		return Promise.resolve( {} );
	} );
	assert.deepEqual( call.data, { format: 'json', ids: [ 5, 6, 7 ] } );
} );
