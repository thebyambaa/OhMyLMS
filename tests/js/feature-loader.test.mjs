import test from 'node:test';
import assert from 'node:assert/strict';
import { createFeatureLoader } from '../../assets/src/extensions/featureLoader.mjs';

test( 'registration is idle and concurrent renders share one feature import', async () => {
	let calls = 0;
	const components = { Screen: () => null };
	const load = createFeatureLoader( async () => {
		calls++;
		return { components };
	}, 'components' );
	assert.equal( calls, 0 );
	const first = load();
	assert.equal( load(), first );
	assert.equal( await first, components );
	assert.equal( await load(), components );
	assert.equal( calls, 1 );
} );

test( 'features load independently and failed imports remain failed for the boundary', async () => {
	let otherCalls = 0;
	const failure = new Error( 'Network unavailable' );
	const load = createFeatureLoader(
		() => Promise.reject( failure ),
		'components'
	);
	const other = createFeatureLoader( () => {
		otherCalls++;
		return { components: {} };
	}, 'components' );
	await assert.rejects( load(), ( error ) => error === failure );
	assert.equal( otherCalls, 0 );
	await assert.rejects( load(), ( error ) => error === failure );
	await other();
	assert.equal( otherCalls, 1 );
} );

test( 'an incompatible feature module fails with an actionable registry error', async () => {
	const load = createFeatureLoader( () => ( {} ), 'courseComponents' );
	await assert.rejects(
		load(),
		/Missing feature registry: courseComponents/
	);
} );
