import test from 'node:test';
import assert from 'node:assert/strict';
import { categoryAppearance } from '../../assets/src/features/curriculum/categoryAppearance.mjs';

test( 'skills use the configured category icon and color with legacy chapter fallback', () => {
	const settings = {
		categories: [ 'Core', 'Extended' ],
		category_styles: { Core: { icon: 'calculator', color: '#aabbcc' } },
	};
	assert.deepEqual( categoryAppearance( { category: 'Core' }, settings ), {
		category: 'Core',
		icon: 'calculator',
		color: '#aabbcc',
	} );
	assert.equal(
		categoryAppearance( {}, settings, { name: 'Core' } ).category,
		'Core'
	);
	assert.equal(
		categoryAppearance(
			{ category: '', category_assigned: true },
			settings,
			{ name: 'Core' }
		).category,
		'',
		'an explicitly cleared category is not inherited again'
	);
	assert.equal(
		categoryAppearance( { category: 'Extended' }, settings, {
			name: 'Core',
		} ).category,
		'Extended'
	);
	assert.deepEqual(
		categoryAppearance( {}, settings, { name: 'Applications' } ),
		{
			category: '',
			icon: 'awards',
			color: '#6e42d3',
		}
	);
} );
