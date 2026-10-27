/** @jsx createElement */
import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Notice,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useWorkspace } from './context';
import {
	readComparison,
	writeComparison,
	readLocalDraft,
	saveLocalDraft,
} from './comparison.mjs';
import { roadmapSourceWrite } from './roadmap-sources.mjs';

/** Source selection on the roadmap's syllabus home. */
export function RoadmapSources() {
	const w = useWorkspace();
	const { onSourceState } = w;
	const [ state, setState ] = useState( null );
	const [ catalog, setCatalog ] = useState( [] );
	const [ sources, setSources ] = useState( null );
	const [ search, setSearch ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ reload, setReload ] = useState( 0 );
	const saving = useRef( false );
	const key = `sources-${ w.syllabusId }`;
	const dirty = Boolean(
		state &&
		sources &&
		JSON.stringify( sources ) !== JSON.stringify( state.syllabus_ids )
	);
	useEffect( () => {
		let current = true;
		setError( '' );
		Promise.all( [
			readComparison( `roadmaps/${ w.syllabusId }` ),
			readComparison( 'syllabuses' ),
		] )
			.then( ( [ saved, available ] ) => {
				if ( ! current ) {
					return;
				}
				setState( saved );
				setCatalog(
					available.items.filter(
						( item ) => item.id !== w.syllabusId
					)
				);
				setSources(
					( previous ) =>
						previous ||
						readLocalDraft( key ).sources ||
						saved.syllabus_ids
				);
			} )
			.catch( ( cause ) => current && setError( cause.message ) );
		return () => {
			current = false;
		};
	}, [ w.syllabusId, w.revision, key, reload ] );
	useEffect( () => {
		if ( sources ) {
			saveLocalDraft( key, { sources } );
		}
	}, [ key, sources ] );
	useEffect( () => {
		onSourceState( dirty, busy );
		return () => onSourceState( false, false );
	}, [ dirty, busy, onSourceState ] );

	async function save() {
		if (
			saving.current ||
			! state ||
			! sources?.length ||
			sources.length > 10
		) {
			return;
		}
		saving.current = true;
		setBusy( true );
		setError( '' );
		try {
			const saved = await writeComparison(
				`roadmaps/${ w.syllabusId }`,
				roadmapSourceWrite( state, sources ),
				'PUT'
			);
			setState( saved );
			setSources( saved.syllabus_ids );
			w.say(
				'success',
				__(
					'Roadmap sources saved. Existing folders and skills were retained.',
					'ohmylms'
				)
			);
			await w.reload();
		} catch ( cause ) {
			setError( cause.message );
		} finally {
			saving.current = false;
			setBusy( false );
		}
	}
	return (
		<section
			className="ohmylms-syllabus-settings-card"
			aria-label={ __( 'Roadmap sources', 'ohmylms' ) }
		>
			<h3>{ __( 'Roadmap sources', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'Choose up to ten syllabuses or roadmaps to use in the visual editor. Source changes keep your existing roadmap folders, skill order and connections.',
					'ohmylms'
				) }
			</p>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }{ ' ' }
					<Button
						variant="link"
						disabled={ busy }
						onClick={ () => setReload( ( value ) => value + 1 ) }
					>
						{ __( 'Reload sources', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ ! state && ! error && <Spinner /> }
			{ state && sources && (
				<>
					<TextControl
						label={ __( 'Find a source syllabus', 'ohmylms' ) }
						type="search"
						value={ search }
						onChange={ setSearch }
						disabled={ busy || w.pending }
					/>
					<div className="ohmylms-roadmap-source-list">
						{ catalog
							.filter(
								( item ) =>
									sources.includes( item.id ) ||
									item.name
										.toLowerCase()
										.includes( search.toLowerCase() )
							)
							.map( ( item ) => (
								<CheckboxControl
									key={ item.id }
									label={ [ item.name, item.version ]
										.filter( Boolean )
										.join( ' · ' ) }
									checked={ sources.includes( item.id ) }
									disabled={
										busy ||
										w.pending ||
										( sources.length >= 10 &&
											! sources.includes( item.id ) )
									}
									onChange={ ( checked ) =>
										setSources( ( previous ) =>
											checked
												? [ ...previous, item.id ]
												: previous.filter(
														( id ) => id !== item.id
													)
										)
									}
								/>
							) ) }
					</div>
					<p role="status">
						{ sprintf(
							/* translators: %d: number of selected roadmap sources. */ __(
								'%d sources selected',
								'ohmylms'
							),
							sources.length
						) }
						{ dirty
							? ` · ${ __( 'Unsaved source selection', 'ohmylms' ) }`
							: '' }
					</p>
					<Button
						variant="primary"
						disabled={
							busy ||
							w.pending ||
							! dirty ||
							! sources.length ||
							sources.length > 10
						}
						isBusy={ busy }
						onClick={ save }
					>
						{ __( 'Save roadmap sources', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="secondary"
						disabled={ busy || w.pending || ! dirty }
						onClick={ () => setSources( state.syllabus_ids ) }
					>
						{ __( 'Discard source changes', 'ohmylms' ) }
					</Button>
				</>
			) }
		</section>
	);
}
