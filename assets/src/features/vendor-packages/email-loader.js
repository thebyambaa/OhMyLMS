import { createEasyEmailCore } from '../../shared/easy-email/index.mjs';

// Easy Email core stays eager. Admin startup already ran the recovered copy through the
// template previews and the default templates, which call it synchronously. The bridge for
// webpack factory 49050 calls this when the first consumer requires the core.
window.ohmylmsEmailPackages = {
	...window.ohmylmsEmailPackages,
	createEasyEmailCore,
};
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
