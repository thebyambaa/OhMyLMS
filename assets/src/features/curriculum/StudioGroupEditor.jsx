/** @jsx createElement */
import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Modal,
	Notice,
	SelectControl,
	Spinner,
	TextareaControl,
} from '@wordpress/components';
import {
	readComparison,
	writeComparison,
	mappingPreview,
} from './comparison.mjs';
import { relationshipLabels } from './relationships.mjs';

/**
 * Review and update a required-together group without the comparison columns.
 * @param {Object}     props           Editor props.
 * @param {Object}     props.mapping   Selected grouped connection.
 * @param {() => void} props.onClose   Return to the syllabus.
 * @param {() => void} props.onChanged Refresh the syllabus after saving.
 */
export function StudioGroupEditor( { mapping, onClose, onChanged } ) {
	const [ group, setGroup ] = useState( null );
	const [ note, setNote ] = useState( mapping.note || '' );
	const [ relation, setRelation ] = useState( mapping.relation );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ closing, setClosing ] = useState( false );
	const [ removing, setRemoving ] = useState( false );
	const saving = useRef( false );
	const dirty =
		note !== ( mapping.note || '' ) || relation !== mapping.relation;
	useEffect( () => {
		let current = true;
		readComparison( `mapping-groups/${ mapping.group_uuid }` )
			.then( ( response ) => current && setGroup( response ) )
			.catch( ( cause ) => current && setError( cause.message ) );
		return () => {
			current = false;
		};
	}, [ mapping.group_uuid ] );
	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );
	const close = () => {
		if ( busy ) {
			return;
		}
		if ( dirty ) {
			setClosing( true );
		} else {
			onClose();
		}
	};
	async function save( remove = false ) {
		if ( saving.current || ! group ) {
			return;
		}
		saving.current = true;
		setBusy( true );
		setError( '' );
		try {
			const pairs = mappingPreview(
				group.items,
				{ id: group.shared_id },
				relation,
				note
			).map( ( pair ) => ( {
				specific_id: pair.specific_id,
				expected_revision: pair.expected_revision,
			} ) );
			if ( remove ) {
				await writeComparison( 'mapping-groups/remove', {
					uuid: group.uuid,
					pairs,
				} );
			} else {
				await writeComparison( 'mapping-groups', {
					shared_id: group.shared_id,
					groups: [ { source_id: group.source_id, pairs } ],
					relation,
					note,
				} );
			}
			onChanged();
			onClose();
		} catch ( cause ) {
			setError( cause.message );
		} finally {
			saving.current = false;
			setBusy( false );
		}
	}
	return (
		<Modal
			title={ __( 'Edit complete connection group', 'ohmylms' ) }
			onRequestClose={ close }
			shouldCloseOnClickOutside={ false }
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ ! group && ! error && <Spinner /> }
			{ group && (
				<>
					<p>
						{ __(
							'These objectives are edited together. Required-together coverage is descriptive and does not pool assessment evidence.',
							'ohmylms'
						) }
					</p>
					<ul>
						{ group.items.map( ( row ) => (
							<li key={ row.term_id }>
								{ row.code } · { row.name }
							</li>
						) ) }
					</ul>
					<SelectControl
						label={ __( 'Group relationship', 'ohmylms' ) }
						value={ relation }
						onChange={ setRelation }
						disabled={ busy }
						options={ Object.entries( relationshipLabels() ).map(
							( [ value, label ] ) => ( { value, label } )
						) }
					/>
					<TextareaControl
						label={ __(
							'Scope, difficulty and version differences',
							'ohmylms'
						) }
						value={ note }
						onChange={ setNote }
						maxLength={ 1000 }
						disabled={ busy }
					/>
					{ closing && (
						<Notice status="warning" isDismissible={ false }>
							<p>
								{ __(
									'Discard the unsaved group edits?',
									'ohmylms'
								) }
							</p>
							<Button
								variant="secondary"
								onClick={ () => setClosing( false ) }
							>
								{ __( 'Keep editing', 'ohmylms' ) }
							</Button>{ ' ' }
							<Button
								variant="secondary"
								isDestructive
								onClick={ onClose }
							>
								{ __( 'Discard and close', 'ohmylms' ) }
							</Button>
						</Notice>
					) }
					{ ! closing && removing && (
						<Notice status="warning" isDismissible={ false }>
							<p>
								{ __(
									'Remove all connections in this group?',
									'ohmylms'
								) }
							</p>
							<Button
								variant="secondary"
								disabled={ busy }
								onClick={ () => setRemoving( false ) }
							>
								{ __( 'Cancel', 'ohmylms' ) }
							</Button>{ ' ' }
							<Button
								variant="primary"
								isDestructive
								disabled={ busy }
								isBusy={ busy }
								onClick={ () => save( true ) }
							>
								{ __( 'Remove complete group', 'ohmylms' ) }
							</Button>
						</Notice>
					) }
					{ ! closing && ! removing && (
						<>
							<Button
								variant="primary"
								disabled={ busy || ! dirty }
								isBusy={ busy }
								onClick={ () => save() }
							>
								{ __( 'Save group changes', 'ohmylms' ) }
							</Button>{ ' ' }
							<Button
								variant="secondary"
								disabled={ busy }
								onClick={ close }
							>
								{ __( 'Close', 'ohmylms' ) }
							</Button>{ ' ' }
							<Button
								variant="link"
								isDestructive
								disabled={ busy || dirty }
								onClick={ () => setRemoving( true ) }
							>
								{ __( 'Remove group', 'ohmylms' ) }
							</Button>
						</>
					) }
				</>
			) }
		</Modal>
	);
}
