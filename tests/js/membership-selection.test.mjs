import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';
import { restUrl } from '../../assets/src/features/restUrl.mjs';

const { code } = transformSync(
	fs.readFileSync(
		'assets/src/features/memberships/MembershipCourses.jsx',
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
	'Notice',
	'Spinner',
	'CurriculumMindmap',
	'restUrl',
	`${ code }; return createMembershipCourses;`
)( element, 'Button', 'Notice', 'Spinner', 'CurriculumMindmap', restUrl );
function flatten( node ) {
	return ! node || typeof node !== 'object'
		? []
		: Array.isArray( node )
			? node.flatMap( flatten )
			: [ node, ...node.children.flatMap( flatten ) ];
}
const runtime = ( extra ) => ( {
	I: { SpacerWP: 'Spacer', HeadingWP: 'Heading', AdvancedSelectWP: 'Select' },
	Ea: 'Card',
	T: { default: 'store' },
	b: { __: ( text ) => text },
	Ge: ( text ) => text,
	...extra,
} );

// useState slots, in order: courses, items, bundles, preview, error, previewError, loading, item status.
const preview = {
	total: 1,
	courses: [
		{ id: 1, name: 'Direct course', reasons: [ 'Individual course' ] },
	],
};

test( 'membership topic options stay hidden and existing topic access survives picker edits', () => {
	const plan = { course_curriculum: [ 10, 99 ] };
	const states = [
		[],
		[
			{ value: 10, label: 'Syllabus', item_type: 'syllabus' },
			{ value: 99, label: 'Hidden topic', item_type: 'topic' },
		],
		preview,
		'',
		'',
		false,
		'ready',
	];
	let index = 0;
	const writes = [];
	const Component = factory( () =>
		runtime( {
			g: {
				useState: () => [ states[ index++ ], () => {} ],
				useEffect: () => {},
				useRef: () => ( { current: 0 } ),
			},
			y: {
				useSelect: ( read ) =>
					read( () => ( { selectMembershipPlanData: () => plan } ) ),
				useDispatch: () => ( {
					updateMembershipPlan: ( ...args ) => writes.push( args ),
				} ),
			},
			l: () => () => Promise.resolve( [] ),
		} )
	);
	const picker = flatten( Component() ).filter(
		( node ) => node.type === 'Select'
	)[ 1 ];
	assert.deepEqual(
		picker.props.value.map( ( item ) => item.value ),
		[ 10 ]
	);
	assert.deepEqual(
		picker.props.options.map( ( item ) => item.value ),
		[ 10 ]
	);
	picker.props.onChange( null );
	assert.deepEqual( writes, [ [ 'course_curriculum', [ 99 ] ] ] );
} );

test( 'membership editor restores all selections, saves IDs and shows the resolved preview', () => {
	const plan = {
		products: [ { id: 1, name: 'Direct course' } ],
		course_curriculum: [ 10 ],
		course_bundles: [ 20 ],
		course_categories: [],
		course_tags: [],
		excluded_courses: [ 3 ],
	};
	const states = [
		[],
		[ { value: 10, label: 'Mathematics' } ],
		preview,
		'',
		'',
		false,
		'ready',
	];
	let index = 0;
	const writes = [];
	const Component = factory( () =>
		runtime( {
			g: {
				useState: () => [ states[ index++ ], () => {} ],
				useEffect: () => {},
				useRef: () => ( { current: 0 } ),
			},
			y: {
				useSelect: ( read ) =>
					read( () => ( { selectMembershipPlanData: () => plan } ) ),
				useDispatch: () => ( {
					updateMembershipPlan: ( ...args ) => writes.push( args ),
				} ),
			},
			l: () => () => Promise.resolve( [] ),
		} )
	);
	const nodes = flatten( Component() );
	const selects = nodes.filter( ( node ) => node.type === 'Select' );
	assert.deepEqual(
		selects.map( ( node ) =>
			node.props.value.map( ( item ) => item.value )
		),
		[ [ 1 ], [ 10 ], [ 3 ] ]
	);
	selects[ 1 ].props.onChange( [ { value: 11, label: 'Algebra' } ] );
	selects[ 2 ].props.onChange( [ { value: 5, label: 'Excluded' } ] );
	assert.deepEqual( writes, [
		[ 'course_curriculum', [ 11 ] ],
		[ 'excluded_courses', [ 5 ] ],
	] );
	assert.ok(
		nodes.some(
			( node ) =>
				node.type === 'td' &&
				node.children.includes( 'Individual course' )
		)
	);
	assert.ok(
		nodes.some(
			( node ) =>
				node.type === 'td' && node.children.includes( 'Direct course' )
		)
	);
	const mindmap = nodes.find( ( node ) => node.type === 'CurriculumMindmap' );
	assert.deepEqual( mindmap.props.selected, [ 10 ] );
	mindmap.props.onChange( [ 11 ] );
	assert.deepEqual( writes.at( -1 ), [ 'course_curriculum', [ 11 ] ] );
	assert.equal(
		nodes.some(
			( node ) =>
				node.type === 'Notice' &&
				node.props.className === 'ohmylms-membership-legacy-rules'
		),
		false,
		'no legacy notice without legacy rules'
	);
} );

test( 'old category and tag rules are listed, keep counting in the preview, and can only be removed', async () => {
	const plan = {
		products: [],
		course_curriculum: [],
		course_bundles: [],
		course_categories: [ 30, 31 ],
		course_tags: [ '40' ],
		excluded_courses: [],
		legacy_rules: {
			categories: [
				{ id: 30, name: 'Old category' },
				{ id: 31, name: 'Other category' },
			],
			tags: [ { id: 40, name: 'Old tag' } ],
		},
	};
	const states = [ [], [], preview, '', '', false, 'ready' ];
	let index = 0;
	const writes = [];
	const requests = [];
	const effects = [];
	globalThis.window = {};
	try {
		const Component = factory( () =>
			runtime( {
				g: {
					useState: () => [ states[ index++ ], () => {} ],
					useEffect: ( effect ) => effects.push( effect ),
					useRef: () => ( { current: 0 } ),
				},
				y: {
					useSelect: ( read ) =>
						read( () => ( {
							selectMembershipPlanData: () => plan,
						} ) ),
					useDispatch: () => ( {
						updateMembershipPlan: ( ...args ) =>
							writes.push( args ),
					} ),
				},
				l: () => ( settings ) => {
					requests.push( settings );
					return Promise.resolve( { courses: [] } );
				},
			} )
		);
		const nodes = flatten( Component() );
		const notice = nodes.find(
			( node ) =>
				node.type === 'Notice' &&
				node.props.className === 'ohmylms-membership-legacy-rules'
		);
		assert.ok( notice, 'legacy notice is shown' );
		const labels = flatten( notice )
			.filter( ( node ) => node.type === 'li' )
			.map( ( node ) =>
				node.children
					.flat()
					.filter( ( child ) => typeof child === 'string' )
					.join( '' )
			);
		assert.deepEqual(
			labels.map( ( label ) => label.trim() ),
			[
				'Old category (category)',
				'Other category (category)',
				'Old tag (tag)',
			]
		);
		const buttons = flatten( notice ).filter(
			( node ) => node.type === 'Button'
		);
		buttons[ 0 ].props.onClick();
		buttons[ 2 ].props.onClick();
		assert.deepEqual( writes, [
			[ 'course_categories', [ 31 ] ],
			[ 'course_tags', [] ],
		] );
		// The preview request still carries the legacy rules, so it matches what members actually get.
		effects.at( -1 )();
		await new Promise( ( resolve ) => setTimeout( resolve, 260 ) );
		const sent = requests.find(
			( request ) =>
				request.path === '/ohmylms/v1/membership/course-preview'
		);
		assert.deepEqual( sent.data.course_categories, [ 30, 31 ] );
		assert.deepEqual( sent.data.course_tags, [ 40 ] );
	} finally {
		delete globalThis.window;
	}
} );

test( 'curriculum loading uses the REST root without requesting retired bundles', async () => {
	const effects = [];
	const states = [];
	const requests = [];
	let index = 0;
	globalThis.window = {
		ohmylms_params: { api_url: 'http://xyz.local/wp-json/' },
	};
	try {
		const Component = factory( () =>
			runtime( {
				g: {
					useState: ( initial ) => {
						const slot = index++;
						states[ slot ] = initial;
						return [
							initial,
							( value ) => {
								states[ slot ] = value;
							},
						];
					},
					useEffect: ( effect ) => effects.push( effect ),
					useRef: () => ( { current: 0 } ),
				},
				y: {
					useSelect: ( read ) =>
						read( () => ( {
							selectMembershipPlanData: () => ( {} ),
						} ) ),
					useDispatch: () => ( { updateMembershipPlan: () => {} } ),
				},
				l: () => ( settings ) => {
					requests.push( settings );
					return settings.url.endsWith( '/bundles/outline' )
						? Promise.reject( new Error( 'Invalid JSON' ) )
						: Promise.resolve( {
								items: [
									{
										id: 3,
										name: 'Cambridge',
										parent_id: 0,
										courses: [ { id: 9, title: 'Maths' } ],
									},
								],
							} );
				},
			} )
		);
		Component();
		effects[ 0 ]();
		await new Promise( ( resolve ) => setImmediate( resolve ) );
		assert.deepEqual(
			requests.map( ( request ) => request.url ),
			[
				'http://xyz.local/wp-json/ohmylms/v1/curriculum/outline?courses=1',
			]
		);
		assert.equal( states[ 1 ][ 0 ].label, 'Cambridge' );
		assert.deepEqual( states[ 1 ][ 0 ].courses, [
			{ id: 9, title: 'Maths' },
		] );
		assert.equal( states[ 6 ], 'ready' );
		assert.equal( states[ 3 ], '' );
	} finally {
		delete globalThis.window;
	}
} );
test( 'plain-permalink sites get a valid URL for routes with a query string', async () => {
	const effects = [];
	const requests = [];
	globalThis.window = {
		ohmylms_params: { api_url: 'http://xyz.local/index.php?rest_route=/' },
	};
	try {
		const Component = factory( () =>
			runtime( {
				g: {
					useState: ( initial ) => [ initial, () => {} ],
					useEffect: ( effect ) => effects.push( effect ),
					useRef: () => ( { current: 0 } ),
				},
				y: {
					useSelect: ( read ) =>
						read( () => ( {
							selectMembershipPlanData: () => ( {} ),
						} ) ),
					useDispatch: () => ( { updateMembershipPlan: () => {} } ),
				},
				l: () => ( settings ) => {
					requests.push( settings.url );
					return Promise.resolve( { items: [], bundles: [] } );
				},
			} )
		);
		Component();
		effects[ 0 ]();
		await new Promise( ( resolve ) => setImmediate( resolve ) );
		assert.deepEqual( requests, [
			'http://xyz.local/index.php?rest_route=/ohmylms/v1/curriculum/outline&courses=1',
		] );
	} finally {
		delete globalThis.window;
	}
} );
