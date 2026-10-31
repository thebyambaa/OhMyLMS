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

/** @typedef {{ values: number[], labelEvery: number }} ScaleTicks */

/**
 * Number line or measuring jug.
 * @typedef {Object} ScaleFigure
 * @property {'number-line' | 'fill-level'} figure   Figure type.
 * @property {number}                       min      Scale start.
 * @property {number}                       max      Scale end.
 * @property {number}                       step     Tick spacing.
 * @property {string}                       unit     Unit.
 * @property {ScaleTicks}                   ticks    Ticks.
 * @property {number}                       [target] Answer key: the value to mark.
 * @property {string[]}                     [key]    Answer key lines.
 */

/**
 * @typedef {Object} ShadeFigure
 * @property {'shade-model'} figure   Figure type.
 * @property {string}        shape    bar, grid or circle.
 * @property {number}        parts    Equal parts.
 * @property {number}        cols     Grid columns.
 * @property {number}        [shaded] Answer key: parts to shade.
 * @property {string[]}      [key]    Answer key lines.
 */

/**
 * @typedef {Object} BlocksFigure
 * @property {'count-blocks'}         figure   Figure type.
 * @property {string[]}               places   Place names, largest first.
 * @property {Record<string, number>} [counts] Answer key: count per place.
 * @property {string[]}               [key]    Answer key lines.
 */

/**
 * @typedef {Object} ClockFigure
 * @property {'set-clock'} figure   Figure type.
 * @property {boolean}     digital  Whether a digital box is shown.
 * @property {number}      [hour]   Answer key: hour, 0 to 23.
 * @property {number}      [minute] Answer key: minute.
 * @property {string[]}    [key]    Answer key lines.
 */

/**
 * @typedef {Object} MoneyFigure
 * @property {'make-amount'}          figure        Figure type.
 * @property {number[]}               denominations Denominations, largest first.
 * @property {string}                 symbol        Currency symbol.
 * @property {Record<string, number>} [counts]      Answer key: count per denomination.
 * @property {string[]}               [key]         Answer key lines.
 */

/**
 * @typedef {Object} ChartFigure
 * @property {'build-chart'}                         figure     Figure type.
 * @property {Array<{id: string, label: string}>}    categories Categories.
 * @property {number}                                max        Axis maximum.
 * @property {number}                                step       Tick spacing.
 * @property {string}                                unit       Unit.
 * @property {ScaleTicks}                            ticks      Ticks.
 * @property {Array<{label: string, value: number}>} [table]    Data table shown to the learner.
 * @property {Record<string, number>}                [values]   Answer key: value per category.
 * @property {string[]}                              [key]      Answer key lines.
 */

/**
 * @typedef {Object} GridConstraints
 * @property {number|null} area      Required area, null for any.
 * @property {number|null} perimeter Required perimeter, null for any.
 * @property {boolean}     rectangle Whether the shape must be a rectangle.
 * @property {boolean}     connected Whether the squares must connect.
 */

/**
 * @typedef {Object} GridFigure
 * @property {'grid-build'}    figure        Figure type.
 * @property {number}          rows          Rows.
 * @property {number}          cols          Columns.
 * @property {boolean}         measures      Whether measures are shown.
 * @property {GridConstraints} [constraints] Answer key: what the shape must satisfy.
 * @property {string[]}        [key]         Answer key lines.
 */

/**
 * Printed figure of a visual question.
 * @typedef {ScaleFigure | ShadeFigure | BlocksFigure | ClockFigure | MoneyFigure | ChartFigure | GridFigure} VisualFigureModel
 */

/** @type {Record<string, number>} */
const PLACES = { thousands: 1000, hundreds: 100, tens: 10, ones: 1 };

/**
 * @param {unknown} value      Saved value.
 * @param {number}  [fallback] Value when it is not a finite number.
 * @return {number} Number.
 */
const num = ( value, fallback = 0 ) => {
	const number = Number( value );
	return value !== '' &&
		value !== null &&
		value !== undefined &&
		Number.isFinite( number )
		? number
		: fallback;
};

/**
 * @param {unknown} value    Saved value.
 * @param {number}  fallback Value when it is not a finite number.
 * @param {number}  min      Lowest value.
 * @param {number}  max      Highest value.
 * @return {number} Integer between min and max.
 */
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
 * @param {unknown}  target Number.
 * @param {string[]} places Place names.
 * @return {Object<string, number>} Count per place.
 */
function placeCounts( target, places ) {
	let rest = Math.max( 0, Math.trunc( num( target ) ) );
	/** @type {Object<string, number>} */
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
 * @param {unknown}  target        Amount.
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
 * Saved settings of a visual question, full or learner view. Every field is optional and is
 * converted where it is read.
 * @typedef {Object} VisualSettings
 * @property {unknown}                                                                                 [min]           Scale start.
 * @property {unknown}                                                                                 [max]           Scale end or axis maximum.
 * @property {unknown}                                                                                 [step]          Tick spacing.
 * @property {unknown}                                                                                 [unit]          Unit.
 * @property {unknown}                                                                                 [target]        Target value.
 * @property {unknown}                                                                                 [parts]         Equal parts.
 * @property {unknown}                                                                                 [shape]         Shape of a shaded model.
 * @property {unknown}                                                                                 [cols]          Columns.
 * @property {unknown}                                                                                 [rows]          Rows.
 * @property {unknown}                                                                                 [answer]        Parts to shade.
 * @property {string[]}                                                                                [places]        Place names.
 * @property {unknown}                                                                                 [show_digital]  Whether the clock shows a digital box.
 * @property {unknown}                                                                                 [hour]          Hour.
 * @property {unknown}                                                                                 [minute]        Minute.
 * @property {unknown[]}                                                                               [denominations] Notes and coins.
 * @property {unknown}                                                                                 [symbol]        Currency symbol.
 * @property {Array<{id?: unknown, label?: unknown}>}                                                  [categories]    Chart categories.
 * @property {unknown}                                                                                 [table]         Data table shown to the learner.
 * @property {unknown}                                                                                 [show_table]    Whether the data table is shown.
 * @property {Record<string, unknown>}                                                                 [values]        Value per category.
 * @property {unknown}                                                                                 [show_measures] Whether measures are shown.
 * @property {{area?: number|null, perimeter?: number|null, rectangle?: unknown, connected?: unknown}} [constraints]   Grid constraints.
 */

/**
 * The printed figure of a visual question.
 *
 * @param {string}         type       Question type.
 * @param {VisualSettings} [settings] Full settings (editor, answer key) or learner view (student paper).
 * @param {boolean}        [answers]  Whether to add the answer key; needs full settings.
 * @return {VisualFigureModel|null} Figure model, or null for other types.
 */
export function visualFigure( type, settings = {}, answers = false ) {
	const s = settings;
	switch ( type ) {
		case 'number-line':
		case 'fill-level': {
			const isJug = type === 'fill-level';
			const step = Math.max( 1e-6, num( s.step, isJug ? 50 : 1 ) );
			// Ticks are set next, from min and max.
			const figure = /** @type {ScaleFigure} */ ( {
				figure: type,
				min: num( s.min, 0 ),
				max: num( s.max, isJug ? 1000 : 10 ),
				step,
				unit: String( s.unit ?? ( isJug ? 'ml' : '' ) ),
			} );
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
			/** @type {ShadeFigure} */
			const figure = {
				figure: type,
				shape: /** @type {unknown[]} */ ( [
					'bar',
					'grid',
					'circle',
				] ).includes( s.shape )
					? /** @type {string} */ ( s.shape )
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
			/** @type {BlocksFigure} */
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
			/** @type {ClockFigure} */
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
			/** @type {MoneyFigure} */
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
			// Ticks are set next, from max and step.
			const figure = /** @type {ChartFigure} */ ( {
				figure: type,
				categories,
				max: Math.max( 1e-6, num( s.max, 10 ) ),
				step: Math.max( 1e-6, num( s.step, 1 ) ),
				unit: String( s.unit ?? '' ),
			} );
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
				const values = Object.fromEntries(
					categories.map( ( category ) => [
						category.id,
						num( s.values?.[ category.id ] ),
					] )
				);
				figure.values = values;
				figure.key = categories.map(
					( category ) =>
						`${ category.label } = ${ values[ category.id ] }`
				);
			}
			return figure;
		}
		case 'grid-build': {
			/** @type {GridFigure} */
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
