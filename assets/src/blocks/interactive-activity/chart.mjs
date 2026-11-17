import { COLORS } from './config.mjs';
import { formatNumber, statistics } from './activities.mjs';
import { createActivity, html, svg } from './kit.mjs';

const WIDTH = 480;
const HEIGHT = 280;
const LEFT = 44;
const TOP = 28;
const BOTTOM = 40;

/**
 * Bar, line or pie chart of a small table the learner can edit.
 *
 * @param {Element} container Empty host element.
 * @param {Object}  raw       Stored configuration.
 * @param {Object}  options   Mount options.
 * @return {{update: function(Object): void, destroy: function(): void}} Handle.
 */
export function mountChart( container, raw, options = {} ) {
	return createActivity( 'chart', container, raw, options, ( root, ctx ) => {
		const { doc } = ctx;
		let values = [];
		const title = html( doc, 'h4', 'ohmylms-activity-title' );
		const view = svg( doc, 'svg', {
			viewBox: `0 0 ${ WIDTH } ${ HEIGHT }`,
			class: 'ohmylms-activity-svg is-chart',
			role: 'img',
		} );
		const table = html( doc, 'table', 'ohmylms-activity-table' );
		const stats = html( doc, 'p', 'ohmylms-activity-facts', {
			'aria-live': 'polite',
		} );
		root.append( title, view, table, stats );

		const plot = () => {
			const { kind, items } = ctx.config;
			view.replaceChildren();
			if ( kind === 'pie' ) {
				const total = values.reduce( ( sum, value ) => sum + value, 0 );
				const centre = [ HEIGHT / 2, HEIGHT / 2 ];
				const radius = HEIGHT / 2 - 16;
				if ( total === 0 ) {
					view.append(
						svg( doc, 'circle', {
							cx: centre[ 0 ],
							cy: centre[ 1 ],
							r: radius,
							class: 'ohmylms-activity-part',
							fill: 'none',
						} )
					);
				}
				let from = -Math.PI / 2;
				values.forEach( ( value, index ) => {
					if ( value <= 0 ) {
						return;
					}
					const to = from + ( value / total ) * Math.PI * 2;
					const colour = COLORS[ index % COLORS.length ];
					if ( value === total ) {
						view.append(
							svg( doc, 'circle', {
								cx: centre[ 0 ],
								cy: centre[ 1 ],
								r: radius,
								fill: colour,
							} )
						);
					} else {
						const at = ( angle ) =>
							`${ centre[ 0 ] + radius * Math.cos( angle ) } ${ centre[ 1 ] + radius * Math.sin( angle ) }`;
						view.append(
							svg( doc, 'path', {
								d: `M ${ centre[ 0 ] } ${ centre[ 1 ] } L ${ at( from ) } A ${ radius } ${ radius } 0 ${ to - from > Math.PI ? 1 : 0 } 1 ${ at( to ) } Z`,
								fill: colour,
							} )
						);
					}
					from = to;
				} );
				items.forEach( ( item, index ) => {
					const y = 30 + index * 20;
					view.append(
						svg( doc, 'rect', {
							x: HEIGHT + 20,
							y: y - 11,
							width: 12,
							height: 12,
							fill: COLORS[ index % COLORS.length ],
						} ),
						svg(
							doc,
							'text',
							{
								x: HEIGHT + 38,
								y,
								class: 'ohmylms-activity-tick-label',
							},
							`${ item.label } (${ formatNumber( values[ index ] ) })`
						)
					);
				} );
				return;
			}
			const max = Math.max( 1, ...values );
			const plotWidth = WIDTH - LEFT - 12;
			const plotHeight = HEIGHT - TOP - BOTTOM;
			const slot = plotWidth / items.length;
			const y = ( value ) =>
				TOP + plotHeight - ( value / max ) * plotHeight;
			view.append(
				svg( doc, 'line', {
					x1: LEFT,
					y1: TOP,
					x2: LEFT,
					y2: TOP + plotHeight,
					class: 'ohmylms-activity-axis',
				} ),
				svg( doc, 'line', {
					x1: LEFT,
					y1: TOP + plotHeight,
					x2: WIDTH - 12,
					y2: TOP + plotHeight,
					class: 'ohmylms-activity-axis',
				} ),
				svg(
					doc,
					'text',
					{
						x: LEFT - 6,
						y: TOP + 4,
						'text-anchor': 'end',
						class: 'ohmylms-activity-tick-label',
					},
					formatNumber( max )
				),
				svg(
					doc,
					'text',
					{
						x: LEFT - 6,
						y: TOP + plotHeight + 4,
						'text-anchor': 'end',
						class: 'ohmylms-activity-tick-label',
					},
					'0'
				)
			);
			items.forEach( ( item, index ) => {
				const centre = LEFT + slot * index + slot / 2;
				view.append(
					svg(
						doc,
						'text',
						{
							x: centre,
							y: TOP + plotHeight + 18,
							'text-anchor': 'middle',
							class: 'ohmylms-activity-tick-label',
						},
						item.label.slice( 0, 12 )
					)
				);
				if ( kind === 'bar' ) {
					view.append(
						svg( doc, 'rect', {
							x: centre - slot * 0.35,
							y: y( values[ index ] ),
							width: slot * 0.7,
							height: TOP + plotHeight - y( values[ index ] ),
							fill: COLORS[ index % COLORS.length ],
						} ),
						svg(
							doc,
							'text',
							{
								x: centre,
								y: y( values[ index ] ) - 5,
								'text-anchor': 'middle',
								class: 'ohmylms-activity-tick-label',
							},
							formatNumber( values[ index ] )
						)
					);
				}
			} );
			if ( kind === 'line' ) {
				const points = values.map( ( value, index ) => [
					LEFT + slot * index + slot / 2,
					y( value ),
				] );
				view.append(
					svg( doc, 'polyline', {
						points: points
							.map( ( point ) => point.join( ',' ) )
							.join( ' ' ),
						fill: 'none',
						stroke: COLORS[ 0 ],
						'stroke-width': 3,
					} ),
					...points.map( ( point ) =>
						svg( doc, 'circle', {
							cx: point[ 0 ],
							cy: point[ 1 ],
							r: 5,
							fill: COLORS[ 0 ],
						} )
					)
				);
			}
		};

		const render = () => {
			title.textContent = ctx.config.title;
			title.hidden = ! ctx.config.title;
			view.setAttribute(
				'aria-label',
				ctx.config.title || ctx.labels.chart
			);
			plot();
			const summary = statistics( values );
			stats.textContent = [
				`${ ctx.labels.total }: ${ formatNumber( summary.total ) }`,
				`${ ctx.labels.mean }: ${ formatNumber( summary.mean ) }`,
				`${ ctx.labels.median }: ${ formatNumber( summary.median ) }`,
				`${ ctx.labels.mode }: ${ summary.modes.length ? summary.modes.map( formatNumber ).join( ', ' ) : ctx.labels.noMode }`,
				`${ ctx.labels.range }: ${ formatNumber( summary.range ) }`,
			].join( ' · ' );
		};

		return {
			// The table is built once per configuration so typing never loses focus.
			reset() {
				values = ctx.config.items.map( ( item ) => item.value );
				table.replaceChildren(
					...ctx.config.items.map( ( item, index ) => {
						const row = html( doc, 'tr' );
						const value = html( doc, 'td' );
						if ( ctx.config.allowEdit ) {
							const input = html( doc, 'input', '', {
								type: 'number',
								min: 0,
								step: 'any',
								value: item.value,
								'aria-label': `${ item.label } ${ ctx.labels.value }`,
							} );
							input.addEventListener( 'input', () => {
								const next = Number( input.value );
								if (
									input.value !== '' &&
									Number.isFinite( next )
								) {
									values[ index ] = Math.min(
										1e6,
										Math.max( 0, next )
									);
									render();
								}
							} );
							value.append( input );
						} else {
							value.textContent = formatNumber( item.value );
						}
						row.append(
							html( doc, 'th', '', { scope: 'row' }, item.label ),
							value
						);
						return row;
					} )
				);
			},
			render,
		};
	} );
}
