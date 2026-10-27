import test from 'node:test';
import assert from 'node:assert/strict';
import { validateMembership } from '../../assets/src/features/memberships/validateMembership.mjs';
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
