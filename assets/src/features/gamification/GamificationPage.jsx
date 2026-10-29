/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { highlightAdminMenu } from '../../shared/utils';
export function createGamificationPage( readRuntime ) {
	return function GamificationPage() {
		const { R5: GamificationSettings, React } = readRuntime();
		return (
			highlightAdminMenu( 'ohmylms', 'gamification' ),
			(
				<React.Fragment>
					<GamificationSettings />
				</React.Fragment>
			 )
		 );
	};
}
