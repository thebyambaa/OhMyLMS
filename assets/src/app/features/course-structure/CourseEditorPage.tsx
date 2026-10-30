import { Button, Modal, Notice } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import { normalizeApiError } from '../../lib/api';
import { CoursePlacement } from '../curriculum';
import { readOutline, saveOutline } from './api';
import { LessonPicker } from './LessonPicker';
import { UnitEditor } from './UnitEditor';
import {
	move,
	moveActivity,
	savePayload,
	type Outline,
	type Activity,
} from './model';
import { useRequest } from '../../hooks/useRequest';

/** UUIDs identify unsaved units and activity placements on HTTP test sites too. */
export function newId(): string {
	const bytes = crypto.getRandomValues( new Uint8Array( 16 ) );
	bytes[ 6 ] = ( bytes[ 6 ]! % 16 ) + 64;
	bytes[ 8 ] = ( bytes[ 8 ]! % 64 ) + 128;
	const hex = Array.from( bytes, ( byte ) =>
		byte.toString( 16 ).padStart( 2, '0' )
	).join( '' );
	return `${ hex.slice( 0, 8 ) }-${ hex.slice( 8, 12 ) }-${ hex.slice( 12, 16 ) }-${ hex.slice( 16, 20 ) }-${ hex.slice( 20 ) }`;
}

export default function CourseEditorPage() {
	const id = Number( useParams().id );
	if ( ! Number.isSafeInteger( id ) || id <= 0 ) {
		return <p role="alert">{ __( 'Invalid course ID.', 'ohmylms' ) }</p>;
	}
	return <LoadedCourse key={ id } id={ id } />;
}

function LoadedCourse( { id }: { id: number } ) {
	const load = useCallback(
		( signal: AbortSignal ) => readOutline( id, signal ),
		[ id ]
	);
	const request = useRequest( load );
	return (
		<>
			<h1>
				{
					/* translators: %d: course identifier. */ sprintf(
						__( 'Course structure #%d', 'ohmylms' ),
						id
					)
				}
			</h1>
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

export function OutlineWorkspace( {
	initial,
	reload,
}: {
	initial: Outline;
	reload: () => void;
} ) {
	const [ draft, setDraft ] = useState( initial );
	const [ saved, setSaved ] = useState(
		JSON.stringify( {
			revision: initial.revision,
			units: initial.units,
			program: initial.program,
		} )
	);
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ message, setMessage ] = useState( '' );
	const [ reloadConfirm, setReloadConfirm ] = useState( false );
	const [ detach, setDetach ] = useState< Activity | null >( null );
	const [ picker, setPicker ] = useState< {
		unit: number | string;
	} | null >( null );
	const operation = useRef< AbortController | null >( null );
	useEffect( () => () => operation.current?.abort(), [] );
	const dirty =
		JSON.stringify( {
			revision: draft.revision,
			units: draft.units,
			program: draft.program,
		} ) !== saved;
	const guard = useUnsavedChangesGuard( dirty );
	function change( next: Outline ) {
		setDraft( next );
		setMessage( '' );
	}
	async function save() {
		if ( operation.current ) {
			return;
		}
		const controller = new AbortController();
		operation.current = controller;
		setBusy( true );
		setError( '' );
		setMessage( '' );
		try {
			const result = await saveOutline(
				draft,
				savePayload( draft ),
				controller.signal
			);
			if ( ! controller.signal.aborted ) {
				setDraft( result );
				setSaved( JSON.stringify( savePayload( result ) ) );
				setMessage(
					__(
						'Outline draft saved. Learner publication is unchanged.',
						'ohmylms'
					)
				);
			}
		} catch ( failure ) {
			if ( ! controller.signal.aborted ) {
				const problem = normalizeApiError( failure );
				setError(
					problem.status === 409
						? __(
								'This course changed in another session. Your edits are kept. Reload the saved outline before trying again.',
								'ohmylms'
							)
						: problem.message
				);
			}
		} finally {
			if ( ! controller.signal.aborted ) {
				operation.current = null;
				setBusy( false );
			}
		}
	}
	const unplaced = draft.program.items.filter(
		( item ) =>
			! draft.units.some( ( unit ) => unit.id === item.chapter_id )
	);
	return (
		<section aria-label={ __( 'Outline editor', 'ohmylms' ) }>
			<p>
				<Link to="/content-hub/courses">
					{ __( 'Back to courses', 'ohmylms' ) }
				</Link>{ ' ' }
				·{ ' ' }
				<Link to="/content-hub/curriculum">
					{ __( 'Curriculum', 'ohmylms' ) }
				</Link>
			</p>
			<p>
				{ __(
					'Edit the draft units and lesson placements. Saving here does not publish to learners.',
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
				onClick={ () => void save() }
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
			<fieldset disabled={ busy }>
				<legend>{ __( 'Units and lessons', 'ohmylms' ) }</legend>
				{ draft.units.map( ( unit, index ) => (
					<UnitEditor
						key={ unit.id }
						unit={ unit }
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
						onAttach={ () => setPicker( { unit: unit.id } ) }
						onDetach={ setDetach }
						onMoveLesson={ ( id, offset ) =>
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
					<h2>
						{ __( 'Unplaced activities (preserved)', 'ohmylms' ) }
					</h2>
					<ul>
						{ unplaced.map( ( item ) => (
							<li key={ item.id }>
								{ item.name || String( item.content_id ) }
							</li>
						) ) }
					</ul>
				</section>
			) }
			{ picker && (
				<LessonPicker
					course={ draft.course_id }
					onClose={ () => setPicker( null ) }
					onSelect={ ( lesson ) => {
						change( {
							...draft,
							program: {
								...draft.program,
								items: [
									...draft.program.items,
									{
										id: newId(),
										type: 'lesson',
										content_id: lesson.id,
										chapter_id: picker.unit,

										required: true,
										name: lesson.title,
									},
								],
							},
						} );
						setPicker( null );
					} }
				/>
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
					title={ __( 'Detach this lesson placement?', 'ohmylms' ) }
					onRequestClose={ () => setDetach( null ) }
				>
					<p>
						{ __(
							'The reusable lesson and learner history remain. This placement is removed only when you save the outline draft.',
							'ohmylms'
						) }
					</p>
					<Button onClick={ () => setDetach( null ) }>
						{ __( 'Keep lesson', 'ohmylms' ) }
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
