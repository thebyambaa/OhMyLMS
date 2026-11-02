import { Button, SelectControl } from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useWidget } from '../shell/useWidget';
import { WidgetCard } from '../shell/WidgetCard';
import { MasteryLearnersDialog } from './MasteryLearnersDialog';
import { MasteryLegend } from './MasteryLegend';
import { cellAt, levelLabel, parseMasteryHeatmap } from './MasteryModel';
import type { HeatmapRow, HeatmapSkill } from './MasteryModel';
import type { DashboardWidgetProps } from './registry';
import './Mastery.css';

/** Skill columns per page. */
export const SKILLS_PER_PAGE = 8;

/** Subject filter value of skills without a subject. */
const NO_SUBJECT = '__none';

/**
 * "Mastery heatmap": the scope's classes (or courses) × its most practised skills from
 * `GET /dashboard/widgets/mastery-heatmap`. Each cell shows the row's average percentage, a level pattern
 * and, on activation, the learners below Familiar. Skills are paged and can be narrowed by subject.
 *
 * @param props         Component props.
 * @param props.context Scope and period.
 */
export default function MasteryHeatmapWidget( {
	context,
}: DashboardWidgetProps ) {
	const request = useWidget(
		'mastery-heatmap',
		context,
		parseMasteryHeatmap
	);
	const [ subject, setSubject ] = useState( '' );
	const [ page, setPage ] = useState( 0 );
	const [ open, setOpen ] = useState< {
		row: HeatmapRow;
		skill: HeatmapSkill;
	} | null >( null );
	const scopeId = context.scope.id;

	useEffect( () => {
		setSubject( '' );
		setPage( 0 );
		setOpen( null );
	}, [ scopeId ] );

	const data = request.data;
	const columns = useMemo( () => {
		if ( ! data ) {
			return [];
		}
		return data.skills
			.map( ( skill, index ) => ( { skill, index } ) )
			.filter( ( { skill } ) => {
				if ( '' === subject ) {
					return true;
				}
				return NO_SUBJECT === subject
					? '' === skill.subject
					: skill.subject === subject;
			} );
	}, [ data, subject ] );
	const pages = Math.max( 1, Math.ceil( columns.length / SKILLS_PER_PAGE ) );
	const current = Math.min( page, pages - 1 );
	const visible = columns.slice(
		current * SKILLS_PER_PAGE,
		( current + 1 ) * SKILLS_PER_PAGE
	);
	const rowNoun =
		'course' === data?.rowType
			? __( 'Course', 'ohmylms' )
			: __( 'Class', 'ohmylms' );

	let empty: string | false = false;
	if ( data && 0 === data.rows.length ) {
		empty = __(
			'There are no classes or courses with learners in this view yet.',
			'ohmylms'
		);
	} else if ( data && 0 === data.skills.length ) {
		empty = __(
			'No skill evidence yet: levels appear once learners answer questions linked to skills.',
			'ohmylms'
		);
	}

	return (
		<WidgetCard
			id="mastery-heatmap"
			title={ __( 'Mastery heatmap', 'ohmylms' ) }
			meta={
				'course' === data?.rowType
					? __(
							'course × skill · select a cell to see who is struggling',
							'ohmylms'
						)
					: __(
							'class × skill · select a cell to see who is struggling',
							'ohmylms'
						)
			}
			loading={ request.loading }
			error={ request.error }
			onRetry={ request.reload }
			empty={ empty }
		>
			{ data && data.rows.length > 0 && data.skills.length > 0 && (
				<>
					<div className="ohmylms-heatmap__toolbar">
						{ data.subjects.length > 0 && (
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Subject', 'ohmylms' ) }
								labelPosition="side"
								value={ subject }
								onChange={ ( next: string ) => {
									setSubject( next );
									setPage( 0 );
								} }
							>
								<option value="">
									{ __( 'All subjects', 'ohmylms' ) }
								</option>
								{ data.subjects.map( ( item ) => (
									<option
										key={ item.name }
										value={ item.name }
									>
										{ item.name }
									</option>
								) ) }
								{ data.skills.some(
									( skill ) => '' === skill.subject
								) && (
									<option value={ NO_SUBJECT }>
										{ __( 'No subject', 'ohmylms' ) }
									</option>
								) }
							</SelectControl>
						) }
						<span
							className="ohmylms-heatmap__range"
							aria-live="polite"
						>
							{ 0 === columns.length
								? __( 'No skills in this subject', 'ohmylms' )
								: sprintf(
										/* translators: 1: first skill shown, 2: last skill shown, 3: skills. */
										__(
											'Skills %1$d–%2$d of %3$d',
											'ohmylms'
										),
										current * SKILLS_PER_PAGE + 1,
										current * SKILLS_PER_PAGE +
											visible.length,
										columns.length
									) }
						</span>
						{ pages > 1 && (
							<span className="ohmylms-heatmap__pager">
								<Button
									variant="secondary"
									size="compact"
									disabled={ 0 === current }
									accessibleWhenDisabled
									onClick={ () => setPage( current - 1 ) }
								>
									{ __( 'Previous skills', 'ohmylms' ) }
								</Button>
								<Button
									variant="secondary"
									size="compact"
									disabled={ current >= pages - 1 }
									accessibleWhenDisabled
									onClick={ () => setPage( current + 1 ) }
								>
									{ __( 'Next skills', 'ohmylms' ) }
								</Button>
							</span>
						) }
					</div>
					<div className="ohmylms-heatmap__scroll">
						<table className="ohmylms-heatmap">
							<caption className="screen-reader-text">
								{ 'course' === data.rowType
									? __(
											'Average mastery by course and skill',
											'ohmylms'
										)
									: __(
											'Average mastery by class and skill',
											'ohmylms'
										) }
							</caption>
							<thead>
								<tr>
									<th scope="col">{ rowNoun }</th>
									{ visible.map( ( { skill } ) => (
										<th
											key={ skill.id }
											scope="col"
											title={ skill.code || undefined }
										>
											{ skill.name }
										</th>
									) ) }
								</tr>
							</thead>
							<tbody>
								{ data.rows.map( ( row, rowIndex ) => (
									<tr key={ row.key }>
										<th scope="row">
											{ row.label }
											<span className="ohmylms-heatmap__learners">
												{ sprintf(
													/* translators: %d: learners. */
													_n(
														'%d learner',
														'%d learners',
														row.learners,
														'ohmylms'
													),
													row.learners
												) }
											</span>
										</th>
										{ visible.map( ( { skill, index } ) => {
											const cell = cellAt(
												data,
												rowIndex,
												index
											);
											const level = levelLabel(
												cell.level
											);
											if ( 0 === cell.assessed ) {
												return (
													<td key={ skill.id }>
														<span className="ohmylms-heatmap__cell is-not-started">
															<span aria-hidden="true">
																—
															</span>
															<span className="screen-reader-text">
																{ level }
															</span>
														</span>
													</td>
												);
											}
											const label = sprintf(
												/* translators: 1: class or course, 2: skill, 3: level, 4: percentage, 5: learners below Familiar. */
												_n(
													'%1$s, %2$s: %3$s, average %4$d%%, %5$d learner below Familiar',
													'%1$s, %2$s: %3$s, average %4$d%%, %5$d learners below Familiar',
													cell.below,
													'ohmylms'
												),
												row.label,
												skill.name,
												level,
												cell.percent,
												cell.below
											);
											return (
												<td key={ skill.id }>
													<button
														type="button"
														className={ `ohmylms-heatmap__cell is-${ cell.level }` }
														aria-label={ label }
														title={ label }
														onClick={ () =>
															setOpen( {
																row,
																skill,
															} )
														}
													>
														<span className="ohmylms-heatmap__value">
															{ sprintf(
																/* translators: %d: percentage. */
																__(
																	'%d%%',
																	'ohmylms'
																),
																cell.percent
															) }
														</span>
														{ cell.below > 0 && (
															<span className="ohmylms-heatmap__below">
																{ sprintf(
																	/* translators: %d: learners below Familiar. */
																	__(
																		'%d below',
																		'ohmylms'
																	),
																	cell.below
																) }
															</span>
														) }
													</button>
												</td>
											);
										} ) }
									</tr>
								) ) }
							</tbody>
						</table>
					</div>
					{ data.rowsTotal > data.rows.length && (
						<p className="description">
							{ sprintf(
								/* translators: 1: rows shown, 2: rows in the view. */
								__(
									'Showing %1$d of %2$d classes. Choose a school or class above to see the others.',
									'ohmylms'
								),
								data.rows.length,
								data.rowsTotal
							) }
						</p>
					) }
					<MasteryLegend levels={ data.levels } />
				</>
			) }
			{ open && (
				<MasteryLearnersDialog
					scope={ scopeId }
					row={ open.row.key }
					skill={ open.skill.id }
					title={ sprintf(
						/* translators: 1: skill, 2: class or course. */
						__( 'Below Familiar on %1$s · %2$s', 'ohmylms' ),
						open.skill.name,
						open.row.label
					) }
					onClose={ () => setOpen( null ) }
				/>
			) }
		</WidgetCard>
	);
}
