/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
import { highlightAdminMenu } from '../../shared/utils';
import { PageHeader } from '../../shared/media';
export function createCommunitiesPage( readRuntime ) {
	return function CommunitiesPage() {
		const { G9: CommunityList, React, b: I18n } = readRuntime();
		return (
			highlightAdminMenu( 'ohmylms', 'communities' ),
			(
				<Controls.ContainerWP>
					<PageHeader
						title={ ( 0, I18n.__ )( 'Communities', 'ohmylms' ) }
						showAddButton={ ! 1 }
					/>
					<CommunityList />
				</Controls.ContainerWP>
			 )
		 );
	};
}
