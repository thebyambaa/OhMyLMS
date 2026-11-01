import {
	boxStyle,
	fromDrag,
	fromPercentText,
	isBounds,
	MIN_SIDE,
	moveBy,
	nudge,
	pointIn,
	resizeBy,
	sameBounds,
	toPercentText,
	type Bounds,
} from './cropGeometry';

const rect = { left: 100, top: 50, width: 400, height: 200 };

describe( 'crop geometry', () => {
	it( 'maps pointer positions to page fractions, clamped to the page', () => {
		expect( pointIn( rect, 300, 150 ) ).toEqual( { x: 0.5, y: 0.5 } );
		expect( pointIn( rect, 0, 400 ) ).toEqual( { x: 0, y: 1 } );
		expect( pointIn( { ...rect, width: 0 }, 300, 150 ) ).toEqual( {
			x: 0,
			y: 0,
		} );
	} );

	it( 'normalises a drag in any direction and ignores clicks', () => {
		expect( fromDrag( { x: 0.8, y: 0.9 }, { x: 0.2, y: 0.1 } ) ).toEqual( [
			0.2, 0.1, 0.8, 0.9,
		] );
		expect( fromDrag( { x: 0.2, y: 0.9 }, { x: 0.8, y: 0.1 } ) ).toEqual( [
			0.2, 0.1, 0.8, 0.9,
		] );
		expect( fromDrag( { x: 0.5, y: 0.5 }, { x: 0.5, y: 0.5 } ) ).toBeNull();
		expect(
			fromDrag( { x: 0.5, y: 0.5 }, { x: 0.5 + MIN_SIDE / 2, y: 0.9 } )
		).toBeNull();
		expect( fromDrag( { x: -1, y: 0 }, { x: 2, y: 1 } ) ).toEqual( [
			0, 0, 1, 1,
		] );
	} );

	it( 'rounds to the six decimals the server keeps', () => {
		expect(
			fromDrag( { x: 1 / 3, y: 0 }, { x: 2 / 3, y: 1 / 7 } )
		).toEqual( [ 0.333333, 0, 0.666667, 0.142857 ] );
	} );

	it( 'moves a crop without leaving the page or changing its size', () => {
		const crop: Bounds = [ 0.1, 0.2, 0.4, 0.5 ];
		expect( moveBy( crop, 0.1, 0.1 ) ).toEqual( [ 0.2, 0.3, 0.5, 0.6 ] );
		expect( moveBy( crop, 2, -2 ) ).toEqual( [ 0.7, 0, 1, 0.3 ] );
		expect( moveBy( crop, -2, 2 ) ).toEqual( [ 0, 0.7, 0.3, 1 ] );
	} );

	it( 'resizes from the right and bottom edges, keeping a minimum size', () => {
		const crop: Bounds = [ 0.1, 0.2, 0.4, 0.5 ];
		expect( resizeBy( crop, 0.1, -0.1 ) ).toEqual( [ 0.1, 0.2, 0.5, 0.4 ] );
		expect( MIN_SIDE ).toBe( 0.005 );
		expect( resizeBy( crop, -1, -1 ) ).toEqual( [
			0.1, 0.2, 0.105, 0.205,
		] );
		expect( resizeBy( crop, 2, 2 ) ).toEqual( [ 0.1, 0.2, 1, 1 ] );
	} );

	it( 'nudges with arrow keys: move, Shift resizes, Alt is finer', () => {
		const crop: Bounds = [ 0.1, 0.2, 0.4, 0.5 ];
		expect( nudge( crop, 'ArrowRight', {} ) ).toEqual( [
			0.11, 0.2, 0.41, 0.5,
		] );
		expect( nudge( crop, 'ArrowUp', { altKey: true } ) ).toEqual( [
			0.1, 0.199, 0.4, 0.499,
		] );
		expect( nudge( crop, 'ArrowDown', { shiftKey: true } ) ).toEqual( [
			0.1, 0.2, 0.4, 0.51,
		] );
		expect( nudge( crop, 'ArrowLeft', { shiftKey: true } ) ).toEqual( [
			0.1, 0.2, 0.39, 0.5,
		] );
		expect( nudge( crop, 'Enter', {} ) ).toBeNull();
	} );

	it( 'converts to and from the percentage fields', () => {
		expect( toPercentText( [ 0.1, 0.125, 0.333333, 1 ] ) ).toEqual( [
			'10',
			'12.5',
			'33.33',
			'100',
		] );
		expect( fromPercentText( [ '10', '12,5', ' 33.3 ', '100' ] ) ).toEqual(
			[ 0.1, 0.125, 0.333, 1 ]
		);
		expect( fromPercentText( [ '50', '0', '40', '10' ] ) ).toBeNull();
		expect( fromPercentText( [ '0', '0', '101', '10' ] ) ).toBeNull();
		expect( fromPercentText( [ '0', '0', '', '10' ] ) ).toBeNull();
		expect( fromPercentText( [ '0', '0', '1e2', '10' ] ) ).toBeNull();
		expect( fromPercentText( [ '-1', '0', '50', '10' ] ) ).toBeNull();
	} );

	it( 'validates and compares bounds', () => {
		expect( isBounds( [ 0, 0, 1, 1 ] ) ).toBe( true );
		expect( isBounds( [ 0.5, 0, 0.5, 1 ] ) ).toBe( false );
		expect( isBounds( [ 0, 0, 1 ] ) ).toBe( false );
		expect( isBounds( [ 0, 0, Number.NaN, 1 ] ) ).toBe( false );
		expect( isBounds( [ 0, 0, '1', 1 ] ) ).toBe( false );
		expect(
			sameBounds( [ 0.1, 0.2, 0.3, 0.4 ], [ 0.1000001, 0.2, 0.3, 0.4 ] )
		).toBe( true );
		expect(
			sameBounds( [ 0.1, 0.2, 0.3, 0.4 ], [ 0.11, 0.2, 0.3, 0.4 ] )
		).toBe( false );
		expect( sameBounds( undefined, [ 0, 0, 1, 1 ] ) ).toBe( false );
	} );

	it( 'positions a box in percent', () => {
		expect( boxStyle( [ 0.25, 0.5, 0.75, 1 ] ) ).toEqual( {
			left: '25%',
			top: '50%',
			width: '50%',
			height: '50%',
		} );
	} );
} );
