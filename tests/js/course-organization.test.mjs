import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';
import {
	filterItems,
	orderItems,
	orderBundles,
	sameIds,
	toggleId,
} from '../../assets/src/features/courses/organization.mjs';
import { restUrl } from '../../assets/src/features/restUrl.mjs';

const items = [
	{ id: 3, parent_id: 1, position: 2, name: 'Algebra', code: 'M-ALG' },
	{ id: 1, parent_id: 0, position: 1, name: 'Mathematics' },
	{ id: 2, parent_id: 0, position: 0, name: 'Biology' },
	{ id: 4, parent_id: 1, position: 1, name: 'Geometry' },
	{ id: 5, parent_id: 99, position: 0, name: 'Orphan' },
];

test( 'curriculum items are shown in tree order with their depth, and orphans stay reachable', () => {
	const ordered = orderItems( items );
	assert.deepEqual(
		ordered.map( ( item ) => [ item.name, item.depth ] ),
		[
			[ 'Biology', 0 ],
			[ 'Orphan', 0 ],
			[ 'Mathematics', 0 ],
			[ 'Geometry', 1 ],
			[ 'Algebra', 1 ],
		]
	);
	assert.equal( items[ 0 ].depth, undefined, 'input is not mutated' );
} );

test( 'searching keeps the matching items and the ancestors that give them context', () => {
	const ordered = orderItems( items );
	assert.deepEqual(
		filterItems( ordered, 'alg' ).map( ( item ) => item.name ),
		[ 'Mathematics', 'Algebra' ]
	);
	assert.deepEqual(
		filterItems( ordered, 'm-alg' ).map( ( item ) => item.name ),
		[ 'Mathematics', 'Algebra' ],
		'matches the syllabus code too'
	);
	assert.equal( filterItems( ordered, '' ).length, ordered.length );
	assert.deepEqual( filterItems( ordered, 'zzz' ), [] );
} );

test( 'REST URLs join a route query with & on plain-permalink sites', () => {
	assert.equal(
		restUrl( 'http://x.test/wp-json/', '/ohmylms/v1/a?b=1&c=2' ),
		'http://x.test/wp-json/ohmylms/v1/a?b=1&c=2'
	);
	assert.equal(
		restUrl(
			'http://x.test/index.php?rest_route=/',
			'/ohmylms/v1/a?b=1&c=2'
		),
		'http://x.test/index.php?rest_route=/ohmylms/v1/a&b=1&c=2'
	);
	assert.equal(
		restUrl( 'http://x.test/index.php?rest_route=/', 'ohmylms/v1/a' ),
		'http://x.test/index.php?rest_route=/ohmylms/v1/a'
	);
} );

test( 'toggling adds or removes a single id without changing the input', () => {
	const ids = Object.freeze( [ 1, 2 ] );
	assert.deepEqual( toggleId( ids, 3, true ), [ 1, 2, 3 ] );
	assert.deepEqual( toggleId( ids, '2', false ), [ 1 ] );
	assert.deepEqual( toggleId( ids, 2, true ), [ 1, 2 ], 'no duplicates' );
	assert.ok( sameIds( [ 2, 1 ], [ '1', '2' ] ) );
	assert.ok( ! sameIds( [ 1 ], [ 1, 2 ] ) );
} );

test( 'course bundles list published ones first, then drafts, alphabetically', () => {
	assert.deepEqual(
		orderBundles( [
			{ id: 1, title: 'Zebra', status: 'published' },
			{ id: 2, title: 'Alpha', status: 'draft' },
			{ id: 3, title: 'Beta', status: 'published' },
		] ).map( ( bundle ) => bundle.title ),
		[ 'Beta', 'Zebra', 'Alpha' ]
	);
} );

// ---- The panel itself, driven through a minimal hook runtime -------------------------------

const { code } = transformSync(
	fs.readFileSync(
		'assets/src/features/courses/CourseOrganization.jsx',
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
const element = ( type, props, ...children ) => ( {
	type,
	props: props || {},
	children,
} );
const factory = new Function(
	'createElement',
	'Button',
	'CheckboxControl',
	'Notice',
	'Spinner',
	'TextControl',
	'filterItems',
	'orderItems',
	'orderBundles',
	'sameIds',
	'toggleId',
	'restUrl',
	`${ code }; return createCourseOrganization;`
)(
	element,
	'Button',
	'CheckboxControl',
	'Notice',
	'Spinner',
	'TextControl',
	filterItems,
	orderItems,
	orderBundles,
	sameIds,
	toggleId,
	restUrl
);
const flatten = ( node ) =>
	! node || typeof node !== 'object'
		? []
		: Array.isArray( node )
			? node.flatMap( flatten )
			: [ node, ...node.children.flatMap( flatten ) ];
const settle = () => new Promise( ( resolve ) => setImmediate( resolve ) );

/** Mount the panel with stateful hooks; render() re-runs it and flush() runs effects whose dependencies changed. */
function mount( respond ) {
	const slots = [];
	const effects = [];
	const requests = [];
	let cursor = 0;
	let effectCursor = 0;
	let Component;
	const hooks = {
		useState( initial ) {
			const index = cursor++;
			if ( ! ( index in slots ) ) slots[ index ] = initial;
			return [
				slots[ index ],
				( value ) => {
					slots[ index ] = value;
				},
			];
		},
		useEffect( fn, deps ) {
			const index = effectCursor++;
			effects[ index ] = {
				fn,
				deps,
				ran: effects[ index ]?.ran,
				last: effects[ index ]?.last,
			};
		},
	};
	globalThis.window = {
		ohmylms_params: { api_url: 'http://xyz.local/wp-json/' },
		location: {
			href: 'http://xyz.local/wp-admin/admin.php?page=ohmylms#/course-edit/5',
		},
	};
	Component = factory( () => ( {
		I: {
			CardWP: 'Card',
			SpacerWP: 'Spacer',
			FlexWP: 'Flex',
			FlexItemWP: 'FlexItem',
			HeadingWP: 'Heading',
		},
		b: { __: ( text ) => text },
		f: { g: () => ( { id: '5' } ) },
		g: hooks,
		l: () => ( settings ) => {
			requests.push( settings );
			return respond( settings );
		},
	} ) );
	return {
		requests,
		render() {
			cursor = 0;
			effectCursor = 0;
			return flatten( Component() );
		},
		async flush() {
			if ( ! effects.length ) this.render();
			for ( const entry of effects ) {
				const changed =
					! entry.ran || JSON.stringify( entry.deps ) !== entry.last;
				if ( changed ) {
					entry.ran = true;
					entry.last = JSON.stringify( entry.deps );
					entry.fn();
				}
			}
			await settle();
		},
	};
}

const outline = {
	items: [
		{
			id: 1,
			parent_id: 0,
			position: 0,
			name: 'Mathematics',
			item_type: 'subject',
			code: '',
			version: '',
		},
		{
			id: 2,
			parent_id: 1,
			position: 0,
			name: 'Algebra',
			code: '',
			version: '',
		},
	],
};
const bundleOutline = {
	bundles: [
		{ id: 7, title: 'Data career', status: 'published' },
		{ id: 8, title: 'Draft path', status: 'draft' },
	],
};
const organization = ( curriculum, bundles, manage = true ) => ( {
	curriculum: curriculum.map( ( id ) => ( { id } ) ),
	bundles: bundles.map( ( id ) => ( { id } ) ),
	can_manage_bundles: manage,
} );
const route = ( state ) => ( settings ) => {
	if ( settings.url.endsWith( '/curriculum/outline' ) )
		return Promise.resolve( outline );
	if ( settings.url.endsWith( '/bundles/outline' ) )
		return Promise.resolve( bundleOutline );
	if (
		settings.url.endsWith( '/courses/5/organization' ) &&
		settings.method === 'PUT'
	)
		return state.put( settings );
	return Promise.resolve( state.organization );
};

test( 'the panel loads the outline and the course placement, then saves each change immediately', async () => {
	const state = {
		organization: organization( [ 2 ], [ 7 ] ),
		put: ( settings ) =>
			Promise.resolve(
				organization(
					settings.data.curriculum_ids || [ 2 ],
					settings.data.bundle_ids || [ 7 ]
				)
			),
	};
	const panel = mount( route( state ) );
	try {
		assert.ok(
			panel.render().some( ( node ) => node.type === 'Spinner' ),
			'loading first'
		);
		await panel.flush();
		const nodes = panel.render();
		const subjectGroup = nodes.find(
			( node ) =>
				node.props.role === 'group' &&
				node.props[ 'aria-label' ] === 'Subject'
		);
		assert.ok( subjectGroup, 'subjects have their own field in Organize' );
		assert.deepEqual(
			flatten( subjectGroup )
				.filter( ( node ) => node.type === 'CheckboxControl' )
				.map( ( node ) => node.props.label ),
			[ 'Mathematics' ]
		);
		const boxes = nodes.filter(
			( node ) => node.type === 'CheckboxControl'
		);
		assert.deepEqual(
			boxes.map( ( box ) => [
				box.props.label,
				box.props.checked,
				box.props.disabled,
			] ),
			[
				[ 'Mathematics', false, false ],
				[ 'Algebra', true, false ],
			]
		);
		assert.deepEqual(
			panel.requests.slice( 0, 3 ).map( ( request ) => request.url ),
			[
				'http://xyz.local/wp-json/ohmylms/v1/curriculum/outline',
				'http://xyz.local/wp-json/ohmylms/v1/courses/5/organization',
			]
		);

		boxes[ 0 ].props.onChange( true );
		await settle();
		const put = panel.requests.at( -1 );
		assert.equal( put.method, 'PUT' );
		assert.deepEqual( put.data, { curriculum_ids: [ 2, 1 ] } );
		assert.equal(
			panel
				.render()
				.filter( ( node ) => node.type === 'CheckboxControl' )[ 0 ]
				.props.checked,
			true
		);
	} finally {
		delete globalThis.window;
	}
} );

test( 'a rejected change is undone and the server reason is shown', async () => {
	const state = {
		organization: organization( [ 2 ], [ 7 ] ),
		put: () =>
			Promise.reject(
				new Error( 'A published bundle must keep at least one member.' )
			),
	};
	const panel = mount( route( state ) );
	try {
		await panel.flush();
		panel
			.render()
			.filter( ( node ) => node.type === 'CheckboxControl' )[ 1 ]
			.props.onChange( false );
		await settle();
		const nodes = panel.render();
		assert.equal(
			nodes.filter( ( node ) => node.type === 'CheckboxControl' )[ 1 ]
				.props.checked,
			true,
			'the checkbox goes back'
		);
		const notice = nodes.find(
			( node ) => node.type === 'Notice' && node.props.status === 'error'
		);
		assert.match( notice.children.join( '' ), /at least one member/ );
	} finally {
		delete globalThis.window;
	}
} );

test( 'retired course bundles are absent from the organization panel', async () => {
	const state = {
		organization: organization( [], [ 7 ], false ),
		put: () => Promise.reject( new Error( 'unexpected write' ) ),
	};
	const panel = mount( route( state ) );
	try {
		await panel.flush();
		const nodes = panel.render();
		const boxes = nodes.filter(
			( node ) => node.type === 'CheckboxControl'
		);
		assert.deepEqual(
			boxes.map( ( box ) => box.props.disabled ),
			[ false, false ]
		);
		assert.ok(
			! nodes.some(
				( node ) =>
					node.type === 'p' &&
					node.children.join( '' ).includes( 'Only administrators' )
			)
		);
	} finally {
		delete globalThis.window;
	}
} );

test( 'a failed load offers a retry instead of an empty form', async () => {
	let fail = true;
	const panel = mount( ( settings ) =>
		fail
			? Promise.reject(
					new Error( 'Curriculum storage is not installed yet.' )
				)
			: route( { organization: organization( [], [] ) } )( settings )
	);
	try {
		await panel.flush();
		const nodes = panel.render();
		assert.match(
			nodes
				.find( ( node ) => node.type === 'Notice' )
				.children.join( '' ),
			/not installed/
		);
		fail = false;
		nodes.find( ( node ) => node.type === 'Button' ).props.onClick();
		panel.render();
		await panel.flush();
		assert.ok(
			panel.render().some( ( node ) => node.type === 'CheckboxControl' )
		);
	} finally {
		delete globalThis.window;
	}
} );
