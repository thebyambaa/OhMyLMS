import { clockAngles, clockText } from './activities.mjs';
import { createActivity, field, html, svg } from './kit.mjs';

const SIZE = 220;
const CENTRE = SIZE / 2;

/**
 * Analogue clock the learner sets by dragging the face or with the sliders.
 *
 * @param {Element} container Empty host element.
 * @param {Object}  raw       Stored configuration.
 * @param {Object}  options   Mount options.
 * @return {{update: function(Object): void, destroy: function(): void}} Handle.
 */
export function mountClock( container, raw, options = {} ) {
	return createActivity( 'clock', container, raw, options, ( root, ctx ) => {
		const { doc } = ctx;
		let hour = 3;
		let minute = 0;
		const face = svg( doc, 'svg', {
			viewBox: `0 0 ${ SIZE } ${ SIZE }`,
			class: 'ohmylms-activity-svg is-clock',
			role: 'img',
		} );
		face.append(
			svg( doc, 'circle', {
				cx: CENTRE,
				cy: CENTRE,
				r: CENTRE - 6,
				class: 'ohmylms-activity-clock-face',
			} )
		);
		for ( let i = 1; i <= 12; i++ ) {
			const angle = ( i * Math.PI ) / 6;
			face.append(
				svg(
					doc,
					'text',
					{
						x: CENTRE + Math.sin( angle ) * ( CENTRE - 26 ),
						y: CENTRE - Math.cos( angle ) * ( CENTRE - 26 ) + 5,
						'text-anchor': 'middle',
						class: 'ohmylms-activity-tick-label',
					},
					String( i )
				)
			);
		}
		const hourHand = svg( doc, 'line', {
			x1: CENTRE,
			y1: CENTRE,
			x2: CENTRE,
			y2: CENTRE - 55,
			class: 'ohmylms-activity-hand is-hour',
		} );
		const minuteHand = svg( doc, 'line', {
			x1: CENTRE,
			y1: CENTRE,
			x2: CENTRE,
			y2: CENTRE - 82,
			class: 'ohmylms-activity-hand is-minute',
		} );
		face.append(
			hourHand,
			minuteHand,
			svg( doc, 'circle', { cx: CENTRE, cy: CENTRE, r: 5 } )
		);
		const digital = html( doc, 'p', 'ohmylms-activity-digital', {
			'aria-live': 'polite',
		} );
		const hours = field( doc, ctx.labels.hours, {
			type: 'range',
			min: 0,
			max: 23,
			step: 1,
		} );
		const minutes = field( doc, ctx.labels.minutes, {
			type: 'range',
			min: 0,
			max: 59,
			step: 1,
		} );
		const controls = html( doc, 'div', 'ohmylms-activity-controls' );
		controls.append( hours.field, minutes.field );
		root.append( face, digital, controls );

		const snapMinute = ( value ) => {
			const snap = ctx.config.snap;
			return ( Math.round( value / snap ) * snap ) % 60;
		};
		hours.input.addEventListener( 'input', () => {
			hour = Number( hours.input.value );
			render();
		} );
		minutes.input.addEventListener( 'input', () => {
			minute = snapMinute( Number( minutes.input.value ) );
			render();
		} );
		face.addEventListener( 'pointerdown', ( event ) => {
			event.preventDefault();
			const move = ( moved ) => {
				const box = face.getBoundingClientRect();
				const dx = moved.clientX - ( box.left + box.width / 2 );
				const dy = moved.clientY - ( box.top + box.height / 2 );
				const degrees =
					( ( Math.atan2( dx, -dy ) * 180 ) / Math.PI + 360 ) % 360;
				const next = snapMinute( degrees / 6 );
				// Passing 12 carries the hour with it.
				if ( minute >= 45 && next < 15 ) {
					hour = ( hour + 1 ) % 24;
				} else if ( minute < 15 && next >= 45 ) {
					hour = ( hour + 23 ) % 24;
				}
				minute = next;
				render();
			};
			const stop = () => {
				doc.removeEventListener( 'pointermove', move );
				doc.removeEventListener( 'pointerup', stop );
			};
			move( event );
			doc.addEventListener( 'pointermove', move );
			doc.addEventListener( 'pointerup', stop );
		} );

		const render = () => {
			const angles = clockAngles( hour, minute );
			hourHand.setAttribute(
				'transform',
				`rotate(${ angles.hour } ${ CENTRE } ${ CENTRE })`
			);
			minuteHand.setAttribute(
				'transform',
				`rotate(${ angles.minute } ${ CENTRE } ${ CENTRE })`
			);
			const time = clockText( hour, minute, ctx.config.format24 );
			face.setAttribute(
				'aria-label',
				`${ ctx.labels.clock }: ${ time }`
			);
			digital.textContent = time;
			digital.hidden = ! ctx.config.showDigital;
			hours.input.value = hour;
			minutes.input.value = minute;
			minutes.input.step = ctx.config.snap;
		};

		return {
			reset() {
				hour = ctx.config.hour;
				minute = ctx.config.minute;
			},
			render,
		};
	} );
}
