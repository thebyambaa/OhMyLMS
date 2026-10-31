import { Modal, Notice, Spinner } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { describeWidgetError } from '../shell/errors';
import { useRequest } from '../useRequest';
import { getMasteryLearners } from './MasteryApi';
import { levelLabel } from './MasteryModel';

/**
 * Learners of one heatmap row who are below Familiar on a skill (lowest first), each linking to their
 * student report when the user may open it. Shared by the heatmap cells and "See group".
 *
 * @param props         Component props.
 * @param props.scope   Scope id.
 * @param props.row     Row key (`class:9`).
 * @param props.skill   Skill id.
 * @param props.title   Dialog title.
 * @param props.onClose Closes the dialog.
 */
export function MasteryLearnersDialog( {
	scope,
	row,
	skill,
	title,
	onClose,
}: {
	scope: string;
	row: string;
	skill: number;
	title: string;
	onClose: () => void;
} ) {
	const request = useRequest(
		( signal ) => getMasteryLearners( scope, row, skill, signal ),
		`${ scope }|${ row }|${ skill }`
	);
	const data = request.data;
	return (
		<Modal
			title={ title }
			onRequestClose={ onClose }
			className="ohmylms-mastery-dialog"
		>
			{ request.loading && (
				<p role="status">
					<Spinner /> { __( 'Loading…', 'ohmylms' ) }
				</p>
			) }
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ describeWidgetError( request.error ) }
				</Notice>
			) }
			{ data && ! request.loading && (
				<>
					<p>
						{ sprintf(
							/* translators: 1: learners below Familiar, 2: learners with evidence, 3: learners in the class or course. */
							_n(
								'%1$d of %2$d learners with evidence is below Familiar (%3$d in all).',
								'%1$d of %2$d learners with evidence are below Familiar (%3$d in all).',
								data.below,
								'ohmylms'
							),
							data.below,
							data.assessed,
							data.row.learners
						) }
					</p>
					{ 0 === data.learners.length ? (
						<p>
							{ __(
								'Nobody here is below Familiar on this skill.',
								'ohmylms'
							) }
						</p>
					) : (
						<table className="widefat striped ohmylms-mastery-dialog__table">
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Learner', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Mastery', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Level', 'ohmylms' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ data.learners.map( ( learner ) => (
									<tr key={ learner.id }>
										<td>
											{ learner.route ? (
												<Link to={ learner.route }>
													{ learner.name }
												</Link>
											) : (
												learner.name
											) }
										</td>
										<td>
											{ sprintf(
												/* translators: %d: percentage. */
												__( '%d%%', 'ohmylms' ),
												learner.percent
											) }
										</td>
										<td>
											<span
												className={ `ohmylms-mastery-swatch is-${ learner.level }` }
												aria-hidden="true"
											/>{ ' ' }
											{ levelLabel( learner.level ) }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) }
					{ data.below > data.learners.length && (
						<p className="description">
							{ sprintf(
								/* translators: %d: learners listed. */
								__( 'Showing the %d lowest.', 'ohmylms' ),
								data.learners.length
							) }
						</p>
					) }
				</>
			) }
		</Modal>
	);
}
