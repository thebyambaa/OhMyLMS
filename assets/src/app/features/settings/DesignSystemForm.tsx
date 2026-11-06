import type { CSSProperties } from 'react';
import { Button } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { MediaPicker } from '../../components/MediaPicker';
import { normalizeApiError } from '../../lib/api';
import type { MediaImage } from '../../lib/media';
import {
	applyPreset,
	BLACK,
	contrastOn,
	contrastOnWhite,
	DENSITIES,
	designChanged,
	designDraft,
	EDITABLE_COLOR_KEYS,
	fontStack,
	getDesign,
	matchingPreset,
	MAX_RADIUS,
	PILL,
	resetDesign,
	saveDesign,
	saveSchoolBrand,
	validateDesign,
	type ColorKey,
	type DesignSnapshot,
	type DesignValues,
	type Density,
	type MascotDrawing,
	type MascotKind,
	type MascotPlacement,
	type MascotValues,
	type SchoolBrand,
	WHITE,
	WORDPRESS_PRESET,
} from './designSystem';
import {
	ColorField,
	NumberField,
	SelectField,
	TextField,
	ToggleField,
} from './fields';
import { FormFrame } from './FormFrame';
import { describeFailure, invalidResponse } from './model';
import type { FieldErrors } from './types';
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

/** Colours that are read as text on a white card, so they need WCAG AA (4.5:1) against white. */
const TEXT_ON_WHITE: readonly ColorKey[] = [
	'text',
	'heading',
	'primary',
	'link',
	'danger',
	'muted',
];

const FONT_FALLBACK = [
	'inherit',
	'system',
	'Inter',
	'Roboto',
	'Open Sans',
	'Noto Sans',
	'Montserrat',
	'Nunito',
	'PT Sans',
	'Fira Sans',
	'Rubik',
];

const PLACEMENT_LABELS: Record< MascotPlacement, string > = {
	profile: __( '/profile header', 'ohmylms' ),
	empty: __( 'Empty states', 'ohmylms' ),
	celebrate: __( 'Celebrations (level-up, badge earned)', 'ohmylms' ),
	quiz: __( 'Quiz result screen', 'ohmylms' ),
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
				<div className="ohmylms-brand-layout">
					<div>
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
								<div key={ key }>
									<ColorField
										label={ COLOR_LABELS[ key ] }
										pickerLabel={ sprintf(
											/* translators: %s: colour name. */
											__( '%s colour picker', 'ohmylms' ),
											COLOR_LABELS[ key ]
										) }
										value={ draft.colors[ key ] }
										error={
											state.errors[ `colors.${ key }` ]
										}
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
									{ TEXT_ON_WHITE.includes( key ) &&
										contrastOnWhite( draft.colors[ key ] ) <
											4.5 && (
											<span
												className="ohmylms-ds-contrast"
												role="status"
											>
												{ sprintf(
													/* translators: %s: contrast ratio, for example 2.10. */
													__(
														'%s:1 — Fails WCAG AA on white',
														'ohmylms'
													),
													contrastOnWhite(
														draft.colors[ key ]
													).toFixed( 2 )
												) }
											</span>
										) }
								</div>
							) ) }
							<p aria-live="polite">
								<span
									aria-hidden="true"
									className="ohmylms-ds-swatch"
									style={ {
										background: draft.colors.primary,
										color: contrastOn(
											draft.colors.primary
										).text,
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
						<label htmlFor="ohmylms-radius">
							{ sprintf(
								/* translators: %s: radius in pixels, or the word pill. */
								__( 'Button radius: %s', 'ohmylms' ),
								PILL === draft.button_radius
									? __( 'pill', 'ohmylms' )
									: String( draft.button_radius )
							) }
						</label>
						<input
							id="ohmylms-radius"
							type="range"
							min={ 0 }
							max={ MAX_RADIUS }
							step={ 1 }
							disabled={ PILL === draft.button_radius }
							value={
								PILL === draft.button_radius
									? MAX_RADIUS
									: draft.button_radius
							}
							onChange={ ( event ) =>
								set(
									{
										button_radius: Number.parseInt(
											event.target.value,
											10
										),
									},
									'button_radius'
								)
							}
						/>
						<NumberField
							label={ __(
								'Button corner radius (px)',
								'ohmylms'
							) }
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
						<SelectField
							label={ __( 'Learner font', 'ohmylms' ) }
							value={ draft.font }
							options={ ( stored.fonts.length
								? stored.fonts
								: FONT_FALLBACK
							).map( ( font ) => ( {
								value: font,
								label: fontLabel( font ),
							} ) ) }
							help={ __(
								'Every font in this list can show Cyrillic (Монгол). Sample: Сайн уу, суралцагч!',
								'ohmylms'
							) }
							error={ state.errors.font }
							onChange={ ( value ) =>
								set( { font: value }, 'font' )
							}
						/>
						<MascotFields
							draft={ draft }
							drawings={ stored.mascots }
							errors={ state.errors }
							onChange={ ( mascot, field ) =>
								state.update(
									( current ) => ( { ...current, mascot } ),
									field
								)
							}
						/>
						<SchoolFields
							schools={ stored.schools }
							primary={ draft.colors.primary }
							onSaved={ ( snapshot ) => state.adopt( snapshot ) }
						/>
					</div>
					<BrandPreview draft={ draft } />
				</div>
			) }
		</FormFrame>
	);
}

function fontLabel( font: string ): string {
	if ( 'inherit' === font ) {
		return __( 'Theme font', 'ohmylms' );
	}
	if ( 'system' === font ) {
		return __( 'System font', 'ohmylms' );
	}
	return font;
}

function MascotArt( { kind }: { kind: string } ) {
	const shared = {
		viewBox: '0 0 64 64',
		className: 'ohmylms-mascot-svg',
		'aria-hidden': true as const,
		focusable: false as const,
	};
	if ( 'owl' === kind ) {
		return (
			<svg { ...shared }>
				<ellipse cx="32" cy="38" rx="20" ry="22" fill="currentColor" />
				<circle cx="24" cy="32" r="8" fill="var(--oml-surface)" />
				<circle cx="40" cy="32" r="8" fill="var(--oml-surface)" />
				<circle cx="24" cy="33" r="4" fill="var(--oml-heading)" />
				<circle cx="40" cy="33" r="4" fill="var(--oml-heading)" />
			</svg>
		);
	}
	if ( 'robot' === kind ) {
		return (
			<svg { ...shared }>
				<rect
					x="12"
					y="14"
					width="40"
					height="32"
					rx="8"
					fill="currentColor"
				/>
				<rect
					x="18"
					y="22"
					width="28"
					height="14"
					rx="5"
					fill="var(--oml-surface)"
				/>
				<circle cx="26" cy="29" r="3" fill="var(--oml-heading)" />
				<circle cx="38" cy="29" r="3" fill="var(--oml-heading)" />
			</svg>
		);
	}
	if ( 'horse' === kind ) {
		return (
			<svg { ...shared }>
				<path
					d="M20 58V34c0-14 8-24 22-26l4 8-4 4 6 14-6 4-8-6-4 26z"
					fill="currentColor"
				/>
			</svg>
		);
	}
	if ( 'star' === kind ) {
		return (
			<svg { ...shared }>
				<path
					d="M32 4l8 18 20 2-15 13 5 20-18-11-18 11 5-20L4 24l20-2z"
					fill="currentColor"
				/>
			</svg>
		);
	}
	return null;
}

function MascotFields( {
	draft,
	drawings,
	errors,
	onChange,
}: {
	draft: DesignValues;
	drawings: readonly MascotDrawing[];
	errors: FieldErrors;
	onChange: ( mascot: MascotValues, field: string ) => void;
} ) {
	const mascot = draft.mascot;
	const kinds =
		drawings.length > 0
			? drawings
			: ( [ 'owl', 'robot', 'horse', 'star' ] as const ).map(
					( kind ) => ( {
						kind,
						label: kind,
						svg: '',
					} )
				);
	const set = ( change: Partial< MascotValues >, field: string ) =>
		onChange( { ...mascot, ...change }, field );
	return (
		<fieldset className="ohmylms-mascot-fields">
			<legend>{ __( 'Mascot', 'ohmylms' ) }</legend>
			<div
				className="ohmylms-mascot-choices"
				role="group"
				aria-label={ __( 'Choose mascot', 'ohmylms' ) }
			>
				{ kinds.map( ( drawing ) => (
					<button
						key={ drawing.kind }
						type="button"
						className="ohmylms-mascot-choice"
						aria-pressed={ mascot.kind === drawing.kind }
						aria-label={ drawing.label }
						style={ { color: draft.colors.primary } }
						onClick={ () =>
							set(
								{
									kind: drawing.kind as MascotKind,
									attachment_id: 0,
									attachment_url: '',
								},
								'mascot.kind'
							)
						}
					>
						<MascotArt kind={ drawing.kind } />
					</button>
				) ) }
				<button
					type="button"
					className="ohmylms-mascot-choice"
					aria-pressed={ 'none' === mascot.kind }
					onClick={ () =>
						set(
							{
								kind: 'none',
								attachment_id: 0,
								attachment_url: '',
							},
							'mascot.kind'
						)
					}
				>
					{ __( 'None', 'ohmylms' ) }
				</button>
			</div>
			<MediaPicker
				label={ __( 'Mascot image', 'ohmylms' ) }
				chooseText={ __( 'Upload your own', 'ohmylms' ) }
				chooseLabel={ __( 'Choose a mascot image', 'ohmylms' ) }
				removeText={ __( 'Remove upload', 'ohmylms' ) }
				value={
					'upload' === mascot.kind && mascot.attachment_id
						? {
								id: mascot.attachment_id,
								url: mascot.attachment_url,
							}
						: null
				}
				previewSize={ 64 }
				invalid={ invalidResponse }
				onChange={ ( image: MediaImage ) =>
					set(
						{
							kind: 'upload',
							attachment_id: image.id,
							attachment_url: image.thumbnail_url || image.url,
						},
						'mascot.attachment_id'
					)
				}
				onRemove={ () =>
					set(
						{
							kind: 'none',
							attachment_id: 0,
							attachment_url: '',
						},
						'mascot.attachment_id'
					)
				}
			/>
			{ errors[ 'mascot.attachment_id' ] && (
				<p role="alert">{ errors[ 'mascot.attachment_id' ] }</p>
			) }
			<TextField
				label={ __( 'Mascot name', 'ohmylms' ) }
				value={ mascot.name }
				maxLength={ 40 }
				error={ errors[ 'mascot.name' ] }
				onChange={ ( value ) => set( { name: value }, 'mascot.name' ) }
			/>
			<TextField
				label={ __( 'Greeting', 'ohmylms' ) }
				value={ mascot.greeting }
				maxLength={ 160 }
				error={ errors[ 'mascot.greeting' ] }
				onChange={ ( value ) =>
					set( { greeting: value }, 'mascot.greeting' )
				}
			/>
			<fieldset>
				<legend>{ __( 'Show mascot on', 'ohmylms' ) }</legend>
				{ ( Object.keys( PLACEMENT_LABELS ) as MascotPlacement[] ).map(
					( place ) => (
						<ToggleField
							key={ place }
							label={ PLACEMENT_LABELS[ place ] }
							checked={ mascot.placements[ place ] }
							onChange={ ( checked ) =>
								set(
									{
										placements: {
											...mascot.placements,
											[ place ]: checked,
										},
									},
									`mascot.placements.${ place }`
								)
							}
						/>
					)
				) }
			</fieldset>
		</fieldset>
	);
}

function SchoolFields( {
	schools,
	primary,
	onSaved,
}: {
	schools: readonly SchoolBrand[];
	primary: string;
	onSaved: ( snapshot: DesignSnapshot ) => void;
} ) {
	const [ schoolId, setSchoolId ] = useState( 0 );
	const [ accentOn, setAccentOn ] = useState( false );
	const [ accent, setAccent ] = useState( '' );
	const [ logoId, setLogoId ] = useState( 0 );
	const [ logoUrl, setLogoUrl ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const school = schools.find( ( item ) => item.id === schoolId );
	useEffect( () => {
		setAccentOn( Boolean( school?.accent ) );
		setAccent( school?.accent ?? '' );
		setLogoId( school?.logo_id ?? 0 );
		setLogoUrl( school?.logo_url ?? '' );
		setMessage( '' );
	}, [ school ] );
	if ( ! schools.length ) {
		return (
			<p>
				{ __(
					'Per-school accent, logo and mascot apply once a school exists. Role logos for every user type are on the Hub icons tab.',
					'ohmylms'
				) }
			</p>
		);
	}
	return (
		<fieldset>
			<legend>{ __( 'Per-school overrides', 'ohmylms' ) }</legend>
			<p>
				{ __(
					'A school accent or logo replaces the site value for people in that school. Role logos for every user type stay on the Hub icons tab.',
					'ohmylms'
				) }
			</p>
			<SelectField
				label={ __( 'School', 'ohmylms' ) }
				value={ String( schoolId ) }
				options={ [
					{
						value: '0',
						label: __( 'Choose a school', 'ohmylms' ),
					},
					...schools.map( ( item ) => ( {
						value: String( item.id ),
						label: item.name,
					} ) ),
				] }
				onChange={ ( value ) => setSchoolId( Number( value ) ) }
			/>
			{ school && (
				<>
					<ToggleField
						label={ __( 'Accent override', 'ohmylms' ) }
						checked={ accentOn }
						onChange={ setAccentOn }
					/>
					{ accentOn && (
						<ColorField
							label={ __( 'School accent', 'ohmylms' ) }
							pickerLabel={ __(
								'School accent colour picker',
								'ohmylms'
							) }
							value={ accent || primary }
							onChange={ setAccent }
						/>
					) }
					{ accentOn &&
						contrastOnWhite( accent || primary ) < 4.5 && (
							<span className="ohmylms-ds-contrast" role="status">
								{ __(
									'White text on that accent fails WCAG AA.',
									'ohmylms'
								) }
							</span>
						) }
					<MediaPicker
						label={ __( 'School logo', 'ohmylms' ) }
						chooseText={ __( 'Choose logo', 'ohmylms' ) }
						chooseLabel={ __( 'Choose a school logo', 'ohmylms' ) }
						removeText={ __( 'Remove logo', 'ohmylms' ) }
						value={
							logoId
								? {
										id: logoId,
										url: logoUrl,
									}
								: null
						}
						previewSize={ 48 }
						invalid={ invalidResponse }
						onChange={ ( image: MediaImage ) => {
							setLogoId( image.id );
							setLogoUrl( image.thumbnail_url || image.url );
						} }
						onRemove={ () => {
							setLogoId( 0 );
							setLogoUrl( '' );
						} }
					/>
					<Button
						variant="secondary"
						disabled={ busy }
						accessibleWhenDisabled
						onClick={ () => {
							setBusy( true );
							setMessage( '' );
							void saveSchoolBrand( school.id, {
								accent: accentOn ? accent : '',
								reset_accent: ! accentOn,
								logo_id: logoId,
								remove_logo: ! logoId,
							} )
								.then( ( snapshot ) => {
									onSaved( snapshot );
									setMessage(
										__(
											'School override saved.',
											'ohmylms'
										)
									);
								} )
								.catch( ( failure ) => {
									setMessage(
										describeFailure(
											normalizeApiError( failure )
										).message
									);
								} )
								.finally( () => setBusy( false ) );
						} }
					>
						{ __( 'Save school override', 'ohmylms' ) }
					</Button>
					{ message && <span role="status"> { message }</span> }
				</>
			) }
		</fieldset>
	);
}

function BrandPreview( { draft }: { draft: DesignValues } ) {
	const onPrimary = contrastOn( draft.colors.primary ).text;
	const style = {
		'--oml-primary': draft.colors.primary,
		'--oml-heading': draft.colors.heading,
		'--oml-text': draft.colors.text,
		'--oml-success': draft.colors.success,
		'--oml-link': draft.colors.link,
		'--oml-border': draft.colors.border,
		'--oml-surface': draft.colors.surface,
		'--oml-on-primary': onPrimary,
		fontFamily: fontStack( draft.font ),
	} as CSSProperties;
	const show =
		'none' !== draft.mascot.kind && draft.mascot.placements.profile;
	const pads: Record< Density, string > = {
		compact: '6px 12px',
		comfortable: '10px 16px',
		spacious: '14px 22px',
	};
	const pad = pads[ draft.density ];
	return (
		<aside
			className="ohmylms-brand-preview"
			style={ style }
			aria-live="polite"
		>
			<h3>{ __( 'Live preview', 'ohmylms' ) }</h3>
			<p className="ohmylms-brand-preview-note">
				{ __(
					'Sample preview using these colours, the button radius, the density and the learner font. It is not a real learner.',
					'ohmylms'
				) }
			</p>
			<div
				className="ohmylms-brand-preview-band"
				style={ {
					background: `linear-gradient(120deg, ${ draft.colors.heading }, ${ draft.colors.primary })`,
					color: onPrimary,
				} }
			>
				{ show && (
					<span style={ { color: draft.colors.primary } }>
						{ 'upload' === draft.mascot.kind &&
						draft.mascot.attachment_url ? (
							<img
								src={ draft.mascot.attachment_url }
								alt=""
								width={ 48 }
								height={ 48 }
							/>
						) : (
							<MascotArt kind={ draft.mascot.kind } />
						) }
					</span>
				) }
				<div>
					<strong>{ __( 'Sample learner', 'ohmylms' ) }</strong>
					<div>@sample</div>
				</div>
				{ show && ( draft.mascot.name || draft.mascot.greeting ) && (
					<p className="ohmylms-brand-preview-bubble">
						{ [ draft.mascot.name, draft.mascot.greeting ]
							.filter( Boolean )
							.join( ': ' ) }
					</p>
				) }
			</div>
			<h4 style={ { color: draft.colors.heading } }>
				{ __( 'Algebra 1', 'ohmylms' ) }
			</h4>
			<p style={ { color: draft.colors.text } }>
				{ __( 'Next up: Solving two-step equations', 'ohmylms' ) }
			</p>
			<div className="ohmylms-brand-preview-bar">
				<span style={ { background: draft.colors.success } } />
			</div>
			<p>
				<button
					type="button"
					style={ {
						background: draft.colors.primary,
						color: onPrimary,
						borderRadius:
							PILL === draft.button_radius
								? '999px'
								: `${ draft.button_radius }px`,
						padding: pad,
						border: 0,
					} }
				>
					{ __( 'Continue', 'ohmylms' ) }
				</button>{ ' ' }
				<a
					href="#ohmylms-brand-preview"
					style={ { color: draft.colors.link } }
				>
					{ __( 'View all skills', 'ohmylms' ) }
				</a>
			</p>
		</aside>
	);
}
