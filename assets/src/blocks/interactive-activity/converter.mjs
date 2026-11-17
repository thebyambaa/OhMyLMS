import { DEFAULT_UNITS, UNITS, convert, formatNumber } from './activities.mjs';
import { createActivity, field, html } from './kit.mjs';

/**
 * Unit converter: type an amount, pick two units and read the result.
 *
 * @param {Element} container Empty host element.
 * @param {Object}  raw       Stored configuration.
 * @param {Object}  options   Mount options.
 * @return {{update: function(Object): void, destroy: function(): void}} Handle.
 */
export function mountConverter( container, raw, options = {} ) {
	return createActivity(
		'converter',
		container,
		raw,
		options,
		( root, ctx ) => {
			const { doc } = ctx;
			let state = { quantity: 'length', from: 'm', to: 'cm', value: 1 };
			const quantity = field( doc, ctx.labels.quantity, {}, 'select' );
			const amount = field( doc, ctx.labels.amount, {
				type: 'number',
				step: 'any',
			} );
			const from = field( doc, ctx.labels.from, {}, 'select' );
			const to = field( doc, ctx.labels.to, {}, 'select' );
			const result = html( doc, 'p', 'ohmylms-activity-digital', {
				'aria-live': 'polite',
			} );
			const controls = html( doc, 'div', 'ohmylms-activity-controls' );
			controls.append(
				quantity.field,
				amount.field,
				from.field,
				to.field
			);
			root.append( controls, result );

			Object.keys( UNITS ).forEach( ( name ) =>
				quantity.input.append(
					html(
						doc,
						'option',
						'',
						{ value: name },
						ctx.labels[ name ]
					)
				)
			);

			quantity.input.addEventListener( 'change', () => {
				const next = quantity.input.value;
				state = {
					...state,
					quantity: next,
					from: DEFAULT_UNITS[ next ][ 0 ],
					to: DEFAULT_UNITS[ next ][ 1 ],
				};
				render();
			} );
			amount.input.addEventListener( 'input', () => {
				const value = Number( amount.input.value );
				if ( amount.input.value !== '' && Number.isFinite( value ) ) {
					state = { ...state, value };
					render();
				}
			} );
			from.input.addEventListener( 'change', () => {
				state = { ...state, from: from.input.value };
				render();
			} );
			to.input.addEventListener( 'change', () => {
				state = { ...state, to: to.input.value };
				render();
			} );

			const fill = ( select, current ) => {
				select.replaceChildren(
					...Object.keys( UNITS[ state.quantity ] ).map( ( unit ) =>
						html( doc, 'option', '', { value: unit }, unit )
					)
				);
				select.value = current;
			};

			const render = () => {
				quantity.input.value = state.quantity;
				quantity.field.hidden = ! ctx.config.allowQuantity;
				fill( from.input, state.from );
				fill( to.input, state.to );
				if ( doc.activeElement !== amount.input ) {
					amount.input.value = state.value;
				}
				const out = convert(
					state.value,
					state.quantity,
					state.from,
					state.to
				);
				result.textContent = `${ formatNumber( state.value ) } ${
					state.from
				} = ${ formatNumber( out ) } ${ state.to }`;
			};

			return {
				reset() {
					const {
						quantity: kind,
						from: a,
						to: b,
						value,
					} = ctx.config;
					state = { quantity: kind, from: a, to: b, value };
				},
				render,
			};
		}
	);
}
