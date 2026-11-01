import {
	Button,
	Notice,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { useRequest } from '../../hooks/useRequest';
import {
	levelRange,
	readTargets,
	type OverlayLevel,
	type OverlaySkill,
	type SkillOverlay,
} from './skill-map-overlay';
import './skill-map.css';

const SEARCH_DELAY = 300;

/**
 * Name of a level as the server's legend gives it.
 * @param overlay Overlay.
 * @param level   Level.
 */
export function levelName( overlay: SkillOverlay, level: OverlayLevel ) {
	return (
		overlay.levels.find( ( entry ) => entry.id === level )?.label ?? level
	);
}

/**
 * Short text shown on a skill: its level and percentage (the group's average for a class or school).
 * @param overlay Overlay.
 * @param skill   The skill's figures.
 */
export function skillBadge( overlay: SkillOverlay, skill: OverlaySkill ) {
	const name = levelName( overlay, skill.level );
	if ( 'not-started' === skill.level ) {
		return name;
	}
	if ( 'learner' === overlay.target.type ) {
		/* translators: 1: level name, 2: percentage. */
		return sprintf( __( '%1$s · %2$d%%', 'ohmylms' ), name, skill.percent );
	}
	return sprintf(
		/* translators: 1: level name, 2: average percentage of the learners. */
		__( '%1$s · average %2$d%%', 'ohmylms' ),
		name,
		skill.percent
	);
}

/**
 * The selected skill's figures in a sentence.
 * @param overlay Overlay.
 * @param skill   The skill's figures.
 */
export function skillSentence( overlay: SkillOverlay, skill: OverlaySkill ) {
	if ( 'learner' === overlay.target.type ) {
		return sprintf(
			/* translators: 1: learner name, 2: level and percentage. */
			__( '%1$s: %2$s.', 'ohmylms' ),
			overlay.target.label,
			skillBadge( overlay, skill )
		);
	}
	const parts = overlay.levels.map( ( entry ) =>
		sprintf(
			/* translators: 1: level name, 2: number of learners. */
			__( '%1$s %2$d', 'ohmylms' ),
			entry.label,
			skill.distribution[ entry.id ]
		)
	);
	return sprintf(
		/* translators: 1: class or school, 2: level and average, 3: learners per level, 4: number of learners. */
		__( '%1$s: %2$s. Learners: %3$s (of %4$d).', 'ohmylms' ),
		overlay.target.label,
		skillBadge( overlay, skill ),
		parts.join( ', ' ),
		overlay.target.counted
	);
}

/**
 * Search the classes, schools and learners the user may lay over the map.
 * @param props          Component props.
 * @param props.onChoose Choose a target key.
 * @param props.onCancel Keep the current target, when there is one.
 */
function TargetPicker( {
	onChoose,
	onCancel,
}: {
	onChoose: ( target: string ) => void;
	onCancel?: () => void;
} ) {
	const [ text, setText ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	useEffect( () => {
		const timer = window.setTimeout(
			() => setSearch( text.trim() ),
			SEARCH_DELAY
		);
		return () => window.clearTimeout( timer );
	}, [ text ] );
	const targets = useRequest( ( signal ) => readTargets( search, signal ), {
		key: search,
		keepData: true,
	} );
	const data = targets.data;
	return (
		<div className="ohmylms-skill-overlay__picker">
			<TextControl
				type="search"
				label={ __( 'Find a learner, class or school', 'ohmylms' ) }
				value={ text }
				onChange={ setText }
				placeholder={ __( 'Name', 'ohmylms' ) }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
			{ onCancel && (
				<Button variant="link" onClick={ onCancel }>
					{ __( 'Keep the current selection', 'ohmylms' ) }
				</Button>
			) }
			<div aria-live="polite">
				{ targets.loading && (
					<p role="status">{ __( 'Searching…', 'ohmylms' ) }</p>
				) }
			</div>
			{ targets.error && (
				<Notice status="error" isDismissible={ false }>
					{ targets.error }{ ' ' }
					<Button variant="secondary" onClick={ targets.retry }>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ data && (
				<div className="ohmylms-skill-overlay__results">
					{ data.groups.length > 0 && (
						<section
							aria-label={ __(
								'Classes and schools',
								'ohmylms'
							) }
						>
							<h4>{ __( 'Classes and schools', 'ohmylms' ) }</h4>
							<ul>
								{ data.groups.map( ( group ) => (
									<li key={ group.key }>
										<Button
											variant="link"
											onClick={ () =>
												onChoose( group.key )
											}
										>
											{ group.school
												? sprintf(
														/* translators: 1: class, 2: school. */
														__(
															'%1$s (%2$s)',
															'ohmylms'
														),
														group.label,
														group.school
													)
												: group.label }
										</Button>
									</li>
								) ) }
							</ul>
							{ data.groupsTotal > data.groups.length && (
								<p className="ohmylms-skill-overlay__note">
									{ sprintf(
										/* translators: 1: groups shown, 2: groups found. */
										__(
											'Showing %1$d of %2$d; type to narrow the list.',
											'ohmylms'
										),
										data.groups.length,
										data.groupsTotal
									) }
								</p>
							) }
						</section>
					) }
					<section aria-label={ __( 'Learners', 'ohmylms' ) }>
						<h4>{ __( 'Learners', 'ohmylms' ) }</h4>
						{ search.length < data.minSearch && (
							<p className="ohmylms-skill-overlay__note">
								{ sprintf(
									/* translators: %d: number of characters. */
									__(
										'Type at least %d characters to find a learner.',
										'ohmylms'
									),
									data.minSearch
								) }
							</p>
						) }
						{ search.length >= data.minSearch &&
							! data.learners.length && (
								<p className="ohmylms-skill-overlay__note">
									{ __(
										'No learner you can see matches.',
										'ohmylms'
									) }
								</p>
							) }
						{ data.learners.length > 0 && (
							<ul>
								{ data.learners.map( ( learner ) => (
									<li key={ learner.id }>
										<Button
											variant="link"
											onClick={ () =>
												onChoose(
													`learner:${ learner.id }`
												)
											}
										>
											{ learner.name }
										</Button>
									</li>
								) ) }
							</ul>
						) }
					</section>
				</div>
			) }
		</div>
	);
}

/**
 * Legend with how many of the map's skills are at each level, and links to the learner's records.
 * @param props         Component props.
 * @param props.overlay Overlay.
 */
function OverlaySummary( { overlay }: { overlay: SkillOverlay } ) {
	const { target, links } = overlay;
	return (
		<>
			<ul
				className="ohmylms-skill-overlay__legend"
				aria-label={ __( 'Mastery levels', 'ohmylms' ) }
			>
				{ overlay.levels.map( ( entry, index ) => (
					<li
						key={ entry.id }
						className="ohmylms-skill-overlay__legend-item"
					>
						<span
							className={ `ohmylms-skill-overlay__swatch is-${ entry.id }` }
							aria-hidden="true"
						/>
						<span>
							{ entry.label }{ ' ' }
							<span className="ohmylms-skill-overlay__note">
								{ levelRange( overlay.levels, index ) }
							</span>
						</span>
						<strong>
							{ sprintf(
								/* translators: %d: number of skills. */
								_n(
									'%d skill',
									'%d skills',
									overlay.counts[ entry.id ],
									'ohmylms'
								),
								overlay.counts[ entry.id ]
							) }
						</strong>
					</li>
				) ) }
			</ul>
			{ 'learner' !== target.type && (
				<p className="ohmylms-skill-overlay__note">
					{ sprintf(
						/* translators: %d: number of learners. */
						_n(
							'Each skill shows the average of %d learner; learners without evidence count as 0%%.',
							'Each skill shows the average of %d learners; learners without evidence count as 0%%.',
							target.counted,
							'ohmylms'
						),
						target.counted
					) }
					{ target.counted < target.learners &&
						` ${ sprintf(
							/* translators: 1: learners counted, 2: learners in the group. */
							__(
								'Only the first %1$d of %2$d learners are counted.',
								'ohmylms'
							),
							target.counted,
							target.learners
						) }` }
				</p>
			) }
			{ ( links.transcript || links.report || links.gradebook ) && (
				<p className="ohmylms-skill-overlay__links">
					{ links.transcript && (
						<Link to={ links.transcript }>
							{ __( 'Open the transcript', 'ohmylms' ) }
						</Link>
					) }
					{ links.report && (
						<Link to={ links.report }>
							{ __( 'Open the student report', 'ohmylms' ) }
						</Link>
					) }
					{ links.gradebook && (
						<Link to={ links.gradebook }>
							{ __(
								'Open skill mastery in the gradebook',
								'ohmylms'
							) }
						</Link>
					) }
				</p>
			) }
		</>
	);
}

export interface OverlayControlProps {
	enabled: boolean;
	onToggle: ( enabled: boolean ) => void;
	/** Chosen target key, '' for none. */
	target: string;
	onChoose: ( target: string ) => void;
	overlay: SkillOverlay | null;
	loading: boolean;
	error: string;
	onRetry: () => void;
}

/**
 * Turn the learner overlay on, choose who to show and read the legend.
 * @param props          Component props.
 * @param props.enabled  Whether the overlay is on.
 * @param props.onToggle Turn it on or off.
 * @param props.target   Chosen target key, '' for none.
 * @param props.onChoose Choose a target key.
 * @param props.overlay  Loaded overlay, or null.
 * @param props.loading  Whether the overlay is loading.
 * @param props.error    Load failure, '' for none.
 * @param props.onRetry  Load it again.
 */
export function OverlayControl( {
	enabled,
	onToggle,
	target,
	onChoose,
	overlay,
	loading,
	error,
	onRetry,
}: OverlayControlProps ) {
	const [ picking, setPicking ] = useState( false );
	const choose = ( key: string ) => {
		setPicking( false );
		onChoose( key );
	};
	const shown = overlay && overlay.target.key === target ? overlay : null;
	return (
		<section
			className="ohmylms-skill-overlay"
			aria-label={ __( 'Learner mastery', 'ohmylms' ) }
		>
			<ToggleControl
				label={ __( 'Show learner mastery', 'ohmylms' ) }
				help={ __(
					'Mark each skill with the mastery level of a learner, class or school.',
					'ohmylms'
				) }
				checked={ enabled }
				onChange={ onToggle }
				__nextHasNoMarginBottom
			/>
			{ enabled && (
				<>
					{ target && ! picking ? (
						<p className="ohmylms-skill-overlay__target">
							{ shown
								? sprintf(
										/* translators: %s: learner, class or school. */
										__( 'Showing %s', 'ohmylms' ),
										shown.target.school
											? `${ shown.target.label } (${ shown.target.school })`
											: shown.target.label
									)
								: __(
										'Showing your selection',
										'ohmylms'
									) }{ ' ' }
							<Button
								variant="link"
								onClick={ () => setPicking( true ) }
							>
								{ __( 'Change', 'ohmylms' ) }
							</Button>
						</p>
					) : (
						<TargetPicker
							onChoose={ choose }
							onCancel={
								target ? () => setPicking( false ) : undefined
							}
						/>
					) }
					<div aria-live="polite">
						{ loading && (
							<p role="status">
								{ __( 'Loading learner mastery…', 'ohmylms' ) }
							</p>
						) }
					</div>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }{ ' ' }
							<Button variant="secondary" onClick={ onRetry }>
								{ __( 'Retry', 'ohmylms' ) }
							</Button>
						</Notice>
					) }
					{ shown && ! loading && (
						<OverlaySummary overlay={ shown } />
					) }
				</>
			) }
		</section>
	);
}
