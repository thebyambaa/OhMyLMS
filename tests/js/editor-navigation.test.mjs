import test from 'node:test';
import assert from 'node:assert/strict';
import {
	editorBackPath,
	syllabusReturnPath,
	withEditorReturn,
	nativeLessonHref,
	lessonEditorHref,
} from '../../assets/src/features/content-hub/editorNavigation.mjs';

test( 'native lesson links bypass the LMS hash route and preserve syllabus selection', () => {
	const target = syllabusReturnPath( 24, 's:12:34' );
	const href = nativeLessonHref(
		9,
		target,
		'https://example.test/subdir/wp-admin/admin.php?page=ohmylms#/course-edit/24'
	);
	const url = new URL( href );
	assert.equal( url.pathname, '/subdir/wp-admin/post.php' );
	assert.equal( url.hash, '' );
	assert.equal( url.searchParams.get( 'post' ), '9' );
	assert.equal( url.searchParams.get( 'action' ), 'edit' );
	assert.equal( url.searchParams.get( 'ohmylms_return' ), target );
	assert.equal(
		lessonEditorHref(
			{
				id: 9,
				native_editor_url:
					'https://example.test/subdir/wp-admin/post.php?post=9&action=edit',
			},
			target
		),
		href
	);
	assert.equal(
		lessonEditorHref( { id: 9 }, target ),
		`#${ withEditorReturn( '/lesson-edit/9', target ) }`
	);
} );

test( 'content editors return to the syllabus and preserve chapter or skill selection', () => {
	for ( const node of [ 'c:5', 'g:12', 's:12:34' ] ) {
		const target = syllabusReturnPath( 5, node );
		for ( const type of [ 'lesson', 'quiz', 'assignment' ] ) {
			const route = withEditorReturn( `/${ type }-edit/9`, target );
			assert.equal(
				editorBackPath( `#${ route }`, `/${ type }s` ),
				target
			);
			assert.equal(
				new URLSearchParams( target.split( '?' )[ 1 ] ).get( 'node' ),
				node
			);
		}
	}
} );

test( 'ordinary editors keep their fallback and reject unrelated return destinations', () => {
	assert.equal( withEditorReturn( '/lesson-edit/9' ), '/lesson-edit/9' );
	for ( const target of [
		'',
		'https://example.com',
		'//example.com',
		'/lesson-edit/9',
		'/content-hub/curriculum/syllabus/0',
		'/content-hub/curriculum/syllabus/5?node=invalid',
	] ) {
		assert.equal(
			editorBackPath(
				`#${ withEditorReturn( '/lesson-edit/9', target ) }`,
				'/lessons'
			),
			'/lessons'
		);
	}
} );
