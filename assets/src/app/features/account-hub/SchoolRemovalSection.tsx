import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useNavigate } from 'react-router-dom';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import {
	archiveSchool,
	deleteSchool,
	getRemoval,
	restoreSchool,
} from './operationsApi';
import type { RemovalImpact, SchoolDetail, SchoolRemoval } from './types';

/**
 * Consequences of deleting a school, as sentences; rows with nothing to remove are left out.
 *
 * @param impact Counts.
 * @return Lines to list.
 */
export function consequenceLines( impact: RemovalImpact ): string[] {
	const lines: ( string | number )[] = [
		impact.classes &&
			sprintf(
				/* translators: %d: number of classes. */
				_n(
					'%d class with its roster is deleted.',
					'%d classes with their rosters are deleted.',
					impact.classes,
					'ohmylms'
				),
				impact.classes
			),
		impact.academic_years &&
			sprintf(
				/* translators: %d: number of academic years. */
				_n(
					'%d academic year is deleted.',
					'%d academic years are deleted.',
					impact.academic_years,
					'ohmylms'
				),
				impact.academic_years
			),
		impact.students + impact.teachers + impact.school_admins &&
			sprintf(
				/* translators: 1: students, 2: teachers, 3: school administrators. */
				__(
					'School memberships end for %1$d students, %2$d teachers and %3$d school administrators.',
					'ohmylms'
				),
				impact.students,
				impact.teachers,
				impact.school_admins
			),
		impact.teacher_links &&
			sprintf(
				/* translators: %d: number of teacher class links. */
				_n(
					'%d teacher loses a class link.',
					'%d teacher class links are removed.',
					impact.teacher_links,
					'ohmylms'
				),
				impact.teacher_links
			),
		impact.roster_enrolments &&
			sprintf(
				/* translators: %d: number of enrolments. */
				_n(
					'%d course enrolment granted by a class roster is cancelled. Enrolments bought or granted another way stay.',
					'%d course enrolments granted by class rosters are cancelled. Enrolments bought or granted another way stay.',
					impact.roster_enrolments,
					'ohmylms'
				),
				impact.roster_enrolments
			),
		impact.guardian_links &&
			sprintf(
				/* translators: %d: number of guardian links. */
				_n(
					'%d guardian link is removed.',
					'%d guardian links are removed.',
					impact.guardian_links,
					'ohmylms'
				),
				impact.guardian_links
			),
		impact.invitations &&
			sprintf(
				/* translators: %d: number of invitations. */
				_n(
					'%d open invitation stops working.',
					'%d open invitations stop working.',
					impact.invitations,
					'ohmylms'
				),
				impact.invitations
			),
		impact.assignments &&
			sprintf(
				/* translators: %d: number of assignments. */
				_n(
					'%d piece of distributed class work is deleted.',
					'%d pieces of distributed class work are deleted.',
					impact.assignments,
					'ohmylms'
				),
				impact.assignments
			),
		impact.course_links &&
			sprintf(
				/* translators: %d: number of course links. */
				_n(
					'%d course-class link is removed.',
					'%d course-class links are removed.',
					impact.course_links,
					'ohmylms'
				),
				impact.course_links
			),
	];
	return [
		...lines.filter( ( line ): line is string => 'string' === typeof line ),
		impact.managed_accounts
			? sprintf(
					/* translators: %d: number of accounts. */
					_n(
						'No account is deleted: the %d account this school created stays and can still sign in.',
						'No account is deleted: the %d accounts this school created stay and can still sign in.',
						impact.managed_accounts,
						'ohmylms'
					),
					impact.managed_accounts
				)
			: __( 'No user account is deleted.', 'ohmylms' ),
		__(
			'Course progress, quiz attempts and grades of every learner stay.',
			'ohmylms'
		),
	];
}

/**
 * Archive (reversible), restore and permanently delete a school. The deletion dialog loads the current
 * consequences and needs the school name typed exactly.
 *
 * @param props          Component props.
 * @param props.school   The school.
 * @param props.onChange Called with the school after archive or restore.
 */
export function SchoolRemovalSection( {
	school,
	onChange,
}: {
	school: SchoolDetail;
	onChange: ( school: SchoolDetail ) => void;
} ) {
	const navigate = useNavigate();
	const operation = useOperation();
	const [ dialog, setDialog ] = useState< 'archive' | 'delete' | null >(
		null
	);
	const archived = 'archived' === school.status;

	function toggle() {
		void operation.run(
			( signal ) =>
				archived
					? restoreSchool( school.id, signal )
					: archiveSchool( school.id, signal ),
			( result ) => {
				setDialog( null );
				onChange( result );
			}
		);
	}

	return (
		<section aria-label={ __( 'Archive or delete', 'ohmylms' ) }>
			<h3>{ __( 'Archive or delete', 'ohmylms' ) }</h3>
			{ operation.error && ! dialog && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			{ archived ? (
				<>
					<p>
						{ __(
							'This school is archived: its staff, students and families cannot open it, and nothing can be changed until it is restored.',
							'ohmylms'
						) }
					</p>
					<Button
						variant="secondary"
						disabled={ operation.busy }
						isBusy={ operation.busy }
						onClick={ toggle }
					>
						{ __( 'Restore school', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="secondary"
						isDestructive
						disabled={ operation.busy }
						onClick={ () => {
							operation.clearError();
							setDialog( 'delete' );
						} }
					>
						{ __( 'Delete permanently…', 'ohmylms' ) }
					</Button>
				</>
			) : (
				<>
					<p>
						{ __(
							'Archive the school to close it for everyone while keeping all of its records. Restore it at any time; delete it permanently only once it is archived.',
							'ohmylms'
						) }
					</p>
					<Button
						variant="secondary"
						disabled={ operation.busy }
						onClick={ () => {
							operation.clearError();
							setDialog( 'archive' );
						} }
					>
						{ __( 'Archive school…', 'ohmylms' ) }
					</Button>
				</>
			) }
			{ 'archive' === dialog && (
				<Modal
					title={ __( 'Archive this school?', 'ohmylms' ) }
					onRequestClose={ () =>
						! operation.busy && setDialog( null )
					}
				>
					<p>
						{ __(
							'School administrators, teachers, students and parents lose access to the school, its classes and its reports, and open invitations cannot be accepted. Classes, rosters, enrolments and accounts are kept and come back when you restore it.',
							'ohmylms'
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
						onClick={ toggle }
					>
						{ __( 'Archive school', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ operation.busy }
						onClick={ () => setDialog( null ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
			{ 'delete' === dialog && (
				<DeleteDialog
					school={ school }
					onClose={ () => setDialog( null ) }
					onDeleted={ () =>
						navigate( '/account-hub?tab=schools', {
							state: { deleted: school.name },
						} )
					}
				/>
			) }
		</section>
	);
}

function DeleteDialog( {
	school,
	onClose,
	onDeleted,
}: {
	school: SchoolDetail;
	onClose: () => void;
	onDeleted: () => void;
} ) {
	const load = useRequest(
		useCallback(
			( signal: AbortSignal ) => getRemoval( school.id, signal ),
			[ school.id ]
		)
	);
	const remove = useOperation();
	const removal: SchoolRemoval | null = load.data;
	const [ typed, setTyped ] = useState( '' );
	const matches = null !== removal && typed.trim() === removal.confirm;

	return (
		<Modal
			title={ __( 'Delete this school permanently?', 'ohmylms' ) }
			onRequestClose={ () => ! remove.busy && onClose() }
			shouldCloseOnClickOutside={ false }
		>
			{ load.loading && (
				<p role="status">
					{ __( 'Counting what would be removed…', 'ohmylms' ) }
				</p>
			) }
			{ load.error && (
				<Notice status="error" isDismissible={ false }>
					{ load.error }
				</Notice>
			) }
			{ removal && (
				<form
					noValidate
					aria-label={ __( 'Delete school', 'ohmylms' ) }
					onSubmit={ ( event ) => {
						event.preventDefault();
						if ( ! matches ) {
							return;
						}
						void remove.run(
							( signal ) =>
								deleteSchool( school.id, typed.trim(), signal ),
							onDeleted
						);
					} }
				>
					<p>
						<strong>
							{ __( 'This cannot be undone.', 'ohmylms' ) }
						</strong>
					</p>
					<ul className="ul-disc">
						{ consequenceLines( removal.impact ).map( ( line ) => (
							<li key={ line }>{ line }</li>
						) ) }
					</ul>
					{ ! removal.can_delete && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'Archive the school before deleting it.',
								'ohmylms'
							) }
						</Notice>
					) }
					{ remove.error && (
						<Notice status="error" isDismissible={ false }>
							{ remove.error }
						</Notice>
					) }
					<TextControl
						label={ sprintf(
							/* translators: %s: school name. */
							__( 'Type "%s" to confirm', 'ohmylms' ),
							removal.confirm
						) }
						value={ typed }
						disabled={ remove.busy || ! removal.can_delete }
						autoComplete="off"
						onChange={ setTyped }
					/>
					<Button
						type="submit"
						variant="primary"
						isDestructive
						disabled={
							! matches || remove.busy || ! removal.can_delete
						}
						isBusy={ remove.busy }
					>
						{ __( 'Delete school permanently', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ remove.busy }
						onClick={ onClose }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</form>
			) }
		</Modal>
	);
}
