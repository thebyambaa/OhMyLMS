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
				.then( importFeature )
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
