import { Table } from '../../../ui/Table';
import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import { deleteWebhook, rotateSecret, saveWebhook, sendTest } from './api';
import { Deliveries } from './Deliveries';
import { Pagination } from '../../components/Pagination';
import { useWebhooks } from './useWebhooks';
import { WebhookEditor } from './WebhookEditor';
import type { Webhook, WebhookInput } from './types';

type Confirmation = { kind: 'delete' | 'rotate' | 'test'; item: Webhook };

export default function WebhooksPage() {
	const list = useWebhooks();
	const [ query, setQuery ] = useState( '' );
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
			<h2>{ __( 'Webhooks', 'ohmylms' ) }</h2>
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
			<Button
				variant="primary"
				disabled={
					busy || list.loading || ! list.options?.triggers.length
				}
				onClick={ () => {
					setError( null );
					setEditor( { item: null } );
				} }
			>
				{ __( 'Add webhook', 'ohmylms' ) }
			</Button>
			{ list.options && ! list.options.triggers.length && (
				<p role="status">
					{ __(
						'No webhook events are available. You can still inspect or delete existing webhooks.',
						'ohmylms'
					) }
				</p>
			) }
			<form
				role="search"
				aria-label={ __( 'Search webhooks', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					list.search( query );
				} }
			>
				<TextControl
					label={ __( 'Search webhooks', 'ohmylms' ) }
					value={ query }
					onChange={ setQuery }
				/>
				<Button
					type="submit"
					variant="secondary"
					disabled={ busy || list.loading }
				>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
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
			{ ! list.loading &&
				! list.error &&
				( list.data.items.length ? (
					<>
						<p role="status">
							{ sprintf(
								/* translators: %d: total webhook count. */
								_n(
									'%d webhook found.',
									'%d webhooks found.',
									list.data.total,
									'ohmylms'
								),
								list.data.total
							) }
						</p>
						<Table
							caption={ __( 'Configured webhooks', 'ohmylms' ) }
							striped
						>
							<thead>
								<tr>
									{ [
										__( 'Name', 'ohmylms' ),
										__( 'Endpoint', 'ohmylms' ),
										__( 'Event', 'ohmylms' ),
										__( 'Status', 'ohmylms' ),
										__( 'Actions', 'ohmylms' ),
									].map( ( title ) => (
										<th key={ title } scope="col">
											{ title }
										</th>
									) ) }
								</tr>
							</thead>
							<tbody>
								{ list.data.items.map( ( item ) => (
									<tr key={ item.id }>
										<th scope="row">{ item.name }</th>
										<td>{ item.webhook_url }</td>
										<td>
											{ list.options?.triggers.find(
												( option ) =>
													option.value ===
													item.trigger_event
											)?.label ?? item.trigger_event }
										</td>
										<td>
											{ 'active' === item.status
												? __( 'Active', 'ohmylms' )
												: __( 'Inactive', 'ohmylms' ) }
										</td>
										<td>
											<Button
												variant="link"
												disabled={ busy }
												onClick={ () => {
													setError( null );
													setEditor( { item } );
												} }
												aria-label={ sprintf(
													/* translators: %s: webhook name. */
													__( 'Edit %s', 'ohmylms' ),
													item.name
												) }
											>
												{ __( 'Edit', 'ohmylms' ) }
											</Button>{ ' ' }
											<Button
												variant="link"
												disabled={ busy }
												onClick={ () =>
													setDeliveries( item )
												}
												aria-label={ sprintf(
													/* translators: %s: webhook name. */
													__(
														'Deliveries for %s',
														'ohmylms'
													),
													item.name
												) }
											>
												{ __(
													'Deliveries',
													'ohmylms'
												) }
											</Button>{ ' ' }
											{ (
												[
													'test',
													'rotate',
													'delete',
												] as const
											 ).map( ( kind ) => (
												<Button
													key={ kind }
													variant="link"
													isDestructive={
														'delete' === kind
													}
													disabled={ busy }
													onClick={ () => {
														setError( null );
														setConfirmation( {
															kind,
															item,
														} );
													} }
													aria-label={ `${ titles[ kind ] }: ${ item.name }` }
												>
													{ titles[ kind ] }
												</Button>
											) ) }
										</td>
									</tr>
								) ) }
							</tbody>
						</Table>
						<Pagination
							page={ list.page }
							pages={ list.data.totalPages }
							busy={ busy || list.loading }
							onChange={ list.setPage }
						/>
					</>
				) : (
					<p>{ __( 'No webhooks found.', 'ohmylms' ) }</p>
				) ) }
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
