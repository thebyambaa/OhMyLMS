import { createElement, forwardRef, useId } from '@wordpress/element';
import { describedBy, selectClass } from './controls.mjs';
import { Field } from './Field';

/**
 * Native select with options [{value,label,disabled}], or native option/optgroup children.
 *
 * @param {Object} props Native select props plus the same field presentation props as Input.
 */
export const Select = forwardRef( function SelectControl(
	{
		id,
		label,
		help,
		error,
		invalid,
		size,
		fieldClassName,
		className,
		options = [],
		placeholder,
		children,
		required,
		...rest
	},
	ref
) {
	const generatedId = useId();
	const controlId = id || `oml-select-${ generatedId }`;
	const isInvalid = Boolean(
		invalid ||
		error ||
		rest[ 'aria-invalid' ] === true ||
		rest[ 'aria-invalid' ] === 'true'
	);
	return createElement(
		Field,
		{ id: controlId, label, help, error, required, fieldClassName },
		createElement(
			'select',
			{
				...rest,
				ref,
				id: controlId,
				required,
				className: selectClass( {
					size,
					invalid: isInvalid,
					className,
				} ),
				'aria-invalid': isInvalid ? 'true' : rest[ 'aria-invalid' ],
				'aria-describedby': describedBy(
					controlId,
					help,
					error,
					rest[ 'aria-describedby' ]
				),
			},
			placeholder && ! rest.multiple
				? createElement( 'option', { value: '' }, placeholder )
				: null,
			children ??
				options.map( ( option, index ) =>
					createElement(
						'option',
						{
							key: index,
							value: option.value,
							disabled: option.disabled,
						},
						option.label
					)
				)
		)
	);
} );
