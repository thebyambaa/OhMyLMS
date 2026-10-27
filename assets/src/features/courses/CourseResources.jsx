/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createCourseResources( readRuntime ) {
	return function CourseResources() {
		const { Gz, I: Controls, React, bV } = readRuntime();
		return (
			<Controls.CardWP isBorderless={ ! 0 }>
				<Controls.SpacerWP
					padding={ 0 }
					marginTop={ 6 }
					marginBottom={ 6 }
				>
					<Controls.SpacerWP marginBottom={ 0 } padding={ 5 }>
						<Gz />
					</Controls.SpacerWP>
					<Controls.SpacerWP marginBottom={ 0 } padding={ 5 }>
						{ React.createElement( bV, null ) }
					</Controls.SpacerWP>
				</Controls.SpacerWP>
			</Controls.CardWP>
		);
	};
}
