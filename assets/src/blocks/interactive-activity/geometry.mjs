import JXG from 'jsxgraph';
import {
	angleDegrees,
	distance,
	formatNumber,
	isBowTie,
	polygonArea,
} from './activities.mjs';
import { createActivity, html, uniqueId } from './kit.mjs';

/** Starting corners of each shape on the 14 by 10 board. */
const START = {
	triangle: [
		[ 1, 1 ],
		[ 8, 1 ],
		[ 4, 7 ],
	],
	quadrilateral: [
		[ 2, 1 ],
		[ 9, 1 ],
		[ 9, 6 ],
		[ 2, 6 ],
	],
	circle: [
		[ 5, 5 ],
		[ 9, 5 ],
	],
	angle: [
		[ 10, 2 ],
		[ 3, 2 ],
		[ 6, 8 ],
	],
};

/**
 * Draggable shape with live lengths, angles and area.
 *
 * @param {Element} container Empty host element.
 * @param {Object}  raw       Stored configuration.
 * @param {Object}  options   Mount options.
 * @return {{update: function(Object): void, destroy: function(): void}} Handle.
 */
export function mountGeometry( container, raw, options = {} ) {
	return createActivity(
		'geometry',
		container,
		raw,
		options,
		( root, ctx ) => {
			const { doc } = ctx;
			const id = `ohmylms-activity-board-${ uniqueId() }`;
			const boardNode = html( doc, 'div', 'ohmylms-activity-board', {
				id,
				role: 'img',
				'aria-label': ctx.labels.geometry,
			} );
			const readout = html( doc, 'ul', 'ohmylms-activity-readout', {
				'aria-live': 'polite',
			} );
			root.append( boardNode, readout );
			let board = null;
			let points = [];

			const measures = () => {
				const at = points.map( ( point ) => [ point.X(), point.Y() ] );
				const { shape } = ctx.config;
				const rows = [];
				if ( shape === 'triangle' || shape === 'quadrilateral' ) {
					const sides = at.map( ( point, index ) =>
						distance( point, at[ ( index + 1 ) % at.length ] )
					);
					sides.forEach( ( length, index ) =>
						rows.push(
							`${ ctx.labels.side } ${ String.fromCharCode( 65 + index ) }${ String.fromCharCode( 65 + ( ( index + 1 ) % at.length ) ) } = ${ formatNumber( length ) }`
						)
					);
					rows.push(
						`${ ctx.labels.perimeter } = ${ formatNumber( sides.reduce( ( sum, length ) => sum + length, 0 ) ) }`
					);
					if ( shape === 'quadrilateral' && isBowTie( at ) ) {
						rows.push( ctx.labels.noArea );
					} else {
						rows.push(
							`${ ctx.labels.area } = ${ formatNumber( polygonArea( at ) ) }`
						);
					}
					at.forEach( ( point, index ) =>
						rows.push(
							`${ ctx.labels.angle } ${ String.fromCharCode( 65 + index ) } = ${ formatNumber( angleDegrees( at[ ( index + at.length - 1 ) % at.length ], point, at[ ( index + 1 ) % at.length ] ) ) }°`
						)
					);
				} else if ( shape === 'circle' ) {
					const radius = distance( at[ 0 ], at[ 1 ] );
					rows.push(
						`${ ctx.labels.radius } = ${ formatNumber( radius ) }`,
						`${ ctx.labels.circumference } = ${ formatNumber( 2 * Math.PI * radius ) }`,
						`${ ctx.labels.area } = ${ formatNumber( Math.PI * radius * radius ) }`
					);
				} else {
					rows.push(
						`${ ctx.labels.angle } = ${ formatNumber( angleDegrees( at[ 0 ], at[ 1 ], at[ 2 ] ) ) }°`
					);
				}
				readout.replaceChildren(
					...rows.map( ( row ) => html( doc, 'li', '', null, row ) )
				);
				readout.hidden = ! ctx.config.showMeasures;
			};

			const build = () => {
				if ( board ) {
					JXG.JSXGraph.freeBoard( board );
				}
				const { shape, snap } = ctx.config;
				board = JXG.JSXGraph.initBoard( id, {
					// The editor canvas is an iframe, so the board must look up its container there.
					document: doc,
					boundingBox: [ -1, 10.5, 13, -0.5 ],
					axis: false,
					grid: true,
					showCopyright: false,
					showNavigation: false,
					keepAspectRatio: true,
					pan: { enabled: false },
					zoom: { enabled: false },
				} );
				const names = [ 'A', 'B', 'C', 'D' ];
				points = START[ shape ].map( ( start, index ) =>
					board.create( 'point', start, {
						name: names[ index ],
						size: 5,
						snapToGrid: snap,
						snapSizeX: 1,
						snapSizeY: 1,
					} )
				);
				if ( shape === 'circle' ) {
					board.create( 'circle', [ points[ 0 ], points[ 1 ] ], {
						fillColor: 'none',
					} );
					board.create( 'segment', [ points[ 0 ], points[ 1 ] ], {
						dash: 2,
					} );
				} else if ( shape === 'angle' ) {
					board.create( 'segment', [ points[ 1 ], points[ 0 ] ] );
					board.create( 'segment', [ points[ 1 ], points[ 2 ] ] );
					board.create(
						'angle',
						[ points[ 0 ], points[ 1 ], points[ 2 ] ],
						{ radius: 1.2, name: '' }
					);
				} else {
					board.create( 'polygon', points, {
						fillOpacity: 0.12,
						vertices: { visible: true },
					} );
				}
				board.on( 'update', measures );
				measures();
			};

			return {
				reset: build,
				render: measures,
				dispose() {
					if ( board ) {
						JXG.JSXGraph.freeBoard( board );
						board = null;
					}
				},
			};
		}
	);
}
