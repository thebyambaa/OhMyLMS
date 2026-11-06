import { createElement, forwardRef, useId } from '@wordpress/element';
import { describedBy, textareaClass } from './controls.mjs';
import { Field } from './Field';

/**
 * Native textarea with a label, help/error association and forwarded ref.
 *
 * @param {Object} props Native textarea props plus label, help, error, invalid, size and fieldClassName.
 */
export const Textarea = forwardRef( function TextareaControl(
	{
		id,
		label,
		help,
		error,
		invalid,
		size,
		fieldClassName,
		className,
		required,
		rows = 4,
		...rest
	},
	ref
) {
	const generatedId = useId();
	const controlId = id || `oml-textarea-${ generatedId }`;
	const isInvalid = Boolean(
		invalid ||
		error ||
		rest[ 'aria-invalid' ] === true ||
		rest[ 'aria-invalid' ] === 'true'
	);
	return createElement(
		Field,
		{ id: controlId, label, help, error, required, fieldClassName },
		createElement( 'textarea', {
			...rest,
			ref,
			id: controlId,
			rows,
			required,
			className: textareaClass( { size, invalid: isInvalid, className } ),
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
