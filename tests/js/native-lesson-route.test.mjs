import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';
import { editorBackPath } from '../../assets/src/features/content-hub/editorNavigation.mjs';

const source = fs
	.readFileSync(
		'assets/src/features/learning/NativeLessonRoute.jsx',
		'utf8'
	)
	.replace( /^import [\s\S]*?;$/gm, '' )
	.replace( 'export function', 'function' );
const { code } = transformSync( source, {
	configFile: false,
	babelrc: false,
	presets: [ [ '@babel/preset-react', { pragma: 'createElement' } ] ],
} );

async function openLesson( hash, chapterId, data ) {
	let effect;
	let destination;
	const scope = {
		createElement: () => null,
		useState: ( initial ) => [ initial, () => {} ],
		useEffect: ( callback ) => {
			effect = callback;
		},
		apiFetch: async ( request ) => {
			assert.equal( request.path, '/ohmylms/v1/lessons/9' );
			return data;
		},
		Notice: 'notice',
		Spinner: 'spinner',
		editorBackPath,
		window: {
			location: {
				hash,
				href: `https://example.test/wp-admin/admin.php?page=ohmylms${ hash }`,
				replace: ( url ) => {
					destination = url;
				},
			},
		},
	};
	const Route = new Function(
		...Object.keys( scope ),
		`${ code }; return NativeLessonRoute;`
	)( ...Object.values( scope ) );
	Route( { id: 9, chapterId } );
	effect();
	await Promise.resolve();
	return destination && new URL( destination );
}

test( 'course lesson dialogs open the block editor directly and return to the course', async () => {
	const url = await openLesson( '#/course-edit/5', 12, {
		native_editor_url: '/wp-admin/post.php?post=9&action=edit',
	} );
	assert.equal( url.pathname, '/wp-admin/post.php' );
	assert.equal( url.searchParams.get( 'post' ), '9' );
	assert.equal( url.searchParams.get( 'ohmylms_return' ), '/course-edit/5' );
} );

test( 'syllabus lessons open directly and retain the selected skill', async () => {
	const target = '/content-hub/curriculum/syllabus/24?node=s%3A1';
	const url = await openLesson(
		`#/lesson-edit/9?returnTo=${ encodeURIComponent( target ) }`,
		undefined,
		{ native_editor_url: '/wp-admin/post.php?post=9&action=edit' }
	);
	assert.equal( url.searchParams.get( 'ohmylms_return' ), target );
} );

test( 'custom lesson types keep their extension editor', async () => {
	assert.equal( await openLesson( '#/course-edit/5', 12, {} ), undefined );
} );
