/**
 * Versioned configuration for the try-and-learn activities other than the function graph, and the
 * pure helpers they share. `includes/Blocks/InteractiveActivityBlock.php` applies the same limits when
 * rendering (`tests/fixtures/interactive-activity-contract.json` is checked by both sides), so a saved
 * block can never exceed them.
 */
import { DEFAULT_CONFIG as GRAPH_DEFAULT, normalizeConfig } from './config.mjs';

export const ACTIVITY_VERSION = 1;
export const GRAPH = 'function-graph';
export const TYPES = [
	GRAPH,
	'number-line',
	'fractions',
	'clock',
	'converter',
	'chart',
	'geometry',
];
export const MAX_POINTS = 4;
export const MAX_PARTS = 24;
export const MAX_ITEMS = 12;
export const CLOCK_SNAPS = [ 1, 5, 15 ];
export const CHART_KINDS = [ 'bar', 'pie', 'line' ];
export const SHAPES = [ 'triangle', 'quadrilateral', 'circle', 'angle' ];
export const FRACTION_SHAPES = [ 'bar', 'circle' ];

/** Unit symbol to size in the quantity's base unit (metre, kilogram, second, litre). */
export const UNITS = {
	length: {
		mm: 0.001,
		cm: 0.01,
		m: 1,
		km: 1000,
		in: 0.0254,
		ft: 0.3048,
		mi: 1609.344,
	},
	mass: {
		mg: 0.000001,
		g: 0.001,
		kg: 1,
		t: 1000,
		oz: 0.028349523125,
		lb: 0.45359237,
	},
	time: {
		ms: 0.001,
		s: 1,
		min: 60,
		h: 3600,
		day: 86400,
		week: 604800,
	},
	volume: {
		ml: 0.001,
		l: 1,
		tsp: 0.00492892159375,
		cup: 0.2365882365,
		gal: 3.785411784,
	},
};
export const DEFAULT_UNITS = {
	length: [ 'm', 'cm' ],
	mass: [ 'kg', 'g' ],
	time: [ 'h', 'min' ],
	volume: [ 'l', 'ml' ],
};

export const DEFAULTS = {
	'number-line': {
		version: ACTIVITY_VERSION,
		min: 0,
		max: 10,
		step: 1,
		points: [ 0 ],
		snap: true,
	},
	fractions: {
		version: ACTIVITY_VERSION,
		shape: 'bar',
		parts: 4,
		shaded: 1,
		allowParts: true,
	},
	clock: {
		version: ACTIVITY_VERSION,
		hour: 3,
		minute: 0,
		snap: 5,
		showDigital: true,
		format24: false,
	},
	converter: {
		version: ACTIVITY_VERSION,
		quantity: 'length',
		from: 'm',
		to: 'cm',
		value: 1,
		allowQuantity: true,
	},
	chart: {
		version: ACTIVITY_VERSION,
		kind: 'bar',
		title: '',
		items: [
			{ label: 'Red', value: 4 },
			{ label: 'Blue', value: 7 },
			{ label: 'Green', value: 3 },
		],
		allowEdit: true,
	},
	geometry: {
		version: ACTIVITY_VERSION,
		shape: 'triangle',
		snap: true,
		showMeasures: true,
	},
};

/**
 * @param {unknown} value Candidate.
 * @return {boolean} Whether it is a finite number or a numeric string (the PHP side uses is_numeric).
 */
function numeric( value ) {
	const trimmed = typeof value === 'string' ? value.trim() : value;
	return (
		( typeof trimmed === 'number' ||
			( typeof trimmed === 'string' && trimmed !== '' ) ) &&
		Number.isFinite( Number( trimmed ) )
	);
}

/**
 * @param {unknown} value    Candidate.
 * @param {number}  fallback Used when the candidate is not a number.
 * @param {number}  min      Lower limit.
 * @param {number}  max      Upper limit.
 * @return {number} Finite number in range.
 */
function limit( value, fallback, min, max ) {
	return numeric( value )
		? Math.min( max, Math.max( min, Number( value ) ) )
		: fallback;
}

/**
 * @param {unknown} value    Candidate.
 * @param {number}  fallback Used when the candidate is not a number.
 * @param {number}  min      Lower limit.
 * @param {number}  max      Upper limit.
 * @return {number} Whole number in range.
 */
function whole( value, fallback, min, max ) {
	return Math.round( limit( value, fallback, min, max ) );
}

/**
 * @param {unknown}  value    Candidate.
 * @param {string[]} allowed  Accepted values.
 * @param {string}   fallback Used otherwise.
 * @return {string} One of the accepted values.
 */
function choice( value, allowed, fallback ) {
	return typeof value === 'string' && allowed.includes( value )
		? value
		: fallback;
}

/**
 * @param {unknown} value Candidate.
 * @param {number}  max   Maximum length.
 * @return {string} Plain text.
 */
function text( value, max ) {
	return typeof value === 'string'
		? value.replace( /<[^>]*>/g, '' ).slice( 0, max )
		: '';
}

/**
 * Normalize untrusted attribute data for one activity.
 *
 * @param {string} type  Activity type.
 * @param {Object} input Stored or edited configuration.
 * @return {Object} Safe configuration.
 */
export function normalizeActivity( type, input ) {
	const source = input && typeof input === 'object' ? input : {};
	switch ( type ) {
		case 'number-line': {
			let min = limit( source.min, 0, -1e6, 1e6 );
			let max = limit( source.max, 10, -1e6, 1e6 );
			if ( min >= max ) {
				[ min, max ] = [ 0, 10 ];
			}
			const step = Math.min(
				max - min,
				limit( source.step, 1, 1e-6, 1e6 )
			);
			const points = (
				Array.isArray( source.points ) ? source.points : []
			)
				.filter( numeric )
				.slice( 0, MAX_POINTS )
				.map( ( point ) => limit( point, min, min, max ) );
			return {
				version: ACTIVITY_VERSION,
				min,
				max,
				step,
				points: points.length ? points : [ min ],
				snap: source.snap !== false,
			};
		}
		case 'fractions': {
			const parts = whole( source.parts, 4, 1, MAX_PARTS );
			return {
				version: ACTIVITY_VERSION,
				shape: choice( source.shape, FRACTION_SHAPES, 'bar' ),
				parts,
				shaded: whole( source.shaded, 1, 0, parts ),
				allowParts: source.allowParts !== false,
			};
		}
		case 'clock':
			return {
				version: ACTIVITY_VERSION,
				hour: whole( source.hour, 3, 0, 23 ),
				minute: whole( source.minute, 0, 0, 59 ),
				snap: CLOCK_SNAPS.includes( source.snap ) ? source.snap : 5,
				showDigital: source.showDigital !== false,
				format24: source.format24 === true,
			};
		case 'converter': {
			const quantity = choice(
				source.quantity,
				Object.keys( UNITS ),
				'length'
			);
			const names = Object.keys( UNITS[ quantity ] );
			return {
				version: ACTIVITY_VERSION,
				quantity,
				from: choice(
					source.from,
					names,
					DEFAULT_UNITS[ quantity ][ 0 ]
				),
				to: choice( source.to, names, DEFAULT_UNITS[ quantity ][ 1 ] ),
				value: limit( source.value, 1, -1e9, 1e9 ),
				allowQuantity: source.allowQuantity !== false,
			};
		}
		case 'chart': {
			const items = ( Array.isArray( source.items ) ? source.items : [] )
				.slice( 0, MAX_ITEMS )
				.map( ( item ) => ( {
					label: text( item?.label, 40 ),
					value: limit( item?.value, 0, 0, 1e6 ),
				} ) );
			return {
				version: ACTIVITY_VERSION,
				kind: choice( source.kind, CHART_KINDS, 'bar' ),
				title: text( source.title, 80 ),
				items: items.length >= 2 ? items : DEFAULTS.chart.items,
				allowEdit: source.allowEdit !== false,
			};
		}
		case 'geometry':
			return {
				version: ACTIVITY_VERSION,
				shape: choice( source.shape, SHAPES, 'triangle' ),
				snap: source.snap !== false,
				showMeasures: source.showMeasures !== false,
			};
		default:
			return normalizeConfig( source );
	}
}

/**
 * @param {string} type Activity type.
 * @return {Object} The configuration a new block of this type starts with.
 */
export function defaultConfig( type ) {
	return type === GRAPH ? GRAPH_DEFAULT : DEFAULTS[ type ];
}

/**
 * @param {number} value Number to show.
 * @return {string} At most four decimals, without trailing zeros.
 */
export function formatNumber( value ) {
	if ( ! Number.isFinite( value ) ) {
		return '–';
	}
	const rounded = Math.round( value * 1e4 ) / 1e4;
	return String( Object.is( rounded, -0 ) ? 0 : rounded );
}

/**
 * @param {number} a First whole number.
 * @param {number} b Second whole number.
 * @return {number} Greatest common divisor.
 */
export function gcd( a, b ) {
	let x = Math.abs( a );
	let y = Math.abs( b );
	while ( y ) {
		[ x, y ] = [ y, x % y ];
	}
	return x;
}

/**
 * @param {number} shaded Shaded parts.
 * @param {number} parts  Equal parts.
 * @return {{numerator:number, denominator:number, simple:number[], decimal:number, percent:number}} Facts.
 */
export function fractionFacts( shaded, parts ) {
	const divisor = shaded === 0 ? parts : gcd( shaded, parts ) || 1;
	return {
		numerator: shaded,
		denominator: parts,
		simple: [ shaded / divisor, parts / divisor ],
		decimal: shaded / parts,
		percent: ( shaded / parts ) * 100,
	};
}

/**
 * @param {number} hour   Hour, 0 to 23.
 * @param {number} minute Minute, 0 to 59.
 * @return {{hour:number, minute:number}} Hand angles in degrees clockwise from 12.
 */
export function clockAngles( hour, minute ) {
	return { hour: ( hour % 12 ) * 30 + minute * 0.5, minute: minute * 6 };
}

/**
 * @param {number}  hour     Hour, 0 to 23.
 * @param {number}  minute   Minute, 0 to 59.
 * @param {boolean} format24 Twenty-four-hour display.
 * @return {string} Digital time.
 */
export function clockText( hour, minute, format24 ) {
	const pad = ( number ) => String( number ).padStart( 2, '0' );
	if ( format24 ) {
		return `${ pad( hour ) }:${ pad( minute ) }`;
	}
	return `${ hour % 12 || 12 }:${ pad( minute ) } ${ hour < 12 ? 'AM' : 'PM' }`;
}

/**
 * @param {number} value    Amount in the source unit.
 * @param {string} quantity Quantity name.
 * @param {string} from     Source unit.
 * @param {string} to       Target unit.
 * @return {number} Amount in the target unit.
 */
export function convert( value, quantity, from, to ) {
	const table = UNITS[ quantity ];
	return ( value * table[ from ] ) / table[ to ];
}

/**
 * @param {number[]} values Numbers.
 * @return {{total:number, mean:number, median:number, modes:number[], range:number}} Summary.
 */
export function statistics( values ) {
	const sorted = [ ...values ].sort( ( a, b ) => a - b );
	const total = values.reduce( ( sum, value ) => sum + value, 0 );
	const middle = Math.floor( sorted.length / 2 );
	const counts = new Map();
	values.forEach( ( value ) =>
		counts.set( value, ( counts.get( value ) || 0 ) + 1 )
	);
	const most = Math.max( ...counts.values() );
	return {
		total,
		mean: total / values.length,
		median:
			sorted.length % 2
				? sorted[ middle ]
				: ( sorted[ middle - 1 ] + sorted[ middle ] ) / 2,
		// Every value is its own mode when none repeats; that is "no mode".
		modes:
			most === 1
				? []
				: [ ...counts ]
						.filter( ( entry ) => entry[ 1 ] === most )
						.map( ( entry ) => entry[ 0 ] ),
		range: sorted[ sorted.length - 1 ] - sorted[ 0 ],
	};
}

/**
 * @param {number[]} a First point.
 * @param {number[]} b Second point.
 * @return {number} Distance.
 */
export function distance( a, b ) {
	return Math.hypot( a[ 0 ] - b[ 0 ], a[ 1 ] - b[ 1 ] );
}

/**
 * @param {number[][]} points Polygon corners in order.
 * @return {number} Area by the shoelace formula.
 */
export function polygonArea( points ) {
	let sum = 0;
	points.forEach( ( point, index ) => {
		const next = points[ ( index + 1 ) % points.length ];
		sum += point[ 0 ] * next[ 1 ] - next[ 0 ] * point[ 1 ];
	} );
	return Math.abs( sum ) / 2;
}

/**
 * @param {number[]} p1 Segment one start.
 * @param {number[]} p2 Segment one end.
 * @param {number[]} p3 Segment two start.
 * @param {number[]} p4 Segment two end.
 * @return {boolean} Whether the segments cross.
 */
function crosses( p1, p2, p3, p4 ) {
	const side = ( a, b, c ) =>
		Math.sign(
			( b[ 0 ] - a[ 0 ] ) * ( c[ 1 ] - a[ 1 ] ) -
				( b[ 1 ] - a[ 1 ] ) * ( c[ 0 ] - a[ 0 ] )
		);
	return (
		side( p1, p2, p3 ) * side( p1, p2, p4 ) < 0 &&
		side( p3, p4, p1 ) * side( p3, p4, p2 ) < 0
	);
}

/**
 * @param {number[][]} quad Four corners in order.
 * @return {boolean} Whether the sides cross each other (a bow tie, which has no single area).
 */
export function isBowTie( quad ) {
	return (
		crosses( quad[ 0 ], quad[ 1 ], quad[ 2 ], quad[ 3 ] ) ||
		crosses( quad[ 1 ], quad[ 2 ], quad[ 3 ], quad[ 0 ] )
	);
}

/**
 * @param {number[]} a      One end.
 * @param {number[]} vertex Corner of the angle.
 * @param {number[]} b      Other end.
 * @return {number} Angle in degrees, 0 to 180.
 */
export function angleDegrees( a, vertex, b ) {
	const ax = a[ 0 ] - vertex[ 0 ];
	const ay = a[ 1 ] - vertex[ 1 ];
	const bx = b[ 0 ] - vertex[ 0 ];
	const by = b[ 1 ] - vertex[ 1 ];
	const length = Math.hypot( ax, ay ) * Math.hypot( bx, by );
	if ( ! length ) {
		return 0;
	}
	const cosine = Math.min(
		1,
		Math.max( -1, ( ax * bx + ay * by ) / length )
	);
	return ( Math.acos( cosine ) * 180 ) / Math.PI;
}
