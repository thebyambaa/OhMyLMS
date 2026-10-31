import { Button, Notice } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { normalizeApiError } from '../../../lib/api';
import { describeWidgetError } from '../shell/errors';
import { useWidget } from '../shell/useWidget';
import { WidgetCard } from '../shell/WidgetCard';
import { assignReviewSet } from './MasteryApi';
import { MasteryLearnersDialog } from './MasteryLearnersDialog';
import { parseReviewSets } from './ReviewSetsModel';
import type { ReviewSet, ReviewSetGroup } from './ReviewSetsModel';
import type { DashboardWidgetProps } from './registry';
import './Mastery.css';

/**
 * "Recommended next assignments": groups of learners in one class below Familiar on a skill, from
 * `GET /dashboard/widgets/review-sets`. "Assign review set" creates a draft quiz drawing the skill's
 * approved questions (never published) and links to it; "See group" lists the learners.
 *
 * @param props         Component props.
 * @param props.context Scope and period.
 */
export default function ReviewSetsWidget( { context }: DashboardWidgetProps ) {
	const request = useWidget( 'review-sets', context, parseReviewSets );
	const [ created, setCreated ] = useState<
		Readonly< Record< string, ReviewSet > >
	>( {} );
	const [ pending, setPending ] = useState< string | null >( null );
	const [ failure, setFailure ] = useState< {
		key: string;
		message: string;
	} | null >( null );
	const [ announced, setAnnounced ] = useState< ReviewSet | null >( null );
	const [ group, setGroup ] = useState< ReviewSetGroup | null >( null );
	const scopeId = context.scope.id;

	useEffect( () => {
		setCreated( {} );
		setFailure( null );
		setAnnounced( null );
		setGroup( null );
	}, [ scopeId ] );

	const data = request.data;

	const assign = async ( item: ReviewSetGroup ) => {
		if ( pending ) {
			return;
		}
		setPending( item.key );
		setFailure( null );
		try {
			const result = await assignReviewSet(
				scopeId,
				item.classInfo.id,
				item.skill.id
			);
			setCreated( ( sets ) => ( {
				...sets,
				[ item.key ]: result.reviewSet,
			} ) );
			setAnnounced( result.reviewSet );
		} catch ( error ) {
			setFailure( {
				key: item.key,
				message: describeWidgetError( normalizeApiError( error ) ),
			} );
		} finally {
			setPending( null );
		}
	};

	let empty: string | false = false;
	if ( data && 'course' === data.rowType ) {
		empty = __(
			'Recommendations are made for classes, and this view has none. Choose a school or class with classes.',
			'ohmylms'
		);
	} else if ( data && 0 === data.groups.length ) {
		empty = sprintf(
			/* translators: %d: fewest learners in a group. */
			__(
				'No group of %d or more learners in a class is below Familiar on a skill. Nothing to review right now.',
				'ohmylms'
			),
			data.minGroup
		);
	}

	return (
		<WidgetCard
			id="review-sets"
			title={ __( 'Recommended next assignments', 'ohmylms' ) }
			meta={ sprintf(
				/* translators: %s: school or class. */
				__( 'groups below Familiar · %s', 'ohmylms' ),
				context.scope.label
			) }
			loading={ request.loading }
			error={ request.error }
			onRetry={ request.reload }
			empty={ empty }
		>
			{ announced && (
				<div role="status" className="ohmylms-review-sets__status">
					<Notice status="success" isDismissible={ false }>
						{ sprintf(
							/* translators: %s: quiz title. */
							__(
								'Draft review set ready: %s. It is not published; review it, then publish and share it with the group.',
								'ohmylms'
							),
							announced.title
						) }{ ' ' }
						{ announced.route ? (
							<Link to={ announced.route }>
								{ __( 'Open the draft', 'ohmylms' ) }
							</Link>
						) : (
							__(
								'A site administrator can open it under Content › Quizzes.',
								'ohmylms'
							)
						) }
					</Notice>
				</div>
			) }
			{ data && data.groups.length > 0 && (
				<ul className="ohmylms-review-sets">
					{ data.groups.map( ( item ) => {
						const set = created[ item.key ] ?? item.reviewSet;
						return (
							<li
								key={ item.key }
								className="ohmylms-review-sets__item"
							>
								<span>
									<strong>
										{ sprintf(
											/* translators: 1: learners, 2: class, 3: skill. */
											_n(
												'%1$d learner in %2$s is below Familiar on %3$s',
												'%1$d learners in %2$s are below Familiar on %3$s',
												item.learners,
												'ohmylms'
											),
											item.learners,
											item.classInfo.label,
											item.skill.name
										) }
									</strong>
									<span
										className={ `ohmylms-review-sets__detail${
											item.questions.enough
												? ''
												: ' is-short'
										}` }
									>
										{ item.questions.enough
											? sprintf(
													/* translators: 1: questions drawn, 2: approved questions. */
													__(
														'A review set draws %1$d of %2$d approved questions.',
														'ohmylms'
													),
													item.questions.draw,
													item.questions.available
												)
											: sprintf(
													/* translators: 1: approved questions, 2: questions needed. */
													__(
														'Not enough questions: %1$d approved, %2$d needed.',
														'ohmylms'
													),
													item.questions.available,
													item.questions.needed
												) }
									</span>
									{ failure?.key === item.key && (
										<Notice
											status="error"
											isDismissible={ false }
										>
											{ failure.message }
										</Notice>
									) }
								</span>
								<span className="ohmylms-review-sets__actions">
									{ set && (
										<>
											<span>
												{ __(
													'Draft review set created',
													'ohmylms'
												) }
											</span>
											{ set.route && (
												<Link
													to={ set.route }
													className="button"
												>
													{ __(
														'Open draft',
														'ohmylms'
													) }
												</Link>
											) }
										</>
									) }
									{ ! set &&
										item.questions.enough &&
										data.canAssign && (
											<Button
												variant="primary"
												isBusy={ pending === item.key }
												disabled={ null !== pending }
												accessibleWhenDisabled
												onClick={ () => assign( item ) }
												aria-label={ sprintf(
													/* translators: 1: skill, 2: class. */
													__(
														'Assign review set: %1$s, %2$s',
														'ohmylms'
													),
													item.skill.name,
													item.classInfo.label
												) }
											>
												{ __(
													'Assign review set',
													'ohmylms'
												) }
											</Button>
										) }
									{ ! set &&
										item.questions.enough &&
										! data.canAssign && (
											<span className="description">
												{ __(
													'Creating quizzes needs an author account.',
													'ohmylms'
												) }
											</span>
										) }
									<Button
										variant="secondary"
										onClick={ () => setGroup( item ) }
										aria-label={ sprintf(
											/* translators: 1: skill, 2: class. */
											__(
												'See group: %1$s, %2$s',
												'ohmylms'
											),
											item.skill.name,
											item.classInfo.label
										) }
									>
										{ __( 'See group', 'ohmylms' ) }
									</Button>
								</span>
							</li>
						);
					} ) }
				</ul>
			) }
			{ group && (
				<MasteryLearnersDialog
					scope={ scopeId }
					row={ group.classInfo.key }
					skill={ group.skill.id }
					title={ sprintf(
						/* translators: 1: skill, 2: class. */
						__( 'Below Familiar on %1$s · %2$s', 'ohmylms' ),
						group.skill.name,
						group.classInfo.label
					) }
					onClose={ () => setGroup( null ) }
				/>
			) }
		</WidgetCard>
	);
}
