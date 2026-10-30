import { Table } from '../../../ui/Table';
import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { Pagination } from '../../components/Pagination';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { ApiError } from '../../lib/api';
import { getSchool } from './api';
import { ListState, SearchForm } from './ListControls';
import { MAX_NAME } from './model';
import {
	applyRosterImport,
	createInvitation,
	createManagedStudent,
	createSetupLink,
	csvProblem,
	importActionLabel,
	invitationStatusLabel,
	INVITE_ROLES,
	inviteRoleLabel,
	listInvitations,
	listManagedStudents,
	MAX_CSV_ROWS,
	reviewRosterImport,
	revokeRosterInvitation,
	type CreatedLink,
	type ImportReport,
	type InviteRole,
	type ManagedStudent,
} from './roster';
import type { SchoolDetail } from './types';
import { usePagedList } from './usePagedList';

/**
 * School roster intake at `#/account-hub/schools/<id>/roster`: invitations, school-managed students
 * (accounts without email) and a reviewed CSV import of students and teachers.
 */
export default function SchoolRosterPage() {
	const id = Number( useParams().id );
	const valid = Number.isSafeInteger( id ) && id > 0;
	const request = useRequest(
		useCallback(
			( signal: AbortSignal ) =>
				valid
					? getSchool( id, signal )
					: Promise.reject(
							new Error( __( 'School not found.', 'ohmylms' ) )
						),
			[ id, valid ]
		)
	);

	return (
		<>
			<p>
				<Link
					to={
						valid
							? `/account-hub/schools/${ id }`
							: '/account-hub?tab=schools'
					}
				>
					{ __( '← Back to the school', 'ohmylms' ) }
				</Link>
			</p>
			{ request.loading && (
				<p role="status">{ __( 'Loading school…', 'ohmylms' ) }</p>
			) }
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }{ ' ' }
					<Button variant="link" onClick={ request.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ request.data && <Roster school={ request.data } /> }
		</>
	);
}

function Roster( { school }: { school: SchoolDetail } ) {
	const [ revision, setRevision ] = useState( 0 );
	const changed = () => setRevision( ( value ) => value + 1 );
	return (
		<>
			<h2>
				{ sprintf(
					/* translators: %s: school name. */
					__( '%s: roster', 'ohmylms' ),
					school.name
				) }
			</h2>
			<p>
				{ __(
					'Invite staff and students by email, create school-managed student accounts without email, or import a reviewed CSV. Links are shown once; share them privately.',
					'ohmylms'
				) }
			</p>
			{ 'active' !== school.status && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'This school is not active. Its roster cannot change.',
						'ohmylms'
					) }
				</Notice>
			) }
			<Invitations
				key={ `invitations-${ revision }` }
				schoolId={ school.id }
			/>
			<ManagedStudents
				key={ `students-${ revision }` }
				schoolId={ school.id }
			/>
			<RosterImport schoolId={ school.id } onApplied={ changed } />
		</>
	);
}

function OneTimeLink( {
	title,
	link,
	onDismiss,
}: {
	title: string;
	link: CreatedLink;
	onDismiss: () => void;
} ) {
	return (
		<Notice status="success" onRemove={ onDismiss }>
			<p>
				{ title }{ ' ' }
				{ link.email_sent
					? __( 'It was also sent by email.', 'ohmylms' )
					: __(
							'No email was sent. Copy the link now; it is not shown again.',
							'ohmylms'
						) }
			</p>
			<TextControl
				label={ __( 'Single-use link', 'ohmylms' ) }
				value={ link.url }
				readOnly
				onChange={ () => undefined }
				help={
					link.expires_at
						? sprintf(
								/* translators: %s: UTC expiry time. */
								__( 'Expires %s (UTC).', 'ohmylms' ),
								link.expires_at
							)
						: undefined
				}
			/>
		</Notice>
	);
}

function Invitations( { schoolId }: { schoolId: number } ) {
	const [ all, setAll ] = useState( false );
	const load = useCallback(
		( page: number, search: string, signal: AbortSignal ) =>
			listInvitations( schoolId, page, search, all, signal ),
		[ schoolId, all ]
	);
	const list = usePagedList( load );
	const [ role, setRole ] = useState< InviteRole >( 'teacher' );
	const [ email, setEmail ] = useState( '' );
	const [ sendEmail, setSendEmail ] = useState( false );
	const [ emailError, setEmailError ] = useState< string | null >( null );
	const [ created, setCreated ] = useState< CreatedLink | null >( null );
	const [ notice, setNotice ] = useState< string | null >( null );
	const operation = useOperation();

	function submit() {
		const value = email.trim();
		if ( ! /^[^\s@]+@[^\s@]+$/.test( value ) || value.length > 190 ) {
			setEmailError( __( 'Enter a valid email address.', 'ohmylms' ) );
			return;
		}
		setEmailError( null );
		setNotice( null );
		void operation.run(
			( signal ) =>
				createInvitation(
					schoolId,
					{ role, email: value, send_email: sendEmail },
					signal
				),
			( link ) => {
				setCreated( link );
				setEmail( '' );
				list.reload();
			}
		);
	}

	function revoke( invitationId: number ) {
		setCreated( null );
		void operation.run(
			( signal ) =>
				revokeRosterInvitation( schoolId, invitationId, signal ),
			() => {
				setNotice( __( 'Invitation revoked.', 'ohmylms' ) );
				list.reload();
			}
		);
	}

	return (
		<section aria-labelledby="oml-roster-invitations">
			<h3 id="oml-roster-invitations">
				{ __( 'Invitations', 'ohmylms' ) }
			</h3>
			{ created && (
				<OneTimeLink
					title={ __( 'Invitation created.', 'ohmylms' ) }
					link={ created }
					onDismiss={ () => setCreated( null ) }
				/>
			) }
			{ notice && (
				<Notice status="success" onRemove={ () => setNotice( null ) }>
					{ notice }
				</Notice>
			) }
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			<form
				noValidate
				aria-label={ __( 'Invite to the roster', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					submit();
				} }
			>
				<SelectControl< InviteRole >
					label={ __( 'Role', 'ohmylms' ) }
					value={ role }
					options={ INVITE_ROLES.map( ( value ) => ( {
						value,
						label: inviteRoleLabel( value ),
					} ) ) }
					onChange={ setRole }
				/>
				<TextControl
					label={ __( 'Email', 'ohmylms' ) }
					type="email"
					value={ email }
					maxLength={ 190 }
					help={ emailError ?? undefined }
					onChange={ setEmail }
				/>
				<CheckboxControl
					label={ __(
						'Also send the invitation by email',
						'ohmylms'
					) }
					checked={ sendEmail }
					onChange={ setSendEmail }
				/>
				<Button
					type="submit"
					variant="secondary"
					disabled={ operation.busy }
					isBusy={ operation.busy }
				>
					{ __( 'Create invitation', 'ohmylms' ) }
				</Button>
			</form>
			<SearchForm
				label={ __( 'Search invitations by email', 'ohmylms' ) }
				busy={ list.loading }
				onSearch={ list.setSearch }
			>
				<CheckboxControl
					label={ __(
						'Include accepted, revoked and expired',
						'ohmylms'
					) }
					checked={ all }
					onChange={ setAll }
				/>
			</SearchForm>
			<ListState
				loading={ list.loading }
				error={ list.error }
				total={ list.data.total }
				loadingText={ __( 'Loading invitations…', 'ohmylms' ) }
				emptyText={ __( 'No invitations.', 'ohmylms' ) }
				onRetry={ list.reload }
			>
				<Table caption={ __( 'Invitations', 'ohmylms' ) }>
					<thead>
						<tr>
							{ [
								__( 'Recipient', 'ohmylms' ),
								__( 'Role', 'ohmylms' ),
								__( 'Status', 'ohmylms' ),
								__( 'Expires (UTC)', 'ohmylms' ),
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
								<th scope="row">
									{ item.email ??
										( item.email_hint ||
											item.student_name ||
											'—' ) }
								</th>
								<td>{ inviteRoleLabel( item.role ) }</td>
								<td>
									{ invitationStatusLabel( item.status ) }
								</td>
								<td>{ item.expires_at ?? '—' }</td>
								<td>
									{ 'pending' === item.status && (
										<Button
											variant="link"
											isDestructive
											disabled={ operation.busy }
											aria-label={ sprintf(
												/* translators: %s: recipient. */
												__(
													'Revoke the invitation for %s',
													'ohmylms'
												),
												item.email ?? item.email_hint
											) }
											onClick={ () => revoke( item.id ) }
										>
											{ __( 'Revoke', 'ohmylms' ) }
										</Button>
									) }
								</td>
							</tr>
						) ) }
					</tbody>
				</Table>
				<Pagination
					page={ list.page }
					pages={ list.data.totalPages }
					busy={ list.loading }
					onChange={ list.setPage }
				/>
			</ListState>
		</section>
	);
}

function ManagedStudents( { schoolId }: { schoolId: number } ) {
	const load = useCallback(
		( page: number, search: string, signal: AbortSignal ) =>
			listManagedStudents( schoolId, page, search, signal ),
		[ schoolId ]
	);
	const list = usePagedList( load );
	const [ name, setName ] = useState( '' );
	const [ external, setExternal ] = useState( '' );
	const [ formError, setFormError ] = useState< string | null >( null );
	const [ created, setCreated ] = useState< ManagedStudent | null >( null );
	const [ link, setLink ] = useState< CreatedLink | null >( null );
	const [ confirm, setConfirm ] = useState< ManagedStudent | null >( null );
	const operation = useOperation();

	function submit() {
		const cleanName = name.trim();
		const cleanId = external.trim();
		if (
			! cleanName ||
			Array.from( cleanName ).length > MAX_NAME ||
			! cleanId ||
			cleanId.length > 100
		) {
			setFormError(
				__(
					'Enter a name (up to 190 characters) and a school student ID (up to 100).',
					'ohmylms'
				)
			);
			return;
		}
		setFormError( null );
		setLink( null );
		void operation.run(
			( signal ) =>
				createManagedStudent(
					schoolId,
					{ name: cleanName, external_student_id: cleanId },
					signal
				),
			( student ) => {
				setCreated( student );
				setName( '' );
				setExternal( '' );
				list.reload();
			}
		);
	}

	function setup( student: ManagedStudent ) {
		setCreated( null );
		void operation.run(
			( signal ) => createSetupLink( schoolId, student.id, signal ),
			( result ) => {
				setConfirm( null );
				setLink( result );
				list.reload();
			}
		);
	}

	return (
		<section aria-labelledby="oml-roster-managed">
			<h3 id="oml-roster-managed">
				{ __( 'Managed students', 'ohmylms' ) }
			</h3>
			<p>
				{ __(
					'Accounts the school creates for students without email. Give the student their sign-in name and a password setup link.',
					'ohmylms'
				) }
			</p>
			{ created && (
				<Notice status="success" onRemove={ () => setCreated( null ) }>
					{ sprintf(
						/* translators: 1: student name, 2: sign-in name. */
						__(
							'%1$s was added. Their sign-in name is %2$s. Create a password setup link so they can choose a password.',
							'ohmylms'
						),
						created.display_name,
						created.username
					) }
				</Notice>
			) }
			{ link && (
				<OneTimeLink
					title={ __(
						'Password setup link created. Earlier unused links were revoked.',
						'ohmylms'
					) }
					link={ link }
					onDismiss={ () => setLink( null ) }
				/>
			) }
			{ operation.error && ! confirm && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			<form
				noValidate
				aria-label={ __( 'Add a managed student', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					submit();
				} }
			>
				<TextControl
					label={ __( 'Student name', 'ohmylms' ) }
					value={ name }
					maxLength={ MAX_NAME }
					onChange={ setName }
				/>
				<TextControl
					label={ __( 'School student ID', 'ohmylms' ) }
					value={ external }
					maxLength={ 100 }
					help={ formError ?? undefined }
					onChange={ setExternal }
				/>
				<Button
					type="submit"
					variant="secondary"
					disabled={ operation.busy }
					isBusy={ operation.busy }
				>
					{ __( 'Add managed student', 'ohmylms' ) }
				</Button>
			</form>
			<SearchForm
				label={ __( 'Search managed students', 'ohmylms' ) }
				busy={ list.loading }
				onSearch={ list.setSearch }
			/>
			<ListState
				loading={ list.loading }
				error={ list.error }
				total={ list.data.total }
				loadingText={ __( 'Loading managed students…', 'ohmylms' ) }
				emptyText={ __( 'No managed students.', 'ohmylms' ) }
				onRetry={ list.reload }
			>
				<Table caption={ __( 'Managed students', 'ohmylms' ) }>
					<thead>
						<tr>
							{ [
								__( 'Name', 'ohmylms' ),
								__( 'School student ID', 'ohmylms' ),
								__( 'Sign-in name', 'ohmylms' ),
								__( 'Setup link', 'ohmylms' ),
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
								<th scope="row">{ item.display_name }</th>
								<td>{ item.external_student_id }</td>
								<td>{ item.username }</td>
								<td>
									{ item.setup_expires_at
										? sprintf(
												/* translators: %s: UTC expiry time. */
												__(
													'Open until %s',
													'ohmylms'
												),
												item.setup_expires_at
											)
										: '—' }
								</td>
								<td>
									<Button
										variant="link"
										disabled={ operation.busy }
										aria-label={ sprintf(
											/* translators: %s: student name. */
											__(
												'Create a password setup link for %s',
												'ohmylms'
											),
											item.display_name
										) }
										onClick={ () => {
											operation.clearError();
											setConfirm( item );
										} }
									>
										{ __( 'Setup link', 'ohmylms' ) }
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</Table>
				<Pagination
					page={ list.page }
					pages={ list.data.totalPages }
					busy={ list.loading }
					onChange={ list.setPage }
				/>
			</ListState>
			{ confirm && (
				<Modal
					title={ __( 'Create a password setup link', 'ohmylms' ) }
					onRequestClose={ () =>
						! operation.busy && setConfirm( null )
					}
					shouldCloseOnClickOutside={ false }
				>
					<p>
						{ sprintf(
							/* translators: %s: student name. */
							__(
								'Create a new setup link for %s? Earlier unused links stop working. The password changes only when the student chooses a new one.',
								'ohmylms'
							),
							confirm.display_name
						) }
					</p>
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error }
						</Notice>
					) }
					<Button
						variant="primary"
						disabled={ operation.busy }
						isBusy={ operation.busy }
						onClick={ () => setup( confirm ) }
					>
						{ __( 'Create link', 'ohmylms' ) }
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
		</section>
	);
}

function RosterImport( {
	schoolId,
	onApplied,
}: {
	schoolId: number;
	onApplied: () => void;
} ) {
	const [ csv, setCsv ] = useState( '' );
	const [ problem, setProblem ] = useState< string | null >( null );
	const [ review, setReview ] = useState< {
		csv: string;
		report: ImportReport;
	} | null >( null );
	const [ applied, setApplied ] = useState< ImportReport | null >( null );
	const [ sendEmail, setSendEmail ] = useState( false );
	const [ stale, setStale ] = useState( false );
	const operation = useOperation();
	const current = review && review.csv === csv ? review.report : null;
	const writable = current
		? ( current.counts.create_student ?? 0 ) +
			( current.counts.invite ?? 0 )
		: 0;

	function check() {
		const found = csvProblem( csv );
		setProblem( found );
		setApplied( null );
		setStale( false );
		if ( found ) {
			return;
		}
		void operation.run(
			( signal ) => reviewRosterImport( schoolId, csv, signal ),
			( report ) => setReview( { csv, report } )
		);
	}

	function apply() {
		if ( ! current?.review ) {
			return;
		}
		const token = current.review;
		setStale( false );
		void operation.run(
			async ( signal ) => {
				try {
					return await applyRosterImport(
						schoolId,
						csv,
						token,
						sendEmail,
						signal
					);
				} catch ( failure ) {
					if (
						failure instanceof ApiError &&
						'ohmylms_account_hub_review_stale' === failure.code
					) {
						setReview( null );
						setStale( true );
					}
					throw failure;
				}
			},
			( report ) => {
				setReview( null );
				setApplied( report );
				onApplied();
			}
		);
	}

	const shown = applied ?? current;

	return (
		<section aria-labelledby="oml-roster-import">
			<h3 id="oml-roster-import">{ __( 'Import CSV', 'ohmylms' ) }</h3>
			<p>
				{ sprintf(
					/* translators: %d: maximum rows. */
					__(
						'Up to %d people. Header columns: role (student or teacher), name, email, external_student_id. Students without email become managed students; everyone with email is invited. People already on the roster are skipped, so importing the same file again changes nothing.',
						'ohmylms'
					),
					MAX_CSV_ROWS
				) }
			</p>
			<TextareaControl
				label={ __( 'CSV', 'ohmylms' ) }
				value={ csv }
				rows={ 8 }
				help={ problem ?? undefined }
				onChange={ ( value ) => {
					setCsv( value );
					setApplied( null );
				} }
			/>
			<p>
				<label htmlFor={ `oml-roster-file-${ schoolId }` }>
					{ __( 'Or choose a CSV file', 'ohmylms' ) }
				</label>{ ' ' }
				<input
					id={ `oml-roster-file-${ schoolId }` }
					type="file"
					accept=".csv,text/csv"
					onChange={ ( event ) => {
						const file = event.target.files?.[ 0 ];
						if ( file ) {
							void file.text().then( ( value ) => {
								setCsv( value );
								setApplied( null );
							} );
						}
					} }
				/>
			</p>
			<Button
				variant="secondary"
				disabled={ operation.busy }
				isBusy={ operation.busy && ! current }
				onClick={ check }
			>
				{ __( 'Review import', 'ohmylms' ) }
			</Button>
			{ stale && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'The roster or the file changed since the review. Review it again before importing.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ operation.error && ! stale && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			{ review && ! current && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'The CSV changed after the review. Review it again.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ shown && (
				<>
					<p role="status">
						{ shown.dry_run
							? sprintf(
									/* translators: 1: managed students, 2: invitations, 3: skipped rows, 4: rows with errors. */
									__(
										'Review: %1$d managed students to create, %2$d invitations to send, %3$d already on the roster or invited, %4$d with errors. Nothing has changed yet.',
										'ohmylms'
									),
									shown.counts.create_student ?? 0,
									shown.counts.invite ?? 0,
									( shown.counts.existing ?? 0 ) +
										( shown.counts.pending ?? 0 ),
									shown.counts.error ?? 0
								)
							: sprintf(
									/* translators: 1: created students, 2: invitations, 3: rows not imported. */
									__(
										'Import finished: %1$d managed students created, %2$d invitations created, %3$d rows not imported.',
										'ohmylms'
									),
									shown.counts.created ?? 0,
									shown.counts.invited ?? 0,
									shown.counts.error ?? 0
								) }
					</p>
					{ shown.ignored.length > 0 && (
						<p>
							{ sprintf(
								/* translators: %s: column names. */
								__( 'Ignored columns: %s', 'ohmylms' ),
								shown.ignored.join( ', ' )
							) }
						</p>
					) }
					<Table
						caption={
							shown.dry_run
								? __( 'Import review', 'ohmylms' )
								: __( 'Import result', 'ohmylms' )
						}
					>
						<thead>
							<tr>
								{ [
									__( 'Line', 'ohmylms' ),
									__( 'Name', 'ohmylms' ),
									__( 'Role', 'ohmylms' ),
									__( 'Email or student ID', 'ohmylms' ),
									__( 'Result', 'ohmylms' ),
								].map( ( title ) => (
									<th key={ title } scope="col">
										{ title }
									</th>
								) ) }
							</tr>
						</thead>
						<tbody>
							{ shown.rows.map( ( row ) => (
								<tr key={ row.line }>
									<td>{ row.line }</td>
									<th scope="row">{ row.name || '—' }</th>
									<td>{ inviteRoleLabel( row.role ) }</td>
									<td>
										{ row.email ||
											row.external_student_id ||
											'—' }
									</td>
									<td>
										{ importActionLabel( row.action ) }
										{ row.error && `: ${ row.error }` }
										{ row.username &&
											' ' +
												sprintf(
													/* translators: %s: sign-in name. */
													__(
														'(sign-in name %s)',
														'ohmylms'
													),
													row.username
												) }
										{ row.url && (
											<TextControl
												label={ sprintf(
													/* translators: %d: CSV line. */
													__(
														'Invitation link for line %d',
														'ohmylms'
													),
													row.line
												) }
												value={ row.url }
												readOnly
												onChange={ () => undefined }
											/>
										) }
									</td>
								</tr>
							) ) }
						</tbody>
					</Table>
				</>
			) }
			{ current && (
				<>
					<CheckboxControl
						label={ __(
							'Email the invitations created by the import',
							'ohmylms'
						) }
						checked={ sendEmail }
						onChange={ setSendEmail }
					/>
					<Button
						variant="primary"
						disabled={ operation.busy || ! writable }
						isBusy={ operation.busy }
						onClick={ apply }
					>
						{ __( 'Import reviewed rows', 'ohmylms' ) }
					</Button>
				</>
			) }
		</section>
	);
}
