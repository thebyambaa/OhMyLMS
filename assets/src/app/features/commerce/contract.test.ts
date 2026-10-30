import contract from './contract.json';
import { ORDER_STATUSES } from './model';
import * as api from './api';

describe( 'commerce REST contract', () => {
	it( 'describes every route the client calls', () => {
		const paths = contract.routes.map(
			( route ) =>
				`${ [ route.method ].flat().join( '|' ) } ${ route.path }`
		);
		expect( paths ).toEqual( [
			'GET /orders',
			'GET /orders/status-counts',
			'GET /orders/{id}',
			'PUT|PATCH|POST /orders/{id}',
			'DELETE /orders/{id}',
			'GET /orders/{id}/notes',
			'POST /orders/{id}/notes',
			'GET /orders/{id}/refunds',
			'POST /orders/{id}/refunds',
			'GET /coupons',
			'POST /coupons',
			'GET /coupons/{id}',
			'PUT|PATCH|POST /coupons/{id}',
			'DELETE /coupons/{id}',
		] );
		expect( contract.capability ).toBe( 'manage_options' );
		expect( contract.namespace ).toBe( 'ohmylms-commerce/v1' );
	} );

	it( 'lists the same statuses as the client and keeps refunded terminal', () => {
		expect( contract.orderStatuses ).toEqual( [ ...ORDER_STATUSES ] );
		expect( Object.keys( contract.transitions ).sort() ).toEqual(
			[ ...ORDER_STATUSES ].sort()
		);
		expect( contract.transitions.refunded ).toEqual( [] );
		for ( const targets of Object.values( contract.transitions ) ) {
			for ( const status of targets ) {
				expect( contract.settableStatuses ).toContain( status );
			}
		}
		expect( contract.settableStatuses ).not.toContain( 'refunded' );
	} );

	it( 'has no client function for the server-only refund routes', () => {
		expect( Object.keys( api ) ).not.toContain( 'createRefund' );
	} );
} );
