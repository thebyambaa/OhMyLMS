/**
 * Share one import across a feature's components without loading it at registration.
 * @param importFeature
 * @param registryName
 */
export function createFeatureLoader( importFeature, registryName ) {
	let pending;
	return function loadFeature() {
		if ( ! pending ) {
			pending = Promise.resolve()
				.then( () =>
					Promise.all( [
						importFeature(),
						globalThis.ohmylmsStyles?.ensure( registryName ),
					] )
				)
				.then( ( [ module ] ) => module )
				.then( ( module ) => {
					const components = module[ registryName ];
					if ( ! components ) {
						throw new Error(
							`Missing feature registry: ${ registryName }`
						);
					}
					return components;
				} );
		}
		return pending;
	};
}
