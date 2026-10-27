/** @jsx createElement */
import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import { prepareAttachments } from './attachment-files.mjs';
import { __, sprintf } from '@wordpress/i18n';

const base = '/ohmylms/v1/admin-assistant';
const api = ( suffix = '', data ) =>
	window.wp.apiFetch( {
		path: base + suffix,
		...( data === undefined ? {} : { method: 'POST', data } ),
	} );

export default function AssistantPanel( { onClose, active } ) {
	const [ status, setStatus ] = useState( null );
	const [ conversation, setConversation ] = useState( null );
	const [ attachments, setAttachments ] = useState( [] );
	const [ uploading, setUploading ] = useState( '' );
	const [ dragging, setDragging ] = useState( false );
	const fileInput = useRef( null );
	const [ text, setText ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ config, setConfig ] = useState( false );
	const [ model, setModel ] = useState( '' );
	const [ key, setKey ] = useState( '' );
	const [ enabled, setEnabled ] = useState( false );
	const close = useRef( null );
	const log = useRef( null );
	async function refresh() {
		const result = await api();
		setStatus( result );
		setModel( result.settings.model );
		setEnabled( result.settings.enabled );
	}
	async function run( action ) {
		setBusy( true );
		setError( '' );
		try {
			await action();
		} catch ( failure ) {
			setError(
				failure.message ||
					__(
						'Request failed. Refresh the status before retrying.',
						'ohmylms'
					)
			);
		} finally {
			setBusy( false );
		}
	}
	useEffect( () => {
		run( refresh );
	}, [] );
	useEffect( () => {
		if ( active ) {
			close.current?.focus();
		}
	}, [ active ] );
	useEffect( () => {
		const escape = ( event ) => {
			if ( active && event.key === 'Escape' ) {
				onClose();
			}
		};
		document.addEventListener( 'keydown', escape );
		return () => document.removeEventListener( 'keydown', escape );
	}, [ onClose, active ] );
	useEffect( () => {
		log.current?.scrollTo( 0, log.current.scrollHeight );
	}, [ conversation, error ] );

	async function send( event ) {
		event.preventDefault();
		await run( async () => {
			const ids = [];
			for ( const attachment of attachments ) {
				let uploaded = attachment.uploaded;
				if ( ! uploaded ) {
					setUploading( attachment.file.name );
					const form = new FormData();
					form.append( 'file', attachment.file );
					uploaded = await window.wp.apiFetch( {
						path: base + '/attachments',
						method: 'POST',
						body: form,
					} );
					setAttachments( ( current ) =>
						current.map( ( item ) =>
							item.id === attachment.id
								? { ...item, uploaded }
								: item
						)
					);
				}
				ids.push( uploaded.id );
			}
			setUploading( '' );
			setConversation(
				await api( '/message', {
					message: text,
					attachments: ids,
					conversation_id: conversation?.conversation_id || '',
					context: window.location.hash,
				} )
			);
			setText( '' );
			setAttachments( [] );
		} );
		setUploading( '' );
	}
	const pending = conversation?.pending;
	function addFiles( files ) {
		if ( busy || pending ) {
			return;
		}
		const result = prepareAttachments(
			attachments,
			Array.from( files ),
			status?.attachments
		);
		setAttachments( result.attachments );
		setError( result.error );
	}
	async function removeAttachment( attachment ) {
		setAttachments( ( current ) =>
			current.filter( ( item ) => item.id !== attachment.id )
		);
		if ( attachment.uploaded ) {
			await run( () =>
				window.wp.apiFetch( {
					path: base + '/attachments/' + attachment.uploaded.id,
					method: 'DELETE',
				} )
			);
		}
	}
	return (
		<div
			id="oml-assistant-panel"
			role="dialog"
			aria-modal="false"
			aria-labelledby="oml-assistant-title"
			className={ dragging ? 'is-dragging-files' : '' }
			onDragOver={ ( event ) => {
				if ( event.dataTransfer.types.includes( 'Files' ) ) {
					event.preventDefault();
					setDragging( true );
				}
			} }
			onDragLeave={ ( event ) => {
				if ( ! event.currentTarget.contains( event.relatedTarget ) ) {
					setDragging( false );
				}
			} }
			onDrop={ ( event ) => {
				event.preventDefault();
				setDragging( false );
				addFiles( event.dataTransfer.files );
			} }
		>
			<header>
				<div>
					<strong id="oml-assistant-title">
						{ __( 'OhMyLMS assistant', 'ohmylms' ) }
					</strong>
					<small>
						{ __( 'Administrator workspace', 'ohmylms' ) }
					</small>
				</div>
				<button
					ref={ close }
					type="button"
					onClick={ onClose }
					aria-label={ __( 'Close assistant', 'ohmylms' ) }
				>
					×
				</button>
			</header>
			<nav aria-label={ __( 'Chat settings', 'ohmylms' ) }>
				<button
					type="button"
					aria-expanded={ config }
					onClick={ () => setConfig( ! config ) }
				>
					{ __( 'Connection', 'ohmylms' ) }
				</button>
			</nav>
			<div className="oml-assistant-body" ref={ log }>
				{ ! status && (
					<p role="status">
						{ __( 'Loading connection…', 'ohmylms' ) }
					</p>
				) }
				{ config && (
					<form
						className="oml-assistant-card"
						onSubmit={ ( event ) => {
							event.preventDefault();
							run( async () => {
								await api( '/settings', {
									model,
									key,
									enabled,
								} );
								setKey( '' );
								await refresh();
							} );
						} }
					>
						<h3>{ __( 'OpenAI connection', 'ohmylms' ) }</h3>
						<p>
							{ __(
								'An existing OpenAI tutor connection can be reused. Keys stay on the server. Do not enter credentials in chat.',
								'ohmylms'
							) }
						</p>
						<label htmlFor="oml-assistant-enabled">
							<input
								id="oml-assistant-enabled"
								type="checkbox"
								checked={ enabled }
								onChange={ ( event ) =>
									setEnabled( event.target.checked )
								}
							/>{ ' ' }
							{ __( 'Enable administrator chat', 'ohmylms' ) }
						</label>
						<label htmlFor="oml-assistant-model">
							{ __( 'Model ID', 'ohmylms' ) }
							<input
								value={ model }
								id="oml-assistant-model"
								onChange={ ( event ) =>
									setModel( event.target.value )
								}
								autoComplete="off"
							/>
						</label>
						<label htmlFor="oml-assistant-key">
							{ __(
								'API key (leave blank to keep the current key)',
								'ohmylms'
							) }
							<input
								type="password"
								id="oml-assistant-key"
								value={ key }
								onChange={ ( event ) =>
									setKey( event.target.value )
								}
								autoComplete="new-password"
							/>
						</label>
						<p>
							{ status?.settings.has_key
								? __(
										'A server credential is available.',
										'ohmylms'
									)
								: __( 'No API key configured.', 'ohmylms' ) }
						</p>
						<button disabled={ busy } type="submit">
							{ __( 'Save connection', 'ohmylms' ) }
						</button>
					</form>
				) }
				<>
					{ status && ! status.settings.configured && (
						<p>
							{ __(
								'Open Connection to enable chat and choose an OpenAI model.',
								'ohmylms'
							) }
						</p>
					) }
					{ status && ! status.mcp.ready && (
						<p>
							{ __(
								'LMS tools are unavailable. Enable and check MCP in',
								'ohmylms'
							) }{ ' ' }
							<a href="#/integrations">
								{ __( 'Add-ons', 'ohmylms' ) }
							</a>
							.
						</p>
					) }
					{ ! conversation && (
						<p>
							{ __(
								'Ask about courses, lessons, syllabuses or shared skills. New draft lessons and shared skills require your review before saving.',
								'ohmylms'
							) }
						</p>
					) }
					<div role="log" aria-live="polite">
						{ conversation?.messages.map( ( message, index ) => (
							<article
								className={ `oml-assistant-message is-${ message.role }` }
								key={ index }
							>
								<strong>
									{ message.role === 'user'
										? __( 'You', 'ohmylms' )
										: __( 'Assistant', 'ohmylms' ) }
								</strong>
								<p>{ message.text }</p>
								{ message.attachments?.map(
									( file, fileIndex ) => (
										<small key={ fileIndex }>
											📎 { file.name }
										</small>
									)
								) }
							</article>
						) ) }
					</div>
					{ pending && (
						<div className="oml-assistant-card">
							<h3>
								{ __( 'Review proposed action', 'ohmylms' ) }
							</h3>
							<strong>
								{ pending.name === 'save_lesson'
									? __( 'Create draft lesson', 'ohmylms' )
									: __( 'Create shared skill', 'ohmylms' ) }
							</strong>
							{ Object.entries( pending.arguments ).map(
								( [ name, value ] ) => (
									<div key={ name }>
										<b>{ name }</b>
										<pre>{ String( value ) }</pre>
									</div>
								)
							) }
							{ [
								[ 'approve', __( 'Create now', 'ohmylms' ) ],
								[ 'reject', __( 'Cancel action', 'ohmylms' ) ],
							].map( ( [ decision, label ] ) => (
								<button
									type="button"
									key={ decision }
									disabled={ busy }
									onClick={ () =>
										run( async () =>
											setConversation(
												await api( '/message', {
													conversation_id:
														conversation.conversation_id,
													action_id: pending.call_id,
													decision,
												} )
											)
										)
									}
								>
									{ label }
								</button>
							) ) }
						</div>
					) }
					{ conversation?.receipts.map( ( receipt, index ) => (
						<p key={ index } role="status">
							{ receipt.name }:{ ' ' }
							{ receipt.success
								? __( 'Saved by the LMS tool.', 'ohmylms' )
								: __( 'Action was not saved.', 'ohmylms' ) }
						</p>
					) ) }
					{ conversation?.error && (
						<p role="alert">{ conversation.error }</p>
					) }
				</>
				{ error && <p role="alert">{ error }</p> }
			</div>
			<footer>
				<form onSubmit={ send }>
					{ attachments.length > 0 && (
						<ul
							className="oml-assistant-attachments"
							aria-label={ __( 'Attached files', 'ohmylms' ) }
						>
							{ attachments.map( ( attachment ) => (
								<li key={ attachment.id }>
									<span>
										📎 { attachment.file.name }{ ' ' }
										<small>
											{ Math.max(
												1,
												Math.round(
													attachment.file.size / 1024
												)
											) }{ ' ' }
											KB
										</small>
									</span>
									<button
										type="button"
										disabled={ busy }
										aria-label={ sprintf(
											/* translators: %s: filename. */ __(
												'Remove %s',
												'ohmylms'
											),
											attachment.file.name
										) }
										onClick={ () =>
											removeAttachment( attachment )
										}
									>
										×
									</button>
								</li>
							) ) }
						</ul>
					) }
					<input
						ref={ fileInput }
						hidden
						type="file"
						multiple
						accept={ (
							status?.attachments?.extensions || [
								'pdf',
								'png',
								'jpg',
								'jpeg',
								'webp',
								'txt',
								'md',
								'csv',
								'json',
								'docx',
								'xlsx',
								'pptx',
							]
						)
							.map( ( extension ) => '.' + extension )
							.join( ',' ) }
						onChange={ ( event ) => {
							addFiles( event.target.files );
							event.target.value = '';
						} }
						disabled={ busy || !! pending }
					/>
					<label
						className="screen-reader-text"
						htmlFor="oml-assistant-input"
					>
						{ __( 'Your request', 'ohmylms' ) }
					</label>
					<textarea
						id="oml-assistant-input"
						maxLength={ 4000 }
						value={ text }
						onChange={ ( event ) => setText( event.target.value ) }
						placeholder={ __(
							'Ask about your LMS or drop files here…',
							'ohmylms'
						) }
						disabled={ busy || !! pending }
					/>
					<small className="oml-assistant-upload-help">
						{ sprintf(
							/* translators: %s: file size in MB. */ __(
								'Up to 3 files · %s MB each. Files are sent to OpenAI when you press Send.',
								'ohmylms'
							),
							(
								( status?.attachments?.max_bytes || 2097152 ) /
								1048576
							).toFixed( 0 )
						) }
					</small>
					{ uploading && (
						<p role="status">
							{ sprintf(
								/* translators: %s: filename. */ __(
									'Uploading %s…',
									'ohmylms'
								),
								uploading
							) }
						</p>
					) }
					<div className="oml-assistant-actions">
						<button
							type="button"
							onClick={ () => fileInput.current?.click() }
							disabled={
								busy || !! pending || attachments.length >= 3
							}
						>
							{ __( 'Attach files', 'ohmylms' ) }
						</button>
						<button
							type="submit"
							disabled={
								busy ||
								( ! text.trim() && ! attachments.length ) ||
								! status?.settings.configured ||
								!! pending
							}
						>
							{ busy
								? __( 'Working…', 'ohmylms' )
								: __( 'Send', 'ohmylms' ) }
						</button>
						<button
							type="button"
							disabled={ busy }
							onClick={ () => run( refresh ) }
							aria-label={ __(
								'Refresh connection status',
								'ohmylms'
							) }
						>
							↻
						</button>
						<button
							type="button"
							disabled={ busy || !! pending }
							onClick={ () => setConversation( null ) }
						>
							{ __( 'New chat', 'ohmylms' ) }
						</button>
					</div>
				</form>
			</footer>
		</div>
	);
}
