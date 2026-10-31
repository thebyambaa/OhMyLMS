import { ListTable } from '../../../ui/ListTable';
import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link, useNavigate } from 'react-router-dom';
import { statusViews } from '../../components/listViews';
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

function statusFilters(): Option[] {
	return [
		{ value: 'all', label: __( 'All', 'ohmylms' ) },
		{ value: 'publish', label: __( 'Published', 'ohmylms' ) },
		{ value: 'draft', label: __( 'Draft', 'ohmylms' ) },
	];
}

type Dialog = { kind: 'create' } | { kind: 'delete'; item: Certificate } | null;

/**
 * Certificate template list (`#/certificates`): paged, searchable, with create, duplicate and
 * confirmed delete. The editor lives at `#/certificates/<id>`.
 */
export default function CertificatesPage() {
	const list = useCertificates();
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
			<PageTitleActions>
				<PageTitleAction
					disabled={ busy }
					onClick={ () => {
						setName( '' );
						setError( null );
						setDialog( { kind: 'create' } );
					} }
				>
					{ __( 'Add template', 'ohmylms' ) }
				</PageTitleAction>
			</PageTitleActions>
			<p>
				{ __(
					'Design certificate templates and assign them to courses.',
					'ohmylms'
				) }
			</p>
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
				<ListTable< Certificate >
					caption={ __( 'Certificate templates', 'ohmylms' ) }
					heading={ __( 'Certificate templates list', 'ohmylms' ) }
					formLabel={ __(
						'Search certificate templates',
						'ohmylms'
					) }
					busy={ busy }
					rows={ list.data.items }
					rowKey={ ( item ) => item.id }
					emptyMessage={ __(
						'No certificate templates found.',
						'ohmylms'
					) }
					views={ statusViews(
						statusFilters(),
						list.status,
						list.data.total,
						list.setStatus,
						list.statusHref
					) }
					search={ {
						label: __( 'Search certificate templates', 'ohmylms' ),
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
							/* translators: %d: total template count. */
							_n(
								'%d template found.',
								'%d templates found.',
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
								<strong>
									<Link
										className="row-title"
										to={ `/certificates/${ item.id }` }
									>
										{ item.name }
									</Link>
								</strong>
							),
						},
						{
							id: 'preview',
							label: __( 'Preview', 'ohmylms' ),
							render: ( item ) =>
								item.background_url ? (
									<img
										src={ item.background_url }
										alt=""
										width={ 64 }
										loading="lazy"
									/>
								) : (
									__( 'No image', 'ohmylms' )
								),
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( item ) =>
								'publish' === item.status
									? __( 'Published', 'ohmylms' )
									: __( 'Draft', 'ohmylms' ),
						},
						{
							id: 'layout',
							label: __( 'Layout', 'ohmylms' ),
							render: ( item ) =>
								`${
									'portrait' === item.orientation
										? __( 'Portrait', 'ohmylms' )
										: __( 'Landscape', 'ohmylms' )
								}, ${ item.size.toUpperCase() }`,
						},
						{
							id: 'courses',
							label: __( 'Courses', 'ohmylms' ),
							className: 'num',
							render: ( item ) => item.course_count,
						},
					] }
					rowActions={ ( item ) => [
						{
							key: 'edit',
							label: __( 'Edit', 'ohmylms' ),
							render: (
								<Link
									to={ `/certificates/${ item.id }` }
									aria-label={ sprintf(
										/* translators: %s: template name. */
										__( 'Edit %s', 'ohmylms' ),
										item.name
									) }
								>
									{ __( 'Edit', 'ohmylms' ) }
								</Link>
							),
						},
						{
							key: 'duplicate',
							label: __( 'Duplicate', 'ohmylms' ),
							disabled: busy,
							onClick: () => duplicate( item ),
							ariaLabel: sprintf(
								/* translators: %s: template name. */
								__( 'Duplicate %s', 'ohmylms' ),
								item.name
							),
						},
						{
							key: 'delete',
							label: __( 'Delete', 'ohmylms' ),
							destructive: true,
							disabled: busy,
							onClick: () => {
								setError( null );
								setDialog( { kind: 'delete', item } );
							},
							ariaLabel: sprintf(
								/* translators: %s: template name. */
								__( 'Delete %s', 'ohmylms' ),
								item.name
							),
						},
					] }
				/>
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
