import { __, sprintf } from '@wordpress/i18n';
import { asText, ColorField, TextField } from './fields';
import { FormFrame } from './FormFrame';
import type { SettingsGroupState } from './hooks';
import type { FieldName } from './types';

const COLORS: ReadonlyArray< {
	name: FieldName< 'branding' >;
	label: string;
} > = [
	{ name: 'primary_color', label: __( 'Primary colour', 'ohmylms' ) },
	{
		name: 'primary_hover_color',
		label: __( 'Primary hover colour', 'ohmylms' ),
	},
	{ name: 'heading_color', label: __( 'Heading colour', 'ohmylms' ) },
	{ name: 'body_text_color', label: __( 'Body text colour', 'ohmylms' ) },
	{ name: 'progress_color', label: __( 'Progress bar colour', 'ohmylms' ) },
	{
		name: 'video_player_logo_bg_color',
		label: __( 'Video player logo background colour', 'ohmylms' ),
	},
];

/**
 * Branding tab: brand colours and the video player logo.
 *
 * @param props       Component props.
 * @param props.state State of the `branding` group.
 */
export function BrandingForm( {
	state,
}: {
	state: SettingsGroupState< 'branding' >;
} ) {
	const draft = state.draft;
	return (
		<FormFrame
			title={ __( 'Branding', 'ohmylms' ) }
			description={ __(
				'Brand colours (hex, for example #6e42d3) and the logo shown in the lesson video player.',
				'ohmylms'
			) }
			state={ state }
		>
			{ draft && (
				<>
					<TextField
						label={ __( 'Colour preset name', 'ohmylms' ) }
						help={ __(
							'Lower-case words separated by hyphens.',
							'ohmylms'
						) }
						value={ asText( draft.color_preset ) }
						error={ state.errors.color_preset }
						onChange={ ( value ) =>
							state.setField( 'color_preset', value )
						}
					/>
					{ COLORS.map( ( color ) => (
						<ColorField
							key={ color.name }
							label={ color.label }
							pickerLabel={ sprintf(
								/* translators: %s: colour setting name. */
								__( '%s picker', 'ohmylms' ),
								color.label
							) }
							value={ asText( draft[ color.name ] ) }
							error={ state.errors[ color.name ] }
							onChange={ ( value ) =>
								state.setField( color.name, value )
							}
						/>
					) ) }
					<TextField
						label={ __( 'Video player logo URL', 'ohmylms' ) }
						help={ __(
							'A full http or https address. Leave empty for no logo.',
							'ohmylms'
						) }
						value={ asText( draft.video_player_logo ) }
						error={ state.errors.video_player_logo }
						onChange={ ( value ) =>
							state.setField( 'video_player_logo', value )
						}
					/>
				</>
			) }
		</FormFrame>
	);
}
