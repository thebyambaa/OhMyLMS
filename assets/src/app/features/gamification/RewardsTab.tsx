import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { ToggleField } from './fields';
import type { SectionState } from './hooks';
import { isStale } from './model';
import { SectionFrame } from './SectionFrame';

/**
 * Rewards tab: learners spend the bonus points they earned on eligible courses. Each course sets
 * its own point price and can opt out; a course is sold for points only while the Gamification
 * add-on, rewards and point purchases are all on.
 *
 * @param props         Component props.
 * @param props.rewards Rewards section state.
 * @param props.points  Points section state, to warn when no points can be earned.
 */
export function RewardsTab( {
	rewards,
	points,
}: {
	rewards: SectionState< 'rewards' >;
	points: SectionState< 'points' >;
} ) {
	const draft = rewards.draft;
	return (
		<>
			{ isStale( rewards.saveError ) && (
				<Notice status="warning" isDismissible={ false }>
					<p>
						{ __(
							'Someone else saved these settings after you opened them. Reload to see their changes; your edits will be discarded.',
							'ohmylms'
						) }
					</p>
					<Button variant="secondary" onClick={ rewards.reload }>
						{ __( 'Reload settings', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ points.saved && ! points.saved.enabled && draft?.enabled && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'Bonus points are switched off on the Points tab, so learners cannot earn points to spend.',
						'ohmylms'
					) }
				</Notice>
			) }
			<SectionFrame
				title={ __( 'Point rewards', 'ohmylms' ) }
				description={ __(
					'Let learners redeem earned points for eligible courses.',
					'ohmylms'
				) }
				state={ rewards }
			>
				{ draft && (
					<>
						<ToggleField
							label={ __( 'Enable point rewards', 'ohmylms' ) }
							checked={ draft.enabled }
							onChange={ ( enabled ) =>
								rewards.update( ( current ) => ( {
									...current,
									enabled,
								} ) )
							}
						/>
						<ToggleField
							label={ __(
								'Allow course purchases with bonus points',
								'ohmylms'
							) }
							help={ __(
								'Learners redeem their earned points for course access. Set the point price on each course; a course can opt out.',
								'ohmylms'
							) }
							checked={ draft.purchase_with_points }
							onChange={ ( value ) =>
								rewards.update( ( current ) => ( {
									...current,
									purchase_with_points: value,
								} ) )
							}
						/>
					</>
				) }
			</SectionFrame>
		</>
	);
}
