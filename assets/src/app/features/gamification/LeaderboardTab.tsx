import { __ } from '@wordpress/i18n';
import { NumberField, SelectField, ToggleField } from './fields';
import { SectionFrame } from './SectionFrame';
import type { SectionState } from './hooks';
import type { GamificationOptions } from './types';

/**
 * Leaderboard and rewards tab. The leaderboard ranks learners per course; learners see only a
 * rank, a name and a score. Rewards let learners pay for eligible courses with points.
 *
 * @param props             Component props.
 * @param props.leaderboard Leaderboard section state.
 * @param props.rewards     Rewards section state.
 * @param props.options     Choices from the server, if loaded.
 */
export function LeaderboardTab( {
	leaderboard,
	rewards,
	options,
}: {
	leaderboard: SectionState< 'leaderboard' >;
	rewards: SectionState< 'rewards' >;
	options: GamificationOptions | null;
} ) {
	const draft = leaderboard.draft;
	const modes = options?.leaderboard_modes ?? [
		{
			value: 'completion_rate' as const,
			label: __( 'Course completion rate', 'ohmylms' ),
		},
		{
			value: 'highest_quiz' as const,
			label: __( 'Highest quiz score', 'ohmylms' ),
		},
		{
			value: 'fastest_time' as const,
			label: __( 'Fastest completion time', 'ohmylms' ),
		},
	];
	return (
		<>
			<SectionFrame
				title={ __( 'Leaderboard', 'ohmylms' ) }
				description={ __(
					'Show learners how they rank in a course. Only the rank, display name and score are shown.',
					'ohmylms'
				) }
				state={ leaderboard }
			>
				{ draft && (
					<>
						<ToggleField
							label={ __( 'Show a leaderboard', 'ohmylms' ) }
							checked={ draft.enabled }
							onChange={ ( enabled ) =>
								leaderboard.update( ( current ) => ( {
									...current,
									enabled,
								} ) )
							}
						/>
						<SelectField
							label={ __( 'Rank learners by', 'ohmylms' ) }
							value={ draft.mode }
							options={ modes }
							onChange={ ( mode ) =>
								leaderboard.update( ( current ) => ( {
									...current,
									mode,
								} ) )
							}
						/>
						<NumberField
							label={ __(
								'Minimum percentage to be listed',
								'ohmylms'
							) }
							help={ __( '0 lists every learner.', 'ohmylms' ) }
							value={ draft.threshold }
							min={ 0 }
							max={ 100 }
							error={ leaderboard.errors.threshold }
							onChange={ ( threshold ) =>
								leaderboard.update( ( current ) => ( {
									...current,
									threshold,
								} ) )
							}
						/>
						<NumberField
							label={ __( 'Learners shown', 'ohmylms' ) }
							value={ draft.limit }
							min={ 1 }
							max={ 100 }
							error={ leaderboard.errors.limit }
							onChange={ ( limit ) =>
								leaderboard.update( ( current ) => ( {
									...current,
									limit,
								} ) )
							}
						/>
					</>
				) }
			</SectionFrame>
			<SectionFrame
				title={ __( 'Rewards', 'ohmylms' ) }
				description={ __(
					'Let learners spend the points they earned.',
					'ohmylms'
				) }
				state={ rewards }
			>
				{ rewards.draft && (
					<>
						<ToggleField
							label={ __( 'Enable rewards', 'ohmylms' ) }
							checked={ rewards.draft.enabled }
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
								'Set the point price on each course; a course can opt out.',
								'ohmylms'
							) }
							checked={ rewards.draft.purchase_with_points }
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
