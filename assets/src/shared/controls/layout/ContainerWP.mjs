import { createElement, Fragment, memo } from '@wordpress/element';

/**
 * App container; props other than the two below are ignored.
 *
 * @param {Object}               props
 * @param {boolean}              [props.isFullWidth=false] Add the full width class.
 * @param {import('react').Node} [props.children]          Container content.
 * @return {import('react').ReactElement} Container markup.
 */
export const ContainerWP = memo( function ContainerWPRender( {
	isFullWidth = false,
	children,
} ) {
	return createElement(
		Fragment,
		null,
		createElement(
			'div',
			{
				className:
					'ohmylms-app-container ' +
					( isFullWidth ? 'ohmylms-container-fullwidth' : '' ),
			},
			children
		)
	);
} );
