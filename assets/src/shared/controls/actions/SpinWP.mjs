import { Spinner } from '@wordpress/components';
import { createElement } from '@wordpress/element';

/**
 * Thin wrapper around the WordPress `Spinner`. All props are passed through.
 *
 * @param {Object} props Spinner props.
 * @return {import('react').ReactElement} Spinner.
 */
export function SpinWP( props ) {
	return createElement( Spinner, { ...props } );
}
