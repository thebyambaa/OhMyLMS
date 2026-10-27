import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';

const source = fs
	.readFileSync( 'assets/src/features/learning/LessonContent.jsx', 'utf8' )
	.replace( /^import [\s\S]*?;$/gm, '' )
	.replace( 'export function', 'function' );
const { code } = transformSync( source, {
	configFile: false,
	babelrc: false,
	presets: [ [ '@babel/preset-react', { pragma: 'createElement' } ] ],
} );
const createElement = ( type, props, ...children ) => ( {
	type,
	props,
	children,
} );

function renderContent( props, customContent ) {
	const createLessonContent = new Function(
		'createElement',
		'window',
		`${ code }; return createLessonContent;`
	)( createElement, {
		ohmylms: { extensions: { lessonEditor: () => customContent } },
	} );
	const Content = createLessonContent( () => ( {
		Pr: 'content-form',
		React: { Fragment: 'fragment' },
		b: { __: ( text ) => text },
	} ) );
	const wrapper = Content( props );
	return wrapper.type( wrapper.props );
}

test( 'custom lesson editors retain their content and settings without embedded Gutenberg', () => {
	const custom = { type: 'custom-editor' };
	const settings = { type: 'lesson-settings' };
	const tree = renderContent(
		{ lesson: { id: 9, type: 'custom' }, settings },
		custom
	);
	assert.deepEqual( tree.children, [ custom, settings ] );
} );

test( 'legacy fallback keeps title, content and media handlers for custom lessons', () => {
	const onTitle = () => {};
	const onContent = () => {};
	const settings = { type: 'lesson-settings' };
	const tree = renderContent(
		{
			lesson: {
				id: 9,
				name: 'Custom lesson',
				description: '<p>Body</p>',
				type: 'custom',
			},
			handleInputChange: onTitle,
			handleEditorContentChange: onContent,
			settings,
		},
		null
	);
	const form = tree.children[ 0 ];
	assert.equal( form.type, 'content-form' );
	assert.equal( form.props.titleValue, 'Custom lesson' );
	assert.equal( form.props.content, '<p>Body</p>' );
	assert.equal( form.props.onInputChange, onTitle );
	assert.equal( form.props.onContentChange, onContent );
	assert.equal( tree.children[ 1 ], settings );
} );
