/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createSettingsActionBar( readRuntime ) {
	return function SettingsActionBar( props ) {
		const { D: Buttons, React, b: I18n } = readRuntime();
		var t = props.activeTab,
			n = props.handleSave,
			r = props.handleMigration,
			a = props.selectedCourses,
			o = props.isSaving;
		return (
			<React.Fragment>
				<Controls.SpacerWP
					padding={ 0 }
					paddingBottom={ 25 }
					marginBottom={ 0 }
					marginTop={ 2 }
				>
					<Controls.FlexWP justify={ 'flex-end' }>
						{ 'migration' === t ? (
							<Buttons.A
								type={ 'primary' }
								onClick={ r }
								disabled={ 0 === a.length }
							>
								{ ( 0, I18n.__ )( 'Start Migrate', 'ohmylms' ) }
							</Buttons.A>
						) : (
							<Buttons.A
								key={ 'Save' }
								variant={ 'primary' }
								size={ 'md' }
								onClick={ n }
								isBusy={ o }
							>
								{ ( 0, I18n.__ )( 'Save Changes', 'ohmylms' ) }
							</Buttons.A>
						) }
					</Controls.FlexWP>
				</Controls.SpacerWP>
			</React.Fragment>
		);
	};
}
