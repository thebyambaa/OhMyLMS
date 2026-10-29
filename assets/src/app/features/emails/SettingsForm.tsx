import { useRef, useState } from '@wordpress/element';
import {
	Button,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import { saveEmailSettings } from './api';
import { useEmailSettings } from './hooks';
import {
	BUTTON_POSITIONS,
	serverFieldError,
	settingsPatch,
	validateSettings,
	type FieldErrors,
} from './model';
import type { ButtonPosition, EmailSettings, SettingsField } from './types';

const FIELDS: readonly SettingsField[] = [
	'branding_image',
	'base_color',
	'background_color',
	'body_background_color',
	'body_text_color',
	'button_position',
	'sender_name',
	'sender_email',
	'footer_text',
];

const POSITION_OPTIONS: ReadonlyArray< {
	value: ButtonPosition;
	label: string;
} > = [
	{ value: 'left', label: __( 'Left', 'ohmylms' ) },
	{ value: 'center', label: __( 'Center', 'ohmylms' ) },
	{ value: 'right', label: __( 'Right', 'ohmylms' ) },
];

/** Sender, branding and colors shared by every email. */
export function SettingsForm() {
	const state = useEmailSettings();
	return (
		<section aria-labelledby="oml-email-settings-title">
			<h3 id="oml-email-settings-title">
				{ __( 'Sender and branding', 'ohmylms' ) }
			</h3>
			{ state.loading && (
				<p role="status">{ __( 'Loading settings…', 'ohmylms' ) }</p>
			) }
			{ state.error && (
				<Notice status="error" isDismissible={ false }>
					{ state.error }{ ' ' }
					<Button variant="link" onClick={ state.reload }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ state.settings && (
				<Form saved={ state.settings } onSaved={ state.setSettings } />
			) }
		</section>
	);
}

function Form( {
	saved,
	onSaved,
}: {
	saved: EmailSettings;
	onSaved: ( settings: EmailSettings ) => void;
} ) {
	const [ values, setValues ] = useState< EmailSettings >( saved );
	const [ serverErrors, setServerErrors ] = useState<
		FieldErrors< SettingsField >
	>( {} );
	const [ submitted, setSubmitted ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState< {
		status: 'success' | 'error';
		text: string;
	} | null >( null );
	const formRef = useRef< HTMLFormElement >( null );

	const patch = settingsPatch( values, saved );
	const dirty = Object.keys( patch ).length > 0;
	const errors = { ...validateSettings( values ), ...serverErrors };
	const shown = ( field: SettingsField ) =>
		submitted || serverErrors[ field ] ? errors[ field ] : undefined;

	const update = < K extends SettingsField >(
		field: K,
		value: EmailSettings[ K ]
	) => {
		setValues( ( current ) => ( { ...current, [ field ]: value } ) );
		setServerErrors( ( current ) => {
			const next = { ...current };
			delete next[ field ];
			return next;
		} );
		setMessage( null );
	};

	const focusInvalid = () =>
		window.requestAnimationFrame( () =>
			formRef.current
				?.querySelector< HTMLElement >( '[aria-invalid="true"]' )
				?.focus()
		);

	async function save() {
		setSubmitted( true );
		setMessage( null );
		if ( Object.keys( errors ).length ) {
			setMessage( {
				status: 'error',
				text: __( 'Fix the highlighted fields.', 'ohmylms' ),
			} );
			focusInvalid();
			return;
		}
		setBusy( true );
		try {
			const result = await saveEmailSettings( patch );
			onSaved( result );
			setValues( result );
			setSubmitted( false );
			setServerErrors( {} );
			setMessage( {
				status: 'success',
				text: __( 'Settings saved.', 'ohmylms' ),
			} );
		} catch ( failure ) {
			const error = normalizeApiError( failure );
			const field = serverFieldError( error, FIELDS );
			if ( field ) {
				setServerErrors( ( current ) => ( {
					...current,
					[ field.field ]: field.message,
				} ) );
				focusInvalid();
			}
			setMessage( {
				status: 'error',
				text: field
					? __( 'Fix the highlighted fields.', 'ohmylms' )
					: error.message,
			} );
		} finally {
			setBusy( false );
		}
	}

	const text = (
		field: Exclude< SettingsField, 'button_position' | 'footer_text' >,
		label: string,
		help?: string
	) => (
		<TextControl
			__nextHasNoMarginBottom
			label={ label }
			value={ values[ field ] }
			onChange={ ( value ) => update( field, value ) }
			help={ shown( field ) ?? help }
			aria-invalid={ shown( field ) ? 'true' : undefined }
		/>
	);

	return (
		<form
			ref={ formRef }
			noValidate
			aria-label={ __( 'Sender and branding', 'ohmylms' ) }
			onSubmit={ ( event ) => {
				event.preventDefault();
				if ( ! busy ) {
					void save();
				}
			} }
		>
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
				</Notice>
			) }
			<fieldset disabled={ busy }>
				<legend>{ __( 'Sender', 'ohmylms' ) }</legend>
				{ text(
					'sender_name',
					__( 'Sender name', 'ohmylms' ),
					__(
						'Shown as the “From” name. Used only when the sender email is also set.',
						'ohmylms'
					)
				) }
				{ text( 'sender_email', __( 'Sender email', 'ohmylms' ) ) }
			</fieldset>
			<fieldset disabled={ busy }>
				<legend>{ __( 'Branding', 'ohmylms' ) }</legend>
				{ text(
					'branding_image',
					__( 'Logo address', 'ohmylms' ),
					__(
						'Full http or https address of an image. Leave empty for no logo.',
						'ohmylms'
					)
				) }
				{ text( 'base_color', __( 'Accent color', 'ohmylms' ) ) }
				{ text(
					'background_color',
					__( 'Page background color', 'ohmylms' )
				) }
				{ text(
					'body_background_color',
					__( 'Message background color', 'ohmylms' )
				) }
				{ text( 'body_text_color', __( 'Text color', 'ohmylms' ) ) }
				<SelectControl
					__nextHasNoMarginBottom
					label={ __( 'Button position', 'ohmylms' ) }
					value={ values.button_position }
					options={ POSITION_OPTIONS }
					onChange={ ( value ) =>
						update(
							'button_position',
							BUTTON_POSITIONS.find(
								( position ) => position === value
							) ?? ( 'left' as ButtonPosition )
						)
					}
				/>
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'Footer text (HTML)', 'ohmylms' ) }
					value={ values.footer_text }
					rows={ 3 }
					onChange={ ( value ) => update( 'footer_text', value ) }
					help={ shown( 'footer_text' ) }
					aria-invalid={ shown( 'footer_text' ) ? 'true' : undefined }
				/>
			</fieldset>
			<p>
				<Button
					type="submit"
					variant="primary"
					isBusy={ busy }
					disabled={ busy || ! dirty }
					accessibleWhenDisabled
				>
					{ __( 'Save settings', 'ohmylms' ) }
				</Button>
			</p>
		</form>
	);
}
