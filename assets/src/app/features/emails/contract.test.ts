import contract from './contract.json';
import { editorPath } from './model';

describe( 'emails REST contract', () => {
	it( 'describes every route the client calls', () => {
		const paths = contract.routes.map(
			( route ) =>
				`${ [ route.method ].flat().join( '|' ) } ${ route.path }`
		);
		expect( paths ).toEqual( [
			'GET /emails',
			'GET /emails/settings',
			'PUT|PATCH|POST /emails/settings',
			'GET /emails/{id}',
			'PUT|PATCH|POST /emails/{id}',
			'POST /emails/{id}/preview',
			'POST /emails/{id}/test',
		] );
		expect( contract.capability ).toBe( 'manage_options' );
	} );

	it( 'lists the twelve emails once and every id is URL safe', () => {
		expect( new Set( contract.emailIds ).size ).toBe( 12 );
		for ( const id of contract.emailIds ) {
			expect( id ).toMatch( /^[a-z][a-z0-9_]*$/ );
			expect( editorPath( id ) ).toBe( `/emails?email=${ id }` );
		}
		expect( Object.keys( contract.mergeTags.perEmail ).sort() ).toEqual(
			[ ...contract.emailIds ].sort()
		);
	} );
} );
