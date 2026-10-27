/**
 * Versioned configuration for the function-graph activity. `includes/Blocks/InteractiveActivityBlock.php`
 * applies the same limits when rendering, so a saved block can never exceed them.
 */

export const CONFIG_VERSION = 1;
export const MAX_FUNCTIONS = 12;
export const MAX_PARAMS = 6;
export const COLORS = [
	'#2563eb',
	'#dc2626',
	'#16a34a',
	'#9333ea',
	'#ea580c',
	'#0891b2',
];

export const DEFAULT_CONFIG = {
	version: CONFIG_VERSION,
	view: { xMin: -10, xMax: 10, yMin: -7, yMax: 7 },
	params: [],
	functions: [ { id: 'f1', latex: 'x^2', color: COLORS[ 0 ] } ],
	allowAdd: true,
	allowEdit: true,
};

/**
 * @param {string|number|null|undefined} value    Candidate.
 * @param {number}                       fallback Value used when the candidate is not finite.
 * @param {number}                       min      Lower limit.
 * @param {number}                       max      Upper limit.
 * @return {number} Finite number in range.
 */
function number( value, fallback, min, max ) {
	const parsed = Number( value );
	return Number.isFinite( parsed )
		? Math.min( max, Math.max( min, parsed ) )
		: fallback;
}

/**
 * Normalize untrusted attribute data to the supported contract.
 *
 * @param {Object} input Stored or edited configuration.
 * @return {Object} Safe configuration.
 */
export function normalizeConfig( input ) {
	const source = input && typeof input === 'object' ? input : {};
	const view = source.view || {};
	let xMin = number( view.xMin, -10, -1e6, 1e6 );
	let xMax = number( view.xMax, 10, -1e6, 1e6 );
	let yMin = number( view.yMin, -7, -1e6, 1e6 );
	let yMax = number( view.yMax, 7, -1e6, 1e6 );
	if ( xMin >= xMax ) {
		[ xMin, xMax ] = [ -10, 10 ];
	}
	if ( yMin >= yMax ) {
		[ yMin, yMax ] = [ -7, 7 ];
	}
	const seen = new Set();
	const params = ( Array.isArray( source.params ) ? source.params : [] )
		.filter( ( param ) => {
			const name = String( param?.name || '' );
			const valid = /^[a-df-wyzA-Z]$/.test( name ) && ! seen.has( name );
			seen.add( name );
			return valid;
		} )
		.slice( 0, MAX_PARAMS )
		.map( ( param ) => {
			let min = number( param.min, -5, -1e6, 1e6 );
			let max = number( param.max, 5, -1e6, 1e6 );
			if ( min >= max ) {
				[ min, max ] = [ -5, 5 ];
			}
			const step = number( param.step, 0.1, 1e-6, 1e6 );
			return {
				name: param.name,
				min,
				max,
				step,
				value: number( param.value, 1, min, max ),
			};
		} );
	const functions = (
		Array.isArray( source.functions ) ? source.functions : []
	)
		.slice( 0, MAX_FUNCTIONS )
		.map( ( item, index ) => ( {
			id: /^[\w-]{1,24}$/.test( String( item?.id ) )
				? String( item.id )
				: `f${ index + 1 }`,
			latex: String( item?.latex || '' ).slice( 0, 500 ),
			color: /^#[0-9a-fA-F]{6}$/.test( String( item?.color ) )
				? item.color
				: COLORS[ index % COLORS.length ],
		} ) );
	return {
		version: CONFIG_VERSION,
		view: { xMin, xMax, yMin, yMax },
		params,
		functions,
		allowAdd: source.allowAdd !== false,
		allowEdit: source.allowEdit !== false,
	};
}
