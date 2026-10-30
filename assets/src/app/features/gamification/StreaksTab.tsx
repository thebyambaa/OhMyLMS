import { Button, Notice } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import { listMilestoneBadges } from './engagementApi';
import { ENGAGEMENT_LIMITS } from './engagementModel';
import { NumberField, SelectField, ToggleField } from './fields';
import { SectionFrame } from './SectionFrame';
import type { EngagementState } from './useEngagement';
import type { Badge, StreakDraft, StreakMilestoneDraft } from './types';

/** Badges a milestone can award, loaded once when the tab opens. */
function useMilestoneBadges() {
	const [ badges, setBadges ] = useState< Badge[] | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	useEffect( () => {
		const controller = new AbortController();
		listMilestoneBadges( controller.signal ).then(
			( next ) => {
				if ( ! controller.signal.aborted ) {
					setBadges( next );
				}
			},
			( failure: unknown ) => {
				if ( ! controller.signal.aborted ) {
					setError( normalizeApiError( failure ).message );
				}
			}
		);
		return () => controller.abort();
	}, [] );
	return { badges, error };
}

/**
 * Streaks tab: which activities make a learning day, freezes that protect a missed day, and the
 * milestones that award a badge and points.
 *
 * @param props       Component props.
 * @param props.state Streak group state.
 */
export function StreaksTab( {
	state,
}: {
	state: EngagementState< 'streak' >;
} ) {
	const { badges, error: badgeError } = useMilestoneBadges();
	const draft = state.draft;
	const errors = state.errors;
	const set = < K extends keyof StreakDraft >(
		key: K,
		value: StreakDraft[ K ]
	) => state.update( ( current ) => ( { ...current, [ key ]: value } ) );
	const setMilestone = (
		index: number,
		change: Partial< StreakMilestoneDraft >
	) =>
		state.update( ( current ) => ( {
			...current,
			milestones: current.milestones.map( ( item, position ) =>
				position === index ? { ...item, ...change } : item
			),
		} ) );

	const badgeOptions = ( selected: string ) => {
		const options = [
			{ value: '', label: __( 'No badge', 'ohmylms' ) },
			...( badges ?? [] ).map( ( badge ) => ( {
				value: badge.slug,
				label: badge.name,
			} ) ),
		];
		// Keep a stored choice visible even when the list could not be loaded.
		if (
			selected &&
			! options.some( ( item ) => item.value === selected )
		) {
			options.push( { value: selected, label: selected } );
		}
		return options;
	};

	return (
		<SectionFrame
			title={ __( 'Learning streaks', 'ohmylms' ) }
			description={ __(
				'Count the days in a row a learner studies. Streaks are off until you switch them on; switching them on installs their storage.',
				'ohmylms'
			) }
			state={ state }
		>
			{ draft && (
				<>
					<ToggleField
						label={ __( 'Record learning streaks', 'ohmylms' ) }
						checked={ draft.enable }
						onChange={ ( value ) => set( 'enable', value ) }
					/>
					<fieldset>
						<legend>
							{ __( 'What makes a learning day', 'ohmylms' ) }
						</legend>
						<ToggleField
							label={ __( 'A completed lesson', 'ohmylms' ) }
							checked={ draft.lesson }
							onChange={ ( value ) => set( 'lesson', value ) }
						/>
						<ToggleField
							label={ __( 'A finished quiz', 'ohmylms' ) }
							checked={ draft.quiz }
							onChange={ ( value ) => set( 'quiz', value ) }
						/>
						<ToggleField
							label={ __( 'A practice session', 'ohmylms' ) }
							checked={ draft.practice }
							onChange={ ( value ) => set( 'practice', value ) }
						/>
						<NumberField
							label={ __(
								'Answers a practice session needs',
								'ohmylms'
							) }
							value={ draft.practice_minimum }
							min={ 1 }
							max={ 30 }
							error={ errors.practice_minimum }
							onChange={ ( value ) =>
								set( 'practice_minimum', value )
							}
						/>
					</fieldset>
					<fieldset>
						<legend>{ __( 'Freezes', 'ohmylms' ) }</legend>
						<ToggleField
							label={ __(
								'Let freezes protect a missed day',
								'ohmylms'
							) }
							checked={ draft.freezes }
							onChange={ ( value ) => set( 'freezes', value ) }
						/>
						<NumberField
							label={ __(
								'Freezes a new learner starts with',
								'ohmylms'
							) }
							value={ draft.initial_freezes }
							min={ 0 }
							max={ 10 }
							error={ errors.initial_freezes }
							onChange={ ( value ) =>
								set( 'initial_freezes', value )
							}
						/>
						<NumberField
							label={ __(
								'Most freezes a learner can hold',
								'ohmylms'
							) }
							value={ draft.maximum_freezes }
							min={ 0 }
							max={ 10 }
							error={ errors.maximum_freezes }
							onChange={ ( value ) =>
								set( 'maximum_freezes', value )
							}
						/>
						<NumberField
							label={ __(
								'Learning days that earn a freeze back',
								'ohmylms'
							) }
							value={ draft.refill_days }
							min={ 1 }
							max={ 365 }
							error={ errors.refill_days }
							onChange={ ( value ) =>
								set( 'refill_days', value )
							}
						/>
					</fieldset>
					<fieldset>
						<legend>{ __( 'Milestones', 'ohmylms' ) }</legend>
						<p>
							{ __(
								'Each milestone is awarded once per learner. Bonus points need the points feature to be on.',
								'ohmylms'
							) }
						</p>
						{ badgeError && (
							<Notice status="warning" isDismissible={ false }>
								{ sprintf(
									/* translators: %s: error message. */
									__(
										'The badge list could not be loaded: %s',
										'ohmylms'
									),
									badgeError
								) }
							</Notice>
						) }
						{ errors.milestones && (
							<Notice status="error" isDismissible={ false }>
								{ errors.milestones }
							</Notice>
						) }
						{ draft.milestones.map( ( milestone, index ) => (
							<fieldset key={ index }>
								<legend>
									{ sprintf(
										/* translators: %d: milestone number. */
										__( 'Milestone %d', 'ohmylms' ),
										index + 1
									) }
								</legend>
								<NumberField
									label={ __( 'Days in a row', 'ohmylms' ) }
									value={ milestone.days }
									min={ 1 }
									max={ 10000 }
									error={
										errors[ `milestones.${ index }.days` ]
									}
									onChange={ ( days ) =>
										setMilestone( index, { days } )
									}
								/>
								<SelectField
									label={ __( 'Badge', 'ohmylms' ) }
									value={ milestone.badge }
									options={ badgeOptions( milestone.badge ) }
									onChange={ ( badge ) =>
										setMilestone( index, { badge } )
									}
								/>
								<NumberField
									label={ __( 'Bonus points', 'ohmylms' ) }
									value={ milestone.points }
									min={ 0 }
									max={ 100000 }
									error={
										errors[ `milestones.${ index }.points` ]
									}
									onChange={ ( points ) =>
										setMilestone( index, { points } )
									}
								/>
								<Button
									variant="link"
									isDestructive
									onClick={ () =>
										state.update( ( current ) => ( {
											...current,
											milestones:
												current.milestones.filter(
													( _item, position ) =>
														position !== index
												),
										} ) )
									}
								>
									{ sprintf(
										/* translators: %d: milestone number. */
										__( 'Remove milestone %d', 'ohmylms' ),
										index + 1
									) }
								</Button>
							</fieldset>
						) ) }
						<Button
							variant="secondary"
							disabled={
								draft.milestones.length >=
								ENGAGEMENT_LIMITS.maxMilestones
							}
							accessibleWhenDisabled
							onClick={ () =>
								state.update( ( current ) => ( {
									...current,
									milestones: [
										...current.milestones,
										{ days: null, badge: '', points: 0 },
									],
								} ) )
							}
						>
							{ __( 'Add milestone', 'ohmylms' ) }
						</Button>
					</fieldset>
				</>
			) }
		</SectionFrame>
	);
}
