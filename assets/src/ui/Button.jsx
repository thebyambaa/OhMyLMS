import { createElement } from '@wordpress/element';
import { buttonClass } from './button.mjs';

/**
 * Shared button. Renders an <a> when href is given, otherwise a <button> (type="button" unless set).
 * Styles are assets/css/oml-ui.css, loaded on plugin admin screens and by Ui::enqueue() on learner pages.
 *
 * @param {Object}  props
 * @param {string}  props.variant   primary, secondary (default), tertiary or danger.
 * @param {string}  props.size      sm, md (default) or lg.
 * @param {boolean} props.block     Full width.
 * @param {boolean} props.disabled  Disabled; links get aria-disabled.
 */
export function Button( {
	variant,
	size,
	block,
	className,
	disabled,
	href,
	children,
	...rest
} ) {
	const classes = buttonClass( { variant, size, block, className } );
	if ( href ) {
		return createElement(
			'a',
			{
				...rest,
				href: disabled ? undefined : href,
				className: classes,
				'aria-disabled': disabled ? 'true' : undefined,
			},
			children
		);
	}
	return createElement(
		'button',
		{ type: 'button', ...rest, className: classes, disabled },
		children
	);
}
