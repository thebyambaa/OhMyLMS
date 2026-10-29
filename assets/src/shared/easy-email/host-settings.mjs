/**
 * Page globals the recovered Easy Email core reads.
 *
 * The recovered code took some of them when it loaded and some on every call. The split is
 * kept: `readHostSettings()` is the load-time snapshot, `getHostVars()` is read live.
 */

/** Live `window.MRM_Vars`, or undefined when the page does not define it. */
export function getHostVars() {
	return globalThis.window?.MRM_Vars;
}

/** Values the recovered core captured once, when it loaded. */
export function readHostSettings() {
	const vars = getHostVars();
	return {
		// An absent base URL is deliberately not defaulted: the recovered core produced
		// "undefinedsocial-facebook.png", and existing sites can only rely on that.
		imagesUrl: vars?.images_url,
		translations: vars?.mint_trans,
		socialMedia: vars?.business_social_settings?.socialMedia,
		rtl:
			'rtl' ===
			globalThis.document?.querySelector( 'html' )?.getAttribute( 'dir' ),
	};
}
