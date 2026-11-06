import { createElement } from '@wordpress/element';
import { buttonClass } from './button.mjs';

/**
 * @typedef {Object} ButtonProps
 * @property {string}                    [variant]   primary, secondary (default), tertiary or danger.
 * @property {string}                    [size]      sm, md (default) or lg.
 * @property {boolean}                   [block]     Full width.
 * @property {string}                    [className] Additional classes.
 * @property {boolean}                   [disabled]  Disabled; links get aria-disabled.
 * @property {boolean}                   [loading]   Busy: blocks repeat clicks and keeps the label for assistive technology.
 * @property {string}                    [href]      Renders a link.
 * @property {import('react').ReactNode} [children]  Content.
 */

/**
 * Shared button. Renders an <a> when href is given, otherwise a <button> (type="button" unless set).
 * Styles are assets/css/oml-ui.css, loaded on plugin admin screens and by Ui::enqueue() on learner pages.
 *
 * @param {ButtonProps & Record<string, unknown>} props Other props pass to the element.
 */
export function Button( {
	variant,
	size,
	block,
	className,
	disabled,
	loading,
	href,
	children,
	...rest
} ) {
	const classes = buttonClass( {
		variant,
		size,
		block,
		className: [ className, loading ? 'is-loading' : '' ]
			.filter( Boolean )
			.join( ' ' ),
	} );
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
		{
			type: 'button',
			...rest,
			className: classes,
			disabled: disabled || loading,
			'aria-busy': loading ? 'true' : undefined,
		},
		children
	);
}
