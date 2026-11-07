import { Button, Modal, Notice, VisuallyHidden } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useSearchParams } from 'react-router-dom';
import { useBuilderSave } from '../../hooks/useBuilderSave';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import { isCourseEditorStep } from '../../routing/links';
import { normalizeApiError, type ApiError } from '../../lib/api';

import { deleteUnit, readOutline, saveOutline, type ResourceType } from './api';
import { ResourcePicker } from './ResourcePicker';
import { UnitEditor } from './UnitEditor';

import { SkillPlan, type PlanSelection } from './SkillPlan';

import '../../styles/authoring.css';
import {
	removeNewUnit,
	savePayload,
	type Outline,
	type Activity,
	type Unit,
} from './model';
import { describeMove, dropOn, placeActivity, unitKey } from './reorder';
import { useDragMove } from './useDragMove';
import { useRequest } from '../../hooks/useRequest';
import { newId } from '../../lib/uuid';

/**
 * Outline tab of the course editor: loads the saved draft outline of one course. `?focus=<type>:<id>`
 * (for example from the assignment editor) opens it on that resource's placement.
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
	const [ params ] = useSearchParams();
	return (
		<>
			<h2 className="screen-reader-text">
				{ __( 'Outline', 'ohmylms' ) }
			</h2>
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
					opening={ params.get( 'focus' ) ?? '' }
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
	opening = '',
}: {
	initial: Outline;
	reload: () => void;
	/** `<type>:<content id>` of a placement to open the outline on. */
	opening?: string;
} ) {
	const [ targeted ] = useState( () =>
		initial.program.items.find(
			( item ) => `${ item.type }:${ item.content_id }` === opening
		)
	);
	const [ draft, setDraft ] = useState( initial );
	// What the skill panel follows: a unit, or a lesson or quiz placed in a unit.
	const [ selection, setSelection ] = useState< PlanSelection | null >( () =>
		targeted && [ 'lesson', 'quiz' ].includes( targeted.type )
			? {
					kind: targeted.type as 'lesson' | 'quiz',
					id: targeted.content_id,
					unitId: targeted.chapter_id ?? 0,
					name: targeted.name || String( targeted.content_id ),
				}
			: null
	);
	// Changes whenever the saved outline changes, so the skill panel reads the course again.
	const [ planVersion, setPlanVersion ] = useState( 0 );
	const [ skillCounts, setSkillCounts ] = useState<
		ReadonlyMap< number, number >
	>( () => new Map() );
	// Topics chosen for each saved unit, as the skill panel reports them.
	const [ unitTopics, setUnitTopics ] = useState<
		Readonly< Record< number, number[] > >
	>( {} );
	const [ folded, setFolded ] = useState< ReadonlySet< Unit[ 'id' ] > >(
		() => new Set()
	);
	const selectUnit = ( unit: Unit ) =>
		setSelection( ( current ) =>
			'unit' === current?.kind && current.id === unit.id
				? null
				: { kind: 'unit', id: unit.id, name: unit.title }
		);
	const selectContent = ( item: Activity ) =>
		setSelection( ( current ) =>
			current &&
			'unit' !== current.kind &&
			current.kind === item.type &&
			current.id === item.content_id
				? null
				: {
						kind: item.type as 'lesson' | 'quiz',
						id: item.content_id,
						unitId: item.chapter_id ?? 0,
						name: item.name || String( item.content_id ),
					}
		);
	/**
	 * Select a lesson or quiz by ID (from a skill row) and bring its row into view.
	 *
	 * @param kind    `lesson` or `quiz`.
	 * @param content Lesson or quiz ID.
	 */
	function revealContent( kind: 'lesson' | 'quiz', content: number ) {
		const item = draft.program.items.find(
			( row ) => kind === row.type && row.content_id === content
		);
		if ( ! item ) {
			return;
		}
		if ( item.chapter_id ) {
			setFolded( ( current ) => {
				const next = new Set( current );
				next.delete( item.chapter_id! );
				return next;
			} );
		}
		setSelection( {
			kind,
			id: item.content_id,
			unitId: item.chapter_id ?? 0,
			name: item.name || String( item.content_id ),
		} );
		setFocus( [ item.id, '' ] );
	}
	/**
	 * The saved unit a lesson or quiz is placed in, when there is one.
	 *
	 * @param content Lesson or quiz ID.
	 * @return Unit ID, or null.
	 */
	function unitOf( content: number ): number | null {
		const item = draft.program.items.find(
			( row ) =>
				[ 'lesson', 'quiz' ].includes( row.type ) &&
				row.content_id === content
		);
		return item &&
			'number' === typeof item.chapter_id &&
			item.chapter_id > 0
			? item.chapter_id
			: null;
	}
	const toggleUnit = ( id: Unit[ 'id' ] ) =>
		setFolded( ( current ) => {
			const next = new Set( current );
			if ( ! next.delete( id ) ) {
				next.add( id );
			}
			return next;
		} );
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
	const [ said, say ] = useState( '' );
	const [ stale, setStale ] = useState( false );
	const [ focus, setFocus ] = useState< [ string, string ] | null >(
		targeted ? [ targeted.id, '' ] : null
	);
	const root = useRef< HTMLElement >( null );
	const operation = useRef< AbortController | null >( null );
	useEffect( () => () => operation.current?.abort(), [] );
	useEffect( () => {
		if ( ! focus ) {
			return;
		}
		// Rows stay focusable while the fieldset is disabled; the control returns once the save ends.
		const row = root.current?.querySelector< HTMLElement >(
			`[data-oml-drop=${ JSON.stringify( focus[ 0 ] ) }]`
		);
		const control = busy
			? null
			: row?.querySelector< HTMLElement >(
					`[data-oml-control="${ focus[ 1 ] }"]:not(:disabled)`
				);
		( control ?? row )?.focus();
		row?.scrollIntoView?.( { block: 'nearest' } );
		if ( ! busy ) {
			setFocus( null );
		}
	}, [ focus, busy ] );
	const dirty = snapshot( draft ) !== saved;
	const guard = useUnsavedChangesGuard( dirty, {
		allow: ( path ) => isCourseEditorStep( initial.course_id, path ),
	} );
	// The builder bar has the Save button; it runs this draft save.
	useBuilderSave( { save, dirty, busy }, 'outline' );
	function change( next: Outline ) {
		setDraft( next );
		setMessage( '' );
	}
	function accept( result: Outline, notice: string ) {
		setDraft( result );
		setSaved( snapshot( result ) );
		setPlanVersion( ( count ) => count + 1 );
		setMessage( notice );
	}
	async function write(
		work: ( signal: AbortSignal ) => Promise< Outline >,
		notice: string,
		undo?: ( problem: ApiError ) => void
	) {
		if ( operation.current ) {
			return;
		}
		const controller = new AbortController();
		operation.current = controller;
		setBusy( true );
		setError( '' );
		setStale( false );
		setMessage( '' );
		try {
			const result = await work( controller.signal );
			if ( ! controller.signal.aborted ) {
				accept( result, notice );
			}
		} catch ( failure ) {
			if ( ! controller.signal.aborted ) {
				const problem = normalizeApiError( failure );
				setError( failureMessage( problem ) );
				undo?.( problem );
			}
		} finally {
			if ( ! controller.signal.aborted ) {
				operation.current = null;
				setBusy( false );
			}
		}
	}
	const askReload = () => ( dirty ? setReloadConfirm( true ) : reload() );
	/**
	 * Move a unit or activity (drag, move buttons or unit select), announce it and keep focus on it. On
	 * a saved outline the move is saved at once and undone if that fails; otherwise it joins the draft.
	 * @param source  Unit key or activity ID.
	 * @param target  Unit key or activity ID it is dropped on.
	 * @param after   Placed after the target.
	 * @param control Control to focus again in the moved row.
	 */
	function reorder(
		source: string,
		target: string,
		after: boolean,
		control = ''
	) {
		const next = dropOn( draft, source, target, after );
		if ( next === draft ) {
			return;
		}
		say( describeMove( next, source ) );
		setFocus( [ source, control ] );
		if ( dirty ) {
			change( next );
			return;
		}
		setDraft( next );
		void write(
			( signal ) => saveOutline( next, savePayload( next ), signal ),
			__(
				'Outline order saved to the draft. Learner publication is unchanged.',
				'ohmylms'
			),
			( problem ) => {
				setDraft( draft );
				say( '' );
				setStale(
					409 === problem.status &&
						! REFUSALS.includes( problem.code )
				);
				setError(
					sprintf(
						/* translators: %s: reason. */
						__(
							'The move was not saved and has been undone. %s',
							'ohmylms'
						),
						problem.message
					)
				);
			}
		);
	}
	const { drag, handle } = useDragMove( reorder, busy );
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
	/**
	 * Place resources at the end of a unit. Items already in the course are skipped with a message; the
	 * draft is updated from its latest value, so several quick adds in a row never overwrite each other.
	 *
	 * @param unit      Unit ID.
	 * @param type      Resource type.
	 * @param resources Resources to place.
	 */
	function place(
		unit: Unit[ 'id' ],
		type: ResourceType,
		resources: ReadonlyArray< { id: number; title: string } >
	) {
		setFolded( ( current ) => {
			const next = new Set( current );
			next.delete( unit );
			return next;
		} );
		const placed = new Set(
			draft.program.items
				.filter( ( item ) => item.type === type )
				.map( ( item ) => item.content_id )
		);
		const fresh = resources.filter(
			( resource, index ) =>
				! placed.has( resource.id ) &&
				resources.findIndex( ( row ) => row.id === resource.id ) ===
					index
		);
		setError(
			fresh.length < resources.length
				? __(
						'Some items are already placed in the course and were skipped.',
						'ohmylms'
					)
				: ''
		);
		if ( ! fresh.length ) {
			return;
		}
		setMessage( '' );
		setDraft( ( current ) => ( {
			...current,
			program: {
				...current.program,
				items: fresh.reduce( ( items, resource ) => {
					const item: Activity = {
						id: newId(),
						type,
						content_id: resource.id,
						chapter_id: 0,
						required: true,
						name: resource.title,
					};
					return placeActivity(
						[ ...items, item ],
						item.id,
						unit,
						Infinity
					);
				}, current.program.items ),
			},
		} ) );
	}
	function add( resource: { id: number; title: string } ) {
		if ( ! picker ) {
			return;
		}
		const { unit, type } = picker;
		setPicker( null );
		place( unit, type, [ resource ] );
	}
	function addMany(
		resources: ReadonlyArray< { id: number; title: string } >
	) {
		if ( ! picker ) {
			return;
		}
		const { unit, type } = picker;
		setPicker( null );
		place( unit, type, resources );
	}
	const unplaced = draft.program.items.filter(
		( item ) =>
			! draft.units.some( ( unit ) => unit.id === item.chapter_id )
	);
	return (
		<section
			ref={ root }
			className="oml-outline"
			aria-label={ __( 'Outline editor', 'ohmylms' ) }
		>
			<VisuallyHidden role="status">{ said }</VisuallyHidden>
			<span className="screen-reader-text" role="status">
				{ dirty
					? __( 'Unsaved changes', 'ohmylms' )
					: __( 'Saved draft', 'ohmylms' ) }
			</span>
			<div className="oml-outline-actions">
				<Button
					variant="tertiary"
					disabled={ busy }
					onClick={ askReload }
				>
					{ __( 'Reload saved outline', 'ohmylms' ) }
				</Button>
				<Button
					variant="primary"
					className="oml-builder-inner-save"
					disabled={ busy || ! dirty }
					isBusy={ busy }
					onClick={ save }
				>
					{ __( 'Save outline draft', 'ohmylms' ) }
				</Button>
			</div>
			{ error && (
				<Notice
					status="error"
					isDismissible={ false }
					actions={
						stale
							? [
									{
										label: __(
											'Reload saved outline',
											'ohmylms'
										),
										onClick: askReload,
									},
								]
							: []
					}
				>
					{ error }
				</Notice>
			) }
			{ message && (
				<Notice status="success" isDismissible={ false }>
					{ message }
				</Notice>
			) }
			{ opening && (
				<Notice status="info" isDismissible={ false }>
					{ targeted
						? sprintf(
								/* translators: 1: activity name, 2: unit title. */
								__(
									'Opened on “%1$s” in %2$s. Move it to another unit or detach it here, then save the outline draft.',
									'ohmylms'
								),
								targeted.name || String( targeted.content_id ),
								initial.units.find(
									( unit ) => unit.id === targeted.chapter_id
								)?.title ??
									__( 'the unplaced activities', 'ohmylms' )
							)
						: __(
								'That item is not placed in this course outline. Add it to a unit, then save the outline draft.',
								'ohmylms'
							) }
				</Notice>
			) }
			<div className="oml-plan-grid">
				<div className="oml-editor-main">
					<p className="oml-editor-hint">
						{ __(
							'Edit the draft units and activity placements. Saving here does not publish to learners.',
							'ohmylms'
						) }
					</p>
					<fieldset className="oml-outline-units" disabled={ busy }>
						<legend className="screen-reader-text">
							{ __( 'Units and activities', 'ohmylms' ) }
						</legend>
						{ draft.units.length > 1 && (
							<div className="oml-outline-toolbar">
								<Button
									size="small"
									variant="tertiary"
									disabled={ 0 === folded.size }
									onClick={ () => setFolded( new Set() ) }
								>
									{ __( 'Expand all', 'ohmylms' ) }
								</Button>
								<Button
									size="small"
									variant="tertiary"
									disabled={ draft.units.every( ( unit ) =>
										folded.has( unit.id )
									) }
									onClick={ () =>
										setFolded(
											new Set(
												draft.units.map(
													( unit ) => unit.id
												)
											)
										)
									}
								>
									{ __( 'Collapse all', 'ohmylms' ) }
								</Button>
							</div>
						) }
						{ draft.units.map( ( unit ) => (
							<UnitEditor
								key={ unit.id }
								unit={ unit }
								collapsed={ folded.has( unit.id ) }
								onToggle={ () => toggleUnit( unit.id ) }
								units={ draft.units }
								items={ draft.program.items.filter(
									( item ) => item.chapter_id === unit.id
								) }
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
								drag={ drag }
								handle={ handle }
								onReorder={ reorder }
								onAdd={ ( type ) =>
									setPicker( { unit: unit.id, type } )
								}
								onCreated={ ( type, resource ) =>
									place( unit.id, type, [ resource ] )
								}
								onDelete={ () => setDeleting( unit ) }
								onDetach={ setDetach }
								selectedUnit={
									'unit' === selection?.kind &&
									selection.id === unit.id
								}
								selectedItem={
									selection && 'unit' !== selection.kind
										? ( draft.program.items.find(
												( row ) =>
													selection.kind ===
														row.type &&
													row.content_id ===
														selection.id
											)?.id ?? null )
										: null
								}
								onSelectUnit={ () => selectUnit( unit ) }
								onSelectItem={ selectContent }
								skillCounts={ skillCounts }
								topicCount={
									'number' === typeof unit.id
										? ( unitTopics[ unit.id ]?.length ?? 0 )
										: 0
								}
							/>
						) ) }

						<Button
							className="oml-outline-add-unit"
							variant="secondary"
							icon="plus"
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
								{ __(
									'Unplaced activities (preserved)',
									'ohmylms'
								) }
							</h3>
							<ul>
								{ unplaced.map( ( item ) => (
									<li key={ item.id }>
										{ item.name ||
											String( item.content_id ) }{ ' ' }
										{ draft.units.length > 0 && (
											<Button
												variant="link"
												disabled={ busy }
												onClick={ () =>
													reorder(
														item.id,
														unitKey(
															draft.units[ 0 ]!.id
														),
														true
													)
												}
											>
												{ sprintf(
													/* translators: %s: unit title. */
													__(
														'Place in %s',
														'ohmylms'
													),
													draft.units[ 0 ]!.title
												) }
											</Button>
										) }
									</li>
								) ) }
							</ul>
						</section>
					) }
				</div>
				<SkillPlan
					course={ draft.course_id }
					selection={ selection }
					onSelect={ revealContent }
					unsaved={ dirty }
					refresh={ planVersion }
					onCounts={ setSkillCounts }
					onTopics={ setUnitTopics }
					unitOf={ unitOf }
				/>
			</div>

			{ picker && (
				<ResourcePicker
					course={ draft.course_id }
					type={ picker.type }
					exclude={ draft.program.items
						.filter( ( item ) => item.type === picker.type )
						.map( ( item ) => item.content_id ) }
					onClose={ () => setPicker( null ) }
					onSelect={ add }
					onSelectMany={ addMany }
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
		</section>
	);
}
