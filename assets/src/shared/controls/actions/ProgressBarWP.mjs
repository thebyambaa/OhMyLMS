import { ProgressBar } from '@wordpress/components';
import { createElement, Fragment, memo } from '@wordpress/element';

/**
 * Thin wrapper around the WordPress `ProgressBar`. All props, such as `value`
 * and `className`, are passed through.
 *
 * @param {Object} props ProgressBar props.
 * @return {import('react').ReactElement} Progress bar.
 */
function ProgressBarWPBase( props ) {
	return createElement( Fragment, null, createElement( ProgressBar, props ) );
}

export const ProgressBarWP = memo( ProgressBarWPBase );
