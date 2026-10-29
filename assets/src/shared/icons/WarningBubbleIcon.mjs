import { createElement, Fragment, memo } from '@wordpress/element';

/**
 * Gold speech bubble with an exclamation mark, used for inline warnings.
 *
 * @return {Element} Icon svg.
 */
function WarningBubbleIconSvg() {
	return createElement(
		Fragment,
		null,
		createElement(
			'svg',
			{
				fill: 'none',
				width: '22',
				height: '22',
				viewBox: '0 0 22 22',
				xmlns: 'http://www.w3.org/2000/svg',
			},
			createElement( 'path', {
				fill: '#9B8540',
				d: 'M11 2.75a8.24 8.24 0 017.487 11.695 1.771 1.771 0 00-.055 1.301l.77 2.292a.923.923 0 01-1.164 1.164l-2.292-.77a1.771 1.771 0 00-1.301.055A8.25 8.25 0 1111 2.75zm-.687 11a.688.688 0 101.375 0V11a.688.688 0 00-1.375 0v2.75zm.67-4.583a.917.917 0 00.009-1.834h-.01a.917.917 0 000 1.834z',
			} )
		)
	);
}

/**
 * Memoized warning bubble icon (recovered module 75809).
 */
export const WarningBubbleIcon = memo( WarningBubbleIconSvg );
