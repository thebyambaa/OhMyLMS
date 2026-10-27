/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	SelectControl,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import {
	readComparison,
	writeComparison,
	readLocalDraft,
	saveLocalDraft,
} from './comparison.mjs';
import { SharedContentEditor } from './SharedContentEditor';

/**
 * Stable shared skill editor with the affected objectives visible before saving.
 * @param {Object} props           Editor props.
 * @param {Object} props.skill     Current skill, or null for creation.
 * @param          props.subject
 * @param          props.onSaved   Save callback.
 * @param          props.onDirty   Dirty state callback.
 * @param          props.onBusy    Optional save-in-progress callback.
 * @param          props.roadmapId
 */
export function SharedSkillEditor( {
	skill,
	subject,
	onSaved,
	onDirty,
	onBusy,
	roadmapId = 0,
} ) {
	const draftKey = `skill-${ skill?.id || 0 }`;
	const [ draft, setDraft ] = useState( () => readLocalDraft( draftKey ) );
	const [ name, setName ] = useState( draft.name ?? skill?.name ?? '' );
	const [ description, setDescription ] = useState(
		draft.description ?? skill?.description ?? ''
	);
	const [ topic, setTopic ] = useState( draft.topic ?? skill?.topic ?? '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	useEffect( () => {
		const changed =
			name !== ( skill?.name || '' ) ||
			description !== ( skill?.description || '' ) ||
			topic !== ( skill?.topic || '' );
		saveLocalDraft(
			draftKey,
			changed
				? {
						name,
						description,
						topic,
						revision: draft.revision || skill?.revision,
					}
				: {}
		);
		onDirty( changed );
	}, [ name, description, topic, skill, onDirty, draftKey, draft.revision ] );
	async function save() {
		setBusy( true );
		onBusy?.( true );
		setError( '' );
		try {
			const response = await writeComparison( 'shared-skills/save', {
				id: skill?.id || 0,
				roadmap_id: roadmapId,
				subject,
				name,
				description,
				topic,
				expected_revision: draft.revision || skill?.revision,
			} );
			onDirty( false );
			saveLocalDraft( draftKey, {} );
			onSaved( response );
		} catch ( cause ) {
			setError( cause.message );
		} finally {
			setBusy( false );
			onBusy?.( false );
		}
	}
	return (
		<div>
			<h3>
				{ skill
					? __( 'Edit shared skill', 'ohmylms' )
					: __( 'Create shared skill', 'ohmylms' ) }
			</h3>
			{ skill && (
				<p>
					{ sprintf(
						/* translators: %d: count or skill ID. */
						__( 'Stable skill ID: %d', 'ohmylms' ),
						skill.id
					) }
				</p>
			) }
			<TextControl
				label={ __( 'Assessable ability', 'ohmylms' ) }
				value={ name }
				maxLength={ 190 }
				onChange={ setName }
			/>
			<TextareaControl
				label={ __( 'Scope and limits', 'ohmylms' ) }
				value={ description }
				maxLength={ 2000 }
				onChange={ setDescription }
			/>
			<TextControl
				label={ __( 'Subject topic', 'ohmylms' ) }
				value={ topic }
				maxLength={ 100 }
				onChange={ setTopic }
			/>
			{ skill?.mappings.mapped_from.length > 0 && (
				<div>
					<h4>{ __( 'Affected objectives', 'ohmylms' ) }</h4>
					<ul>
						{ skill.mappings.mapped_from.map( ( mapping ) => (
							<li key={ mapping.id }>
								{ mapping.specific.code }{ ' ' }
								{ mapping.specific.name } · { mapping.relation }{ ' ' }
								· { mapping.note }
							</li>
						) ) }
					</ul>
				</div>
			) }
			{ error && <p role="alert">{ error }</p> }
			<Button
				variant="secondary"
				disabled={ busy || ! name.trim() }
				isBusy={ busy }
				onClick={ save }
			>
				{ __( 'Save shared skill', 'ohmylms' ) }
			</Button>
			<Button
				variant="link"
				disabled={ busy }
				onClick={ () => {
					setDraft( {} );
					saveLocalDraft( draftKey, {} );
					setName( skill?.name || '' );
					setDescription( skill?.description || '' );
					setTopic( skill?.topic || '' );
					onDirty( false );
				} }
			>
				{ __( 'Discard skill edits', 'ohmylms' ) }
			</Button>
			{ skill && (
				<SharedContentEditor key={ skill.id } id={ skill.id } />
			) }
		</div>
	);
}

/**
 * Search and page shared skills without loading the full catalogue.
 * @param {Object}  props           Picker props.
 * @param {number}  props.roadmapId Limit the library to this Roadmap and its sources.
 * @param {Object}  props.skill     Current skill.
 * @param           props.subject
 * @param           props.onChoose  Selection callback.
 * @param {number}  props.revision  Reload counter.
 * @param {boolean} props.disabled  Disable selection.
 */
export function SharedSkillPicker( {
	roadmapId = 0,
	skill,
	subject,
	onChoose,
	revision,
	disabled,
} ) {
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	useEffect( () => {
		let current = true;
		const timer = setTimeout(
			() =>
				readComparison( 'shared-skills', {
					search,
					page,
					subject,
					...( roadmapId ? { roadmap_id: roadmapId } : {} ),
				} )
					.then( ( response ) => {
						if ( current ) {
							setData( response );
							setError( '' );
						}
					} )
					.catch( ( cause ) => current && setError( cause.message ) ),
			250
		);
		return () => {
			current = false;
			clearTimeout( timer );
		};
	}, [ search, page, revision, subject, roadmapId ] );
	return (
		<div>
			<TextControl
				label={ __( 'Find a shared skill', 'ohmylms' ) }
				value={ search }
				onChange={ ( value ) => {
					setSearch( value );
					setPage( 1 );
				} }
			/>
			<SelectControl
				label={ __( 'Shared skill', 'ohmylms' ) }
				value={ skill?.id || '' }
				disabled={ disabled }
				onChange={ ( value ) =>
					onChoose(
						data.items.find(
							( row ) => row.id === Number( value )
						) || null
					)
				}
				options={ [
					{ value: '', label: __( 'Create a new skill', 'ohmylms' ) },
					...( skill &&
					! data?.items.some( ( row ) => row.id === skill.id )
						? [ { value: skill.id, label: skill.name } ]
						: [] ),
					...( data?.items || [] ).map( ( row ) => ( {
						value: row.id,
						label: `${ row.topic } · ${ row.name }`,
					} ) ),
				] }
			/>
			{ error && <p role="alert">{ error }</p> }
			<div className="ohmylms-comparison-pagination">
				<Button
					variant="link"
					disabled={ page === 1 }
					onClick={ () => setPage( page - 1 ) }
				>
					{ __( 'Previous skills', 'ohmylms' ) }
				</Button>
				<Button
					variant="link"
					disabled={ page * 30 >= ( data?.total || 0 ) }
					onClick={ () => setPage( page + 1 ) }
				>
					{ __( 'More skills', 'ohmylms' ) }
				</Button>
			</div>
		</div>
	);
}
