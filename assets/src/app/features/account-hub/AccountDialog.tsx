import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { normalizeApiError } from '../../lib/api';
import {
	MAX_ACCOUNT_NAME,
	MAX_EMAIL,
	MAX_USERNAME,
	failedField,
	validateAccount,
	validateNewAccount,
} from './accounts';
import { createAccount, getAccount, updateAccount } from './accountsApi';
import { isConflict } from './model';
import type {
	Account,
	AccountInput,
	AccountOptions,
	AccountType,
	EditableAccount,
	FormErrors,
	NewAccountInput,
} from './types';

const NEW_FIELDS = [ 'first_name', 'last_name', 'email', 'username' ] as const;

/**
 * Run a save; remember a field error or a stale-save conflict from the server, then rethrow so the
 * operation shows the message.
 *
 * @param work    The request.
 * @param onField Called with the server's field and message.
 * @param onStale Called on a 409 conflict.
 * @return The request's result.
 */
async function tracked< T >(
	work: () => Promise< T >,
	onField: (
		field: ( typeof NEW_FIELDS )[ number ],
		message: string
	) => void,
	onStale: () => void
): Promise< T > {
	try {
		return await work();
	} catch ( failure ) {
		const error = normalizeApiError( failure );
		if ( isConflict( error ) ) {
			onStale();
		}
		const field = failedField( error.details, NEW_FIELDS );
		if ( field ) {
			onField( field, error.message );
		}
		throw failure;
	}
}

/**
 * Create a student, teacher or parent account. The person sets their own password from the
 * emailed link; no password is ever shown or chosen here.
 *
 * @param props           Component props.
 * @param props.options   Account types the current user may create.
 * @param props.onClose   Close the dialog.
 * @param props.onCreated Called with the created account.
 */
export function CreateAccountDialog( {
	options,
	onClose,
	onCreated,
}: {
	options: AccountOptions;
	onClose: () => void;
	onCreated: ( account: Account ) => void;
} ) {
	const operation = useOperation();
	const [ form, setForm ] = useState< NewAccountInput >( {
		type: options.types[ 0 ]?.value ?? 'student',
		first_name: '',
		last_name: '',
		email: '',
		username: '',
		send_email: true,
	} );
	const [ errors, setErrors ] = useState< FormErrors< NewAccountInput > >(
		{}
	);

	function submit() {
		const found = validateNewAccount( form );
		setErrors( found );
		if ( Object.keys( found ).length ) {
			return;
		}
		void operation.run(
			( signal ) =>
				tracked(
					() => createAccount( form, signal ),
					( field, message ) =>
						setErrors( ( current ) => ( {
							...current,
							[ field ]: message,
						} ) ),
					() => undefined
				),
			onCreated
		);
	}

	return (
		<Modal
			title={ __( 'Add account', 'ohmylms' ) }
			onRequestClose={ () => ! operation.busy && onClose() }
			shouldCloseOnClickOutside={ false }
		>
			<form
				noValidate
				onSubmit={ ( event ) => {
					event.preventDefault();
					submit();
				} }
			>
				{ operation.error && (
					<Notice status="error" isDismissible={ false }>
						{ operation.error }
					</Notice>
				) }
				<SelectControl
					label={ __( 'Account type', 'ohmylms' ) }
					value={ form.type }
					options={ options.types }
					disabled={ operation.busy }
					onChange={ ( type ) =>
						setForm( { ...form, type: type as AccountType } )
					}
				/>
				<TextControl
					label={ __( 'First name', 'ohmylms' ) }
					value={ form.first_name }
					maxLength={ MAX_ACCOUNT_NAME }
					required
					disabled={ operation.busy }
					help={ errors.first_name }
					onChange={ ( value ) =>
						setForm( { ...form, first_name: value } )
					}
				/>
				<TextControl
					label={ __( 'Last name', 'ohmylms' ) }
					value={ form.last_name }
					maxLength={ MAX_ACCOUNT_NAME }
					disabled={ operation.busy }
					help={ errors.last_name }
					onChange={ ( value ) =>
						setForm( { ...form, last_name: value } )
					}
				/>
				<TextControl
					label={ __( 'Email', 'ohmylms' ) }
					type="email"
					value={ form.email }
					maxLength={ MAX_EMAIL }
					required
					disabled={ operation.busy }
					help={ errors.email }
					onChange={ ( email ) => setForm( { ...form, email } ) }
				/>
				<TextControl
					label={ __( 'Username (optional)', 'ohmylms' ) }
					value={ form.username }
					maxLength={ MAX_USERNAME }
					disabled={ operation.busy }
					help={
						errors.username ??
						__(
							'Leave empty to derive it from the email.',
							'ohmylms'
						)
					}
					onChange={ ( username ) =>
						setForm( { ...form, username } )
					}
				/>
				<CheckboxControl
					label={ __(
						'Email the person a link to set their password',
						'ohmylms'
					) }
					checked={ form.send_email }
					disabled={ operation.busy }
					onChange={ ( value ) =>
						setForm( { ...form, send_email: value } )
					}
				/>
				<Button
					type="submit"
					variant="primary"
					disabled={ operation.busy }
					isBusy={ operation.busy }
				>
					{ __( 'Create account', 'ohmylms' ) }
				</Button>{ ' ' }
				<Button
					variant="tertiary"
					disabled={ operation.busy }
					onClick={ onClose }
				>
					{ __( 'Cancel', 'ohmylms' ) }
				</Button>
			</form>
		</Modal>
	);
}

function toInput( account: EditableAccount ): AccountInput {
	return {
		first_name: account.first_name,
		last_name: account.last_name,
		email: account.email,
	};
}

/**
 * Edit an account's first name, last name and email. A save made after someone else changed the
 * account is refused; the dialog then offers to reload it.
 *
 * @param props         Component props.
 * @param props.account Listed account.
 * @param props.onClose Close the dialog.
 * @param props.onSaved Called with the saved account.
 */
export function EditAccountDialog( {
	account,
	onClose,
	onSaved,
}: {
	account: Account;
	onClose: () => void;
	onSaved: ( account: EditableAccount ) => void;
} ) {
	const operation = useOperation();
	const loaded = useRequest(
		useCallback(
			( signal: AbortSignal ) => getAccount( account.id, signal ),
			[ account.id ]
		)
	);
	const [ form, setForm ] = useState< AccountInput | null >( null );
	const [ sendReset, setSendReset ] = useState( false );
	const [ stale, setStale ] = useState( false );
	const [ errors, setErrors ] = useState< FormErrors< AccountInput > >( {} );
	const data = loaded.data;

	useEffect( () => {
		setForm( data ? toInput( data ) : null );
		setStale( false );
		setErrors( {} );
	}, [ data ] );

	function submit() {
		if ( ! form || ! data ) {
			return;
		}
		const found = validateAccount( form );
		setErrors( found );
		if ( Object.keys( found ).length ) {
			return;
		}
		void operation.run(
			( signal ) =>
				tracked(
					() =>
						updateAccount(
							data.id,
							form,
							data.version,
							sendReset,
							signal
						),
					( field, message ) =>
						setErrors( ( current ) => ( {
							...current,
							[ field ]: message,
						} ) ),
					() => setStale( true )
				),
			onSaved
		);
	}

	return (
		<Modal
			title={ sprintf(
				/* translators: %s: account name. */
				__( 'Edit %s', 'ohmylms' ),
				account.display_name
			) }
			onRequestClose={ () => ! operation.busy && onClose() }
			shouldCloseOnClickOutside={ false }
		>
			{ loaded.loading && (
				<p role="status">{ __( 'Loading account…', 'ohmylms' ) }</p>
			) }
			{ loaded.error && (
				<Notice status="error" isDismissible={ false }>
					{ loaded.error }
				</Notice>
			) }
			{ data && form && (
				<form
					noValidate
					onSubmit={ ( event ) => {
						event.preventDefault();
						submit();
					} }
				>
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error }
						</Notice>
					) }
					{ stale && (
						<Notice status="warning" isDismissible={ false }>
							<Button
								variant="secondary"
								onClick={ () => {
									operation.clearError();
									loaded.retry();
								} }
							>
								{ __(
									'Reload the account (discards your edits)',
									'ohmylms'
								) }
							</Button>
						</Notice>
					) }
					<p>
						{ sprintf(
							/* translators: 1: username, 2: role names. */
							__( 'Username: %1$s. Roles: %2$s.', 'ohmylms' ),
							data.username,
							data.roles
								.map( ( role ) => role.label )
								.join( ', ' ) || __( 'none', 'ohmylms' )
						) }
					</p>
					<TextControl
						label={ __( 'First name', 'ohmylms' ) }
						value={ form.first_name }
						maxLength={ MAX_ACCOUNT_NAME }
						required
						disabled={ operation.busy }
						help={ errors.first_name }
						onChange={ ( value ) =>
							setForm( { ...form, first_name: value } )
						}
					/>
					<TextControl
						label={ __( 'Last name', 'ohmylms' ) }
						value={ form.last_name }
						maxLength={ MAX_ACCOUNT_NAME }
						disabled={ operation.busy }
						help={ errors.last_name }
						onChange={ ( value ) =>
							setForm( { ...form, last_name: value } )
						}
					/>
					<TextControl
						label={ __( 'Email', 'ohmylms' ) }
						type="email"
						value={ form.email }
						maxLength={ MAX_EMAIL }
						required
						disabled={ operation.busy }
						help={ errors.email }
						onChange={ ( email ) => setForm( { ...form, email } ) }
					/>
					<CheckboxControl
						label={ __(
							'Email the person a password reset link',
							'ohmylms'
						) }
						checked={ sendReset }
						disabled={ operation.busy }
						onChange={ setSendReset }
					/>
					<Button
						type="submit"
						variant="primary"
						disabled={ operation.busy || stale }
						isBusy={ operation.busy }
					>
						{ __( 'Save account', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ operation.busy }
						onClick={ onClose }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</form>
			) }
		</Modal>
	);
}
