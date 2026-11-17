import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import {
	TYPES,
	UNITS,
	angleDegrees,
	clockAngles,
	clockText,
	convert,
	defaultConfig,
	distance,
	formatNumber,
	fractionFacts,
	isBowTie,
	normalizeActivity,
	polygonArea,
	statistics,
} from '../../assets/src/blocks/interactive-activity/activities.mjs';

const root = path.resolve( import.meta.dirname, '../..' );
const near = ( actual, expected ) =>
	assert.ok( Math.abs( actual - expected ) < 1e-9, `${ actual } !== ${ expected }` );

test( 'every activity type has a default that its own contract keeps', () => {
	TYPES.forEach( ( type ) => {
		const config = defaultConfig( type );
		assert.ok( config.version >= 1, type );
		assert.deepEqual( normalizeActivity( type, config ), config, type );
	} );
} );

test( 'the contract matches the shared fixture the PHP side is also checked against', () => {
	const cases = JSON.parse(
		fs.readFileSync(
			path.join( root, 'tests/fixtures/interactive-activity-contract.json' ),
			'utf8'
		)
	);
	assert.ok( cases.length > 15 );
	cases.forEach( ( { activity, input, expected } ) =>
		assert.deepEqual(
			normalizeActivity( activity, input ),
			expected,
			`${ activity } ${ JSON.stringify( input ) }`
		)
	);
} );

test( 'the PHP block lists the same activities and units as the script', () => {
	const php = fs.readFileSync(
		path.join( root, 'includes/Blocks/InteractiveActivityBlock.php' ),
		'utf8'
	);
	const listed = php.match( /const ACTIVITIES\s*=\s*array\(([^)]*)\)/ )[ 1 ];
	assert.deepEqual(
		[ ...listed.matchAll( /'([^']+)'/g ) ].map( ( match ) => match[ 1 ] ),
		TYPES
	);
	Object.entries( UNITS ).forEach( ( [ quantity, units ] ) => {
		const row = php.match(
			new RegExp( String.raw`'${ quantity }'\s*=>\s*array\(([^)]*)\)` )
		)[ 1 ];
		assert.deepEqual(
			[ ...row.matchAll( /'([^']+)'/g ) ].map( ( match ) => match[ 1 ] ),
			Object.keys( units ),
			quantity
		);
	} );
} );

test( 'fractions reduce, and zero shaded stays zero', () => {
	assert.deepEqual( fractionFacts( 6, 8 ).simple, [ 3, 4 ] );
	near( fractionFacts( 3, 4 ).decimal, 0.75 );
	near( fractionFacts( 1, 8 ).percent, 12.5 );
	assert.deepEqual( fractionFacts( 0, 5 ).simple, [ 0, 1 ] );
} );

test( 'clock hands and digital text', () => {
	assert.deepEqual( clockAngles( 3, 0 ), { hour: 90, minute: 0 } );
	assert.deepEqual( clockAngles( 15, 30 ), { hour: 105, minute: 180 } );
	assert.equal( clockText( 0, 5, false ), '12:05 AM' );
	assert.equal( clockText( 15, 45, false ), '3:45 PM' );
	assert.equal( clockText( 15, 45, true ), '15:45' );
} );

test( 'unit conversion', () => {
	near( convert( 1, 'length', 'm', 'cm' ), 100 );
	near( convert( 2.5, 'mass', 'kg', 'g' ), 2500 );
	near( convert( 90, 'time', 'min', 'h' ), 1.5 );
	near( convert( 1, 'length', 'mi', 'km' ), 1.609344 );
	near( convert( 1, 'volume', 'l', 'ml' ), 1000 );
	assert.equal( formatNumber( convert( 1, 'length', 'in', 'cm' ) ), '2.54' );
} );

test( 'statistics', () => {
	const summary = statistics( [ 2, 4, 4, 10 ] );
	assert.equal( summary.total, 20 );
	assert.equal( summary.mean, 5 );
	assert.equal( summary.median, 4 );
	assert.deepEqual( summary.modes, [ 4 ] );
	assert.equal( summary.range, 8 );
	assert.deepEqual( statistics( [ 1, 2, 3 ] ).modes, [] );
	assert.equal( statistics( [ 1, 2, 3 ] ).median, 2 );
	assert.deepEqual( statistics( [ 1, 1, 2, 2 ] ).modes, [ 1, 2 ] );
} );

test( 'geometry measures', () => {
	near( distance( [ 0, 0 ], [ 3, 4 ] ), 5 );
	near( polygonArea( [ [ 0, 0 ], [ 4, 0 ], [ 0, 3 ] ] ), 6 );
	near( polygonArea( [ [ 0, 0 ], [ 4, 0 ], [ 4, 3 ], [ 0, 3 ] ] ), 12 );
	near( angleDegrees( [ 1, 0 ], [ 0, 0 ], [ 0, 1 ] ), 90 );
	near( angleDegrees( [ 1, 0 ], [ 0, 0 ], [ -1, 0 ] ), 180 );
	assert.equal( angleDegrees( [ 0, 0 ], [ 0, 0 ], [ 1, 1 ] ), 0 );
	assert.equal( isBowTie( [ [ 0, 0 ], [ 4, 4 ], [ 4, 0 ], [ 0, 4 ] ] ), true );
	assert.equal( isBowTie( [ [ 0, 0 ], [ 4, 0 ], [ 4, 4 ], [ 0, 4 ] ] ), false );
} );

test( 'untrusted configuration is clamped, never trusted', () => {
	const line = normalizeActivity( 'number-line', {
		min: 0,
		max: 10,
		step: 3,
		points: [ 99, -9, 'x', null ],
	} );
	assert.deepEqual( line.points, [ 10, 0 ] );
	assert.equal( normalizeActivity( 'chart', { items: 'nope' } ).items.length, 3 );
	assert.equal( normalizeActivity( 'converter', { quantity: '__proto__' } ).quantity, 'length' );
	assert.equal( normalizeActivity( 'nothing-here', null ).version, 1 );
} );
