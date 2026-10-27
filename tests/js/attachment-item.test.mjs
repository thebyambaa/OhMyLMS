import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';
import { courseEditPath } from '../../assets/src/features/content-hub/hubRoutes.mjs';
import {
	withEditorReturn,
	lessonEditorHref,
} from '../../assets/src/features/content-hub/editorNavigation.mjs';

const createElement = ( type, props, ...children ) => ( {
	type,
	props: props || {},
	children: children
		.flat( Infinity )
		.filter(
			( child ) =>
				child !== null && child !== undefined && child !== false
		),
} );
const stub = ( name ) => Object.assign( () => null, { stubName: name } );
const Button = stub( 'Button' );
const CheckboxControl = stub( 'CheckboxControl' );
const TextControl = stub( 'TextControl' );
const stubs = new Set( [ Button, CheckboxControl, TextControl ] );

const sprintf = ( format, ...args ) => {
	let next = 0;
	return String( format ).replace(
		/%(?:(\d+)\$)?[sd]/g,
		( _, position ) => args[ position ? Number( position ) - 1 : next++ ]
	);
};
const scope = {
	createElement,
	__: ( text ) => text,
	sprintf,
	Button,
	CheckboxControl,
	TextControl,
	courseEditPath,
	withEditorReturn,
	lessonEditorHref,
	editPath: ( type, id ) => `/${ type }-edit/${ id }`,
};
const { code } = transformSync(
	fs.readFileSync(
		'assets/src/features/content-hub/AttachmentItem.jsx',
		'utf8'
	),
	{
		configFile: false,
		babelrc: false,
		presets: [ [ '@babel/preset-react', { pragma: 'createElement' } ] ],
		plugins: [
			() => ( {
				visitor: {
					ImportDeclaration( path ) {
						path.remove();
					},
					ExportNamedDeclaration( path ) {
						path.replaceWith( path.node.declaration );
					},
				},
			} ),
		],
	}
);
const { AttachmentItem } = new Function(
	...Object.keys( scope ),
	`${ code }; return { AttachmentItem };`
)( ...Object.values( scope ) );

/** Render user components into plain elements, leaving the stubbed controls in place. */
function expand( node ) {
	if ( node === null || node === undefined || typeof node !== 'object' )
		return node;
	if ( Array.isArray( node ) ) return node.map( expand );
	if ( typeof node.type === 'function' && ! stubs.has( node.type ) ) {
		return expand(
			node.type( { ...node.props, children: node.children } )
		);
	}
	return { ...node, children: node.children.map( expand ) };
}
const walk = ( node, visit ) => {
	if ( ! node || typeof node !== 'object' ) return;
	visit( node );
	( node.children || [] ).forEach( ( child ) => walk( child, visit ) );
};
const find = ( tree, predicate ) => {
	const hits = [];
	walk( tree, ( node ) => predicate( node ) && hits.push( node ) );
	return hits;
};
const text = ( node ) =>
	typeof node === 'string'
		? node
		: node && typeof node === 'object'
			? ( node.children || [] ).map( text ).join( '' )
			: '';

const skills = [
	{ term_id: 1, name: 'Count to 100' },
	{ term_id: 2, name: 'Skip count' },
];
const item = ( over ) => ( {
	id: 'x',
	type: 'lesson',
	content_id: 5,
	title: 'Counting lesson',
	status: 'publish',
	required: false,
	pass_percent: null,
	chapter_id: 10,
	skill_ids: [],
	also_in: [],
	...over,
} );
const attachments = [
	item( { id: 'a', content_id: 5, title: 'Counting lesson' } ),
	item( {
		id: 'b',
		type: 'quiz',
		content_id: 6,
		title: 'Counting checkpoint',
		status: 'draft',
		required: true,
		pass_percent: 70,
		skill_ids: [ 1, 2 ],
		also_in: [ { id: 99, title: 'Grade 3 Math' } ],
	} ),
];
const calls = [];
const record =
	( name ) =>
	( ...args ) =>
		calls.push( [ name, ...args ] );
const actions = Object.fromEntries(
	[ 'updateAttachment', 'removeAttachment' ].map( ( name ) => [
		name,
		record( name ),
	] )
);

test( 'a syllabus lesson title links directly to Gutenberg without a hash redirect', () => {
	const returnTo = '/content-hub/curriculum/syllabus/24?node=c%3A10';
	const lesson = expand(
		createElement( AttachmentItem, {
			item: item( {
				native_editor_url:
					'https://example.test/wp-admin/post.php?post=5&action=edit',
			} ),
			skills,
			actions,
			returnTo,
		} )
	);
	const [ link ] = find( lesson, ( node ) => node.type === 'a' );
	const url = new URL( link.props.href );
	assert.equal( url.pathname, '/wp-admin/post.php' );
	assert.equal( url.hash, '' );
	assert.equal( url.searchParams.get( 'post' ), '5' );
	assert.equal( url.searchParams.get( 'ohmylms_return' ), returnTo );
} );

test( 'Attachments say whether they are published, required, graded and used elsewhere', () => {
	const quiz = expand(
		createElement( AttachmentItem, {
			item: attachments[ 1 ],
			skills,
			actions,
		} )
	);
	assert.match( text( quiz ), /Quiz/ );
	assert.match( text( quiz ), /Not published/ );
	assert.match( text( quiz ), /Covers 2 skills/ );
	assert.match( text( quiz ), /Also in Grade 3 Math/ );
	const link = find(
		quiz,
		( n ) => n.type === 'a' && n.props.href === `#${ courseEditPath( 99 ) }`
	);
	assert.equal(
		link.length,
		1,
		'it links to the other course, which opens in its editor'
	);
	assert.equal(
		find( quiz, ( n ) => n.props?.href === '#/quiz-edit/6' ).length,
		1,
		'the title opens the quiz editor'
	);
	assert.equal(
		find( quiz, ( n ) => n.type === TextControl ).length,
		1,
		'only a quiz has a pass percentage'
	);
	const lesson = expand(
		createElement( AttachmentItem, {
			item: attachments[ 0 ],
			skills,
			actions,
		} )
	);
	assert.equal( find( lesson, ( n ) => n.type === TextControl ).length, 0 );
	assert.doesNotMatch( text( lesson ), /Not published/ );
	const missing = expand(
		createElement( AttachmentItem, {
			item: item( { status: 'missing' } ),
			skills,
			actions,
		} )
	);
	assert.match( text( missing ), /Unavailable/ );
} );

test( 'Editing a pass percentage clamps it and toggling required reports the change', () => {
	calls.length = 0;
	const quiz = expand(
		createElement( AttachmentItem, {
			item: attachments[ 1 ],
			skills,
			actions,
		} )
	);
	find( quiz, ( n ) => n.type === TextControl )[ 0 ].props.onChange( '250' );
	find( quiz, ( n ) => n.type === CheckboxControl )[ 0 ].props.onChange(
		false
	);
	find(
		quiz,
		( n ) => n.type === Button && text( n ) === 'Remove'
	)[ 0 ].props.onClick();
	assert.deepEqual( calls, [
		[ 'updateAttachment', 'b', { pass_percent: 100 } ],
		[ 'updateAttachment', 'b', { required: false } ],
		[ 'removeAttachment', 'b' ],
	] );
} );
