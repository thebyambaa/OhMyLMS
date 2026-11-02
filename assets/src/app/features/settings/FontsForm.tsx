import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	ADMIN_FONTS,
	fontLabel,
	getFonts,
	isDirty,
	LEARNER_FONTS,
	saveFonts,
	validateFonts,
	type AdminFont,
	type FontValues,
	type LearnerFont,
} from './designSettings';
import { SelectField } from './fields';
import { FormFrame } from './FormFrame';
import { useResource } from './useResource';

/**
 * Fonts tab (`/settings/fonts`): the font of learner pages and the font of OhMyLMS admin screens.
 *
 * @param props         Component props.
 * @param props.active  Whether the tab is selected (loads on first selection).
 * @param props.onDirty Reports unsaved edits.
 */
export function FontsForm( {
	active,
	onDirty,
}: {
	active: boolean;
	onDirty: ( dirty: boolean ) => void;
} ) {
	const state = useResource< FontValues, FontValues >(
		{
			load: async ( signal ) => ( await getFonts( signal ) ).values,
			toDraft: ( stored ) => ( { ...stored } ),
			isDirty,
			validate: validateFonts,
			save: async ( stored, draft, signal ) => {
				const result = await saveFonts( stored, draft, signal );
				return { snapshot: result.values, changed: result.changed };
			},
		},
		active
	);
	useEffect( () => onDirty( state.dirty ), [ state.dirty, onDirty ] );
	const draft = state.draft;

	return (
		<FormFrame
			title={ __( 'Fonts', 'ohmylms' ) }
			description={ __(
				'Web fonts are loaded from Google Fonts, only on the pages that use them, and support Cyrillic (Mongolian).',
				'ohmylms'
			) }
			state={ state }
			savedNote={
				state.lastSaved?.includes( 'admin_font_family' )
					? __(
							'Reload the page to see the new admin font.',
							'ohmylms'
						)
					: undefined
			}
		>
			{ draft && (
				<>
					<SelectField
						label={ __( 'Learner font', 'ohmylms' ) }
						help={ __(
							"Course, lesson, quiz and practice pages. Theme font keeps your theme's font.",
							'ohmylms'
						) }
						value={ draft.font_family }
						options={ LEARNER_FONTS.map( ( font ) => ( {
							value: font,
							label: fontLabel( font ),
						} ) ) }
						error={ state.errors.font_family }
						onChange={ ( value ) =>
							state.update(
								( current ) => ( {
									...current,
									font_family: value as LearnerFont,
								} ),
								'font_family'
							)
						}
					/>
					<SelectField
						label={ __( 'Admin font', 'ohmylms' ) }
						help={ __(
							'OhMyLMS admin screens only. System font is the one WordPress uses.',
							'ohmylms'
						) }
						value={ draft.admin_font_family }
						options={ ADMIN_FONTS.map( ( font ) => ( {
							value: font,
							label: fontLabel( font ),
						} ) ) }
						error={ state.errors.admin_font_family }
						onChange={ ( value ) =>
							state.update(
								( current ) => ( {
									...current,
									admin_font_family: value as AdminFont,
								} ),
								'admin_font_family'
							)
						}
					/>
				</>
			) }
		</FormFrame>
	);
}
