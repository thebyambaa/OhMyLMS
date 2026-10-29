/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { highlightAdminMenu } from '../../shared/utils';
export function createMembershipsPage( readRuntime ) {
	return function MembershipsPage() {
		const { $8: MembershipList, React } = readRuntime();
		return (
			highlightAdminMenu( 'ohmylms', 'memberships' ),
			(
				<React.Fragment>
					<MembershipList />
				</React.Fragment>
			 )
		 );
	};
}
