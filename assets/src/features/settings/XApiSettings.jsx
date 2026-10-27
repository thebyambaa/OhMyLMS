import {
	createElement,
	useCallback,
	useEffect,
	useState,
} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const PATH = '/ohmylms/v1/xapi/settings';

export function XApiSettings() {
	const [ data, setData ] = useState( null );
	const [ form, setForm ] = useState( null );
	const [ busy, setBusy ] = useState( '' );
	const [ dirty, setDirty ] = useState( false );
	const [ message, setMessage ] = useState( null );
	const load = useCallback( ( snapshot ) => {
		setData( snapshot );
		setForm( {
			...snapshot.settings,
			enabled: !! snapshot.settings.enabled,
			responses: !! snapshot.settings.responses,
			groups: Object.fromEntries(
				Object.entries( snapshot.settings.groups ).map(
					( [ key, value ] ) => [ key, !! value ]
				)
			),
			secret: '',
		} );
		setDirty( false );
	}, [] );
	useEffect( () => {
		let active = true;
		apiFetch( { path: PATH } )
			.then( ( snapshot ) => {
				if ( active ) {
					load( snapshot );
				}
			} )
			.catch( ( error ) => {
				if ( active ) {
					setMessage( { status: 'error', text: error.message } );
				}
			} );
		return () => {
			active = false;
		};
	}, [ load ] );
	const set = ( key, value ) => {
		setForm( ( previous ) => ( { ...previous, [ key ]: value } ) );
		setDirty( true );
	};
	const run = async ( action ) => {
		if ( busy ) {
			return;
		}
		setBusy( action );
		setMessage( null );
		try {
			if ( action === 'save' ) {
				load(
					await apiFetch( { path: PATH, method: 'POST', data: form } )
				);
				setMessage( {
					status: 'success',
					text: __( 'xAPI settings saved.', 'ohmylms' ),
				} );
			} else if ( action === 'load' ) {
				load( await apiFetch( { path: PATH } ) );
			} else {
				const result = await apiFetch( {
					path: `${ PATH }/${ action }`,
					method: 'POST',
				} );
				load( result.snapshot );
				setMessage( {
					status: result.ok ? 'success' : 'error',
					text: result.message,
				} );
			}
		} catch ( error ) {
			setMessage( { status: 'error', text: error.message } );
		} finally {
			setBusy( '' );
		}
	};
	if ( ! data || ! form ) {
		return message ? (
			<div>
				<Notice status="error" isDismissible={ false }>
					{ message.text }
				</Notice>
				<Button
					variant="secondary"
					onClick={ () => run( 'load' ) }
					disabled={ !! busy }
				>
					{ __( 'Try again', 'ohmylms' ) }
				</Button>
			</div>
		) : (
			createElement( Spinner )
		);
	}
	const lockedHelp = __( 'Set in wp-config.php.', 'ohmylms' );
	const secretHelp = data.has_secret
		? __( 'A secret is saved. Leave blank to keep it.', 'ohmylms' )
		: undefined;
	const countLabels = {
		pending: __( 'Waiting to send', 'ohmylms' ),
		sent: __( 'Sent', 'ohmylms' ),
		failed: __( 'Failed', 'ohmylms' ),
		skipped: __( 'Skipped', 'ohmylms' ),
	};
	return (
		<div className="ohmylms-streak-settings">
			<header className="ohmylms-streak-settings__header">
				<h2>xAPI</h2>
				<p>
					{ __(
						'Send learning activity to a Learning Record Store (LRS). Statements are queued and delivered in the background.',
						'ohmylms'
					) }
				</p>
			</header>
			{ message && (
				<Notice status={ message.status } isDismissible={ false }>
					{ message.text }
				</Notice>
			) }
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					run( 'save' );
				} }
			>
				<fieldset
					disabled={ !! busy }
					className="ohmylms-streak-settings__fields"
				>
					<div className="ohmylms-streak-settings__grid">
						<section className="ohmylms-streak-settings__card">
							<h3>{ __( 'Connection', 'ohmylms' ) }</h3>
							<ToggleControl
								label={ __( 'Send statements', 'ohmylms' ) }
								help={ __(
									'Uses the same switch as the xAPI add-on.',
									'ohmylms'
								) }
								checked={ form.enabled }
								onChange={ ( value ) =>
									set( 'enabled', value )
								}
							/>
							<TextControl
								label={ __( 'LRS endpoint', 'ohmylms' ) }
								type="url"
								value={ form.endpoint }
								disabled={ data.locked.endpoint }
								help={
									data.locked.endpoint
										? lockedHelp
										: __(
												'For example https://lrs.example.com/xapi',
												'ohmylms'
											)
								}
								onChange={ ( value ) =>
									set( 'endpoint', value )
								}
							/>
							<TextControl
								label={ __( 'Key (username)', 'ohmylms' ) }
								value={ form.key }
								autoComplete="off"
								disabled={ data.locked.key }
								help={
									data.locked.key ? lockedHelp : undefined
								}
								onChange={ ( value ) => set( 'key', value ) }
							/>
							<TextControl
								label={ __( 'Secret (password)', 'ohmylms' ) }
								type="password"
								value={ form.secret }
								autoComplete="new-password"
								disabled={ data.locked.secret }
								help={
									data.locked.secret ? lockedHelp : secretHelp
								}
								onChange={ ( value ) => set( 'secret', value ) }
							/>
						</section>
						<section className="ohmylms-streak-settings__card">
							<h3>{ __( 'Identity and privacy', 'ohmylms' ) }</h3>
							<SelectControl
								label={ __( 'Learner identity', 'ohmylms' ) }
								value={ form.actor }
								options={ [
									{
										value: 'pseudonym',
										label: __(
											'Pseudonym (recommended)',
											'ohmylms'
										),
									},
									{
										value: 'user_id',
										label: __(
											'WordPress user ID',
											'ohmylms'
										),
									},
									{
										value: 'mbox',
										label: __( 'Email address', 'ohmylms' ),
									},
								] }
								help={ __(
									'Pseudonyms send no name or email. Email mode sends personal data to the LRS and skips learners without an email address.',
									'ohmylms'
								) }
								onChange={ ( value ) => set( 'actor', value ) }
							/>
							<CheckboxControl
								label={ __(
									'Include what the learner answered',
									'ohmylms'
								) }
								checked={ form.responses }
								onChange={ ( value ) =>
									set( 'responses', value )
								}
							/>
							<TextControl
								label={ __(
									'Keep sent records (days)',
									'ohmylms'
								) }
								type="number"
								min={ 1 }
								max={ 365 }
								required
								value={ form.retention_days }
								onChange={ ( value ) =>
									set(
										'retention_days',
										value === '' ? '' : Number( value )
									)
								}
							/>
						</section>
						<section className="ohmylms-streak-settings__card">
							<h3>{ __( 'Report', 'ohmylms' ) }</h3>
							{ Object.entries( data.group_labels ).map(
								( [ key, label ] ) => (
									<CheckboxControl
										key={ key }
										label={ label }
										checked={ !! form.groups[ key ] }
										onChange={ ( value ) =>
											set( 'groups', {
												...form.groups,
												[ key ]: value,
											} )
										}
									/>
								)
							) }
						</section>
					</div>
					<Button
						variant="primary"
						type="submit"
						isBusy={ busy === 'save' }
						disabled={ !! busy }
					>
						{ __( 'Save settings', 'ohmylms' ) }
					</Button>
				</fieldset>
			</form>
			<section className="ohmylms-streak-settings__card">
				<h3>{ __( 'Delivery', 'ohmylms' ) }</h3>
				<table className="widefat striped">
					<tbody>
						{ Object.entries( countLabels ).map(
							( [ key, label ] ) => (
								<tr key={ key }>
									<th scope="row">{ label }</th>
									<td>{ data.counts[ key ] }</td>
								</tr>
							)
						) }
						<tr>
							<th scope="row">
								{ __( 'Last successful send', 'ohmylms' ) }
							</th>
							<td>
								{ data.health.last_success
									? new Date(
											data.health.last_success * 1000
										).toLocaleString()
									: __( 'Never', 'ohmylms' ) }
							</td>
						</tr>
						{ data.health.last_error && (
							<tr>
								<th scope="row">
									{ __( 'Last problem', 'ohmylms' ) }
								</th>
								<td>{ data.health.last_error }</td>
							</tr>
						) }
					</tbody>
				</table>
				<p>
					{ __(
						'Actions use the saved settings. Save any changes first.',
						'ohmylms'
					) }
				</p>
				<div className="ohmylms-streak-settings__fields">
					{ [
						[ 'test', __( 'Test connection', 'ohmylms' ) ],
						[ 'send', __( 'Send queued now', 'ohmylms' ) ],
						[ 'retry', __( 'Retry failed', 'ohmylms' ) ],
					].map( ( [ action, label ] ) => (
						<Button
							key={ action }
							variant="secondary"
							disabled={
								!! busy ||
								dirty ||
								( action === 'send' && ! data.settings.enabled )
							}
							isBusy={ busy === action }
							onClick={ () => run( action ) }
						>
							{ label }
						</Button>
					) ) }
				</div>
				{ data.failures.length > 0 && (
					<div>
						<h4>{ __( 'Recent failures', 'ohmylms' ) }</h4>
						<table className="widefat striped">
							<thead>
								<tr>
									<th>{ __( 'Event', 'ohmylms' ) }</th>
									<th>{ __( 'Attempts', 'ohmylms' ) }</th>
									<th>{ __( 'Problem', 'ohmylms' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ data.failures.map( ( failure ) => (
									<tr key={ failure.id }>
										<td>{ failure.kind }</td>
										<td>{ failure.tries }</td>
										<td>{ failure.last_error }</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>
				) }
			</section>
		</div>
	);
}
