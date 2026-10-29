import { FormFileUpload } from '@wordpress/components';
import { createElement, memo } from '@wordpress/element';

/**
 * File picker button: a 40px `FormFileUpload` whose `label` is rendered as its
 * children. The remaining props (`accept`, `onChange( event )`, ...) are
 * forwarded.
 *
 * @param {Object}                    props         Component props.
 * @param {import('react').ReactNode} [props.label] Button content.
 * @return {import('react').ReactElement} File upload button.
 */
function FormFileUploadControl( { label, ...rest } ) {
	return createElement(
		FormFileUpload,
		{ __next40pxDefaultSize: true, ...rest },
		label
	);
}

/** Memoised file picker button; see `FormFileUploadControl`. */
export const FormFileUploadWP = memo( FormFileUploadControl );
