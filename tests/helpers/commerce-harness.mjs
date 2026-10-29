import {
	validateCoupon,
	validateRefund,
} from '../../assets/src/features/commerce/model.mjs';
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { parse } from '@babel/parser';
import generatorModule from '@babel/generator';
import { transformSync } from '@babel/core';

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
	fs.readFileSync( 'assets/src/features/commerce/components.json' )
);
const compiled = new Map(
	rows.map( ( row ) => {
		const input = fs
			.readFileSync( 'assets/src/features/commerce/' + row.file, 'utf8' )
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
			// New explicit control associations are asserted separately from recovered rendering parity.
			if (
				[ 'id', 'htmlFor' ].includes( key ) &&
				typeof item === 'string' &&
				item.startsWith( 'lint-test-' )
			)
				return undefined;
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
		useId: () => 'lint-test-commerce',
		validateCoupon,
		validateRefund,
		showRequestError: ( message ) => requests.push( { notice: message } ),
		useNotifications: () => ( {
			openNotificationWithIcon() {},
			contextHolder: null,
		} ),
		h: () => React,
		console: { error() {} },
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
					index in states
						? states[ index ]
						: typeof initial === 'function'
							? initial()
							: initial,
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
	Object.assign( globals, {
		VY: ( value ) => String( value ),
		JY: ( value ) => String( value ),
		OQ: ( value ) => String( value ),
		Ge: ( value ) => value,
		sn: () => ( value ) => ( {
			format: () => String( value ),
			isValid: () => true,
		} ),
		moment: ( value ) => ( {
			format: () => String( value ),
			isValid: () => true,
		} ),
		SQ: ( value ) => ( Array.isArray( value ) ? value : [] ),
		c: () => ( { get: () => Promise.resolve( { data: {} } ) } ),
		order: {
			id: 321,
			status: 'completed',
			total: '100',
			refunds: [],
			currency: { currency: 'USD', currency_pos: 'left' },
			line_items: [],
			coupon_lines: [],
			order_notes: [],
			related_orders: [],
			student_id: 914,
			student_name: 'Learner',
			student_email: 'learner@example.test',
		},
		subscription: {
			id: 322,
			status: 'active',
			related_orders: [],
			line_items: [],
		},
		tQ: { A: 'Tag' },
		W: { A: 'Textarea' },
		wn: { A: 'InputNumber' },
		JQ: ( value ) => value,
		BQ: ( value ) => value,
		KQ: 'SubscriptionStatus',
		nf: 'SearchInput',
		Cm: 'SearchInput',
		Ea: 'ContentCard',
		Ie: 'ConfirmDialog',
		jre: 'CouponModal',
		Gre: 'CouponList',
		HG() {},
		$Y: 'OrderList',
		HQ: 'SubscriptionList',
		iZ: 'SubscriptionDetails',
		Ere: 'CouponRefreshIcon',
		sH: 'DatePicker',
		jf: 'CourseSelect',
	} );
	globals.actions = new Proxy(
		{},
		{
			get:
				( _, name ) =>
				( ...args ) => {
					requests.push( { action: name, args } );
					return Promise.resolve( true );
				},
		}
	);
	globals.selectors = {
		selectOrdersPagination: () => ( { totalOrders: 1, totalPages: 1 } ),
		selectOrders: () => [],
		getCurrency: () => ( { currency: 'USD', currency_pos: 'left' } ),
		selectSubscriptions: () => [],
		selectSubscriptionsPagination: () => ( {
			totalSubscriptions: 1,
			totalPages: 1,
		} ),
		selectSubscriptionsLoading: () => false,
		getNotificationMessage: () => '',
		getNotificationStatus: () => '',
		getOrder: () => globals.order,
		getRefundState: () => ( { amount: '10', reason: 'Test' } ),
		selectSingleSubscription: () => globals.subscription,
	};
	globals.y = {
		useDispatch: () => globals.actions,
		useSelect: ( fn ) => fn( () => globals.selectors ),
	};
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

export { harness, rows, plain, find };
