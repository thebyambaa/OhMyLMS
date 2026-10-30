import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import {
	getChildren,
	linkChild,
	listSchools,
	listSchoolStudents,
	revokeInvitation,
	unlinkChild,
} from './api';
import type { GuardianInvitation, Parent } from './types';

const NO_PEOPLE = { items: [], total: 0, totalPages: 0 };

/**
 * A parent's children: active links (revocable), pending guardian invitations (revocable) and a form
 * that invites the parent to link with a student of a school. Links become active only when the
 * parent signs in with their own account and accepts the single-use link.
 *
 * @param props          Component props.
 * @param props.parent   Parent account.
 * @param props.onClose  Close the dialog.
 * @param props.onChange Called after a link or invitation changed.
 */
export function ParentChildrenDialog( {
	parent,
	onClose,
	onChange,
}: {
	parent: Parent;
	onClose: () => void;
	onChange: () => void;
} ) {
	const operation = useOperation();
	const children = useRequest(
		useCallback(
			( signal: AbortSignal ) => getChildren( parent.id, signal ),
			[ parent.id ]
		)
	);
	const [ schoolQuery, setSchoolQuery ] = useState( '' );
	const [ schoolText, setSchoolText ] = useState( '' );
	const schools = useRequest(
		useCallback(
			( signal: AbortSignal ) => listSchools( 1, schoolQuery, signal ),
			[ schoolQuery ]
		)
	);
	const [ schoolId, setSchoolId ] = useState( 0 );
	const selectedSchool = schoolId || ( schools.data?.items[ 0 ]?.id ?? 0 );
	const [ studentText, setStudentText ] = useState( '' );
	const [ studentQuery, setStudentQuery ] = useState< string | null >( null );
	const students = useRequest(
		useCallback(
			( signal: AbortSignal ) =>
				selectedSchool && null !== studentQuery
					? listSchoolStudents( selectedSchool, studentQuery, signal )
					: Promise.resolve( NO_PEOPLE ),
			[ selectedSchool, studentQuery ]
		)
	);
	const [ invitation, setInvitation ] = useState< GuardianInvitation | null >(
		null
	);
	const [ confirming, setConfirming ] = useState( 0 );

	function changed() {
		children.retry();
		onChange();
	}

	return (
		<Modal
			title={ sprintf(
				/* translators: %s: parent name. */
				__( 'Children of %s', 'ohmylms' ),
				parent.display_name
			) }
			onRequestClose={ () => ! operation.busy && onClose() }
			shouldCloseOnClickOutside={ false }
		>
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			{ children.loading && (
				<p role="status">{ __( 'Loading children…', 'ohmylms' ) }</p>
			) }
			{ children.error && (
				<Notice status="error" isDismissible={ false }>
					{ children.error }{ ' ' }
					<Button variant="link" onClick={ children.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ children.data && (
				<>
					<h3>{ __( 'Linked children', 'ohmylms' ) }</h3>
					{ children.data.children.length ? (
						<ul aria-label={ __( 'Linked children', 'ohmylms' ) }>
							{ children.data.children.map( ( child ) => (
								<li key={ child.id }>
									{ sprintf(
										/* translators: 1: student name, 2: school name. */
										__( '%1$s (%2$s)', 'ohmylms' ),
										child.student_name,
										child.school_name
									) }{ ' ' }
									{ confirming === child.id ? (
										<Button
											variant="link"
											isDestructive
											disabled={ operation.busy }
											onClick={ () =>
												void operation.run(
													( signal ) =>
														unlinkChild(
															parent.id,
															child.id,
															signal
														),
													() => {
														setConfirming( 0 );
														changed();
													}
												)
											}
										>
											{ sprintf(
												/* translators: %s: student name. */
												__(
													'Confirm: unlink %s',
													'ohmylms'
												),
												child.student_name
											) }
										</Button>
									) : (
										<Button
											variant="link"
											isDestructive
											disabled={ operation.busy }
											onClick={ () =>
												setConfirming( child.id )
											}
										>
											{ sprintf(
												/* translators: %s: student name. */
												__( 'Unlink %s', 'ohmylms' ),
												child.student_name
											) }
										</Button>
									) }
								</li>
							) ) }
						</ul>
					) : (
						<p>{ __( 'No linked children.', 'ohmylms' ) }</p>
					) }
					{ children.data.pending.length > 0 && (
						<>
							<h3>
								{ __( 'Waiting for the parent', 'ohmylms' ) }
							</h3>
							<ul
								aria-label={ __(
									'Pending invitations',
									'ohmylms'
								) }
							>
								{ children.data.pending.map( ( pending ) => (
									<li key={ pending.id }>
										{ sprintf(
											/* translators: 1: student name, 2: school name. */
											__( '%1$s (%2$s)', 'ohmylms' ),
											pending.student_name,
											pending.school_name
										) }{ ' ' }
										<Button
											variant="link"
											disabled={ operation.busy }
											onClick={ () =>
												void operation.run(
													( signal ) =>
														revokeInvitation(
															parent.id,
															pending.id,
															signal
														),
													changed
												)
											}
										>
											{ sprintf(
												/* translators: %s: student name. */
												__(
													'Revoke invitation for %s',
													'ohmylms'
												),
												pending.student_name
											) }
										</Button>
									</li>
								) ) }
							</ul>
						</>
					) }
				</>
			) }
			<h3>{ __( 'Link a child', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'This creates a single-use link for the parent. The child is linked only after the parent signs in with their own account and accepts it.',
					'ohmylms'
				) }
			</p>
			{ invitation && (
				<Notice status="success" isDismissible={ false }>
					<TextControl
						label={ __(
							'Invitation link (shown once, valid for 48 hours)',
							'ohmylms'
						) }
						value={ invitation.url }
						readOnly
						onChange={ () => undefined }
					/>
					<p>
						{ __(
							'Share it privately with the parent. Nothing was emailed.',
							'ohmylms'
						) }
					</p>
				</Notice>
			) }
			<form
				role="search"
				aria-label={ __( 'Find a school', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					setSchoolId( 0 );
					setSchoolQuery( schoolText.trim() );
				} }
			>
				<TextControl
					label={ __( 'Find a school', 'ohmylms' ) }
					value={ schoolText }
					onChange={ setSchoolText }
				/>
				<Button type="submit" variant="secondary">
					{ __( 'Find school', 'ohmylms' ) }
				</Button>
			</form>
			<SelectControl
				label={ __( 'School', 'ohmylms' ) }
				value={ String( selectedSchool ) }
				disabled={ ! schools.data?.items.length }
				options={
					schools.data?.items.length
						? schools.data.items.map( ( school ) => ( {
								value: String( school.id ),
								label: school.name,
							} ) )
						: [
								{
									value: '0',
									label: __( 'No school found', 'ohmylms' ),
								},
							]
				}
				onChange={ ( value ) => {
					setSchoolId( Number( value ) );
					setStudentQuery( null );
				} }
			/>
			<form
				role="search"
				aria-label={ __( 'Find a student', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					setStudentQuery( studentText.trim() );
				} }
			>
				<TextControl
					label={ __( 'Student name', 'ohmylms' ) }
					value={ studentText }
					onChange={ setStudentText }
				/>
				<Button
					type="submit"
					variant="secondary"
					disabled={ ! selectedSchool }
				>
					{ __( 'Find student', 'ohmylms' ) }
				</Button>
			</form>
			{ students.error && (
				<Notice status="error" isDismissible={ false }>
					{ students.error }
				</Notice>
			) }
			{ null !== studentQuery &&
				students.data &&
				( students.data.items.length ? (
					<ul aria-label={ __( 'Matching students', 'ohmylms' ) }>
						{ students.data.items.map( ( student ) => (
							<li key={ student.id }>
								{ student.display_name }{ ' ' }
								<Button
									variant="secondary"
									disabled={ operation.busy }
									onClick={ () =>
										void operation.run(
											( signal ) =>
												linkChild(
													parent.id,
													selectedSchool,
													student.id,
													signal
												),
											( created ) => {
												setInvitation( created );
												changed();
											}
										)
									}
								>
									{ sprintf(
										/* translators: %s: student name. */
										__( 'Invite to link %s', 'ohmylms' ),
										student.display_name
									) }
								</Button>
							</li>
						) ) }
					</ul>
				) : (
					<p>
						{ __( 'No students found in this school.', 'ohmylms' ) }
					</p>
				) ) }
			<Button variant="tertiary" onClick={ onClose }>
				{ __( 'Close', 'ohmylms' ) }
			</Button>
		</Modal>
	);
}
