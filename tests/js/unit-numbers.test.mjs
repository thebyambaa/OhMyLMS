import test from 'node:test';
import assert from 'node:assert/strict';
import {
	UNIT_NUMBER_MAX,
	normalizeUnitNumber,
	saveUnitNumber,
	unitLabel,
	unitNumberChanges,
} from '../../assets/src/features/courses/unitNumbers.mjs';

test( 'unit numbers accept short labels and treat empty as automatic', () => {
	assert.equal( normalizeUnitNumber( ' 2A ' ), '2A' );
	assert.equal( normalizeUnitNumber( '3' ), '3' );
	assert.equal( normalizeUnitNumber( 4 ), '4' );
	assert.equal( normalizeUnitNumber( 'A.1-b' ), 'A.1-b' );
	assert.equal( normalizeUnitNumber( '' ), '' );
	assert.equal( normalizeUnitNumber( '   ' ), '' );
	assert.equal( normalizeUnitNumber( null ), '' );
	assert.equal(
		normalizeUnitNumber( 'x'.repeat( UNIT_NUMBER_MAX ) ).length,
		10
	);
	assert.equal(
		normalizeUnitNumber( 'x'.repeat( UNIT_NUMBER_MAX + 1 ) ),
		null
	);
	assert.equal( normalizeUnitNumber( 'Unit 2' ), null );
	assert.equal( normalizeUnitNumber( '<b>' ), null );
} );

test( 'a unit shows its own label or its position', () => {
	assert.equal( unitLabel( '', 0 ), '1' );
	assert.equal( unitLabel( '', 4 ), '5' );
	assert.equal( unitLabel( '2A', 0 ), '2A' );
	assert.equal( unitLabel( undefined, 1 ), '2' );
} );

test( 'only changed units are saved and invalid labels block saving', () => {
	const chapters = [
		{ id: 10, number: '' },
		{ id: 11, number: '3' },
		{ id: 12, number: '2A' },
		{ id: 13 },
	];
	const { changes, invalid } = unitNumberChanges( chapters, {
		10: '3',
		11: '3',
		12: '',
		13: '  ',
		99: 'ignored',
	} );
	assert.deepEqual( changes, [
		{ id: 10, number: '3' },
		{ id: 12, number: '' },
	] );
	assert.deepEqual( invalid, [] );
	assert.deepEqual( unitNumberChanges( chapters, { 10: 'not valid!' } ), {
		changes: [],
		invalid: [ 10 ],
	} );
	assert.deepEqual( unitNumberChanges( chapters, {} ).changes, [] );
} );

test( 'a unit number is saved on its chapter', async () => {
	let call;
	await saveUnitNumber(
		5,
		10,
		'2A',
		async ( options ) => ( call = options )
	);
	assert.equal( call.method, 'PUT' );
	assert.equal( call.path, '/ohmylms/v1/content-hub/catalog/5/chapters/10' );
	assert.deepEqual( call.data, { number: '2A' } );
} );
