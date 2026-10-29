import { createElement } from '@wordpress/element';

/**
 * Shared label and descriptions for Input/Select; all content is plain React text.
 *
 * @param {Object}                    props                Field presentation, the control ID and control children.
 * @param {string}                    props.id             Control ID.
 * @param {string}                    props.label          Visible label.
 * @param {string}                    props.help           Supporting description.
 * @param {string}                    props.error          Validation message.
 * @param {boolean}                   props.required       Native required state.
 * @param {string}                    props.fieldClassName Extra wrapper class names.
 * @param {import('react').ReactNode} props.children       Native control.
 */
export function Field( {
	id,
	label,
	help,
	error,
	required,
	fieldClassName,
	children,
} ) {
	return createElement(
		'div',
		{
			className: [ 'oml-field', fieldClassName ]
				.filter( Boolean )
				.join( ' ' ),
		},
		label
			? createElement(
					'label',
					{ className: 'oml-field-label', htmlFor: id },
					label,
					required
						? createElement(
								'span',
								{
									className: 'oml-field-required',
									'aria-hidden': true,
								},
								' *'
							)
						: null
				)
			: null,
		children,
		help
			? createElement(
					'p',
					{ className: 'oml-field-help', id: `${ id }-help` },
					help
				)
			: null,
		error
			? createElement(
					'p',
					{
						className: 'oml-field-error',
						id: `${ id }-error`,
						role: 'alert',
					},
					error
				)
			: null
	);
}
