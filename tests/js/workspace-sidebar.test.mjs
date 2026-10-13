import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';
import * as workspace from '../../assets/src/features/curriculum/workspace.mjs';
import * as syllabus from '../../assets/src/features/curriculum/syllabus.mjs';
import { skillText } from '../../assets/src/features/curriculum/skillTranslations.mjs';
import { categoryAppearance } from '../../assets/src/features/curriculum/categoryAppearance.mjs';

const source = fs
	.readFileSync(
		'assets/src/features/curriculum/WorkspaceSidebar.jsx',
		'utf8'
	)
	.replace(
		/import\s*\{([^}]+)\}\s*from\s*'([^']+)';/g,
		( _, names, module ) =>
			`const {${ names }} = runtime[${ JSON.stringify( module ) }];`
	)
	.replace( /export /g, '' );
const { code } = transformSync( source, {
	configFile: false,
	babelrc: false,
	presets: [ [ '@babel/preset-react', { pragma: 'createElement' } ] ],
} );

function fixture() {
	const state = [];
	let cursor = 0;
	const runtime = {
		'@wordpress/element': {
			createElement: ( type, props, ...children ) => ( {
				type,
				props,
				children,
			} ),
			useState: ( initial ) => {
				const index = cursor++;
				if ( ! ( index in state ) ) {
					state[ index ] = initial;
				}
				return [
					state[ index ],
					( value ) => {
						state[ index ] = value;
					},
				];
			},
		},
		'@wordpress/i18n': {
			__: ( value ) => value,
			_n: ( one, many, count ) => ( count === 1 ? one : many ),
			sprintf: ( format, value ) => format.replace( /%[sd]/, value ),
		},
		'@wordpress/components': { Dashicon: 'icon' },
		'./AddContentMenu': { AddContentMenu: 'add' },
		'./WorkspaceParts': { QuickAdd: 'quick-add', KindIcon: 'kind-icon' },
		'./workspace.mjs': workspace,
		'./syllabus.mjs': syllabus,
		'./skillTranslations.mjs': { skillText },
		'./categoryAppearance.mjs': { categoryAppearance },
	};
	const Sidebar = new Function(
		'runtime',
		code + ';return WorkspaceSidebar;'
	)( runtime );
	const tree = workspace.buildOutline( {
		contents: [
			{ id: 1, name: 'Syllabus', depth: 0, groups: [] },
			{ id: 2, parent_id: 1, name: 'Parent', depth: 1, groups: [] },
			{
				id: 3,
				parent_id: 2,
				name: 'Child',
				depth: 2,
				groups: [ { id: 8, name: 'Group', skills: [] } ],
			},
			{ id: 4, parent_id: 1, name: 'Other', depth: 1, groups: [] },
		],
	} );
	const calls = [];
	const render = () => {
		cursor = 0;
		const view = Sidebar( {
			tree,
			open: new Set(),
			settings: {},
			onReorder: ( ...args ) => calls.push( args ),
		} );
		const body = view.children[ 1 ];
		const branch = body.children[ 2 ].children[ 0 ][ 0 ];
		return { body, drag: branch.props.drag };
	};
	const event = ( clientY = 120 ) => ( {
		clientY,
		currentTarget: {
			getBoundingClientRect: () => ( { top: 100, height: 40 } ),
		},
		preventDefault() {
			this.prevented = true;
		},
		stopPropagation() {},
		dataTransfer: { setData() {} },
	} );
	const start = ( key ) => {
		render().drag.start( event(), tree.index.get( key ) );
	};
	return { render, start, event, tree, calls };
}

test( 'dropping a nested folder on the blank outline moves it to the syllabus root', () => {
	const f = fixture();
	f.start( 'c:3' );
	const view = f.render();
	const over = f.event();
	view.body.props.onDragOver( over );
	assert.equal( over.prevented, true );
	const highlighted = f.render();
	assert.match(
		highlighted.body.children[ 0 ].props.className,
		/is-drop-inside/
	);
	highlighted.body.props.onDrop( f.event() );
	assert.deepEqual( f.calls, [ [ 'c:3', 'c:1', 'inside' ] ] );
	assert.equal( f.render().body.children[ 0 ], '' );
} );

test( 'folder row centers nest folders while row edges place them alongside their parent', () => {
	const f = fixture();
	f.start( 'c:3' );
	f.render().drag.drop( f.event( 120 ), f.tree.index.get( 'c:4' ) );
	f.start( 'c:3' );
	f.render().drag.drop( f.event( 139 ), f.tree.index.get( 'c:2' ) );
	assert.deepEqual( f.calls, [
		[ 'c:3', 'c:4', 'inside' ],
		[ 'c:3', 'c:2', true ],
	] );
} );

test( 'folder drag handlers reject cycles and allow groups to leave their topic', () => {
	const f = fixture();
	f.start( 'c:2' );
	f.render().drag.drop( f.event(), f.tree.index.get( 'c:3' ) );
	assert.deepEqual( f.calls, [] );
	f.start( 'g:8' );
	f.render().body.props.onDrop( f.event() );
	assert.deepEqual( f.calls, [ [ 'g:8', 'c:1', 'inside' ] ] );
} );
