/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createStudentReminderDialog( readRuntime ) {
	return function StudentReminderDialog( props ) {
		const { I: Controls, M, React, b: I18n, g: ReactHooks } = readRuntime();
		M().noConflict();
		var t = props.title,
			n = void 0 === t ? ( 0, I18n.__ )( 'Send Reminder', 'ohmylms' ) : t,
			r = ( props.isOpen, props.handleCancel ),
			a = props.handleOk,
			o = props.okText,
			i = void 0 === o ? ( 0, I18n.__ )( 'Send', 'ohmylms' ) : o,
			l = props.onEmailChange,
			c = props.isEmailDisabled,
			u = props.email,
			s = props.onSubjectChange,
			d = props.subject,
			m = ( props.onEmailBodyChange, props.emailBody ),
			p =
				void 0 === m
					? 'If you no longer wish to receive these emails, please update your Preferences or Unsubscribe here.'
					: m,
			f = ( props.className, props.loading );
		return (
			( 0, ReactHooks.useEffect )( function () {
				var e,
					_tLocal =
						'rtl' ===
						( null === ( e = document.querySelector( 'html' ) ) ||
						void 0 === e
							? void 0
							: e.getAttribute( 'dir' ) );
				wp.editor.remove( 'new-message' );
				wp.editor.initialize( 'new-message', {
					tinymce: {
						plugins:
							'colorpicker compat3x lists tabfocus textcolor wordpress wpautoresize wpdialogs wpeditimage wpemoji wpgallery wptextpattern wpview link directionality',
						toolbar1:
							'bold italic underline strikethrough | bullist numlist | blockquote hr wp_more | alignleft aligncenter alignright | link unlink | ltr rtl | wp_adv',
						toolbar2:
							'formatselect alignjustify forecolor | fontsizeselect | fontselect |pastetext removeformat charmap | outdent indent | undo redo | wp_help',
						branding: ! 1,
					},
					quicktags: ! 0,
					mediaButtons: ! 0,
					wpautop: ! 0,
					directionality: ''.concat( _tLocal ? 'rtl' : 'ltr' ),
					initialValue: p,
				} );
			}, [] ),
			(
				<React.Fragment>
					<Controls.ModalWP
						title={ n }
						shouldCloseOnEsc={ ! 0 }
						shouldCloseOnClickOutside={ ! 0 }
						onRequestClose={ r }
					>
						<Controls.TextWP as={ 'span' } size={ 14 }>
							{ ( 0, I18n.__ )( 'To:', 'ohmylms' ) }
						</Controls.TextWP>
						<Controls.InputWP
							onChange={ function ( e ) {
								return l( e );
							} }
							disabled={ c }
							value={ u }
							type={ 'email' }
						/>
						<Controls.SpacerWP marginBottom={ 4 } />
						<Controls.InputWP
							value={ d }
							onChange={ function ( e ) {
								return s( e );
							} }
							placeholder={ 'Subject' }
							type={ 'text' }
						/>
						<Controls.SpacerWP marginBottom={ 4 } />
						<textarea
							id={ 'new-message' }
							placeholder={ 'Type something here...' }
							defaultValue={ p }
						/>
						<Controls.SpacerWP marginBottom={ 4 } />
						<Controls.FlexWP justify={ 'flex-end' }>
							<Controls.ButtonWP
								variant={ 'primary' }
								onClick={ a }
								isBusy={ f }
							>
								{ i }
							</Controls.ButtonWP>
						</Controls.FlexWP>
					</Controls.ModalWP>
				</React.Fragment>
			 )
		 );
	};
}
