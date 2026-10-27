/** @jsx createElement */
import { createElement } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Static drawings of the visual question types for paper. The answer key draws the target in the
 * key colour on the same figure.
 */

const KEY = '#b32d2e';
const label = ( value ) => String( Number( Number( value ).toFixed( 4 ) ) );

/**
 * Horizontal number line with ticks and labels.
 *
 * @param {Object} props        Properties.
 * @param {Object} props.figure Figure model.
 * @return {Element} Number line.
 */
function NumberLine( { figure } ) {
	const { min, max, ticks } = figure;
	const span = max - min || 1;
	const x = ( value ) => 12 + ( ( value - min ) / span ) * 276;
	const hasTarget = Number.isFinite( figure.target );
	return (
		<svg
			className="ohmylms-print-figure is-number-line"
			viewBox="0 0 300 46"
			aria-hidden="true"
		>
			<line x1="4" y1="24" x2="296" y2="24" stroke="currentColor" />
			<path d="M4 24l6-4v8zM296 24l-6-4v8z" fill="currentColor" />
			{ ticks.values.map( ( value, index ) => {
				const labelled =
					index % ticks.labelEvery === 0 ||
					index === ticks.values.length - 1;
				return (
					<g key={ value }>
						<line
							x1={ x( value ) }
							y1={ labelled ? 18 : 20 }
							x2={ x( value ) }
							y2={ labelled ? 30 : 28 }
							stroke="currentColor"
						/>
						{ labelled && (
							<text
								x={ x( value ) }
								y="42"
								textAnchor="middle"
								fontSize="8"
								fill="currentColor"
							>
								{ label( value ) }
							</text>
						) }
					</g>
				);
			} ) }
			{ hasTarget && (
				<g fill={ KEY }>
					<circle cx={ x( figure.target ) } cy="24" r="3.5" />
					<text
						x={ x( figure.target ) }
						y="11"
						textAnchor="middle"
						fontSize="8"
					>
						{ label( figure.target ) }
					</text>
				</g>
			) }
		</svg>
	);
}

/**
 * Measuring jug with a scale, filled to the target in the answer key.
 *
 * @param {Object} props        Properties.
 * @param {Object} props.figure Figure model.
 * @return {Element} Jug.
 */
function Jug( { figure } ) {
	const { min, max, ticks, unit } = figure;
	const span = max - min || 1;
	const y = ( value ) => 196 - ( ( value - min ) / span ) * 170;
	const hasTarget = Number.isFinite( figure.target );
	return (
		<svg
			className="ohmylms-print-figure is-jug"
			viewBox="0 0 130 206"
			aria-hidden="true"
		>
			{ hasTarget && (
				<rect
					x="56"
					y={ y( figure.target ) }
					width="60"
					height={ 196 - y( figure.target ) }
					fill="#f5c4c4"
					stroke={ KEY }
				/>
			) }
			<path
				d="M56 14v182h60v-182"
				fill="none"
				stroke="currentColor"
				strokeWidth="1.5"
			/>
			{ unit && (
				<text
					x="86"
					y="9"
					textAnchor="middle"
					fontSize="8"
					fill="currentColor"
				>
					{ unit }
				</text>
			) }
			{ ticks.values.map( ( value, index ) => {
				const labelled =
					index % ticks.labelEvery === 0 ||
					index === ticks.values.length - 1;
				return (
					<g key={ value }>
						<line
							x1="56"
							y1={ y( value ) }
							x2={ labelled ? 68 : 63 }
							y2={ y( value ) }
							stroke="currentColor"
						/>
						{ labelled && (
							<text
								x="51"
								y={ y( value ) + 3 }
								textAnchor="end"
								fontSize="8"
								fill="currentColor"
							>
								{ label( value ) }
							</text>
						) }
					</g>
				);
			} ) }
		</svg>
	);
}

/**
 * Bar, grid or circle divided into equal parts; the key shades the answer.
 *
 * @param {Object} props        Properties.
 * @param {Object} props.figure Figure model.
 * @return {Element} Model.
 */
function ShadeModel( { figure } ) {
	const { parts, shape } = figure;
	const shaded = figure.shaded ?? 0;
	const fill = ( index ) => ( index < shaded ? '#f5c4c4' : 'none' );
	if ( shape === 'circle' ) {
		const sectors = Array.from( { length: parts }, ( _, index ) => {
			const start = ( index / parts ) * 2 * Math.PI - Math.PI / 2;
			const end = ( ( index + 1 ) / parts ) * 2 * Math.PI - Math.PI / 2;
			const point = ( angle ) =>
				`${ 60 + 55 * Math.cos( angle ) } ${ 60 + 55 * Math.sin( angle ) }`;
			return parts === 1 ? (
				<circle
					key={ index }
					cx="60"
					cy="60"
					r="55"
					fill={ fill( index ) }
					stroke="currentColor"
				/>
			) : (
				<path
					key={ index }
					d={ `M60 60L${ point( start ) }A55 55 0 ${
						end - start > Math.PI ? 1 : 0
					} 1 ${ point( end ) }Z` }
					fill={ fill( index ) }
					stroke="currentColor"
				/>
			);
		} );
		return (
			<svg
				className="ohmylms-print-figure is-circle"
				viewBox="0 0 120 120"
				aria-hidden="true"
			>
				{ sectors }
			</svg>
		);
	}
	const cols = shape === 'bar' ? parts : figure.cols;
	const rows = Math.ceil( parts / cols );
	const cell = shape === 'bar' ? 280 / parts : 20;
	const height = shape === 'bar' ? 36 : 20;
	return (
		<svg
			className={ `ohmylms-print-figure is-${ shape }` }
			viewBox={ `0 0 ${ cols * cell + 2 } ${ rows * height + 2 }` }
			style={
				shape === 'grid' ? { width: `${ cols * 9 }mm` } : undefined
			}
			aria-hidden="true"
		>
			{ Array.from( { length: parts }, ( _, index ) => (
				<rect
					key={ index }
					x={ 1 + ( index % cols ) * cell }
					y={ 1 + Math.floor( index / cols ) * height }
					width={ cell }
					height={ height }
					fill={ fill( index ) }
					stroke="currentColor"
				/>
			) ) }
		</svg>
	);
}

/**
 * Clock face without hands; the key draws them. A digital box is added when the question shows one.
 *
 * @param {Object} props        Properties.
 * @param {Object} props.figure Figure model.
 * @return {Element} Clock.
 */
function Clock( { figure } ) {
	const hasTime = Number.isFinite( figure.hour );
	const hand = ( angle, length ) => {
		const radians = ( angle - 90 ) * ( Math.PI / 180 );
		return {
			x2: 60 + length * Math.cos( radians ),
			y2: 60 + length * Math.sin( radians ),
		};
	};
	return (
		<div className="ohmylms-print-clock">
			<svg
				className="ohmylms-print-figure is-clock"
				viewBox="0 0 120 120"
				aria-hidden="true"
			>
				<circle
					cx="60"
					cy="60"
					r="56"
					fill="none"
					stroke="currentColor"
					strokeWidth="1.5"
				/>
				{ Array.from( { length: 60 }, ( _, minute ) => {
					const radians = ( minute * 6 - 90 ) * ( Math.PI / 180 );
					const inner = minute % 5 ? 52 : 48;
					return (
						<line
							key={ minute }
							x1={ 60 + inner * Math.cos( radians ) }
							y1={ 60 + inner * Math.sin( radians ) }
							x2={ 60 + 56 * Math.cos( radians ) }
							y2={ 60 + 56 * Math.sin( radians ) }
							stroke="currentColor"
							strokeWidth={ minute % 5 ? 0.5 : 1.2 }
						/>
					);
				} ) }
				{ Array.from( { length: 12 }, ( _, index ) => {
					const radians =
						( ( index + 1 ) * 30 - 90 ) * ( Math.PI / 180 );
					return (
						<text
							key={ index }
							x={ 60 + 40 * Math.cos( radians ) }
							y={ 60 + 40 * Math.sin( radians ) + 3.5 }
							textAnchor="middle"
							fontSize="10"
							fill="currentColor"
						>
							{ index + 1 }
						</text>
					);
				} ) }
				{ hasTime && (
					<g stroke={ KEY } strokeLinecap="round">
						<line
							x1="60"
							y1="60"
							{ ...hand(
								( ( figure.hour % 12 ) + figure.minute / 60 ) *
									30,
								26
							) }
							strokeWidth="3"
						/>
						<line
							x1="60"
							y1="60"
							{ ...hand( figure.minute * 6, 42 ) }
							strokeWidth="1.8"
						/>
					</g>
				) }
				<circle cx="60" cy="60" r="2.5" fill="currentColor" />
			</svg>
			{ figure.digital && (
				<p className="ohmylms-print-digital">
					<span className="ohmylms-print-box">
						{ hasTime && String( figure.hour ) }
					</span>
					:
					<span className="ohmylms-print-box">
						{ hasTime &&
							String( figure.minute ).padStart( 2, '0' ) }
					</span>
				</p>
			) }
		</div>
	);
}

/**
 * Place value table to write how many blocks of each place.
 *
 * @param {Object} props        Properties.
 * @param {Object} props.figure Figure model.
 * @return {Element} Table.
 */
function PlaceValue( { figure } ) {
	const names = {
		thousands: __( 'Thousands', 'ohmylms' ),
		hundreds: __( 'Hundreds', 'ohmylms' ),
		tens: __( 'Tens', 'ohmylms' ),
		ones: __( 'Ones', 'ohmylms' ),
	};
	return (
		<table className="ohmylms-print-table is-places">
			<thead>
				<tr>
					{ figure.places.map( ( place ) => (
						<th key={ place }>{ names[ place ] }</th>
					) ) }
				</tr>
			</thead>
			<tbody>
				<tr>
					{ figure.places.map( ( place ) => (
						<td key={ place }>{ figure.counts?.[ place ] }</td>
					) ) }
				</tr>
			</tbody>
		</table>
	);
}

/**
 * Notes and coins with a box for how many of each, and the total.
 *
 * @param {Object} props        Properties.
 * @param {Object} props.figure Figure model.
 * @return {Element} Money.
 */
function Money( { figure } ) {
	return (
		<div className="ohmylms-print-money">
			{ figure.denominations.map( ( value ) => (
				<span key={ value }>
					<span className="ohmylms-print-note-value">
						{ value } { figure.symbol }
					</span>
					×
					<span className="ohmylms-print-box">
						{ figure.counts?.[ value ] }
					</span>
				</span>
			) ) }
		</div>
	);
}

/**
 * Bar chart axes with categories; the key draws the bars.
 *
 * @param {Object} props        Properties.
 * @param {Object} props.figure Figure model.
 * @return {Element} Chart.
 */
function Chart( { figure } ) {
	const { categories, max, ticks } = figure;
	const top = 12;
	const bottom = 150;
	const left = 36;
	const right = 292;
	const y = ( value ) => bottom - ( value / max ) * ( bottom - top );
	const width = ( right - left ) / Math.max( 1, categories.length );
	return (
		<>
			{ figure.table && (
				<table className="ohmylms-print-table is-data">
					<tbody>
						{ figure.table.map( ( row, index ) => (
							<tr key={ index }>
								<th>{ row.label }</th>
								<td>{ label( row.value ) }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
			<svg
				className="ohmylms-print-figure is-chart"
				viewBox="0 0 300 172"
				aria-hidden="true"
			>
				{ ticks.values.map( ( value, index ) => (
					<g key={ value }>
						<line
							x1={ left }
							y1={ y( value ) }
							x2={ right }
							y2={ y( value ) }
							stroke="#c3c4c7"
							strokeWidth="0.5"
						/>
						{ ( index % ticks.labelEvery === 0 ||
							index === ticks.values.length - 1 ) && (
							<text
								x={ left - 4 }
								y={ y( value ) + 3 }
								textAnchor="end"
								fontSize="8"
								fill="currentColor"
							>
								{ label( value ) }
							</text>
						) }
					</g>
				) ) }
				{ figure.unit && (
					<text
						x={ left - 4 }
						y="6"
						textAnchor="end"
						fontSize="8"
						fill="currentColor"
					>
						{ figure.unit }
					</text>
				) }
				{ categories.map( ( category, index ) => (
					<g key={ category.id }>
						{ figure.values && (
							<rect
								x={ left + index * width + width * 0.2 }
								y={ y(
									Math.min(
										max,
										figure.values[ category.id ]
									)
								) }
								width={ width * 0.6 }
								height={
									bottom -
									y(
										Math.min(
											max,
											figure.values[ category.id ]
										)
									)
								}
								fill="#f5c4c4"
								stroke={ KEY }
							/>
						) }
						<text
							x={ left + index * width + width / 2 }
							y={ bottom + 14 }
							textAnchor="middle"
							fontSize="8"
							fill="currentColor"
						>
							{ category.label }
						</text>
					</g>
				) ) }
				<path
					d={ `M${ left } ${ top }V${ bottom }H${ right }` }
					fill="none"
					stroke="currentColor"
				/>
			</svg>
		</>
	);
}

/**
 * Squared grid to draw on.
 *
 * @param {Object} props        Properties.
 * @param {Object} props.figure Figure model.
 * @return {Element} Grid.
 */
function Grid( { figure } ) {
	const { rows, cols } = figure;
	return (
		<svg
			className="ohmylms-print-figure is-grid-build"
			viewBox={ `0 0 ${ cols * 10 + 2 } ${ rows * 10 + 2 }` }
			style={ { width: `${ cols * 7 }mm` } }
			aria-hidden="true"
		>
			{ Array.from( { length: rows + 1 }, ( _, row ) => (
				<line
					key={ `r${ row }` }
					x1="1"
					y1={ 1 + row * 10 }
					x2={ 1 + cols * 10 }
					y2={ 1 + row * 10 }
					stroke="#8c8f94"
					strokeWidth="0.5"
				/>
			) ) }
			{ Array.from( { length: cols + 1 }, ( _, col ) => (
				<line
					key={ `c${ col }` }
					x1={ 1 + col * 10 }
					y1="1"
					x2={ 1 + col * 10 }
					y2={ 1 + rows * 10 }
					stroke="#8c8f94"
					strokeWidth="0.5"
				/>
			) ) }
		</svg>
	);
}

/**
 * Answer key lines of a figure.
 *
 * @param {Object} props        Properties.
 * @param {Object} props.figure Figure model.
 * @return {Element|null} Key.
 */
function FigureKey( { figure } ) {
	let lines = figure.key || [];
	if ( figure.constraints ) {
		const c = figure.constraints;
		lines = [
			c.area !== null &&
				sprintf(
					/* translators: %d: area in grid squares. */
					_n(
						'Area: %d square',
						'Area: %d squares',
						c.area,
						'ohmylms'
					),
					c.area
				),
			c.perimeter !== null &&
				sprintf(
					/* translators: %d: perimeter in grid units. */
					_n(
						'Perimeter: %d unit',
						'Perimeter: %d units',
						c.perimeter,
						'ohmylms'
					),
					c.perimeter
				),
			c.rectangle && __( 'The shape is a rectangle.', 'ohmylms' ),
			c.connected &&
				__( 'The squares form one connected shape.', 'ohmylms' ),
		].filter( Boolean );
	}
	if ( ! lines.length ) {
		return null;
	}
	return (
		<div className="ohmylms-print-key">
			{ lines.length === 1 ? (
				<p>
					{ sprintf(
						/* translators: %s: correct answer. */
						__( 'Answer: %s', 'ohmylms' ),
						lines[ 0 ]
					) }
				</p>
			) : (
				<>
					<p>{ __( 'Answer:', 'ohmylms' ) }</p>
					{ lines.map( ( line, index ) => (
						<p key={ index }>{ line }</p>
					) ) }
				</>
			) }
		</div>
	);
}

/**
 * The printed figure of a visual question, with its key when the model has one.
 *
 * @param {Object} props        Properties.
 * @param {Object} props.figure Figure model from paperVisual.mjs.
 * @return {Element|null} Figure.
 */
export function VisualFigure( { figure } ) {
	if ( ! figure ) {
		return null;
	}
	const drawing = {
		'number-line': NumberLine,
		'fill-level': Jug,
		'shade-model': ShadeModel,
		'set-clock': Clock,
		'count-blocks': PlaceValue,
		'make-amount': Money,
		'build-chart': Chart,
		'grid-build': Grid,
	}[ figure.figure ];
	return (
		<div className="ohmylms-print-visual">
			{ drawing && createElement( drawing, { figure } ) }
			{ figure.figure === 'make-amount' && (
				<div className="ohmylms-print-answer-line">
					{ __( 'Total:', 'ohmylms' ) }
					<span className="ohmylms-print-line" />
					<span className="ohmylms-print-unit">
						{ figure.symbol }
					</span>
				</div>
			) }
			<FigureKey figure={ figure } />
		</div>
	);
}
