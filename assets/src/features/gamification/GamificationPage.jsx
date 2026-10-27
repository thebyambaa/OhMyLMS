/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createGamificationPage( readRuntime ) {
	return function GamificationPage() {
		const { HG, R5: GamificationSettings, React } = readRuntime();
		return (
			HG( 'ohmylms', 'gamification' ),
			(
				<React.Fragment>
					<GamificationSettings />
				</React.Fragment>
			 )
		 );
	};
}
