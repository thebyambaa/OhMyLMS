import { createElement, useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { SelectControl } from '@wordpress/components';
import { matchingPreset, presetPatch } from './theme.mjs';

const design = () => window.ohmylmsDesign || { fonts: [], defaults: {} };

/**
 * Font choices: the learner site may keep the theme's font; the admin uses system or a web font.
 * @param allowInherit
 */
function fontOptions( allowInherit ) {
	return [
		...( allowInherit
			? [
					{
						value: 'inherit',
						label: __( 'Theme font (inherit)', 'ohmylms' ),
					},
				]
			: [] ),
		{ value: 'system', label: __( 'System font', 'ohmylms' ) },
		...design().fonts.map( ( font ) => ( { value: font, label: font } ) ),
	];
}

/**
 * Live preview of admin tokens before saving (the saved values are printed on reload).
 * @param name
 * @param value
 */
function previewAdmin( name, value ) {
	if ( ! value ) {
		return;
	}
	const root = document.documentElement.style;
	if ( name === 'ohmylms_admin_primary_color' ) {
		[
			'--wp-admin-theme-color',
			'--wp-components-color-accent',
			'--ohmylms-primary-color',
		].forEach( ( token ) => root.setProperty( token, value ) );
	}
	if ( name === 'ohmylms_admin_heading_color' ) {
		[
			'--wp-components-color-foreground',
			'--ohmylms-admin-heading-color',
			'--ohmylms-admin-text-color',
		].forEach( ( token ) => root.setProperty( token, value ) );
	}
	if ( name === 'ohmylms_admin_muted_color' ) {
		[
			'--wp-components-color-subdued',
			'--ohmylms-admin-muted-color',
		].forEach( ( token ) => root.setProperty( token, value ) );
	}
}

/**
 * Settings → Design → Colors & fonts. Learner-site colors (with presets) and font, and the
 * admin dashboard's colors and font. Values are saved with the Design settings and turned
 * into CSS variables by OhMyLMS\Design\Tokens.
 * @param root0
 * @param root0.store
 * @param root0.presets
 * @param root0.ColorCards
 * @param root0.Controls
 */
export function createThemeSettings( {
	store,
	presets,
	ColorCards,
	Controls,
} ) {
	return function ThemeSettings() {
		const settings = useSelect(
			( select ) => select( store ).getDesignSettings() || {},
			[]
		);
		const { updateDesignSettings } = useDispatch( store );
		const defaults = design().defaults;
		const value = ( key ) => settings?.[ key ]?.value ?? defaults[ key ];
		const set = ( key ) => ( next ) =>
			updateDesignSettings( { [ key ]: { value: next } } );
		const learner = {
			primary: value( 'ohmylms_primary_color_scheme' ),
			heading: value( 'ohmylms_heading_color_scheme' ),
			text: value( 'ohmylms_body_text_color_scheme' ),
			progress: value( 'ohmylms_body_progress_color_scheme' ),
		};
		const preset = matchingPreset( presets, learner );
		useEffect( () => {
			[
				'ohmylms_admin_primary_color',
				'ohmylms_admin_heading_color',
				'ohmylms_admin_muted_color',
			].forEach( ( key ) =>
				previewAdmin( key, settings?.[ key ]?.value )
			);
		}, [ settings ] );
		const color = ( key, title, description ) => ( {
			title,
			description,
			isShowResetBtn: true,
			defaultColor: defaults[ key ],
			initialColor: value( key ),
			onChange: ( next ) => {
				set( key )( next );
				previewAdmin( key, next );
			},
		} );
		return (
			<div className="ohmylms-theme-settings">
				<Controls.CardWP isBorderless>
					<Controls.SpacerWP padding={ 6 } marginBottom={ 4 }>
						<h3
							style={ {
								margin: '0 0 4px',
								fontSize: 16,
								fontWeight: 600,
							} }
						>
							{ __( 'Learner site', 'ohmylms' ) }
						</h3>
						<p
							className="ohmylms-ext-muted"
							style={ { margin: '0 0 16px' } }
						>
							{ __(
								'Course pages, lessons, quizzes, practice, lesson checks and the learning hub.',
								'ohmylms'
							) }
						</p>
						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Color preset', 'ohmylms' ) }
							value={ preset }
							options={ [
								...presets.map( ( item ) => ( {
									value: item.value,
									label: item.label,
								} ) ),
								...( preset === 'custom'
									? [
											{
												value: 'custom',
												label: __(
													'Custom',
													'ohmylms'
												),
											},
										]
									: [] ),
							] }
							onChange={ ( next ) => {
								const chosen = presets.find(
									( item ) => item.value === next
								);
								if ( ! chosen ) {
									return;
								}
								updateDesignSettings( presetPatch( chosen ) );
							} }
						/>
						<ColorCards
							key={ `learner-${ preset }` }
							showDivider={ false }
							colorsConfig={ [
								color(
									'ohmylms_primary_color_scheme',
									__( 'Primary color', 'ohmylms' ),
									__(
										'Buttons, links and highlights.',
										'ohmylms'
									)
								),
								color(
									'ohmylms_heading_color_scheme',
									__( 'Heading color', 'ohmylms' ),
									__( 'Titles and headings.', 'ohmylms' )
								),
								color(
									'ohmylms_body_text_color_scheme',
									__( 'Text color', 'ohmylms' ),
									__( 'Body text.', 'ohmylms' )
								),
								color(
									'ohmylms_body_progress_color_scheme',
									__( 'Progress bar color', 'ohmylms' ),
									__( 'Course progress bars.', 'ohmylms' )
								),
							] }
						/>
						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Font', 'ohmylms' ) }
							help={ __(
								'Web fonts are loaded from Google Fonts and support Cyrillic (Mongolian).',
								'ohmylms'
							) }
							value={ value( 'ohmylms_font_family' ) }
							options={ fontOptions( true ) }
							onChange={ set( 'ohmylms_font_family' ) }
						/>
					</Controls.SpacerWP>
				</Controls.CardWP>
				<Controls.CardWP isBorderless>
					<Controls.SpacerWP padding={ 6 } marginBottom={ 4 }>
						<h3
							style={ {
								margin: '0 0 4px',
								fontSize: 16,
								fontWeight: 600,
							} }
						>
							{ __( 'Admin dashboard', 'ohmylms' ) }
						</h3>
						<p
							className="ohmylms-ext-muted"
							style={ { margin: '0 0 16px' } }
						>
							{ __(
								'Every OhMyLMS admin screen, including the question bank, skills and reports. Colors preview immediately; save to keep them.',
								'ohmylms'
							) }
						</p>
						<ColorCards
							showDivider={ false }
							colorsConfig={ [
								color(
									'ohmylms_admin_primary_color',
									__( 'Primary color', 'ohmylms' ),
									__(
										'Buttons, active tabs, links and focus.',
										'ohmylms'
									)
								),
								color(
									'ohmylms_admin_heading_color',
									__( 'Text color', 'ohmylms' ),
									__( 'Headings and table text.', 'ohmylms' )
								),
								color(
									'ohmylms_admin_muted_color',
									__( 'Muted text color', 'ohmylms' ),
									__(
										'Descriptions, table headers and hints.',
										'ohmylms'
									)
								),
							] }
						/>
						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Font', 'ohmylms' ) }
							help={ __(
								'Applies after saving and reloading.',
								'ohmylms'
							) }
							value={ value( 'ohmylms_admin_font_family' ) }
							options={ fontOptions( false ) }
							onChange={ set( 'ohmylms_admin_font_family' ) }
						/>
					</Controls.SpacerWP>
				</Controls.CardWP>
			</div>
		);
	};
}
