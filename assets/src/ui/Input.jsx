import { createElement, forwardRef, useId } from '@wordpress/element';
import { describedBy, inputClass, inputType } from './controls.mjs';
import { Field } from './Field';

/**
 * Native text-like input with a label, help/error association and forwarded ref.
 *
 * @param {Object} props Native input props plus label, help, error, invalid, size and fieldClassName.
 */
export const Input = forwardRef( function InputControl(
	{
		id,
		label,
		help,
		error,
		invalid,
		size,
		fieldClassName,
		className,
		type = 'text',
		required,
		...rest
	},
	ref
) {
	const generatedId = useId();
	const controlId = id || `oml-input-${ generatedId }`;
	const isInvalid = Boolean(
		invalid ||
		error ||
		rest[ 'aria-invalid' ] === true ||
		rest[ 'aria-invalid' ] === 'true'
	);
	return createElement(
		Field,
		{ id: controlId, label, help, error, required, fieldClassName },
		createElement( 'input', {
			...rest,
			ref,
			id: controlId,
			type: inputType( type ),
			required,
			className: inputClass( { size, invalid: isInvalid, className } ),
			'aria-invalid': isInvalid ? 'true' : rest[ 'aria-invalid' ],
			'aria-describedby': describedBy(
				controlId,
				help,
				error,
				rest[ 'aria-describedby' ]
			),
		} )
	);
} );
