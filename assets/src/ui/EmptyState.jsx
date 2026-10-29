import { createElement } from '@wordpress/element';

/**
 * Empty-content guidance. Updates should use a surrounding status region when needed.
 *
 * @param {Object} props             Component properties.
 * @param {string} props.title       Visible title.
 * @param {string} props.description Supporting text.
 * @param {Object} props.children    Optional action elements.
 * @param {string} props.className   Additional wrapper classes.
 */
export function EmptyState( {
	title,
	description,
	children,
	className = '',
	...rest
} ) {
	return createElement(
		'div',
		{ ...rest, className: `oml-empty-state ${ className }` },
		createElement( 'p', { className: 'oml-empty-state-title' }, title ),
		description &&
			createElement(
				'p',
				{ className: 'oml-empty-state-description' },
				description
			),
		children &&
			createElement(
				'div',
				{ className: 'oml-empty-state-actions' },
				children
			)
	);
}
