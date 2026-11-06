import { cloneElement, useId } from '@wordpress/element';
import type { ReactElement } from 'react';

/**
 * A labelled control. The label’s `htmlFor` matches the control id.
 *
 * @param props          Props.
 * @param props.label    Visible label.
 * @param props.children One form control.
 */
export function Field( {
	label,
	children,
}: {
	label: string;
	children: ReactElement< { id?: string } >;
} ) {
	const id = useId();
	return (
		<div className="oml-dash-field">
			<label htmlFor={ id }>{ label }</label>
			{ cloneElement( children, { id } ) }
		</div>
	);
}

/**
 * A checkbox with its label.
 *
 * @param props          Props.
 * @param props.label    Visible label.
 * @param props.checked  Whether it is on.
 * @param props.onChange New value.
 */
export function Check( {
	label,
	checked,
	onChange,
}: {
	label: string;
	checked: boolean;
	onChange: ( checked: boolean ) => void;
} ) {
	const id = useId();
	return (
		<div className="oml-dash-field">
			<input
				id={ id }
				type="checkbox"
				checked={ checked }
				onChange={ ( event ) => onChange( event.target.checked ) }
			/>
			<label htmlFor={ id }>{ label }</label>
		</div>
	);
}
