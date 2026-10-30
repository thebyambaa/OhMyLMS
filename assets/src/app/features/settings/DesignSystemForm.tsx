import { Button } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import {
	applyPreset,
	BLACK,
	contrastOn,
	DENSITIES,
	designChanged,
	designDraft,
	EDITABLE_COLOR_KEYS,
	getDesign,
	matchingPreset,
	MAX_RADIUS,
	PILL,
	resetDesign,
	saveDesign,
	validateDesign,
	type ColorKey,
	type DesignSnapshot,
	type DesignValues,
	type Density,
	WHITE,
	WORDPRESS_PRESET,
} from './designSystem';
import { ColorField, NumberField, SelectField, ToggleField } from './fields';
import { FormFrame } from './FormFrame';
import { describeFailure } from './model';
import { useResource } from './useResource';

const COLOR_LABELS: Record< ColorKey, string > = {
	primary: __( 'Primary', 'ohmylms' ),
	secondary: __( 'Secondary (button outline)', 'ohmylms' ),
	success: __( 'Success', 'ohmylms' ),
	danger: __( 'Danger', 'ohmylms' ),
	warning: __( 'Warning', 'ohmylms' ),
	link: __( 'Link', 'ohmylms' ),
	heading: __( 'Heading', 'ohmylms' ),
	text: __( 'Body text', 'ohmylms' ),
	muted: __( 'Muted text', 'ohmylms' ),
	info: __( 'Info', 'ohmylms' ),
	border: __( 'Border', 'ohmylms' ),
	surface: __( 'Surface', 'ohmylms' ),
	background: __( 'Background', 'ohmylms' ),
};

const DENSITY_LABELS: Record< Density, string > = {
	compact: __( 'Compact', 'ohmylms' ),
	comfortable: __( 'Comfortable', 'ohmylms' ),
	spacious: __( 'Spacious', 'ohmylms' ),
};

/**
 * Design system tab: the look of every OhMyLMS admin screen (`/design-system`).
 *
 * @param props         Component props.
 * @param props.active  Whether the tab is selected (loads on first selection).
 * @param props.onDirty Reports unsaved edits.
 */
export function DesignSystemForm( {
	active,
	onDirty,
}: {
	active: boolean;
	onDirty: ( dirty: boolean ) => void;
} ) {
	const state = useResource< DesignSnapshot, DesignValues >(
		{
			load: getDesign,
			toDraft: designDraft,
			isDirty: designChanged,
			validate: validateDesign,
			save: async ( stored, draft, signal ) => ( {
				snapshot: await saveDesign(
					{
						...draft,
						preset: matchingPreset( draft, stored.presets ),
					},
					signal
				),
				changed: [ 'design-system' ],
			} ),
		},
		active
	);
	useEffect( () => onDirty( state.dirty ), [ state.dirty, onDirty ] );

	const [ confirming, setConfirming ] = useState( false );
	const [ resetting, setResetting ] = useState( false );
	const [ outcome, setOutcome ] = useState< string | null >( null );
	const [ problem, setProblem ] = useState< string | null >( null );
	const mounted = useRef( true );
	useEffect( () => {
		mounted.current = true;
		return () => {
			mounted.current = false;
		};
	}, [] );

	async function reset() {
		setResetting( true );
		setOutcome( null );
		setProblem( null );
		try {
			const snapshot = await resetDesign();
			if ( mounted.current ) {
				state.adopt( snapshot );
				setOutcome(
					__( 'The default design was restored.', 'ohmylms' )
				);
			}
		} catch ( failure ) {
			if ( mounted.current ) {
				setProblem(
					describeFailure( normalizeApiError( failure ) ).message
				);
			}
		} finally {
			if ( mounted.current ) {
				setResetting( false );
				setConfirming( false );
			}
		}
	}

	const stored = state.snapshot;
	const draft = state.draft;
	const set = ( change: Partial< DesignValues >, field: string ) =>
		state.update( ( current ) => ( { ...current, ...change } ), field );

	let contrastNote = '';
	if ( stored && draft ) {
		const primary = draft.colors.primary;
		const preview = contrastOn( primary );
		const savedPrimary = primary.toLowerCase() === stored.colors.primary;
		const text = savedPrimary ? stored.on_primary : preview.text;
		const passes = savedPrimary ? stored.primary_passes : preview.passes;
		const textName =
			WHITE === text
				? __( 'white', 'ohmylms' )
				: __( 'black', 'ohmylms' );
		contrastNote = sprintf(
			/* translators: 1: white or black, 2: contrast ratio, 3: verdict. */
			__(
				'Button text on the primary colour is %1$s (contrast %2$s:1). %3$s',
				'ohmylms'
			),
			textName,
			preview.ratio.toFixed( 1 ),
			passes
				? __( 'Meets WCAG AA.', 'ohmylms' )
				: __(
						'Below WCAG AA (4.5:1): choose a darker or lighter primary colour.',
						'ohmylms'
					)
		);
		if ( BLACK === text ) {
			contrastNote += ` ${ __(
				'White text would be too faint, so buttons use black text.',
				'ohmylms'
			) }`;
		}
	}

	return (
		<FormFrame
			title={ __( 'Design system', 'ohmylms' ) }
			description={ __(
				'Colours, button roundness and spacing of OhMyLMS screens. The WordPress preset keeps admin screens in each user’s WordPress admin colour scheme; the other presets brand admin screens too. The palette always brands learner pages.',
				'ohmylms'
			) }
			state={ state }
			extra={
				<>
					{ ' ' }
					{ confirming ? (
						<>
							<Button
								variant="secondary"
								isDestructive
								onClick={ () => void reset() }
								isBusy={ resetting }
								disabled={ resetting }
								accessibleWhenDisabled
							>
								{ __( 'Confirm reset', 'ohmylms' ) }
							</Button>{ ' ' }
							<Button
								variant="tertiary"
								onClick={ () => setConfirming( false ) }
							>
								{ __( 'Keep my design', 'ohmylms' ) }
							</Button>
						</>
					) : (
						<Button
							variant="secondary"
							onClick={ () => setConfirming( true ) }
							disabled={ state.saving }
							accessibleWhenDisabled
						>
							{ __( 'Reset to defaults', 'ohmylms' ) }
						</Button>
					) }
					{ outcome && <span role="status"> { outcome }</span> }
					{ problem && <span role="alert"> { problem }</span> }
				</>
			}
		>
			{ stored && draft && (
				<>
					<SelectField
						label={ __( 'Preset', 'ohmylms' ) }
						value={ matchingPreset( draft, stored.presets ) }
						options={ [
							...stored.presets.map( ( preset ) => ( {
								value: preset.preset,
								label: preset.label,
							} ) ),
							{
								value: 'custom',
								label: __( 'Custom', 'ohmylms' ),
							},
						] }
						help={
							WORDPRESS_PRESET ===
							matchingPreset( draft, stored.presets )
								? sprintf(
										/* translators: 1: admin colour scheme slug, 2: its theme colour. */
										__(
											'Admin screens follow each user’s WordPress admin colour scheme (yours: %1$s, %2$s), set under Users › Profile. The colours below brand learner pages only.',
											'ohmylms'
										),
										stored.admin_scheme,
										stored.admin_colors.primary
									)
								: __(
										'Choosing a preset replaces the colours, roundness and density below.',
										'ohmylms'
									)
						}
						error={ state.errors.preset }
						onChange={ ( name ) =>
							state.update(
								( current ) =>
									applyPreset(
										current,
										stored.presets,
										name
									),
								'preset'
							)
						}
					/>
					<fieldset>
						<legend>{ __( 'Colours', 'ohmylms' ) }</legend>
						{ EDITABLE_COLOR_KEYS.map( ( key ) => (
							<ColorField
								key={ key }
								label={ COLOR_LABELS[ key ] }
								pickerLabel={ sprintf(
									/* translators: %s: colour name. */
									__( '%s colour picker', 'ohmylms' ),
									COLOR_LABELS[ key ]
								) }
								value={ draft.colors[ key ] }
								error={ state.errors[ `colors.${ key }` ] }
								onChange={ ( value ) =>
									state.update(
										( current ) => ( {
											...current,
											colors: {
												...current.colors,
												[ key ]: value,
											},
										} ),
										`colors.${ key }`
									)
								}
							/>
						) ) }
						<p aria-live="polite">
							<span
								aria-hidden="true"
								className="ohmylms-ds-swatch"
								style={ {
									background: draft.colors.primary,
									color: contrastOn( draft.colors.primary )
										.text,
								} }
							>
								{ __( 'Button', 'ohmylms' ) }
							</span>
							{ contrastNote }
						</p>
					</fieldset>
					<ToggleField
						label={ __( 'Pill-shaped buttons', 'ohmylms' ) }
						checked={ PILL === draft.button_radius }
						onChange={ ( pill ) =>
							set(
								{ button_radius: pill ? PILL : 8 },
								'button_radius'
							)
						}
					/>
					<NumberField
						label={ __( 'Button corner radius (px)', 'ohmylms' ) }
						help={ __( 'From 0 (square) to 24.', 'ohmylms' ) }
						value={
							PILL === draft.button_radius
								? Number.NaN
								: draft.button_radius
						}
						min={ 0 }
						max={ MAX_RADIUS }
						step={ 1 }
						disabled={ PILL === draft.button_radius }
						error={ state.errors.button_radius }
						onChange={ ( value ) =>
							set( { button_radius: value }, 'button_radius' )
						}
					/>
					<SelectField
						label={ __( 'Density', 'ohmylms' ) }
						value={ draft.density }
						options={ DENSITIES.map( ( density ) => ( {
							value: density,
							label: DENSITY_LABELS[ density ],
						} ) ) }
						error={ state.errors.density }
						onChange={ ( value ) =>
							set(
								{
									density:
										DENSITIES.find(
											( item ) => item === value
										) ?? 'comfortable',
								},
								'density'
							)
						}
					/>
				</>
			) }
		</FormFrame>
	);
}
