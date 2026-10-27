import { createElement, useEffect, useState, useId } from '@wordpress/element';
import { Modal, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
const accountFields = [
	[ 'first_name', __( 'First name', 'ohmylms' ), 'text', true ],
	[ 'last_name', __( 'Last name', 'ohmylms' ), 'text', false ],
	[ 'email', __( 'Email', 'ohmylms' ), 'email', true ],
	[ 'username', __( 'Username (optional)', 'ohmylms' ), 'text', false ],
];
const kinds = {
	students: {
		title: __( 'Add student', 'ohmylms' ),
		path: 'users',
		role: 'student',
		account: true,
	},
	teachers: {
		title: __( 'Add teacher', 'ohmylms' ),
		path: 'users',
		role: 'teacher',
		account: true,
	},
	parents: {
		title: __( 'Add parent', 'ohmylms' ),
		path: 'users',
		role: 'parent',
		account: true,
	},
	users: {
		title: __( 'Add user', 'ohmylms' ),
		path: 'users',
		account: true,
	},
	classes: {
		title: __( 'Create independent class', 'ohmylms' ),
		path: 'classes',
		fields: [
			[ 'name', __( 'Class name', 'ohmylms' ), 'text', true ],
			[ 'subject', __( 'Subject', 'ohmylms' ), 'text', false ],
			[ 'grade', __( 'Grade', 'ohmylms' ), 'text', false ],
		],
	},
	schools: {
		title: __( 'Create school', 'ohmylms' ),
		path: 'schools',
		fields: [
			[ 'name', __( 'School name', 'ohmylms' ), 'text', true ],
			[
				'timezone',
				__( 'Timezone', 'ohmylms' ),
				'text',
				true,
				'Asia/Ulaanbaatar',
			],
		],
	},
};
export function addLabel( kind ) {
	return kinds[ kind ]?.title || __( 'Add', 'ohmylms' );
}
export function AddModal( { kind, onClose, onSaved } ) {
	const _controlId = useId();
	const config = kinds[ kind ];
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );
	if ( ! config ) {
		return null;
	}
	const fields = config.account ? accountFields : config.fields;
	return (
		<Modal
			title={ config.title }
			onRequestClose={ () => ! saving && onClose() }
		>
			<form
				onSubmit={ async ( event ) => {
					event.preventDefault();
					if ( saving ) {
						return;
					}
					const data = Object.fromEntries(
						new FormData( event.currentTarget )
					);
					if ( config.account ) {
						data.role = config.role || data.role;
						data.send_email = data.send_email === 'on';
					}
					setSaving( true );
					setError( '' );
					try {
						const result = await window.wp.apiFetch( {
							path: `/ohmylms/v1/school/${ config.path }`,
							method: 'POST',
							data,
						} );
						onSaved( result );
					} catch ( failure ) {
						setError( failure.message );
						setSaving( false );
					}
				} }
			>
				<fieldset
					disabled={ saving }
					style={ {
						border: 0,
						padding: 0,
						margin: 0,
					} }
				>
					{ config.account && ! config.role && (
						<p>
							<label htmlFor={ _controlId + '-field-1' }>
								{ __( 'Role', 'ohmylms' ) } *
								<br />
								<select
									name="role"
									required
									defaultValue="student"
									style={ {
										width: '100%',
									} }
									id={ _controlId + '-field-1' }
								>
									<option value="student">
										{ __( 'Student', 'ohmylms' ) }
									</option>
									<option value="teacher">
										{ __( 'Teacher', 'ohmylms' ) }
									</option>
									<option value="parent">
										{ __( 'Parent', 'ohmylms' ) }
									</option>
								</select>
							</label>
						</p>
					) }
					{ fields.map(
						( [ name, label, type, required, value ] ) => (
							<p key={ name }>
								<label
									htmlFor={
										_controlId + '-field-2' + '-' + name
									}
								>
									{ label }
									{ required ? ' *' : '' }
									<br />
									<input
										name={ name }
										type={ type }
										required={ required }
										defaultValue={ value }
										maxLength={ 190 }
										style={ {
											width: '100%',
										} }
										id={
											_controlId + '-field-2' + '-' + name
										}
									/>
								</label>
							</p>
						)
					) }
					{ config.account && (
						<p>
							<label htmlFor={ _controlId + '-field-3' }>
								<input
									type="checkbox"
									name="send_email"
									defaultChecked
									id={ _controlId + '-field-3' }
								/>{ ' ' }
								{ __(
									'Email the user a link to set their password',
									'ohmylms'
								) }
							</label>
						</p>
					) }
					{ error && (
						<p
							role="alert"
							style={ {
								color: '#b32d2e',
							} }
						>
							{ error }
						</p>
					) }
					<div
						style={ {
							display: 'flex',
							gap: 8,
							justifyContent: 'flex-end',
						} }
					>
						<Button
							variant="tertiary"
							onClick={ onClose }
							disabled={ saving }
						>
							{ __( 'Cancel', 'ohmylms' ) }
						</Button>
						<Button
							variant="primary"
							type="submit"
							isBusy={ saving }
							disabled={ saving }
						>
							{ __( 'Save', 'ohmylms' ) }
						</Button>
					</div>
				</fieldset>
			</form>
		</Modal>
	);
}
export function EditModal( { id, onClose, onSaved } ) {
	const _controlId2 = useId();
	const [ user, setUser ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );
	useEffect( () => {
		window.wp
			.apiFetch( {
				path: `/ohmylms/v1/school/users/${ id }`,
			} )
			.then( setUser, ( failure ) => setError( failure.message ) );
	}, [ id ] );
	const fields = [
		[ 'first_name', __( 'First name', 'ohmylms' ), 'text', true ],
		[ 'last_name', __( 'Last name', 'ohmylms' ), 'text', false ],
		[ 'email', __( 'Email', 'ohmylms' ), 'email', true ],
	];
	return (
		<Modal
			title={ __( 'Edit user', 'ohmylms' ) }
			onRequestClose={ () => ! saving && onClose() }
		>
			{ ! user ? (
				<p role={ error ? 'alert' : 'status' }>
					{ error || __( 'Loading…', 'ohmylms' ) }
				</p>
			) : (
				<form
					onSubmit={ async ( event ) => {
						event.preventDefault();
						if ( saving ) {
							return;
						}
						const data = Object.fromEntries(
							new FormData( event.currentTarget )
						);
						data.send_reset = data.send_reset === 'on';
						setSaving( true );
						setError( '' );
						try {
							onSaved(
								await window.wp.apiFetch( {
									path: `/ohmylms/v1/school/users/${ id }`,
									method: 'POST',
									data,
								} )
							);
						} catch ( failure ) {
							setError( failure.message );
							setSaving( false );
						}
					} }
				>
					<fieldset
						disabled={ saving }
						style={ {
							border: 0,
							padding: 0,
							margin: 0,
						} }
					>
						<p>
							{ __( 'Username', 'ohmylms' ) }:{ ' ' }
							<strong>{ user.username }</strong>
						</p>
						{ fields.map( ( [ name, label, type, required ] ) => (
							<p key={ name }>
								<label
									htmlFor={
										_controlId2 + '-field-4' + '-' + name
									}
								>
									{ label }
									{ required ? ' *' : '' }
									<br />
									<input
										name={ name }
										type={ type }
										required={ required }
										defaultValue={ user[ name ] }
										maxLength={ 190 }
										style={ {
											width: '100%',
										} }
										id={
											_controlId2 +
											'-field-4' +
											'-' +
											name
										}
									/>
								</label>
							</p>
						) ) }
						<p>
							<label htmlFor={ _controlId2 + '-field-5' }>
								<input
									type="checkbox"
									name="send_reset"
									id={ _controlId2 + '-field-5' }
								/>{ ' ' }
								{ __(
									'Email the user a password reset link',
									'ohmylms'
								) }
							</label>
						</p>
						{ error && (
							<p
								role="alert"
								style={ {
									color: '#b32d2e',
								} }
							>
								{ error }
							</p>
						) }
						<div
							style={ {
								display: 'flex',
								gap: 8,
								justifyContent: 'flex-end',
							} }
						>
							<Button
								variant="tertiary"
								onClick={ onClose }
								disabled={ saving }
							>
								{ __( 'Cancel', 'ohmylms' ) }
							</Button>
							<Button
								variant="primary"
								type="submit"
								isBusy={ saving }
								disabled={ saving }
							>
								{ __( 'Save', 'ohmylms' ) }
							</Button>
						</div>
					</fieldset>
				</form>
			) }
		</Modal>
	);
}
