import { createElement } from '@wordpress/element';
// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- the recovered application renders this experimental component; markup parity is tested.
import { __experimentalText as Text } from '@wordpress/components';

/**
 * Text; with `html` the children are rendered as raw HTML.
 *
 * Only pass trusted or already sanitised markup together with `html`.
 *
 * @param {Object}               props
 * @param {import('react').Node} [props.children] Content, or the HTML string.
 * @param {boolean}              [props.html]     Treat children as HTML.
 * @return {import('react').ReactElement} Text element.
 */
export function TextWP( { children, html, ...rest } ) {
	if ( html ) {
		return createElement( Text, {
			...rest,
			dangerouslySetInnerHTML: { __html: children },
		} );
	}
	return createElement( Text, rest, children );
}
