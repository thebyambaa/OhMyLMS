import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import { getMcp, manageMcp, type McpProvider, type McpSnapshot } from './mcp';
import { describeFailure } from './model';
import type { FailureView } from './types';

/**
 * MCP tab: the server URL and one access token per AI client. A new token is shown once, right
 * after it is generated; the server keeps only its hash.
 *
 * @param props        Component props.
 * @param props.active Whether the tab is selected (loads on first selection).
 */
export function McpForm( { active }: { active: boolean } ) {
	const [ requested, setRequested ] = useState( active );
	const [ version, setVersion ] = useState( 0 );
	const [ snapshot, setSnapshot ] = useState< McpSnapshot | null >( null );
	const [ failure, setFailure ] = useState< FailureView | null >( null );
	const [ busy, setBusy ] = useState< string | null >( null );
	const [ token, setToken ] = useState< {
		provider: string;
		value: string;
	} | null >( null );
	const [ problem, setProblem ] = useState< string | null >( null );
	const mounted = useRef( true );
	useEffect( () => {
		mounted.current = true;
		return () => {
			mounted.current = false;
		};
	}, [] );

	useEffect( () => {
		if ( active ) {
			setRequested( true );
		}
	}, [ active ] );

	useEffect( () => {
		if ( ! requested ) {
			return undefined;
		}
		const controller = new AbortController();
		setFailure( null );
		getMcp( controller.signal ).then(
			( loaded ) => {
				if ( ! controller.signal.aborted ) {
					setSnapshot( loaded );
				}
			},
			( error: unknown ) => {
				if ( ! controller.signal.aborted ) {
					setFailure( describeFailure( normalizeApiError( error ) ) );
				}
			}
		);
		return () => controller.abort();
	}, [ requested, version ] );

	async function manage(
		provider: McpProvider,
		action: 'generate' | 'revoke'
	) {
		setBusy( `${ provider.id }:${ action }` );
		setProblem( null );
		setToken( null );
		try {
			const result = await manageMcp( provider.id, action );
			if ( mounted.current ) {
				setSnapshot( result.snapshot );
				if ( result.token ) {
					setToken( {
						provider: provider.label,
						value: result.token,
					} );
				}
			}
		} catch ( error ) {
			if ( mounted.current ) {
				setProblem(
					describeFailure( normalizeApiError( error ) ).message
				);
			}
		} finally {
			if ( mounted.current ) {
				setBusy( null );
			}
		}
	}

	if ( failure ) {
		return (
			<Notice status="error" isDismissible={ false }>
				<p>{ failure.message }</p>
				{ ! failure.permission && (
					<Button
						variant="secondary"
						onClick={ () => setVersion( ( n ) => n + 1 ) }
					>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				) }
			</Notice>
		);
	}
	if ( ! snapshot ) {
		return (
			<p role="status">
				<Spinner /> { __( 'Loading MCP settings…', 'ohmylms' ) }
			</p>
		);
	}

	return (
		<section aria-label={ __( 'MCP', 'ohmylms' ) }>
			<h3>{ __( 'MCP', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'Let AI assistants read LMS content and map syllabuses through the Model Context Protocol. Each client gets its own token; revoke it to cut access.',
					'ohmylms'
				) }
			</p>
			{ ! snapshot.ready && snapshot.message && (
				<Notice status="warning" isDismissible={ false }>
					{ snapshot.message }
				</Notice>
			) }
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Server URL', 'ohmylms' ) }
				value={ snapshot.serverUrl }
				readOnly
				onChange={ () => undefined }
			/>
			{ token && (
				<Notice status="success" isDismissible={ false }>
					<p>
						{ sprintf(
							/* translators: %s: AI client name. */
							__(
								'New token for %s. Copy it now: it will not be shown again.',
								'ohmylms'
							),
							token.provider
						) }
					</p>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Access token', 'ohmylms' ) }
						value={ token.value }
						readOnly
						onChange={ () => undefined }
					/>
				</Notice>
			) }
			{ problem && (
				<Notice status="error" isDismissible={ false }>
					{ problem }
				</Notice>
			) }
			<table className="widefat striped">
				<thead>
					<tr>
						<th scope="col">{ __( 'Client', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'Token', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'Actions', 'ohmylms' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ snapshot.providers.map( ( provider ) => (
						<tr key={ provider.id }>
							<th scope="row">{ provider.label }</th>
							<td>
								{ provider.configured
									? sprintf(
											/* translators: %s: creation date. */
											__( 'Active since %s', 'ohmylms' ),
											provider.created ?? '—'
										)
									: __( 'No token', 'ohmylms' ) }
							</td>
							<td>
								<Button
									variant="secondary"
									onClick={ () =>
										void manage( provider, 'generate' )
									}
									isBusy={
										`${ provider.id }:generate` === busy
									}
									disabled={
										null !== busy || ! snapshot.ready
									}
									accessibleWhenDisabled
									aria-label={
										provider.configured
											? sprintf(
													/* translators: %s: AI client name. */
													__(
														'Replace the %s token',
														'ohmylms'
													),
													provider.label
												)
											: sprintf(
													/* translators: %s: AI client name. */
													__(
														'Generate a %s token',
														'ohmylms'
													),
													provider.label
												)
									}
								>
									{ provider.configured
										? __( 'Replace token', 'ohmylms' )
										: __( 'Generate token', 'ohmylms' ) }
								</Button>{ ' ' }
								{ provider.configured && (
									<Button
										variant="tertiary"
										isDestructive
										onClick={ () =>
											void manage( provider, 'revoke' )
										}
										isBusy={
											`${ provider.id }:revoke` === busy
										}
										disabled={ null !== busy }
										accessibleWhenDisabled
										aria-label={ sprintf(
											/* translators: %s: AI client name. */
											__(
												'Revoke the %s token',
												'ohmylms'
											),
											provider.label
										) }
									>
										{ __( 'Revoke', 'ohmylms' ) }
									</Button>
								) }
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
			{ snapshot.guideUrl && (
				<p>
					<a
						href={ snapshot.guideUrl }
						target="_blank"
						rel="noreferrer"
					>
						{ __( 'Connection guide', 'ohmylms' ) }
					</a>
				</p>
			) }
		</section>
	);
}
