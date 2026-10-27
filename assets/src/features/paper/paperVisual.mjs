/**
 * Printed figures of the visual question types: number line, shaded model, place value blocks,
 * clock, money, measuring jug, bar chart and squared grid. Every figure reads only the fields of
 * the learner view (OhMyLMS\Assessment\Visual::public_view(), with the same defaults), so the
 * editor and the student print page draw the same figure; the answer key adds the private target.
 */

export const VISUAL_TYPES = [
	'number-line',
	'shade-model',
	'count-blocks',
	'set-clock',
	'make-amount',
	'fill-level',
	'build-chart',
	'grid-build',
];

const PLACES = { thousands: 1000, hundreds: 100, tens: 10, ones: 1 };

const num = ( value, fallback = 0 ) => {
	const number = Number( value );
	return value !== '' &&
		value !== null &&
		value !== undefined &&
		Number.isFinite( number )
		? number
		: fallback;
};

const int = ( value, fallback, min, max ) =>
	Math.max( min, Math.min( max, Math.trunc( num( value, fallback ) ) ) );

/**
 * Values at each tick of a scale, at most `limit` ticks, with every nth labelled.
 *
 * @param {number} min   Start.
 * @param {number} max   End.
 * @param {number} step  Tick spacing.
 * @param {number} limit Most ticks to draw.
 * @return {{values: number[], labelEvery: number}} Ticks.
 */
export function scaleTicks( min, max, step, limit = 101 ) {
	const low = Math.min( min, max );
	const high = Math.max( min, max );
	let spacing = Math.max( step, ( high - low ) / ( limit - 1 ), 1e-6 );
	// Keep the spacing a whole multiple of the step, so ticks stay on step values.
	spacing = step * Math.ceil( spacing / step - 1e-9 );
	const values = [];
	for ( let value = low; value <= high + spacing / 1e6; value += spacing ) {
		values.push( Number( value.toFixed( 6 ) ) );
	}
	return {
		values,
		labelEvery: Math.max( 1, Math.ceil( values.length / 11 ) ),
	};
}

/**
 * Place values making up a number with the given places, largest first.
 *
 * @param {number}   target Number.
 * @param {string[]} places Place names.
 * @return {Object<string, number>} Count per place.
 */
function placeCounts( target, places ) {
	let rest = Math.max( 0, Math.trunc( num( target ) ) );
	const counts = {};
	const ordered = Object.keys( PLACES ).filter( ( place ) =>
		places.includes( place )
	);
	for ( const place of ordered ) {
		counts[ place ] = Math.floor( rest / PLACES[ place ] );
		rest -= counts[ place ] * PLACES[ place ];
	}
	return counts;
}

/**
 * Fewest notes and coins making an amount, when the denominations can make it.
 *
 * @param {number}   target        Amount.
 * @param {number[]} denominations Denominations, largest first.
 * @return {Array<{value: number, count: number}>|null} Breakdown.
 */
function breakdown( target, denominations ) {
	let rest = Math.trunc( num( target ) );
	const out = [];
	for ( const value of denominations ) {
		const count = Math.floor( rest / value );
		if ( count > 0 ) {
			out.push( { value, count } );
			rest -= count * value;
		}
	}
	return rest === 0 ? out : null;
}

/**
 * The printed figure of a visual question.
 *
 * @param {string}  type     Question type.
 * @param {Object}  settings Full settings (editor, answer key) or learner view (student paper).
 * @param {boolean} answers  Whether to add the answer key; needs full settings.
 * @return {Object|null} Figure model, or null for other types.
 */
export function visualFigure( type, settings = {}, answers = false ) {
	const s = settings;
	switch ( type ) {
		case 'number-line':
		case 'fill-level': {
			const isJug = type === 'fill-level';
			const step = Math.max( 1e-6, num( s.step, isJug ? 50 : 1 ) );
			const figure = {
				figure: type,
				min: num( s.min, 0 ),
				max: num( s.max, isJug ? 1000 : 10 ),
				step,
				unit: String( s.unit ?? ( isJug ? 'ml' : '' ) ),
			};
			figure.ticks = scaleTicks( figure.min, figure.max, step );
			if ( answers ) {
				figure.target = num( s.target, NaN );
				figure.key = [
					`${ s.target ?? '' }${ figure.unit ? ` ${ figure.unit }` : '' }`,
				];
			}
			return figure;
		}
		case 'shade-model': {
			const parts = int( s.parts, 8, 1, 100 );
			const figure = {
				figure: type,
				shape: [ 'bar', 'grid', 'circle' ].includes( s.shape )
					? s.shape
					: 'bar',
				parts,
				cols: int( s.cols, Math.min( parts, 10 ), 1, parts ),
			};
			if ( answers ) {
				figure.shaded = int( s.answer, 0, 0, parts );
				figure.key = [ `${ figure.shaded } / ${ parts }` ];
			}
			return figure;
		}
		case 'count-blocks': {
			const chosen = Object.keys( PLACES ).filter( ( place ) =>
				( s.places || [ 'hundreds', 'tens', 'ones' ] ).includes( place )
			);
			const figure = {
				figure: type,
				places: chosen.length ? chosen : [ 'hundreds', 'tens', 'ones' ],
			};
			if ( answers ) {
				figure.counts = placeCounts( s.target, figure.places );
				figure.key = [ String( s.target ?? '' ) ];
			}
			return figure;
		}
		case 'set-clock': {
			const figure = {
				figure: type,
				digital: Boolean( s.show_digital ),
			};
			if ( answers ) {
				figure.hour = int( s.hour, 12, 0, 23 );
				figure.minute = int( s.minute, 0, 0, 59 );
				figure.key = [
					`${ figure.hour }:${ String( figure.minute ).padStart(
						2,
						'0'
					) }`,
				];
			}
			return figure;
		}
		case 'make-amount': {
			const denominations = [
				...new Set(
					( s.denominations || [] )
						.map( ( value ) => Math.trunc( num( value ) ) )
						.filter( ( value ) => value > 0 )
				),
			].sort( ( a, b ) => b - a );
			const figure = {
				figure: type,
				denominations,
				symbol: String( s.symbol ?? '₮' ),
			};
			if ( answers ) {
				figure.counts = Object.fromEntries(
					( breakdown( s.target, denominations ) || [] ).map(
						( { value, count } ) => [ value, count ]
					)
				);
				figure.key = [ `${ s.target ?? '' } ${ figure.symbol }` ];
			}
			return figure;
		}
		case 'build-chart': {
			const categories = ( s.categories || [] ).map( ( category ) => ( {
				id: String( category.id ?? '' ).replace( /[^a-z0-9_-]/gi, '' ),
				label: String( category.label ?? '' ),
			} ) );
			const figure = {
				figure: type,
				categories,
				max: Math.max( 1e-6, num( s.max, 10 ) ),
				step: Math.max( 1e-6, num( s.step, 1 ) ),
				unit: String( s.unit ?? '' ),
			};
			figure.ticks = scaleTicks( 0, figure.max, figure.step, 21 );
			// The author may show the data the learner turns into bars; the learner view has it too.
			if ( Array.isArray( s.table ) ) {
				figure.table = s.table.map( ( row ) => ( {
					label: String( row.label ?? '' ),
					value: num( row.value ),
				} ) );
			} else if ( s.show_table ) {
				figure.table = categories.map( ( category ) => ( {
					label: category.label,
					value: num( s.values?.[ category.id ] ),
				} ) );
			}
			if ( answers ) {
				figure.values = Object.fromEntries(
					categories.map( ( category ) => [
						category.id,
						num( s.values?.[ category.id ] ),
					] )
				);
				figure.key = categories.map(
					( category ) =>
						`${ category.label } = ${ figure.values[ category.id ] }`
				);
			}
			return figure;
		}
		case 'grid-build': {
			const figure = {
				figure: type,
				rows: int( s.rows, 6, 1, 20 ),
				cols: int( s.cols, 6, 1, 20 ),
				measures: Boolean( s.show_measures ),
			};
			if ( answers ) {
				// The shape is the candidate's; the key states what it must satisfy.
				const c = s.constraints || {};
				figure.constraints = {
					area: c.area ?? null,
					perimeter: c.perimeter ?? null,
					rectangle: Boolean( c.rectangle ),
					connected: Boolean( c.connected ),
				};
			}
			return figure;
		}
		default:
			return null;
	}
}
