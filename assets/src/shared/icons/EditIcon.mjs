import { createElement, memo } from '@wordpress/element';

/**
 * Pencil icon used for "edit" row actions.
 *
 * @param {Object}        props        Component props.
 * @param {number|string} props.width  Icon width. Defaults to 16.
 * @param {number|string} props.height Icon height. Defaults to 17.
 * @return {Element} Icon wrapped in a span.
 */
function EditIconSvg( { width = 16, height = 17 } ) {
	return createElement(
		'span',
		null,
		createElement(
			'svg',
			{
				className: 'edit-icon-svg',
				width,
				height,
				fill: 'none',
				viewBox: '0 0 16 17',
				xmlns: 'http://www.w3.org/2000/svg',
			},
			createElement( 'path', {
				fill: 'currentColor',
				fillRule: 'evenodd',
				d: 'M10.374 1.54a2.5 2.5 0 013.535 0l1.179 1.18a2.5 2.5 0 010 3.535l-8.66 8.66a.834.834 0 01-.426.228l-4.42.884a.833.833 0 01-.98-.98l.883-4.42a.834.834 0 01.228-.426l8.66-8.66zm2.357 1.18l1.178 1.178a.833.833 0 010 1.178l-1.178 1.179-2.357-2.357 1.178-1.179a.833.833 0 011.179 0zM9.195 5.075L3.07 11.201l-.589 2.947 2.946-.59 6.125-6.125-2.357-2.357z',
				clipRule: 'evenodd',
			} )
		)
	);
}

/**
 * Memoized pencil icon (recovered module 22601).
 */
export const EditIcon = memo( EditIconSvg );
