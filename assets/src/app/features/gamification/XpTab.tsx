import { __ } from '@wordpress/i18n';
import { NumberField, SelectField, TextField, ToggleField } from './fields';
import { SectionFrame } from './SectionFrame';
import type { EngagementState } from './useEngagement';
import type { ScoreBandDraft, ScoreDraft, XpDraft } from './types';

const BAND_LABELS = [
	__( 'Getting started (0–69)', 'ohmylms' ),
	__( 'Practising (70–79)', 'ohmylms' ),
	__( 'Nearly there (80–89)', 'ohmylms' ),
	__( 'Mastering (90–100)', 'ohmylms' ),
];

/**
 * XP and mastery tab: XP and the daily goal, the 0-100 mastery score, and the model that decides
 * skill levels. Each part is saved on its own.
 *
 * @param props         Component props.
 * @param props.xp      XP group state.
 * @param props.score   Mastery score group state.
 * @param props.mastery Mastery model group state.
 */
export function XpTab( {
	xp,
	score,
	mastery,
}: {
	xp: EngagementState< 'xp' >;
	score: EngagementState< 'score' >;
	mastery: EngagementState< 'mastery' >;
} ) {
	const setXp = < K extends keyof XpDraft >( key: K, value: XpDraft[ K ] ) =>
		xp.update( ( current ) => ( { ...current, [ key ]: value } ) );
	const setScore = < K extends keyof ScoreDraft >(
		key: K,
		value: ScoreDraft[ K ]
	) => score.update( ( current ) => ( { ...current, [ key ]: value } ) );
	const setBand = ( index: number, change: Partial< ScoreBandDraft > ) =>
		score.update( ( current ) => ( {
			...current,
			bands: current.bands.map( ( band, position ) =>
				position === index ? { ...band, ...change } : band
			),
		} ) );
	const xpNumber = (
		key:
			| 'lesson'
			| 'perfect'
			| 'challenge'
			| 'min_answered'
			| 'default_goal'
			| 'goal_bonus'
			| 'daily_cap',
		label: string,
		min: number,
		max: number,
		help?: string
	) =>
		xp.draft && (
			<NumberField
				label={ label }
				help={ help }
				value={ xp.draft[ key ] }
				min={ min }
				max={ max }
				error={ xp.errors[ key ] }
				onChange={ ( value ) => setXp( key, value ) }
			/>
		);
	const policies = mastery.saved?.policies ?? [];
	const policy = mastery.draft
		? policies.find( ( item ) => item.id === mastery.draft?.policy )
		: undefined;

	return (
		<>
			<SectionFrame
				title={ __( 'XP and daily goal', 'ohmylms' ) }
				description={ __(
					'XP rewards effort and is never spent; points stay the spendable currency. Replayed questions never earn XP.',
					'ohmylms'
				) }
				state={ xp }
			>
				{ xp.draft && (
					<>
						<ToggleField
							label={ __( 'Award XP', 'ohmylms' ) }
							checked={ xp.draft.enable }
							onChange={ ( value ) => setXp( 'enable', value ) }
						/>
						{ xpNumber(
							'lesson',
							__( 'XP per completed practice lesson', 'ohmylms' ),
							0,
							1000
						) }
						{ xpNumber(
							'perfect',
							__(
								'Bonus XP for a lesson without mistakes',
								'ohmylms'
							),
							0,
							1000
						) }
						{ xpNumber(
							'challenge',
							__(
								'XP per first-try answer in the challenge zone (score 90+)',
								'ohmylms'
							),
							0,
							100
						) }
						{ xpNumber(
							'min_answered',
							__(
								'Answers a lesson needs to earn XP',
								'ohmylms'
							),
							1,
							30
						) }
						{ xpNumber(
							'daily_cap',
							__( 'Most XP per learner per day', 'ohmylms' ),
							10,
							100000
						) }
						<TextField
							label={ __( 'Daily goal choices (XP)', 'ohmylms' ) }
							help={ __(
								'One to six numbers separated by commas, for example 10, 20, 30.',
								'ohmylms'
							) }
							value={ xp.draft.goals }
							error={ xp.errors.goals }
							onChange={ ( value ) => setXp( 'goals', value ) }
						/>
						{ xpNumber(
							'default_goal',
							__( 'Default daily goal', 'ohmylms' ),
							1,
							10000,
							__( 'Must be one of the goal choices.', 'ohmylms' )
						) }
						{ xpNumber(
							'goal_bonus',
							__(
								'Bonus points for reaching the goal',
								'ohmylms'
							),
							0,
							1000,
							__(
								'Bonus points need the points feature to be on.',
								'ohmylms'
							)
						) }
					</>
				) }
			</SectionFrame>

			<SectionFrame
				title={ __( 'Mastery score', 'ohmylms' ) }
				description={ __(
					'Every skill gets a score from 0 to 100 built from first-try answers. It never falls below the start of its band, so one slip cannot undo progress.',
					'ohmylms'
				) }
				state={ score }
			>
				{ score.draft && (
					<>
						<ToggleField
							label={ __(
								'Compute the mastery score',
								'ohmylms'
							) }
							checked={ score.draft.enable }
							onChange={ ( value ) =>
								setScore( 'enable', value )
							}
						/>
						{ score.errors.bands && (
							<p role="alert">{ score.errors.bands }</p>
						) }
						{ score.draft.bands.map( ( band, index ) => (
							<fieldset key={ index }>
								<legend>
									{ BAND_LABELS[ index ] ??
										String( index + 1 ) }
								</legend>
								<NumberField
									label={ __(
										'Gain per correct answer',
										'ohmylms'
									) }
									value={ band.gain }
									min={ 0 }
									max={ 50 }
									step={ 0.5 }
									error={
										score.errors[ `bands.${ index }.gain` ]
									}
									onChange={ ( gain ) =>
										setBand( index, { gain } )
									}
								/>
								<NumberField
									label={ __(
										'Loss per mistake',
										'ohmylms'
									) }
									value={ band.loss }
									min={ 0 }
									max={ 50 }
									step={ 0.5 }
									error={
										score.errors[ `bands.${ index }.loss` ]
									}
									onChange={ ( loss ) =>
										setBand( index, { loss } )
									}
								/>
							</fieldset>
						) ) }
						<NumberField
							label={ __(
								'Share of the gain kept after a hint (0–1)',
								'ohmylms'
							) }
							value={ score.draft.hint_gain }
							min={ 0 }
							max={ 1 }
							step={ 0.05 }
							error={ score.errors.hint_gain }
							onChange={ ( value ) =>
								setScore( 'hint_gain', value )
							}
						/>
						<NumberField
							label={ __(
								'Score that unlocks the next skill',
								'ohmylms'
							) }
							value={ score.draft.unlock }
							min={ 50 }
							max={ 100 }
							error={ score.errors.unlock }
							onChange={ ( value ) =>
								setScore( 'unlock', value )
							}
						/>
						<NumberField
							label={ __(
								'Idle days before the score fades',
								'ohmylms'
							) }
							value={ score.draft.decay_grace_days }
							min={ 0 }
							max={ 365 }
							error={ score.errors.decay_grace_days }
							onChange={ ( value ) =>
								setScore( 'decay_grace_days', value )
							}
						/>
						<NumberField
							label={ __(
								'Points lost per idle week (never below 90)',
								'ohmylms'
							) }
							value={ score.draft.decay_per_week }
							min={ 0 }
							max={ 20 }
							step={ 0.5 }
							error={ score.errors.decay_per_week }
							onChange={ ( value ) =>
								setScore( 'decay_per_week', value )
							}
						/>
					</>
				) }
			</SectionFrame>

			<SectionFrame
				title={ __( 'Mastery model', 'ohmylms' ) }
				description={ __(
					'Choose how skill levels are decided. Every model reads the same answers, so levels are rebuilt in the background when you switch and no answer is lost.',
					'ohmylms'
				) }
				state={ mastery }
			>
				{ mastery.draft && mastery.saved && (
					<>
						<SelectField
							label={ __(
								'How skill levels are decided',
								'ohmylms'
							) }
							help={ policy?.description }
							value={ mastery.draft.policy }
							options={ policies.map( ( item ) => ( {
								value: item.id,
								label: item.label,
							} ) ) }
							onChange={ ( value ) =>
								mastery.update( ( current ) => ( {
									...current,
									policy: value,
								} ) )
							}
						/>
						<fieldset>
							<legend>
								{ __(
									'Answers that count toward skills',
									'ohmylms'
								) }
							</legend>
							{ mastery.saved.surface_options.map(
								( surface ) => (
									<ToggleField
										key={ surface.id }
										label={ surface.label }
										checked={
											mastery.draft?.surfaces[
												surface.id
											] ?? surface.counts
										}
										onChange={ ( value ) =>
											mastery.update( ( current ) => ( {
												...current,
												surfaces: {
													...current.surfaces,
													[ surface.id ]: value,
												},
											} ) )
										}
									/>
								)
							) }
						</fieldset>
						<ToggleField
							label={ __(
								'Show the mastery score beside the level',
								'ohmylms'
							) }
							checked={ mastery.draft.show_score }
							onChange={ ( value ) =>
								mastery.update( ( current ) => ( {
									...current,
									show_score: value,
								} ) )
							}
						/>
						<ToggleField
							label={ __( 'Write AI study notes', 'ohmylms' ) }
							help={
								mastery.saved.ai_ready
									? __(
											'The AI explains skills; it never changes a level or a score. Only skill names and numbers are sent.',
											'ohmylms'
										)
									: __(
											'Connect an AI provider under AI settings first.',
											'ohmylms'
										)
							}
							disabled={
								! mastery.saved.ai_ready &&
								! mastery.draft.ai_insights
							}
							checked={ mastery.draft.ai_insights }
							onChange={ ( value ) =>
								mastery.update( ( current ) => ( {
									...current,
									ai_insights: value,
								} ) )
							}
						/>
					</>
				) }
			</SectionFrame>
		</>
	);
}
