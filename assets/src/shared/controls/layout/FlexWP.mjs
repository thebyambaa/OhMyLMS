import { createElement, forwardRef } from '@wordpress/element';
import { Flex } from '@wordpress/components';

/**
 * Flex container; every prop except children goes to `Flex`.
 *
 * @param {Object}               props
 * @param {import('react').Node} [props.children] Flex children.
 * @param {import('react').Ref}  ref              Forwarded to `Flex`.
 * @return {import('react').ReactElement} Flex element.
 */
export const FlexWP = forwardRef( function FlexWPRender(
	{ children, ...rest },
	ref
) {
	return createElement( Flex, { ...rest, ref }, children );
} );
