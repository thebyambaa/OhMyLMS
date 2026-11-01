import {
	CheckboxControl,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fieldValue, type SettingsField } from './settings';

function shown( value: unknown ): string {
	return 'string' === typeof value || 'number' === typeof value
		? String( value )
		: '';
}

/**
 * Generic fields for the simple properties of extension settings (see `settingsFields`), for
 * settings whose extension registers no panel of its own.
 *
 * @param props          Component props.
 * @param props.id       Settings ID, used to build element ids.
 * @param props.fields   Editable fields.
 * @param props.value    Values of the namespace.
 * @param props.errors   Messages by property name.
 * @param props.disabled Whether editing is off.
 * @param props.onChange Receives the whole new namespace object.
 */
export function SettingsFields( {
	id,
	fields,
	value,
	errors,
	disabled,
	onChange,
}: {
	id: string;
	fields: readonly SettingsField[];
	value: Readonly< Record< string, unknown > >;
	errors: Readonly< Record< string, string > >;
	disabled: boolean;
	onChange: ( next: Record< string, unknown > ) => void;
} ) {
	const set = ( field: SettingsField, input: string | boolean ) => {
		const next = { ...value };
		const stored = fieldValue( field, input );
		if ( undefined === stored ) {
			delete next[ field.name ];
		} else {
			next[ field.name ] = stored;
		}
		onChange( next );
	};
	return (
		<>
			{ fields.map( ( field ) => {
				const error = errors[ field.name ];
				const errorId = `oml-ext-${ id }-${ field.name }-error`;
				const shared = {
					label: field.label,
					help: field.help || undefined,
					disabled,
					'aria-invalid': error ? true : undefined,
					'aria-describedby': error ? errorId : undefined,
				};
				let control;
				switch ( field.kind ) {
					case 'boolean':
						control = (
							<CheckboxControl
								label={ field.label }
								help={ field.help || undefined }
								disabled={ disabled }
								checked={ true === value[ field.name ] }
								onChange={ ( checked ) =>
									set( field, checked )
								}
							/>
						);
						break;
					case 'choice':
						control = (
							<SelectControl
								{ ...shared }
								value={ shown( value[ field.name ] ) }
								options={ [
									{
										value: '',
										label: __( 'Not set', 'ohmylms' ),
										disabled: field.required,
									},
									...field.choices,
								] }
								onChange={ ( next ) => set( field, next ) }
							/>
						);
						break;
					case 'textarea':
						control = (
							<TextareaControl
								{ ...shared }
								value={ shown( value[ field.name ] ) }
								rows={ 3 }
								maxLength={ field.maxLength }
								onChange={ ( next ) => set( field, next ) }
							/>
						);
						break;
					default:
						control = (
							<TextControl
								{ ...shared }
								type={
									'number' === field.kind ||
									'integer' === field.kind
										? 'number'
										: field.kind
								}
								min={ field.minimum }
								max={ field.maximum }
								step={ 'integer' === field.kind ? 1 : 'any' }
								maxLength={ field.maxLength }
								required={ field.required }
								value={ shown( value[ field.name ] ) }
								onChange={ ( next ) => set( field, next ) }
							/>
						);
				}
				return (
					<div key={ field.name }>
						{ control }
						{ error && (
							<p id={ errorId } role="alert">
								{ error }
							</p>
						) }
					</div>
				);
			} ) }
		</>
	);
}
