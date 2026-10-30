import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { ListTable } from '../../../ui/ListTable';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import { normalizeApiError } from '../../lib/api';
import {
	createBundle,
	deleteBundle,
	listBundles,
	moveMember,
	publishBundle,
	readBundle,
	saveMembers,
	searchTargets,
	updateBundle,
	type BundleDetail,
	type Member,
	type MemberType,
} from './bundles';

const STALE = 'ohmylms_bundle_stale';

/** Course bundles: ordered courses and curriculum items learners can follow together. */
export default function BundlesPage() {
	const load = useCallback(
		( signal: AbortSignal ) => listBundles( signal ),
		[]
	);
	const list = useRequest( load );
	const operation = useOperation();
	const [ detail, setDetail ] = useState< BundleDetail | null >( null );
	const [ fields, setFields ] = useState( { title: '', description: '' } );
	const [ members, setMembers ] = useState< Member[] >( [] );
	const [ adding, setAdding ] = useState( false );
	const [ stale, setStale ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const [ deleting, setDeleting ] = useState( false );
	const [ followers, setFollowers ] = useState( 0 );
	const dirtyFields =
		!! detail &&
		( fields.title !== detail.bundle.title ||
			fields.description !== detail.bundle.description );
	const dirtyMembers =
		!! detail &&
		JSON.stringify( members.map( ( row ) => [ row.type, row.id ] ) ) !==
			JSON.stringify(
				detail.members.map( ( row ) => [ row.type, row.id ] )
			);
	const guard = useUnsavedChangesGuard( dirtyFields || dirtyMembers );
	function show( next: BundleDetail ) {
		setDetail( next );
		setFields( {
			title: next.bundle.title,
			description: next.bundle.description,
		} );
		setMembers( next.members );
		setStale( false );
	}
	function write(
		work: ( signal: AbortSignal ) => Promise< BundleDetail >,
		done: string
	) {
		setMessage( '' );
		void operation.run(
			async ( signal ) => {
				try {
					return await work( signal );
				} catch ( failure ) {
					if ( normalizeApiError( failure ).code === STALE ) {
						setStale( true );
					}
					throw failure;
				}
			},
			( next ) => {
				show( next );
				setMessage( done );
				list.retry();
			}
		);
	}
	function reload() {
		if ( ! detail ) {
			return;
		}
		const keepFields = dirtyFields ? fields : null;
		const keepMembers = dirtyMembers ? members : null;
		void operation.run(
			( signal ) => readBundle( detail.bundle.id, signal ),
			( next ) => {
				show( next );
				if ( keepFields ) {
					setFields( keepFields );
				}
				if ( keepMembers ) {
					setMembers( keepMembers );
				}
				setMessage(
					__(
						'Bundle reloaded. Your unsaved edits were kept; save again to apply them.',
						'ohmylms'
					)
				);
			}
		);
	}
	const busy = operation.busy;
	const bundle = detail?.bundle;
	return (
		<section aria-labelledby="bundles-heading">
			<h1 id="bundles-heading">{ __( 'Course bundles', 'ohmylms' ) }</h1>
			<p>
				<Link to="/content-hub/curriculum">
					{ __( 'Back to curriculum', 'ohmylms' ) }
				</Link>
			</p>
			<p>
				{ __(
					'A bundle groups courses and curriculum items that learners follow together. Publishing shows it to learners; unpublishing keeps their follows.',
					'ohmylms'
				) }
			</p>
			{ list.loading && (
				<p role="status">{ __( 'Loading bundles…', 'ohmylms' ) }</p>
			) }
			{ list.error && (
				<Notice status="error" isDismissible={ false }>
					{ list.error }{ ' ' }
					<Button variant="secondary" onClick={ list.retry }>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( '' ) }>
					{ message }
				</Notice>
			) }
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
					{ stale && (
						<>
							{ ' ' }
							<Button
								variant="secondary"
								disabled={ busy }
								onClick={ reload }
							>
								{ __(
									'Reload bundle and keep my edits',
									'ohmylms'
								) }
							</Button>
						</>
					) }
				</Notice>
			) }
			<NewBundle
				busy={ busy }
				onCreate={ ( title, description ) =>
					write(
						( signal ) =>
							createBundle( title, description, signal ),
						__( 'Bundle created.', 'ohmylms' )
					)
				}
			/>
			{ list.data && (
				<ListTable
					caption={ __( 'Bundles', 'ohmylms' ) }
					rows={ list.data.bundles }
					rowKey={ ( row ) => row.id }
					emptyMessage={ __( 'No bundles yet.', 'ohmylms' ) }
					columns={ [
						{
							id: 'title',
							label: __( 'Title', 'ohmylms' ),
							primary: true,
							render: ( row ) => (
								<strong className="row-title">
									{ row.title }
								</strong>
							),
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( row ) =>
								row.status === 'published'
									? __( 'Published', 'ohmylms' )
									: __( 'Draft', 'ohmylms' ),
						},
						{
							id: 'members',
							label: __( 'Members', 'ohmylms' ),
							className: 'num',
							render: ( row ) => row.member_count,
						},
						{
							id: 'followers',
							label: __( 'Followers', 'ohmylms' ),
							className: 'num',
							render: ( row ) => row.follower_count,
						},
					] }
					rowActions={ ( row ) => [
						{
							key: 'edit',
							label: __( 'Edit', 'ohmylms' ),
							disabled: busy || dirtyFields || dirtyMembers,
							ariaLabel: sprintf(
								/* translators: %s: bundle title. */
								__( 'Edit bundle: %s', 'ohmylms' ),
								row.title
							),
							onClick: () =>
								void operation.run(
									( signal ) => readBundle( row.id, signal ),
									show
								),
						},
					] }
				/>
			) }
			{ detail && bundle && (
				<section aria-labelledby="bundle-editor-heading">
					<h2 id="bundle-editor-heading">{ bundle.title }</h2>
					<form
						onSubmit={ ( event ) => {
							event.preventDefault();
							write(
								( signal ) =>
									updateBundle( bundle, fields, signal ),
								__( 'Bundle details saved.', 'ohmylms' )
							);
						} }
					>
						<fieldset disabled={ busy }>
							<legend>{ __( 'Details', 'ohmylms' ) }</legend>
							<TextControl
								label={ __( 'Title', 'ohmylms' ) }
								value={ fields.title }
								maxLength={ 190 }
								required
								onChange={ ( title ) =>
									setFields( { ...fields, title } )
								}
							/>
							<TextareaControl
								label={ __( 'Description', 'ohmylms' ) }
								value={ fields.description }
								maxLength={ 2000 }
								onChange={ ( description ) =>
									setFields( { ...fields, description } )
								}
							/>
							<Button
								type="submit"
								variant="primary"
								disabled={
									stale ||
									! dirtyFields ||
									! fields.title.trim()
								}
							>
								{ __( 'Save details', 'ohmylms' ) }
							</Button>
						</fieldset>
					</form>
					<h3>{ __( 'Members', 'ohmylms' ) }</h3>
					{ members.length ? (
						<ol>
							{ members.map( ( member, index ) => (
								<li key={ `${ member.type }:${ member.id }` }>
									{ member.title || `#${ member.id }` } (
									{ member.type === 'course'
										? __( 'course', 'ohmylms' )
										: __( 'curriculum item', 'ohmylms' ) }
									{ ! member.available &&
										`, ${ __( 'missing', 'ohmylms' ) }` }
									){ ' ' }
									<Button
										variant="tertiary"
										disabled={ busy || index === 0 }
										onClick={ () =>
											setMembers(
												moveMember( members, index, -1 )
											)
										}
									>
										{ sprintf(
											/* translators: %s: member title. */
											__( 'Move %s up', 'ohmylms' ),
											member.title
										) }
									</Button>
									<Button
										variant="tertiary"
										disabled={
											busy || index === members.length - 1
										}
										onClick={ () =>
											setMembers(
												moveMember( members, index, 1 )
											)
										}
									>
										{ sprintf(
											/* translators: %s: member title. */
											__( 'Move %s down', 'ohmylms' ),
											member.title
										) }
									</Button>
									<Button
										variant="tertiary"
										isDestructive
										disabled={ busy }
										onClick={ () =>
											setMembers(
												members.filter(
													( _row, at ) => at !== index
												)
											)
										}
									>
										{ sprintf(
											/* translators: %s: member title. */
											__( 'Remove %s', 'ohmylms' ),
											member.title
										) }
									</Button>
								</li>
							) ) }
						</ol>
					) : (
						<p>
							{ __(
								'This bundle has no members yet.',
								'ohmylms'
							) }
						</p>
					) }
					<Button
						variant="secondary"
						disabled={
							busy ||
							members.length >= ( list.data?.maxMembers ?? 100 )
						}
						onClick={ () => setAdding( true ) }
					>
						{ __( 'Add members', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="primary"
						disabled={ busy || stale || ! dirtyMembers }
						onClick={ () =>
							write(
								( signal ) =>
									saveMembers( bundle, members, signal ),
								__( 'Members saved.', 'ohmylms' )
							)
						}
					>
						{ __( 'Save members', 'ohmylms' ) }
					</Button>
					<h3>{ __( 'Publishing', 'ohmylms' ) }</h3>
					<p>
						{ bundle.status === 'published'
							? __(
									'Learners can see and follow this bundle.',
									'ohmylms'
								)
							: __(
									'Only administrators can see this draft.',
									'ohmylms'
								) }
					</p>
					<Button
						variant="secondary"
						disabled={
							busy || stale || dirtyFields || dirtyMembers
						}
						onClick={ () =>
							write(
								( signal ) =>
									publishBundle(
										bundle,
										bundle.status !== 'published',
										signal
									),
								bundle.status === 'published'
									? __( 'Bundle unpublished.', 'ohmylms' )
									: __( 'Bundle published.', 'ohmylms' )
							)
						}
					>
						{ bundle.status === 'published'
							? __( 'Unpublish', 'ohmylms' )
							: __( 'Publish', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="secondary"
						isDestructive
						disabled={ busy || stale }
						onClick={ () => {
							setFollowers( bundle.follower_count );
							setDeleting( true );
						} }
					>
						{ __( 'Delete bundle…', 'ohmylms' ) }
					</Button>
					{ ( dirtyFields || dirtyMembers ) && (
						<p>
							{ __(
								'Save or discard your edits before publishing.',
								'ohmylms'
							) }
						</p>
					) }
				</section>
			) }
			{ adding && bundle && (
				<MemberSearch
					existing={ members }
					onAdd={ ( member ) => setMembers( [ ...members, member ] ) }
					onClose={ () => setAdding( false ) }
				/>
			) }
			{ deleting && bundle && (
				<Modal
					title={ sprintf(
						/* translators: %s: bundle title. */
						__( 'Delete “%s”?', 'ohmylms' ),
						bundle.title
					) }
					onRequestClose={ () => ! busy && setDeleting( false ) }
					shouldCloseOnClickOutside={ false }
				>
					<p>
						{ followers
							? sprintf(
									/* translators: %d: number of learners. */
									__(
										'%d learners follow this bundle. Deleting it removes it from their dashboards.',
										'ohmylms'
									),
									followers
								)
							: __(
									'The courses and curriculum items in it are not deleted.',
									'ohmylms'
								) }
					</p>
					<Button
						variant="primary"
						isDestructive
						isBusy={ busy }
						disabled={ busy }
						onClick={ () => {
							setMessage( '' );
							void operation.run(
								( signal ) =>
									deleteBundle(
										bundle,
										followers > 0,
										signal
									),
								() => {
									setDeleting( false );
									setDetail( null );
									setMessage(
										__( 'Bundle deleted.', 'ohmylms' )
									);
									list.retry();
								}
							);
						} }
					>
						{ __( 'Delete bundle', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="secondary"
						disabled={ busy }
						onClick={ () => setDeleting( false ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
			{ guard.pendingPath && (
				<Modal
					title={ __( 'Leave with unsaved changes?', 'ohmylms' ) }
					onRequestClose={ guard.stay }
				>
					<Button onClick={ guard.stay }>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>
					<Button onClick={ guard.leave }>
						{ __( 'Discard edits and leave', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</section>
	);
}

function NewBundle( {
	busy,
	onCreate,
}: {
	busy: boolean;
	onCreate: ( title: string, description: string ) => void;
} ) {
	const [ title, setTitle ] = useState( '' );
	return (
		<form
			aria-label={ __( 'New bundle', 'ohmylms' ) }
			onSubmit={ ( event ) => {
				event.preventDefault();
				if ( title.trim() ) {
					onCreate( title, '' );
					setTitle( '' );
				}
			} }
		>
			<TextControl
				label={ __( 'New bundle title', 'ohmylms' ) }
				value={ title }
				maxLength={ 190 }
				disabled={ busy }
				onChange={ setTitle }
			/>
			<Button
				type="submit"
				variant="primary"
				disabled={ busy || ! title.trim() }
			>
				{ __( 'Add bundle', 'ohmylms' ) }
			</Button>
		</form>
	);
}

function MemberSearch( {
	existing,
	onAdd,
	onClose,
}: {
	existing: Member[];
	onAdd: ( member: Member ) => void;
	onClose: () => void;
} ) {
	const [ type, setType ] = useState< MemberType >( 'course' );
	const [ query, setQuery ] = useState( '' );
	const [ search, setSearch ] = useState< string | null >( null );
	const [ rows, setRows ] = useState< Member[] >( [] );
	const [ error, setError ] = useState( '' );
	const [ loading, setLoading ] = useState( false );
	useEffect( () => {
		if ( search === null ) {
			return;
		}
		const controller = new AbortController();
		setLoading( true );
		setError( '' );
		searchTargets( type, search, controller.signal ).then(
			( result ) => {
				if ( ! controller.signal.aborted ) {
					setRows( result );
					setLoading( false );
				}
			},
			( failure ) => {
				if ( ! controller.signal.aborted ) {
					setError( normalizeApiError( failure ).message );
					setLoading( false );
				}
			}
		);
		return () => controller.abort();
	}, [ type, search ] );
	const taken = new Set(
		existing.map( ( row ) => `${ row.type }:${ row.id }` )
	);
	return (
		<Modal
			title={ __( 'Add members', 'ohmylms' ) }
			onRequestClose={ onClose }
		>
			<form
				role="search"
				onSubmit={ ( event ) => {
					event.preventDefault();
					setSearch( query.trim() );
				} }
			>
				<SelectControl
					label={ __( 'Member type', 'ohmylms' ) }
					value={ type }
					options={ [
						{ value: 'course', label: __( 'Courses', 'ohmylms' ) },
						{
							value: 'curriculum',
							label: __( 'Curriculum items', 'ohmylms' ),
						},
					] }
					onChange={ ( value ) => {
						setType( value as MemberType );
						setRows( [] );
					} }
				/>
				<TextControl
					label={ __( 'Search by title', 'ohmylms' ) }
					value={ query }
					maxLength={ 200 }
					onChange={ setQuery }
				/>
				<Button type="submit" variant="secondary">
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
			<div aria-live="polite">
				{ loading && __( 'Searching…', 'ohmylms' ) }
				{ ! loading &&
					search !== null &&
					! error &&
					! rows.length &&
					__( 'Nothing found.', 'ohmylms' ) }
			</div>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<ul>
				{ rows.map( ( row ) => (
					<li key={ row.id }>
						{ row.title }{ ' ' }
						<Button
							variant="secondary"
							disabled={ taken.has(
								`${ row.type }:${ row.id }`
							) }
							onClick={ () => onAdd( row ) }
						>
							{ taken.has( `${ row.type }:${ row.id }` )
								? __( 'Added', 'ohmylms' )
								: sprintf(
										/* translators: %s: course or curriculum item title. */
										__( 'Add %s', 'ohmylms' ),
										row.title
									) }
						</Button>
					</li>
				) ) }
			</ul>
			<Button variant="primary" onClick={ onClose }>
				{ __( 'Done', 'ohmylms' ) }
			</Button>
		</Modal>
	);
}
