import test from 'node:test';
import assert from 'node:assert/strict';
import { parse } from '@babel/parser';
import generatorModule from '@babel/generator';
import { adaptCommerceRoutes } from '../../tools/commerce-adapters.mjs';

const generate = generatorModule.default || generatorModule;

test( 'commerce clients use their own API without changing unrelated strings', () => {
	const ast = parse(
		'const paths = ["/cx-ecommerce/v1/orders", "cx-ecommerce/v1/coupons/", "/cx-ecommerce/v1", "cx-ecommerce", "/cx-ecommerce/v10/orders", "https://example.test/cx-ecommerce/v1/orders"];'
	);
	assert.equal( adaptCommerceRoutes( ast ), 3 );
	const output = generate( ast ).code;
	assert.match( output, /"\/ohmylms-commerce\/v1\/orders"/ );
	assert.match( output, /"ohmylms-commerce\/v1\/coupons\/"/ );
	assert.match( output, /"\/ohmylms-commerce\/v1"/ );
	assert.match( output, /"cx-ecommerce"/ );
	assert.match( output, /"\/cx-ecommerce\/v10\/orders"/ );
	assert.match( output, /https:\/\/example.test\/cx-ecommerce\/v1\/orders/ );
	assert.equal( adaptCommerceRoutes( ast ), 0 );
} );
