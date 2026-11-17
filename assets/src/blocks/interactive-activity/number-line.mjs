import { COLORS } from './config.mjs';
import { formatNumber } from './activities.mjs';
import { createActivity, html, svg } from './kit.mjs';

const WIDTH = 640;
const HEIGHT = 120;
const PAD = 32;
const AXIS = 64;

/**
 * Number line with draggable points.
 *
 * @param {Element} container Empty host element.
 * @param {Object}  raw       Stored configuration.
 * @param {Object}  options   Mount options.
 * @return {{update: function(Object): void, destroy: function(): void}} Handle.
 */
export function mountNumberLine( container, raw, options = {} ) {
	return createActivity(
		'number-line',
		container,
		raw,
		options,
		( root, ctx ) => {
			const { doc } = ctx;
			let values = [];
			const board = svg( doc, 'svg', {
				viewBox: `0 0 ${ WIDTH } ${ HEIGHT }`,
				class: 'ohmylms-activity-svg',
				role: 'group',
				'aria-label': ctx.labels.numberLine,
			} );
			const readout = html( doc, 'ul', 'ohmylms-activity-readout' );
			root.append( board, readout );

			const x = ( value ) =>
				PAD +
				( ( value - ctx.config.min ) /
					( ctx.config.max - ctx.config.min ) ) *
					( WIDTH - 2 * PAD );
			const snapped = ( value ) => {
				const { min, max, step, snap } = ctx.config;
				const next = snap
					? min + Math.round( ( value - min ) / step ) * step
					: value;
				return Math.min(
					max,
					Math.max( min, Number( next.toFixed( 9 ) ) )
				);
			};
			const name = ( index ) =>
				`${ ctx.labels.point } ${ String.fromCharCode( 65 + index ) }`;

			const setValue = ( index, value, refocus ) => {
				values[ index ] = snapped( value );
				render();
				if ( refocus ) {
					board.querySelectorAll( '[role=slider]' )[ index ]?.focus();
				}
			};

			const render = () => {
				const { min, max, step } = ctx.config;
				board.replaceChildren();
				board.append(
					svg( doc, 'line', {
						x1: PAD,
						y1: AXIS,
						x2: WIDTH - PAD,
						y2: AXIS,
						class: 'ohmylms-activity-axis',
					} )
				);
				// Label about ten ticks at most; draw every step when there are few.
				const count = Math.round( ( max - min ) / step );
				const every = Math.max( 1, Math.ceil( count / 10 ) );
				// A tiny step would mean millions of ticks; only the labelled ones are drawn then.
				const stride = count > 60 ? every : 1;
				for ( let i = 0; i <= count; i += stride ) {
					const value = min + i * step;
					const labelled = i % every === 0;
					const reach = labelled ? 9 : 5;
					board.append(
						svg( doc, 'line', {
							x1: x( value ),
							y1: AXIS - reach,
							x2: x( value ),
							y2: AXIS + reach,
							class: 'ohmylms-activity-tick',
						} )
					);
					if ( labelled ) {
						board.append(
							svg(
								doc,
								'text',
								{
									x: x( value ),
									y: AXIS + 28,
									'text-anchor': 'middle',
									class: 'ohmylms-activity-tick-label',
								},
								formatNumber( value )
							)
						);
					}
				}
				values.forEach( ( value, index ) => {
					const handle = svg( doc, 'circle', {
						cx: x( value ),
						cy: AXIS,
						r: 11,
						fill: COLORS[ index % COLORS.length ],
						class: 'ohmylms-activity-handle',
						tabindex: 0,
						role: 'slider',
						'aria-label': name( index ),
						'aria-valuemin': min,
						'aria-valuemax': max,
						'aria-valuenow': value,
					} );
					handle.addEventListener( 'pointerdown', ( event ) => {
						event.preventDefault();
						const move = ( moved ) => {
							const box = board.getBoundingClientRect();
							const px =
								( ( moved.clientX - box.left ) / box.width ) *
								WIDTH;
							setValue(
								index,
								min +
									( ( px - PAD ) / ( WIDTH - 2 * PAD ) ) *
										( max - min )
							);
						};
						const stop = () => {
							doc.removeEventListener( 'pointermove', move );
							doc.removeEventListener( 'pointerup', stop );
						};
						doc.addEventListener( 'pointermove', move );
						doc.addEventListener( 'pointerup', stop );
					} );
					handle.addEventListener( 'keydown', ( event ) => {
						const moves = {
							ArrowRight: step,
							ArrowUp: step,
							ArrowLeft: -step,
							ArrowDown: -step,
						};
						if ( event.key in moves ) {
							setValue( index, value + moves[ event.key ], true );
						} else if ( event.key === 'Home' ) {
							setValue( index, min, true );
						} else if ( event.key === 'End' ) {
							setValue( index, max, true );
						} else {
							return;
						}
						event.preventDefault();
					} );
					board.append( handle );
				} );
				readout.replaceChildren(
					...values.map( ( value, index ) =>
						html(
							doc,
							'li',
							'',
							null,
							`${ name( index ) } = ${ formatNumber( value ) }`
						)
					)
				);
			};

			return {
				reset() {
					values = ctx.config.points.map( snapped );
				},
				render,
			};
		}
	);
}
