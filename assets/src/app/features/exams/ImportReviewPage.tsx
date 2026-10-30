import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { ApiError, normalizeApiError } from '../../lib/api';
import {
	applyJob,
	createFigure,
	decideRow,
	deleteJob,
	extractJob,
	getJob,
	listFigures,
	searchSkills,
	updateRow,
} from './api';
import {
	LIMITS,
	POLL_MS,
	examPath,
	isWorking,
	rowStatusLabel,
	stateLabel,
	targetLabel,
	withSkill,
} from './model';
import type {
	FigureAsset,
	ImportFigure,
	ImportJobDetail,
	ImportPart,
	ImportRow,
	PartKind,
	RowChanges,
	SkillMatch,
} from './types';

const KINDS: readonly PartKind[] = [ 'numerical', 'text', 'written' ];

function routeId( value: string | undefined ): number | null {
	return value && /^[1-9]\d*$/.test( value ) ? Number( value ) : null;
}

function kindLabel( kind: PartKind ): string {
	if ( 'numerical' === kind ) {
		return __( 'Number (auto-marked)', 'ohmylms' );
	}
	return 'text' === kind
		? __( 'Text (auto-marked)', 'ohmylms' )
		: __( 'Written (teacher-marked)', 'ohmylms' );
}

/**
 * Find skills and add their references to a row.
 * @param root0
 * @param root0.onPick
 */
function SkillSearch( { onPick }: { onPick: ( skill: SkillMatch ) => void } ) {
	const [ query, setQuery ] = useState( '' );
	const operation = useOperation();
	const [ found, setFound ] = useState< SkillMatch[] | null >( null );
	return (
		<div>
			<TextControl
				label={ __( 'Find a skill', 'ohmylms' ) }
				value={ query }
				onChange={ setQuery }
			/>
			<Button
				variant="secondary"
				size="small"
				disabled={ ! query.trim() || operation.busy }
				onClick={ () =>
					void operation.run(
						( signal ) => searchSkills( query.trim(), signal ),
						( result ) => setFound( result.items )
					)
				}
			>
				{ __( 'Search skills', 'ohmylms' ) }
			</Button>
			{ operation.error && <p role="alert">{ operation.error }</p> }
			{ found && (
				<ul aria-label={ __( 'Skills', 'ohmylms' ) }>
					{ found.map( ( skill ) => (
						<li key={ skill.id }>
							{ [ skill.code, skill.name ]
								.filter( Boolean )
								.join( ' ' ) }
							{ skill.path && ` (${ skill.path })` }{ ' ' }
							<Button
								variant="link"
								onClick={ () => onPick( skill ) }
							>
								{ __( 'Use', 'ohmylms' ) }
							</Button>
						</li>
					) ) }
					{ ! found.length && (
						<li>{ __( 'No skills found.', 'ohmylms' ) }</li>
					) }
				</ul>
			) }
		</div>
	);
}

interface RowEditorProps {
	row: ImportRow;
	crops: FigureAsset[];
	editable: boolean;
	busy: boolean;
	onSave: ( changes: RowChanges ) => void;
	onDecide: ( decision: 'accept' | 'reject' ) => void;
}

/**
 * One question of the paper: edit, then accept or reject.
 * @param root0
 * @param root0.row
 * @param root0.crops
 * @param root0.editable
 * @param root0.busy
 * @param root0.onSave
 * @param root0.onDecide
 */
function RowEditor( {
	row,
	crops,
	editable,
	busy,
	onSave,
	onDecide,
}: RowEditorProps ) {
	const [ question, setQuestion ] = useState( row.question );
	const [ page, setPage ] = useState( String( row.page ) );
	const [ skills, setSkills ] = useState( row.skills );
	const [ notes, setNotes ] = useState( row.notes );
	const [ requiresSource, setRequiresSource ] = useState(
		row.requires_source
	);
	const [ parts, setParts ] = useState< ImportPart[] >( row.parts );
	const [ figures, setFigures ] = useState< ImportFigure[] >( row.figures );
	const [ error, setError ] = useState< string | null >( null );
	// Reset the draft only when the stored row changed, so other rows keep their unsaved edits.
	const stored = JSON.stringify( row );
	useEffect( () => {
		const next = JSON.parse( stored ) as ImportRow;
		setQuestion( next.question );
		setPage( String( next.page ) );
		setSkills( next.skills );
		setNotes( next.notes );
		setRequiresSource( next.requires_source );
		setParts( next.parts );
		setFigures( next.figures );
	}, [ stored ] );
	const changes: RowChanges = {};
	if ( question !== row.question ) {
		changes.question = question;
	}
	if ( page !== String( row.page ) ) {
		changes.page = Number( page );
	}
	if ( skills !== row.skills ) {
		changes.skills = skills;
	}
	if ( notes !== row.notes ) {
		changes.notes = notes;
	}
	if ( requiresSource !== row.requires_source ) {
		changes.requires_source = requiresSource;
	}
	if ( JSON.stringify( parts ) !== JSON.stringify( row.parts ) ) {
		changes.parts = parts;
	}
	if ( JSON.stringify( figures ) !== JSON.stringify( row.figures ) ) {
		changes.figures = figures;
	}
	const dirty = Object.keys( changes ).length > 0;
	const setPart = ( index: number, next: ImportPart ) =>
		setParts( ( current ) =>
			current.map( ( item, at ) => ( at === index ? next : item ) )
		);
	const setFigure = ( index: number, next: ImportFigure ) =>
		setFigures( ( current ) =>
			current.map( ( item, at ) => ( at === index ? next : item ) )
		);
	const readyCrops = crops.filter( ( crop ) => 'ready' === crop.state );
	function save() {
		if ( ! /^\d{1,3}$/.test( page ) || Number( page ) < 1 ) {
			setError(
				__(
					'Enter the source page as a whole number from 1.',
					'ohmylms'
				)
			);
			return;
		}
		if ( ! question.trim() ) {
			setError( __( 'The question needs its stem.', 'ohmylms' ) );
			return;
		}
		setError( null );
		onSave( changes );
	}
	return (
		<article aria-labelledby={ `oml-row-${ row.index }` }>
			<h4 id={ `oml-row-${ row.index }` }>
				{ sprintf(
					/* translators: 1: question number; 2: review status; 3: marks. */
					__( 'Question %1$s — %2$s — %3$s marks', 'ohmylms' ),
					row.number,
					rowStatusLabel( row.status ),
					String( row.marks )
				) }
			</h4>
			<fieldset disabled={ ! editable || busy }>
				<TextareaControl
					label={ __( 'Question stem', 'ohmylms' ) }
					value={ question }
					rows={ 3 }
					onChange={ setQuestion }
				/>
				<TextControl
					label={ __( 'Source page', 'ohmylms' ) }
					value={ page }
					inputMode="numeric"
					onChange={ setPage }
				/>
				<CheckboxControl
					label={ __( 'Needs the source figure', 'ohmylms' ) }
					checked={ requiresSource }
					onChange={ setRequiresSource }
				/>
				<TextControl
					label={ __( 'Skills', 'ohmylms' ) }
					help={ __(
						'Skill codes or #IDs, separated by semicolons.',
						'ohmylms'
					) }
					value={ skills }
					onChange={ setSkills }
				/>
				{ editable && (
					<SkillSearch
						onPick={ ( skill ) =>
							setSkills( withSkill( skills, skill.id ) )
						}
					/>
				) }
				{ parts.map( ( part, index ) => (
					<fieldset key={ part.id }>
						<legend>
							{ sprintf(
								/* translators: %s: part label or ID. */
								__( 'Part %s', 'ohmylms' ),
								part.label || part.id
							) }
						</legend>
						<TextareaControl
							label={ __( 'Prompt', 'ohmylms' ) }
							value={ part.prompt ?? '' }
							rows={ 2 }
							onChange={ ( prompt ) =>
								setPart( index, { ...part, prompt } )
							}
						/>
						<SelectControl
							label={ __( 'Answer type', 'ohmylms' ) }
							value={ part.kind ?? 'written' }
							options={ KINDS.map( ( kind ) => ( {
								value: kind,
								label: kindLabel( kind ),
							} ) ) }
							onChange={ ( next ) => {
								const kind = KINDS.find(
									( item ) => item === next
								);
								if ( kind ) {
									setPart( index, { ...part, kind } );
								}
							} }
						/>
						<TextControl
							label={ __( 'Marks', 'ohmylms' ) }
							value={ String( part.marks ) }
							inputMode="decimal"
							onChange={ ( marks ) =>
								setPart( index, {
									...part,
									marks: Number( marks ) || 0,
								} )
							}
						/>
						{ 'numerical' === part.kind && (
							<TextControl
								label={ __( 'Answer', 'ohmylms' ) }
								value={
									undefined === part.answer
										? ''
										: String( part.answer )
								}
								inputMode="decimal"
								onChange={ ( answer ) =>
									setPart( index, {
										...part,
										...( '' === answer.trim()
											? { answer: undefined }
											: { answer: Number( answer ) } ),
									} )
								}
							/>
						) }
						{ 'text' === part.kind && (
							<TextControl
								label={ __( 'Accepted answers', 'ohmylms' ) }
								help={ __(
									'Separate alternatives with a vertical bar (|).',
									'ohmylms'
								) }
								value={ ( part.accepted ?? [] ).join( '|' ) }
								onChange={ ( accepted ) =>
									setPart( index, {
										...part,
										accepted: accepted
											.split( '|' )
											.map( ( item ) => item.trim() )
											.filter( Boolean ),
									} )
								}
							/>
						) }
						<TextareaControl
							label={ __(
								'Marking notes (teachers only)',
								'ohmylms'
							) }
							value={ part.rubric ?? '' }
							rows={ 2 }
							onChange={ ( rubric ) =>
								setPart( index, { ...part, rubric } )
							}
						/>
					</fieldset>
				) ) }
				{ figures.map( ( item, index ) => {
					const crop = crops.find(
						( entry ) => entry.id === item.asset_id
					);
					return (
						<fieldset key={ item.id }>
							<legend>
								{ sprintf(
									/* translators: %s: figure ID. */
									__( 'Figure %s', 'ohmylms' ),
									item.id
								) }
							</legend>
							{ crop?.url ? (
								<img
									src={ crop.url }
									alt={ item.alt }
									style={ { maxWidth: '20rem' } }
								/>
							) : (
								<p>
									{ __(
										'The crop is not ready yet.',
										'ohmylms'
									) }
								</p>
							) }
							<SelectControl
								label={ __( 'Crop', 'ohmylms' ) }
								value={ item.asset_id }
								options={ [
									{
										value: '',
										label: __( 'Choose a crop', 'ohmylms' ),
									},
									...readyCrops.map( ( entry ) => ( {
										value: entry.id,
										label: sprintf(
											/* translators: %d: source page. */
											__( 'Page %d crop', 'ohmylms' ),
											entry.page
										),
									} ) ),
								] }
								onChange={ ( assetId ) =>
									setFigure( index, {
										...item,
										asset_id: assetId,
									} )
								}
							/>
							<TextControl
								label={ __(
									'Description for screen readers',
									'ohmylms'
								) }
								value={ item.alt }
								onChange={ ( alt ) =>
									setFigure( index, { ...item, alt } )
								}
							/>
							<CheckboxControl
								label={ __( 'Approve this figure', 'ohmylms' ) }
								checked={ item.approved }
								onChange={ ( approved ) =>
									setFigure( index, { ...item, approved } )
								}
							/>
							<Button
								variant="link"
								isDestructive
								onClick={ () =>
									setFigures(
										figures.filter(
											( _, at ) => at !== index
										)
									)
								}
							>
								{ __( 'Remove figure', 'ohmylms' ) }
							</Button>
						</fieldset>
					);
				} ) }
				{ editable && readyCrops.length > 0 && figures.length < 20 && (
					<Button
						variant="secondary"
						size="small"
						onClick={ () => {
							const crop = readyCrops[ 0 ] as FigureAsset;
							setFigures( [
								...figures,
								{
									id: `f${ figures.length + 1 }`,
									page: crop.page,
									bounds: crop.bounds,
									part_ids: [],
									asset_id: crop.id,
									alt: '',
									not_to_scale: false,
									selected: 'original',
									approved: false,
								},
							] );
						} }
					>
						{ __( 'Add figure', 'ohmylms' ) }
					</Button>
				) }
				<TextareaControl
					label={ __( 'Review notes', 'ohmylms' ) }
					value={ notes }
					rows={ 2 }
					onChange={ setNotes }
				/>
			</fieldset>
			{ error && <p role="alert">{ error }</p> }
			{ editable && (
				<p>
					<Button
						variant="secondary"
						disabled={ busy || ! dirty }
						onClick={ save }
					>
						{ __( 'Save changes', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="primary"
						disabled={ busy || dirty || 'accepted' === row.status }
						onClick={ () => onDecide( 'accept' ) }
					>
						{ __( 'Accept', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						isDestructive
						disabled={ busy || 'rejected' === row.status }
						onClick={ () => onDecide( 'reject' ) }
					>
						{ __( 'Reject', 'ohmylms' ) }
					</Button>
				</p>
			) }
		</article>
	);
}

/**
 * Import review (`#/exams/imports/<jobId>`): job status (polled while the server works), extraction,
 * row-by-row review with parts, skills and figures, and apply. Replaces the review dialog of the legacy
 * Content Hub Exams tab.
 */
export default function ImportReviewPage() {
	const params = useParams();
	const id = routeId( params.jobId );
	const load = useCallback(
		( signal: AbortSignal ) =>
			null === id
				? Promise.reject( new Error( 'invalid id' ) )
				: getJob( id, signal ),
		[ id ]
	);
	const request = useRequest( load );
	const operation = useOperation();
	const [ job, setJob ] = useState< ImportJobDetail | null >( null );
	const [ crops, setCrops ] = useState< FigureAsset[] >( [] );
	const [ failure, setFailure ] = useState< ApiError | null >( null );
	const [ message, setMessage ] = useState< string | null >( null );
	const [ title, setTitle ] = useState( '' );
	const [ minutes, setMinutes ] = useState( '' );
	const [ crop, setCrop ] = useState( {
		page: '1',
		left: '0',
		top: '0',
		right: '100',
		bottom: '50',
	} );
	const [ deleting, setDeleting ] = useState( false );
	const [ deleted, setDeleted ] = useState( false );

	const adopt = useCallback( ( next: ImportJobDetail ) => {
		setJob( next );
		setTitle(
			( current ) => current || next.inventory.title || next.title
		);
		setMinutes(
			( current ) =>
				current || String( next.inventory.duration_minutes || 60 )
		);
	}, [] );

	useEffect( () => {
		if ( request.data ) {
			adopt( request.data );
		}
	}, [ request.data, adopt ] );

	const state = job?.state;
	useEffect( () => {
		if ( null === id || ! state || ! isWorking( state ) ) {
			return undefined;
		}
		const controller = new AbortController();
		const timer = window.setTimeout( () => {
			getJob( id, controller.signal ).then( adopt, () => undefined );
		}, POLL_MS );
		return () => {
			window.clearTimeout( timer );
			controller.abort();
		};
	}, [ id, state, job, adopt ] );

	const kind = job?.kind;
	useEffect( () => {
		if ( null === id || 'pdf' !== kind ) {
			return undefined;
		}
		const controller = new AbortController();
		listFigures( id, controller.signal ).then( setCrops, () => undefined );
		return () => controller.abort();
	}, [ id, kind ] );

	if ( null === id ) {
		return (
			<>
				<h2>{ __( 'Import review', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ __( 'This import link is invalid.', 'ohmylms' ) }
				</Notice>
				<Link to="/exams">{ __( 'Back to exams', 'ohmylms' ) }</Link>
			</>
		);
	}
	if ( deleted ) {
		return (
			<>
				<h2>{ __( 'Import review', 'ohmylms' ) }</h2>
				<Notice status="success" isDismissible={ false }>
					{ __(
						'The import and its private files were deleted.',
						'ohmylms'
					) }
				</Notice>
				<Link to="/exams">{ __( 'Back to exams', 'ohmylms' ) }</Link>
			</>
		);
	}
	if ( request.loading && ! job ) {
		return (
			<>
				<h2>{ __( 'Import review', 'ohmylms' ) }</h2>
				<p role="status">{ __( 'Loading import…', 'ohmylms' ) }</p>
			</>
		);
	}
	if ( ! job ) {
		return (
			<>
				<h2>{ __( 'Import review', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ request.error ||
						__( 'The import could not be loaded.', 'ohmylms' ) }
				</Notice>
				<p>
					<Button variant="secondary" onClick={ request.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>{ ' ' }
					<Link to="/exams">
						{ __( 'Back to exams', 'ohmylms' ) }
					</Link>
				</p>
			</>
		);
	}

	function run(
		work: ( signal: AbortSignal ) => Promise< ImportJobDetail >,
		done: string
	) {
		setMessage( null );
		setFailure( null );
		void operation.run(
			async ( signal ) => {
				try {
					return await work( signal );
				} catch ( problem ) {
					const error = normalizeApiError( problem );
					if ( ! signal.aborted ) {
						setFailure( error );
					}
					throw error;
				}
			},
			( result ) => {
				adopt( result );
				setMessage( done );
			}
		);
	}

	const current = job;
	const reviewable = 'ready_for_review' === current.state;
	const pending = current.rows.filter(
		( row ) => 'pending' === row.status
	).length;

	function apply() {
		const duration = /^\d{1,4}$/.test( minutes ) ? Number( minutes ) : 0;
		if (
			'questions' !== current.target &&
			( ! title.trim() ||
				Array.from( title.trim() ).length > LIMITS.title )
		) {
			setFailure(
				new ApiError( {
					status: 422,
					code: 'ohmylms_exam_invalid_field',
					message: __(
						'Enter a title of 1 to 190 characters.',
						'ohmylms'
					),
				} )
			);
			return;
		}
		if (
			'exam' === current.target &&
			( duration < 1 || duration > LIMITS.duration )
		) {
			setFailure(
				new ApiError( {
					status: 422,
					code: 'ohmylms_exam_invalid_field',
					message: __(
						'Enter a duration from 1 to 1440 minutes.',
						'ohmylms'
					),
				} )
			);
			return;
		}
		run(
			( signal ) =>
				applyJob(
					current.id,
					current.revision,
					'questions' === current.target
						? {}
						: {
								title: title.trim(),
								...( 'exam' === current.target
									? { duration_minutes: duration }
									: {} ),
							},
					signal
				),
			__( 'Import applied.', 'ohmylms' )
		);
	}

	function requestCrop() {
		const values = [ crop.left, crop.top, crop.right, crop.bottom ].map(
			( value ) => Number( value ) / 100
		);
		const valid =
			/^\d{1,3}$/.test( crop.page ) &&
			Number( crop.page ) >= 1 &&
			values.every(
				( value ) =>
					Number.isFinite( value ) && value >= 0 && value <= 1
			) &&
			( values[ 0 ] as number ) < ( values[ 2 ] as number ) &&
			( values[ 1 ] as number ) < ( values[ 3 ] as number );
		if ( ! valid ) {
			setFailure(
				new ApiError( {
					status: 422,
					code: 'ohmylms_exam_import_crop',
					message: __(
						'Choose a page and a crop area from 0 to 100 percent, with left before right and top before bottom.',
						'ohmylms'
					),
				} )
			);
			return;
		}
		setFailure( null );
		void operation.run(
			( signal ) =>
				createFigure(
					current.id,
					Number( crop.page ),
					values as [ number, number, number, number ],
					signal
				).then( () => listFigures( current.id, signal ) ),
			( next ) => {
				setCrops( next );
				setMessage(
					__(
						'Crop requested. It appears here when it is ready.',
						'ohmylms'
					)
				);
			}
		);
	}

	return (
		<>
			<p>
				<Link to="/exams">{ __( 'Back to exams', 'ohmylms' ) }</Link>
			</p>
			<h2>
				{ sprintf(
					/* translators: %s: import title. */
					__( 'Review import: %s', 'ohmylms' ),
					current.title || current.source.name
				) }
			</h2>
			<p role="status">
				{ stateLabel( current.state ) } ·{ ' ' }
				{ targetLabel( current.target ) }
				{ current.row_count > 0 &&
					` · ${ sprintf(
						/* translators: 1: accepted rows; 2: rejected rows; 3: all rows. */
						__( '%1$d accepted, %2$d rejected of %3$d', 'ohmylms' ),
						current.accepted_count,
						current.rejected_count,
						current.row_count
					) }` }
			</p>
			{ ( current.error || current.validation_error ) && (
				<Notice status="warning" isDismissible={ false }>
					{ current.validation_error || current.error }
				</Notice>
			) }
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			{ failure && (
				<Notice status="error" isDismissible={ false }>
					{ failure.message }
					{ 'ohmylms_exam_import_conflict' === failure.code && (
						<>
							{ ' ' }
							<Button variant="link" onClick={ request.retry }>
								{ __( 'Reload the latest version', 'ohmylms' ) }
							</Button>
						</>
					) }
				</Notice>
			) }
			{ current.can_extract && (
				<p>
					<Button
						variant="primary"
						disabled={ operation.busy }
						onClick={ () =>
							run(
								( signal ) => extractJob( current.id, signal ),
								__( 'Extraction started.', 'ohmylms' )
							)
						}
					>
						{ __( 'Extract questions with AI', 'ohmylms' ) }
					</Button>
				</p>
			) }
			{ isWorking( current.state ) && (
				<p>
					{ __(
						'The server is working on this import. This page updates by itself.',
						'ohmylms'
					) }
				</p>
			) }

			{ reviewable && (
				<section aria-labelledby="oml-import-rows">
					<h3 id="oml-import-rows">
						{ __( 'Questions', 'ohmylms' ) }
					</h3>
					{ current.rows.map( ( row ) => (
						<RowEditor
							key={ row.index }
							row={ row }
							crops={ crops }
							editable={ reviewable }
							busy={ operation.busy }
							onSave={ ( changes ) =>
								run(
									( signal ) =>
										updateRow(
											current.id,
											row.index,
											changes,
											current.revision,
											signal
										),
									__(
										'Question saved. Accept it when it is right.',
										'ohmylms'
									)
								)
							}
							onDecide={ ( decision ) =>
								run(
									( signal ) =>
										decideRow(
											current.id,
											row.index,
											decision,
											current.revision,
											signal
										),
									'accept' === decision
										? __( 'Question accepted.', 'ohmylms' )
										: __( 'Question rejected.', 'ohmylms' )
								)
							}
						/>
					) ) }
				</section>
			) }

			{ 'pdf' === current.kind && reviewable && (
				<section aria-labelledby="oml-import-crops">
					<h3 id="oml-import-crops">
						{ __( 'Figure crops', 'ohmylms' ) }
					</h3>
					<ul>
						{ crops.map( ( entry ) => (
							<li key={ entry.id }>
								{ sprintf(
									/* translators: 1: page; 2: crop state. */
									__( 'Page %1$d: %2$s', 'ohmylms' ),
									entry.page,
									entry.state
								) }
							</li>
						) ) }
					</ul>
					<fieldset disabled={ operation.busy }>
						<legend>{ __( 'Request a crop', 'ohmylms' ) }</legend>
						{ (
							[
								[ 'page', __( 'Page', 'ohmylms' ) ],
								[ 'left', __( 'Left (%)', 'ohmylms' ) ],
								[ 'top', __( 'Top (%)', 'ohmylms' ) ],
								[ 'right', __( 'Right (%)', 'ohmylms' ) ],
								[ 'bottom', __( 'Bottom (%)', 'ohmylms' ) ],
							] as const
						 ).map( ( [ key, label ] ) => (
							<TextControl
								key={ key }
								label={ label }
								value={ crop[ key ] }
								inputMode="numeric"
								onChange={ ( value ) =>
									setCrop( { ...crop, [ key ]: value } )
								}
							/>
						) ) }
						<Button variant="secondary" onClick={ requestCrop }>
							{ __( 'Request crop', 'ohmylms' ) }
						</Button>
					</fieldset>
				</section>
			) }

			{ ( current.can_apply ||
				( reviewable && current.row_count > 0 ) ) && (
				<section aria-labelledby="oml-import-apply">
					<h3 id="oml-import-apply">{ __( 'Apply', 'ohmylms' ) }</h3>
					{ pending > 0 && (
						<p>
							{ sprintf(
								/* translators: %d: rows still to review. */
								__(
									'%d questions still need a decision.',
									'ohmylms'
								),
								pending
							) }
						</p>
					) }
					{ 'questions' !== current.target && (
						<TextControl
							label={
								'exam' === current.target
									? __( 'Exam title', 'ohmylms' )
									: __( 'Quiz title', 'ohmylms' )
							}
							value={ title }
							maxLength={ LIMITS.title }
							onChange={ setTitle }
						/>
					) }
					{ 'exam' === current.target && (
						<TextControl
							label={ __( 'Duration (minutes)', 'ohmylms' ) }
							value={ minutes }
							inputMode="numeric"
							onChange={ setMinutes }
						/>
					) }
					<Button
						variant="primary"
						disabled={ operation.busy || ! current.can_apply }
						isBusy={ operation.busy }
						onClick={ apply }
					>
						{ __( 'Apply import', 'ohmylms' ) }
					</Button>
				</section>
			) }

			{ current.exam_id > 0 && (
				<p>
					<Link to={ examPath( current.exam_id ) }>
						{ __( 'Open the exam', 'ohmylms' ) }
					</Link>
				</p>
			) }
			{ 'committed' === current.state &&
				0 === current.exam_id &&
				'exam' !== current.target && (
					<p>
						{ sprintf(
							/* translators: %d: number of questions. */
							__(
								'%d questions were saved to the question bank as drafts.',
								'ohmylms'
							),
							current.question_ids.length
						) }
					</p>
				) }
			{ 0 === current.exam_id && (
				<p>
					<Button
						variant="link"
						isDestructive
						disabled={ operation.busy }
						onClick={ () => setDeleting( true ) }
					>
						{ __( 'Delete import', 'ohmylms' ) }
					</Button>
				</p>
			) }
			{ deleting && (
				<Modal
					title={ __( 'Delete import', 'ohmylms' ) }
					onRequestClose={ () =>
						! operation.busy && setDeleting( false )
					}
					shouldCloseOnClickOutside={ false }
				>
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error }
						</Notice>
					) }
					<p>
						{ __(
							'Delete this import and its private source files? Questions already saved to the question bank stay.',
							'ohmylms'
						) }
					</p>
					<Button
						variant="primary"
						isDestructive
						disabled={ operation.busy }
						onClick={ () =>
							void operation.run(
								( signal ) => deleteJob( current.id, signal ),
								() => {
									setDeleting( false );
									setDeleted( true );
								}
							)
						}
					>
						{ __( 'Delete', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ operation.busy }
						onClick={ () => setDeleting( false ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}
