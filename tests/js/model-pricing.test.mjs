import test from 'node:test';
import assert from 'node:assert/strict';
import {
	modelPrice,
	pricingSources,
} from '../../assets/src/features/settings/modelPricing.mjs';

test( 'unknown models and snapshots never inherit a guessed price', () => {
	assert.equal( modelPrice( 'openai', 'gpt-6-luna-unknown-snapshot' ), null );
	assert.equal( modelPrice( 'unknown', 'gpt-6-luna' ), null );
	assert.equal( modelPrice( 'openai', 'toString' ), null );
} );
test( 'context tiers are explicit and promotional rates expire', () => {
	assert.deepEqual(
		modelPrice( 'anthropic', 'claude-haiku-5-5' ),
		[ 0.1, 0.5, 0.5, 2.5 ]
	);
	assert.ok( modelPrice( 'gemini', 'gemini-3.8-flash', '2026-12-31' ) );
	assert.equal(
		modelPrice( 'gemini', 'gemini-3.8-flash', '2027-01-01' ),
		null
	);
	assert.equal(
		new URL( pricingSources.openai ).hostname,
		'developers.openai.com'
	);
} );
