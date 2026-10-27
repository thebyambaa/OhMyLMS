/**
 * Intersect built-in authoring controls with the installed server manifest.
 * Include trusted extension editors, preserving existing type identifiers.
 *
 * @param {Array} builtins Built-in ID/label pairs.
 * @return {Array} Available select options.
 */
export function nativeQuestionTypes( builtins ) {
	const manifest =
		typeof window === 'undefined'
			? null
			: window.ohmylmsNativeLesson?.questionTypes ||
				window.ohmylmsExtensionManifest?.question;
	const entries =
		typeof window === 'undefined'
			? []
			: window.ohmylms?.extensions?.list?.( 'question-editor' ) || [];
	const labels = new Map( [
		...builtins,
		...Object.entries( manifest || {} )
			.filter(
				( [ , definition ] ) =>
					definition.editor?.format === 'ohmylms-options'
			)
			.map( ( [ id, definition ] ) => [ id, definition.label ] ),
		...entries.map( ( entry ) => [ entry.id, entry.label ] ),
	] );
	return [ ...labels ]
		.filter( ( [ id ] ) => ! manifest || manifest[ id ] )
		.map( ( [ value, label ] ) => ( {
			value,
			label: manifest?.[ value ]?.label || label,
		} ) );
}
