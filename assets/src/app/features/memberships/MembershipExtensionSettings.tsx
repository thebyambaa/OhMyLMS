import type { ComponentType } from 'react';
import { ExtensionBoundary } from '../../extensions/ExtensionBoundary';
import { useExtensions } from '../../extensions/context';
import type { ExtensionEntry } from '../../extensions/registry.mjs';
import { extensionErrorPath } from './model';
import type { ExtensionSettings, FormErrors, MembershipStatus } from './types';

/** Props a registered `membership-settings` entry receives (docs/EXTENSIONS.md, `registerMembershipSettings`). */
export interface MembershipSettingsProps {
	/** The plan being edited, as last saved. */
	plan: { id: number; name: string; status: MembershipStatus };
	/** This extension's values for the plan (its namespace); empty before anything is saved. */
	value: Readonly< Record< string, unknown > >;
	/** Replace this extension's values with a plain object; "Save plan" stores them with the plan. */
	onChange: ( next: Record< string, unknown > ) => void;
	/** True while the plan is saving. */
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

function SettingsView( {
	entry,
	props,
}: {
	entry: ExtensionEntry;
	props: MembershipSettingsProps;
} ) {
	if ( entry.when && ! entry.when( { ...props } ) ) {
		return null;
	}
	const Render = entry.render as ComponentType< MembershipSettingsProps >;
	return <Render { ...props } />;
}

/**
 * The `membership-settings` extensions the server accepts settings for, each in its own fieldset and
 * error boundary. Values live in the editor form, so they count as unsaved changes and are saved with
 * the plan. Re-renders when extensions register later.
 *
 * @param props            Component props.
 * @param props.plan       Plan passed to every extension.
 * @param props.namespaces Extension IDs the server registered for memberships.
 * @param props.values     Current values by extension ID.
 * @param props.errors     Form errors; server rejections use {@link extensionErrorPath}.
 * @param props.disabled   Disable the panels while saving.
 * @param props.onChange   Receives an extension ID and its new values.
 */
export function MembershipExtensionSettings( {
	plan,
	namespaces,
	values,
	errors,
	disabled,
	onChange,
}: {
	plan: MembershipSettingsProps[ 'plan' ];
	namespaces: readonly string[];
	values: ExtensionSettings;
	errors: FormErrors;
	disabled: boolean;
	onChange: ( id: string, next: Record< string, unknown > ) => void;
} ) {
	const entries = useExtensions( 'membership-settings' ).filter( ( entry ) =>
		namespaces.includes( entry.id )
	);
	return (
		<>
			{ entries.map( ( entry ) => {
				const error = errors[ extensionErrorPath( entry.id ) ];
				const errorId = `oml-mem-ext-${ entry.id }-error`;
				return (
					<fieldset
						key={ entry.id }
						disabled={ disabled }
						aria-describedby={ error ? errorId : undefined }
					>
						<legend>{ entry.label }</legend>
						<ExtensionBoundary id={ entry.id }>
							<SettingsView
								entry={ entry }
								props={ {
									plan,
									value: values[ entry.id ] ?? NONE,
									disabled,
									onChange: ( next ) => {
										if ( isPlainObject( next ) ) {
											onChange( entry.id, next );
										}
									},
								} }
							/>
						</ExtensionBoundary>
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
