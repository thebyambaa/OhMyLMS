import { createElement, memo } from '@wordpress/element';

/**
 * Empty-state picture: a tilted card on a pale blob.
 *
 * @return {import('react').ReactElement} 109 by 108 SVG.
 */
function NoDataIllustrationBase() {
	return createElement(
		'svg',
		{
			width: '109',
			height: '108',
			fill: 'none',
			viewBox: '0 0 109 108',
			xmlns: 'http://www.w3.org/2000/svg',
		},
		createElement( 'path', {
			fill: '#7A8B9A',
			fillOpacity: '.09',
			d: 'M86.91 72.302c4.358-3.37 6.787-18.542-2.722-33.757-9.508-15.216-20.302-21.28-30.61-20.166-10.307 1.114-13.663 7.443-18.257 8.646-4.594 1.202-11.96-.54-14.174 7.445-2.215 7.984 5.478 14.175 4.036 17.98-1.443 3.803-3.01.549-6.136 5.968-3.128 5.419-1.451 19.19 14.592 26.12s34.916 5.734 39.318-.453c4.401-6.186-1.526-8.782 1.78-10.203 3.306-1.422 7.814 1.789 12.172-1.58z',
		} ),
		createElement( 'path', {
			stroke: '#000D25',
			strokeLinecap: 'round',
			strokeLinejoin: 'round',
			strokeWidth: '1.5',
			d: 'M69.408 34.11c2.946-.79 4.42-1.184 5.58-.514 1.16.67 1.555 2.143 2.344 5.09l5.825 21.737c.79 2.946 1.184 4.42.514 5.58-.67 1.16-2.143 1.555-5.089 2.344L39.9 78.712c-2.946.79-4.42 1.184-5.58.514-1.16-.67-1.555-2.143-2.344-5.089L26.15 52.4c-.79-2.946-1.184-4.419-.514-5.58.67-1.16 2.143-1.555 5.09-2.344L69.407 34.11z',
		} ),
		createElement( 'path', {
			stroke: '#000D25',
			strokeLinecap: 'round',
			strokeLinejoin: 'round',
			strokeWidth: '1.5',
			d: 'M35.604 54.288l11.148-3.093c.541-.15 1.017-.282 1.438-.397m-7.438 7.49L51.9 55.195c.54-.15 1.016-.282 1.438-.397',
		} )
	);
}

export const NoDataIllustration = memo( NoDataIllustrationBase );
