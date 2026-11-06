import { createElement, forwardRef, useId } from '@wordpress/element';

/**
 * Native checkbox with its label and an optional hint. Click targets include the label text.
 *
 * @param {Object} props           Native input props plus label, help and className.
 * @param          props.id        Control id.
 * @param          props.label     Visible label.
 * @param          props.help      Supporting description.
 * @param          props.className Extra classes on the wrapper.
 */
export const Checkbox = forwardRef( function CheckboxControl(
	{ id, label, help, className, ...rest },
	ref
) {
	const generatedId = useId();
	const controlId = id || `oml-checkbox-${ generatedId }`;
	return createElement(
		'div',
		{ className: [ 'oml-check', className ].filter( Boolean ).join( ' ' ) },
		createElement( 'input', {
			...rest,
			ref,
			id: controlId,
			type: 'checkbox',
			className: 'oml-checkbox',
			'aria-describedby': help ? `${ controlId }-help` : undefined,
		} ),
		createElement(
			'label',
			{ className: 'oml-check-label', htmlFor: controlId },
			label
		),
		help
			? createElement(
					'p',
					{ className: 'oml-field-help', id: `${ controlId }-help` },
					help
				)
			: null
	);
} );
