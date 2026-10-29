import { buildQueryString, withQuery } from './query';

describe( 'buildQueryString', () => {
	it( 'encodes values and drops null and undefined', () => {
		expect(
			buildQueryString( {
				search: 'a b&c',
				page: 2,
				flag: false,
				none: null,
				missing: undefined,
			} )
		).toBe( 'search=a%20b%26c&page=2&flag=false' );
	} );

	it( 'serialises arrays as key[] pairs', () => {
		expect( buildQueryString( { include: [ 1, 2 ] } ) ).toBe(
			'include%5B%5D=1&include%5B%5D=2'
		);
	} );

	it( 'keeps empty strings', () => {
		expect( buildQueryString( { s: '' } ) ).toBe( 's=' );
	} );
} );

describe( 'withQuery', () => {
	it( 'returns the path unchanged without parameters', () => {
		expect( withQuery( '/wp/v2/posts' ) ).toBe( '/wp/v2/posts' );
		expect( withQuery( '/wp/v2/posts', { a: undefined } ) ).toBe(
			'/wp/v2/posts'
		);
	} );

	it( 'appends with ? or & as needed', () => {
		expect( withQuery( '/wp/v2/posts', { a: 1 } ) ).toBe(
			'/wp/v2/posts?a=1'
		);
		expect( withQuery( '/wp/v2/posts?x=1', { a: 1 } ) ).toBe(
			'/wp/v2/posts?x=1&a=1'
		);
	} );
} );
