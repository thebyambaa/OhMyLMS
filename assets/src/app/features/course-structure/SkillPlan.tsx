import {
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Badge } from '../../../ui/Badge';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import {
	getSkillPlan,
	linkLessonSkills,
	setUnitTopics,
	type PlanTopic,
	type SkillPlan as Plan,
	type SkillState,
} from './skill-plan-api';
import './SkillPlan.css';

/** What the author picked on the outline: a unit, or a lesson placed in a unit. */
export type PlanSelection =
	| { kind: 'unit'; id: number | string; name: string }
	| {
			kind: 'lesson';
			/** Lesson post ID. */
			id: number;
			unitId: number | string;
			name: string;
	  };

type Scope = 'unit' | 'all';

/**
 * The lessons that teach each skill, turned around: lesson ID to its number of skills.
 *
 * @param skills How the course teaches each skill it touches.
 * @return Skills per lesson ID.
 */
function countsByLesson( skills: Record< number, SkillState > ) {
	const counts = new Map< number, number >();
	for ( const state of Object.values( skills ) ) {
		for ( const lesson of state.teaching ) {
			counts.set( lesson.id, ( counts.get( lesson.id ) ?? 0 ) + 1 );
		}
	}
	return counts;
}

function skillCount( topic: PlanTopic ) {
	return topic.groups.reduce(
		( total, group ) => total + group.skills.length,
		0
	);
}

/**
 * The skills beside the outline. It follows the outline: with nothing selected it shows the chosen curriculum and how
 * the course teaches each skill; with a unit selected it chooses the topics that unit covers; with a lesson selected
 * it lists the skills of the unit's topics (or all topics) with a checkbox to connect each one to the lesson.
 *
 * @param props           Component props.
 * @param props.course    Course ID.
 * @param props.selection What is selected on the outline, if anything.
 * @param props.onSelect  Selects a lesson that teaches a skill (chosen from a skill row); gets its ID.
 * @param props.unsaved   Whether the outline has changes that are not saved; connections need a saved outline.
 * @param props.refresh   Changes when the saved outline changes, so the teaching state is read again.
 * @param props.onCounts  Told how many skills each lesson is connected to.
 * @param props.onTopics  Told the topics chosen for each saved unit.
 * @param props.unitOf    Unit ID a lesson (by ID) is placed in, when it is saved.
 */
export function SkillPlan( {
	course,
	selection,
	onSelect,
	unsaved,
	refresh,
	onCounts,
	onTopics,
	unitOf,
}: {
	course: number;
	selection: PlanSelection | null;
	onSelect: ( lesson: number ) => void;
	unsaved: boolean;
	refresh: number;
	onCounts?: ( counts: ReadonlyMap< number, number > ) => void;
	onTopics?: ( topics: Record< number, number[] > ) => void;
	unitOf: ( lesson: number ) => number | null;
} ) {
	const [ curriculum, setCurriculum ] = useState( 0 );
	const load = useCallback(
		( signal: AbortSignal ) => getSkillPlan( course, curriculum, signal ),
		[ course, curriculum ]
	);
	const request = useRequest( load, {
		key: `${ course }:${ curriculum }:${ refresh }`,
		keepData: true,
	} );
	const [ skills, setSkills ] = useState< Record< number, SkillState > >(
		{}
	);
	const [ unitTopics, setTopics ] = useState< Record< number, number[] > >(
		{}
	);
	const [ scope, setScope ] = useState< Scope >( 'unit' );
	const [ connectedOnly, setConnectedOnly ] = useState( false );
	const [ search, setSearch ] = useState( '' );
	const operation = useOperation();
	const plan: Plan | null = request.data;
	useEffect( () => {
		if ( plan ) {
			setSkills( plan.skills );
			setTopics( plan.unitTopics );
		}
	}, [ plan ] );
	useEffect( () => {
		onTopics?.( unitTopics );
	}, [ unitTopics, onTopics ] );
	useEffect( () => {
		onCounts?.( countsByLesson( skills ) );
	}, [ skills, onCounts ] );
	useEffect( () => {
		setScope( 'unit' );
		setConnectedOnly( false );
	}, [ selection?.kind, selection?.id ] );

	const lesson = 'lesson' === selection?.kind ? selection : null;
	const unit = 'unit' === selection?.kind ? selection : null;
	const lessonUnit = lesson ? unitOf( lesson.id ) : null;
	const scopedTopics =
		lessonUnit && unitTopics[ lessonUnit ]?.length
			? unitTopics[ lessonUnit ]!
			: null;
	const connected = useMemo( () => {
		const ids = new Set< number >();
		if ( lesson ) {
			for ( const [ id, state ] of Object.entries( skills ) ) {
				if ( state.teaching.some( ( row ) => row.id === lesson.id ) ) {
					ids.add( Number( id ) );
				}
			}
		}
		return ids;
	}, [ lesson, skills ] );
	const needle = search.trim().toLowerCase();
	const matches = ( id: number ) => {
		const name = plan?.names[ id ];
		return (
			! needle ||
			Boolean(
				name &&
				( name.name.toLowerCase().includes( needle ) ||
					name.code.toLowerCase().includes( needle ) )
			)
		);
	};

	if ( ! plan ) {
		return (
			<aside
				className="oml-skillplan"
				aria-label={ __( 'Skills', 'ohmylms' ) }
			>
				{ request.error ? (
					<Notice status="error" isDismissible={ false }>
						{ request.error }
						<Button variant="link" onClick={ request.retry }>
							{ __( 'Retry', 'ohmylms' ) }
						</Button>
					</Notice>
				) : (
					<p role="status">{ __( 'Loading skills…', 'ohmylms' ) }</p>
				) }
			</aside>
		);
	}

	const topics = plan.topics.filter( ( topic ) => skillCount( topic ) > 0 );
	const locked = unsaved || operation.busy;
	const total = Object.keys( plan.names ).length;
	const taught = Object.keys( plan.names ).filter(
		( id ) => ( skills[ Number( id ) ]?.teaching.length ?? 0 ) > 0
	).length;

	function connect( skill: number, on: boolean ) {
		if ( ! lesson ) {
			return;
		}
		void operation.run(
			( signal ) =>
				linkLessonSkills(
					course,
					lesson.id,
					on ? { add: [ skill ] } : { remove: [ skill ] },
					signal
				),
			( result ) => setSkills( result.skills )
		);
	}
	function toggleTopic( topic: number, on: boolean ) {
		if ( ! unit || 'number' !== typeof unit.id ) {
			return;
		}
		const current = unitTopics[ unit.id ] ?? [];
		const next = on
			? [ ...current, topic ]
			: current.filter( ( id ) => id !== topic );
		const unitId = unit.id;
		void operation.run(
			( signal ) => setUnitTopics( course, unitId, next, signal ),
			( result ) => setTopics( result )
		);
	}

	function skillRow( id: number ) {
		const name = plan?.names[ id ];
		if ( ! name ) {
			return null;
		}
		const state = skills[ id ];
		const others = ( state?.teaching ?? [] ).filter(
			( row ) => row.id !== lesson?.id
		);
		const label = name.code ? `${ name.name } (${ name.code })` : name.name;
		return (
			<li key={ id } className="oml-skillplan-skill">
				{ lesson ? (
					<CheckboxControl
						__nextHasNoMarginBottom
						label={ label }
						checked={ connected.has( id ) }
						disabled={ locked }
						onChange={ ( on ) => connect( id, on ) }
					/>
				) : (
					<span className="oml-skillplan-skill-name">{ label }</span>
				) }
				<span className="oml-skillplan-skill-meta">
					{ others.map( ( row ) => (
						<Button
							key={ row.id }
							size="small"
							variant="tertiary"
							className="oml-skillplan-lesson"
							onClick={ () => onSelect( row.id ) }
							label={ sprintf(
								/* translators: %s: lesson title. */
								__( 'Select lesson %s', 'ohmylms' ),
								row.title
							) }
						>
							{ row.title }
						</Button>
					) ) }
					{ ! state?.teaching.length && ! lesson && (
						<Badge tone="warning">
							{ __( 'Not taught', 'ohmylms' ) }
						</Badge>
					) }
					{ state?.teaching.length && ! state.questionsOk ? (
						<Badge tone="warning">
							{ sprintf(
								/* translators: 1: approved questions, 2: needed. */
								__( '%1$d of %2$d questions', 'ohmylms' ),
								state.questions,
								state.questionsNeed
							) }
						</Badge>
					) : null }
				</span>
			</li>
		);
	}

	function topicBlock( topic: PlanTopic ) {
		const groups = topic.groups
			.map( ( group ) => ( {
				...group,
				skills: group.skills.filter(
					( id ) =>
						matches( id ) &&
						( ! connectedOnly || connected.has( id ) )
				),
			} ) )
			.filter( ( group ) => group.skills.length > 0 );
		if ( ! groups.length ) {
			return null;
		}
		const shown = groups.reduce(
			( sum, group ) => sum + group.skills.length,
			0
		);
		return (
			<details
				key={ `${ topic.id }:${ scope }:${ selection?.kind ?? '' }` }
				className="oml-skillplan-topic"
				open={
					Boolean( needle ) ||
					( Boolean( lesson ) && Boolean( scopedTopics ) ) ||
					topics.length < 4
				}
			>
				<summary>
					<strong>{ topic.name }</strong>{ ' ' }
					<span className="oml-skillplan-count">{ shown }</span>
				</summary>
				{ groups.map( ( group ) => (
					<div key={ group.id } className="oml-skillplan-group">
						{ groups.length > 1 || group.name ? (
							<h4>{ group.name }</h4>
						) : null }
						<ul>{ group.skills.map( skillRow ) }</ul>
					</div>
				) ) }
			</details>
		);
	}

	const shownTopics =
		lesson && scopedTopics && 'unit' === scope
			? topics.filter( ( topic ) => scopedTopics.includes( topic.id ) )
			: topics;
	const otherConnected = lesson
		? [ ...connected ].filter( ( id ) => ! plan.names[ id ] )
		: [];

	return (
		<aside
			className="oml-skillplan"
			aria-label={ __( 'Skills', 'ohmylms' ) }
		>
			<SelectControl
				__nextHasNoMarginBottom
				label={ __( 'Curriculum', 'ohmylms' ) }
				value={ String( plan.curriculum ) }
				disabled={ operation.busy || 0 === plan.curricula.length }
				options={
					plan.curricula.length
						? plan.curricula.map( ( row ) => ( {
								value: String( row.id ),
								label: row.name,
							} ) )
						: [
								{
									value: '0',
									label: __( 'No curriculum yet', 'ohmylms' ),
								},
							]
				}
				onChange={ ( value ) => setCurriculum( Number( value ) ) }
			/>
			<p className="oml-skillplan-summary" role="status">
				{ sprintf(
					/* translators: 1: skills a lesson of this course teaches, 2: skills in the curriculum. */
					__(
						'%1$d of %2$d skills are taught in this course.',
						'ohmylms'
					),
					taught,
					total
				) }
			</p>
			<TextControl
				__nextHasNoMarginBottom
				label={ __( 'Search skills', 'ohmylms' ) }
				type="search"
				value={ search }
				onChange={ setSearch }
			/>
			{ unsaved && selection && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Save the outline first, then connect skills: they attach to saved lessons and units.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			{ ! selection && (
				<p className="oml-skillplan-hint">
					{ __(
						'Select a unit to choose the topics it covers, or a lesson to connect it to skills.',
						'ohmylms'
					) }
				</p>
			) }
			{ unit && (
				<section className="oml-skillplan-context">
					<h3>
						{ sprintf(
							/* translators: %s: unit title. */
							__( 'Topics for %s', 'ohmylms' ),
							unit.name
						) }
					</h3>
					{ 'number' !== typeof unit.id ? (
						<p>
							{ __(
								'Save the outline to choose topics for this new unit.',
								'ohmylms'
							) }
						</p>
					) : (
						<p>
							{ __(
								'Choose the topics this unit covers. When you select one of its lessons, only the skills of these topics are listed.',
								'ohmylms'
							) }
						</p>
					) }
					<ul className="oml-skillplan-topics">
						{ topics.map( ( topic ) => (
							<li key={ topic.id }>
								<CheckboxControl
									__nextHasNoMarginBottom
									label={ sprintf(
										/* translators: 1: topic name, 2: number of skills. */
										_n(
											'%1$s (%2$d skill)',
											'%1$s (%2$d skills)',
											skillCount( topic ),
											'ohmylms'
										),
										topic.name,
										skillCount( topic )
									) }
									checked={
										'number' === typeof unit.id &&
										Boolean(
											unitTopics[ unit.id ]?.includes(
												topic.id
											)
										)
									}
									disabled={
										locked || 'number' !== typeof unit.id
									}
									onChange={ ( on ) =>
										toggleTopic( topic.id, on )
									}
								/>
							</li>
						) ) }
					</ul>
				</section>
			) }
			{ lesson && (
				<section className="oml-skillplan-context">
					<h3>
						{ sprintf(
							/* translators: %s: lesson title. */
							__( 'Skills for %s', 'ohmylms' ),
							lesson.name
						) }
					</h3>
					<p>
						{ sprintf(
							/* translators: %d: number of skills. */
							_n(
								'Connected to %d skill.',
								'Connected to %d skills.',
								connected.size,
								'ohmylms'
							),
							connected.size
						) }
					</p>
					<div className="oml-skillplan-filters">
						{ scopedTopics && (
							<>
								<Button
									size="small"
									variant="secondary"
									isPressed={ 'unit' === scope }
									onClick={ () => setScope( 'unit' ) }
								>
									{ __( 'Unit topics', 'ohmylms' ) }
								</Button>
								<Button
									size="small"
									variant="secondary"
									isPressed={ 'all' === scope }
									onClick={ () => setScope( 'all' ) }
								>
									{ __( 'All topics', 'ohmylms' ) }
								</Button>
							</>
						) }
						<CheckboxControl
							__nextHasNoMarginBottom
							label={ __( 'Only connected', 'ohmylms' ) }
							checked={ connectedOnly }
							onChange={ setConnectedOnly }
						/>
					</div>
					{ ! scopedTopics && (
						<p className="oml-skillplan-hint">
							{ __(
								'Select the unit and choose its topics to narrow this list.',
								'ohmylms'
							) }
						</p>
					) }
				</section>
			) }
			{ ! unit && (
				<div className="oml-skillplan-topiclist">
					{ shownTopics.map( topicBlock ) }
					{ 0 === topics.length && (
						<p className="oml-skillplan-hint">
							{ __(
								'This curriculum has no skills yet.',
								'ohmylms'
							) }
						</p>
					) }
				</div>
			) }
			{ lesson && otherConnected.length > 0 && (
				<p className="oml-skillplan-hint">
					{ sprintf(
						/* translators: %d: number of skills. */
						_n(
							'Also connected to %d skill outside this curriculum.',
							'Also connected to %d skills outside this curriculum.',
							otherConnected.length,
							'ohmylms'
						),
						otherConnected.length
					) }
				</p>
			) }
		</aside>
	);
}
