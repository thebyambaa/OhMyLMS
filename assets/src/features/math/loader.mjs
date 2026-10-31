/** @type {Promise<NonNullable<Window['OhMyLMSMath']>>|undefined} */
let pending;

/** @return {boolean} Whether the site enabled the rollout. */
export const mathEnabled = () =>
	typeof window !== 'undefined' && !! window.ohmylmsMath?.enabled;

/** @return {Promise<NonNullable<Window['OhMyLMSMath']>>} Locally packaged runtime, loaded only on demand. */
export function loadMath() {
	if ( ! mathEnabled() ) {
		return Promise.reject( new Error( 'Math input is disabled.' ) );
	}
	const config = /** @type {NonNullable<Window['ohmylmsMath']>} */ (
		window.ohmylmsMath
	);
	if ( window.OhMyLMSMath ) {
		return Promise.resolve( window.OhMyLMSMath );
	}
	if ( ! pending ) {
		if ( ! document.querySelector( '[data-ohmylms-math-style]' ) ) {
			const style = document.createElement( 'link' );
			style.rel = 'stylesheet';
			style.href = config.style;
			style.dataset.ohmylmsMathStyle = '1';
			document.head.append( style );
		}
		pending = new Promise( ( resolve, reject ) => {
			const existing = /** @type {HTMLScriptElement|null} */ (
				document.querySelector( 'script[src="' + config.runtime + '"]' )
			);
			const script = existing || document.createElement( 'script' );
			script.addEventListener(
				'load',
				() => {
					if ( window.OhMyLMSMath ) {
						resolve( window.OhMyLMSMath );
					} else {
						pending = undefined;
						reject(
							new Error( 'Math input could not initialize.' )
						);
					}
				},
				{ once: true }
			);
			script.addEventListener(
				'error',
				() => {
					pending = undefined;
					script.remove();
					reject( new Error( 'Math input could not load.' ) );
				},
				{ once: true }
			);
			if ( ! existing ) {
				script.src = config.runtime;
				document.head.append( script );
			}
		} );
	}
	return pending;
}
