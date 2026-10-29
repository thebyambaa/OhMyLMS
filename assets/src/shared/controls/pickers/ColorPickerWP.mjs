import { ColorIndicator, ColorPicker } from '@wordpress/components';
import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import { ButtonWP } from '../actions/ButtonWP.mjs';
import {
	getIndicatorClassName,
	getNextColor,
	isOutside,
} from './color-model.mjs';

const containerStyle = { position: 'relative', display: 'flex', gap: '8px' };
const resetStyle = { padding: '4px 0' };
const popoverStyle = {
	position: 'absolute',
	zIndex: 1000,
	top: '100%',
	right: 0,
	marginTop: '8px',
	boxShadow: '0 0 10px rgba(0,0,0,0.2)',
	background: 'white',
	borderRadius: '4px',
};
const strokeProps = {
	stroke: '#000',
	strokeLinecap: 'round',
	strokeLinejoin: 'round',
	strokeWidth: '2',
};

const resetIcon = createElement(
	'svg',
	{
		width: '26',
		height: '25',
		fill: 'none',
		viewBox: '0 0 26 25',
		xmlns: 'http://www.w3.org/2000/svg',
	},
	createElement( 'path', {
		...strokeProps,
		d: 'M1 2.088v6.546h6.545M25 22.543v-6.545h-6.546',
	} ),
	createElement( 'path', {
		...strokeProps,
		d: 'M22.262 7.543a9.819 9.819 0 00-16.2-3.666L1 8.634m24 7.363l-5.062 4.757a9.82 9.82 0 01-16.2-3.666',
	} )
);

/**
 * Colour swatch that opens a colour picker popover; clicking outside closes it.
 *
 * @param {Object}                    props
 * @param {string}                    [props.initialColor]   Colour shown; follows later changes of the prop.
 * @param {string}                    [props.defaultColor]   Colour the reset button restores, empty by default.
 * @param {boolean}                   [props.isShowResetBtn] Show the reset button.
 * @param {boolean}                   [props.showColorCode]  Show the colour code beside the swatch.
 * @param {boolean}                   [props.disableAlpha]   Hide the alpha slider.
 * @param {boolean}                   [props.disabled]       Stops the swatch following changes; `onChange` is still called.
 * @param {( color: Object ) => void} [props.onChange]       Called with the colour object (`{ hex, ... }`) after a pick or reset.
 * @return {import('react').ReactElement} Colour picker.
 */
export function ColorPickerWP( {
	defaultColor = '',
	isShowResetBtn = false,
	initialColor,
	onChange,
	disableAlpha = false,
	showColorCode = false,
	disabled,
} ) {
	const [ color, setColor ] = useState( initialColor );
	const [ isOpen, setIsOpen ] = useState( false );
	const container = useRef( null );

	const change = ( value ) => {
		if ( ! disabled ) {
			setColor( getNextColor( color, value, disabled ) );
		}
		if ( onChange ) {
			onChange( value );
		}
	};

	useEffect( () => {
		const close = ( event ) => {
			if ( isOutside( container.current, event.target ) ) {
				setIsOpen( false );
			}
		};
		if ( isOpen ) {
			document.addEventListener( 'mousedown', close );
		} else {
			document.removeEventListener( 'mousedown', close );
		}
		return () => document.removeEventListener( 'mousedown', close );
	}, [ isOpen ] );

	useEffect( () => {
		if ( initialColor ) {
			setColor( initialColor );
		}
	}, [ initialColor ] );

	return createElement(
		'div',
		{ ref: container, style: containerStyle },
		createElement(
			'div',
			{
				'aria-pressed': isOpen,
				'aria-label': 'Toggle color picker',
				className: getIndicatorClassName( isOpen ),
				onClick: () => setIsOpen( ( open ) => ! open ),
				disabled,
			},
			createElement( ColorIndicator, { colorValue: color } ),
			showColorCode &&
				createElement(
					'span',
					{ className: 'ohmylms-color-code' },
					color
				)
		),
		isShowResetBtn &&
			createElement(
				ButtonWP,
				{
					variant: 'secondary',
					className: 'ohmylms-color-reset',
					style: resetStyle,
					onClick: () => change( { hex: defaultColor } ),
				},
				resetIcon
			),
		isOpen &&
			createElement(
				'div',
				{
					className: 'ohmylms-color-picker-popover',
					style: popoverStyle,
				},
				createElement( ColorPicker, {
					color,
					onChangeComplete: change,
					disableAlpha,
				} )
			)
	);
}
