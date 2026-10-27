const lower = ( value ) => String( value || '' ).toLowerCase();

/**
 * Which color preset the four learner colors match, or 'custom'.
 * @param presets
 * @param colors
 */
export function matchingPreset( presets, colors ) {
	const found = ( presets || [] ).find(
		( preset ) =>
			lower( preset.colors.primary ) === lower( colors.primary ) &&
			lower( preset.colors.heading ) === lower( colors.heading ) &&
			lower( preset.colors.text ) === lower( colors.text ) &&
			lower( preset.colors.progress ) === lower( colors.progress )
	);
	return found ? found.value : 'custom';
}

/**
 * Design-setting changes that apply a preset's four learner colors.
 * @param preset
 */
export function presetPatch( preset ) {
	return {
		ohmylms_color_preset: { value: preset.value },
		ohmylms_primary_color_scheme: { value: preset.colors.primary },
		ohmylms_heading_color_scheme: { value: preset.colors.heading },
		ohmylms_body_text_color_scheme: { value: preset.colors.text },
		ohmylms_body_progress_color_scheme: { value: preset.colors.progress },
	};
}
