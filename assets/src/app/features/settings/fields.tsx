import {
	CheckboxControl,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import type { ReactNode } from 'react';
import { toSwatch } from './model';
import type { SettingValue } from './types';

// Read a draft value as text (an unexpected kind shows as empty).
export function asText( value: SettingValue | undefined ): string {
	return 'string' === typeof value ? value : '';
}

// Read a draft value as a number; `NaN` marks text that is not a number yet.
export function asNumber( value: SettingValue | undefined ): number {
	return 'number' === typeof value ? value : Number.NaN;
}

export function asFlag( value: SettingValue | undefined ): boolean {
	return true === value;
}

// Show a number in an input: `NaN` (invalid text) becomes empty.
export function numberToInput( value: number ): string {
	return Number.isFinite( value ) ? String( value ) : '';
}

// Parse an input into a number: empty or invalid text becomes `NaN` so validation can flag it.
export function inputToNumber( input: string ): number {
	return '' === input.trim() ? Number.NaN : Number( input );
}

interface FieldProps {
	label: string;
	help?: string;
	error?: string;
	disabled?: boolean;
}

// The message shown under a control: the error wins over the help text.
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

export function NumberField( {
	value,
	onChange,
	min,
	max,
	step,
	...field
}: FieldProps & {
	value: number;
	onChange: ( value: number ) => void;
	min: number;
	max: number;
	step: number;
} ) {
	return (
		<TextControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			type="number"
			label={ field.label }
			value={ numberToInput( value ) }
			onChange={ ( input: string ) => onChange( inputToNumber( input ) ) }
			help={ message( field ) }
			disabled={ field.disabled }
			min={ min }
			max={ max }
			step={ step }
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

export function SelectField( {
	value,
	options,
	onChange,
	...field
}: FieldProps & {
	value: string;
	options: ReadonlyArray< { value: string; label: string } >;
	onChange: ( value: string ) => void;
} ) {
	return (
		<SelectControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ field.label }
			value={ value }
			options={ [ ...options ] }
			onChange={ onChange }
			help={ message( field ) }
			disabled={ field.disabled }
			aria-invalid={ field.error ? true : undefined }
		/>
	);
}

export function TextAreaField( {
	value,
	onChange,
	rows = 4,
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

// A write-only credential: the stored value is never shown; empty keeps it, the checkbox removes it.
export function SecretField( {
	value,
	onChange,
	hasValue,
	hint,
	cleared,
	onClear,
	...field
}: FieldProps & {
	value: string;
	onChange: ( value: string ) => void;
	hasValue: boolean;
	hint?: string;
	cleared: boolean;
	onClear: ( cleared: boolean ) => void;
} ) {
	let status: string = __( 'Not set.', 'ohmylms' );
	if ( cleared ) {
		status = __(
			'The saved value will be removed when you save.',
			'ohmylms'
		);
	} else if ( hasValue ) {
		status = hint
			? sprintf(
					/* translators: %s: last characters of the saved value. */
					__( 'Saved (%s). Leave empty to keep it.', 'ohmylms' ),
					hint
				)
			: __( 'Saved. Leave empty to keep it.', 'ohmylms' );
	}
	return (
		<div>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				type="password"
				autoComplete="new-password"
				spellCheck={ false }
				label={ field.label }
				value={ cleared ? '' : value }
				onChange={ onChange }
				help={
					field.error ??
					( field.help ? `${ field.help } ${ status }` : status )
				}
				disabled={ field.disabled || cleared }
				aria-invalid={ field.error ? true : undefined }
			/>
			{ hasValue && ! field.disabled && (
				<CheckboxControl
					__nextHasNoMarginBottom
					label={ sprintf(
						/* translators: %s: field label. */
						__( 'Remove the saved %s', 'ohmylms' ),
						field.label
					) }
					checked={ cleared }
					onChange={ onClear }
				/>
			) }
		</div>
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
				value={ toSwatch( value ) }
				disabled={ field.disabled }
				onChange={ ( event ) => onChange( event.target.value ) }
			/>
		</div>
	);
}
