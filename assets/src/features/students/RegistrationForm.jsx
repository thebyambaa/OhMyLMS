import {
	createElement,
	createPortal,
	Fragment,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	submitRegistration,
	registrationRedirect,
} from './registration-api.mjs';

/**
 * React fields inside the original PHP form retain third-party hook markup.
 * @param root0
 * @param root0.form
 * @param root0.countries
 * @param root0.submitHost
 * @param root0.noticeHost
 * @param root0.icons
 * @param root0.initialValues
 */
export function RegistrationForm( {
	form,
	countries,
	submitHost,
	noticeHost,
	icons,
	initialValues,
} ) {
	const [ values, setValues ] = useState( initialValues );
	const [ visible, setVisible ] = useState( false );
	const [ accepted, setAccepted ] = useState(
		Boolean( form.elements[ 'tnc-accept' ]?.checked )
	);
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ pending, setPending ] = useState( '' );
	const inFlight = useRef( false );
	useEffect( () => {
		const changed = () =>
			setAccepted( Boolean( form.elements[ 'tnc-accept' ]?.checked ) );
		async function submit( event ) {
			event.preventDefault();
			event.stopImmediatePropagation();
			if ( inFlight.current || ! form.reportValidity() ) {
				return;
			}
			if ( ! form.elements[ 'tnc-accept' ]?.checked ) {
				setError(
					__( 'Please accept the Privacy Policy.', 'ohmylms' )
				);
				return;
			}
			inFlight.current = true;
			setBusy( true );
			setError( '' );
			try {
				const result = await submitRegistration(
					window.ohmylms_frontend_params?.ajax_url ||
						window.ohmylms_checkout_params?.ajax_url,
					new FormData( form )
				);
				if ( result.status === 'pending_verification' ) {
					setPending( result.message );
					form.style.display = 'none';
				} else {
					const redirect = registrationRedirect(
						result.redirect_url,
						window.location.href
					);
					if ( ! redirect ) {
						throw Error(
							__(
								'Your account was created, but the redirect is unavailable. Please log in.',
								'ohmylms'
							)
						);
					}
					window.location.assign( redirect );
				}
			} catch ( cause ) {
				// WordPress may include formatting in validation messages; render only text.
				const text = new DOMParser().parseFromString(
					cause.message ||
						__(
							'Registration failed. Please try again.',
							'ohmylms'
						),
					'text/html'
				).body.textContent;
				setError( text );
			} finally {
				inFlight.current = false;
				setBusy( false );
			}
		}
		form.addEventListener( 'submit', submit, true );
		form.addEventListener( 'change', changed );
		form.dataset.ohmylmsRegistration = 'react';
		return () => {
			form.removeEventListener( 'submit', submit, true );
			form.removeEventListener( 'change', changed );
		};
	}, [ form ] );
	const update = ( event ) =>
		setValues( ( current ) => ( {
			...current,
			[ event.target.name ]: event.target.value,
		} ) );
	function field( name, label, type = 'text', required = false ) {
		const id = 'signup-' + name.replace( '_', '-' );
		return (
			<p
				className={ `ohmylms-form-row row-${ name.replace( '_', '-' ) }` }
			>
				<span className="ohmylms-input-wrapper">
					<label htmlFor={ id } className="ohmylms-input-label">
						{ label }
						{ required && (
							<span className="required" aria-hidden="true">
								{ ' ' }
								*
							</span>
						) }
					</label>
					<input
						type={ type }
						name={ name }
						id={ id }
						className={ `ohmylms-input-text ${ name }` }
						value={ values[ name ] || '' }
						onChange={ update }
						required={ required }
						aria-required={ required }
						pattern={
							type === 'email'
								? '^[a-zA-Z0-9._%+\\-]+@[a-zA-Z0-9.\\-]+\\.[a-zA-Z]{2,}$'
								: undefined
						}
					/>
				</span>
			</p>
		);
	}
	return (
		<Fragment>
			{ error && (
				<p role="alert" className="ohmylms-registration-error">
					{ error }
				</p>
			) }
			<div className="ohmylms-form-row ohmylms-form-names-row">
				{ field(
					'first_name',
					__( 'First Name', 'ohmylms' ),
					'text',
					true
				) }
				{ field(
					'last_name',
					__( 'Last Name', 'ohmylms' ),
					'text',
					true
				) }
			</div>
			{ field( 'email', __( 'Email', 'ohmylms' ), 'email', true ) }
			<p className="ohmylms-form-row row-password">
				<span className="ohmylms-input-wrapper">
					<label
						htmlFor="signup-password"
						className="ohmylms-input-label"
					>
						{ __( 'Password', 'ohmylms' ) }{ ' ' }
						<span className="required" aria-hidden="true">
							*
						</span>
					</label>
					<span className="ohmylms-password-show">
						<input
							id="signup-password"
							name="password"
							type={ visible ? 'text' : 'password' }
							className="ohmylms-input-text password"
							required
							aria-required="true"
							minLength={ 8 }
							autoComplete="new-password"
							value={ values.password || '' }
							onChange={ update }
						/>
						<button
							type="button"
							className="show-password-icon"
							style={ {
								border: 0,
								padding: 0,
								background: 'transparent',
							} }
							aria-label={ __( 'Show password', 'ohmylms' ) }
							aria-pressed={ visible }
							onClickCapture={ ( event ) => {
								event.preventDefault();
								event.stopPropagation();
								setVisible( ( current ) => ! current );
							} }
						>
							<span className="show-password" aria-hidden="true">
								<span
									className="eye-on"
									style={ {
										display: visible ? 'block' : 'none',
									} }
									dangerouslySetInnerHTML={ {
										__html: icons.on,
									} }
								/>
								<span
									className="eye-off"
									style={ {
										display: visible ? 'none' : 'block',
									} }
									dangerouslySetInnerHTML={ {
										__html: icons.off,
									} }
								/>
							</span>
						</button>
					</span>
				</span>
			</p>
			{ field( 'phone', __( 'Phone Number', 'ohmylms' ) ) }
			<p className="ohmylms-form-row row-country">
				<span className="ohmylms-input-wrapper">
					<label
						htmlFor="signup-country"
						className="ohmylms-input-label"
					>
						{ __( 'Country', 'ohmylms' ) }
					</label>
					<select
						id="signup-country"
						name="country"
						className="ohmylms-input-text"
						value={ values.country || '' }
						onChange={ update }
					>
						{ countries.map( ( country ) => (
							<option
								key={ country.value }
								value={ country.value }
							>
								{ country.label }
							</option>
						) ) }
					</select>
				</span>
			</p>
			{ createPortal(
				<button
					type="submit"
					className="ohmylms-button ohmylms-form-signup-submit"
					name="signup"
					value={ __( 'Sign Up', 'ohmylms' ) }
					disabled={ ! accepted || busy }
					aria-busy={ busy }
				>
					{ busy
						? __( 'Signing up…', 'ohmylms' )
						: __( 'Sign Up', 'ohmylms' ) }
				</button>,
				submitHost
			) }
			{ pending &&
				createPortal(
					<div className="ohmylms-email-pending-notice" role="status">
						<h3>{ __( 'Check your inbox!', 'ohmylms' ) }</h3>
						<p>{ pending }</p>
					</div>,
					noticeHost
				) }
		</Fragment>
	);
}
