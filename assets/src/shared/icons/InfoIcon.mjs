import { createElement, Fragment, memo } from '@wordpress/element';

/**
 * Grey circled "i" icon used next to field labels and hints.
 *
 * @param {Object} props           Component props.
 * @param {string} props.className Optional class name for the svg element.
 * @return {Element} Icon svg.
 */
function InfoIconSvg( { className } ) {
	return createElement(
		Fragment,
		null,
		createElement(
			'svg',
			{
				className,
				width: '14',
				height: '15',
				fill: 'none',
				viewBox: '0 0 14 15',
				xmlns: 'http://www.w3.org/2000/svg',
			},
			createElement( 'path', {
				fill: '#7A8B9A',
				fillRule: 'evenodd',
				d: 'M13.665 7.5a6.667 6.667 0 11-13.333 0 6.667 6.667 0 0113.333 0zM7 6.833c.368 0 .666.299.666.667v3.334a.667.667 0 11-1.333 0V7.5c0-.368.299-.667.667-.667zM7 5.5a.667.667 0 100-1.333A.667.667 0 007 5.5z',
				clipRule: 'evenodd',
			} )
		)
	);
}

/**
 * Memoized info icon (recovered module 79476).
 */
export const InfoIcon = memo( InfoIconSvg );
