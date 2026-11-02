import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	AT_RISK_RANGES,
	getAtRiskSettings,
	saveAtRiskSettings,
	validateAtRisk,
	type AtRiskNumber,
	type AtRiskToggle,
	type AtRiskValues,
} from './atRisk';
import { isDirty } from './designSettings';
import { NumberField, ToggleField } from './fields';
import { FormFrame } from './FormFrame';
import { useResource } from './useResource';

interface RuleSection {
	title: string;
	toggle: AtRiskToggle;
	toggleLabel: string;
	fields: readonly { name: AtRiskNumber; label: string; help?: string }[];
}

/**
 * At-risk learners tab (`/settings/at-risk`): the rules that flag learners on the dashboard, the level
 * thresholds and the nudge limits.
 *
 * @param props         Component props.
 * @param props.active  Whether the tab is selected (loads on first selection).
 * @param props.onDirty Reports unsaved edits.
 */
export function AtRiskForm( {
	active,
	onDirty,
}: {
	active: boolean;
	onDirty: ( dirty: boolean ) => void;
} ) {
	const state = useResource< AtRiskValues, AtRiskValues >(
		{
			load: async ( signal ) =>
				( await getAtRiskSettings( signal ) ).values,
			toDraft: ( stored ) => ( { ...stored } ),
			isDirty,
			validate: validateAtRisk,
			save: async ( stored, draft, signal ) => {
				const result = await saveAtRiskSettings(
					stored,
					draft,
					signal
				);
				return { snapshot: result.values, changed: result.changed };
			},
		},
		active
	);
	useEffect( () => onDirty( state.dirty ), [ state.dirty, onDirty ] );
	const draft = state.draft;
	const set = < K extends keyof AtRiskValues >(
		key: K,
		value: AtRiskValues[ K ]
	) => state.update( ( current ) => ( { ...current, [ key ]: value } ), key );

	const sections: readonly RuleSection[] = [
		{
			title: __( 'Inactivity', 'ohmylms' ),
			toggle: 'inactive_enabled',
			toggleLabel: __(
				'Flag learners who have not been active',
				'ohmylms'
			),
			fields: [
				{
					name: 'inactive_days',
					label: __( 'Days without activity', 'ohmylms' ),
					help: __(
						'Lessons, quizzes, assignments and practice count as activity.',
						'ohmylms'
					),
				},
				{
					name: 'inactive_severe_days',
					label: __( 'High risk after (days)', 'ohmylms' ),
				},
			],
		},
		{
			title: __( 'Falling mastery', 'ohmylms' ),
			toggle: 'mastery_enabled',
			toggleLabel: __(
				'Flag learners whose skill mastery is dropping',
				'ohmylms'
			),
			fields: [
				{
					name: 'mastery_drop',
					label: __( 'Points lost on a skill', 'ohmylms' ),
					help: __(
						'On the 0–100 mastery score of any skill the learner practised.',
						'ohmylms'
					),
				},
				{
					name: 'mastery_window_days',
					label: __( 'Within (days)', 'ohmylms' ),
				},
				{
					name: 'mastery_severe_drop',
					label: __( 'High risk at (points)', 'ohmylms' ),
				},
			],
		},
		{
			title: __( 'Overdue work', 'ohmylms' ),
			toggle: 'overdue_enabled',
			toggleLabel: __(
				'Flag learners with overdue class work',
				'ohmylms'
			),
			fields: [
				{
					name: 'overdue_items',
					label: __( 'Overdue items', 'ohmylms' ),
					help: __(
						'Class assignments past their due date and not completed, due within the dashboard period.',
						'ohmylms'
					),
				},
				{
					name: 'overdue_severe_items',
					label: __( 'High risk at (items)', 'ohmylms' ),
				},
			],
		},
	];

	const number = ( name: AtRiskNumber, label: string, help?: string ) => (
		<NumberField
			key={ name }
			label={ label }
			help={ help }
			value={ draft ? draft[ name ] : Number.NaN }
			min={ AT_RISK_RANGES[ name ][ 0 ] }
			max={ AT_RISK_RANGES[ name ][ 1 ] }
			step={ 1 }
			error={ state.errors[ name ] }
			onChange={ ( value ) => set( name, value ) }
		/>
	);

	return (
		<FormFrame
			title={ __( 'At-risk learners', 'ohmylms' ) }
			description={ __(
				'Rules that flag learners in the dashboard’s At-risk learners widget. A learner is high risk when two or more rules match or one reaches its high-risk threshold, and on watch when one rule matches.',
				'ohmylms'
			) }
			state={ state }
		>
			{ draft && (
				<>
					{ sections.map( ( section ) => (
						<fieldset
							key={ section.toggle }
							className="ohmylms-settings-fieldset"
						>
							<legend>
								<strong>{ section.title }</strong>
							</legend>
							<ToggleField
								label={ section.toggleLabel }
								checked={ draft[ section.toggle ] }
								onChange={ ( value ) =>
									set( section.toggle, value )
								}
							/>
							{ draft[ section.toggle ] &&
								section.fields.map( ( field ) =>
									number(
										field.name,
										field.label,
										field.help
									)
								) }
						</fieldset>
					) ) }
					<fieldset className="ohmylms-settings-fieldset">
						<legend>
							<strong>{ __( 'Nudges', 'ohmylms' ) }</strong>
						</legend>
						<p>
							{ __(
								'A nudge sends the “Nudge an At-Risk Learner” email (edit it under Emails). Each sender can nudge a learner once every 72 hours.',
								'ohmylms'
							) }
						</p>
						{ number(
							'nudge_daily_cap',
							__( 'Nudges per sender per day', 'ohmylms' )
						) }
						<ToggleField
							label={ __(
								'Also send a copy to the learner’s guardians',
								'ohmylms'
							) }
							help={ __(
								'Active guardians linked in Account Hub, one email each.',
								'ohmylms'
							) }
							checked={ draft.nudge_guardians }
							onChange={ ( value ) =>
								set( 'nudge_guardians', value )
							}
						/>
					</fieldset>
				</>
			) }
		</FormFrame>
	);
}
