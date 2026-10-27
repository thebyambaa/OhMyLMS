/** Standard public USD rates per million tokens, verified from these sources. */
export const pricingChecked = '2026-10-18';
export const pricingSources = {
	openai: 'https://developers.openai.com/api/docs/pricing',
	anthropic: 'https://platform.claude.com/docs/en/about-claude/pricing',
	gemini: 'https://ai.google.dev/gemini-api/docs/pricing',
};

// Exact IDs only: an unlisted model must never inherit a guessed family price.
const rates = {
	openai: {
		'gpt-6-astra': [ 10, 20, 50, 75 ],
		'gpt-6.1-sol': [ 2, 4, 10, 15 ],
		'gpt-6-luna': [ 0.1, 0.2, 0.5, 0.75 ],
	},
	anthropic: {
		'claude-fable-5-1': [ 10, 10, 50, 50 ],
		'claude-opus-5-5': [ 4, 4, 20, 20 ],
		'claude-sonnet-5-5': [ 2, 2, 10, 10 ],
		'claude-haiku-5-5': [ 0.1, 0.5, 0.5, 2.5 ],
		'claude-sonnet-4-6': [ 3, 3, 15, 15 ],
		'claude-haiku-4-5': [ 1, 1, 5, 5 ],
	},
	gemini: {
		'gemini-3.5-flash-lite': [ 0.3, 0.3, 2.5, 2.5 ],
		'gemini-3.1-flash-lite': [ 0.25, 0.25, 1.5, 1.5 ],
		'gemini-3.8-flash': [ 0.75, 0.75, 3.75, 3.75 ],
	},
};

/**
 * Return a dated reference rate, never a prediction of a provider invoice.
 * @param {string} provider Provider identifier.
 * @param {string} model    Exact model identifier.
 * @param {string} today    UTC date for expiry checks.
 * @return {number[]|null} Input low/high and output low/high, or unknown.
 */
export function modelPrice(
	provider,
	model,
	today = new Date().toISOString().slice( 0, 10 )
) {
	if ( model === 'gemini-3.8-flash' && today > '2026-12-31' ) {
		return null;
	}
	return Object.hasOwn( rates, provider ) &&
		Object.hasOwn( rates[ provider ], model )
		? rates[ provider ][ model ]
		: null;
}

/**
 * Models with verified reference prices, independently of account availability.
 * @param {string} provider Provider identifier.
 * @return {string[]} Exact model identifiers.
 */
export function pricedModels( provider ) {
	return Object.hasOwn( rates, provider )
		? Object.keys( rates[ provider ] )
		: [];
}
