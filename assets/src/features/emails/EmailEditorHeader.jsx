/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createEmailEditorHeader( readRuntime ) {
	return function EmailEditorHeader( props ) {
		const {
			Eee: MemoEmailMobileIcon,
			I: Controls,
			React,
			_ee: MemoEmailDesktopIcon,
			b: I18n,
		} = readRuntime();
		var t = props.title,
			n = props.handlePreview,
			r = props.handleSave,
			a = props.device;
		return (
			<React.Fragment>
				<Controls.SpacerWP marginY={ 3 } padding={ 3 }>
					<Controls.FlexWP
						justify={ 'space-between' }
						align={ 'center' }
						gap={ '2' }
					>
						<Controls.HeadingWP level={ '3' }>
							{ t }
						</Controls.HeadingWP>
						<Controls.FlexItemWP>
							<Controls.FlexWP
								align={ 'center' }
								justify={ 'start' }
								gap={ 4 }
								className={
									'ohmylms-email-editor-responsieve-switcher'
								}
							>
								<Controls.RadioGroupIconWP
									onChange={ function ( e ) {
										return n( e );
									} }
									value={ a }
									options={ [
										{
											label: ( 0, I18n.__ )(
												'Desktop',
												'ohmylms'
											),
											value: 'desktop',
											icon: <MemoEmailDesktopIcon />,
										},
										{
											label: ( 0, I18n.__ )(
												'Mobile',
												'ohmylms'
											),
											value: 'mobile',
											icon: <MemoEmailMobileIcon />,
										},
									] }
								/>
								<Controls.ButtonWP
									variant={ 'primary' }
									onClick={ r }
								>
									{ ( 0, I18n.__ )(
										'Save Changes',
										'ohmylms'
									) }
								</Controls.ButtonWP>
							</Controls.FlexWP>
						</Controls.FlexItemWP>
					</Controls.FlexWP>
				</Controls.SpacerWP>
			</React.Fragment>
		);
	};
}
