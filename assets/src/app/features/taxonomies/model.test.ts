import { bundle, child, detail, orphan, root } from './fixtures';
import {
	buildTermQuery,
	getTaxonomies,
	parentLabel,
	parseTerm,
	parseTermDetail,
} from './model';

describe( 'term boundary', () => {
	it( 'keeps exactly the listed fields', () => {
		expect(
			parseTerm( { ...root, _links: {}, term_id: 3, courses: [] } )
		).toEqual( root );
	} );
	it.each( [
		[ 'a string id', { id: '3' } ],
		[ 'a zero id', { id: 0 } ],
		[ 'a negative parent', { parent: -1 } ],
		[ 'a fractional count', { count: 1.5 } ],
		[ 'a missing slug', { slug: undefined } ],
		[ 'a non-string description', { description: null } ],
	] )( 'rejects %s', ( _label, patch ) => {
		expect( () => parseTerm( { ...root, ...patch } ) ).toThrow();
	} );
	it( 'rejects non-objects', () => {
		expect( () => parseTerm( null ) ).toThrow();
		expect( () => parseTerm( [] ) ).toThrow();
	} );
	it( 'validates linked courses on the detail', () => {
		expect( parseTermDetail( detail ) ).toEqual( detail );
		expect( () => parseTermDetail( child ) ).toThrow();
		expect( () =>
			parseTermDetail( { ...child, courses: [ { id: 0, title: 'x' } ] } )
		).toThrow();
		expect( () =>
			parseTermDetail( { ...child, courses: [ { id: 1 } ] } )
		).toThrow();
	} );
} );

describe( 'queries and labels', () => {
	it( 'trims the search and omits it when blank', () => {
		expect( buildTermQuery( { page: 2, search: ' alg ' } ) ).toEqual( {
			page: 2,
			per_page: 20,
			orderby: 'name',
			order: 'asc',
			search: 'alg',
		} );
		expect(
			buildTermQuery( { page: 1, search: '   ' } )
		).not.toHaveProperty( 'search' );
	} );
	it( 'describes the parent from the loaded page, or by ID', () => {
		expect( parentLabel( root, [ root, child ] ) ).toBe( 'Top level' );
		expect( parentLabel( child, [ root, child ] ) ).toBe( 'Mathematics' );
		expect( parentLabel( orphan, [ root, child, orphan ] ) ).toBe( '#99' );
	} );
	it( 'lists both taxonomies with their owning screens', () => {
		const [ categories, tags ] = getTaxonomies();
		expect( categories?.kind ).toBe( 'categories' );
		expect( categories?.manageUrl ).toContain( 'curriculum' );
		expect( tags?.kind ).toBe( 'tags' );
		expect( tags?.manageUrl ).toContain( 'bundles' );
		expect( bundle.slug ).toBe( 'b8' );
	} );
} );
