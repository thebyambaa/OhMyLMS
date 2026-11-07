import { describe, expect, it } from 'vitest';
import {
	bulkRequest,
	folderPaths,
	organizationLabels,
	parseAssignments,
	parseTerms,
} from './model';

const terms = parseTerms( {
	folders: [
		{ id: 1, name: 'Algebra', parent: 0 },
		{ id: 2, name: 'Linear', parent: 1 },
		{ id: 3, name: 'Geometry', parent: 0 },
	],
	tags: [ { id: 9, name: 'review', parent: 0 } ],
} );

describe( 'content bank model', () => {
	it( 'rejects folders it cannot read', () => {
		expect( () => parseTerms( { folders: [ {} ], tags: [] } ) ).toThrow();
		expect( () => parseTerms( { folders: [] } ) ).toThrow();
	} );

	it( 'names a folder with its parents, in order', () => {
		expect( folderPaths( terms.folders ).map( ( f ) => f.label ) ).toEqual(
			[ 'Algebra', 'Algebra / Linear', 'Geometry' ]
		);
	} );

	it( 'does not loop on a folder that is its own ancestor', () => {
		const loop = parseTerms( {
			folders: [
				{ id: 1, name: 'A', parent: 2 },
				{ id: 2, name: 'B', parent: 1 },
			],
			tags: [],
		} );
		expect( folderPaths( loop.folders ) ).toHaveLength( 2 );
	} );

	it( 'reads assignments keyed by item, and an empty list', () => {
		expect(
			parseAssignments( { '5': { folders: [ 2 ], tags: [ 9, 'x' ] } } )
		).toEqual( { 5: { folders: [ 2 ], tags: [ 9 ] } } );
		expect( parseAssignments( [] ) ).toEqual( {} );
	} );

	it( 'labels an item with folder paths and tag names', () => {
		expect(
			organizationLabels( terms, { folders: [ 2 ], tags: [ 9 ] } )
		).toEqual( { folders: [ 'Algebra / Linear' ], tags: [ 'review' ] } );
		expect( organizationLabels( terms ) ).toEqual( {
			folders: [],
			tags: [],
		} );
	} );

	it( 'turns bulk actions into requests', () => {
		expect( bulkRequest( 'add-folder:2', [ 4, 5 ] ) ).toEqual( {
			ids: [ 4, 5 ],
			folder_ids: [ 2 ],
			mode: 'add',
		} );
		expect( bulkRequest( 'add-tag:9', [ 4 ] ) ).toEqual( {
			ids: [ 4 ],
			tag_ids: [ 9 ],
			mode: 'add',
		} );
		expect( bulkRequest( 'clear-folders', [ 4 ] ) ).toEqual( {
			ids: [ 4 ],
			folder_ids: [],
			mode: 'replace',
		} );
		expect( bulkRequest( 'clear-tags', [ 4 ] )?.tag_ids ).toEqual( [] );
		expect( bulkRequest( 'add-folder:0', [ 4 ] ) ).toBeNull();
		expect( bulkRequest( 'delete', [ 4 ] ) ).toBeNull();
	} );
} );
