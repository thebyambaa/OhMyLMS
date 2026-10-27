/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createSetupWizardPage( readRuntime ) {
	return function SetupWizardPage() {
		const {
			React,
			b: I18n,
			bre: MemoSetupWizard,
			g: ReactHooks,
		} = readRuntime();
		return (
			( 0, ReactHooks.useLayoutEffect )( function () {
				return (
					( document.title = ( 0, I18n.__ )(
						'Setup Wizard - OhMyLMS',
						'ohmylms'
					) ),
					document.documentElement.classList.add(
						'ohmylms-setup-wizard'
					),
					function () {
						document.documentElement.classList.remove(
							'ohmylms-setup-wizard'
						);
						document.title = 'OhMyLMS';
					}
				 );
			}, [] ),
			(
				<React.Fragment>
					<MemoSetupWizard />
				</React.Fragment>
			 )
		 );
	};
}
