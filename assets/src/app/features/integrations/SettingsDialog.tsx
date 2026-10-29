import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	TextControl,
} from '@wordpress/components';
import { useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { saveIntegrationSettings } from './api';
import { useIntegrationSettings, useOperation } from './hooks';
import {
	buildChanges,
	createDraft,
	fieldErrorsFrom,
	validateDraft,
} from './model';
import type {
	FieldErrors,
	Integration,
	IntegrationSettings,
	SettingsField,
} from './types';

interface DialogProps {
	integration: Integration;
	onClose: () => void;
	onSaved: ( integration: Integration ) => void;
}

/**
 * Credential form for one integration. Stored secrets are never shown or sent back.
 *
 * @param props             Component props.
 * @param props.integration Integration being configured.
 * @param props.onClose     Called when the dialog is dismissed without saving.
 * @param props.onSaved     Called after the settings were saved.
 */
export function SettingsDialog( {
	integration,
	onClose,
	onSaved,
}: DialogProps ) {
	const loaded = useIntegrationSettings( integration.id );
	const [ saving, setSaving ] = useState( false );
	return (
		<Modal
			title={ sprintf(
				/* translators: %s: integration name. */
				__( '%s settings', 'ohmylms' ),
				integration.label
			) }
			onRequestClose={ () => {
				if ( ! saving ) {
					onClose();
				}
			} }
			shouldCloseOnClickOutside={ false }
		>
			{ loaded.loading && (
				<p role="status">{ __( 'Loading settings…', 'ohmylms' ) }</p>
			) }
			{ loaded.error && (
				<Notice status="error" isDismissible={ false }>
					{ loaded.error.message }{ ' ' }
					{ ! loaded.error.permission && (
						<Button variant="link" onClick={ loaded.reload }>
							{ __( 'Try again', 'ohmylms' ) }
						</Button>
					) }
				</Notice>
			) }
			{ loaded.settings && ! loaded.loading && ! loaded.error && (
				<SettingsForm
					integration={ integration }
					settings={ loaded.settings }
					onBusyChange={ setSaving }
					onCancel={ onClose }
					onSaved={ () => onSaved( integration ) }
				/>
			) }
		</Modal>
	);
}

interface FormProps {
	integration: Integration;
	settings: IntegrationSettings;
	onBusyChange: ( busy: boolean ) => void;
	onCancel: () => void;
	onSaved: () => void;
}

function SettingsForm( {
	integration,
	settings,
	onBusyChange,
	onCancel,
	onSaved,
}: FormProps ) {
	const [ draft, setDraft ] = useState( () => createDraft( settings ) );
	const [ clientErrors, setClientErrors ] = useState< FieldErrors >( {} );
	const form = useRef< HTMLFormElement >( null );
	const operation = useOperation();
	const serverErrors = operation.error
		? fieldErrorsFrom( operation.error )
		: {};
	const errors = { ...serverErrors, ...clientErrors };
	const hasFieldErrors = Object.keys( errors ).length > 0;

	function change( name: string, value: string ) {
		setDraft( ( current ) => ( {
			...current,
			values: { ...current.values, [ name ]: value },
		} ) );
		setClientErrors( ( current ) => {
			const rest = { ...current };
			delete rest[ name ];
			return rest;
		} );
	}
	function remove( name: string, checked: boolean ) {
		setDraft( ( current ) => ( {
			values: checked
				? { ...current.values, [ name ]: '' }
				: current.values,
			cleared: { ...current.cleared, [ name ]: checked },
		} ) );
	}
	function submit() {
		if ( operation.busy ) {
			return;
		}
		const problems = validateDraft( settings, draft );
		setClientErrors( problems );
		if ( Object.keys( problems ).length ) {
			// Move focus to the first invalid field once it has rendered its error.
			window.setTimeout( () => {
				form.current
					?.querySelector< HTMLElement >( '[aria-invalid="true"]' )
					?.focus();
			}, 0 );
			return;
		}
		const changes = buildChanges( settings, draft );
		if ( ! Object.keys( changes ).length ) {
			onCancel();
			return;
		}
		onBusyChange( true );
		void operation
			.run( async ( signal ) => {
				await saveIntegrationSettings(
					integration.id,
					changes,
					signal
				);
				if ( ! signal.aborted ) {
					onSaved();
				}
			} )
			.finally( () => onBusyChange( false ) );
	}

	function renderField( field: SettingsField ) {
		const message = errors[ field.name ];
		const inputId = `ohmylms-integration-${ integration.id }-${ field.name }`;
		if ( 'text' === field.kind ) {
			return (
				<TextControl
					key={ field.name }
					id={ inputId }
					label={ field.label }
					value={ draft.values[ field.name ] ?? '' }
					maxLength={ field.max_length }
					required={ field.required }
					aria-invalid={ message ? true : undefined }
					help={ message || field.help || undefined }
					onChange={ ( next ) => change( field.name, next ) }
					__nextHasNoMarginBottom
				/>
			);
		}
		const state = field.configured
			? sprintf(
					/* translators: %s: last characters of the stored secret. */
					__(
						'A value is stored (%s). Leave this blank to keep it.',
						'ohmylms'
					),
					field.hint
				)
			: __( 'No value is stored yet.', 'ohmylms' );
		const markedForRemoval = !! draft.cleared[ field.name ];
		return (
			<div key={ field.name }>
				<TextControl
					id={ inputId }
					label={ field.label }
					type="password"
					autoComplete="new-password"
					value={ draft.values[ field.name ] ?? '' }
					maxLength={ field.max_length }
					required={ field.required && ! field.configured }
					disabled={ markedForRemoval }
					aria-invalid={ message ? true : undefined }
					help={
						message ||
						[ field.help, state ].filter( Boolean ).join( ' ' )
					}
					onChange={ ( next ) => change( field.name, next ) }
					__nextHasNoMarginBottom
				/>
				{ field.configured && ! field.required && (
					<CheckboxControl
						label={ sprintf(
							/* translators: %s: field name. */
							__( 'Remove the stored %s', 'ohmylms' ),
							field.label.toLowerCase()
						) }
						checked={ markedForRemoval }
						onChange={ ( checked ) =>
							remove( field.name, checked )
						}
						__nextHasNoMarginBottom
					/>
				) }
			</div>
		);
	}

	return (
		<form
			ref={ form }
			noValidate
			onSubmit={ ( event ) => {
				event.preventDefault();
				submit();
			} }
		>
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error.message }
				</Notice>
			) }
			{ ! operation.error && hasFieldErrors && (
				<Notice status="error" isDismissible={ false }>
					{ __(
						'Fix the highlighted fields and try again.',
						'ohmylms'
					) }
				</Notice>
			) }
			<fieldset disabled={ operation.busy }>
				<legend>{ __( 'Credentials', 'ohmylms' ) }</legend>
				{ settings.fields.map( renderField ) }
			</fieldset>
			{ settings.readonly.length > 0 && (
				<fieldset>
					<legend>
						{ __( 'Copy into the provider’s console', 'ohmylms' ) }
					</legend>
					{ settings.readonly.map( ( item ) => (
						<TextControl
							key={ item.name }
							label={ item.label }
							value={ item.value }
							help={ item.help }
							readOnly
							onChange={ () => undefined }
							__nextHasNoMarginBottom
						/>
					) ) }
				</fieldset>
			) }
			<p>
				<Button
					type="submit"
					variant="primary"
					disabled={ operation.busy }
					isBusy={ operation.busy }
				>
					{ __( 'Save settings', 'ohmylms' ) }
				</Button>{ ' ' }
				<Button
					variant="tertiary"
					disabled={ operation.busy }
					onClick={ onCancel }
				>
					{ __( 'Cancel', 'ohmylms' ) }
				</Button>
			</p>
		</form>
	);
}
