/** @jsx createElement */
import {
	createElement,
	Fragment,
	useEffect,
	useMemo,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { AdminCard, AdminPage } from '../../extensions/AdminPage';
import { readComparison } from './comparison.mjs';
import {
	LEVELS,
	band,
	cellsOf,
	chainOf,
	chipClass,
	compareGroups,
	coverageOf,
	includedBy,
	levelOf,
	skillsById,
} from './skillMap.mjs';

const LEVEL_LABELS = {
	'not-started': __( 'Not started', 'ohmylms' ),
	attempted: __( 'Attempted', 'ohmylms' ),
	familiar: __( 'Familiar', 'ohmylms' ),
	proficient: __( 'Proficient', 'ohmylms' ),
	mastered: __( 'Mastered', 'ohmylms' ),
};

const RELATIONS = {
	equivalent: __( 'equivalent', 'ohmylms' ),
	broader: __( 'broader', 'ohmylms' ),
	narrower: __( 'narrower', 'ohmylms' ),
	combined: __( 'combined', 'ohmylms' ),
};

/**
 * Skill map: the coherence map of a subject (shared skills by domain and grade, with a syllabus or a
 * learner laid over it) and the comparison of two syllabuses. Opened at
 * `#/content-hub/curriculum/skill-map`.
 */
export function SkillMapPage() {
	const [ tab, setTab ] = useState( 'map' );
	const [ subjects, setSubjects ] = useState( null );
	const [ error, setError ] = useState( '' );
	useEffect( () => {
		let current = true;
		readComparison( 'skill-map/subjects' )
			.then( ( data ) => current && setSubjects( data ) )
			.catch( ( cause ) => current && setError( cause.message ) );
		return () => {
			current = false;
		};
	}, [] );
	return (
		<AdminPage
			className="ohmylms-skill-map-page"
			title={ __( 'Skill map', 'ohmylms' ) }
			description={ __(
				'See how the shared skills of a subject fit together across domains and grades, which of them a syllabus teaches, where a learner stands, and how two syllabuses compare.',
				'ohmylms'
			) }
			actions={
				<Button variant="secondary" href="#/content-hub/curriculum">
					{ __( 'Back to curriculum', 'ohmylms' ) }
				</Button>
			}
		>
			<div
				className="ohmylms-skill-map-tabs"
				role="tablist"
				aria-label={ __( 'Skill map views', 'ohmylms' ) }
			>
				{ [
					[ 'map', __( 'Coherence map', 'ohmylms' ) ],
					[ 'compare', __( 'Compare syllabuses', 'ohmylms' ) ],
				].map( ( [ id, label ] ) => (
					<Button
						key={ id }
						role="tab"
						aria-selected={ tab === id }
						variant={ tab === id ? 'primary' : 'secondary' }
						onClick={ () => setTab( id ) }
					>
						{ label }
					</Button>
				) ) }
			</div>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ ! subjects && ! error && <Spinner /> }
			{ subjects && tab === 'map' && (
				<CoherenceMap subjects={ subjects } />
			) }
			{ subjects && tab === 'compare' && (
				<SyllabusComparison subjects={ subjects } />
			) }
		</AdminPage>
	);
}

/**
 * The coherence map of one subject.
 *
 * @param {Object}   props          Component props.
 * @param {Object[]} props.subjects Subjects with their syllabuses.
 */
function CoherenceMap( { subjects } ) {
	const [ subject, setSubject ] = useState( subjects[ 0 ]?.subject || '' );
	const [ syllabusId, setSyllabusId ] = useState( '' );
	const [ learner, setLearner ] = useState( null );
	const [ map, setMap ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ selectedId, setSelectedId ] = useState( 0 );
	const entry = subjects.find( ( item ) => item.subject === subject );
	useEffect( () => {
		if ( ! subject ) {
			return undefined;
		}
		let current = true;
		setLoading( true );
		setError( '' );
		readComparison( 'skill-map', {
			subject,
			syllabus_id: syllabusId,
			student_id: learner?.id || '',
		} )
			.then( ( data ) => current && setMap( data ) )
			.catch( ( cause ) => current && setError( cause.message ) )
			.finally( () => current && setLoading( false ) );
		return () => {
			current = false;
		};
	}, [ subject, syllabusId, learner ] );
	const skills = useMemo( () => skillsById( map ), [ map ] );
	const cells = useMemo( () => cellsOf( map ), [ map ] );
	const selected = useMemo(
		() =>
			selectedId && skills.has( selectedId )
				? { id: selectedId, chain: chainOf( skills, selectedId ) }
				: null,
		[ skills, selectedId ]
	);
	if ( ! subjects.length ) {
		return (
			<Notice status="info" isDismissible={ false }>
				{ __(
					'No shared skills yet. Add shared skills to a subject, or analyse a syllabus with AI, to see its map.',
					'ohmylms'
				) }
			</Notice>
		);
	}
	return (
		<Fragment>
			<div className="ohmylms-skill-map-controls">
				<SelectControl
					label={ __( 'Subject', 'ohmylms' ) }
					value={ subject }
					options={ subjects.map( ( item ) => ( {
						value: item.subject,
						label: sprintf(
							/* translators: 1: subject, 2: number of shared skills. */
							__( '%1$s (%2$d skills)', 'ohmylms' ),
							item.subject,
							item.skills
						),
					} ) ) }
					onChange={ ( value ) => {
						setSubject( value );
						setSyllabusId( '' );
						setSelectedId( 0 );
					} }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Show what a syllabus teaches', 'ohmylms' ) }
					value={ String( syllabusId ) }
					options={ [
						{ value: '', label: __( 'No syllabus', 'ohmylms' ) },
						...( entry?.syllabuses || [] ).map( ( item ) => ( {
							value: String( item.id ),
							label: item.name,
						} ) ),
					] }
					onChange={ setSyllabusId }
					__nextHasNoMarginBottom
				/>
				<LearnerPicker learner={ learner } onChange={ setLearner } />
			</div>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ loading && <Spinner /> }
			{ map && (
				<Fragment>
					<MapSummary map={ map } />
					<MapLegend map={ map } />
					{ map.domains.length ? (
						<div
							className="ohmylms-skill-map-scroll"
							role="region"
							tabIndex={ 0 }
							aria-label={ __( 'Coherence map', 'ohmylms' ) }
						>
							<table className="ohmylms-skill-map-table">
								<thead>
									<tr>
										<th scope="col">
											{ __( 'Domain', 'ohmylms' ) }
										</th>
										{ map.grades.map( ( grade ) => (
											<th scope="col" key={ grade.value }>
												{ grade.label }
											</th>
										) ) }
									</tr>
								</thead>
								<tbody>
									{ map.domains.map( ( domain ) => (
										<tr key={ domain.id }>
											<th scope="row">
												{ domain.label }
												<AreaStrength
													map={ map }
													domain={ domain.id }
												/>
											</th>
											{ map.grades.map( ( grade ) => (
												<td key={ grade.value }>
													{ (
														cells[ domain.id ]?.[
															grade.value
														] || []
													).map( ( skill ) => (
														<button
															type="button"
															key={ skill.id }
															className={ chipClass(
																map,
																skill.id,
																selected
															) }
															aria-pressed={
																selectedId ===
																skill.id
															}
															title={ chipTitle(
																map,
																skill
															) }
															onClick={ () =>
																setSelectedId(
																	selectedId ===
																		skill.id
																		? 0
																		: skill.id
																)
															}
														>
															{ skill.name }
															{ skill.grade_to >
																skill.grade_from && (
																<span className="ohmylms-map-band">
																	{ sprintf(
																		/* translators: %d: last grade of the band. */
																		__(
																			'to %d',
																			'ohmylms'
																		),
																		skill.grade_to
																	) }
																</span>
															) }
														</button>
													) ) }
												</td>
											) ) }
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					) : (
						<p>
							{ __(
								'None of these shared skills has a domain and grade yet. Place them in domains and grades (in a skill’s settings, or with AI analysis) to draw the map.',
								'ohmylms'
							) }
						</p>
					) }
					{ selected && (
						<SkillDetails
							map={ map }
							skills={ skills }
							skill={ skills.get( selected.id ) }
							onSelect={ setSelectedId }
						/>
					) }
					<Issues
						map={ map }
						skills={ skills }
						onSelect={ setSelectedId }
					/>
				</Fragment>
			) }
		</Fragment>
	);
}

/**
 * Hover text for a chip: grades, level and coverage.
 *
 * @param {Object} map   Skill map.
 * @param {Object} skill Skill.
 * @return {string} Title.
 */
function chipTitle( map, skill ) {
	const parts = [ band( skill, map.grades ) ];
	if ( map.student ) {
		parts.push( LEVEL_LABELS[ levelOf( map, skill.id ) ] );
	}
	const coverage = coverageOf( map, skill.id );
	if ( coverage ) {
		parts.push(
			coverage.length
				? __( 'Taught in this syllabus', 'ohmylms' )
				: __( 'Not in this syllabus', 'ohmylms' )
		);
	}
	return parts.join( ' · ' );
}

/**
 * Counts above the map.
 *
 * @param {Object} props     Component props.
 * @param {Object} props.map Skill map.
 */
function MapSummary( { map } ) {
	const placed = map.skills.length - map.unplaced.length;
	const covered = map.syllabus
		? Object.keys( map.syllabus.covered || {} ).length
		: 0;
	return (
		<ul className="ohmylms-skill-map-stats">
			<li>
				<strong>{ placed }</strong>{ ' ' }
				{ _n( 'skill placed', 'skills placed', placed, 'ohmylms' ) }
			</li>
			<li>
				<strong>{ map.domains.length }</strong>{ ' ' }
				{ _n( 'domain', 'domains', map.domains.length, 'ohmylms' ) }
			</li>
			{ map.unplaced.length > 0 && (
				<li>
					<strong>{ map.unplaced.length }</strong>{ ' ' }
					{ __( 'without a domain or grade', 'ohmylms' ) }
				</li>
			) }
			<li>
				<strong>{ map.issues.length }</strong>{ ' ' }
				{ _n(
					'link to check',
					'links to check',
					map.issues.length,
					'ohmylms'
				) }
			</li>
			{ map.syllabus && (
				<li>
					{ sprintf(
						/* translators: 1: syllabus name, 2: skills taught, 3: objectives with no mapping. */
						__(
							'%1$s teaches %2$d of these skills; %3$d of its objectives are not mapped yet',
							'ohmylms'
						),
						map.syllabus.name,
						covered,
						map.syllabus.unmapped
					) }
				</li>
			) }
		</ul>
	);
}

/**
 * What the chip styles mean for the current overlays.
 *
 * @param {Object} props     Component props.
 * @param {Object} props.map Skill map.
 */
function MapLegend( { map } ) {
	return (
		<ul className="ohmylms-skill-map-legend">
			{ map.student &&
				LEVELS.map( ( level ) => (
					<li key={ level }>
						<span
							className={ `ohmylms-map-swatch ohmylms-map-${ level }` }
							aria-hidden="true"
						/>
						{ LEVEL_LABELS[ level ] }
					</li>
				) ) }
			{ map.syllabus && (
				<Fragment>
					<li>
						<span
							className="ohmylms-map-swatch is-covered"
							aria-hidden="true"
						/>
						{ __( 'Taught in the syllabus', 'ohmylms' ) }
					</li>
					<li>
						<span
							className="ohmylms-map-swatch is-uncovered"
							aria-hidden="true"
						/>
						{ __( 'Not in the syllabus', 'ohmylms' ) }
					</li>
				</Fragment>
			) }
			<li>
				<span
					className="ohmylms-map-swatch is-before"
					aria-hidden="true"
				/>
				{ __( 'What the selected skill builds on', 'ohmylms' ) }
			</li>
			<li>
				<span
					className="ohmylms-map-swatch is-after"
					aria-hidden="true"
				/>
				{ __( 'What builds on it', 'ohmylms' ) }
			</li>
			<li>
				{ __( 'Select a skill to light up its chain.', 'ohmylms' ) }
			</li>
		</ul>
	);
}

/**
 * A learner's strength in a domain, when a learner is shown.
 *
 * @param {Object} props        Component props.
 * @param {Object} props.map    Skill map.
 * @param {string} props.domain Domain.
 */
function AreaStrength( { map, domain } ) {
	const area = map.student?.areas.find( ( item ) => item.domain === domain );
	return area ? (
		<span className="ohmylms-map-strength">
			{ sprintf(
				/* translators: %d: strength from 0 to 100. */
				__( '%d/100', 'ohmylms' ),
				area.strength
			) }
		</span>
	) : null;
}

/**
 * The selected skill: its grades, links, coverage and the learner's level.
 *
 * @param {Object}               props          Component props.
 * @param {Object}               props.map      Skill map.
 * @param {Map}                  props.skills   Skills keyed by ID.
 * @param {Object}               props.skill    Selected skill.
 * @param {(id: number) => void} props.onSelect Select another skill.
 */
function SkillDetails( { map, skills, skill, onSelect } ) {
	const neededBy = [ ...skills.values() ].filter( ( other ) =>
		( other.prerequisites || [] ).includes( skill.id )
	);
	const outside = ( skill.prerequisites || [] ).filter(
		( id ) => ! skills.has( id )
	).length;
	const coverage = coverageOf( map, skill.id );
	const link = ( other ) => (
		<Button variant="link" onClick={ () => onSelect( other.id ) }>
			{ other.name }
		</Button>
	);
	const list = ( title, items, render ) =>
		items.length > 0 && (
			<Fragment>
				<h4>{ title }</h4>
				<ul>
					{ items.map( ( item, index ) => (
						<li key={ index }>{ render( item ) }</li>
					) ) }
				</ul>
			</Fragment>
		);
	return (
		<AdminCard title={ skill.name } className="ohmylms-skill-map-details">
			<p>
				{ [
					map.domains.find( ( item ) => item.id === skill.domain )
						?.label,
					band( skill, map.grades ),
					sprintf(
						/* translators: %d: number of syllabus objectives. */
						_n(
							'%d objective mapped to it',
							'%d objectives mapped to it',
							skill.mapped,
							'ohmylms'
						),
						skill.mapped
					),
				]
					.filter( Boolean )
					.join( ' · ' ) }
			</p>
			{ map.student && (
				<p>
					{ sprintf(
						/* translators: %s: level name. */
						__( 'Learner: %s', 'ohmylms' ),
						LEVEL_LABELS[ levelOf( map, skill.id ) ]
					) }
				</p>
			) }
			{ list(
				__( 'Needs first', 'ohmylms' ),
				( skill.prerequisites || [] )
					.map( ( id ) => skills.get( id ) )
					.filter( Boolean ),
				link
			) }
			{ outside > 0 && (
				<p>
					{ sprintf(
						/* translators: %d: number of prerequisites in other subjects. */
						_n(
							'%d prerequisite from another subject.',
							'%d prerequisites from other subjects.',
							outside,
							'ohmylms'
						),
						outside
					) }
				</p>
			) }
			{ list( __( 'Needed by', 'ohmylms' ), neededBy, link ) }
			{ list(
				__( 'Includes', 'ohmylms' ),
				( skill.includes || [] )
					.map( ( item ) => ( {
						skill: skills.get( item.id ),
						weight: item.weight,
					} ) )
					.filter( ( item ) => item.skill ),
				( item ) => (
					<Fragment>
						{ link( item.skill ) }{ ' ' }
						{ Math.round( item.weight * 100 ) }%
					</Fragment>
				)
			) }
			{ list(
				__( 'Included in', 'ohmylms' ),
				includedBy( skills, skill.id ),
				( item ) => (
					<Fragment>
						{ link( item.skill ) }{ ' ' }
						{ Math.round( item.weight * 100 ) }%
					</Fragment>
				)
			) }
			{ coverage &&
				( coverage.length ? (
					list(
						sprintf(
							/* translators: %s: syllabus name. */
							__( 'Taught in %s by', 'ohmylms' ),
							map.syllabus.name
						),
						coverage,
						( item ) =>
							[ item.code, item.name ]
								.filter( Boolean )
								.join( ' ' ) +
							( item.relation !== 'equivalent'
								? ` (${ RELATIONS[ item.relation ] || item.relation })`
								: '' )
					)
				) : (
					<p>
						{ sprintf(
							/* translators: %s: syllabus name. */
							__( '%s does not teach this skill.', 'ohmylms' ),
							map.syllabus.name
						) }
					</p>
				) ) }
			<Button variant="secondary" onClick={ () => onSelect( 0 ) }>
				{ __( 'Clear selection', 'ohmylms' ) }
			</Button>
		</AdminCard>
	);
}

/**
 * Coherence checks and skills without a place.
 *
 * @param {Object}               props          Component props.
 * @param {Object}               props.map      Skill map.
 * @param {Map}                  props.skills   Skills keyed by ID.
 * @param {(id: number) => void} props.onSelect Select a skill.
 */
function Issues( { map, skills, onSelect } ) {
	const name = ( id ) => skills.get( id )?.name || '';
	const text = ( issue ) => {
		if ( issue.type === 'later_prerequisite' ) {
			return sprintf(
				/* translators: 1: skill, 2: prerequisite. */
				__(
					'%1$s needs %2$s first, but %2$s is taught in a later grade.',
					'ohmylms'
				),
				name( issue.skill ),
				name( issue.other )
			);
		}
		if ( issue.type === 'long_gap' ) {
			return sprintf(
				/* translators: 1: skill, 2: prerequisite. */
				__(
					'%1$s builds on %2$s from four or more grades earlier. A review or a bridging skill may help.',
					'ohmylms'
				),
				name( issue.skill ),
				name( issue.other )
			);
		}
		return sprintf(
			/* translators: %s: skill. */
			__(
				'%s needs nothing first, though it comes after the first grade of its domain. A prerequisite may be missing.',
				'ohmylms'
			),
			name( issue.skill )
		);
	};
	return (
		<AdminCard
			title={ __( 'Links to check', 'ohmylms' ) }
			className="ohmylms-skill-map-issues"
		>
			{ map.issues.length === 0 ? (
				<p>{ __( 'No problems found in the links.', 'ohmylms' ) }</p>
			) : (
				<ul>
					{ map.issues.slice( 0, 100 ).map( ( issue ) => (
						<li
							key={ `${ issue.type }-${ issue.skill }-${ issue.other }` }
						>
							<Button
								variant="link"
								onClick={ () => onSelect( issue.skill ) }
							>
								{ text( issue ) }
							</Button>
						</li>
					) ) }
				</ul>
			) }
			{ map.unplaced.length > 0 && (
				<Fragment>
					<h4>{ __( 'Not on the map yet', 'ohmylms' ) }</h4>
					<p>
						{ map.unplaced
							.map( ( id ) => name( id ) )
							.filter( Boolean )
							.join( ', ' ) }
					</p>
				</Fragment>
			) }
		</AdminCard>
	);
}

/**
 * Find a learner to lay over the map.
 *
 * @param {Object}                     props          Component props.
 * @param {Object}                     props.learner  Chosen learner, or null.
 * @param {(learner: ?Object) => void} props.onChange Choose a learner, or null to clear.
 */
function LearnerPicker( { learner, onChange } ) {
	const [ search, setSearch ] = useState( '' );
	const [ found, setFound ] = useState( [] );
	useEffect( () => {
		if ( search.trim().length < 2 ) {
			setFound( [] );
			return undefined;
		}
		let current = true;
		const timer = window.setTimeout( () => {
			window.wp
				.apiFetch( {
					path: `/wp/v2/users?${ new URLSearchParams( {
						search: search.trim(),
						per_page: '8',
						context: 'edit',
					} ) }`,
				} )
				.then( ( users ) => current && setFound( users ) )
				.catch( () => current && setFound( [] ) );
		}, 300 );
		return () => {
			current = false;
			window.clearTimeout( timer );
		};
	}, [ search ] );
	if ( learner ) {
		return (
			<div className="ohmylms-skill-map-learner">
				<span>
					{ sprintf(
						/* translators: %s: learner name. */
						__( 'Showing %s', 'ohmylms' ),
						learner.name
					) }
				</span>
				<Button variant="link" onClick={ () => onChange( null ) }>
					{ __( 'Hide learner', 'ohmylms' ) }
				</Button>
			</div>
		);
	}
	return (
		<div className="ohmylms-skill-map-learner">
			<TextControl
				label={ __( 'Show a learner', 'ohmylms' ) }
				value={ search }
				onChange={ setSearch }
				placeholder={ __( 'Name or email', 'ohmylms' ) }
				__nextHasNoMarginBottom
			/>
			{ found.length > 0 && (
				<ul className="ohmylms-skill-map-learner-results">
					{ found.map( ( user ) => (
						<li key={ user.id }>
							<Button
								variant="link"
								onClick={ () => {
									onChange( {
										id: user.id,
										name: user.name,
									} );
									setSearch( '' );
								} }
							>
								{ user.name }
								{ user.email ? ` (${ user.email })` : '' }
							</Button>
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}

/**
 * Compare two syllabuses through the shared skills their objectives map onto.
 *
 * @param {Object}   props          Component props.
 * @param {Object[]} props.subjects Subjects with their syllabuses.
 */
function SyllabusComparison( { subjects } ) {
	const [ syllabuses, setSyllabuses ] = useState( null );
	const [ a, setA ] = useState( '' );
	const [ b, setB ] = useState( '' );
	const [ result, setResult ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( '' );
	useEffect( () => {
		let current = true;
		readComparison( 'syllabuses' )
			.then( ( data ) => {
				if ( ! current ) {
					return;
				}
				const list = Array.isArray( data ) ? data : data.items || [];
				setSyllabuses( list );
				const configured = subjects.flatMap( ( item ) =>
					item.syllabuses.map( ( syllabus ) => String( syllabus.id ) )
				);
				setA( configured[ 0 ] || String( list[ 0 ]?.id || '' ) );
				setB( configured[ 1 ] || String( list[ 1 ]?.id || '' ) );
			} )
			.catch( ( cause ) => current && setError( cause.message ) );
		return () => {
			current = false;
		};
	}, [ subjects ] );
	useEffect( () => {
		if ( ! a || ! b || a === b ) {
			setResult( null );
			return undefined;
		}
		let current = true;
		setLoading( true );
		setError( '' );
		readComparison( 'compare', { a, b } )
			.then( ( data ) => current && setResult( data ) )
			.catch( ( cause ) => current && setError( cause.message ) )
			.finally( () => current && setLoading( false ) );
		return () => {
			current = false;
		};
	}, [ a, b ] );
	const groups = useMemo( () => compareGroups( result ), [ result ] );
	if ( ! syllabuses ) {
		return error ? (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		) : (
			<Spinner />
		);
	}
	const options = [
		{ value: '', label: __( 'Choose a syllabus', 'ohmylms' ) },
		...syllabuses.map( ( item ) => ( {
			value: String( item.id ),
			label: item.name,
		} ) ),
	];
	const nameA = result?.a.name || __( 'Syllabus A', 'ohmylms' );
	const nameB = result?.b.name || __( 'Syllabus B', 'ohmylms' );
	return (
		<Fragment>
			<div className="ohmylms-skill-map-controls">
				<SelectControl
					label={ __( 'Syllabus A', 'ohmylms' ) }
					value={ a }
					options={ options }
					onChange={ setA }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Syllabus B', 'ohmylms' ) }
					value={ b }
					options={ options }
					onChange={ setB }
					__nextHasNoMarginBottom
				/>
			</div>
			{ a && a === b && (
				<Notice status="warning" isDismissible={ false }>
					{ __( 'Choose two different syllabuses.', 'ohmylms' ) }
				</Notice>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ loading && <Spinner /> }
			{ result && (
				<Fragment>
					<ul className="ohmylms-skill-map-stats">
						<li>
							<strong>{ result.overlap }%</strong>{ ' ' }
							{ __(
								'of the skills are taught by both',
								'ohmylms'
							) }
						</li>
						<li>
							<strong>{ result.both.length }</strong>{ ' ' }
							{ __( 'in both', 'ohmylms' ) }
						</li>
						<li>
							<strong>{ result.only_a.length }</strong>{ ' ' }
							{ sprintf(
								/* translators: %s: syllabus name. */
								__( 'only in %s', 'ohmylms' ),
								nameA
							) }
						</li>
						<li>
							<strong>{ result.only_b.length }</strong>{ ' ' }
							{ sprintf(
								/* translators: %s: syllabus name. */
								__( 'only in %s', 'ohmylms' ),
								nameB
							) }
						</li>
					</ul>
					{ ( result.unmapped_a.length > 0 ||
						result.unmapped_b.length > 0 ) && (
						<Notice status="info" isDismissible={ false }>
							{ [
								[ result.unmapped_a.length, nameA ],
								[ result.unmapped_b.length, nameB ],
							]
								.filter( ( [ count ] ) => count > 0 )
								.map( ( [ count, name ] ) =>
									sprintf(
										/* translators: 1: number of objectives, 2: syllabus name. */
										_n(
											'%1$d objective of %2$s is not mapped to a shared skill yet.',
											'%1$d objectives of %2$s are not mapped to a shared skill yet.',
											count,
											'ohmylms'
										),
										count,
										name
									)
								)
								.join( ' ' ) }{ ' ' }
							{ __(
								'They are left out. Map them, or analyse the syllabus with AI, for a full comparison.',
								'ohmylms'
							) }
						</Notice>
					) }
					{ result.domains.length > 0 && (
						<AdminCard title={ __( 'By domain', 'ohmylms' ) }>
							<table className="widefat striped ohmylms-compare-domains">
								<thead>
									<tr>
										<th scope="col">
											{ __( 'Domain', 'ohmylms' ) }
										</th>
										<th scope="col">
											{ __( 'Both', 'ohmylms' ) }
										</th>
										<th scope="col">{ nameA }</th>
										<th scope="col">{ nameB }</th>
									</tr>
								</thead>
								<tbody>
									{ result.domains.map( ( domain ) => (
										<tr key={ domain.domain }>
											<th scope="row">
												{ domain.label }
											</th>
											<td>{ domain.both }</td>
											<td>{ domain.only_a }</td>
											<td>{ domain.only_b }</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</AdminCard>
					) }
					<CompareGroup
						title={ __( 'Taught in both', 'ohmylms' ) }
						rows={ groups.both }
						nameA={ nameA }
						nameB={ nameB }
					/>
					<CompareGroup
						title={ sprintf(
							/* translators: %s: syllabus name. */
							__( 'Only in %s', 'ohmylms' ),
							nameA
						) }
						rows={ groups.onlyA }
						nameA={ nameA }
						nameB={ nameB }
					/>
					<CompareGroup
						title={ sprintf(
							/* translators: %s: syllabus name. */
							__( 'Only in %s', 'ohmylms' ),
							nameB
						) }
						rows={ groups.onlyB }
						nameA={ nameA }
						nameB={ nameB }
					/>
				</Fragment>
			) }
		</Fragment>
	);
}

/**
 * One group of compared skills, with the objectives on each side.
 *
 * @param {Object}   props       Component props.
 * @param {string}   props.title Heading.
 * @param {Object[]} props.rows  Rows.
 * @param {string}   props.nameA Syllabus A.
 * @param {string}   props.nameB Syllabus B.
 */
function CompareGroup( { title, rows, nameA, nameB } ) {
	if ( ! rows.length ) {
		return null;
	}
	const objectives = ( items ) =>
		items.length ? (
			<ul>
				{ items.map( ( item ) => (
					<li key={ item.id }>
						{ [ item.code, item.name ]
							.filter( Boolean )
							.join( ' ' ) }
						{ item.relation !== 'equivalent' && (
							<em>
								{ ' ' }
								({ RELATIONS[ item.relation ] || item.relation }
								)
							</em>
						) }
					</li>
				) ) }
			</ul>
		) : (
			'—'
		);
	return (
		<AdminCard title={ `${ title } (${ rows.length })` }>
			<div className="ohmylms-compare-scroll">
				<table className="widefat striped ohmylms-compare-table">
					<thead>
						<tr>
							<th scope="col">
								{ __( 'Shared skill', 'ohmylms' ) }
							</th>
							<th scope="col">{ nameA }</th>
							<th scope="col">{ nameB }</th>
						</tr>
					</thead>
					<tbody>
						{ rows.map( ( row ) => (
							<tr key={ row.skill.id }>
								<th scope="row">
									{ row.skill.name }
									{ row.skill.grade_from > 0 && (
										<span className="ohmylms-map-band">
											{ sprintf(
												/* translators: %s: level band such as 7 or 7–9. */
												__( 'level %s', 'ohmylms' ),
												band( row.skill )
											) }
										</span>
									) }
								</th>
								<td>{ objectives( row.a ) }</td>
								<td>{ objectives( row.b ) }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>
		</AdminCard>
	);
}
