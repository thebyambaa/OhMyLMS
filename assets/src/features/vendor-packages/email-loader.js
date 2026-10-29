let pending;

// Keep package downloads behind the existing email designer boundary and allow retries.
window.ohmylmsLoadEmailPackages = () => {
	if ( ! pending ) {
		pending = import(
			/* webpackChunkName: "vendor-email" */ './email'
		).catch( ( error ) => {
			pending = undefined;
			throw error;
		} );
	}
	return pending;
};
