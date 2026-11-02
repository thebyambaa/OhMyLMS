import { Button, Notice, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { useOperation } from '../../hooks/useOperation';
import {
	createRoadmap,
	isStale,
	MAX_ROADMAP_SKILLS,
	moved,
	previewRoadmap,
	readRoadmap,
	saveRoadmap,
	type CompareSource,
	type RoadmapPreview,
	type RoadmapState,
} from './compare';
import { SourcePicker } from './SourcePicker';

/**
 * Roadmaps: draft roadmaps built from the shared skills that source syllabuses map onto.
 *
 * @param props         Component props.
 * @param props.sources Syllabuses and roadmaps that can be sources.
 */
export function CompareRoadmaps( {
	sources,
}: {
	sources: readonly CompareSource[];
} ) {
	const [ created, setCreated ] = useState< number[] >( [] );
	const [ editing, setEditing ] = useState< number | null >( null );
	const roadmaps = sources.filter(
		( source ) =>
			'roadmap' === source.item_type || created.includes( source.id )
	);
	return (
		<section aria-labelledby="oml-compare-roadmaps">
			<h3 id="oml-compare-roadmaps">{ __( 'Roadmaps', 'ohmylms' ) }</h3>
			<NewRoadmap
				sources={ sources.filter(
					( source ) => 'syllabus' === source.item_type
				) }
				onCreated={ ( id ) => {
					setCreated( [ ...created, id ] );
					setEditing( id );
				} }
			/>
			<h4>{ __( 'Existing roadmaps', 'ohmylms' ) }</h4>
			{ roadmaps.length ? (
				<ul>
					{ roadmaps.map( ( roadmap ) => (
						<li key={ roadmap.id }>
							{ roadmap.name }{ ' ' }
							<Button
								variant="link"
								aria-label={ sprintf(
									/* translators: %s: roadmap name. */
									__( 'Edit steps of %s', 'ohmylms' ),
									roadmap.name
								) }
								onClick={ () => setEditing( roadmap.id ) }
							>
								{ __( 'Edit steps', 'ohmylms' ) }
							</Button>{ ' ' }
							<Link
								to={ `/content-hub/curriculum/syllabus/${ roadmap.id }` }
							>
								{ __(
									'Open in the syllabus editor',
									'ohmylms'
								) }
							</Link>
						</li>
					) ) }
				</ul>
			) : (
				! created.length && (
					<p>{ __( 'No roadmaps yet.', 'ohmylms' ) }</p>
				)
			) }
			{ editing && (
				<RoadmapEditor
					key={ editing }
					id={ editing }
					sources={ sources }
					onClose={ () => setEditing( null ) }
				/>
			) }
		</section>
	);
}

function PreviewSummary( { preview }: { preview: RoadmapPreview } ) {
	return (
		<p role="status">
			{ sprintf(
				/* translators: 1: shared skills, 2: sources, 3: unmapped objectives. */
				__(
					'%1$d shared skills from %2$d sources; %3$d objectives are not mapped to a shared skill and are left out.',
					'ohmylms'
				),
				preview.skills.length,
				preview.sources.length,
				preview.unmapped
			) }
		</p>
	);
}

function NewRoadmap( {
	sources,
	onCreated,
}: {
	sources: readonly CompareSource[];
	onCreated: ( id: number ) => void;
} ) {
	const [ name, setName ] = useState( '' );
	const [ ids, setIds ] = useState< number[] >( [] );
	const [ preview, setPreview ] = useState< {
		ids: string;
		data: RoadmapPreview;
	} | null >( null );
	const [ problem, setProblem ] = useState< string | null >( null );
	const operation = useOperation();
	const current = preview && preview.ids === ids.join( ',' ) ? preview : null;

	function check() {
		void operation.run(
			( signal ) => previewRoadmap( ids, signal ),
			( data ) => setPreview( { ids: ids.join( ',' ), data } )
		);
	}

	function create() {
		const value = name.trim();
		if ( ! value || Array.from( value ).length > 190 ) {
			setProblem(
				__( 'Enter a name of 1 to 190 characters.', 'ohmylms' )
			);
			return;
		}
		setProblem( null );
		void operation.run(
			( signal ) => createRoadmap( value, ids, signal ),
			( id ) => {
				setName( '' );
				setIds( [] );
				setPreview( null );
				onCreated( id );
			}
		);
	}

	return (
		<form
			noValidate
			aria-label={ __( 'New roadmap', 'ohmylms' ) }
			onSubmit={ ( event ) => {
				event.preventDefault();
				create();
			} }
		>
			<h4>{ __( 'New roadmap', 'ohmylms' ) }</h4>
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			<TextControl
				label={ __( 'Roadmap name', 'ohmylms' ) }
				value={ name }
				maxLength={ 190 }
				help={ problem ?? undefined }
				onChange={ setName }
			/>
			<SourcePicker
				legend={ __( 'Source syllabuses', 'ohmylms' ) }
				sources={ sources }
				selected={ ids }
				onChange={ setIds }
			/>
			<Button
				variant="secondary"
				disabled={ operation.busy || ! ids.length }
				onClick={ check }
			>
				{ __( 'Preview shared skills', 'ohmylms' ) }
			</Button>{ ' ' }
			<Button
				type="submit"
				variant="primary"
				disabled={ operation.busy || ! current }
				isBusy={ operation.busy }
			>
				{ __( 'Create draft roadmap', 'ohmylms' ) }
			</Button>
			{ current && <PreviewSummary preview={ current.data } /> }
		</form>
	);
}

function RoadmapEditor( {
	id,
	sources,
	onClose,
}: {
	id: number;
	sources: readonly CompareSource[];
	onClose: () => void;
} ) {
	const [ state, setState ] = useState< RoadmapState | null >( null );
	const [ name, setName ] = useState( '' );
	const [ ids, setIds ] = useState< number[] >( [] );
	const [ steps, setSteps ] = useState< { id: number; name: string }[] >(
		[]
	);
	const [ stale, setStale ] = useState( false );
	const [ message, setMessage ] = useState< string | null >( null );
	const operation = useOperation();

	useEffect( () => {
		const controller = new AbortController();
		readRoadmap( id, controller.signal ).then(
			( loaded ) => {
				if ( ! controller.signal.aborted ) {
					setState( loaded );
					setName( loaded.name );
					setIds( loaded.syllabus_ids );
					setSteps( loaded.skills );
				}
			},
			() => undefined
		);
		return () => controller.abort();
	}, [ id ] );

	if ( ! state ) {
		return <p role="status">{ __( 'Loading roadmap…', 'ohmylms' ) }</p>;
	}

	function addFromSources() {
		setMessage( null );
		void operation.run(
			( signal ) => previewRoadmap( ids, signal ),
			( preview ) => {
				const known = new Set( steps.map( ( step ) => step.id ) );
				const added = preview.skills.filter(
					( skill ) => ! known.has( skill.id )
				);
				setSteps(
					[ ...steps, ...added ].slice( 0, MAX_ROADMAP_SKILLS )
				);
				setMessage(
					sprintf(
						/* translators: %d: added steps. */
						__(
							'%d shared skills added at the end. Save to keep them.',
							'ohmylms'
						),
						added.length
					)
				);
			}
		);
	}

	function save() {
		if ( ! state ) {
			return;
		}
		setMessage( null );
		void operation.run(
			async ( signal ) => {
				try {
					return await saveRoadmap(
						id,
						{
							name,
							syllabus_ids: ids,
							skill_ids: steps.map( ( step ) => step.id ),
						},
						state.revision,
						signal
					);
				} catch ( failure ) {
					setStale( isStale( failure ) );
					throw failure;
				}
			},
			( saved ) => {
				setStale( false );
				setState( saved );
				setSteps( saved.skills );
				setMessage( __( 'Roadmap saved.', 'ohmylms' ) );
			}
		);
	}

	function reloadKeepingEdits() {
		void operation.run(
			( signal ) => readRoadmap( id, signal ),
			( fresh ) => {
				setState( fresh );
				setStale( false );
				setMessage(
					__(
						'Reloaded the latest roadmap. Your unsaved edits were kept; review them and save again.',
						'ohmylms'
					)
				);
			}
		);
	}

	return (
		<section aria-label={ __( 'Roadmap steps', 'ohmylms' ) }>
			<h4>{ state.name }</h4>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			{ stale && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'This roadmap changed after you opened it.',
						'ohmylms'
					) }{ ' ' }
					<Button variant="secondary" onClick={ reloadKeepingEdits }>
						{ __( 'Reload roadmap and keep my edits', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ operation.error && ! stale && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			<TextControl
				label={ __( 'Roadmap name', 'ohmylms' ) }
				value={ name }
				maxLength={ 190 }
				onChange={ setName }
			/>
			<SourcePicker
				legend={ __( 'Source syllabuses', 'ohmylms' ) }
				sources={ sources }
				selected={ ids }
				exclude={ id }
				onChange={ setIds }
			/>
			<Button
				variant="secondary"
				disabled={ operation.busy || ! ids.length }
				onClick={ addFromSources }
			>
				{ __( 'Add shared skills from the sources', 'ohmylms' ) }
			</Button>
			<ol aria-label={ __( 'Steps in learning order', 'ohmylms' ) }>
				{ steps.map( ( step, index ) => (
					<li key={ step.id }>
						{ step.name }{ ' ' }
						<Button
							variant="link"
							disabled={ 0 === index }
							aria-label={ sprintf(
								/* translators: %s: skill name. */
								__( 'Move %s up', 'ohmylms' ),
								step.name
							) }
							onClick={ () =>
								setSteps( moved( steps, index, -1 ) )
							}
						>
							{ __( 'Up', 'ohmylms' ) }
						</Button>{ ' ' }
						<Button
							variant="link"
							disabled={ index === steps.length - 1 }
							aria-label={ sprintf(
								/* translators: %s: skill name. */
								__( 'Move %s down', 'ohmylms' ),
								step.name
							) }
							onClick={ () =>
								setSteps( moved( steps, index, 1 ) )
							}
						>
							{ __( 'Down', 'ohmylms' ) }
						</Button>{ ' ' }
						<Button
							variant="link"
							isDestructive
							aria-label={ sprintf(
								/* translators: %s: skill name. */
								__( 'Remove %s', 'ohmylms' ),
								step.name
							) }
							onClick={ () =>
								setSteps(
									steps.filter(
										( entry ) => entry.id !== step.id
									)
								)
							}
						>
							{ __( 'Remove', 'ohmylms' ) }
						</Button>
					</li>
				) ) }
			</ol>
			<Button
				variant="primary"
				disabled={
					operation.busy || stale || ! ids.length || ! name.trim()
				}
				isBusy={ operation.busy }
				onClick={ save }
			>
				{ __( 'Save roadmap', 'ohmylms' ) }
			</Button>{ ' ' }
			<Button
				variant="tertiary"
				disabled={ operation.busy }
				onClick={ onClose }
			>
				{ __( 'Close', 'ohmylms' ) }
			</Button>
		</section>
	);
}
