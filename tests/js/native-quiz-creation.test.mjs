import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';

const source = fs
	.readFileSync( 'assets/src/features/content-hub/QuizActions.jsx', 'utf8' )
	.replace( /^import [\s\S]*?;$/gm, '' )
	.replace( /export /g, '' );
const { code } = transformSync( source, {
	configFile: false,
	babelrc: false,
	presets: [ [ '@babel/preset-react', { pragma: 'createElement' } ] ],
} );

function authoring( nativeUrl, formAuthoring = false ) {
	const calls = [];
	const location = {
		hash: '#/content-hub/quizzes',
		assign: ( url ) => calls.push( [ 'native', url ] ),
	};
	const useAddQuiz = new Function(
		'window',
		'useState',
		'__',
		'createContent',
		'editPath',
		code + '; return useAddQuiz;'
	)(
		{
			location,
			ohmylmsAssessment: { formAuthoring },
			alert: ( message ) => calls.push( [ 'error', message ] ),
		},
		( value ) => [ value, () => {} ],
		( text ) => text,
		async ( type, name ) => {
			calls.push( [ 'create', type, name ] );
			return { id: 42, native_editor_url: nativeUrl };
		},
		( type, id ) => `/quiz-edit/${ id }`
	);
	return { calls, location, useAddQuiz };
}

test( 'Add Quiz opens the native WordPress post editor returned by the quiz API', async () => {
	const url = 'http://800.local/wp-admin/post.php?post=42&action=edit';
	const view = authoring( url );
	const action = view.useAddQuiz( true );
	assert.equal( action.label, 'Add Quiz' );
	await action.onClick();
	assert.deepEqual( view.calls[ 1 ], [ 'native', url ] );
	assert.equal( view.location.hash, '#/content-hub/quizzes' );
} );

test( 'Add Quiz defaults to the native editor', async () => {
	const view = authoring( 'unused' );
	const action = view.useAddQuiz();
	assert.equal( action.label, 'Add Quiz' );
	await action.onClick();
	assert.equal( view.location.hash, '#/content-hub/quizzes' );
	assert.deepEqual( view.calls[ 1 ], [ 'native', 'unused' ] );
} );

test( 'an unavailable native editor reports the problem without silently opening another editor', async () => {
	const view = authoring();
	await view.useAddQuiz( true ).onClick();
	assert.equal( view.calls[ 1 ][ 0 ], 'error' );
	assert.match( view.calls[ 1 ][ 1 ], /draft remains/ );
	assert.equal( view.location.hash, '#/content-hub/quizzes' );
} );

test( 'retired editor arguments and rollback settings cannot restore quiz form authoring', async () => {
	const view = authoring( 'native-only', true );
	await view.useAddQuiz( false ).onClick();
	assert.deepEqual( view.calls[ 1 ], [ 'native', 'native-only' ] );
	assert.equal( view.location.hash, '#/content-hub/quizzes' );
} );
