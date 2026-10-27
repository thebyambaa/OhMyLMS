import {
	createElement,
	Fragment,
	useEffect,
	useState,
} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
export function MCPSettings() {
	const [ settings, setSettings ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( '' );
	const [ issued, setIssued ] = useState( null );
	const [ copied, setCopied ] = useState( '' );
	useEffect( () => {
		let active = true;
		apiFetch( {
			path: '/ohmylms/v1/mcp/settings',
		} ).then(
			( data ) => active && setSettings( data ),
			( failure ) =>
				active &&
				setError( failure.message || 'Unable to load MCP settings.' )
		);
		return () => {
			active = false;
		};
	}, [] );
	async function update( provider, action ) {
		setBusy( provider.id );
		setError( '' );
		setCopied( '' );
		try {
			const data = await apiFetch( {
				path: `/ohmylms/v1/mcp/settings/${ provider.id }`,
				method: 'POST',
				data: {
					action,
				},
			} );
			setSettings( data );
			setIssued(
				data.token
					? {
							provider: provider.label,
							token: data.token,
						}
					: null
			);
		} catch ( failure ) {
			setError( failure.message || 'Unable to update this connection.' );
		} finally {
			setBusy( '' );
		}
	}
	async function copy( value, label ) {
		try {
			await navigator.clipboard.writeText( value );
			setCopied( `${ label } copied.` );
		} catch {
			setError(
				'Copy is unavailable in this browser. Select and copy the value manually.'
			);
		}
	}
	return (
		<section
			aria-labelledby="ohmylms-mcp-heading"
			style={ {
				maxWidth: 900,
			} }
		>
			<h2 id="ohmylms-mcp-heading">MCP connections</h2>
			<p>Connect OpenAI, Anthropic, and Gemini assistants to your LMS.</p>
			{ error && <p role="alert">{ error }</p> }
			{ ! settings && ! error && (
				<p role="status">Loading connections…</p>
			) }
			{ settings && (
				<Fragment>
					<h3>Server URL</h3>
					<p>
						{ settings.implementation }{ ' ' }
						{ settings.status?.adapter_version }
					</p>
					{ settings.status && ! settings.status.ready && (
						<p role="alert">{ settings.status.message }</p>
					) }
					<input
						aria-label="MCP server URL"
						value={ settings.server_url }
						readOnly
						onFocus={ ( event ) => event.target.select() }
						style={ {
							width: '100%',
							padding: 10,
							marginBottom: 10,
						} }
					/>
					<button
						className="button"
						onClick={ () =>
							copy( settings.server_url, 'Server URL' )
						}
					>
						Copy server URL
					</button>
					<p>
						Use Streamable HTTP and a bearer token. Hosted AI
						services need a public HTTPS address. These connections
						can read draft courses and quiz answer keys.
					</p>
					{ issued && (
						<div
							role="status"
							style={ {
								padding: 16,
								background: '#edf7ed',
								margin: '20px 0',
							} }
						>
							<strong>{ issued.provider } token created</strong>
							<p>
								Copy it now. It will not be shown again. Any
								previous token has been revoked.
							</p>
							<input
								aria-label={ `${ issued.provider } MCP token` }
								value={ issued.token }
								readOnly
								autoComplete="off"
								onFocus={ ( event ) => event.target.select() }
								style={ {
									width: '100%',
									padding: 10,
									marginBottom: 10,
								} }
							/>
							<button
								className="button"
								onClick={ () => copy( issued.token, 'Token' ) }
							>
								Copy token
							</button>{ ' ' }
							<button
								className="button"
								onClick={ () => setIssued( null ) }
							>
								Dismiss token
							</button>
						</div>
					) }
					<p role="status" aria-live="polite">
						{ copied }
					</p>
					{ settings.providers.map( ( provider ) => (
						<div
							key={ provider.id }
							style={ {
								padding: '16px 0',
								borderTop: '1px solid #e5e7eb',
							} }
						>
							<h3>{ provider.label }</h3>
							<p>
								{ provider.configured
									? `Token configured · Created ${ new Date( provider.created ).toLocaleString() }`
									: 'No token configured' }
							</p>
							<button
								className="button button-primary"
								disabled={
									Boolean( busy ) ||
									settings.status?.ready === false
								}
								onClick={ () => update( provider, 'generate' ) }
							>
								{ ( () => {
									if ( busy === provider.id ) {
										return 'Updating…';
									}
									if ( provider.configured ) {
										return 'Replace token';
									}
									return 'Generate token';
								} )() }
							</button>{ ' ' }
							<button
								className="button"
								disabled={
									Boolean( busy ) || ! provider.configured
								}
								onClick={ () => update( provider, 'revoke' ) }
							>
								Revoke token
							</button>
						</div>
					) ) }
					<p>
						<a
							href={ settings.guide_url }
							target="_blank"
							rel="noreferrer"
						>
							Connection guide and provider examples
						</a>
					</p>
					<p>Read-only tools: { settings.tools.join( ', ' ) }.</p>
					<p>
						OAuth-only chat connectors require an additional OAuth
						gateway.
					</p>
				</Fragment>
			) }
		</section>
	);
}
