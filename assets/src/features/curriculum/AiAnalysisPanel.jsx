/** @jsx createElement */
import {
	createElement,
	Fragment,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { readComparison, writeComparison } from './comparison.mjs';
import {
	gradeBand,
	isActive,
	overridesFor,
	percent,
	progress,
	proposes,
	skillSummary,
	waitsForProposal,
	weightPercent,
} from './aiAnalysis.mjs';

const RELATIONS = [ 'equivalent', 'broader', 'narrower', 'related' ];

/**
 * AI analysis of a syllabus, and the review queue of what it suggests.
 * Nothing changes the curriculum until a suggestion is accepted.
 *
 * @param {Object}     props           Panel props.
 * @param {Array}      props.sources   Syllabus records (id, name) the editor works with.
 * @param {string}     props.subject   Subject of new shared skills.
 * @param {() => void} props.onChanged Called after suggestions are accepted.
 */
export function AiAnalysisPanel( { sources, subject, onChanged } ) {
	const [ options, setOptions ] = useState( null );
	const [ syllabusId, setSyllabusId ] = useState(
		String( sources[ 0 ]?.id || '' )
	);
	const [ includeMapped, setIncludeMapped ] = useState( false );
	const [ runs, setRuns ] = useState( [] );
	const [ run, setRun ] = useState( null );
	const [ list, setList ] = useState( { items: [], total: 0 } );
	const [ selected, setSelected ] = useState( [] );
	const [ edits, setEdits ] = useState( {} );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ reload, setReload ] = useState( 0 );
	const open = useRef( true );
	useEffect(
		() => () => {
			open.current = false;
		},
		[]
	);
	useEffect( () => {
		readComparison( 'skill-placement', { subject } )
			.then( setOptions )
			.catch( ( cause ) =>
				setNotice( { status: 'error', message: cause.message } )
			);
	}, [ subject ] );
	useEffect( () => {
		readComparison( 'ai/runs', { syllabus_id: syllabusId || 0, subject } )
			.then( ( rows ) => {
				setRuns( rows );
				setRun( ( current ) =>
					current && rows.some( ( row ) => row.id === current.id )
						? current
						: rows[ 0 ] || null
				);
			} )
			.catch( () => setRuns( [] ) );
	}, [ syllabusId, subject, reload ] );
	// Keep advancing an active run while the panel is open; WP-Cron finishes it otherwise.
	useEffect( () => {
		if ( ! isActive( run ) ) {
			return undefined;
		}
		let stopped = false;
		const advance = async () => {
			try {
				const next = await writeComparison(
					`ai/runs/${ run.id }/step`,
					{}
				);
				if ( ! stopped && open.current ) {
					setRun( next );
				}
			} catch ( cause ) {
				if ( ! stopped ) {
					setNotice( { status: 'error', message: cause.message } );
				}
			}
		};
		const timer = setTimeout( advance, 600 );
		return () => {
			stopped = true;
			clearTimeout( timer );
		};
	}, [ run ] );
	useEffect( () => {
		if ( ! run ) {
			setList( { items: [], total: 0 } );
			return;
		}
		readComparison( 'ai/suggestions', { run_id: run.id } )
			.then( ( data ) => {
				setList( data );
				setSelected( [] );
			} )
			.catch( ( cause ) =>
				setNotice( { status: 'error', message: cause.message } )
			);
	}, [ run, reload ] );

	async function start( kind ) {
		setBusy( true );
		setNotice( null );
		try {
			const started = await writeComparison(
				'ai/runs',
				kind === 'placement' || kind === 'links'
					? { kind, subject }
					: {
							kind,
							syllabus_id: Number( syllabusId ),
							include_mapped: includeMapped,
						}
			);
			setRun( started );
			setReload( ( value ) => value + 1 );
		} catch ( cause ) {
			setNotice( { status: 'error', message: cause.message } );
			if ( cause.data?.run ) {
				setRun( cause.data.run );
			}
		} finally {
			setBusy( false );
		}
	}

	async function review( ids, action ) {
		if ( ! ids.length ) {
			return;
		}
		setBusy( true );
		setNotice( null );
		try {
			const response = await writeComparison( 'ai/suggestions/review', {
				ids,
				action,
				overrides:
					action === 'accept' ? overridesFor( edits, ids ) : {},
			} );
			const failed = Object.values( response.results ).filter(
				( row ) => row.error
			);
			setNotice(
				failed.length
					? {
							status: 'warning',
							message: failed
								.map( ( row ) => row.error )
								.join( ' ' ),
						}
					: {
							status: 'success',
							message:
								action === 'accept'
									? __( 'Accepted.', 'ohmylms' )
									: __( 'Rejected.', 'ohmylms' ),
						}
			);
			if ( action === 'accept' ) {
				onChanged();
			}
			setReload( ( value ) => value + 1 );
		} catch ( cause ) {
			setNotice( { status: 'error', message: cause.message } );
		} finally {
			setBusy( false );
		}
	}

	async function acceptConfident() {
		setBusy( true );
		try {
			const result = await writeComparison(
				`ai/runs/${ run.id }/accept-confident`,
				{ min_confidence: 0.85 }
			);
			setNotice( {
				status: result.failed ? 'warning' : 'success',
				message: sprintf(
					/* translators: 1: accepted suggestions, 2: suggestions that could not be accepted. */
					__(
						'%1$d accepted, %2$d could not be accepted.',
						'ohmylms'
					),
					result.accepted,
					result.failed
				),
			} );
			onChanged();
			setReload( ( value ) => value + 1 );
		} catch ( cause ) {
			setNotice( { status: 'error', message: cause.message } );
		} finally {
			setBusy( false );
		}
	}

	const edit = ( id, field, value ) =>
		setEdits( ( current ) => ( {
			...current,
			[ id ]: { ...( current[ id ] || {} ), [ field ]: value },
		} ) );
	const domainOptions = [
		{ label: __( 'No domain', 'ohmylms' ), value: '' },
		...Object.entries( options?.domains || {} ).map(
			( [ value, label ] ) => ( { value, label } )
		),
	];
	const gradeOptions = [
		{ label: '—', value: '0' },
		...( options?.grades || [] ).map( ( grade ) => ( {
			label: grade.label,
			value: String( grade.value ),
		} ) ),
	];
	const placementFields = ( row, values ) => (
		<div className="ohmylms-ai-placement">
			<SelectControl
				label={ __( 'Domain', 'ohmylms' ) }
				value={ edits[ row.id ]?.domain ?? values?.domain ?? '' }
				options={ domainOptions }
				onChange={ ( value ) => edit( row.id, 'domain', value ) }
				__nextHasNoMarginBottom
			/>
			<SelectControl
				label={ __( 'From', 'ohmylms' ) }
				value={ String(
					edits[ row.id ]?.grade_from ?? values?.grade_from ?? 0
				) }
				options={ gradeOptions }
				onChange={ ( value ) =>
					edit( row.id, 'grade_from', Number( value ) )
				}
				__nextHasNoMarginBottom
			/>
			<SelectControl
				label={ __( 'To', 'ohmylms' ) }
				value={ String(
					edits[ row.id ]?.grade_to ?? values?.grade_to ?? 0
				) }
				options={ gradeOptions }
				onChange={ ( value ) =>
					edit( row.id, 'grade_to', Number( value ) )
				}
				__nextHasNoMarginBottom
			/>
		</div>
	);

	if ( ! options ) {
		return <Spinner />;
	}
	return (
		<section
			className="ohmylms-ai-analysis"
			aria-label={ __( 'AI analysis', 'ohmylms' ) }
		>
			{ ! options.ai_available && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Connect an AI provider under Settings → AI tools to analyse syllabuses.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }
			<div className="ohmylms-ai-controls">
				<SelectControl
					label={ __( 'Syllabus', 'ohmylms' ) }
					value={ syllabusId }
					options={ sources.map( ( source ) => ( {
						label: source.name,
						value: String( source.id ),
					} ) ) }
					onChange={ setSyllabusId }
					__nextHasNoMarginBottom
				/>
				<CheckboxControl
					label={ __(
						'Include objectives already mapped',
						'ohmylms'
					) }
					checked={ includeMapped }
					onChange={ setIncludeMapped }
					__nextHasNoMarginBottom
				/>
				<Button
					variant="primary"
					disabled={
						busy ||
						! syllabusId ||
						! options.ai_available ||
						isActive( run )
					}
					isBusy={ busy }
					onClick={ () => start( 'syllabus' ) }
				>
					{ __( 'Analyze with AI', 'ohmylms' ) }
				</Button>
				<Button
					variant="secondary"
					disabled={
						busy || ! options.ai_available || isActive( run )
					}
					onClick={ () => start( 'placement' ) }
				>
					{ __(
						'Place shared skills in domains and levels',
						'ohmylms'
					) }
				</Button>
				<Button
					variant="secondary"
					disabled={
						busy || ! options.ai_available || isActive( run )
					}
					onClick={ () => start( 'links' ) }
				>
					{ __( 'Suggest prerequisites and links', 'ohmylms' ) }
				</Button>
			</div>
			<p className="ohmylms-ai-help">
				{ __(
					'The AI places each objective in a domain and level band, then suggests which shared skills it maps to, or proposes a new shared skill. Nothing changes until you accept a suggestion.',
					'ohmylms'
				) }
			</p>
			{ runs.length > 0 && (
				<SelectControl
					label={ __( 'Analysis', 'ohmylms' ) }
					value={ String( run?.id || '' ) }
					options={ runs.map( ( row ) => ( {
						value: String( row.id ),
						label: sprintf(
							/* translators: 1: date, 2: status, 3: pending suggestions. */
							__( '%1$s · %2$s · %3$d to review', 'ohmylms' ),
							row.created_at,
							row.status,
							row.pending
						),
					} ) ) }
					onChange={ ( value ) =>
						setRun(
							runs.find( ( row ) => String( row.id ) === value )
						)
					}
					__nextHasNoMarginBottom
				/>
			) }
			{ run && (
				<div className="ohmylms-ai-run" role="status">
					<progress max="100" value={ progress( run ) } />
					<span>
						{ sprintf(
							/* translators: 1: processed, 2: total, 3: suggestions, 4: status. */
							__(
								'%1$d of %2$d analysed · %3$d suggestions · %4$s',
								'ohmylms'
							),
							run.processed,
							run.total,
							run.suggested,
							run.status
						) }
					</span>
					{ run.failures > 0 && (
						<span className="ohmylms-ai-failures">
							{ sprintf(
								/* translators: %d: objectives the AI could not analyse. */
								__( '%d could not be analysed.', 'ohmylms' ),
								run.failures
							) }{ ' ' }
							{ run.error }
						</span>
					) }
					{ isActive( run ) && (
						<Button
							variant="link"
							onClick={ () =>
								writeComparison(
									`ai/runs/${ run.id }/cancel`,
									{}
								).then( setRun )
							}
						>
							{ __( 'Stop', 'ohmylms' ) }
						</Button>
					) }
				</div>
			) }
			{ list.items.length > 0 && (
				<Fragment>
					<div className="ohmylms-ai-bulk">
						<Button
							variant="secondary"
							disabled={ busy || ! selected.length }
							onClick={ () => review( selected, 'accept' ) }
						>
							{ sprintf(
								/* translators: %d: selected suggestions. */
								__( 'Accept %d selected', 'ohmylms' ),
								selected.length
							) }
						</Button>
						<Button
							variant="secondary"
							disabled={ busy || ! selected.length }
							onClick={ () => review( selected, 'reject' ) }
						>
							{ __( 'Reject selected', 'ohmylms' ) }
						</Button>
						<Button
							variant="tertiary"
							disabled={ busy }
							onClick={ acceptConfident }
						>
							{ __( 'Accept all at 85% or more', 'ohmylms' ) }
						</Button>
						<span>
							{ sprintf(
								/* translators: %d: suggestions waiting for review. */
								__( '%d waiting for review', 'ohmylms' ),
								list.total
							) }
						</span>
					</div>
					<table className="widefat striped ohmylms-ai-suggestions">
						<thead>
							<tr>
								<th>
									<CheckboxControl
										label={ __( 'Select all', 'ohmylms' ) }
										checked={
											selected.length ===
											list.items.length
										}
										onChange={ ( value ) =>
											setSelected(
												value
													? list.items.map(
															( row ) => row.id
														)
													: []
											)
										}
										__nextHasNoMarginBottom
									/>
								</th>
								<th>
									{ __( 'Objective or skill', 'ohmylms' ) }
								</th>
								<th>{ __( 'Suggestion', 'ohmylms' ) }</th>
								<th>{ __( 'Confidence', 'ohmylms' ) }</th>
								<th />
							</tr>
						</thead>
						<tbody>
							{ list.items.map( ( row ) => (
								<tr key={ row.id }>
									<td>
										<CheckboxControl
											label={ __( 'Select', 'ohmylms' ) }
											checked={ selected.includes(
												row.id
											) }
											onChange={ ( value ) =>
												setSelected( ( current ) =>
													value
														? [ ...current, row.id ]
														: current.filter(
																( id ) =>
																	id !==
																	row.id
															)
												)
											}
											__nextHasNoMarginBottom
										/>
									</td>
									<td>
										{ row.objective ? (
											<Fragment>
												<strong>
													{ row.objective.code }
												</strong>{ ' ' }
												{ row.objective.name }
												{ row.objective.gloss && (
													<div className="ohmylms-ai-gloss">
														{ row.objective.gloss }
													</div>
												) }
												<div className="ohmylms-ai-meta">
													{ [
														options.domains[
															row.objective.domain
														],
														gradeBand(
															row.objective
																.grade_from,
															row.objective
																.grade_to,
															options.grades
														),
													]
														.filter( Boolean )
														.join( ' · ' ) }
												</div>
											</Fragment>
										) : (
											<Fragment>
												<strong>
													{ row.skill?.name ||
														row.shared?.name }
												</strong>
												<div className="ohmylms-ai-meta">
													{ skillSummary(
														row.skill || row.shared,
														options?.grades
													) }
												</div>
											</Fragment>
										) }
									</td>
									<td>
										<span
											className={ `ohmylms-ai-kind is-${ row.kind }` }
										>
											{
												{
													new_skill: __(
														'New shared skill',
														'ohmylms'
													),
													mapping: __(
														'Map to',
														'ohmylms'
													),
													placement: __(
														'Domain and levels',
														'ohmylms'
													),
													link:
														row.relation ===
														'encompasses'
															? __(
																	'Includes',
																	'ohmylms'
																)
															: __(
																	'Needs first',
																	'ohmylms'
																),
												}[ row.kind ]
											}
										</span>
										{ row.kind === 'new_skill' ? (
											<Fragment>
												<TextControl
													label={ __(
														'Name',
														'ohmylms'
													) }
													value={
														edits[ row.id ]?.name ??
														row.proposal?.name ??
														''
													}
													onChange={ ( value ) =>
														edit(
															row.id,
															'name',
															value
														)
													}
													__nextHasNoMarginBottom
												/>
												{ placementFields(
													row,
													row.proposal
												) }
											</Fragment>
										) : (
											row.kind !== 'link' && (
												<div>
													{ proposes(
														row,
														options?.grades
													) }
												</div>
											)
										) }
										{ row.kind === 'mapping' && (
											<SelectControl
												label={ __(
													'Relationship',
													'ohmylms'
												) }
												value={
													edits[ row.id ]?.relation ??
													row.relation
												}
												options={ RELATIONS.map(
													( value ) => ( {
														value,
														label: value,
													} )
												) }
												onChange={ ( value ) =>
													edit(
														row.id,
														'relation',
														value
													)
												}
												__nextHasNoMarginBottom
											/>
										) }
										{ row.kind === 'placement' &&
											placementFields(
												row,
												row.placement
											) }
										{ row.kind === 'link' && (
											<Fragment>
												<div>
													<strong>
														{ row.shared?.name }
													</strong>
													<div className="ohmylms-ai-meta">
														{ skillSummary(
															row.shared,
															options?.grades
														) }
													</div>
												</div>
												{ row.relation ===
													'encompasses' && (
													<TextControl
														type="number"
														min="0.05"
														max="1"
														step="0.05"
														label={ sprintf(
															/* translators: %d: percentage of the included skill practised. */
															__(
																'Weight (practising this skill covers %d%% of the included one)',
																'ohmylms'
															),
															weightPercent(
																edits[ row.id ]
																	?.weight ??
																	row.weight
															)
														) }
														value={
															edits[ row.id ]
																?.weight ??
															row.weight ??
															0.5
														}
														onChange={ ( value ) =>
															edit(
																row.id,
																'weight',
																Number( value )
															)
														}
														__nextHasNoMarginBottom
													/>
												) }
											</Fragment>
										) }
										{ waitsForProposal( row ) && (
											<p className="ohmylms-ai-wait">
												{ __(
													'Accept the proposed skill first.',
													'ohmylms'
												) }
											</p>
										) }
										{ row.reason && (
											<p className="ohmylms-ai-reason">
												{ row.reason }
											</p>
										) }
									</td>
									<td>{ percent( row.confidence ) }%</td>
									<td>
										<Button
											variant="primary"
											size="small"
											disabled={
												busy || waitsForProposal( row )
											}
											onClick={ () =>
												review( [ row.id ], 'accept' )
											}
										>
											{ __( 'Accept', 'ohmylms' ) }
										</Button>{ ' ' }
										<Button
											variant="secondary"
											size="small"
											disabled={ busy }
											onClick={ () =>
												review( [ row.id ], 'reject' )
											}
										>
											{ __( 'Reject', 'ohmylms' ) }
										</Button>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</Fragment>
			) }
			{ run && ! isActive( run ) && ! list.items.length && (
				<p>{ __( 'Nothing is waiting for review.', 'ohmylms' ) }</p>
			) }
		</section>
	);
}
