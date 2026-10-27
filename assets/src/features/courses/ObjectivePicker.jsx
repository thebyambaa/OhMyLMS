/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import {
	defaultSyllabusId,
	filterObjectives,
	objectiveKey,
	objectiveRequest,
	rememberSyllabus,
	syllabusLabel,
} from './objectives.mjs';

/**
 * Browse one syllabus at a time, retaining selections across syllabus and search changes.
 * @param root0
 * @param root0.courseId
 * @param root0.data
 * @param root0.busy
 * @param root0.error
 * @param root0.onClose
 * @param root0.onSave
 */
export function ObjectivePicker( {
	courseId,
	data,
	busy,
	error,
	onClose,
	onSave,
} ) {
	const [ syllabusId, setSyllabusId ] = useState( () =>
		defaultSyllabusId( data.syllabuses, data.mappings, window.localStorage )
	);
	const [ rows, setRows ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ topic, setTopic ] = useState( '' );
	const [ selected, setSelected ] = useState( new Map() );
	const [ retry, setRetry ] = useState( 0 );
	useEffect( () => {
		let active = true;
		setRows( null );
		setLoadError( '' );
		objectiveRequest( courseId, { syllabusId } )
			.then( ( result ) => {
				if ( active ) {
					setRows( result.objectives );
				}
			} )
			.catch( ( cause ) => {
				if ( active ) {
					setLoadError(
						cause.message ||
							__( 'Could not load objectives.', 'ohmylms' )
					);
				}
			} );
		return () => {
			active = false;
		};
	}, [ syllabusId, courseId, retry ] );
	const saved = new Set( data.mappings.map( objectiveKey ) );
	const available = filterObjectives( rows || [], search, topic );
	const limitReached = data.mappings.length + selected.size >= data.limit;
	const choosable = available.filter(
		( row ) => ! saved.has( objectiveKey( row ) )
	);
	function selectShown() {
		setSelected( ( current ) => {
			const next = new Map( current );
			for ( const row of choosable ) {
				if ( data.mappings.length + next.size >= data.limit ) {
					break;
				}
				next.set( objectiveKey( row ), row );
			}
			return next;
		} );
	}
	function toggle( row, checked ) {
		setSelected( ( current ) => {
			const next = new Map( current );
			if ( checked ) {
				next.set( objectiveKey( row ), row );
			} else {
				next.delete( objectiveKey( row ) );
			}
			return next;
		} );
	}
	return (
		<Modal
			title={ __( 'Add syllabus objectives', 'ohmylms' ) }
			onRequestClose={ onClose }
			className="ohmylms-objective-picker"
			shouldCloseOnClickOutside={ ! busy }
			shouldCloseOnEsc={ ! busy }
		>
			{ ( error || loadError ) && (
				<Notice status="error" isDismissible={ false }>
					{ error || loadError }
					{ loadError && (
						<Button
							variant="link"
							onClick={ () => setRetry( retry + 1 ) }
						>
							{ __( 'Retry', 'ohmylms' ) }
						</Button>
					) }
				</Notice>
			) }
			<SelectControl
				label={ __( 'Official syllabus / edition', 'ohmylms' ) }
				value={ syllabusId }
				options={ data.syllabuses.map( ( row ) => ( {
					value: String( row.id ),
					label: syllabusLabel( row ),
				} ) ) }
				disabled={ busy }
				onChange={ ( value ) => {
					rememberSyllabus( value, window.localStorage );
					setSyllabusId( value );
					setTopic( '' );
				} }
			/>
			<TextControl
				label={ __( 'Search code, objective or topic', 'ohmylms' ) }
				type="search"
				value={ search }
				onChange={ setSearch }
				disabled={ busy }
			/>
			<div className="ohmylms-objective-browser">
				<aside>
					<SelectControl
						label={ __( 'Topic', 'ohmylms' ) }
						value={ topic }
						onChange={ setTopic }
						disabled={ busy || ! rows }
						options={ [
							{ value: '', label: __( 'All topics', 'ohmylms' ) },
							...[
								...new Set(
									( rows || [] ).map( ( row ) => row.topic )
								),
							].map( ( name ) => ( {
								value: name,
								label: name,
							} ) ),
						] }
					/>
				</aside>
				<div
					className="ohmylms-objective-results"
					aria-busy={ ! rows && ! loadError }
				>
					{ rows && choosable.length > 0 && (
						<div className="ohmylms-objective-bulk">
							<Button
								variant="secondary"
								size="small"
								disabled={ busy || limitReached }
								onClick={ selectShown }
							>
								{ topic
									? sprintf(
											/* translators: %d: number of objectives in the chosen topic. */
											__(
												'Select all %d in this topic',
												'ohmylms'
											),
											choosable.length
										)
									: sprintf(
											/* translators: %d: number of objectives shown. */
											__(
												'Select all %d shown',
												'ohmylms'
											),
											choosable.length
										) }
							</Button>
							{ selected.size > 0 && (
								<Button
									variant="tertiary"
									size="small"
									disabled={ busy }
									onClick={ () => setSelected( new Map() ) }
								>
									{ __( 'Clear selection', 'ohmylms' ) }
								</Button>
							) }
						</div>
					) }
					{ ! rows && ! loadError && <Spinner /> }
					{ rows && ! available.length && (
						<p>
							{ __(
								'No matching objectives in this syllabus.',
								'ohmylms'
							) }
						</p>
					) }
					{ available.map( ( row ) => {
						const key = objectiveKey( row );
						const added = saved.has( key );
						return (
							<div
								key={ key }
								className="ohmylms-objective-result"
							>
								<CheckboxControl
									label={ `${ row.code ? `${ row.code } · ` : '' }${ row.name }${ added ? ` (${ __( 'Added', 'ohmylms' ) })` : '' }` }
									checked={ added || selected.has( key ) }
									disabled={
										busy ||
										added ||
										( limitReached &&
											! selected.has( key ) )
									}
									onChange={ ( checked ) =>
										toggle( row, checked )
									}
								/>
								<p>{ row.objective }</p>
								<small>{ row.topic }</small>
							</div>
						);
					} ) }
				</div>
			</div>
			{ selected.size > 0 && (
				<details className="ohmylms-objective-selection">
					<summary>
						{ __( 'Review selected objectives', 'ohmylms' ) }
					</summary>
					{ [ ...selected.values() ].map( ( row ) => (
						<CheckboxControl
							key={ objectiveKey( row ) }
							label={ `${ row.code || '' } ${ row.name }` }
							checked
							disabled={ busy }
							onChange={ () => toggle( row, false ) }
						/>
					) ) }
				</details>
			) }
			<div className="ohmylms-objective-picker-actions">
				<span role="status">
					{ sprintf(
						/* translators: 1: selected objectives, 2: maximum targets per course. */ __(
							'%1$d selected · limit %2$d per course',
							'ohmylms'
						),
						selected.size,
						data.limit
					) }
				</span>
				<Button
					variant="tertiary"
					disabled={ busy }
					onClick={ onClose }
				>
					{ __( 'Cancel', 'ohmylms' ) }
				</Button>
				<Button
					variant="primary"
					isBusy={ busy }
					disabled={ busy || ! selected.size }
					onClick={ () => onSave( [ ...selected.values() ] ) }
				>
					{ __( 'Add selected objectives', 'ohmylms' ) }
				</Button>
			</div>
		</Modal>
	);
}
