/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createCommunitiesPage( readRuntime ) {
	return function CommunitiesPage() {
		const {
			G9: CommunityList,
			HG,
			I: Controls,
			React,
			YG,
			b: I18n,
		} = readRuntime();
		return (
			HG( 'ohmylms', 'communities' ),
			(
				<Controls.ContainerWP>
					<YG
						title={ ( 0, I18n.__ )( 'Communities', 'ohmylms' ) }
						showAddButton={ ! 1 }
					/>
					<CommunityList />
				</Controls.ContainerWP>
			 )
		 );
	};
}
