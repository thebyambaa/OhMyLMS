import { Button, CheckboxControl, Modal, Notice } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { Badge } from '../../../ui/Badge';
import { CreateSkillForm } from '../content-hub/public';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import {
	isConflict,
	readContentLinks,
	updateContentLinks,
	type ContentKind,
	type ContentLinks,
	type SkillRef,
} from './workspace';
import { contentRoute, kindLabel, SkillChooser } from './WorkspaceParts';

/**
 * Persistent skill connections shared by the course authoring and curriculum workspaces.
 * @param root0
 * @param root0.kind
 * @param root0.id
 * @param root0.onDirtyChange
 */
export function ContentSkillConnections( {
	kind,
	id,
	onDirtyChange,
}: {
	kind: ContentKind;
	id: number;
	onDirtyChange?: ( dirty: boolean ) => void;
} ) {
	const load = useCallback(
		( signal: AbortSignal ) => readContentLinks( kind, id, signal ),
		[ kind, id ]
	);
	const request = useRequest( load );
	const [ links, setLinks ] = useState< ContentLinks | null >( null );
	const [ add, setAdd ] = useState< SkillRef[] >( [] );
	const [ remove, setRemove ] = useState< number[] >( [] );
	const [ stale, setStale ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const operation = useOperation();
	const current = links ?? request.data;
	const [ creationDirty, setCreationDirty ] = useState( false );
	const dirty =
		add.length > 0 || remove.length > 0 || creationDirty || operation.busy;
	const guard = useUnsavedChangesGuard( dirty && ! onDirtyChange );
	useEffect( () => {
		onDirtyChange?.( dirty );
	}, [ dirty, onDirtyChange ] );
	if ( request.loading && ! links ) {
		return <p role="status">{ __( 'Loading links…', 'ohmylms' ) }</p>;
	}
	if ( ! current ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ request.error }
				<Button onClick={ request.retry }>
					{ __( 'Retry', 'ohmylms' ) }
				</Button>
			</Notice>
		);
	}
	const linkedIds = current.skills.map( ( skill ) => skill.id );
	function save( base: ContentLinks ) {
		void operation.run(
			async ( signal ) => {
				try {
					return await updateContentLinks(
						base,
						add.map( ( skill ) => skill.id ),
						remove,
						signal
					);
				} catch ( failure ) {
					setStale( isConflict( failure ) );
					throw failure;
				}
			},
			( saved ) => {
				setLinks( saved );
				setAdd( [] );
				setRemove( [] );
				setStale( false );
				setMessage( __( 'Links saved.', 'ohmylms' ) );
			}
		);
	}
	async function reloadKeeping() {
		const fresh = await readContentLinks( kind, id ).catch( () => null );
		if ( ! fresh ) {
			return;
		}
		const ids = fresh.skills.map( ( skill ) => skill.id );
		setLinks( fresh );
		setAdd( ( list ) =>
			list.filter( ( skill ) => ! ids.includes( skill.id ) )
		);
		setRemove( ( list ) =>
			list.filter( ( skill ) => ids.includes( skill ) )
		);
		setStale( false );
		operation.clearError();
		setMessage(
			__(
				'Reloaded the latest links. Your unsaved changes were kept.',
				'ohmylms'
			)
		);
	}
	return (
		<section
			className="oml-skill-connections"
			aria-label={ __( 'Skill connections', 'ohmylms' ) }
		>
			<h4>
				<Link to={ contentRoute( kind, current.content.id ) }>
					{ current.content.title }
				</Link>{ ' ' }
				<small>{ kindLabel( kind ) }</small>
			</h4>
			<p>{ connectionHelp( kind ) }</p>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( '' ) }>
					{ message }
				</Notice>
			) }
			{ stale ? (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Someone changed the skills of this content after you opened it.',
						'ohmylms'
					) }{ ' ' }
					<Button
						variant="secondary"
						onClick={ () => void reloadKeeping() }
					>
						{ __( 'Reload links and keep my changes', 'ohmylms' ) }
					</Button>
				</Notice>
			) : (
				operation.error && (
					<Notice status="error" isDismissible={ false }>
						{ operation.error }
					</Notice>
				)
			) }
			<ul
				className="oml-connection-list"
				aria-label={ __( 'Linked skills', 'ohmylms' ) }
			>
				{ ! current.skills.length && ! add.length && (
					<li>{ __( 'No linked skills yet.', 'ohmylms' ) }</li>
				) }
				{ current.skills.map( ( skill ) => (
					<li key={ skill.id }>
						<strong>
							{ skill.code
								? skill.code + ' ' + skill.name
								: skill.name }
						</strong>
						<Badge>{ roleLabel( skill.role, skill.shared ) }</Badge>
						{ skill.syllabuses.length > 0 && (
							<span>{ skill.syllabuses.join( ', ' ) }</span>
						) }
						{ skill.archived && (
							<span>{ __( 'Archived', 'ohmylms' ) }</span>
						) }
						<CheckboxControl
							label={ sprintf(
								/* translators: %s: skill name. */ __(
									'Remove %s',
									'ohmylms'
								),
								skill.name
							) }
							disabled={
								skill.role === 'primary' || operation.busy
							}
							checked={ remove.includes( skill.id ) }
							onChange={ ( checked ) =>
								setRemove( ( list ) =>
									checked
										? [ ...list, skill.id ]
										: list.filter(
												( entry ) => entry !== skill.id
											)
								)
							}
						/>
					</li>
				) ) }
				{ add.map( ( skill ) => (
					<li key={ 'add-' + skill.id }>
						<strong>{ skill.name }</strong>
						<Badge>{ __( 'To be linked', 'ohmylms' ) }</Badge>
						<Button
							variant="link"
							disabled={ operation.busy }
							onClick={ () =>
								setAdd( ( list ) =>
									list.filter(
										( row ) => row.id !== skill.id
									)
								)
							}
						>
							{ __( 'Undo', 'ohmylms' ) }
						</Button>
					</li>
				) ) }
			</ul>
			<SkillChooser
				label={ __( 'Add a skill or objective', 'ohmylms' ) }
				exclude={ [
					...linkedIds,
					...add.map( ( skill ) => skill.id ),
				] }
				disabled={ operation.busy }
				onChoose={ ( skill ) =>
					setAdd( ( list ) => [ ...list, skill ] )
				}
			/>
			<Button
				variant="primary"
				disabled={
					( ! add.length && ! remove.length ) ||
					operation.busy ||
					creationDirty ||
					stale
				}
				isBusy={ operation.busy }
				onClick={ () => save( current ) }
			>
				{ sprintf(
					/* translators: 1: skills to link, 2: skills to unlink. */
					__( 'Save links (+%1$d, −%2$d)', 'ohmylms' ),
					add.length,
					remove.length
				) }
			</Button>
			<CreateSkillForm
				onDirtyChange={ setCreationDirty }
				disabled={ operation.busy }
				onCreated={ ( skill ) =>
					setAdd( ( list ) => [ ...list, skill ] )
				}
			/>
			<Link to="/content-hub/skills">
				{ __( 'Create or manage skills', 'ohmylms' ) }
			</Link>
			{ guard.pendingPath && (
				<Modal
					title={ __(
						'Leave with unsaved skill connections?',
						'ohmylms'
					) }
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

function connectionHelp( kind: ContentKind ) {
	switch ( kind ) {
		case 'lesson':
			return __(
				'Taught skills describe what this lesson teaches. Lesson completion does not establish mastery.',
				'ohmylms'
			);
		case 'quiz':
			return __(
				'Quiz links describe intended coverage. Assessed mastery comes from the primary skills on individual questions.',
				'ohmylms'
			);
		case 'question':
			return __(
				'The primary skill receives assessment evidence. Change it in the question editor; supporting links describe additional coverage.',
				'ohmylms'
			);
		default:
			return __(
				'Connect the skills and syllabus objectives covered by this content.',
				'ohmylms'
			);
	}
}
function roleLabel( role: string, shared: boolean ) {
	if ( role === 'primary' ) {
		return __( 'Primary skill', 'ohmylms' );
	}
	if ( role === 'supporting' ) {
		return __( 'Supporting skill', 'ohmylms' );
	}
	return shared
		? __( 'Shared skill', 'ohmylms' )
		: __( 'Objective', 'ohmylms' );
}
