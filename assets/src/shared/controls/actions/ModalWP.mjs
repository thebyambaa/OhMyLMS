import { Modal } from '@wordpress/components';
import { createElement } from '@wordpress/element';
import { omitProps } from './model.mjs';

/**
 * Thin wrapper around the WordPress `Modal`. Every prop except `children` is
 * passed through.
 *
 * @param {Object}                    props          Modal props.
 * @param {import('react').ReactNode} props.children Content.
 * @return {import('react').ReactElement} Modal.
 */
export function ModalWP( props ) {
	return createElement(
		Modal,
		omitProps( props, [ 'children' ] ),
		props.children
	);
}
