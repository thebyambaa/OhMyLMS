import { useRef, useState } from '@wordpress/element';
import {
	Button,
	Modal,
	Notice,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import type { MouseEvent } from 'react';
import { ApiError, normalizeApiError } from '../../lib/api';
import { saveEmail, sendTestEmail } from './api';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import { useEmail, useEmailPreview } from './hooks';
import {
	LIST_PATH,
	changedFields,
	insertTag,
	serverFieldError,
	toDraft,
	toInput,
	validateDraft,
	validateTags,
	type FieldErrors,
} from './model';
import type { DraftField, EmailDraft, EmailTemplate } from './types';

const ALL_FIELDS: readonly DraftField[] = [
	'subject',
	'heading',
	'additional_content',
	'button_text',
	'button_link',
	'footer_text',
	'course_suggestion_text',
	'recipient_email',
];

/**
 * Fields sent to the preview and test routes (recipients never matter to either).
 * @param email
 */
function previewFields( email: EmailTemplate ): DraftField[] {
	return ALL_FIELDS.filter(
		( field ) =>
			'recipient_email' !== field &&
			( 'course_suggestion_text' !== field ||
				null !== email.course_suggestion_text )
	);
}

interface Message {
	status: 'success' | 'error' | 'warning';
	text: string;
}

export function EmailEditor( { id }: { id: string } ) {
	const state = useEmail( id );
	if ( state.loading ) {
		return <p role="status">{ __( 'Loading email…', 'ohmylms' ) }</p>;
	}
	if ( state.error || ! state.email ) {
		return (
			<>
				<Notice status="error" isDismissible={ false }>
					{ state.error ?? __( 'Email not found.', 'ohmylms' ) }{ ' ' }
					<Button variant="link" onClick={ state.reload }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
				<Button
					variant="link"
					href={ `#${ LIST_PATH }` }
					aria-label={ __( 'Back to all emails', 'ohmylms' ) }
				>
					{ __( 'Back to all emails', 'ohmylms' ) }
				</Button>
			</>
		);
	}
	return <Editor email={ state.email } onSaved={ state.setEmail } />;
}

function Editor( {
	email,
	onSaved,
}: {
	email: EmailTemplate;
	onSaved: ( email: EmailTemplate ) => void;
} ) {
	const [ draft, setDraft ] = useState< EmailDraft >( () =>
		toDraft( email )
	);
	const [ submitted, setSubmitted ] = useState( false );
	const [ touched, setTouched ] = useState< readonly DraftField[] >( [] );
	const [ serverErrors, setServerErrors ] = useState<
		FieldErrors< DraftField >
	>( {} );
	const [ busy, setBusy ] = useState< 'save' | 'test' | null >( null );
	const [ message, setMessage ] = useState< Message | null >( null );
	const formRef = useRef< HTMLFormElement >( null );

	const changed = changedFields( draft, email );
	const dirty = changed.length > 0;
	const guard = useUnsavedChangesGuard( dirty );
	const hardErrors = validateDraft( draft, email );
	const tagErrors = validateTags( draft, email );
	const errors: FieldErrors< DraftField > = {
		...tagErrors,
		...hardErrors,
		...serverErrors,
	};

	const previewable = previewFields( email );
	const previewInput = toInput( draft, previewable );
	const preview = useEmailPreview(
		email.id,
		previewInput,
		previewable.every( ( field ) => ! hardErrors[ field ] )
	);

	const shown = ( field: DraftField ) =>
		submitted || touched.includes( field ) || serverErrors[ field ]
			? errors[ field ]
			: undefined;

	const update = ( field: DraftField, value: string ) => {
		setDraft( ( current ) => ( { ...current, [ field ]: value } ) );
		setServerErrors( ( current ) => {
			if ( ! current[ field ] ) {
				return current;
			}
			const next = { ...current };
			delete next[ field ];
			return next;
		} );
		setMessage( null );
	};

	const focusFirstInvalid = () => {
		window.requestAnimationFrame( () =>
			formRef.current
				?.querySelector< HTMLElement >( '[aria-invalid="true"]' )
				?.focus()
		);
	};

	const hasErrors = () => Object.keys( errors ).length > 0;

	function describe( failure: unknown ) {
		const error = normalizeApiError( failure );
		const field = serverFieldError( error, ALL_FIELDS );
		if ( field ) {
			setServerErrors( ( current ) => ( {
				...current,
				[ field.field ]: field.message,
			} ) );
			focusFirstInvalid();
			return {
				status: 'error' as const,
				text: __( 'Fix the highlighted fields.', 'ohmylms' ),
			};
		}
		return { status: 'error' as const, text: error.message };
	}

	async function save() {
		setSubmitted( true );
		setMessage( null );
		if ( hasErrors() ) {
			setMessage( {
				status: 'error',
				text: __( 'Fix the highlighted fields.', 'ohmylms' ),
			} );
			focusFirstInvalid();
			return;
		}
		setBusy( 'save' );
		try {
			const saved = await saveEmail(
				email.id,
				toInput( draft, changed )
			);
			onSaved( saved );
			setDraft( toDraft( saved ) );
			setSubmitted( false );
			setServerErrors( {} );
			setMessage( {
				status: 'success',
				text: __( 'Email saved.', 'ohmylms' ),
			} );
		} catch ( failure ) {
			setMessage( describe( failure ) );
		} finally {
			setBusy( null );
		}
	}

	async function sendTest() {
		setSubmitted( true );
		setMessage( null );
		if ( hasErrors() ) {
			setMessage( {
				status: 'error',
				text: __(
					'Fix the highlighted fields before sending a test.',
					'ohmylms'
				),
			} );
			focusFirstInvalid();
			return;
		}
		setBusy( 'test' );
		try {
			const result = await sendTestEmail( email.id, previewInput );
			setMessage( {
				status: 'success',
				text: sprintf(
					/* translators: %s: email address. */
					__( 'Test email sent to %s.', 'ohmylms' ),
					result.to
				),
			} );
		} catch ( failure ) {
			const error = failure instanceof ApiError ? failure : null;
			setMessage(
				error && 429 === error.status
					? { status: 'warning', text: error.message }
					: describe( failure )
			);
		} finally {
			setBusy( null );
		}
	}

	function addTag( tag: string ) {
		const element = formRef.current?.querySelector(
			'textarea[data-field="additional_content"]'
		);
		const area = element instanceof HTMLTextAreaElement ? element : null;
		const start = area?.selectionStart ?? draft.additional_content.length;
		const end = area?.selectionEnd ?? start;
		const result = insertTag( draft.additional_content, start, end, tag );
		update( 'additional_content', result.value );
		window.requestAnimationFrame( () => {
			area?.focus();
			area?.setSelectionRange( result.caret, result.caret );
		} );
	}

	const text = (
		field: DraftField,
		label: string,
		help?: string,
		extra: { type?: 'url' } = {}
	) => (
		<TextControl
			__nextHasNoMarginBottom
			label={ label }
			value={ draft[ field ] }
			type={ extra.type }
			onChange={ ( value ) => update( field, value ) }
			onBlur={ () => setTouched( ( current ) => [ ...current, field ] ) }
			help={ shown( field ) ?? help }
			aria-invalid={ shown( field ) ? 'true' : undefined }
		/>
	);
	const area = (
		field: DraftField,
		label: string,
		rows: number,
		help?: string
	) => (
		<TextareaControl
			__nextHasNoMarginBottom
			data-field={ field }
			label={ label }
			value={ draft[ field ] }
			rows={ rows }
			onChange={ ( value ) => update( field, value ) }
			onBlur={ () => setTouched( ( current ) => [ ...current, field ] ) }
			help={ shown( field ) ?? help }
			aria-invalid={ shown( field ) ? 'true' : undefined }
		/>
	);

	const unknown = preview.preview?.unknown_tags ?? [];

	return (
		<>
			<p>
				<Button
					variant="link"
					href={ `#${ LIST_PATH }` }
					onClick={ ( event: MouseEvent ) => {
						event.preventDefault();
						guard.requestLeave( LIST_PATH );
					} }
				>
					{ __( '← Back to all emails', 'ohmylms' ) }
				</Button>
			</p>
			<h2>{ email.title }</h2>
			<p>{ email.description }</p>
			<p>
				{ email.enabled
					? __(
							'This email is on. Turn it on or off from the email list.',
							'ohmylms'
						)
					: __(
							'This email is off and is not being sent. Turn it on from the email list.',
							'ohmylms'
						) }
			</p>
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
					politeness={
						'success' === message.status ? 'polite' : 'assertive'
					}
				>
					{ message.text }
				</Notice>
			) }
			<form
				ref={ formRef }
				noValidate
				aria-label={ sprintf(
					/* translators: %s: email name. */
					__( 'Edit %s', 'ohmylms' ),
					email.title
				) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					if ( ! busy ) {
						void save();
					}
				} }
			>
				<fieldset disabled={ 'save' === busy }>
					<legend>{ __( 'Message', 'ohmylms' ) }</legend>
					{ text(
						'subject',
						__( 'Subject', 'ohmylms' ),
						__( 'Plain text on one line.', 'ohmylms' )
					) }
					{ text( 'heading', __( 'Heading', 'ohmylms' ) ) }
					{ area(
						'additional_content',
						__( 'Message (HTML)', 'ohmylms' ),
						8,
						__(
							'Formatting, links, images, lists and tables are kept. Scripts and forms are removed.',
							'ohmylms'
						)
					) }
					<div
						role="group"
						aria-label={ __( 'Insert a merge tag', 'ohmylms' ) }
					>
						{ email.tags.map( ( tag ) => (
							<Button
								key={ tag.tag }
								size="small"
								variant="secondary"
								title={ tag.description }
								onClick={ () => addTag( tag.tag ) }
							>
								{ `{${ tag.tag }}` }
							</Button>
						) ) }
					</div>
					{ text( 'button_text', __( 'Button text', 'ohmylms' ) ) }
					{ text(
						'button_link',
						__( 'Button link', 'ohmylms' ),
						__(
							'Optional. Some emails choose their own destination and ignore this.',
							'ohmylms'
						),
						{ type: 'url' }
					) }
					{ area(
						'footer_text',
						__( 'Footer (HTML)', 'ohmylms' ),
						4
					) }
					{ null !== email.course_suggestion_text &&
						area(
							'course_suggestion_text',
							__( 'Course suggestion text', 'ohmylms' ),
							3
						) }
					{ 'creator' === email.recipient_type &&
						area(
							'recipient_email',
							__( 'Recipients', 'ohmylms' ),
							3,
							__(
								'One address per line. Leave empty to use the default recipient.',
								'ohmylms'
							)
						) }
				</fieldset>
				<h3>{ __( 'Merge tags', 'ohmylms' ) }</h3>
				<p>
					{ __(
						'Write a tag as {tag_name} or [tag_name]. It is replaced when the email is sent.',
						'ohmylms'
					) }
				</p>
				<dl>
					{ email.tags.map( ( tag ) => (
						<div key={ tag.tag }>
							<dt>{ `{${ tag.tag }}` }</dt>
							<dd>{ tag.description }</dd>
						</div>
					) ) }
				</dl>
				<p>
					<Button
						type="submit"
						variant="primary"
						isBusy={ 'save' === busy }
						disabled={ null !== busy || ! dirty }
						accessibleWhenDisabled
					>
						{ __( 'Save changes', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="secondary"
						disabled={ null !== busy }
						isBusy={ 'test' === busy }
						onClick={ () => void sendTest() }
					>
						{ __( 'Send test email to me', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ null !== busy || ! dirty }
						onClick={ () => {
							setDraft( toDraft( email ) );
							setServerErrors( {} );
							setSubmitted( false );
							setTouched( [] );
							setMessage( null );
						} }
					>
						{ __( 'Discard changes', 'ohmylms' ) }
					</Button>
				</p>
			</form>
			<section aria-labelledby="oml-email-preview-title">
				<h3 id="oml-email-preview-title">
					{ __( 'Preview', 'ohmylms' ) }
				</h3>
				<p>
					{ __(
						'Rendered with sample data and your current, unsaved text.',
						'ohmylms'
					) }
				</p>
				{ unknown.length > 0 && (
					<Notice status="warning" isDismissible={ false }>
						{ sprintf(
							/* translators: %s: comma separated merge tags. */
							__( 'These tags are not replaced: %s', 'ohmylms' ),
							unknown.join( ', ' )
						) }
					</Notice>
				) }
				{ preview.error && (
					<Notice status="error" isDismissible={ false }>
						{ preview.error }
					</Notice>
				) }
				{ preview.loading && (
					<p role="status">
						{ __( 'Updating preview…', 'ohmylms' ) }
					</p>
				) }
				{ preview.preview && (
					<>
						<p>
							<strong>{ __( 'Subject:', 'ohmylms' ) }</strong>{ ' ' }
							{ preview.preview.subject }
						</p>
						<iframe
							title={ __( 'Email preview', 'ohmylms' ) }
							sandbox=""
							srcDoc={ `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body style="margin:0">${ preview.preview.html }</body></html>` }
							className="ohmylms-email-preview"
						/>
					</>
				) }
			</section>
			{ guard.pendingPath && (
				<Modal
					title={ __( 'Discard unsaved changes?', 'ohmylms' ) }
					onRequestClose={ guard.stay }
					shouldCloseOnClickOutside={ false }
				>
					<p>
						{ __(
							'You have changes that are not saved. If you leave now they are lost.',
							'ohmylms'
						) }
					</p>
					<Button
						variant="primary"
						isDestructive
						onClick={ guard.leave }
					>
						{ __( 'Discard and leave', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button variant="tertiary" onClick={ guard.stay }>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}
