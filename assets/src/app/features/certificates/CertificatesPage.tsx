import { Badge } from '../../../ui/Badge';
import {
	Button,
	Dashicon,
	Modal,
	Notice,
	TextControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router-dom';
import { EngagementToolbar } from '../../components/EngagementToolbar';
import { Pagination } from '../../components/Pagination';
import { useAdminPlace } from '../../routing/place';
import {
	PageTitleAction,
	PageTitleActions,
} from '../../layout/PageTitleActions';
import { normalizeApiError } from '../../lib/api';
import {
	createCertificate,
	deleteCertificate,
	duplicateCertificate,
} from './api';
import { useCertificates } from './useCertificates';
import type { Certificate, Option } from './types';

/** Former `list` value of the issued certificates, now their own section. */
const ISSUED = 'issued';

function statusFilters(): Option[] {
	return [
		{ value: 'all', label: __( 'Any status', 'ohmylms' ) },
		{ value: 'publish', label: __( 'Published', 'ohmylms' ) },
		{ value: 'draft', label: __( 'Draft', 'ohmylms' ) },
	];
}

type Dialog = { kind: 'create' } | { kind: 'delete'; item: Certificate } | null;

/**
 * Certificates screen: the template list. The old `?list=issued` opens `/certificates/issued` with
 * the rest of the query.
 */
export default function CertificatesPage() {
	const [ params ] = useSearchParams();
	if ( ISSUED === params.get( 'list' ) ) {
		const rest = new URLSearchParams( params );
		rest.delete( 'list' );
		const search = rest.toString();
		return (
			<Navigate
				replace
				to={ `/certificates/issued${ search ? `?${ search }` : '' }` }
			/>
		);
	}
	return <TemplatesList />;
}

/**
 * Certificate template list (`#/certificates`): paged, searchable, with create, duplicate and
 * confirmed delete. The editor lives at `#/certificates/<id>`.
 */
function TemplatesList() {
	const list = useCertificates();
	const place = useAdminPlace();
	const navigate = useNavigate();
	const [ query, setQuery ] = useState( list.searchTerm );
	useEffect( () => setQuery( list.searchTerm ), [ list.searchTerm ] );
	const [ dialog, setDialog ] = useState< Dialog >( null );
	const [ name, setName ] = useState( '' );
	const [ nameError, setNameError ] = useState< string | null >( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );
	const [ message, setMessage ] = useState< string | null >( null );
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
	function create() {
		const value = name.trim();
		if ( ! value || Array.from( value ).length > 200 ) {
			setNameError(
				__( 'Enter a name of 1 to 200 characters.', 'ohmylms' )
			);
			return;
		}
		setNameError( null );
		void run( async ( signal ) => {
			const created = await createCertificate( value, signal );
			if ( signal.aborted ) {
				return;
			}
			setDialog( null );
			navigate( `/certificates/${ created.id }` );
		} );
	}
	function duplicate( item: Certificate ) {
		void run( async ( signal ) => {
			await duplicateCertificate( item.id, signal );
			if ( signal.aborted ) {
				return;
			}
			setMessage(
				sprintf(
					/* translators: %s: certificate template name. */
					__( 'Duplicated %s as a draft.', 'ohmylms' ),
					item.name
				)
			);
			list.reload();
		} );
	}
	function remove( item: Certificate ) {
		void run( async ( signal ) => {
			await deleteCertificate( item.id, signal );
			if ( signal.aborted ) {
				return;
			}
			setDialog( null );
			setMessage( __( 'Template deleted.', 'ohmylms' ) );
			list.afterDelete();
		} );
	}
	function close() {
		if ( ! busy ) {
			setDialog( null );
			setError( null );
			setNameError( null );
		}
	}

	return (
		<>
			{ ! place && (
				<PageTitleActions>
					<PageTitleAction to="/certificates/issued">
						{ __( 'Issued certificates', 'ohmylms' ) }
					</PageTitleAction>
				</PageTitleActions>
			) }
			<EngagementToolbar
				searchLabel={ __( 'Search certificate templates', 'ohmylms' ) }
				search={ query }
				onSearchChange={ setQuery }
				onSearch={ () => list.search( query ) }
				status={ list.status }
				statusOptions={ statusFilters() }
				onStatusChange={ list.setStatus }
				count={
					list.loading || list.error
						? ''
						: sprintf(
								/* translators: %d: total template count. */
								_n(
									'%d template found.',
									'%d templates found.',
									list.data.total,
									'ohmylms'
								),
								list.data.total
							)
				}
			>
				<Button
					variant="primary"
					disabled={ busy }
					onClick={ () => {
						setName( '' );
						setError( null );
						setDialog( { kind: 'create' } );
					} }
				>
					{ __( 'Add template', 'ohmylms' ) }
				</Button>
			</EngagementToolbar>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			{ error && ! dialog && (
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
				<p role="status">
					{ __( 'Loading certificate templates…', 'ohmylms' ) }
				</p>
			) }
			{ ! list.loading && ! list.error && (
				<>
					{ list.data.items.length ? (
						<ul
							className="oml-engagement-grid"
							aria-label={ __(
								'Certificate templates',
								'ohmylms'
							) }
						>
							{ list.data.items.map( ( item ) => {
								const published = 'publish' === item.status;
								const editor = `/certificates/${ item.id }`;
								return (
									<li
										className="oml-engagement-card"
										key={ item.id }
									>
										<div className="oml-certificate-thumbnail">
											{ item.background_url ? (
												<img
													src={ item.background_url }
													alt=""
													loading="lazy"
												/>
											) : (
												<Dashicon
													icon="awards"
													aria-hidden="true"
												/>
											) }
										</div>
										<div className="oml-engagement-card-heading">
											<h2>
												<Link to={ editor }>
													{ item.name }
												</Link>
											</h2>
											<Badge
												tone={
													published
														? 'success'
														: undefined
												}
											>
												{ published
													? __(
															'Published',
															'ohmylms'
														)
													: __( 'Draft', 'ohmylms' ) }
											</Badge>
										</div>
										<p className="oml-engagement-card-meta">
											{ sprintf(
												/* translators: 1: orientation, 2: paper size. */
												__( '%1$s, %2$s', 'ohmylms' ),
												'portrait' === item.orientation
													? __(
															'Portrait',
															'ohmylms'
														)
													: __(
															'Landscape',
															'ohmylms'
														),
												item.size.toUpperCase()
											) }
											<span>
												{ sprintf(
													/* translators: %d: number of courses using the template. */
													_n(
														'%d course',
														'%d courses',
														item.course_count,
														'ohmylms'
													),
													item.course_count
												) }
											</span>
										</p>
										<div className="oml-engagement-card-actions">
											<Link
												className="button button-secondary"
												to={ editor }
												aria-label={ sprintf(
													/* translators: %s: template name. */
													__( 'Edit %s', 'ohmylms' ),
													item.name
												) }
											>
												{ __( 'Edit', 'ohmylms' ) }
											</Link>
											<Button
												variant="tertiary"
												disabled={ busy }
												aria-label={ sprintf(
													/* translators: %s: template name. */
													__(
														'Duplicate %s',
														'ohmylms'
													),
													item.name
												) }
												onClick={ () =>
													duplicate( item )
												}
											>
												{ __( 'Duplicate', 'ohmylms' ) }
											</Button>
											<Button
												variant="tertiary"
												isDestructive
												disabled={ busy }
												aria-label={ sprintf(
													/* translators: %s: template name. */
													__(
														'Delete %s',
														'ohmylms'
													),
													item.name
												) }
												onClick={ () => {
													setError( null );
													setDialog( {
														kind: 'delete',
														item,
													} );
												} }
											>
												{ __( 'Delete', 'ohmylms' ) }
											</Button>
										</div>
									</li>
								);
							} ) }
						</ul>
					) : (
						<p className="oml-engagement-panel">
							{ __(
								'No certificate templates found.',
								'ohmylms'
							) }
						</p>
					) }
					{ list.data.totalPages > 1 && (
						<div className="oml-engagement-pagination">
							<Pagination
								page={ list.page }
								pages={ list.data.totalPages }
								busy={ busy }
								onChange={ list.setPage }
								href={ list.pageHref }
							/>
						</div>
					) }
				</>
			) }
			{ dialog?.kind === 'create' && (
				<Modal
					title={ __( 'Add certificate template', 'ohmylms' ) }
					onRequestClose={ close }
					shouldCloseOnClickOutside={ false }
				>
					<form
						onSubmit={ ( event ) => {
							event.preventDefault();
							create();
						} }
						noValidate
					>
						{ error && (
							<Notice status="error" isDismissible={ false }>
								{ error }
							</Notice>
						) }
						<TextControl
							label={ __( 'Template name', 'ohmylms' ) }
							value={ name }
							maxLength={ 200 }
							required
							disabled={ busy }
							aria-invalid={ nameError ? true : undefined }
							aria-describedby={
								nameError ? 'oml-cert-create-error' : undefined
							}
							onChange={ setName }
							help={ __(
								'A default layout is created; you can change everything in the editor.',
								'ohmylms'
							) }
						/>
						{ nameError && (
							<p id="oml-cert-create-error" role="alert">
								{ nameError }
							</p>
						) }
						<Button
							type="submit"
							variant="primary"
							disabled={ busy }
							isBusy={ busy }
						>
							{ __( 'Create template', 'ohmylms' ) }
						</Button>{ ' ' }
						<Button
							variant="tertiary"
							disabled={ busy }
							onClick={ close }
						>
							{ __( 'Cancel', 'ohmylms' ) }
						</Button>
					</form>
				</Modal>
			) }
			{ dialog?.kind === 'delete' && (
				<Modal
					title={ __( 'Delete certificate template', 'ohmylms' ) }
					onRequestClose={ close }
					shouldCloseOnClickOutside={ false }
				>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					<p>
						{ sprintf(
							/* translators: %s: template name. */
							__(
								'Permanently delete %s? Courses that use it will no longer have a certificate template.',
								'ohmylms'
							),
							dialog.item.name
						) }
					</p>
					<Button
						variant="primary"
						isDestructive
						disabled={ busy }
						isBusy={ busy }
						onClick={ () => remove( dialog.item ) }
					>
						{ __( 'Delete template', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ busy }
						onClick={ close }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}
