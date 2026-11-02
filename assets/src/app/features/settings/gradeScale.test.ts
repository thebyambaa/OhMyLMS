import {
	parseGradeScale,
	scaleRanges,
	validateGradeScale,
	type GradeScaleValues,
} from './gradeScale';

const values: GradeScaleValues = {
	a_min: 90,
	b_min: 80,
	c_min: 70,
	d_min: 60,
	missing_as_zero: true,
};

describe( 'grade scale settings', () => {
	it( 'parses the settings group', () => {
		expect(
			parseGradeScale( {
				group: 'grade-scale',
				values,
				changed: [ 'a_min' ],
			} )
		).toEqual( { values, changed: [ 'a_min' ] } );
		expect( () =>
			parseGradeScale( { group: 'at-risk', values, changed: [] } )
		).toThrow();
		expect( () =>
			parseGradeScale( {
				group: 'grade-scale',
				values: { ...values, a_min: 90.5 },
				changed: [],
			} )
		).toThrow();
	} );

	it( 'requires whole numbers from 1 to 100 that descend', () => {
		expect( validateGradeScale( values ) ).toEqual( {} );
		expect( validateGradeScale( { ...values, a_min: 101 } ) ).toEqual( {
			a_min: 'Enter a number from 1 to 100.',
		} );
		expect( validateGradeScale( { ...values, b_min: 90 } ) ).toEqual( {
			b_min: 'B must start below A.',
		} );
		expect(
			validateGradeScale( { ...values, d_min: Number.NaN } )
		).toEqual( { d_min: 'Enter a number from 1 to 100.' } );
	} );

	it( 'previews the ranges, F included', () => {
		expect( scaleRanges( { ...values, c_min: 75 } ) ).toEqual( [
			{ letter: 'A', range: '90–100%' },
			{ letter: 'B', range: '80–89%' },
			{ letter: 'C', range: '75–79%' },
			{ letter: 'D', range: '60–74%' },
			{ letter: 'F', range: '0–59%' },
		] );
	} );
} );
