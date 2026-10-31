import { nested } from './fixtures';
import {
	branchKeys,
	countNodes,
	layoutMap,
	MAP_CARD,
	syllabusTree,
} from './mind-map';
import {
	cellProblem,
	cellRef,
	cellValue,
	duplicateCodes,
	moveCell,
	pastedRows,
	planChanges,
	sheetRows,
	type SheetRow,
} from './sheet';

describe( 'syllabus mind map', () => {
	it( 'builds the tree in saved order, groups before child items', () => {
		const root = syllabusTree( nested );
		expect( root.kind ).toBe( 'syllabus' );
		expect( root.children.map( ( node ) => node.key ) ).toEqual( [
			'g7',
			'c6',
		] );
		expect(
			root.children[ 1 ]?.children.map( ( node ) => node.key )
		).toEqual( [ 'g8', 'c13' ] );
		expect( countNodes( root ) ).toEqual( {
			syllabus: 1,
			item: 2,
			group: 2,
			skill: 3,
		} );
	} );

	it( 'lays out leaves one per row with parents centred beside them', () => {
		const root = syllabusTree( nested );
		const open = layoutMap( root, new Set() );
		const leaves = open.nodes.filter(
			( placed ) => ! placed.node.children.length
		);
		expect( leaves.map( ( placed ) => placed.y ) ).toEqual(
			leaves.map(
				( _placed, index ) => MAP_CARD.padding + index * MAP_CARD.row
			)
		);
		const group = open.nodes.find( ( placed ) => 'g7' === placed.node.key );
		expect( group?.x ).toBe( MAP_CARD.padding + MAP_CARD.column );
		expect( group?.y ).toBe(
			( MAP_CARD.padding + MAP_CARD.padding + MAP_CARD.row ) / 2
		);
		expect( open.edges ).toHaveLength( open.nodes.length - 1 );
	} );

	it( 'collapses branches and marks them folded', () => {
		const root = syllabusTree( nested );
		const closed = layoutMap(
			root,
			new Set( branchKeys( root ).slice( 1 ) )
		);
		expect( closed.nodes.map( ( placed ) => placed.node.key ) ).toEqual( [
			'c5',
			'g7',
			'c6',
		] );
		expect(
			closed.nodes.every(
				( placed ) => 'c5' === placed.node.key || placed.folded
			)
		).toBe( true );
	} );

	it( 'searches names and codes, opening matching branches', () => {
		const root = syllabusTree( nested );
		const found = layoutMap( root, new Set( branchKeys( root ) ), 'g.1' );
		expect( found.nodes.map( ( placed ) => placed.node.key ) ).toEqual( [
			'c5',
			'c6',
			'g8',
			'g8s12',
		] );
		expect( layoutMap( root, new Set(), 'nothing like it' ).nodes ).toEqual(
			[]
		);
	} );
} );

describe( 'syllabus spreadsheet', () => {
	const rows = sheetRows( nested );

	it( 'flattens the outline without losing identities', () => {
		expect( rows.map( ( row ) => row.key ) ).toEqual( [
			'g7s10',
			'g7s11',
			'g8s12',
			'c13',
		] );
		expect( rows[ 3 ] ).toMatchObject( {
			path: 'Geometry',
			unit: 'Circles',
			groupId: null,
			termId: null,
		} );
		expect( cellRef( rows[ 0 ] as SheetRow, 'unit' ) ).toBeNull();
		expect( cellRef( rows[ 2 ] as SheetRow, 'unitCode' ) ).toEqual( {
			kind: 'item',
			id: 6,
			field: 'code',
		} );
		expect( cellRef( rows[ 3 ] as SheetRow, 'skill' ) ).toBeNull();
	} );

	it( 'plans one change per identity with the value it was edited from', () => {
		const edits = {
			'group:7:name': 'Algebra 1',
			'skill:10:code': ' A.9 ',
			'skill:11:name': 'Use expressions',
		};
		expect( cellValue( rows[ 1 ] as SheetRow, 'group', edits ) ).toBe(
			'Algebra 1'
		);
		expect( planChanges( rows, edits ) ).toEqual( [
			{
				kind: 'group',
				id: 7,
				field: 'name',
				previous: 'Algebra',
				value: 'Algebra 1',
			},
			{
				kind: 'skill',
				id: 10,
				field: 'code',
				previous: 'A.1',
				value: 'A.9',
			},
		] );
	} );

	it( 'validates names, code lengths and duplicate skill codes', () => {
		expect(
			cellProblem( { kind: 'group', id: 7, field: 'name' }, '  ' )
		).toMatch( /required/ );
		expect(
			cellProblem(
				{ kind: 'skill', id: 10, field: 'code' },
				'x'.repeat( 41 )
			)
		).toMatch( /40/ );
		expect(
			cellProblem(
				{ kind: 'group', id: 7, field: 'code' },
				'x'.repeat( 41 )
			)
		).toBeNull();
		expect(
			[ ...duplicateCodes( rows, { 'skill:11:code': 'a.1' } ) ].sort()
		).toEqual( [ 10, 11 ] );
		expect(
			duplicateCodes( rows, {
				'skill:10:code': 'A.2',
				'skill:11:code': 'A.1',
			} ).size
		).toBe( 0 );
	} );

	it( 'reads pasted rows, repeating merged unit and group cells', () => {
		expect(
			pastedRows(
				'Unit\tGroup\tCode\tSkill\nGeometry › Circles\tArcs\tC.1\tArc length\n\t\tC.2\tSector area\nNumber\t\t\tCount\n'
			)
		).toEqual( [
			{
				line: 2,
				content: 'Geometry > Circles',
				group: 'Arcs',
				skill_code: 'C.1',
				skill: 'Arc length',
			},
			{
				line: 3,
				content: 'Geometry > Circles',
				group: 'Arcs',
				skill_code: 'C.2',
				skill: 'Sector area',
			},
			{
				line: 4,
				content: 'Number',
				group: '',
				skill_code: '',
				skill: 'Count',
			},
		] );
	} );

	it( 'moves within the grid bounds', () => {
		const size = { rows: 3, columns: 7 };
		expect( moveCell( { row: 0, column: 0 }, 'ArrowUp', size ) ).toEqual( {
			row: 0,
			column: 0,
		} );
		expect( moveCell( { row: 1, column: 2 }, 'End', size ) ).toEqual( {
			row: 1,
			column: 6,
		} );
		expect( moveCell( { row: 2, column: 2 }, 'ArrowDown', size ) ).toEqual(
			{ row: 2, column: 2 }
		);
		expect( moveCell( { row: 1, column: 1 }, 'x', size ) ).toBeNull();
	} );
} );
