import test from 'node:test';
import assert from 'node:assert/strict';
import {
	MEMBERSHIP_SCREENS,
	MEMBERSHIP_TABS,
	membershipRoutes,
} from '../../assets/src/features/memberships/membershipRoutes.mjs';

test( 'membership tabs reuse commerce screens and retain pagination parameters', () => {
	const routes = Object.keys( MEMBERSHIP_SCREENS ).map( ( path ) => ( {
		path,
		element: { path },
	} ) );
	const aliases = membershipRoutes( routes, ( Screen, active ) => ( {
		Screen,
		active,
	} ) );
	assert.deepEqual(
		aliases.map( ( route ) => route.path ),
		[
			'/memberships/coupons',
			'/memberships/orders',
			'/memberships/orders/:page',
			'/memberships/subscriptions',
			'/memberships/subscriptions/:page',
		]
	);
	for ( const alias of aliases ) {
		const source = alias.path.replace( '/memberships', '' );
		assert.equal(
			alias.element.Screen,
			routes.find( ( route ) => route.path === source ).element
		);
		assert.equal( alias.element.active, MEMBERSHIP_SCREENS[ source ] );
	}
	assert.deepEqual(
		MEMBERSHIP_TABS.map( ( tab ) => tab.id ),
		[ 'plans', 'coupons', 'orders', 'subscriptions' ]
	);
} );

test( 'legacy detail and paginated routes retain the matching Membership section', () => {
	assert.equal( MEMBERSHIP_SCREENS[ '/order-edit/:id' ], 'orders' );
	assert.equal(
		MEMBERSHIP_SCREENS[ '/subscription-edit/:id' ],
		'subscriptions'
	);
	assert.equal( MEMBERSHIP_SCREENS[ '/orders/:page' ], 'orders' );
	assert.equal(
		MEMBERSHIP_SCREENS[ '/subscriptions/:page' ],
		'subscriptions'
	);
	assert.deepEqual(
		membershipRoutes( [], () =>
			assert.fail( 'Missing screens should be skipped' )
		),
		[]
	);
} );
