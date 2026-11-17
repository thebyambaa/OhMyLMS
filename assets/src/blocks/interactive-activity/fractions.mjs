import { COLORS } from './config.mjs';
import { MAX_PARTS, formatNumber, fractionFacts } from './activities.mjs';
import { createActivity, field, html, svg } from './kit.mjs';

const SIZE = 240;

/**
 * @param {number} centre Centre x and y.
 * @param {number} radius Radius.
 * @param {number} index  Part number.
 * @param {number} parts  Equal parts.
 * @return {string} Path of one pie wedge, starting at 12 o'clock.
 */
function wedge( centre, radius, index, parts ) {
	const start = ( ( index / parts ) * 2 - 0.5 ) * Math.PI;
	const end = ( ( ( index + 1 ) / parts ) * 2 - 0.5 ) * Math.PI;
	const point = ( angle ) =>
		`${ centre + radius * Math.cos( angle ) } ${
			centre + radius * Math.sin( angle )
		}`;
	const large = 1 / parts > 0.5 ? 1 : 0;
	return `M ${ centre } ${ centre } L ${ point( start ) } A ${ radius } ${ radius } 0 ${ large } 1 ${ point(
		end
	) } Z`;
}

/**
 * Fraction bar or circle whose parts the learner shades.
 *
 * @param {Element} container Empty host element.
 * @param {Object}  raw       Stored configuration.
 * @param {Object}  options   Mount options.
 * @return {{update: function(Object): void, destroy: function(): void}} Handle.
 */
export function mountFractions( container, raw, options = {} ) {
	return createActivity(
		'fractions',
		container,
		raw,
		options,
		( root, ctx ) => {
			const { doc } = ctx;
			let parts = 4;
			let shaded = new Set();
			const view = svg( doc, 'svg', {
				class: 'ohmylms-activity-svg is-fractions',
				role: 'group',
				'aria-label': ctx.labels.fractions,
			} );
			const readout = html( doc, 'p', 'ohmylms-activity-facts', {
				'aria-live': 'polite',
			} );
			const partsField = field( doc, ctx.labels.parts, {
				type: 'range',
				min: 1,
				max: MAX_PARTS,
				step: 1,
			} );
			const partsValue = html(
				doc,
				'output',
				'ohmylms-activity-param-value'
			);
			partsField.field.append( partsValue );
			root.append( view, partsField.field, readout );

			const toggle = ( index ) => {
				if ( shaded.has( index ) ) {
					shaded.delete( index );
				} else {
					shaded.add( index );
				}
				render();
			};

			partsField.input.addEventListener( 'input', () => {
				const count = shaded.size;
				parts = Number( partsField.input.value );
				shaded = new Set(
					Array.from(
						{ length: Math.min( count, parts ) },
						( unused, i ) => i
					)
				);
				render();
			} );

			const render = () => {
				const circle = ctx.config.shape === 'circle';
				view.setAttribute(
					'viewBox',
					`0 0 ${ SIZE } ${ circle ? SIZE : SIZE / 3 }`
				);
				view.replaceChildren();
				for ( let i = 0; i < parts; i++ ) {
					const on = shaded.has( i );
					const common = {
						class: `ohmylms-activity-part${ on ? ' is-shaded' : '' }`,
						fill: on ? COLORS[ 0 ] : 'none',
						tabindex: 0,
						role: 'checkbox',
						'aria-checked': on,
						'aria-label': `${ ctx.labels.part } ${ i + 1 }`,
					};
					let piece;
					if ( ! circle ) {
						piece = svg( doc, 'rect', {
							...common,
							x: 8 + ( i * ( SIZE - 16 ) ) / parts,
							y: 8,
							width: ( SIZE - 16 ) / parts,
							height: SIZE / 3 - 16,
						} );
					} else if ( parts === 1 ) {
						piece = svg( doc, 'circle', {
							...common,
							cx: SIZE / 2,
							cy: SIZE / 2,
							r: SIZE / 2 - 8,
						} );
					} else {
						piece = svg( doc, 'path', {
							...common,
							d: wedge( SIZE / 2, SIZE / 2 - 8, i, parts ),
						} );
					}
					piece.addEventListener( 'click', () => toggle( i ) );
					piece.addEventListener( 'keydown', ( event ) => {
						if ( event.key === ' ' || event.key === 'Enter' ) {
							event.preventDefault();
							toggle( i );
						}
					} );
					view.append( piece );
				}
				partsField.input.value = parts;
				partsValue.textContent = String( parts );
				partsField.field.hidden = ! ctx.config.allowParts;
				const facts = fractionFacts( shaded.size, parts );
				const same =
					facts.simple[ 0 ] === facts.numerator &&
					facts.simple[ 1 ] === facts.denominator;
				readout.textContent = [
					`${ ctx.labels.shaded }: ${ facts.numerator }/${ facts.denominator }`,
					same
						? ''
						: `${ ctx.labels.simplified }: ${ facts.simple[ 0 ] }/${ facts.simple[ 1 ] }`,
					`${ ctx.labels.decimal }: ${ formatNumber( facts.decimal ) }`,
					`${ ctx.labels.percent }: ${ formatNumber( facts.percent ) }%`,
				]
					.filter( Boolean )
					.join( ' · ' );
			};

			return {
				reset() {
					parts = ctx.config.parts;
					shaded = new Set(
						Array.from(
							{ length: ctx.config.shaded },
							( unused, i ) => i
						)
					);
				},
				render,
			};
		}
	);
}
