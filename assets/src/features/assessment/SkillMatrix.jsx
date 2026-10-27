import { createElement, Fragment } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import {
	averageKey,
	cellView,
	legendLevels,
	levelCssClass,
	unitColumns,
} from './skillMatrix.mjs';

/**
 * Colour-coded Khan mastery legend.
 * @param root0
 * @param root0.labels
 */
export function MatrixLegend( { labels } ) {
	return (
		<ul
			className="ohmylms-mx-legend"
			aria-label={ __( 'Mastery levels', 'ohmylms' ) }
		>
			{ legendLevels( labels ).map( ( level ) => (
				<li key={ level.key }>
					<span
						className={ `ohmylms-mx-cell ${ levelCssClass( level.key ) }` }
						aria-hidden="true"
					>
						{ level.letter }
					</span>
					{ level.label }
				</li>
			) ) }
			<li>
				<span className="ohmylms-mx-review" aria-hidden="true">
					↻
				</span>
				{ __( 'Review due', 'ohmylms' ) }
			</li>
		</ul>
	);
}

/**
 * Learners x skills matrix with skills under unit headers. Each cell shows a letter and carries
 * the level name as hidden text, so colour is never the only signal.
 * @param root0
 * @param root0.matrix
 * @param root0.onSelect
 */
export function SkillMatrix( { matrix, onSelect } ) {
	const columns = unitColumns( matrix );
	const labels = matrix.display_levels || {};
	const grouped = columns.length > 1 || columns[ 0 ]?.name;
	return (
		<Fragment>
			<MatrixLegend labels={ labels } />
			<div className="ohmylms-mx-scroll">
				<table className="ohmylms-mx">
					<caption className="ohmylms-mx-sr">
						{ __(
							'Skill levels by learner, grouped by unit',
							'ohmylms'
						) }
					</caption>
					<thead>
						{ grouped && (
							<tr>
								<th scope="col" rowSpan="2">
									{ __( 'Learner', 'ohmylms' ) }
								</th>
								{ columns.map( ( group ) => (
									<th
										key={ group.id }
										scope="colgroup"
										className="ohmylms-mx-unit"
										colSpan={ group.skills.length }
									>
										{ group.name }
									</th>
								) ) }
							</tr>
						) }
						<tr>
							{ ! grouped && (
								<th scope="col">
									{ __( 'Learner', 'ohmylms' ) }
								</th>
							) }
							{ columns.map( ( group ) =>
								group.skills.map( ( skill ) => (
									<th
										key={ skill.id }
										scope="col"
										title={ skill.name }
									>
										{ skill.code || skill.name }
									</th>
								) )
							) }
						</tr>
					</thead>
					<tbody>
						{ matrix.students.map( ( learner ) => (
							<tr key={ learner.id }>
								<th scope="row">
									{ onSelect ? (
										<Button
											variant="link"
											onClick={ () =>
												onSelect( learner )
											}
										>
											{ learner.name }
										</Button>
									) : (
										learner.name
									) }
								</th>
								{ columns.map( ( group ) =>
									group.skills.map( ( skill ) => {
										const view = cellView(
											learner.skills?.[ skill.id ],
											labels
										);
										return (
											<td
												key={ skill.id }
												className={ `ohmylms-mx-cell ${ levelCssClass( view.key ) }` }
												title={ sprintf(
													/* translators: 1: level, 2: evidence count, 3: independent correct count. */
													__(
														'%1$s · %2$d pieces of evidence, %3$d correct on their own',
														'ohmylms'
													),
													view.label,
													view.evidence,
													view.independent
												) }
											>
												<span aria-hidden="true">
													{ view.letter }
												</span>
												<span className="ohmylms-mx-sr">
													{ view.label }
												</span>
												{ view.reviewDue && (
													<Fragment>
														<span
															className="ohmylms-mx-review"
															aria-hidden="true"
														>
															↻
														</span>
														<span className="ohmylms-mx-sr">
															{ ', ' +
																__(
																	'review due',
																	'ohmylms'
																) }
														</span>
													</Fragment>
												) }
											</td>
										);
									} )
								) }
							</tr>
						) ) }
						<tr className="ohmylms-mx-average">
							<th scope="row">
								{ __( 'Class average', 'ohmylms' ) }
							</th>
							{ columns.map( ( group ) =>
								group.skills.map( ( skill ) => {
									const key = averageKey( matrix, skill.id );
									const view = cellView(
										key ? { display: key } : null,
										labels
									);
									return (
										<td
											key={ skill.id }
											className={ `ohmylms-mx-cell ${ levelCssClass( view.key ) }` }
										>
											<span aria-hidden="true">
												{ key ? view.letter : '–' }
											</span>
											<span className="ohmylms-mx-sr">
												{ key
													? view.label
													: __(
															'Too few learners to average',
															'ohmylms'
														) }
											</span>
										</td>
									);
								} )
							) }
						</tr>
					</tbody>
				</table>
			</div>
		</Fragment>
	);
}
