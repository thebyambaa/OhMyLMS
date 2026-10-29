import { Notice } from '@wordpress/components';
import { createElement } from '@wordpress/element';
import { omitProps } from './model.mjs';

/**
 * Thin wrapper around the WordPress `Notice`. Every prop except `children` is
 * passed through.
 *
 * @param {Object}                    props          Notice props.
 * @param {import('react').ReactNode} props.children Message.
 * @return {import('react').ReactElement} Notice.
 */
export function NoticeWP( props ) {
	return createElement(
		Notice,
		omitProps( props, [ 'children' ] ),
		props.children
	);
}
