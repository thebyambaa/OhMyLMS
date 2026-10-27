/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createCertificatesPage( readRuntime ) {
	return function CertificatesPage() {
		const { HG, React, gte } = readRuntime();
		return (
			HG( 'ohmylms', 'certificates' ),
			(
				<React.Fragment>
					{ React.createElement( gte, null ) }
				</React.Fragment>
			 )
		 );
	};
}
