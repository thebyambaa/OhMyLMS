/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { editorBackPath } from '../content-hub/editorNavigation.mjs';
export function createLearningEditorHeader( readRuntime ) {
	return function LearningEditorHeader( props ) {
		const { I: Controls, Nr, React, f: Router } = readRuntime();
		var title = props.title,
			redirection = props.redirection,
			rightContent = props.rightContent,
			a = ( 0, Router.Zp )();
		return (
			<Controls.CardWP
				style={ {
					borderRadius: 0,
				} }
			>
				<Controls.SpacerWP padding={ 4 }>
					<Controls.FlexWP
						justify={ 'space-between' }
						align={ 'center' }
					>
						<Controls.FlexWP
							align={ 'center' }
							gap={ 2 }
							justify={ 'flex-start' }
						>
							<Nr
								onClick={ function () {
									a(
										editorBackPath(
											window.location.hash,
											redirection ?? '/'
										)
									);
								} }
							/>
							<Controls.HeadingWP level={ 4 } title={ title }>
								{ title }
							</Controls.HeadingWP>
						</Controls.FlexWP>
						<Controls.FlexWP
							align={ 'center' }
							gap={ 2 }
							justify={ 'flex-end' }
						>
							{ rightContent }
						</Controls.FlexWP>
					</Controls.FlexWP>
				</Controls.SpacerWP>
			</Controls.CardWP>
		);
	};
}
