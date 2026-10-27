import test from 'node:test';
import assert from 'node:assert/strict';
import { harness, rows, plain, find } from '../helpers/commerce-harness.mjs';
import {
	validateCoupon,
	validateRefund,
} from '../../assets/src/features/commerce/model.mjs';
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
const order = {
	id: 321,
	status: 'completed',
	total: '100',
	refunds: [],
	currency: { currency: 'USD', currency_pos: 'left' },
	line_items: [],
	coupon_lines: {},
	order_notes: [],
	related_orders: [],
	student_name: 'Learner',
	student_email: 'learner@example.test',
	total_orders: '2',
	total_revenue: '200',
	aov: '100',
};
const props = {
	id: 321,
	order,
	subscription: { id: 322, status: 'active' },
	status: 'active',
	notes: [],
	items: [],
	coupon: {},
	subtotal: 100,
	total: 100,
	paid: 100,
	discount: 0,
	taxAmount: 0,
	taxRate: 0,
	relatedOrders: [],
	address: 'Test address',
	data: null,
	isOpen: false,
};
test( 'commerce source preserves render trees for twenty baseline components', () => {
	for ( const row of rows.filter(
		( row ) =>
			! [ 'OrderBilling', 'SubscriptionStatus' ].includes( row.name )
	) ) {
		const h = harness();
		const legacy = h.render( row.name, props, true );
		if ( row.name === 'OrderGeneral' ) {
			const container = all( legacy, 'div' ).find(
				( node ) => node.props.layout === 'horizontal'
			);
			assert.ok( container );
			delete container.props.layout;
		}
		assert.deepEqual(
			plain( h.render( row.name, props ) ),
			plain( legacy ),
			row.name
		);
	}
} );
test( 'orders retain custom-date filters and sort criteria when returning to page one', async () => {
	const states = [
		false,
		[],
		false,
		3,
		10,
		'Learner',
		'any',
		null,
		null,
		[ '2026-09-01', '2026-09-25' ],
		'offline',
		{ field: 'date', order: 'DESC' },
	];
	const h = harness( states );
	const tree = h.render( 'OrderList' );
	for ( const effect of h.effects ) effect();
	await flush();
	const request = h.requests.find( ( r ) => r.action === 'fetchOrders' )
		.args[ 0 ];
	assert.equal( request.offset, 20 );
	assert.equal( request.start_date, '2026-09-01' );
	assert.equal( request.end_date, '2026-09-25' );
	assert.equal( request.date_filter, 'custom' );
	find( tree, 'Table' ).props.onChange(
		{},
		{},
		{ field: 'name', order: 'ascend' }
	);
	// Apply state updates and inspect the next request rather than a private hook index.
	for ( const [ index, value ] of h.updates )
		states[ index ] =
			typeof value === 'function' ? value( states[ index ] ) : value;
	h.effects.length = 0;
	h.requests.length = 0;
	h.render( 'OrderList' );
	for ( const effect of h.effects ) effect();
	await flush();
	const sorted = h.requests.find( ( r ) => r.action === 'fetchOrders' )
		.args[ 0 ];
	assert.equal( sorted.page, 1 );
	assert.equal( sorted.offset, 0 );
	assert.equal( sorted.orderby, 'title' );
	assert.equal( sorted.order, 'ASC' );
	assert.equal( sorted.start_date, '2026-09-01' );
	assert.equal( sorted.end_date, '2026-09-25' );
	assert.equal( sorted.date_filter, 'custom' );
} );
test( 'subscription status retains pending cancellation and failed saves release loading', async () => {
	const h = harness();
	h.globals.actions = {
		updateSubscription: async () => {
			throw Error( 'offline' );
		},
		showNotification() {},
	};
	const tree = h.render( 'SubscriptionStatus', props );
	assert.ok(
		find( tree, 'Select' ).props.options.some(
			( option ) => option.value === 'pending-cancel'
		)
	);
	await all( tree, 'ButtonWP' )
		.find( ( button ) => button.props.variant === 'primary' )
		.props.onClick();
	assert.equal( h.updates.get( 0 ), false );
} );
test( 'refund validation rejects malformed and excessive values before dispatch', async () => {
	for ( const amount of [ 'Infinity', '10abc', 101, -1, 0 ] )
		assert.equal(
			validateRefund( { amount, reason: 'Test' }, 100 ).valid,
			false
		);
	assert.equal(
		validateRefund( { amount: 10, reason: 'Test' }, 100 ).valid,
		true
	);
	const h = harness( [ true, false ] );
	h.globals.selectors.getRefundState = () => ( {
		amount: 101,
		reason: 'Test',
	} );
	const tree = h.render( 'OrderItems', props );
	const fields = [
		...all( tree, 'A' ),
		...all( tree, 'InputNumber' ),
		...all( tree, 'Textarea' ),
	];
	const labels = all( tree, 'label' );
	assert.equal( labels.length, 2 );
	assert.equal(
		new Set( labels.map( ( label ) => label.props.htmlFor ) ).size,
		2
	);
	for ( const label of labels ) {
		assert.ok(
			fields.some( ( field ) => field.props.id === label.props.htmlFor )
		);
	}
	const buttons = all( tree, 'ButtonWP' );
	const submit = buttons.find( ( button ) =>
		JSON.stringify( button.children ).includes( 'Process Refund' )
	);
	assert.ok( submit );
	await submit.props.onClick();
	assert.equal(
		h.requests.filter( ( r ) => r.action === 'issueRefund' ).length,
		0
	);
	assert.ok( h.requests.some( ( r ) => r.notice ) );
} );
test( 'refund API failure leaves the modal open and allows retry', async () => {
	const h = harness( [ true, false ] );
	h.globals.actions = {
		issueRefund: async () => {
			throw Error( 'offline' );
		},
		showNotification() {},
	};
	const tree = h.render( 'OrderItems', props );
	await all( tree, 'ButtonWP' )
		.find( ( button ) =>
			JSON.stringify( button.children ).includes( 'Process Refund' )
		)
		.props.onClick();
	assert.equal( h.updates.get( 1 ), false );
	assert.notEqual( h.updates.get( 0 ), false );
} );
test( 'unsaved order notes survive a failed save', async () => {
	const h = harness( [ 'Keep this note', false, true ] );
	h.globals.actions = { saveOrderNote: async () => false };
	const tree = h.render( 'OrderNotes', props );
	await all( tree, 'ButtonWP' )
		.find( ( button ) => button.children.includes( 'Add Note' ) )
		.props.onClick();
	assert.equal( h.updates.has( 0 ), false );
	assert.equal( h.updates.get( 1 ), false );
} );
test( 'coupon validation follows REST dates, limits, course restrictions and percentage bounds', () => {
	const coupon = {
		title: 'Test',
		code: 'TEST',
		amount: 10,
		discount_type: 'percent',
		date_start: { date: '2026-09-25T10:00:00' },
		date_expires: { date: '2026-09-26T10:00:00' },
		usage_limit: 2,
		usage_limit_per_user: 1,
	};
	assert.deepEqual( validateCoupon( coupon ), {} );
	for ( const patch of [
		{ amount: 101 },
		{ amount: Infinity },
		{ code: ' ' },
		{ date_expires: { date: '2026-09-24' } },
		{ usage_limit: 1.5 },
		{ course_id_type: 'selected_course', course_ids: [] },
	] )
		assert.ok(
			Object.keys( validateCoupon( { ...coupon, ...patch } ) ).length
		);
} );
test( 'coupon pagination uses six-row offsets and fetch failures release loading', async () => {
	const states = [
		false,
		null,
		[],
		false,
		false,
		'sale',
		[],
		[],
		false,
		null,
		2,
		12,
		false,
	];
	const h = harness( states );
	let request;
	h.globals.l = () => async ( input ) => {
		request = input;
		throw Error( 'offline' );
	};
	h.render( 'CouponList' );
	for ( const effect of h.effects ) effect();
	await flush();
	assert.match( request.path, /offset=6/ );
	assert.match( request.path, /per_page=6/ );
	assert.match( request.path, /search=sale/ );
	assert.equal( h.updates.get( 8 ), false );
} );
test( 'subscription fetch failure releases its skeleton', async () => {
	const h = harness();
	h.globals.actions = {
		fetchSubscription: async () => {
			throw Error( 'offline' );
		},
	};
	h.render( 'SubscriptionDetails' );
	for ( const effect of h.effects ) effect();
	await flush();
	assert.equal( h.updates.get( 0 ), false );
} );
