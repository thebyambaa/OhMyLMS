import { createElement } from '@wordpress/element';
import { cardClass } from './components.mjs';

/**
 * Shared card: a bordered surface with the design-system radius and shadow.
 *
 * @param {Object}  props
 * @param {boolean} props.flat        No shadow.
 * @param {boolean} props.interactive Lifts on hover (for clickable cards).
 * @param {string}  props.as          Element name, div by default.
 * @param           props.className
 * @param           props.children
 */
export function Card( {
	as = 'div',
	flat,
	interactive,
	className,
	children,
	...rest
} ) {
	return createElement(
		as,
		{ ...rest, className: cardClass( { flat, interactive, className } ) },
		children
	);
}

export function CardTitle( { as = 'h3', className, children, ...rest } ) {
	return createElement(
		as,
		{
			...rest,
			className: [ 'oml-card-title', className ]
				.filter( Boolean )
				.join( ' ' ),
		},
		children
	);
}
