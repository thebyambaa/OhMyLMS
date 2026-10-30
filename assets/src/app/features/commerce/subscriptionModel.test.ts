import {
	activeSubscription,
	pausedSubscription,
	subscriptionDetail,
} from './subscriptionFixtures';
import {
	EMPTY_SUBSCRIPTION_QUERY,
	actionConsequence,
	amountLabel,
	availableActions,
	billingLabel,
	buildSubscriptionQuery,
	parseSubscriptionDetail,
	parseSubscriptionSummary,
	subscriptionPath,
	subscriptionStatusLabel,
} from './subscriptionModel';

describe( 'subscription parsing', () => {
	it( 'accepts a list row and drops unknown properties', () => {
		expect(
			parseSubscriptionSummary( {
				...activeSubscription,
				_links: { self: [] },
			} )
		).toEqual( activeSubscription );
	} );

	it( 'accepts a full subscription', () => {
		expect( parseSubscriptionDetail( subscriptionDetail ) ).toEqual(
			subscriptionDetail
		);
	} );

	it.each( [
		[ 'an unknown status', { status: 'paused' } ],
		[ 'an unknown action', { allowed_actions: [ 'expire' ] } ],
		[ 'a float amount', { recurring_amount: 25 } ],
		[ 'a malformed amount', { recurring_amount: '25,00' } ],
		[ 'a zero interval', { billing_interval: 0 } ],
		[ 'an unknown period', { billing_period: 'fortnight' } ],
		[ 'a missing id', { id: undefined } ],
		[ 'a numeric name', { customer_name: 12 } ],
	] )( 'rejects %s', ( _label, change ) => {
		expect( () =>
			parseSubscriptionSummary( { ...activeSubscription, ...change } )
		).toThrow( expect.objectContaining( { code: 'invalid_response' } ) );
	} );

	it( 'rejects a detail without notes or with a bad related order', () => {
		expect( () =>
			parseSubscriptionDetail( { ...subscriptionDetail, notes: null } )
		).toThrow();
		expect( () =>
			parseSubscriptionDetail( {
				...subscriptionDetail,
				related_orders: [
					{
						...subscriptionDetail.related_orders[ 0 ],
						relationship: 'child',
					},
				],
			} )
		).toThrow();
		expect( () => parseSubscriptionDetail( [] ) ).toThrow();
	} );
} );

describe( 'subscription helpers', () => {
	it( 'builds a bounded query and omits blank filters', () => {
		expect( buildSubscriptionQuery( EMPTY_SUBSCRIPTION_QUERY ) ).toEqual( {
			page: 1,
			per_page: 20,
			orderby: 'date',
			order: 'desc',
		} );
		expect(
			buildSubscriptionQuery( {
				...EMPTY_SUBSCRIPTION_QUERY,
				page: 3,
				search: '  ada ',
				status: 'on-hold',
				order: 'asc',
			} )
		).toEqual( {
			page: 3,
			per_page: 20,
			orderby: 'date',
			order: 'asc',
			search: 'ada',
			status: 'on-hold',
		} );
	} );

	it( 'labels statuses, billing and amounts', () => {
		expect( subscriptionStatusLabel( 'on-hold' ) ).toBe(
			'Paused (on hold)'
		);
		expect( subscriptionStatusLabel( 'custom' ) ).toBe( 'custom' );
		expect( billingLabel( 'month', 1 ) ).toBe( 'Every month' );
		expect( billingLabel( 'week', 2 ) ).toBe( 'Every 2 weeks' );
		expect( billingLabel( '', 1 ) ).toBe( '' );
		expect( amountLabel( activeSubscription ) ).toMatch(
			/25\.00 · Every month$/
		);
		expect(
			amountLabel( { ...activeSubscription, billing_period: '' } )
		).not.toContain( '·' );
		expect( subscriptionPath( 501 ) ).toBe( '/subscriptions/501' );
	} );

	it( 'offers only the actions the server allows, in a fixed order', () => {
		expect( availableActions( activeSubscription ) ).toEqual( [
			'pause',
			'cancel',
		] );
		expect(
			availableActions( {
				...pausedSubscription,
				allowed_actions: [ 'cancel', 'resume' ],
			} )
		).toEqual( [ 'resume', 'cancel' ] );
		expect(
			availableActions( { ...activeSubscription, allowed_actions: [] } )
		).toEqual( [] );
	} );

	it( 'explains that cancelling cannot be undone', () => {
		expect( actionConsequence( 'cancel' ) ).toMatch( /cannot be undone/ );
		expect( actionConsequence( 'pause' ) ).toMatch(
			/renewal payments stop/
		);
	} );
} );
