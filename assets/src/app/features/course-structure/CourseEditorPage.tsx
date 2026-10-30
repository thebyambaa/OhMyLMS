import { Button, Modal, Notice } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import { normalizeApiError, type ApiError } from '../../lib/api';
import { CoursePlacement } from '../curriculum';
import { deleteUnit, readOutline, saveOutline, type ResourceType } from './api';
import { ResourcePicker } from './ResourcePicker';
import { UnitEditor } from './UnitEditor';
import { LearningSettings } from './LearningSettings';
import {
	move,
	moveActivity,
	moveToUnit,
	removeNewUnit,
	savePayload,
	type Outline,
	type Activity,
	type Unit,
} from './model';
import { useRequest } from '../../hooks/useRequest';
import { newId } from '../../lib/uuid';

/**
 * Outline tab of the course editor: loads the saved draft outline of one course.
 *
 * @param root0
 * @param root0.id Course ID.
 */
export function CourseOutline( { id }: { id: number } ) {
	const load = useCallback(
		( signal: AbortSignal ) => readOutline( id, signal ),
		[ id ]
	);
	const request = useRequest( load );
	return (
		<>
			<h2>{ __( 'Outline', 'ohmylms' ) }</h2>
			{ request.loading && (
				<p role="status">
					{ __( 'Loading course structure…', 'ohmylms' ) }
				</p>
			) }
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }
					<Button onClick={ request.retry }>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ request.data && (
				<OutlineWorkspace
					key={ `${ id }:${ request.data.revision }` }
					initial={ request.data }
					reload={ request.retry }
				/>
			) }
		</>
	);
}

function snapshot( outline: Outline ) {
	return JSON.stringify( {
		revision: outline.revision,
		units: outline.units,
		program: outline.program,
	} );
}

/** Conflicts that are not stale reads: a busy lock, or a unit the server refuses to delete. */
const REFUSALS = [
	'ohmylms_program_busy',
	'ohmylms_chapter_in_use',
	'ohmylms_chapter_published',
];

function failureMessage( problem: ApiError ) {
	return 409 === problem.status && ! REFUSALS.includes( problem.code )
		? __(
				'This course changed in another session. Your edits are kept. Reload the saved outline before trying again.',
				'ohmylms'
			)
		: problem.message;
}

export function OutlineWorkspace( {
	initial,
	reload,
}: {
	initial: Outline;
	reload: () => void;
} ) {
	const [ draft, setDraft ] = useState( initial );
	const [ saved, setSaved ] = useState( snapshot( initial ) );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ message, setMessage ] = useState( '' );
	const [ reloadConfirm, setReloadConfirm ] = useState( false );
	const [ detach, setDetach ] = useState< Activity | null >( null );
	const [ deleting, setDeleting ] = useState< Unit | null >( null );
	const [ picker, setPicker ] = useState< {
		unit: number | string;
		type: ResourceType;
	} | null >( null );
	const operation = useRef< AbortController | null >( null );
	useEffect( () => () => operation.current?.abort(), [] );
	const dirty = snapshot( draft ) !== saved;
	const guard = useUnsavedChangesGuard( dirty );
	function change( next: Outline ) {
		setDraft( next );
		setMessage( '' );
	}
	function accept( result: Outline, notice: string ) {
		setDraft( result );
		setSaved( snapshot( result ) );
		setMessage( notice );
	}
	async function write(
		work: ( signal: AbortSignal ) => Promise< Outline >,
		notice: string
	) {
		if ( operation.current ) {
			return;
		}
		const controller = new AbortController();
		operation.current = controller;
		setBusy( true );
		setError( '' );
		setMessage( '' );
		try {
			const result = await work( controller.signal );
			if ( ! controller.signal.aborted ) {
				accept( result, notice );
			}
		} catch ( failure ) {
			if ( ! controller.signal.aborted ) {
				setError( failureMessage( normalizeApiError( failure ) ) );
			}
		} finally {
			if ( ! controller.signal.aborted ) {
				operation.current = null;
				setBusy( false );
			}
		}
	}
	function save() {
		let payload: ReturnType< typeof savePayload >;
		try {
			payload = savePayload( draft );
		} catch ( failure ) {
			setError( normalizeApiError( failure ).message );
			return;
		}
		void write(
			( signal ) => saveOutline( draft, payload, signal ),
			__(
				'Outline draft saved. Learner publication is unchanged.',
				'ohmylms'
			)
		);
	}
	function confirmDelete( unit: Unit ) {
		setDeleting( null );
		if ( 'string' === typeof unit.id ) {
			change( removeNewUnit( draft, unit.id ) );
			return;
		}
		const id = unit.id;
		void write(
			( signal ) => deleteUnit( draft, id, signal ),
			__(
				'Unit deleted. Its activities are kept as unplaced items in the draft.',
				'ohmylms'
			)
		);
	}
	function add( resource: { id: number; title: string } ) {
		if ( ! picker ) {
			return;
		}
		const { unit, type } = picker;
		setPicker( null );
		if (
			draft.program.items.some(
				( item ) =>
					item.type === type && item.content_id === resource.id
			)
		) {
			setError(
				__( 'This item is already placed in the course.', 'ohmylms' )
			);
			return;
		}
		setError( '' );
		const item: Activity = {
			id: newId(),
			type,
			content_id: resource.id,
			chapter_id: 0,
			required: true,
			name: resource.title,
		};
		change( {
			...draft,
			program: {
				...draft.program,
				items: moveToUnit(
					[ ...draft.program.items, item ],
					item.id,
					unit
				),
			},
		} );
	}
	const unplaced = draft.program.items.filter(
		( item ) =>
			! draft.units.some( ( unit ) => unit.id === item.chapter_id )
	);
	return (
		<section aria-label={ __( 'Outline editor', 'ohmylms' ) }>
			<p>
				<Link to={ `/courses/${ draft.course_id }/settings` }>
					{ __( 'Course settings', 'ohmylms' ) }
				</Link>{ ' ' }
				·{ ' ' }
				<Link to={ `/courses/${ draft.course_id }/publish` }>
					{ __( 'Preview and publish', 'ohmylms' ) }
				</Link>{ ' ' }
				·{ ' ' }
				<Link to="/content-hub/curriculum">
					{ __( 'Curriculum', 'ohmylms' ) }
				</Link>
			</p>
			<p>
				{ __(
					'Edit the draft units and activity placements. Saving here does not publish to learners.',
					'ohmylms'
				) }
			</p>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ message && (
				<Notice status="success" isDismissible={ false }>
					{ message }
				</Notice>
			) }
			<p role="status">
				{ dirty
					? __( 'Unsaved changes', 'ohmylms' )
					: __( 'Saved draft', 'ohmylms' ) }
			</p>
			<Button
				variant="primary"
				disabled={ busy || ! dirty }
				isBusy={ busy }
				onClick={ save }
			>
				{ __( 'Save outline draft', 'ohmylms' ) }
			</Button>
			<Button
				disabled={ busy }
				onClick={ () =>
					dirty ? setReloadConfirm( true ) : reload()
				}
			>
				{ __( 'Reload saved outline', 'ohmylms' ) }
			</Button>
			<LearningSettings
				draft={ draft }
				change={ change }
				disabled={ busy }
			/>
			<fieldset disabled={ busy }>
				<legend>{ __( 'Units and activities', 'ohmylms' ) }</legend>
				{ draft.units.map( ( unit, index ) => (
					<UnitEditor
						key={ unit.id }
						unit={ unit }
						units={ draft.units }
						items={ draft.program.items.filter(
							( item ) => item.chapter_id === unit.id
						) }
						first={ index === 0 }
						last={ index === draft.units.length - 1 }
						onChange={ ( patch ) =>
							change( {
								...draft,
								units: draft.units.map( ( row ) =>
									row.id === unit.id
										? { ...row, ...patch }
										: row
								),
							} )
						}
						onMove={ ( offset ) =>
							change( {
								...draft,
								units: move( draft.units, index, offset ),
							} )
						}
						onAdd={ ( type ) =>
							setPicker( { unit: unit.id, type } )
						}
						onDelete={ () => setDeleting( unit ) }
						onDetach={ setDetach }
						onMoveItem={ ( id, offset ) =>
							change( {
								...draft,
								program: {
									...draft.program,
									items: moveActivity(
										draft.program.items,
										id,
										offset
									),
								},
							} )
						}
						onMoveToUnit={ ( id, target ) =>
							change( {
								...draft,
								program: {
									...draft.program,
									items: moveToUnit(
										draft.program.items,
										id,
										target
									),
								},
							} )
						}
					/>
				) ) }

				<Button
					onClick={ () =>
						change( {
							...draft,
							units: [
								...draft.units,
								{
									id: newId(),
									title: __( 'New unit', 'ohmylms' ),
									number: '',
									description: '',
								},
							],
						} )
					}
				>
					{ __( 'Add unit', 'ohmylms' ) }
				</Button>
			</fieldset>
			{ unplaced.length > 0 && (
				<section>
					<h3>
						{ __( 'Unplaced activities (preserved)', 'ohmylms' ) }
					</h3>
					<ul>
						{ unplaced.map( ( item ) => (
							<li key={ item.id }>
								{ item.name || String( item.content_id ) }{ ' ' }
								{ draft.units.length > 0 && (
									<Button
										variant="link"
										disabled={ busy }
										onClick={ () =>
											change( {
												...draft,
												program: {
													...draft.program,
													items: moveToUnit(
														draft.program.items,
														item.id,
														draft.units[ 0 ]!.id
													),
												},
											} )
										}
									>
										{ sprintf(
											/* translators: %s: unit title. */
											__( 'Place in %s', 'ohmylms' ),
											draft.units[ 0 ]!.title
										) }
									</Button>
								) }
							</li>
						) ) }
					</ul>
				</section>
			) }
			{ picker && (
				<ResourcePicker
					course={ draft.course_id }
					type={ picker.type }
					onClose={ () => setPicker( null ) }
					onSelect={ add }
				/>
			) }
			{ deleting && (
				<Modal
					title={ __( 'Delete this unit?', 'ohmylms' ) }
					onRequestClose={ () => setDeleting( null ) }
				>
					<p>
						{ 'string' === typeof deleting.id
							? __(
									'This unit has not been saved yet. Its activities stay in the draft as unplaced items.',
									'ohmylms'
								)
							: __(
									'The unit is deleted now. Its activities stay in the draft as unplaced items; lessons, quizzes, assignments and learner history are kept. A unit the published program still uses cannot be deleted.',
									'ohmylms'
								) }
					</p>
					{ 'number' === typeof deleting.id && dirty && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'Save or reload the outline before deleting a saved unit.',
								'ohmylms'
							) }
						</Notice>
					) }
					<Button onClick={ () => setDeleting( null ) }>
						{ __( 'Keep unit', 'ohmylms' ) }
					</Button>
					<Button
						variant="primary"
						isDestructive
						disabled={
							busy || ( 'number' === typeof deleting.id && dirty )
						}
						onClick={ () => confirmDelete( deleting ) }
					>
						{ __( 'Delete unit', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
			{ reloadConfirm && (
				<Modal
					title={ __( 'Discard unsaved outline edits?', 'ohmylms' ) }
					onRequestClose={ () => setReloadConfirm( false ) }
				>
					<Button onClick={ () => setReloadConfirm( false ) }>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>
					<Button variant="primary" onClick={ reload }>
						{ __( 'Discard and reload', 'ohmylms' ) }
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
			{ detach && (
				<Modal
					title={ __( 'Detach this placement?', 'ohmylms' ) }
					onRequestClose={ () => setDetach( null ) }
				>
					<p>
						{ __(
							'The reusable item and learner history remain. This placement is removed only when you save the outline draft.',
							'ohmylms'
						) }
					</p>
					<Button onClick={ () => setDetach( null ) }>
						{ __( 'Keep placement', 'ohmylms' ) }
					</Button>
					<Button
						onClick={ () => {
							change( {
								...draft,
								program: {
									...draft.program,
									items: draft.program.items.filter(
										( item ) => item.id !== detach.id
									),
								},
							} );
							setDetach( null );
						} }
					>
						{ __( 'Detach placement', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
			<CoursePlacement course={ draft.course_id } />
		</section>
	);
}
