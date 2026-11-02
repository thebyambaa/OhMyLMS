import {
	courseCsv,
	masteryCsv,
	rollupCsv,
	rosterCsv,
	transcriptCsv,
} from './exports';
import { grid, mastery, rollup, roster, transcript, SCALE } from './fixtures';
import {
	cellDescription,
	cellText,
	cellTone,
	csvField,
	formatPercent,
	groupScopes,
	letterFor,
	moveFocus,
	paramsFromQuery,
	queryFromParams,
	toCsv,
	weightsError,
	weightsText,
} from './model';

describe( 'gradebook query', () => {
	it( 'reads valid parameters and drops malformed ones', () => {
		const query = queryFromParams(
			new URLSearchParams(
				'scope=class:7&course=12&audience=class:7&status=missing&type=homework&module=5&s=ana&orderby=total&order=desc&page=3'
			)
		);
		expect( query ).toMatchObject( {
			scope: 'class:7',
			course: 12,
			audience: 'class:7',
			status: 'missing',
			type: 'homework',
			module: 5,
			search: 'ana',
			orderby: 'total',
			order: 'desc',
			page: 3,
		} );
		const bad = queryFromParams(
			new URLSearchParams(
				'scope=district:1&course=-4&audience=class:x&status=evil&type=Bad Type&orderby=email&page=0'
			)
		);
		expect( bad ).toMatchObject( {
			scope: '',
			course: 0,
			audience: 'all',
			status: '',
			type: '',
			orderby: 'name',
			page: 1,
		} );
	} );

	it( 'writes only non-default parameters and round-trips', () => {
		const query = queryFromParams(
			new URLSearchParams( 'course=12&audience=individuals' )
		);
		const params = paramsFromQuery( query );
		expect( params.toString() ).toBe( 'course=12&audience=individuals' );
		expect( queryFromParams( params ) ).toEqual( query );
	} );
} );

describe( 'cells', () => {
	const [ ana, bat ] = grid.rows;

	it( 'shows each state in words, not only colour', () => {
		expect( cellText( ana!.cells[ '101' ] ) ).toBe( '80%' );
		expect( cellText( ana!.cells[ '102' ] ) ).toBe( 'missing' );
		expect( cellText( ana!.cells[ '103' ] ) ).toBe( '—' );
		expect( cellText( bat!.cells[ '102' ] ) ).toBe( 'to grade' );
		expect( cellText( bat!.cells[ '103' ] ) ).toBe( '–' );
		expect( cellText( null ) ).toBe( '·' );
	} );

	it( 'keeps missing and not assigned apart', () => {
		expect( cellTone( ana!.cells[ '102' ] ) ).toBe( 'missing' );
		expect( cellTone( ana!.cells[ '103' ] ) ).toBe( 'not-assigned' );
		expect( cellDescription( ana!.cells[ '102' ] ) ).toBe( 'Missing' );
		expect( cellDescription( ana!.cells[ '103' ] ) ).toBe( 'Not assigned' );
	} );

	it( 'describes an override with the computed value', () => {
		expect( cellDescription( bat!.cells[ '101' ] ) ).toBe(
			'90% (9 of 10), set manually; computed: To grade'
		);
	} );

	it( 'bands scores', () => {
		const at = ( percent: number ) =>
			cellTone( { state: 'score', score: percent, max: 100, percent } );
		expect( [ at( 95 ), at( 75 ), at( 65 ), at( 30 ) ] ).toEqual( [
			'high',
			'good',
			'fair',
			'low',
		] );
	} );

	it( 'formats percentages and letters', () => {
		expect( formatPercent( 79.46 ) ).toBe( '79.5%' );
		expect( formatPercent( null ) ).toBe( '—' );
		expect( letterFor( 90, SCALE ) ).toBe( 'A' );
		expect( letterFor( 89.99, SCALE ) ).toBe( 'B' );
		expect( letterFor( null, SCALE ) ).toBeNull();
	} );
} );

describe( 'weights', () => {
	it( 'describes weights', () => {
		expect( weightsText( { homework: 30, quiz: 70 } ) ).toBe(
			'homework 30% · quiz 70%'
		);
		expect( weightsText( {} ) ).toBe(
			'total points (no category weights)'
		);
	} );

	it( 'validates like the server', () => {
		expect( weightsError( { quiz: 50, test: 50 } ) ).toBe( '' );
		expect( weightsError( {} ) ).toBe( '' );
		expect( weightsError( { quiz: 50, test: 40 } ) ).toMatch(
			/add up to 90%/
		);
		expect( weightsError( { Quiz: 100 } ) ).toMatch( /lowercase/ );
		expect( weightsError( { quiz: 100.005 } ) ).toMatch( /two decimals/ );
		expect( weightsError( { a: -10, b: 110 } ) ).toMatch( /0 to 100/ );
	} );
} );

describe( 'grid keyboard model', () => {
	const size = { rows: 30, cols: 8 };

	it( 'moves with arrows and stops at the edges', () => {
		expect(
			moveFocus( { row: 2, col: 2 }, { key: 'ArrowRight' }, size )
		).toEqual( { row: 2, col: 3 } );
		expect(
			moveFocus( { row: 2, col: 2 }, { key: 'ArrowUp' }, size )
		).toEqual( { row: 1, col: 2 } );
		expect(
			moveFocus( { row: 0, col: 0 }, { key: 'ArrowLeft' }, size )
		).toEqual( { row: 0, col: 0 } );
		expect(
			moveFocus( { row: 29, col: 7 }, { key: 'ArrowDown' }, size )
		).toEqual( { row: 29, col: 7 } );
	} );

	it( 'supports Home, End, Ctrl+Home/End and paging', () => {
		expect(
			moveFocus( { row: 4, col: 5 }, { key: 'Home' }, size )
		).toEqual( { row: 4, col: 0 } );
		expect( moveFocus( { row: 4, col: 5 }, { key: 'End' }, size ) ).toEqual(
			{ row: 4, col: 7 }
		);
		expect(
			moveFocus(
				{ row: 4, col: 5 },
				{ key: 'Home', ctrlKey: true },
				size
			)
		).toEqual( { row: 0, col: 0 } );
		expect(
			moveFocus( { row: 4, col: 5 }, { key: 'End', metaKey: true }, size )
		).toEqual( { row: 29, col: 7 } );
		expect(
			moveFocus( { row: 4, col: 5 }, { key: 'PageDown' }, size )
		).toEqual( { row: 14, col: 5 } );
		expect(
			moveFocus( { row: 4, col: 5 }, { key: 'PageUp' }, size )
		).toEqual( { row: 0, col: 5 } );
		expect(
			moveFocus( { row: 4, col: 5 }, { key: 'a' }, size )
		).toBeNull();
	} );
} );

describe( 'CSV', () => {
	it( 'quotes fields and defuses formulas', () => {
		expect( csvField( 'a "b"' ) ).toBe( '"a ""b"""' );
		expect( csvField( '=HYPERLINK("x")' ) ).toBe( '"\'=HYPERLINK(""x"")"' );
		expect( csvField( '-1+1' ) ).toBe( '"\'-1+1"' );
		expect( csvField( -5 ) ).toBe( '"-5"' );
		expect( csvField( null ) ).toBe( '""' );
		expect(
			toCsv( [
				[ 'a', 1 ],
				[ 'b', 2 ],
			] )
		).toBe( '"a","1"\r\n"b","2"' );
	} );

	it( 'exports every view', () => {
		const course = courseCsv( grid ).split( '\r\n' );
		expect( course ).toHaveLength( 3 );
		expect( course[ 0 ] ).toContain( '"Linear quiz (quiz, /10)"' );
		expect( course[ 1 ] ).toBe(
			'"Ana Example","active","roster","Grade 8A","32","F","32","80","missing","not assigned"'
		);
		expect( rosterCsv( roster ) ).toContain(
			'"Chen Sample","not enrolled","not enrolled","not enrolled"'
		);
		expect( transcriptCsv( transcript ) ).toContain(
			'"Skill","Linear equations","proficient","88"'
		);
		expect( masteryCsv( mastery ).split( '\r\n' )[ 2 ] ).toBe(
			'"Bat Demo",""'
		);
		expect( rollupCsv( rollup.nodes ).split( '\r\n' ) ).toHaveLength( 4 );
	} );
} );

describe( 'scopes', () => {
	it( 'groups scopes by school', () => {
		const { top, groups } = groupScopes( [
			{
				id: 'all',
				type: 'all',
				label: 'All schools',
				school_id: 0,
				school_name: '',
			},
			{
				id: 'school:1',
				type: 'school',
				label: 'Alpha',
				school_id: 1,
				school_name: 'Alpha',
			},
			{
				id: 'class:7',
				type: 'class',
				label: '8A',
				school_id: 1,
				school_name: 'Alpha',
			},
			{
				id: 'class:9',
				type: 'class',
				label: 'Club',
				school_id: 0,
				school_name: '',
			},
		] );
		expect( top ).toEqual( [ { value: 'all', label: 'All schools' } ] );
		expect( groups.map( ( group ) => group.label ) ).toEqual( [
			'Alpha',
			'Independent classes',
		] );
		expect( groups[ 0 ]!.options ).toEqual( [
			{ value: 'school:1', label: 'All of Alpha' },
			{ value: 'class:7', label: '8A' },
		] );
	} );
} );
