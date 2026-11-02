import { __, sprintf } from '@wordpress/i18n';
import type { ComponentType } from 'react';
import { ExtensionBoundary } from '../../extensions/ExtensionBoundary';
import { useExtensions } from '../../extensions/context';
import type { ExtensionEntry } from '../../extensions/registry.mjs';
import {
	settingsFields,
	type SettingsDefinition,
} from '../../extensions/settings';
import { SettingsFields } from '../../extensions/SettingsFields';
import { extensionErrorPath } from './model';
import type { ExtensionValues, FormErrors } from './types';

/** Props a registered `question-settings` entry receives (docs/EXTENSIONS.md, `registerQuestionSettings`). */
export interface QuestionSettingsProps {
	/** The question being edited (id 0 for a draft that is not saved yet). */
	question: { id: number; type: string; name: string };
	/** This extension's values for the question (its namespace); empty before anything is saved. */
	value: Readonly< Record< string, unknown > >;
	/** Replace this extension's values with a plain object; saving the question stores them. */
	onChange: ( next: Record< string, unknown > ) => void;
	/** True while the question is saving or cannot be edited. */
	disabled: boolean;
}

const NONE: Readonly< Record< string, unknown > > = Object.freeze( {} );

function isPlainObject( value: unknown ): value is Record< string, unknown > {
	return (
		!! value &&
		'object' === typeof value &&
		Object.getPrototypeOf( value ) === Object.prototype
	);
}

function Panel( {
	entry,
	props,
}: {
	entry: ExtensionEntry;
	props: QuestionSettingsProps;
} ) {
	const Render = entry.render as ComponentType< QuestionSettingsProps >;
	return <Render { ...props } />;
}

function fieldErrors(
	id: string,
	errors: FormErrors
): Record< string, string > {
	const prefix = `${ extensionErrorPath( id ) }.`;
	return Object.fromEntries(
		Object.entries( errors )
			.filter( ( [ key ] ) => key.startsWith( prefix ) )
			.map( ( [ key, message ] ) => [
				key.slice( prefix.length ),
				message,
			] )
	);
}

/**
 * The extension settings registered for questions on the server, each in its own fieldset: the
 * extension's `question-settings` panel when it registered one, otherwise generic fields built from
 * the settings schema. Values live in the editor form, count as unsaved changes and are saved with
 * the question (`extension_settings`), where the server validates them against the schema.
 *
 * @param props             Component props.
 * @param props.definitions Settings registered for the `question` context.
 * @param props.question    Question passed to every panel.
 * @param props.values      Current values by settings ID.
 * @param props.errors      Form errors (`extension.<id>` and `extension.<id>.<property>`).
 * @param props.disabled    Disable the fields.
 * @param props.onChange    Receives a settings ID and its new values.
 */
export function QuestionExtensionSettings( {
	definitions,
	question,
	values,
	errors,
	disabled,
	onChange,
}: {
	definitions: readonly SettingsDefinition[];
	question: QuestionSettingsProps[ 'question' ];
	values: ExtensionValues;
	errors: FormErrors;
	disabled: boolean;
	onChange: ( id: string, next: Record< string, unknown > ) => void;
} ) {
	const panels = useExtensions( 'question-settings' );
	return (
		<>
			{ definitions.map( ( definition ) => {
				const value = values[ definition.id ] ?? NONE;
				const props: QuestionSettingsProps = {
					question,
					value,
					disabled,
					onChange: ( next ) => {
						if ( isPlainObject( next ) ) {
							onChange( definition.id, next );
						}
					},
				};
				const entry = panels.find(
					( item ) => item.id === definition.id
				);
				if ( entry?.when && ! entry.when( { ...props } ) ) {
					return null;
				}
				const { fields, unsupported } = settingsFields(
					definition.schema
				);
				const error = errors[ extensionErrorPath( definition.id ) ];
				const errorId = `oml-question-ext-${ definition.id }-error`;
				return (
					<fieldset
						key={ definition.id }
						disabled={ disabled }
						aria-describedby={ error ? errorId : undefined }
					>
						<legend>{ entry?.label ?? definition.label }</legend>
						{ entry ? (
							<ExtensionBoundary id={ entry.id }>
								<Panel entry={ entry } props={ props } />
							</ExtensionBoundary>
						) : (
							<>
								<SettingsFields
									id={ definition.id }
									fields={ fields }
									value={ value }
									errors={ fieldErrors(
										definition.id,
										errors
									) }
									disabled={ disabled }
									onChange={ props.onChange }
								/>
								{ unsupported.length > 0 && (
									<p>
										{ sprintf(
											/* translators: %s: setting names, comma separated. */
											__(
												'These settings can only be changed by the add-on that registered them and are kept as they are: %s.',
												'ohmylms'
											),
											unsupported.join( ', ' )
										) }
									</p>
								) }
							</>
						) }
						{ error && (
							<p id={ errorId } role="alert">
								{ error }
							</p>
						) }
					</fieldset>
				);
			} ) }
		</>
	);
}
