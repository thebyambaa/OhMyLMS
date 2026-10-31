import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { formatNumber } from '../model';
import { useWidget } from '../shell/useWidget';
import { WidgetCard } from '../shell/WidgetCard';
import { healthBand, parseCourseHealth } from './CourseHealthModel';
import type {
	CourseDropOff,
	CourseHealthRow,
	HealthBand,
} from './CourseHealthModel';
import type { DashboardWidgetProps } from './registry';
import './CourseHealthWidget.css';

const WIDTH = 240;
const HEIGHT = 140;
const PAD = 24;

function bandLabel( band: HealthBand ): string {
	if ( 'good' === band ) {
		return __( 'Healthy', 'ohmylms' );
	}
	return 'fair' === band
		? __( 'Watch', 'ohmylms' )
		: __( 'At risk', 'ohmylms' );
}

function typeLabel( type: string ): string {
	switch ( type ) {
		case 'lesson':
			return __( 'lesson', 'ohmylms' );
		case 'quiz':
			return __( 'quiz', 'ohmylms' );
		case 'assignment':
			return __( 'assignment', 'ohmylms' );
		default:
			return __( 'content', 'ohmylms' );
	}
}

export function describeDropOff( stop: CourseDropOff ): string {
	return sprintf(
		/* translators: 1: content title, 2: content type, 3: position in the course, 4: percentage of learners. */
		__( '“%1$s” (%2$s, item %3$d) · %4$d%% stop', 'ohmylms' ),
		stop.title,
		typeLabel( stop.type ),
		stop.position,
		stop.percent
	);
}

export function describeRating( row: CourseHealthRow ): string {
	if ( ! row.rating ) {
		return __( 'No reviews', 'ohmylms' );
	}
	return sprintf(
		/* translators: 1: average rating out of 5, 2: number of reviews. */
		_n(
			'%1$s of 5 (%2$s review)',
			'%1$s of 5 (%2$s reviews)',
			row.rating.count,
			'ohmylms'
		),
		row.rating.average.toFixed( 1 ),
		formatNumber( row.rating.count )
	);
}

/**
 * Completion × rating scatter of the rated courses. The table above carries the same figures, so the
 * chart is described in one sentence for assistive technology.
 *
 * @param props         Component props.
 * @param props.courses Courses with a rating.
 */
function Scatter( { courses }: { courses: readonly CourseHealthRow[] } ) {
	const x = ( completion: number ) =>
		PAD + ( completion / 100 ) * ( WIDTH - 2 * PAD );
	const y = ( rating: number ) =>
		HEIGHT - PAD - ( ( rating - 1 ) / 4 ) * ( HEIGHT - 2 * PAD );
	const summary = courses
		.map( ( course ) =>
			sprintf(
				/* translators: 1: course title, 2: completion percentage, 3: rating. */
				__( '%1$s: %2$d%% completion, rated %3$s', 'ohmylms' ),
				course.title,
				course.completion,
				course.rating?.average.toFixed( 1 ) ?? ''
			)
		)
		.join( '; ' );
	return (
		<figure className="ohmylms-course-health__chart">
			<svg
				role="img"
				aria-label={ sprintf(
					/* translators: %s: list of courses with their completion and rating. */
					__( 'Completion against rating. %s.', 'ohmylms' ),
					summary
				) }
				viewBox={ `0 0 ${ WIDTH } ${ HEIGHT }` }
				width={ WIDTH }
				height={ HEIGHT }
			>
				<line
					className="ohmylms-course-health__axis"
					x1={ PAD }
					y1={ HEIGHT - PAD }
					x2={ WIDTH - PAD }
					y2={ HEIGHT - PAD }
				/>
				<line
					className="ohmylms-course-health__axis"
					x1={ PAD }
					y1={ PAD }
					x2={ PAD }
					y2={ HEIGHT - PAD }
				/>
				<text x={ WIDTH - PAD } y={ HEIGHT - 6 } textAnchor="end">
					{ __( 'completion →', 'ohmylms' ) }
				</text>
				<text x={ 4 } y={ PAD - 8 }>
					{ __( '★ rating', 'ohmylms' ) }
				</text>
				{ courses.map( ( course ) => (
					<circle
						key={ course.id }
						className={ `ohmylms-course-health__dot is-${ healthBand(
							course
						) }` }
						cx={ x( course.completion ) }
						cy={ y( course.rating?.average ?? 1 ) }
						r={ 5 }
					>
						<title>{ course.title }</title>
					</circle>
				) ) }
			</svg>
			<figcaption className="ohmylms-dashboard-widget__meta">
				{ __(
					'Completion (across) against average rating (up); the table lists the same figures.',
					'ohmylms'
				) }
			</figcaption>
		</figure>
	);
}

/**
 * "Course health": per course of the scope its learners, completion, rating, median progress and the
 * item where most learners stop, from `GET /dashboard/widgets/course-health`, with a completion × rating
 * scatter when courses have reviews. Course titles link to the course report when the user may open it.
 *
 * @param props         Component props.
 * @param props.context Scope and period.
 */
export default function CourseHealthWidget( {
	context,
}: DashboardWidgetProps ) {
	const request = useWidget( 'course-health', context, parseCourseHealth );
	const data = request.data;
	const rated = ( data?.courses ?? [] ).filter( ( course ) => course.rating );
	const meta = data
		? sprintf(
				/* translators: 1: courses shown, 2: courses in the scope. */
				_n(
					'%1$d of %2$d course · most enrolled first',
					'%1$d of %2$d courses · most enrolled first',
					data.total,
					'ohmylms'
				),
				data.courses.length,
				data.total
			)
		: undefined;
	return (
		<WidgetCard
			id="course-health"
			title={ __( 'Course health', 'ohmylms' ) }
			meta={ meta }
			loading={ request.loading }
			error={ request.error }
			onRetry={ request.reload }
			empty={
				data && 0 === data.courses.length
					? __(
							'No learner of this scope is enrolled in a course yet.',
							'ohmylms'
						)
					: false
			}
		>
			{ data && data.courses.length > 0 && (
				<>
					<div className="ohmylms-course-health__scroll">
						<table className="widefat striped ohmylms-course-health">
							<caption className="screen-reader-text">
								{ __(
									'Course health: learners, completion, rating, median progress and drop-off per course',
									'ohmylms'
								) }
							</caption>
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Course', 'ohmylms' ) }
									</th>
									<th scope="col" className="num">
										{ __( 'Learners', 'ohmylms' ) }
									</th>
									<th scope="col" className="num">
										{ __( 'Completion', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Rating', 'ohmylms' ) }
									</th>
									<th scope="col" className="num">
										{ __( 'Median progress', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Drop-off', 'ohmylms' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ data.courses.map( ( course ) => {
									const band = healthBand( course );
									return (
										<tr key={ course.id }>
											<th scope="row">
												<span
													className={ `ohmylms-course-health__band is-${ band }` }
												>
													{ bandLabel( band ) }
												</span>{ ' ' }
												{ course.report ? (
													<Link to={ course.report }>
														{ course.title }
													</Link>
												) : (
													course.title
												) }
											</th>
											<td className="num">
												{ formatNumber(
													course.enrolled
												) }
											</td>
											<td className="num">
												{ `${ course.completion }%` }
											</td>
											<td>
												{ course.rating ? (
													<>
														<span aria-hidden="true">
															★{ ' ' }
															{ course.rating.average.toFixed(
																1
															) }
														</span>
														<span className="screen-reader-text">
															{ describeRating(
																course
															) }
														</span>
														<span
															className="ohmylms-dashboard-widget__meta"
															aria-hidden="true"
														>
															{ ` (${ formatNumber(
																course.rating
																	.count
															) })` }
														</span>
													</>
												) : (
													<span className="ohmylms-dashboard-widget__meta">
														{ describeRating(
															course
														) }
													</span>
												) }
											</td>
											<td className="num">
												{ null === course.medianProgress
													? '—'
													: `${ course.medianProgress }%` }
											</td>
											<td>
												{ course.dropOff
													? describeDropOff(
															course.dropOff
														)
													: '—' }
											</td>
										</tr>
									);
								} ) }
							</tbody>
						</table>
					</div>
					{ rated.length > 0 && <Scatter courses={ rated } /> }
				</>
			) }
		</WidgetCard>
	);
}
