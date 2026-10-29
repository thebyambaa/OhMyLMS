import test from 'node:test';
import assert from 'node:assert/strict';
import { validateMembership } from '../../assets/src/features/memberships/validateMembership.mjs';

test( 'zero-price sales require a real calendar end date', () => {
	const plan = { name: 'Plan', regular_price: '120', sale_price: 0 };
	assert.ok( validateMembership( plan ).sale_dates );
	assert.deepEqual(
		validateMembership( {
			...plan,
			sale_price_dates_to: { date: '2027-01-31T00:00:00' },
		} ),
		{}
	);
	assert.ok(
		validateMembership( { ...plan, sale_price_dates_to: '2027-02-30' } )
			.sale_dates
	);
	assert.ok(
		validateMembership( {
			...plan,
			sale_price_dates_from: '2027-02-01',
			sale_price_dates_to: '2027-01-31',
		} ).sale_dates
	);
	assert.ok( validateMembership( { ...plan, sale_price: '-1' } ).sale_price );
	assert.deepEqual(
		validateMembership( {
			...plan,
			sale_price: '',
			sale_price_dates_from: '',
			sale_price_dates_to: '',
		} ),
		{}
	);
} );
test( 'membership form retains required name and price rules', () => {
	assert.deepEqual( validateMembership( { name: ' ', regular_price: 0 } ), {
		name: 'Name is required.',
		price: 'Price must be greater than 0.',
	} );
	assert.deepEqual(
		validateMembership( {
			name: 'Plan',
			regular_price: '120',
			sale_price: '100',
		} ),
		{}
	);
	assert.ok(
		validateMembership( {
			name: 'Plan',
			regular_price: '100',
			sale_price: '120',
		} ).sale_price
	);
} );
