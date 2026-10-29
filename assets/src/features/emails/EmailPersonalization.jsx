import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
import * as Controls from '../../shared/controls';
export function createEmailPersonalization( readRuntime ) {
	return function EmailPersonalization( props ) {
		const {
			E4,
			L2,
			React,
			T: StoreModule,
			_4,
			b: I18n,
			b4,
			v4: MemoEmailButtonPosition,
			y: WordPressData,
			y4: MemoEmailSenderOptions,
		} = readRuntime();
		var t = props.isOpen,
			n = props.setIsOpen,
			r = useNotifications(),
			a = r.openNotificationWithIcon,
			o = r.contextHolder,
			i = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).selectSettingsMap();
			}, [] ),
			l = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			c = l.updateEmailSettings,
			u = l.updateSettings,
			s = function () {
				n( ! 1 );
			},
			d = ( function () {
				var e,
					_tLocal =
						( ( e = _4().m( function _eLocal() {
							var _tLocal2;
							return _4().w(
								function ( _eLocal2 ) {
									for (;;) {
										switch ( ( _eLocal2.p = _eLocal2.n ) ) {
											case 0:
												return (
													( _eLocal2.p = 0 ),
													( _eLocal2.n = 1 ),
													c( i )
												 );
											case 1:
												a(
													'success',
													'Saved successfully.'
												);
												_eLocal2.n = 3;
												break;
											case 2:
												_eLocal2.p = 2;
												_tLocal2 = _eLocal2.v;
												showRequestError( _tLocal2 );
												a(
													'error',
													'Failed to update. Please try again.'
												);
											// Falls through to the shared async state cleanup.
											case 3:
												return _eLocal2.a( 2 );
										}
									}
								},
								_eLocal,
								null,
								[ [ 0, 2 ] ]
							);
						} ) ),
						function ( ..._args ) {
							var _tLocal3 = this,
								_nLocal = _args;
							return new Promise( function ( _rLocal, _aLocal ) {
								var _oLocal = e.apply( _tLocal3, _nLocal );
								function _iLocal( _eLocal3 ) {
									E4(
										_oLocal,
										_rLocal,
										_aLocal,
										_iLocal,
										_lLocal,
										'next',
										_eLocal3
									);
								}
								function _lLocal( _eLocal4 ) {
									E4(
										_oLocal,
										_rLocal,
										_aLocal,
										_iLocal,
										_lLocal,
										'throw',
										_eLocal4
									);
								}
								_iLocal( void 0 );
							} );
						} );
				return function ( ..._args2 ) {
					return _tLocal.apply( this, _args2 );
				};
			} )(),
			m = function ( e, _tLocal4, _nLocal2 ) {
				u(
					e,
					( function ( _eLocal5, _tLocal5, _nLocal3 ) {
						return (
							( _tLocal5 = ( function ( _eLocal6 ) {
								var _tLocal6 = ( function ( _eLocal7 ) {
									if (
										'object' !== b4( _eLocal7 ) ||
										! _eLocal7
									) {
										return _eLocal7;
									}
									var _tLocal7 =
										_eLocal7[ Symbol.toPrimitive ];
									if ( void 0 !== _tLocal7 ) {
										var _nLocal4 = _tLocal7.call(
											_eLocal7,
											'string'
										);
										if ( 'object' !== b4( _nLocal4 ) ) {
											return _nLocal4;
										}
										throw new TypeError(
											'@@toPrimitive must return a primitive value.'
										);
									}
									return String( _eLocal7 );
								} )( _eLocal6 );
								return 'symbol' === b4( _tLocal6 )
									? _tLocal6
									: _tLocal6 + '';
							} )( _tLocal5 ) ) in _eLocal5
								? Object.defineProperty( _eLocal5, _tLocal5, {
										value: _nLocal3,
										enumerable: ! 0,
										configurable: ! 0,
										writable: ! 0,
									} )
								: ( _eLocal5[ _tLocal5 ] = _nLocal3 ),
							_eLocal5
						 );
					} )( {}, _tLocal4, _nLocal2 )
				);
			},
			p = [
				{
					title: ( 0, I18n.__ )( 'Base color', 'ohmylms' ),
					description: ( 0, I18n.__ )(
						'The base color for email template.',
						'ohmylms'
					),
					isShowResetBtn: ! 0,
					defaultColor: '#6E42D3',
					initialColor: i.ohmylms_email_base_color,
					onChange( e ) {
						m( 'ohmylms_email_base_color', 'value', e );
					},
				},
				{
					title: ( 0, I18n.__ )( 'Background color', 'ohmylms' ),
					description: ( 0, I18n.__ )(
						'The background color for email template.',
						'ohmylms'
					),
					isShowResetBtn: ! 0,
					defaultColor: '#F4F5F7',
					initialColor: i.ohmylms_email_background_color,
					onChange( e ) {
						m( 'ohmylms_email_background_color', 'value', e );
					},
				},
				{
					title: ( 0, I18n.__ )( 'Body background color', 'ohmylms' ),
					description: ( 0, I18n.__ )(
						'The main body background color.',
						'ohmylms'
					),
					isShowResetBtn: ! 0,
					defaultColor: '#FFFFFF',
					initialColor: i.ohmylms_email_body_background_color,
					onChange( e ) {
						m( 'ohmylms_email_body_background_color', 'value', e );
					},
				},
				{
					title: ( 0, I18n.__ )( 'Body text color', 'ohmylms' ),
					description: ( 0, I18n.__ )(
						'The main body text color default.',
						'ohmylms'
					),
					isShowResetBtn: ! 0,
					defaultColor: '#1F2328',
					initialColor: i.ohmylms_email_body_text_color,
					onChange( e ) {
						m( 'ohmylms_email_body_text_color', 'value', e );
					},
				},
				{
					title: ( 0, I18n.__ )( 'Notification color', 'ohmylms' ),
					description: ( 0, I18n.__ )(
						'Color used for verification and notification banners on the site.',
						'ohmylms'
					),
					isShowResetBtn: ! 0,
					defaultColor: '#6E42D3',
					initialColor: i.ohmylms_notification_color,
					onChange( e ) {
						m( 'ohmylms_notification_color', 'value', e );
					},
				},
			];
		return (
			<React.Fragment>
				{ o }
				{ t && (
					<Controls.ModalWP
						title={ ( 0, I18n.__ )( 'Settings', 'ohmylms' ) }
						onRequestClose={ s }
						size={ 'large' }
					>
						<Controls.FlexWP direction={ 'column' } gap={ '4' }>
							<L2
								title={ ( 0, I18n.__ )(
									'Branding',
									'ohmylms'
								) }
								description={ ( 0, I18n.__ )(
									'Add a logo and color theme to customize the look and feel of email notification your customers receive.',
									'ohmylms'
								) }
								brandingImg={ i.ohmylms_email_branding_image }
								handleRemove={ function () {
									m(
										'ohmylms_email_branding_image',
										'value',
										''
									);
								} }
								handleChange={ function ( e ) {
									return m(
										'ohmylms_email_branding_image',
										'value',
										e
									);
								} }
								colorsConfig={ p }
								showDivider={ ! 0 }
							/>
							<MemoEmailButtonPosition />
							<MemoEmailSenderOptions />
						</Controls.FlexWP>
						<Controls.DividerWP marginStart={ 3 } marginEnd={ 4 } />
						<Controls.SpacerWP marginBottom={ 0 }>
							<Controls.FlexWP justify={ 'end' } gap={ '2' }>
								<Controls.ButtonWP
									variant={ 'secondary' }
									onClick={ s }
								>
									{ ( 0, I18n.__ )( 'Cancel', 'ohmylms' ) }
								</Controls.ButtonWP>
								<Controls.ButtonWP
									variant={ 'primary' }
									onClick={ d }
								>
									{ ( 0, I18n.__ )( 'Save', 'ohmylms' ) }
								</Controls.ButtonWP>
							</Controls.FlexWP>
						</Controls.SpacerWP>
					</Controls.ModalWP>
				) }
			</React.Fragment>
		);
	};
}
