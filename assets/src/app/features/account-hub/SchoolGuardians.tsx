import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	RadioControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { ListTableSelect } from '../../../ui/ListTable';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { listClasses, listParents, listSchoolStudents } from './api';
import { HubListTable } from './ListControls';
import {
	inviteGuardian,
	listGuardians,
	revokeGuardianInvitation,
	unlinkGuardian,
} from './operationsApi';
import type {
	GuardianInvitation,
	GuardianRow,
	GuardianStatusFilter,
	PersonRef,
	SchoolDetail,
} from './types';
import { usePagedList } from '../../hooks/usePagedList';

const NO_PEOPLE = { items: [] as PersonRef[], total: 0, totalPages: 0 };

function when( row: GuardianRow ): string {
	const value = ( row.linked_at ?? row.expires_at ?? '' ).slice( 0, 10 );
	if ( ! value ) {
		return '—';
	}
	return 'link' === row.kind
		? sprintf(
				/* translators: %s: date. */
				__( 'Since %s', 'ohmylms' ),
				value
			)
		: sprintf(
				/* translators: %s: date. */
				__( 'Expires %s', 'ohmylms' ),
				value
			);
}

/**
 * Guardians of a school's learners: active links and open invitations, filtered by status and class,
 * with invite, link and unlink. A link is active only once the parent accepts the single-use
 * invitation with their own account.
 *
 * @param props              Component props.
 * @param props.school       The school.
 * @param props.initialClass Class to filter by first (`class_id` of the address), or 0.
 */
export function SchoolGuardians( {
	school,
	initialClass = 0,
}: {
	school: SchoolDetail;
	initialClass?: number;
} ) {
	const [ status, setStatus ] = useState< GuardianStatusFilter >( 'all' );
	const [ classId, setClassId ] = useState( initialClass );
	const classes = useRequest(
		useCallback(
			( signal: AbortSignal ) =>
				listClasses( 1, '', 'active', school.id, signal ),
			[ school.id ]
		)
	);
	const load = useCallback(
		( page: number, search: string, signal: AbortSignal ) =>
			listGuardians( school.id, page, search, status, classId, signal ),
		[ school.id, status, classId ]
	);
	const list = usePagedList( load );
	const operation = useOperation();
	const [ confirm, setConfirm ] = useState< GuardianRow | null >( null );
	const [ inviting, setInviting ] = useState( false );
	const [ created, setCreated ] = useState< GuardianInvitation | null >(
		null
	);
	const archived = 'archived' === school.status;
	const views: { key: GuardianStatusFilter; label: string }[] = [
		{ key: 'all', label: __( 'All', 'ohmylms' ) },
		{ key: 'active', label: __( 'Linked', 'ohmylms' ) },
		{ key: 'pending', label: __( 'Invitation pending', 'ohmylms' ) },
	];

	return (
		<section aria-label={ __( 'Guardians', 'ohmylms' ) }>
			<h3>{ __( 'Guardians', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'Parents and guardians linked to this school’s students, and invitations they have not accepted yet. Only names are shown; open invitations show a masked address.',
					'ohmylms'
				) }
			</p>
			{ ! archived && (
				<p>
					<Button
						variant="secondary"
						onClick={ () => {
							setCreated( null );
							setInviting( true );
						} }
					>
						{ __( 'Invite a guardian', 'ohmylms' ) }
					</Button>
				</p>
			) }
			{ created && (
				<Notice status="success" onRemove={ () => setCreated( null ) }>
					<TextControl
						label={ __(
							'Invitation link (shown once, valid for 48 hours)',
							'ohmylms'
						) }
						value={ created.url }
						readOnly
						onChange={ () => undefined }
					/>
					<p>
						{ created.email_sent
							? __(
									'The link was also emailed to the guardian.',
									'ohmylms'
								)
							: __(
									'Share it privately with the guardian. Nothing was emailed.',
									'ohmylms'
								) }
					</p>
				</Notice>
			) }
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			<HubListTable< GuardianRow >
				caption={ __( 'Guardians of this school', 'ohmylms' ) }
				searchLabel={ __( 'Search students and guardians', 'ohmylms' ) }
				list={ list }
				loadingText={ __( 'Loading guardians…', 'ohmylms' ) }
				emptyText={ __( 'No guardians found.', 'ohmylms' ) }
				views={ views.map( ( view ) => ( {
					...view,
					current: view.key === status,
					onSelect: () => setStatus( view.key ),
				} ) ) }
				filters={
					<ListTableSelect
						label={ __( 'Filter by class', 'ohmylms' ) }
						value={ String( classId ) }
						options={ [
							{
								value: '0',
								label: __( 'All classes', 'ohmylms' ),
							},
							...( classes.data?.items ?? [] ).map(
								( item ) => ( {
									value: String( item.id ),
									label: item.name,
								} )
							),
						] }
						onChange={ ( value ) => setClassId( Number( value ) ) }
					/>
				}
				table={ {
					rowKey: ( row ) => `${ row.kind }-${ row.id }`,
					columns: [
						{
							id: 'student',
							label: __( 'Student', 'ohmylms' ),
							primary: true,
							render: ( row ) => (
								<strong>{ row.student.display_name }</strong>
							),
						},
						{
							id: 'guardian',
							label: __( 'Guardian', 'ohmylms' ),
							render: ( row ) =>
								row.parent?.display_name ??
								sprintf(
									/* translators: %s: masked email address. */
									__( 'Invited: %s', 'ohmylms' ),
									row.email_hint ?? ''
								),
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( row ) =>
								'active' === row.status
									? __( 'Linked', 'ohmylms' )
									: __( 'Invitation pending', 'ohmylms' ),
						},
						{
							id: 'when',
							label: __( 'Date', 'ohmylms' ),
							render: when,
						},
					],
					rowActions: ( row ) =>
						archived
							? []
							: [
									{
										key: 'trash',
										destructive: true,
										label:
											'link' === row.kind
												? __( 'Unlink', 'ohmylms' )
												: __(
														'Revoke invitation',
														'ohmylms'
													),
										onClick: () => setConfirm( row ),
									},
								],
				} }
			/>
			{ confirm && (
				<Modal
					title={
						'link' === confirm.kind
							? __( 'Unlink this guardian?', 'ohmylms' )
							: __( 'Revoke this invitation?', 'ohmylms' )
					}
					onRequestClose={ () =>
						! operation.busy && setConfirm( null )
					}
				>
					<p>
						{ 'link' === confirm.kind
							? sprintf(
									/* translators: 1: guardian name, 2: student name. */
									__(
										'%1$s will no longer see the school work of %2$s. Both accounts stay.',
										'ohmylms'
									),
									confirm.parent?.display_name ?? '',
									confirm.student.display_name
								)
							: sprintf(
									/* translators: %s: student name. */
									__(
										'The invitation to link with %s stops working.',
										'ohmylms'
									),
									confirm.student.display_name
								) }
					</p>
					<Button
						variant="primary"
						isDestructive
						disabled={ operation.busy }
						isBusy={ operation.busy }
						onClick={ () =>
							void operation.run(
								( signal ) =>
									'link' === confirm.kind
										? unlinkGuardian(
												school.id,
												confirm.id,
												signal
											)
										: revokeGuardianInvitation(
												school.id,
												confirm.id,
												signal
											),
								() => {
									setConfirm( null );
									list.reload();
								}
							)
						}
					>
						{ 'link' === confirm.kind
							? __( 'Unlink', 'ohmylms' )
							: __( 'Revoke invitation', 'ohmylms' ) }
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
			{ inviting && (
				<InviteGuardianDialog
					school={ school }
					onClose={ () => setInviting( false ) }
					onCreated={ ( invitation ) => {
						setInviting( false );
						setCreated( invitation );
						list.reload();
					} }
				/>
			) }
		</section>
	);
}

function InviteGuardianDialog( {
	school,
	onClose,
	onCreated,
}: {
	school: SchoolDetail;
	onClose: () => void;
	onCreated: ( invitation: GuardianInvitation ) => void;
} ) {
	const operation = useOperation();
	const [ studentText, setStudentText ] = useState( '' );
	const [ studentQuery, setStudentQuery ] = useState< string | null >( null );
	const students = useRequest(
		useCallback(
			( signal: AbortSignal ) =>
				null === studentQuery
					? Promise.resolve( NO_PEOPLE )
					: listSchoolStudents( school.id, studentQuery, signal ),
			[ school.id, studentQuery ]
		)
	);
	const [ student, setStudent ] = useState( 0 );
	const [ mode, setMode ] = useState< 'email' | 'parent' >( 'email' );
	const [ email, setEmail ] = useState( '' );
	const [ parentText, setParentText ] = useState( '' );
	const [ parentQuery, setParentQuery ] = useState< string | null >( null );
	const parents = useRequest(
		useCallback(
			( signal: AbortSignal ) =>
				null === parentQuery
					? Promise.resolve( { items: [], total: 0, totalPages: 0 } )
					: listParents( 1, parentQuery, signal ),
			[ parentQuery ]
		)
	);
	const [ parent, setParent ] = useState( 0 );
	const [ send, setSend ] = useState( false );
	const [ error, setError ] = useState( '' );
	const chosenStudent = student || ( students.data?.items[ 0 ]?.id ?? 0 );
	const chosenParent = parent || ( parents.data?.items[ 0 ]?.id ?? 0 );

	function submit() {
		if ( ! chosenStudent ) {
			setError( __( 'Find and choose a student first.', 'ohmylms' ) );
			return;
		}
		if (
			'email' === mode &&
			! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email.trim() )
		) {
			setError( __( 'Enter a valid email address.', 'ohmylms' ) );
			return;
		}
		if ( 'parent' === mode && ! chosenParent ) {
			setError( __( 'Find and choose a parent account.', 'ohmylms' ) );
			return;
		}
		setError( '' );
		void operation.run(
			( signal ) =>
				inviteGuardian(
					school.id,
					'parent' === mode
						? {
								student_id: chosenStudent,
								parent_id: chosenParent,
								send_email: send,
							}
						: {
								student_id: chosenStudent,
								email,
								send_email: send,
							},
					signal
				),
			onCreated
		);
	}

	return (
		<Modal
			title={ __( 'Invite a guardian', 'ohmylms' ) }
			onRequestClose={ () => ! operation.busy && onClose() }
			shouldCloseOnClickOutside={ false }
		>
			<p>
				{ __(
					'The guardian gets a single-use link that is valid for 48 hours. The link becomes active only when they accept it while signed in with the invited address.',
					'ohmylms'
				) }
			</p>
			{ ( error || operation.error ) && (
				<Notice status="error" isDismissible={ false }>
					{ error || operation.error }
				</Notice>
			) }
			<form
				role="search"
				aria-label={ __( 'Find a student', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					setStudent( 0 );
					setStudentQuery( studentText.trim() );
				} }
			>
				<TextControl
					label={ __( 'Student name', 'ohmylms' ) }
					value={ studentText }
					onChange={ setStudentText }
				/>
				<Button type="submit" variant="secondary">
					{ __( 'Find student', 'ohmylms' ) }
				</Button>
			</form>
			{ null !== studentQuery && students.data && (
				<SelectControl
					label={ __( 'Student', 'ohmylms' ) }
					value={ String( chosenStudent ) }
					disabled={ ! students.data.items.length }
					options={
						students.data.items.length
							? students.data.items.map( ( item ) => ( {
									value: String( item.id ),
									label: item.display_name,
								} ) )
							: [
									{
										value: '0',
										label: __(
											'No students found in this school.',
											'ohmylms'
										),
									},
								]
					}
					onChange={ ( value ) => setStudent( Number( value ) ) }
				/>
			) }
			<RadioControl
				label={ __( 'Guardian', 'ohmylms' ) }
				selected={ mode }
				options={ [
					{
						value: 'email',
						label: __( 'Invite by email address', 'ohmylms' ),
					},
					{
						value: 'parent',
						label: __(
							'Link an existing parent account',
							'ohmylms'
						),
					},
				] }
				onChange={ ( value ) =>
					setMode( 'parent' === value ? 'parent' : 'email' )
				}
			/>
			{ 'email' === mode ? (
				<TextControl
					label={ __( 'Guardian email', 'ohmylms' ) }
					type="email"
					value={ email }
					maxLength={ 190 }
					onChange={ setEmail }
				/>
			) : (
				<>
					<form
						role="search"
						aria-label={ __( 'Find a parent', 'ohmylms' ) }
						onSubmit={ ( event ) => {
							event.preventDefault();
							setParent( 0 );
							setParentQuery( parentText.trim() );
						} }
					>
						<TextControl
							label={ __( 'Parent name', 'ohmylms' ) }
							value={ parentText }
							onChange={ setParentText }
						/>
						<Button type="submit" variant="secondary">
							{ __( 'Find parent', 'ohmylms' ) }
						</Button>
					</form>
					{ null !== parentQuery && parents.data && (
						<SelectControl
							label={ __( 'Parent account', 'ohmylms' ) }
							value={ String( chosenParent ) }
							disabled={ ! parents.data.items.length }
							options={
								parents.data.items.length
									? parents.data.items.map( ( item ) => ( {
											value: String( item.id ),
											label: item.display_name,
										} ) )
									: [
											{
												value: '0',
												label: __(
													'No parent accounts found.',
													'ohmylms'
												),
											},
										]
							}
							onChange={ ( value ) =>
								setParent( Number( value ) )
							}
						/>
					) }
				</>
			) }
			<CheckboxControl
				label={ __( 'Also email the link', 'ohmylms' ) }
				checked={ send }
				onChange={ setSend }
			/>
			<Button
				variant="primary"
				disabled={ operation.busy }
				isBusy={ operation.busy }
				onClick={ submit }
			>
				{ __( 'Create invitation', 'ohmylms' ) }
			</Button>{ ' ' }
			<Button
				variant="tertiary"
				disabled={ operation.busy }
				onClick={ onClose }
			>
				{ __( 'Cancel', 'ohmylms' ) }
			</Button>
		</Modal>
	);
}
