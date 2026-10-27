/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createQuizSettings( readRuntime ) {
	return function QuizSettings( props ) {
		const { I: Controls, React, Xm, b: I18n } = readRuntime();
		const setIsSettingsOpen = props.setIsSettingsOpen;
		if ( props.embedded ) {
			return (
				<div className="ohmylms-quiz-inspector-settings">
					<Xm />
				</div>
			);
		}
		return (
			props.chapterId,
			( 0, I18n.__ )( 'General', 'ohmylms' ),
			(
				<Controls.CardWP variant={ 'secondary' } isBorderless={ ! 0 }>
					<Controls.SpacerWP
						marginBottom={ 0 }
						marginTop={ 4 }
						padding={ 4 }
					>
						<Controls.ContainerWP>
							<div>
								<Controls.ButtonWP
									variant={ 'secondary' }
									onClick={ function () {
										return setIsSettingsOpen( ! 1 );
									} }
								>
									<Controls.FlexWP
										justify={ 'start' }
										gap={ 2 }
									>
										<svg
											className={
												'ohmylms-back-arrow-btn-icon'
											}
											width={ '19' }
											height={ '16' }
											fill={ 'none' }
											viewBox={ '0 0 19 16' }
											xmlns={
												'http://www.w3.org/2000/svg'
											}
										>
											<path
												fill={ 'currentColor' }
												d={
													'M8.707 13.793a1 1 0 11-1.414 1.414L1.5 9.414a2 2 0 010-2.828L7.293.793a1 1 0 111.414 1.414L3.914 7H18a1 1 0 110 2H3.914l4.793 4.793z'
												}
											/>
										</svg>
										<span>
											{ ( 0, I18n.__ )(
												'Back',
												'ohmylms'
											) }
										</span>
									</Controls.FlexWP>
								</Controls.ButtonWP>
								<Controls.SpacerWP />
								<Controls.HeadingWP level={ 4 }>
									{ ( 0, I18n.__ )( 'Settings', 'ohmylms' ) }
								</Controls.HeadingWP>
							</div>
							<Controls.CardWP isBorderless={ ! 0 }>
								<Controls.SpacerWP
									marginBottom={ 0 }
									marginTop={ 4 }
									padding={ 4 }
								>
									<Xm />
								</Controls.SpacerWP>
							</Controls.CardWP>
						</Controls.ContainerWP>
					</Controls.SpacerWP>
				</Controls.CardWP>
			 )
		 );
	};
}
