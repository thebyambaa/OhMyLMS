import { DropdownMenu, Icon, Path, SVG } from '@wordpress/components';
import { createElement, Fragment, memo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { FlexWP } from '../layout/FlexWP.mjs';
import { ButtonWP } from './ButtonWP.mjs';
import {
	getDropdownButtonClass,
	getDropdownButtonMenuClass,
} from './model.mjs';

const wrapperStyle = {
	border: '1px solid var(--ohmylms-primary-color)',
	width: 'fit-content',
	borderRadius: '4px',
};

/** Chevron-down icon of the arrow button. */
const chevronDown = createElement(
	SVG,
	{ viewBox: '0 0 24 24', xmlns: 'http://www.w3.org/2000/svg' },
	createElement( Path, {
		d: 'M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z',
	} )
);

/**
 * Split button: a primary button plus an arrow that opens a dropdown menu.
 *
 * @param {Object}   props               Component props.
 * @param {Object[]} [props.menuItems]   Menu controls. Defaults to [].
 * @param {string}   [props.className]   Extra class for the arrow menu. Defaults to "".
 * @param {string}   [props.buttonLabel] Main button label. Defaults to "Actions".
 * @param {Function} [props.onClick]     Main button click handler.
 * @param {boolean}  [props.loading]     Loading state of the main button. Defaults to false.
 * @return {import('react').ReactElement} Split button.
 */
function DropdownButtonWPBase( {
	menuItems = [],
	className = '',
	buttonLabel = __( 'Actions', 'ohmylms' ),
	onClick,
	loading = false,
} ) {
	return createElement(
		Fragment,
		null,
		createElement(
			FlexWP,
			{
				align: 'center',
				gap: 0,
				justify: 'flex-start',
				className: getDropdownButtonClass( loading ),
				style: wrapperStyle,
			},
			createElement(
				ButtonWP,
				{ loading, onClick, variant: 'primary' },
				buttonLabel
			),
			createElement( DropdownMenu, {
				className: getDropdownButtonMenuClass( className ),
				controls: menuItems,
				icon: createElement( Icon, { icon: chevronDown } ),
			} )
		)
	);
}

export const DropdownButtonWP = memo( DropdownButtonWPBase );
