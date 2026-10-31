import { ListTable, ListTableSelect } from '../../../ui/ListTable';
import { Button, Modal, Notice } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useListState } from '../../components/listQuery';
import { statusViews } from '../../components/listViews';
import { useOperation } from '../../hooks/useOperation';
import {
	PageTitleAction,
	PageTitleActions,
} from '../../layout/PageTitleActions';
import { normalizeApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { IssueCertificateDialog } from './IssueCertificateDialog';
import {
	formatIssuedDate,
	issuedLabel,
	listCourseOptions,
	listIssued,
	listTemplateOptions,
	reissueIssued,
	revokeIssued,
	sourceLabel,
	type IssuedCertificate,
} from './issuedApi';
import type { CourseChoice } from './types';

const FILTERS = {
	search: '',
	status: 'all',
	course: '',
	certificate: '',
	source: '',
	orderby: 'issued_at',
	order: 'desc',
};

type Confirm = {
	kind: 'revoke' | 'reissue';
	item: IssuedCertificate;
} | null;

function statusOptions() {
	return [
		{ value: 'all', label: __( 'All', 'ohmylms' ) },
		{ value: 'issued', label: __( 'Valid', 'ohmylms' ) },
		{ value: 'revoked', label: __( 'Revoked', 'ohmylms' ) },
	];
}

/**
 * Issued certificates (`/certificates/issued`, Gamification › Issued certificates): search by code,
 * learner or course; filter by course, template and how they were issued; revoke and re-issue with
 * confirmation; issue by hand. The list state is kept in the address.
 */
export default function IssuedCertificatesList() {
	const list = useListState( FILTERS, { search: 's' } );
	const { filters, page } = list;
	const operation = useOperation();
	const [ version, setVersion ] = useState( 0 );
	const [ data, setData ] = useState< Collection< IssuedCertificate > >( {
		items: [],
		total: 0,
		totalPages: 0,
	} );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState< string | null >( null );
	const [ search, setSearch ] = useState( filters.search );
	useEffect( () => setSearch( filters.search ), [ filters.search ] );
	const [ courses, setCourses ] = useState< CourseChoice[] >( [] );
	const [ templates, setTemplates ] = useState<
		{ id: number; name: string }[]
	>( [] );
	const [ issuing, setIssuing ] = useState( false );
	const [ confirm, setConfirm ] = useState< Confirm >( null );
	const [ message, setMessage ] = useState< string | null >( null );

	useEffect( () => {
		const controller = new AbortController();
		Promise.all( [
			listCourseOptions( '', controller.signal ),
			listTemplateOptions( controller.signal ),
		] ).then(
			( [ loadedCourses, loadedTemplates ] ) => {
				if ( ! controller.signal.aborted ) {
					setCourses( loadedCourses );
					setTemplates( loadedTemplates );
				}
			},
			() => {
				// The filters stay without choices; the list itself reports errors.
			}
		);
		return () => controller.abort();
	}, [] );

	useEffect( () => {
		const controller = new AbortController();
		setLoading( true );
		setError( null );
		listIssued( { ...filters, page }, controller.signal ).then(
			( next ) => {
				if ( ! controller.signal.aborted ) {
					setData( next );
					setLoading( false );
				}
			},
			( failure: unknown ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				const problem = normalizeApiError( failure );
				if (
					page > 1 &&
					'ohmylms_issued_certificate_invalid_page' === problem.code
				) {
					list.setPage( page - 1, { replace: true } );
					return;
				}
				setError( problem.message );
				setLoading( false );
			}
		);
		return () => controller.abort();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ filters, page, version ] );

	const reload = () => setVersion( ( current ) => current + 1 );
	const setFilter = ( key: keyof typeof FILTERS, value: string ) =>
		list.setFilters( { ...filters, [ key ]: value } );

	function act( kind: 'revoke' | 'reissue', item: IssuedCertificate ) {
		setMessage( null );
		void operation.run(
			( signal ) =>
				'revoke' === kind
					? revokeIssued( item.id, signal )
					: reissueIssued( item.id, signal ),
			( result ) => {
				setConfirm( null );
				setMessage(
					'revoke' === kind
						? sprintf(
								/* translators: 1: certificate code, 2: learner and course. */
								__( 'Revoked %1$s (%2$s).', 'ohmylms' ),
								item.code,
								issuedLabel( item )
							)
						: sprintf(
								/* translators: 1: new certificate code, 2: learner and course. */
								__(
									'Re-issued as %1$s (%2$s). The previous code is revoked.',
									'ohmylms'
								),
								result.code,
								issuedLabel( result )
							)
				);
				reload();
			}
		);
	}

	return (
		<>
			<PageTitleActions>
				<PageTitleAction
					disabled={ operation.busy }
					onClick={ () => {
						setMessage( null );
						setIssuing( true );
					} }
				>
					{ __( 'Issue certificate', 'ohmylms' ) }
				</PageTitleAction>
				<PageTitleAction to="/certificates">
					{ __( 'Templates', 'ohmylms' ) }
				</PageTitleAction>
			</PageTitleActions>
			<p>
				{ __(
					'Certificates learners received. They are issued automatically when a learner completes a course whose template is published.',
					'ohmylms'
				) }
			</p>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			{ operation.error && ! confirm && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }{ ' ' }
					<Button variant="link" onClick={ reload }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ loading && (
				<p role="status">
					{ __( 'Loading issued certificates…', 'ohmylms' ) }
				</p>
			) }
			{ ! loading && ! error && (
				<ListTable< IssuedCertificate >
					caption={ __( 'Issued certificates', 'ohmylms' ) }
					heading={ __( 'Issued certificates list', 'ohmylms' ) }
					formLabel={ __( 'Search issued certificates', 'ohmylms' ) }
					busy={ operation.busy }
					rows={ data.items }
					rowKey={ ( item ) => item.id }
					emptyMessage={ __(
						'No certificates have been issued yet.',
						'ohmylms'
					) }
					views={ statusViews(
						statusOptions(),
						filters.status,
						data.total,
						( value ) => setFilter( 'status', value ),
						( value ) =>
							list.filtersHref( { ...filters, status: value } )
					) }
					search={ {
						label: __( 'Search issued certificates', 'ohmylms' ),
						submitLabel: __( 'Search', 'ohmylms' ),
						placeholder: __( 'Code, learner or course', 'ohmylms' ),
						value: search,
						maxLength: 100,
						onChange: setSearch,
					} }
					onFilter={ () => setFilter( 'search', search ) }
					filterLabel={ null }
					filters={
						<>
							<ListTableSelect
								label={ __( 'Course', 'ohmylms' ) }
								value={ filters.course }
								options={ [
									{
										value: '',
										label: __( 'All courses', 'ohmylms' ),
									},
									...courses.map( ( item ) => ( {
										value: String( item.id ),
										label: item.title,
									} ) ),
								] }
								onChange={ ( value ) =>
									setFilter( 'course', value )
								}
							/>
							<ListTableSelect
								label={ __( 'Template', 'ohmylms' ) }
								value={ filters.certificate }
								options={ [
									{
										value: '',
										label: __( 'All templates', 'ohmylms' ),
									},
									...templates.map( ( item ) => ( {
										value: String( item.id ),
										label: item.name,
									} ) ),
								] }
								onChange={ ( value ) =>
									setFilter( 'certificate', value )
								}
							/>
							<ListTableSelect
								label={ __( 'Issued by', 'ohmylms' ) }
								value={ filters.source }
								options={ [
									{
										value: '',
										label: __( 'Any way', 'ohmylms' ),
									},
									{
										value: 'completion',
										label: sourceLabel( 'completion' ),
									},
									{
										value: 'manual',
										label: sourceLabel( 'manual' ),
									},
									{
										value: 'reissue',
										label: sourceLabel( 'reissue' ),
									},
								] }
								onChange={ ( value ) =>
									setFilter( 'source', value )
								}
							/>
						</>
					}
					sort={ {
						orderby: filters.orderby,
						order: 'asc' === filters.order ? 'asc' : 'desc',
						onChange: ( orderby, order ) =>
							list.setFilters( { ...filters, orderby, order } ),
						href: ( orderby, order ) =>
							list.filtersHref( { ...filters, orderby, order } ),
					} }
					pagination={ {
						page,
						totalPages: data.totalPages,
						totalItems: data.total,
						itemsLabel: sprintf(
							/* translators: %d: number of certificates. */
							_n(
								'%d certificate found.',
								'%d certificates found.',
								data.total,
								'ohmylms'
							),
							data.total
						),
						onChange: ( next ) => list.setPage( next ),
						href: list.pageHref,
					} }
					columns={ [
						{
							id: 'student_name',
							label: __( 'Learner', 'ohmylms' ),
							primary: true,
							sortable: true,
							render: ( item ) => (
								<>
									<strong>
										{ item.student_name ||
											__( 'Unknown learner', 'ohmylms' ) }
									</strong>
									{ item.learner.email && (
										<>
											<br />
											{ item.learner.email }
										</>
									) }
									{ ! item.learner.exists && (
										<>
											<br />
											{ __(
												'Account deleted',
												'ohmylms'
											) }
										</>
									) }
								</>
							),
						},
						{
							id: 'course_title',
							label: __( 'Course', 'ohmylms' ),
							sortable: true,
							render: ( item ) => item.course.title,
						},
						{
							id: 'template',
							label: __( 'Template', 'ohmylms' ),
							render: ( item ) =>
								item.template.name ||
								__( 'Deleted template', 'ohmylms' ),
						},
						{
							id: 'issued_at',
							label: __( 'Issued', 'ohmylms' ),
							sortable: 'desc',
							render: ( item ) => (
								<>
									{ formatIssuedDate( item.issued_at ) }
									<br />
									{ sourceLabel( item.source ) }
								</>
							),
						},
						{
							id: 'code',
							label: __( 'Code', 'ohmylms' ),
							render: ( item ) => <code>{ item.code }</code>,
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( item ) =>
								'revoked' === item.status
									? sprintf(
											/* translators: %s: date. */
											__( 'Revoked %s', 'ohmylms' ),
											formatIssuedDate( item.revoked_at )
										)
									: __( 'Valid', 'ohmylms' ),
						},
					] }
					rowActions={ ( item ) => [
						'issued' === item.status && {
							key: 'view',
							label: __( 'PDF', 'ohmylms' ),
							href: item.links.pdf,
							ariaLabel: sprintf(
								/* translators: %s: learner and course. */
								__( 'Download the PDF of %s', 'ohmylms' ),
								issuedLabel( item )
							),
						},
						'issued' === item.status && {
							key: 'print',
							label: __( 'Print', 'ohmylms' ),
							href: item.links.print,
							ariaLabel: sprintf(
								/* translators: %s: learner and course. */
								__( 'Print the certificate of %s', 'ohmylms' ),
								issuedLabel( item )
							),
						},
						{
							key: 'verify',
							label: __( 'Verification page', 'ohmylms' ),
							href: item.links.verify,
						},
						{
							key: 'reissue',
							label: __( 'Re-issue', 'ohmylms' ),
							disabled: operation.busy,
							onClick: () => {
								operation.clearError();
								setConfirm( { kind: 'reissue', item } );
							},
							ariaLabel: sprintf(
								/* translators: %s: learner and course. */
								__(
									'Re-issue the certificate of %s',
									'ohmylms'
								),
								issuedLabel( item )
							),
						},
						'issued' === item.status && {
							key: 'trash',
							label: __( 'Revoke', 'ohmylms' ),
							destructive: true,
							disabled: operation.busy,
							onClick: () => {
								operation.clearError();
								setConfirm( { kind: 'revoke', item } );
							},
							ariaLabel: sprintf(
								/* translators: %s: learner and course. */
								__( 'Revoke the certificate of %s', 'ohmylms' ),
								issuedLabel( item )
							),
						},
					] }
				/>
			) }
			{ confirm && (
				<Modal
					title={
						'revoke' === confirm.kind
							? __( 'Revoke certificate', 'ohmylms' )
							: __( 'Re-issue certificate', 'ohmylms' )
					}
					onRequestClose={ () =>
						! operation.busy && setConfirm( null )
					}
					shouldCloseOnClickOutside={ false }
				>
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error }
						</Notice>
					) }
					<p>
						{ 'revoke' === confirm.kind
							? sprintf(
									/* translators: 1: certificate code, 2: learner and course. */
									__(
										'Revoke %1$s (%2$s)? The learner can no longer download it and the verification page will show it as revoked.',
										'ohmylms'
									),
									confirm.item.code,
									issuedLabel( confirm.item )
								)
							: sprintf(
									/* translators: 1: certificate code, 2: learner and course. */
									__(
										'Re-issue %1$s (%2$s)? A new certificate with a new code is created from the learner’s current name, the course title and its template; this code is revoked.',
										'ohmylms'
									),
									confirm.item.code,
									issuedLabel( confirm.item )
								) }
					</p>
					<Button
						variant="primary"
						isDestructive={ 'revoke' === confirm.kind }
						disabled={ operation.busy }
						isBusy={ operation.busy }
						onClick={ () => act( confirm.kind, confirm.item ) }
					>
						{ 'revoke' === confirm.kind
							? __( 'Revoke certificate', 'ohmylms' )
							: __( 'Re-issue certificate', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ operation.busy }
						onClick={ () => setConfirm( null ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
			{ issuing && (
				<IssueCertificateDialog
					templates={ templates }
					onClose={ () => setIssuing( false ) }
					onIssued={ ( item ) => {
						setIssuing( false );
						setMessage(
							sprintf(
								/* translators: 1: certificate code, 2: learner and course. */
								__( 'Issued %1$s (%2$s).', 'ohmylms' ),
								item.code,
								issuedLabel( item )
							)
						);
						reload();
					} }
				/>
			) }
		</>
	);
}
