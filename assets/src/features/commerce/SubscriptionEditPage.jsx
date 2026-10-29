/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { highlightAdminMenu } from '../../shared/utils';
export function createSubscriptionEditPage( readRuntime ) {
	return function SubscriptionEditPage() {
		const { React, iZ } = readRuntime();
		return (
			highlightAdminMenu( 'ohmylms', 'subscriptions' ),
			(
				<React.Fragment>
					{ React.createElement( iZ, null ) }
				</React.Fragment>
			 )
		 );
	};
}
