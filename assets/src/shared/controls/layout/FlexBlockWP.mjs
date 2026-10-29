import { createElement } from '@wordpress/element';
import { FlexBlock } from '@wordpress/components';

/**
 * Flex block; every prop except children goes to `FlexBlock`.
 *
 * @param {Object}               props
 * @param {import('react').Node} [props.children] Block content.
 * @return {import('react').ReactElement} FlexBlock element.
 */
export function FlexBlockWP( { children, ...rest } ) {
	return createElement( FlexBlock, rest, children );
}
