import {
	createElement,
	Fragment,
	useEffect,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Notice, Spinner } from '@wordpress/components';
import { loadGuidance, loadPlacement } from './api.mjs';

const STEP_LABELS = {
	help: __( 'Needs help', 'ohmylms' ),
	review: __( 'Review', 'ohmylms' ),
	continue: __( 'Keep going', 'ohmylms' ),
	ready: __( 'Ready to learn', 'ohmylms' ),
};

/**
 * What the guidance engine and the placement tests say about one learner, for the teacher's
 * student report: strength by area, next steps, reviews covered by other work, readiness per
 * syllabus and the latest placement in each subject. Read only.
 *
 * @param {Object} props           Component props.
 * @param {number} props.studentId Learner.
 * @param {number} props.courseId  Course the report is scoped to.
 */
export function LearnerGuidance( { studentId, courseId } ) {
	const [ guidance, setGuidance ] = useState( null );
	const [ placements, setPlacements ] = useState( [] );
	const [ error, setError ] = useState( '' );
	useEffect( () => {
		let active = true;
		setGuidance( null );
		Promise.all( [
			loadGuidance( studentId, courseId ),
			loadPlacement( studentId, courseId ).catch( () => [] ),
		] )
			.then( ( [ nextGuidance, nextPlacements ] ) => {
				if ( active ) {
					setGuidance( nextGuidance );
					setPlacements( nextPlacements || [] );
				}
			} )
			.catch( ( cause ) => active && setError( cause.message ) );
		return () => {
			active = false;
		};
	}, [ studentId, courseId ] );
	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}
	if ( ! guidance ) {
		return <Spinner />;
	}
	const empty =
		! guidance.areas.length &&
		! guidance.next_steps.length &&
		! guidance.readiness.length &&
		! guidance.exam &&
		! placements.length;
	if ( empty ) {
		return null;
	}
	return (
		<div className="ohmylms-learner-guidance">
			{ guidance.exam && (
				<Fragment>
					<h3>{ __( 'Preparing for', 'ohmylms' ) }</h3>
					<p>
						{ sprintf(
							/* translators: 1: exam, 2: date, 3: days left, 4: readiness percent. */
							__(
								'%1$s on %2$s (%3$d days left), readiness %4$d%%.',
								'ohmylms'
							),
							guidance.exam.syllabus.name,
							guidance.exam.date,
							guidance.exam.days_left,
							guidance.exam.readiness.percent
						) }{ ' ' }
						{ guidance.exam.on_track
							? __( 'On track.', 'ohmylms' )
							: __( 'Behind the plan.', 'ohmylms' ) }
					</p>
				</Fragment>
			) }
			{ guidance.next_steps.length > 0 && (
				<Fragment>
					<h3>{ __( 'Next steps', 'ohmylms' ) }</h3>
					{ guidance.session_minutes > 0 && (
						<p>
							{ sprintf(
								/* translators: %d: minutes. */
								__(
									'One sitting of about %d minutes.',
									'ohmylms'
								),
								guidance.session_minutes
							) }
						</p>
					) }
					<ul>
						{ guidance.next_steps.map( ( step, index ) => (
							<li key={ index }>
								<strong>{ STEP_LABELS[ step.type ] }:</strong>{ ' ' }
								{ step.skill.name }
								{ step.reason === 'placement' &&
									` · ${ __(
										'suggested by the placement test',
										'ohmylms'
									) }` }
								{ step.type === 'review' &&
									step.covered_by.length > 0 &&
									` · ${ sprintf(
										/* translators: 1: percentage, 2: skill names. */
										__(
											'%1$d%% covered by %2$s',
											'ohmylms'
										),
										Math.round( step.credit * 100 ),
										step.covered_by
											.map( ( item ) => item.name )
											.join( ', ' )
									) }` }
								{ step.also_reviews?.length > 0 &&
									` · ${ sprintf(
										/* translators: %s: skill names. */
										__( 'also reviews %s', 'ohmylms' ),
										step.also_reviews
											.map( ( item ) => item.name )
											.join( ', ' )
									) }` }
								{ step.type === 'help' &&
									step.prerequisites.length > 0 &&
									` · ${ sprintf(
										/* translators: %s: prerequisite skill names. */
										__( 'check first: %s', 'ohmylms' ),
										step.prerequisites
											.map( ( item ) => item.name )
											.join( ', ' )
									) }` }
							</li>
						) ) }
					</ul>
				</Fragment>
			) }
			{ guidance.areas.length > 0 && (
				<Fragment>
					<h3>{ __( 'Strength by area', 'ohmylms' ) }</h3>
					<ul>
						{ guidance.areas.map( ( area ) => (
							<li key={ area.domain }>
								<strong>{ area.label }:</strong>{ ' ' }
								{ sprintf(
									/* translators: 1: strength out of 100, 2: skills started. */
									_n(
										'%1$d/100 over %2$d skill started',
										'%1$d/100 over %2$d skills started',
										area.started,
										'ohmylms'
									),
									area.strength,
									area.started
								) }
								{ area.grade_label &&
									` · ${ sprintf(
										/* translators: %s: grade name. */
										__( 'proficient up to %s', 'ohmylms' ),
										area.grade_label
									) }` }
							</li>
						) ) }
					</ul>
				</Fragment>
			) }
			{ guidance.reviews.covered.length > 0 && (
				<Fragment>
					<h3>
						{ __( 'Reviews covered by other work', 'ohmylms' ) }
					</h3>
					<ul>
						{ guidance.reviews.covered.map( ( review ) => (
							<li key={ review.skill.id }>
								{ sprintf(
									/* translators: 1: skill, 2: skills that covered it. */
									__( '%1$s, by %2$s', 'ohmylms' ),
									review.skill.name,
									review.covered_by
										.map( ( item ) => item.name )
										.join( ', ' )
								) }
							</li>
						) ) }
					</ul>
				</Fragment>
			) }
			{ guidance.readiness.length > 0 && (
				<Fragment>
					<h3>{ __( 'Readiness', 'ohmylms' ) }</h3>
					<ul>
						{ guidance.readiness.map( ( entry ) => (
							<li key={ entry.syllabus.id }>
								<strong>{ entry.syllabus.name }:</strong>{ ' ' }
								{ sprintf(
									/* translators: 1: percent ready, 2: objectives ready, 3: all objectives. */
									__(
										'%1$d%% ready (%2$d of %3$d objectives)',
										'ohmylms'
									),
									entry.percent,
									entry.ready,
									entry.total
								) }
							</li>
						) ) }
					</ul>
				</Fragment>
			) }
			{ placements.length > 0 && (
				<Fragment>
					<h3>{ __( 'Placement tests', 'ohmylms' ) }</h3>
					<ul>
						{ placements.map( ( placement ) => (
							<li key={ placement.uuid }>
								<strong>{ placement.subject }:</strong>{ ' ' }
								{ placement.domains
									.map(
										( domain ) =>
											`${ domain.label } ${
												domain.grade_label ||
												__(
													'from the start',
													'ohmylms'
												)
											}`
									)
									.join( ' · ' ) }
								{ placement.start.length > 0 &&
									` · ${ sprintf(
										/* translators: %s: skill names. */
										__( 'start with %s', 'ohmylms' ),
										placement.start
											.map( ( item ) => item.name )
											.join( ', ' )
									) }` }{ ' ' }
								<span className="ohmylms-ext-muted">
									({ placement.completed_at } UTC)
								</span>
							</li>
						) ) }
					</ul>
				</Fragment>
			) }
		</div>
	);
}
