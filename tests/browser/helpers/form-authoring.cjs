/** Simulate the legacy flag; quiz routes must still open Gutenberg. */
exports.useFormAuthoring = async ( page ) => {
	await page.addInitScript( () => {
		let configuration;
		Object.defineProperty( window, 'ohmylmsAssessment', {
			configurable: true,
			get: () => configuration,
			set: ( value ) => {
				configuration = { ...value, formAuthoring: true };
			},
		} );
	} );
};
