import { cloneElement, useId } from '@wordpress/element';
import type { ReactElement, ReactNode } from 'react';
import { Button } from '../../ui/Button';

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
/**
 * A section that stays closed until the user asks for it.
 *
 * @param props          Props.
 * @param props.title    Button label.
 * @param props.open     Whether the body is shown.
 * @param props.onToggle Toggle handler.
 * @param props.children Hidden until open.
 */
export function Disclosure( {
	title,
	open,
	onToggle,
	children,
}: {
	title: string;
	open: boolean;
	onToggle: () => void;
	children: ReactNode;
} ) {
	return (
		<div className="oml-disclose">
			<Button
				variant="tertiary"
				size="sm"
				aria-expanded={ open }
				onClick={ onToggle }
			>
				{ title }
			</Button>
			{ open ? (
				<div className="oml-disclose-body">{ children }</div>
			) : null }
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
