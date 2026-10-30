import { Table } from '../../../ui/Table';
import {
	Button,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { Pagination } from '../../components/Pagination';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import {
	addMember,
	archiveClass,
	getClass,
	listCandidates,
	listMembers,
	removeMember,
	restoreClass,
	updateClass,
} from './api';
import { ConflictNotice, LeaveNotice } from './EditorNotices';
import { ListState } from './ListControls';
import {
	classStatusLabel,
	isConflict,
	MAX_NAME,
	roleLabel,
	validateClass,
} from './model';
import type {
	ClassInput,
	ClassRole,
	FormErrors,
	PersonRef,
	SchoolClass,
} from './types';

/** Class editor at `#/account-hub/classes/<id>`: details, archive/restore and the roster. */
export default function ClassEditorPage() {
	const id = Number( useParams().id );
	const valid = Number.isSafeInteger( id ) && id > 0;
	const request = useRequest(
		useCallback(
			( signal: AbortSignal ) =>
				valid
					? getClass( id, signal )
					: Promise.reject(
							new Error( __( 'Class not found.', 'ohmylms' ) )
						),
			[ id, valid ]
		)
	);

	return (
		<>
			<p>
				{ request.data?.school_id ? (
					<Link
						to={ `/account-hub/schools/${ request.data.school_id }` }
					>
						{ sprintf(
							/* translators: %s: school name. */
							__( '← %s', 'ohmylms' ),
							request.data.school_name ?? ''
						) }
					</Link>
				) : (
					<Link to="/account-hub?tab=classes">
						{ __( '← All classes', 'ohmylms' ) }
					</Link>
				) }
			</p>
			{ request.loading && (
				<p role="status">{ __( 'Loading class…', 'ohmylms' ) }</p>
			) }
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }{ ' ' }
					<Button variant="link" onClick={ request.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ request.data && (
				<ClassEditor
					key={ request.data.version }
					initial={ request.data }
					onReload={ request.retry }
				/>
			) }
		</>
	);
}

function ClassEditor( {
	initial,
	onReload,
}: {
	initial: SchoolClass;
	onReload: () => void;
} ) {
	const [ item, setItem ] = useState( initial );
	const [ form, setForm ] = useState< ClassInput >( {
		name: initial.name,
		subject: initial.subject,
		grade: initial.grade,
	} );
	const [ errors, setErrors ] = useState< FormErrors< ClassInput > >( {} );
	const [ conflict, setConflict ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const operation = useOperation();
	const [ rosterVersion, setRosterVersion ] = useState( 0 );
	const archived = 'archived' === item.status;
	const dirty =
		form.name !== item.name ||
		form.subject !== item.subject ||
		form.grade !== item.grade;
	const guard = useUnsavedChangesGuard( dirty );

	function accept( result: SchoolClass, text: string ) {
		setItem( result );
		setForm( {
			name: result.name,
			subject: result.subject,
			grade: result.grade,
		} );
		setMessage( text );
	}

	function save() {
		const found = validateClass( form );
		setErrors( found );
		setMessage( '' );
		if ( Object.keys( found ).length ) {
			return;
		}
		setConflict( false );
		void operation.run(
			async ( signal ) => {
				try {
					return await updateClass(
						item.id,
						form,
						item.version,
						signal
					);
				} catch ( failure ) {
					setConflict( isConflict( failure ) );
					throw failure;
				}
			},
			( result ) => accept( result, __( 'Class saved.', 'ohmylms' ) )
		);
	}

	return (
		<>
			<h2>{ item.name }</h2>
			<p>
				{ sprintf(
					/* translators: 1: school name, 2: academic year, 3: status. */
					__( '%1$s · %2$s · %3$s', 'ohmylms' ),
					item.school_name ?? __( 'Independent class', 'ohmylms' ),
					item.academic_year_label ?? '—',
					classStatusLabel( item.status )
				) }
			</p>
			<LeaveNotice guard={ guard } />
			{ conflict && <ConflictNotice onReload={ onReload } /> }
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( '' ) }>
					{ message }
				</Notice>
			) }
			{ operation.error && ! conflict && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			{ archived && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'This class is archived. Restore it to change its details or roster.',
						'ohmylms'
					) }
				</Notice>
			) }
			<form
				noValidate
				aria-label={ __( 'Class details', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					save();
				} }
			>
				<TextControl
					label={ __( 'Class name', 'ohmylms' ) }
					value={ form.name }
					maxLength={ MAX_NAME }
					required
					disabled={ archived || operation.busy }
					help={ errors.name }
					onChange={ ( name ) => setForm( { ...form, name } ) }
				/>
				<TextControl
					label={ __( 'Subject', 'ohmylms' ) }
					value={ form.subject }
					maxLength={ 100 }
					disabled={ archived || operation.busy }
					help={ errors.subject }
					onChange={ ( subject ) => setForm( { ...form, subject } ) }
				/>
				<TextControl
					label={ __( 'Grade', 'ohmylms' ) }
					value={ form.grade }
					maxLength={ 50 }
					disabled={ archived || operation.busy }
					help={ errors.grade }
					onChange={ ( grade ) => setForm( { ...form, grade } ) }
				/>
				<Button
					type="submit"
					variant="primary"
					disabled={ archived || operation.busy || ! dirty }
					isBusy={ operation.busy }
				>
					{ __( 'Save class', 'ohmylms' ) }
				</Button>{ ' ' }
				{ archived ? (
					<Button
						variant="secondary"
						disabled={ operation.busy }
						onClick={ () =>
							void operation.run(
								( signal ) => restoreClass( item.id, signal ),
								( result ) =>
									accept(
										result,
										__( 'Class restored.', 'ohmylms' )
									)
							)
						}
					>
						{ __( 'Restore class', 'ohmylms' ) }
					</Button>
				) : (
					<Button
						variant="secondary"
						isDestructive
						disabled={ operation.busy || dirty }
						onClick={ () =>
							void operation.run(
								( signal ) => archiveClass( item.id, signal ),
								( result ) =>
									accept(
										result,
										__(
											'Class archived. Its roster and history are kept.',
											'ohmylms'
										)
									)
							)
						}
					>
						{ __( 'Archive class', 'ohmylms' ) }
					</Button>
				) }
			</form>
			<Members
				key={ rosterVersion }
				item={ item }
				onChange={ () => setRosterVersion( ( value ) => value + 1 ) }
			/>
			{ ! archived && item.school_id > 0 && (
				<AddMembers
					item={ item }
					onAdded={ () => setRosterVersion( ( value ) => value + 1 ) }
				/>
			) }
			{ ! item.school_id && (
				<p>
					{ __(
						'Students join an independent class through its teacher’s invitation link.',
						'ohmylms'
					) }
				</p>
			) }
		</>
	);
}

function Members( {
	item,
	onChange,
}: {
	item: SchoolClass;
	onChange: () => void;
} ) {
	const [ page, setPage ] = useState( 1 );
	const request = useRequest(
		useCallback(
			( signal: AbortSignal ) => listMembers( item.id, page, signal ),
			[ item.id, page ]
		)
	);
	const operation = useOperation();
	const data = request.data ?? { items: [], total: 0, totalPages: 0 };
	const archived = 'archived' === item.status;

	return (
		<section aria-label={ __( 'Class roster', 'ohmylms' ) }>
			<h3>{ __( 'Roster', 'ohmylms' ) }</h3>
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			<ListState
				loading={ request.loading }
				error={ request.error }
				total={ data.total }
				loadingText={ __( 'Loading roster…', 'ohmylms' ) }
				emptyText={ __( 'Nobody is in this class yet.', 'ohmylms' ) }
				onRetry={ request.retry }
			>
				<Table caption={ __( 'Class roster', 'ohmylms' ) } striped>
					<thead>
						<tr>
							{ [
								__( 'Name', 'ohmylms' ),
								__( 'Role', 'ohmylms' ),
								__( 'Actions', 'ohmylms' ),
							].map( ( title ) => (
								<th key={ title } scope="col">
									{ title }
								</th>
							) ) }
						</tr>
					</thead>
					<tbody>
						{ data.items.map( ( member ) => (
							<tr key={ member.id }>
								<th scope="row">{ member.display_name }</th>
								<td>{ roleLabel( member.role ) }</td>
								<td>
									<Button
										variant="link"
										isDestructive
										disabled={ archived || operation.busy }
										onClick={ () =>
											void operation.run(
												( signal ) =>
													removeMember(
														item.id,
														member.user_id,
														member.role,
														signal
													),
												onChange
											)
										}
										aria-label={ sprintf(
											/* translators: %s: person name. */
											__(
												'Remove %s from the class',
												'ohmylms'
											),
											member.display_name
										) }
									>
										{ __( 'Remove', 'ohmylms' ) }
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</Table>
				<Pagination
					page={ page }
					pages={ data.totalPages }
					busy={ request.loading }
					onChange={ setPage }
				/>
			</ListState>
		</section>
	);
}

function AddMembers( {
	item,
	onAdded,
}: {
	item: SchoolClass;
	onAdded: () => void;
} ) {
	const [ role, setRole ] = useState< ClassRole >( 'student' );
	const [ text, setText ] = useState( '' );
	const [ query, setQuery ] = useState< string | null >( null );
	const results = useRequest(
		useCallback(
			( signal: AbortSignal ) =>
				null === query
					? Promise.resolve( null )
					: listCandidates( item.id, role, query, signal ),
			[ item.id, role, query ]
		)
	);
	const operation = useOperation();

	function add( person: PersonRef ) {
		void operation.run(
			( signal ) => addMember( item.id, person.id, role, signal ),
			() => {
				results.retry();
				onAdded();
			}
		);
	}

	return (
		<section aria-label={ __( 'Add people to the class', 'ohmylms' ) }>
			<h3>{ __( 'Add people', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'Only people on this school’s roster can join. Course access is not changed.',
					'ohmylms'
				) }
			</p>
			<form
				role="search"
				aria-label={ __( 'Search the school roster', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					setQuery( text.trim() );
				} }
			>
				<SelectControl
					label={ __( 'Add as', 'ohmylms' ) }
					value={ role }
					options={ [
						{ value: 'student', label: roleLabel( 'student' ) },
						{ value: 'teacher', label: roleLabel( 'teacher' ) },
					] }
					onChange={ ( value ) => setRole( value as ClassRole ) }
				/>
				<TextControl
					label={ __( 'Name', 'ohmylms' ) }
					value={ text }
					onChange={ setText }
				/>
				<Button type="submit" variant="secondary">
					{ __( 'Search roster', 'ohmylms' ) }
				</Button>
			</form>
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			{ results.error && (
				<Notice status="error" isDismissible={ false }>
					{ results.error }
				</Notice>
			) }
			{ results.data &&
				( results.data.items.length ? (
					<ul aria-label={ __( 'Roster matches', 'ohmylms' ) }>
						{ results.data.items.map( ( person ) => (
							<li key={ person.id }>
								{ person.display_name }{ ' ' }
								<Button
									variant="secondary"
									disabled={ operation.busy }
									onClick={ () => add( person ) }
								>
									{ sprintf(
										/* translators: 1: person name, 2: role. */
										__( 'Add %1$s as %2$s', 'ohmylms' ),
										person.display_name,
										roleLabel( role ).toLowerCase()
									) }
								</Button>
							</li>
						) ) }
					</ul>
				) : (
					<p>
						{ __(
							'Nobody on the school roster matches, or they are already in the class.',
							'ohmylms'
						) }
					</p>
				) ) }
		</section>
	);
}
