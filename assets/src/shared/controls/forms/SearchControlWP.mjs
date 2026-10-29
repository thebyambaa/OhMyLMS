import { SearchControl } from '@wordpress/components';
import { createElement } from '@wordpress/element';

/**
 * Search field: a `SearchControl` without bottom margin. Every prop (`value`,
 * `onChange( value )`, `placeholder`, ...) is forwarded and can override the
 * default.
 *
 * @param {Object} props Props forwarded to `SearchControl`.
 * @return {import('react').ReactElement} Search control.
 */
export function SearchControlWP( props ) {
	return createElement( SearchControl, {
		__nextHasNoMarginBottom: true,
		...props,
	} );
}
