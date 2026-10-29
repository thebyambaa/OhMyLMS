/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createSetupLevelSelection( readRuntime ) {
	return function SetupLevelSelection( props ) {
		const {
			Gte,
			React,
			T: StoreModule,
			b: I18n,
			nne,
			y: WordPressData,
		} = readRuntime();
		var t = props.onTabChange,
			n = props.onWizardSkip,
			r =
				( ( 0, WordPressData.useDispatch )( StoreModule.default ),
				( 0, WordPressData.useSelect )( function ( e ) {
					return e( StoreModule.default ).getSetupWizardData();
				}, [] ) );
		return (
			<React.Fragment>
				<Gte
					isShowIndicator={ ! 1 }
					onSkip={ function () {
						return n === null || n === void 0
							? void 0
							: n( 'level-selection' );
					} }
				/>
				<Controls.ContainerWP>
					{ React.createElement( nne, null ) }
					<Controls.SpacerWP marginBottom={ 0 } marginTop={ 6 }>
						<Controls.FlexWP
							items={ 'center' }
							justify={ 'between' }
							gap={ 4 }
							style={ {
								maxWidth: '884px',
								justifyContent: 'space-between',
								margin: '0 auto',
							} }
						>
							<Controls.ButtonWP
								variant={ 'secondary' }
								onClick={ function () {
									t( 'wizard-welcome' );
								} }
							>
								{ ( 0, I18n.__ )( 'Back', 'ohmylms' ) }
							</Controls.ButtonWP>
							<Controls.ButtonWP
								variant={ 'primary' }
								onClick={ function () {
									if (
										'beginner' ===
										( r === null || r === void 0
											? void 0
											: r.level )
									) {
										t( 'wizard-niche' );
									} else {
										t( 'wizard-preferences' );
									}
								} }
							>
								{ ( 0, I18n.__ )( 'Continue', 'ohmylms' ) }
							</Controls.ButtonWP>
						</Controls.FlexWP>
					</Controls.SpacerWP>
				</Controls.ContainerWP>
			</React.Fragment>
		);
	};
}
