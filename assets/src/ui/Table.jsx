import { createElement } from '@wordpress/element';

/**
 * Native data table in a labelled, keyboard-scrollable region. Headers/cells stay native HTML.
 *
 * @param {Object}  props           Native table properties.
 * @param {string}  props.caption   Visible caption and scroll-region label.
 * @param {boolean} props.striped   Alternate row surfaces.
 * @param {boolean} props.compact   Smaller cell padding.
 * @param {string}  props.className Additional table classes.
 * @param {Object}  props.children  Native thead/tbody/tfoot children.
 */
export function Table( {
	caption,
	striped = false,
	compact = false,
	className = '',
	children,
	...rest
} ) {
	return createElement(
		'div',
		{
			className: 'oml-table-scroll',
			role: 'region',
			'aria-label': caption,
			tabIndex: 0,
		},
		createElement(
			'table',
			{
				...rest,
				className: [
					'oml-table',
					striped && 'oml-table-striped',
					compact && 'oml-table-compact',
					className,
				]
					.filter( Boolean )
					.join( ' ' ),
			},
			createElement( 'caption', null, caption ),
			children
		)
	);
}
