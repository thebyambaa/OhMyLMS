import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';
import { buildMindmapTree } from '../../assets/src/extensions/mindmap/tree.mjs';

const items = [
	{ id: 'math', label: 'Mathematics' },
	{ id: 'algebra', parent: 'math', label: 'Algebra' },
	{ id: 'linear', parent: 'algebra', label: 'Linear equations' },
	{ id: 'science', label: 'Science' },
];
test( 'mindmap builds hierarchies and safely retains orphaned and cyclic records', () => {
	const tree = buildMindmapTree( items );
	assert.equal( tree[ 0 ].children[ 0 ].children[ 0 ].id, 'linear' );
	assert.equal( tree[ 1 ].id, 'science' );
	assert.equal( items[ 0 ].children, undefined );
	const broken = buildMindmapTree( [
		{ id: 1, parent: 2 },
		{ id: 2, parent: 1 },
		{ id: 3, parent: 99 },
	] );
	assert.deepEqual(
		broken.map( ( node ) => node.id ),
		[ 1, 2, 3 ]
	);
} );

const { code } = transformSync(
	fs.readFileSync( 'assets/src/extensions/mindmap/Mindmap.jsx', 'utf8' ),
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
const element = ( type, props, ...children ) => ( {
	type,
	props: props || {},
	children,
} );
function flatten( node ) {
	return ! node || typeof node !== 'object'
		? []
		: Array.isArray( node )
			? node.flatMap( flatten )
			: [ node, ...node.children.flatMap( flatten ) ];
}
function render( props, collapsed = new Set(), instanceId = 'mindmap-test' ) {
	let nextCollapsed;
	const Component = new Function(
		'createElement',
		'Fragment',
		'useState',
		'useId',
		'__',
		'sprintf',
		'buildMindmapTree',
		`${ code }; return Mindmap;`
	)(
		element,
		'Fragment',
		() => [
			collapsed,
			( value ) => {
				nextCollapsed =
					typeof value === 'function' ? value( collapsed ) : value;
			},
		],
		() => instanceId,
		( text ) => text,
		( text, name ) => text.replace( '%s', name ),
		buildMindmapTree
	);
	return {
		nodes: flatten(
			Component( {
				items,
				title: 'Mindmap',
				rootLabel: 'Topics',
				...props,
			} )
		),
		getCollapsed: () => nextCollapsed,
	};
}
test( 'selection labels target unique controls across multiple mindmap instances', () => {
	const first = render(
		{ onSelectionChange() {} },
		new Set(),
		'first-mindmap'
	);
	const second = render(
		{ onSelectionChange() {} },
		new Set(),
		'second-mindmap'
	);
	const controls = [ ...first.nodes, ...second.nodes ].filter(
		( node ) => node.type === 'input'
	);
	assert.equal(
		new Set( controls.map( ( node ) => node.props.id ) ).size,
		controls.length
	);
	for ( const instance of [ first, second ] ) {
		for ( const label of instance.nodes.filter(
			( node ) => node.type === 'label'
		) ) {
			assert.ok(
				instance.nodes.some(
					( node ) =>
						node.type === 'input' &&
						node.props.id === label.props.htmlFor
				)
			);
		}
	}
	assert.equal(
		render( {} ).nodes.some( ( node ) => node.type === 'label' ),
		false
	);
} );

test( 'membership inheritance highlights descendants while independent selection works for skills', () => {
	let next;
	const selected = Object.freeze( [ 'math' ] );
	const inherited = render( {
		selected,
		includeDescendants: true,
		onSelectionChange: ( ids ) => {
			next = ids;
		},
	} );
	const inputs = inherited.nodes.filter( ( node ) => node.type === 'input' );
	assert.deepEqual(
		inputs.map( ( node ) => [ node.props.checked, node.props.disabled ] ),
		[
			[ true, false ],
			[ true, true ],
			[ true, true ],
			[ false, false ],
		]
	);
	inputs[ 0 ].props.onChange();
	assert.deepEqual( next, [] );
	assert.deepEqual( selected, [ 'math' ] );
	const independent = render( {
		selected,
		onSelectionChange: ( ids ) => {
			next = ids;
		},
	} );
	const child = independent.nodes.filter(
		( node ) => node.type === 'input'
	)[ 1 ];
	assert.equal( child.props.checked, false );
	child.props.onChange();
	assert.deepEqual( next, [ 'math', 'algebra' ] );
} );
test( 'mindmap supports branch collapse, read-only browsing and custom node details', () => {
	const map = render( {
		renderNodeDetails: ( node ) => element( 'aside', {}, node.id ),
	} );
	assert.equal(
		map.nodes.filter( ( node ) => node.type === 'input' ).length,
		0
	);
	assert.equal(
		map.nodes.filter( ( node ) => node.type === 'aside' ).length,
		4
	);
	map.nodes
		.find(
			( node ) => node.props[ 'aria-label' ] === 'Collapse Mathematics'
		)
		.props.onClick();
	assert.deepEqual( [ ...map.getCollapsed() ], [ 'math' ] );
	const collapsed = render( {}, map.getCollapsed() );
	assert.equal(
		collapsed.nodes.filter( ( node ) => node.type === 'aside' ).length,
		0
	);
	assert.equal(
		collapsed.nodes.some(
			( node ) =>
				node.type === 'span' && node.children.includes( 'Algebra' )
		),
		false
	);
	assert.equal(
		collapsed.nodes.find(
			( node ) => node.props[ 'aria-label' ] === 'Expand Mathematics'
		).props[ 'aria-expanded' ],
		false
	);
} );

test( 'hidden membership nodes preserve selection inheritance and visible descendants', () => {
	let next;
	const map = render( {
		selected: [ 'algebra' ],
		includeDescendants: true,
		hideNode: ( node ) => node.id === 'algebra',
		onSelectionChange: ( ids ) => {
			next = ids;
		},
	} );
	assert.equal(
		map.nodes.some(
			( node ) =>
				node.type === 'span' && node.children.includes( 'Algebra' )
		),
		false
	);
	assert.ok(
		map.nodes.some(
			( node ) =>
				node.type === 'span' &&
				node.children.includes( 'Linear equations' )
		)
	);
	const inputs = map.nodes.filter( ( node ) => node.type === 'input' );
	assert.deepEqual(
		inputs.map( ( node ) => [ node.props.checked, node.props.disabled ] ),
		[
			[ false, false ],
			[ true, true ],
			[ false, false ],
		]
	);
	inputs[ 2 ].props.onChange();
	assert.deepEqual( next, [ 'algebra', 'science' ] );
} );
