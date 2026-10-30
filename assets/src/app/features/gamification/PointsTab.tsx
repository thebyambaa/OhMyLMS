import { __, sprintf } from '@wordpress/i18n';
import { NumberField, TextAreaField, TextField, ToggleField } from './fields';
import { SectionFrame } from './SectionFrame';
import type { SectionState } from './hooks';
import type { Limits, PointRuleDraft } from './types';

/**
 * Points tab: switch points on and set what each event is worth. Each rule can email the learner
 * when the points are awarded.
 *
 * @param props        Component props.
 * @param props.state  Points section state.
 * @param props.limits Server limits.
 */
export function PointsTab( {
	state,
	limits,
}: {
	state: SectionState< 'points' >;
	limits: Limits;
} ) {
	const { draft, errors } = state;
	function setRule(
		event: string,
		change: ( rule: PointRuleDraft ) => PointRuleDraft
	) {
		state.update( ( current ) => ( {
			...current,
			rules: current.rules.map( ( rule ) =>
				rule.event === event ? change( rule ) : rule
			),
		} ) );
	}
	return (
		<SectionFrame
			title={ __( 'Bonus points', 'ohmylms' ) }
			description={ __(
				'Reward learners with points for what they do. Points are whole numbers; levels, badges and course purchases use them.',
				'ohmylms'
			) }
			state={ state }
		>
			{ draft && (
				<>
					<ToggleField
						label={ __( 'Award bonus points', 'ohmylms' ) }
						checked={ draft.enabled }
						onChange={ ( enabled ) =>
							state.update( ( current ) => ( {
								...current,
								enabled,
							} ) )
						}
					/>
					{ draft.rules.map( ( rule ) => (
						<fieldset key={ rule.event } disabled={ state.saving }>
							<legend>{ rule.label }</legend>
							<p>{ rule.description }</p>
							<ToggleField
								label={ sprintf(
									/* translators: %s: event name. */
									__( 'Enable “%s”', 'ohmylms' ),
									rule.label
								) }
								checked={ rule.enabled }
								onChange={ ( enabled ) =>
									setRule( rule.event, ( current ) => ( {
										...current,
										enabled,
									} ) )
								}
							/>
							<NumberField
								label={ sprintf(
									/* translators: %s: event name. */
									__( 'Points for “%s”', 'ohmylms' ),
									rule.label
								) }
								value={ rule.points }
								min={ 0 }
								max={ limits.max_points }
								error={
									errors[ `rules.${ rule.event }.points` ]
								}
								onChange={ ( points ) =>
									setRule( rule.event, ( current ) => ( {
										...current,
										points,
									} ) )
								}
							/>
							{ rule.supports_threshold && (
								<NumberField
									label={ sprintf(
										/* translators: %s: event name. */
										__(
											'Minimum percentage for “%s”',
											'ohmylms'
										),
										rule.label
									) }
									value={ rule.threshold }
									min={ 0 }
									max={ 100 }
									error={
										errors[
											`rules.${ rule.event }.threshold`
										]
									}
									onChange={ ( threshold ) =>
										setRule( rule.event, ( current ) => ( {
											...current,
											threshold,
										} ) )
									}
								/>
							) }
							<ToggleField
								label={ sprintf(
									/* translators: %s: event name. */
									__(
										'Email the learner for “%s”',
										'ohmylms'
									),
									rule.label
								) }
								checked={ rule.email.enabled }
								onChange={ ( enabled ) =>
									setRule( rule.event, ( current ) => ( {
										...current,
										email: { ...current.email, enabled },
									} ) )
								}
							/>
							{ rule.email.enabled && (
								<>
									<TextField
										label={ sprintf(
											/* translators: %s: event name. */
											__(
												'Email subject for “%s”',
												'ohmylms'
											),
											rule.label
										) }
										value={ rule.email.subject }
										maxLength={ 150 }
										error={
											errors[
												`rules.${ rule.event }.email.subject`
											]
										}
										onChange={ ( subject ) =>
											setRule(
												rule.event,
												( current ) => ( {
													...current,
													email: {
														...current.email,
														subject,
													},
												} )
											)
										}
									/>
									<TextAreaField
										label={ sprintf(
											/* translators: %s: event name. */
											__(
												'Email message for “%s”',
												'ohmylms'
											),
											rule.label
										) }
										value={ rule.email.body }
										error={
											errors[
												`rules.${ rule.event }.email.body`
											]
										}
										onChange={ ( body ) =>
											setRule(
												rule.event,
												( current ) => ( {
													...current,
													email: {
														...current.email,
														body,
													},
												} )
											)
										}
									/>
								</>
							) }
						</fieldset>
					) ) }
				</>
			) }
		</SectionFrame>
	);
}
