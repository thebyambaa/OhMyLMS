/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { highlightAdminMenu } from '../../shared/utils';
export function createCertificatesPage( readRuntime ) {
	return function CertificatesPage() {
		const { React, gte } = readRuntime();
		return (
			highlightAdminMenu( 'ohmylms', 'certificates' ),
			(
				<React.Fragment>
					{ React.createElement( gte, null ) }
				</React.Fragment>
			 )
		 );
	};
}
