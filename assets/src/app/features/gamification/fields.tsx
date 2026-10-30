import {
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import type { ReactNode } from 'react';
import { toNumber } from './model';

interface FieldProps {
	label: string;
	help?: string;
	error?: string;
	disabled?: boolean;
}

// The message under a control: the error wins over the help text.
function message( { help, error }: FieldProps ): ReactNode {
	return error ?? help;
}

export function TextField( {
	value,
	onChange,
	maxLength,
	...field
}: FieldProps & {
	value: string;
	onChange: ( value: string ) => void;
	maxLength?: number;
} ) {
	return (
		<TextControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ field.label }
			value={ value }
			onChange={ onChange }
			help={ message( field ) }
			disabled={ field.disabled }
			maxLength={ maxLength }
			aria-invalid={ field.error ? true : undefined }
		/>
	);
}

// A whole-number input whose value is `null` while empty, so partial typing is never lost.
export function NumberField( {
	value,
	onChange,
	min,
	max,
	...field
}: FieldProps & {
	value: number | null;
	onChange: ( value: number | null ) => void;
	min: number;
	max: number;
} ) {
	return (
		<TextControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			type="number"
			label={ field.label }
			value={ null === value ? '' : String( value ) }
			onChange={ ( input: string ) => onChange( toNumber( input ) ) }
			help={ message( field ) }
			disabled={ field.disabled }
			min={ min }
			max={ max }
			step={ 1 }
			aria-invalid={ field.error ? true : undefined }
		/>
	);
}

export function ToggleField( {
	checked,
	onChange,
	...field
}: FieldProps & { checked: boolean; onChange: ( value: boolean ) => void } ) {
	return (
		<ToggleControl
			__nextHasNoMarginBottom
			label={ field.label }
			checked={ checked }
			onChange={ onChange }
			help={ message( field ) }
			disabled={ field.disabled }
		/>
	);
}

export function SelectField< T extends string >( {
	value,
	options,
	onChange,
	...field
}: FieldProps & {
	value: T;
	options: ReadonlyArray< { value: T; label: string } >;
	onChange: ( value: T ) => void;
} ) {
	return (
		<SelectControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ field.label }
			value={ value }
			options={ [ ...options ] }
			onChange={ ( next: string ) => {
				const match = options.find( ( item ) => item.value === next );
				if ( match ) {
					onChange( match.value );
				}
			} }
			help={ message( field ) }
			disabled={ field.disabled }
			aria-invalid={ field.error ? true : undefined }
		/>
	);
}

export function TextAreaField( {
	value,
	onChange,
	rows = 3,
	...field
}: FieldProps & {
	value: string;
	onChange: ( value: string ) => void;
	rows?: number;
} ) {
	return (
		<TextareaControl
			__nextHasNoMarginBottom
			label={ field.label }
			value={ value }
			rows={ rows }
			onChange={ onChange }
			help={ message( field ) }
			disabled={ field.disabled }
			aria-invalid={ field.error ? true : undefined }
		/>
	);
}

// A hex colour: a text input (the source of truth) plus a native picker kept in sync.
export function ColorField( {
	value,
	onChange,
	pickerLabel,
	...field
}: FieldProps & {
	value: string;
	onChange: ( value: string ) => void;
	pickerLabel: string;
} ) {
	const swatch = /^#[0-9a-fA-F]{6}$/.test( value )
		? value.toLowerCase()
		: '#000000';
	return (
		<div>
			<TextField
				{ ...field }
				value={ value }
				onChange={ onChange }
				maxLength={ 7 }
			/>
			<input
				type="color"
				aria-label={ pickerLabel }
				value={ swatch }
				disabled={ field.disabled }
				onChange={ ( event ) => onChange( event.target.value ) }
			/>
		</div>
	);
}
