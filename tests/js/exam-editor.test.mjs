import test from 'node:test';
import assert from 'node:assert/strict';
import { examEditorHref } from '../../assets/src/features/content-hub/examEditor.mjs';

globalThis.window = {
	location: { href: 'http://site.test/wp-admin/admin.php?page=ohmylms' },
};

test( 'an exam opens its quiz in the block editor and returns to the Exams tab', () => {
	const href = examEditorHref( {
		editor_url: 'http://site.test/wp-admin/post.php?post=12&action=edit',
	} );
	const url = new URL( href );
	assert.equal( url.pathname, '/wp-admin/post.php' );
	assert.equal( url.searchParams.get( 'post' ), '12' );
	assert.equal(
		url.searchParams.get( 'ohmylms_return' ),
		'/content-hub/exams'
	);
} );

test( 'exams without an editor link have no address', () => {
	assert.equal( examEditorHref( { editor_url: '' } ), '' );
	assert.equal( examEditorHref( null ), '' );
} );
