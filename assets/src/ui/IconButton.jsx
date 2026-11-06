import { createElement } from '@wordpress/element';
import { Button } from './Button';

/**
 * Button that shows only an icon or symbol. The label is required: it is the accessible name and the tooltip.
 *
 * @param {Object} props           Button props plus the required label.
 * @param {string} props.label     Accessible name and tooltip.
 * @param {string} props.className Extra classes.
 * @param          props.children  Icon or symbol.
 */
export function IconButton( { label, className, children, ...rest } ) {
	return createElement(
		Button,
		{
			size: 'sm',
			variant: 'tertiary',
			...rest,
			'aria-label': label,
			title: label,
			className: [ 'oml-btn-icon', className ]
				.filter( Boolean )
				.join( ' ' ),
		},
		children
	);
}
