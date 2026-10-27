import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { parse } from '@babel/parser';
import generatorModule from '@babel/generator';
import { transformSync } from '@babel/core';
import {
	loadDashboard,
	sortRecentCourses,
} from '../../assets/src/features/analytics/model.mjs';

const generate = generatorModule.default || generatorModule;
const source = 'assets/src/';
const manifest = JSON.parse( fs.readFileSync( source + 'manifest.json' ) );
const factory = manifest.assets
	.find( ( a ) => a.output === 'assets/dist/admin/ohmylms.js' )
	.factories.find( ( f ) => f.id === '1841' );
const ast = parse(
	factory.fragments
		.map( ( file ) => fs.readFileSync( source + file, 'utf8' ) )
		.join( '\n' )
);
const declarations = new Map();
for ( const statement of ast.program.body ) {
	if ( statement.type === 'VariableDeclaration' )
		for ( const declaration of statement.declarations )
			declarations.set( declaration.id.name, declaration.init );
	if ( statement.type === 'FunctionDeclaration' )
		declarations.set( statement.id.name, statement );
}
const rows = JSON.parse(
	fs.readFileSync( 'assets/src/features/analytics/components.json' )
);
const compiled = new Map(
	rows.map( ( row ) => {
		const input = fs
			.readFileSync( 'assets/src/features/analytics/' + row.file, 'utf8' )
			.replace( /^import .*;$/gm, '' )
			.replace( 'export function', 'function' );
		return [
			row.name,
			transformSync( input, {
				configFile: false,
				babelrc: false,
				presets: [
					[
						'@babel/preset-react',
						{ runtime: 'classic', pragma: 'React.createElement' },
					],
				],
			} ).code,
		];
	} )
);
const plain = ( value ) =>
	JSON.parse(
		JSON.stringify( value, ( key, item ) => {
			// Explicit control associations are covered separately from legacy rendering parity.
			if (
				[ 'id', 'htmlFor' ].includes( key ) &&
				typeof item === 'string' &&
				item.startsWith( 'lint-test-' )
			) {
				return undefined;
			}
			return typeof item === 'function' ? '[callback]' : item;
		} )
	);
const find = ( tree, type ) => {
	if ( ! tree || typeof tree !== 'object' ) return undefined;
	if ( tree.type === type ) return tree;
	for ( const child of Array.isArray( tree ) ? tree : tree.children || [] ) {
		const result = find( child, type );
		if ( result ) return result;
	}
};

function harness( states = [] ) {
	let cursor = 0;
	const updates = new Map(),
		effects = [],
		requests = [];
	const React = {
		Fragment: 'Fragment',
		createElement: ( type, props, ...children ) => ( {
			type,
			props: props || {},
			children,
		} ),
	};
	const controls = new Proxy( {}, { get: ( _, name ) => String( name ) } );
	const globals = {
		React,
		useId: () => 'lint-test-analytics',
		h: () => React,
		console: { error() {} },
		loadDashboard,
		sortRecentCourses,
		window: {},
		I: controls,
		b: { __: ( text ) => text },
		HG() {},
		Ge: ( text ) => text,
		g: {
			memo: ( fn ) => fn,
			useState( initial ) {
				const index = cursor++;
				return [
					index in states ? states[ index ] : initial,
					( value ) => updates.set( index, value ),
				];
			},
			useRef: ( value ) => ( { current: value } ),
			useEffect: ( fn ) => effects.push( fn ),
			useMemo: ( fn ) => fn(),
			useCallback: ( fn ) => fn,
		},
		l: () => async ( request ) => {
			requests.push( request );
			return request.method === 'POST'
				? { status: 'success' }
				: globals.response;
		},
		sN: { A: 'Table' },
		Mt: { A: 'InfoIcon' },
		V: { A: 'Tooltip' },
	};
	Object.assign( globals, {
		wq: { format: ( _, date ) => String( date ) },
		T: { default: 'store' },
		M: () => ( { noConflict() {} } ),
		L: { useIsPro: () => globals.pro, isProActive: true },
		pro: true,
		f: {
			g: () => ( { id: '123' } ),
			Zp: () => ( target ) => requests.push( target ),
		},
		v: { Link: 'Link' },
		z: {
			A: () => ( { openNotificationWithIcon() {}, contextHolder: null } ),
		},
		EG: { A: 'Flex' },
		SG: { A: 'FlexItem' },
		Ne: { A: 'MenuIcon' },
		q: { Icon: 'Icon' },
		D: { A: 'Button' },
		GG: { A: 'Notice' },
		_: { A: 'Skeleton' },
		vn: { A: 'Select' },
		kt: { A: 'Tag' },
		gG: { A: 'Avatar' },
		pG: { A: 'EditIcon' },
		lN: {
			addQueryArgs: ( path, args ) =>
				path + '?' + new URLSearchParams( args ),
		},
		sn: () => ( value ) => ( {
			format: () => String( value ).replaceAll( '/', '-' ),
			isValid: () => true,
		} ),
		aN: () => ( value ) => ( { format: () => value } ),
		UH: ( currency, position, value ) => String( value ),
		overview: {
			currency: '$',
			earning: { growth: {} },
			recent_courses: [],
		},
		filter: { type: 'monthly' },
		loading: false,
		$U: {
			$C: {
				earning_graph: {},
				order_by_country: {},
				transactions: [],
				count_unchecked_orders: 0,
				currency: '$',
			},
		},
	} );
	globals.g.Suspense = 'Suspense';
	globals.actions = {
		setDashboardLoader: ( value ) => updates.set( 'loading', value ),
		setDashboardOverview: ( value ) => updates.set( 'overview', value ),
		setDashboardAll: ( value ) => updates.set( 'all', value ),
	};
	globals.y = {
		useDispatch: () => globals.actions,
		useSelect: ( fn ) =>
			fn( () => ( {
				selectCourses: () => [],
				getDashboardLoader: () => globals.loading,
				getDashboardOverview: () => globals.overview,
				getDashboardFilter: () => globals.filter,
				getNotificationMessage: () => '',
				getNotificationStatus: () => '',
			} ) ),
	};
	globals.window.ohmylms_params = {
		currency: '$',
		plugin_assets: '/assets/',
	};
	globals.ohmylms_params = globals.window.ohmylms_params;
	for ( const name of [
		'YG',
		'NG',
		'VG',
		'lU',
		'nf',
		'kf',
		'mG',
		'wG',
		'MG',
		'_G',
		'PG',
		'lf',
		'yG',
		'Br',
		'uf',
		'df',
		'SB',
		'vG',
		'gU',
		'yU',
		'_U',
		'EU',
		'RU',
		'CU',
		'OU',
		'MU',
		'jU',
		'IU',
		'YH',
		'LU',
		'cq',
		'BU',
		'eq',
		'Cm',
		'ZU',
		'UU',
		'JU',
		'Dq',
		'Fq',
		'dc',
		'Rq',
	] )
		globals[ name ] = name;
	const context = vm.createContext( globals );
	for ( const [ name, declaration ] of declarations ) {
		if ( name in globals ) continue;
		Object.defineProperty( globals, name, {
			configurable: true,
			get() {
				const expression =
					declaration.type === 'FunctionDeclaration'
						? {
								...declaration,
								type: 'FunctionExpression',
								id: null,
							}
						: declaration;
				const value = vm.runInContext(
					'(' +
						generate( expression, { comments: false } ).code +
						'\n)',
					context
				);
				Object.defineProperty( globals, name, {
					value,
					writable: true,
					configurable: true,
				} );
				return value;
			},
			set( value ) {
				Object.defineProperty( globals, name, {
					value,
					writable: true,
					configurable: true,
				} );
			},
		} );
	}
	return {
		globals,
		updates,
		effects,
		requests,
		render( name, props = {}, original = false ) {
			cursor = 0;
			const row = rows.find( ( row ) => row.name === name );
			if ( original )
				return vm.runInContext(
					'(' +
						generate( declarations.get( row.binding ) ).code +
						')',
					context
				)( props );
			const create = vm.runInContext(
				compiled.get( name ) + '\ncreate' + name,
				context
			);
			return create( () => globals )( props );
		},
	};
}

const all = ( tree, type ) =>
	! tree || typeof tree !== 'object'
		? []
		: [
				...( tree.type === type ? [ tree ] : [] ),
				...( Array.isArray( tree )
					? tree
					: tree.children || []
				).flatMap( ( child ) => all( child, type ) ),
			];
const flush = () => new Promise( ( resolve ) => setImmediate( resolve ) );
const props = {
	isOpen: true,
	onClose() {},
	handleAddCourse() {},
	dashboardCardData: [],
	data: {},
	totalCourses: 0,
	popularCourses: [],
	students: [],
	studentData: { name: 'Student', email: 'student@example.test' },
	course: { id: 123, name: 'Course' },
	transactionData: [],
	columns: [],
	skeletonColumns: [],
	orderTypeOptions: [],
	handleTypeFilters() {},
	handleOrderTypeFilters() {},
	graphData: {},
	filterTypeParam: { type: 'custom' },
};
test( 'analytics conversion preserves rendering of all eighteen components', () => {
	for ( const row of rows ) {
		const h = harness();
		assert.deepEqual(
			plain( h.render( row.name, props ) ),
			plain( h.render( row.name, props, true ) ),
			row.name
		);
	}
} );

test( 'dashboard sorting leaves store data unchanged and loader recovers from failed/obsolete requests', async () => {
	const input = [
		{ id: 1, total_sales_count: 2 },
		{ id: 2, total_sales_count: 8 },
	];
	assert.deepEqual(
		sortRecentCourses( input ).map( ( item ) => item.id ),
		[ 2, 1 ]
	);
	assert.deepEqual(
		input.map( ( item ) => item.id ),
		[ 1, 2 ]
	);
	const events = [];
	const actions = {
		setDashboardLoader: ( v ) => events.push( [ 'loading', v ] ),
		setDashboardOverview: ( v ) => events.push( [ 'overview', v ] ),
		setDashboardAll: ( v ) => events.push( [ 'all', v ] ),
	};
	const requests = [];
	await loadDashboard(
		async ( request ) => {
			requests.push( request );
			return { id: 1 };
		},
		actions,
		{
			type: 'custom',
			startDate: { date: '2026/09/01' },
			endDate: { date: '2026/09/25' },
		},
		( date ) => date.replaceAll( '/', '-' )
	).done;
	assert.equal(
		requests[ 0 ].path,
		'ohmylms/v1/dashboard?filter=custom&start_date=2026-09-01&end_date=2026-09-25'
	);
	assert.deepEqual( events.at( -1 ), [ 'loading', false ] );
	const errors = [];
	await loadDashboard(
		async () => {
			throw Error( 'offline' );
		},
		actions,
		{ type: 'monthly' },
		String,
		( error ) => errors.push( error.message )
	).done;
	assert.deepEqual( errors, [ 'offline' ] );
	assert.deepEqual( events.at( -1 ), [ 'loading', false ] );
	let resolve;
	const request = loadDashboard(
		() => new Promise( ( done ) => ( resolve = done ) ),
		actions,
		{ type: 'monthly' },
		String
	);
	request.cancel();
	const count = events.length;
	resolve( { stale: true } );
	await request.done;
	assert.equal( events.length, count );
} );
test( 'dashboard retains earnings and course-list navigation', () => {
	const h = harness();
	const tree = h.render( 'DashboardOverview', props );
	const buttons = all( tree, 'Button' );
	buttons[ 0 ].props.onClick();
	buttons[ 1 ].props.onClick();
	assert.deepEqual( h.requests, [ '/earnings-report', '/courses' ] );
} );
test( 'course report keeps request filters and releases loading state', async () => {
	const h = harness();
	h.globals.response = { title: 'Course', students: [] };
	h.render( 'CourseReport' );
	h.effects[ 0 ]();
	await flush();
	const request = h.requests[ 0 ];
	assert.equal( request.method, 'GET' );
	assert.ok( request.path.startsWith( '/ohmylms/v1/analytics/course/123?' ) );
	assert.ok( request.path.includes( 'completion_type=all' ) );
	assert.ok( request.path.includes( 'sort_by=DESC' ) );
	assert.equal( h.updates.get( 1 ), false );
	assert.deepEqual( h.updates.get( 0 ), h.globals.response );
} );
test( 'earnings reports retain data filters', async () => {
	const h = harness();
	h.globals.response = {
		earning_graph: { total_revenue: 90 },
		order_by_country: { Mongolia: 90 },
		transactions: [],
		count_unchecked_orders: 2,
		currency: '$',
		currency_pos: 'left',
	};
	const tree = h.render( 'EarningsReport' );
	h.effects[ 0 ]();
	await flush();
	assert.ok( h.requests[ 0 ].path.includes( 'data_type=all' ) );
	assert.equal( h.updates.get( 0 ), false );
	assert.deepEqual( h.updates.get( 4 ), h.globals.response.earning_graph );
	find( tree, 'ZU' ).props.onChange( 'current_year' );
	await flush();
	assert.ok( h.requests.at( -1 ).path.includes( 'filter=current_year' ) );
	assert.ok( h.requests.at( -1 ).path.includes( 'data_type=earning' ) );
} );

test( 'populated analytics preserve money cards, course metrics and transaction rows', () => {
	const courseData = {
		title: 'Algebra',
		content_data: {
			total_enrollment: 12,
			completed_students: 3,
			in_progress_students: 9,
			ratings: 4.8,
			chapters: 2,
			lessons: 4,
			quizzes: 1,
			assignments: 1,
		},
		earning: {
			currency: '$',
			currency_pos: 'left',
			total_earning: 120,
			total_refund: 10,
			net_amount: 110,
			graph_data: {},
		},
		students: [],
	};
	for ( const [ name, states, values ] of [
		[ 'CourseReport', [ courseData, false, '', 'all', 'all' ], {} ],
		[
			'EarningsReport',
			[
				false,
				false,
				false,
				'last_30_days',
				{
					total_revenue: 120,
					total_refund: 10,
					net_amount: 110,
					growth: {},
				},
				{ Mongolia: 110 },
				[ { order_id: 9, order_total: 120 } ],
				2,
				'all',
				{ currency: '$', currency_pos: 'left' },
				'30 days',
			],
			{},
		],
		[
			'EarningsSummaryCards',
			[],
			{
				dashboardCardData: [
					{
						label: 'Income',
						value: '120',
						progression_percent: '10%',
						progression_state: 'success',
					},
				],
				dataLoading: false,
			},
		],
		[
			'TransactionHistory',
			[],
			{
				...props,
				transactionData: [ { order_id: 9, order_total: 120 } ],
			},
		],
	] ) {
		const h = harness( states );
		assert.deepEqual(
			plain( h.render( name, values ) ),
			plain( h.render( name, values, true ) ),
			name
		);
	}
} );
test( 'earnings chart preserves monthly, yearly and custom data series', () => {
	for ( const [ type, data, labels ] of [
		[
			'monthly',
			{
				'2026-09-01': { earning: 100, refund: 10, net: 90 },
				'2026-09-02': { earning: 20, refund: 0, net: 20 },
			},
			[ '1', '2' ],
		],
		[
			'yearly',
			{
				'2026-01': { earning: 100, refund: 10, net: 90 },
				'2026-02': { earning: 20, refund: 0, net: 20 },
			},
			[ 'Jan', 'Feb' ],
		],
		[
			'custom',
			{
				'2026-09-01': { earning: 100, refund: 10, net: 90 },
				'2026-09-02': { earning: 20, refund: 0, net: 20 },
			},
			[ 'Sep 1', 'Sep 2' ],
		],
	] ) {
		const h = harness();
		h.render( 'EarningsChart', {
			graphData: data,
			filterTypeParam: { type },
		} );
		h.effects[ 0 ]();
		const series = h.updates.get( 0 );
		assert.deepEqual( plain( series.revenue.labels ), labels );
		assert.deepEqual( plain( series.revenue.values ), [ 100, 20 ] );
		assert.deepEqual( plain( series.refund.values ), [ 10, 0 ] );
		assert.deepEqual( plain( series.net_amount.values ), [ 90, 20 ] );
	}
} );
test( 'analytics date filter emits selected presets and custom ranges', () => {
	const calls = [];
	const h = harness( [ 'custom_range', { startDate: null, endDate: null } ] );
	const tree = h.render( 'AnalyticsDateFilter', {
		onChange: ( value ) => calls.push( value ),
		onRangeChange: ( value ) => calls.push( value ),
	} );
	find( tree, 'SelectWP' ).props.onChange( 'current_year' );
	find( tree, 'DateRangePickerWP' ).props.onChange( [
		'2026-09-01',
		'2026-09-25',
	] );
	assert.deepEqual( calls, [
		'current_year',
		[ '2026-09-01', '2026-09-25' ],
	] );
} );
