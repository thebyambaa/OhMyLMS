import { createElement, Fragment, memo } from '@wordpress/element';

/**
 * Plus sign for "add" buttons, drawn in the current text colour.
 *
 * @return {import('react').ReactElement} 14px SVG.
 */
function PlusIconBase() {
	return createElement(
		Fragment,
		null,
		createElement(
			'svg',
			{
				fill: 'none',
				width: '14',
				height: '14',
				viewBox: '0 0 14 14',
				xmlns: 'http://www.w3.org/2000/svg',
			},
			createElement( 'path', {
				fill: 'currentColor',
				stroke: 'currentColor',
				d: 'M1.6 6.4h4.8V1.6a.6.6 0 011.2 0v4.8h4.8a.6.6 0 010 1.2H7.6v4.8a.6.6 0 01-1.2 0V7.6H1.6a.6.6 0 010-1.2z',
			} )
		)
	);
}

export const PlusIcon = memo( PlusIconBase );
