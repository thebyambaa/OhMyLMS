import { ListTable } from '../../../ui/ListTable';
import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	PageTitleAction,
	PageTitleActions,
} from '../../layout/PageTitleActions';
import { normalizeApiError } from '../../lib/api';
import { deleteWebhook, rotateSecret, saveWebhook, sendTest } from './api';
import { Deliveries } from './Deliveries';
import { useWebhooks } from './useWebhooks';
import { WebhookEditor } from './WebhookEditor';
import type { Webhook, WebhookInput } from './types';

type Confirmation = { kind: 'delete' | 'rotate' | 'test'; item: Webhook };

export default function WebhooksPage() {
	const list = useWebhooks();
	const [ query, setQuery ] = useState( list.searchTerm );
	useEffect( () => setQuery( list.searchTerm ), [ list.searchTerm ] );
	const [ editor, setEditor ] = useState< { item: Webhook | null } | null >(
		null
	);
	const [ confirmation, setConfirmation ] = useState< Confirmation | null >(
		null
	);
	const [ deliveries, setDeliveries ] = useState< Webhook | null >( null );
	const [ secret, setSecret ] = useState< string | null >( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );
	const [ message, setMessage ] = useState< string | null >( null );
	const [ messageStatus, setMessageStatus ] = useState< 'success' | 'error' >(
		'success'
	);
	const operation = useRef< AbortController | null >( null );
	useEffect( () => () => operation.current?.abort(), [] );

	async function run( work: ( signal: AbortSignal ) => Promise< void > ) {
		if ( operation.current ) {
			return;
		}
		const controller = new AbortController();
		operation.current = controller;
		setBusy( true );
		setError( null );
		setMessage( null );
		setMessageStatus( 'success' );
		try {
			await work( controller.signal );
		} catch ( failure ) {
			if ( ! controller.signal.aborted ) {
				setError( normalizeApiError( failure ).message );
			}
		} finally {
			if ( ! controller.signal.aborted ) {
				setBusy( false );
				operation.current = null;
			}
		}
	}
	function save( value: WebhookInput ) {
		void run( async ( signal ) => {
			const result = await saveWebhook(
				value,
				editor?.item?.id ?? null,
				signal
			);
			if ( signal.aborted ) {
				return;
			}
			setEditor( null );
			setSecret( result.secret ?? null );
			setMessage( __( 'Webhook saved.', 'ohmylms' ) );
			list.reload();
		} );
	}
	function confirm() {
		if ( ! confirmation ) {
			return;
		}
		const { kind, item } = confirmation;
		void run( async ( signal ) => {
			if ( 'delete' === kind ) {
				await deleteWebhook( item.id, signal );
				if ( signal.aborted ) {
					return;
				}
				setMessage( __( 'Webhook deleted.', 'ohmylms' ) );
				list.afterDelete();
			} else if ( 'rotate' === kind ) {
				const result = await rotateSecret( item.id, signal );
				if ( signal.aborted ) {
					return;
				}
				setSecret( result.secret );
				setMessage( __( 'Signing secret replaced.', 'ohmylms' ) );
				list.reload();
			} else {
				const success = await sendTest( item.id, signal );
				if ( signal.aborted ) {
					return;
				}
				setMessageStatus( success ? 'success' : 'error' );
				setMessage(
					success
						? __( 'Test delivery succeeded.', 'ohmylms' )
						: __(
								'Test delivery failed. Open deliveries for details.',
								'ohmylms'
							)
				);
				list.reload();
			}
			setConfirmation( null );
		} );
	}
	const titles = {
		delete: __( 'Delete webhook', 'ohmylms' ),
		rotate: __( 'Replace signing secret', 'ohmylms' ),
		test: __( 'Send test delivery', 'ohmylms' ),
	};
	let confirmationText = '';
	if ( confirmation?.kind === 'test' ) {
		confirmationText = sprintf(
			/* translators: %s: webhook endpoint URL. */
			__(
				'Send a sample event to %s? This makes an HTTP request to that service.',
				'ohmylms'
			),
			confirmation.item.webhook_url
		);
	} else if ( confirmation?.kind === 'rotate' ) {
		confirmationText = __(
			'Replace this webhook’s signing secret? Update the receiving service with the new secret before sending more events.',
			'ohmylms'
		);
	} else if ( confirmation ) {
		confirmationText = sprintf(
			/* translators: %s: webhook name. */
			__( 'Permanently delete %s and its delivery history?', 'ohmylms' ),
			confirmation.item.name
		);
	}
	return (
		<>
			<PageTitleActions>
				<PageTitleAction
					disabled={
						busy || list.loading || ! list.options?.triggers.length
					}
					onClick={ () => {
						setError( null );
						setEditor( { item: null } );
					} }
				>
					{ __( 'Add webhook', 'ohmylms' ) }
				</PageTitleAction>
			</PageTitleActions>
			<p>
				{ __(
					'Send LMS events to your services and inspect delivery results.',
					'ohmylms'
				) }
			</p>
			<p>
				{ __(
					'Automatic event delivery follows the Webhooks setting in Add-ons.',
					'ohmylms'
				) }{ ' ' }
				<a href="admin.php?page=ohmylms#/integrations">
					{ __( 'Open Add-ons', 'ohmylms' ) }
				</a>
			</p>
			{ list.options && ! list.options.triggers.length && (
				<p role="status">
					{ __(
						'No webhook events are available. You can still inspect or delete existing webhooks.',
						'ohmylms'
					) }
				</p>
			) }
			{ message && (
				<Notice
					status={ messageStatus }
					onRemove={ () => setMessage( null ) }
				>
					{ message }
				</Notice>
			) }
			{ error && ! editor && ! confirmation && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ list.error && (
				<Notice status="error" isDismissible={ false }>
					{ list.error }{ ' ' }
					<Button variant="link" onClick={ list.reload }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ list.loading && (
				<p role="status">{ __( 'Loading webhooks…', 'ohmylms' ) }</p>
			) }
			{ ! list.loading && ! list.error && (
				<ListTable< Webhook >
					caption={ __( 'Configured webhooks', 'ohmylms' ) }
					heading={ __( 'Webhooks list', 'ohmylms' ) }
					formLabel={ __( 'Search webhooks', 'ohmylms' ) }
					busy={ busy }
					rows={ list.data.items }
					rowKey={ ( item ) => item.id }
					emptyMessage={ __( 'No webhooks found.', 'ohmylms' ) }
					search={ {
						label: __( 'Search webhooks', 'ohmylms' ),
						submitLabel: __( 'Search', 'ohmylms' ),
						value: query,
						onChange: setQuery,
						onSubmit: () => list.search( query ),
					} }
					pagination={ {
						page: list.page,
						totalPages: list.data.totalPages,
						totalItems: list.data.total,
						itemsLabel: sprintf(
							/* translators: %d: total webhook count. */
							_n(
								'%d webhook found.',
								'%d webhooks found.',
								list.data.total,
								'ohmylms'
							),
							list.data.total
						),
						onChange: list.setPage,
						href: list.pageHref,
					} }
					columns={ [
						{
							id: 'name',
							label: __( 'Name', 'ohmylms' ),
							primary: true,
							render: ( item ) => (
								<strong className="row-title">
									{ item.name }
								</strong>
							),
						},
						{
							id: 'endpoint',
							label: __( 'Endpoint', 'ohmylms' ),
							render: ( item ) => item.webhook_url,
						},
						{
							id: 'event',
							label: __( 'Event', 'ohmylms' ),
							render: ( item ) =>
								list.options?.triggers.find(
									( option ) =>
										option.value === item.trigger_event
								)?.label ?? item.trigger_event,
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( item ) =>
								'active' === item.status
									? __( 'Active', 'ohmylms' )
									: __( 'Inactive', 'ohmylms' ),
						},
					] }
					rowActions={ ( item ) => [
						{
							key: 'edit',
							label: __( 'Edit', 'ohmylms' ),
							disabled: busy,
							ariaLabel: sprintf(
								/* translators: %s: webhook name. */
								__( 'Edit %s', 'ohmylms' ),
								item.name
							),
							onClick: () => {
								setError( null );
								setEditor( { item } );
							},
						},
						{
							key: 'deliveries',
							label: __( 'Deliveries', 'ohmylms' ),
							disabled: busy,
							ariaLabel: sprintf(
								/* translators: %s: webhook name. */
								__( 'Deliveries for %s', 'ohmylms' ),
								item.name
							),
							onClick: () => setDeliveries( item ),
						},
						...( [ 'test', 'rotate', 'delete' ] as const ).map(
							( kind ) => ( {
								key: kind,
								label: titles[ kind ],
								destructive: 'delete' === kind,
								disabled: busy,
								ariaLabel: `${ titles[ kind ] }: ${ item.name }`,
								onClick: () => {
									setError( null );
									setConfirmation( { kind, item } );
								},
							} )
						),
					] }
				/>
			) }
			{ editor && list.options && (
				<WebhookEditor
					item={ editor.item }
					options={ list.options }
					busy={ busy }
					error={ error }
					onClose={ () => setEditor( null ) }
					onSave={ save }
				/>
			) }
			{ confirmation && (
				<Modal
					title={ titles[ confirmation.kind ] }
					onRequestClose={ () => {
						if ( ! busy ) {
							setConfirmation( null );
						}
					} }
					shouldCloseOnClickOutside={ false }
				>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					<p>{ confirmationText }</p>
					<Button
						variant="primary"
						isDestructive={ 'delete' === confirmation.kind }
						disabled={ busy }
						isBusy={ busy }
						onClick={ confirm }
					>
						{ titles[ confirmation.kind ] }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ busy }
						onClick={ () => setConfirmation( null ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
			{ secret && (
				<Modal
					title={ __( 'Signing secret', 'ohmylms' ) }
					onRequestClose={ () => setSecret( null ) }
				>
					<p>
						{ __(
							'Shown once. Store this secret in the receiving service before closing.',
							'ohmylms'
						) }
					</p>
					<TextControl
						label={ __( 'New signing secret', 'ohmylms' ) }
						value={ secret }
						onChange={ setSecret }
						readOnly
					/>
					<Button
						variant="primary"
						onClick={ () => setSecret( null ) }
					>
						{ __( 'Done', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
			{ deliveries && (
				<Deliveries
					item={ deliveries }
					onClose={ () => setDeliveries( null ) }
				/>
			) }
		</>
	);
}
