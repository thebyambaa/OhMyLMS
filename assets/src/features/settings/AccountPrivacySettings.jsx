/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createAccountPrivacySettings( readRuntime ) {
	return function AccountPrivacySettings( props ) {
		const {
			$K,
			Ea,
			Ge,
			He,
			Pf,
			React,
			SK: MemoSettingsActionBar,
			T: StoreModule,
			YK,
			ZK,
			b: I18n,
			g: ReactHooks,
			l,
			y: WordPressData,
			zm,
		} = readRuntime();
		var t,
			n,
			r,
			a,
			o = props.activeTab,
			i = props.handleSave,
			c = props.handleMigration,
			u = props.selectedCourses,
			s = props.isSaving,
			d = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			m = ( function ( e, _tLocal ) {
				return (
					( function ( _eLocal ) {
						if ( Array.isArray( _eLocal ) ) {
							return _eLocal;
						}
					} )( e ) ||
					( function ( _eLocal2, _tLocal2 ) {
						var _nLocal =
							_eLocal2 === null || _eLocal2 === void 0
								? null
								: ( 'undefined' !== typeof Symbol &&
										_eLocal2[ Symbol.iterator ] ) ||
									_eLocal2[ '@@iterator' ];
						if ( _nLocal !== null && _nLocal !== void 0 ) {
							var _rLocal,
								_aLocal,
								_oLocal,
								_iLocal,
								_lLocal = [],
								_cLocal = ! 0,
								_uLocal = ! 1;
							try {
								if (
									( ( _oLocal = ( _nLocal =
										_nLocal.call( _eLocal2 ) ).next ),
									0 === _tLocal2 )
								) {
									if ( Object( _nLocal ) !== _nLocal ) {
										return;
									}
									_cLocal = ! 1;
								} else {
									for (
										;
										! ( _cLocal = ( _rLocal =
											_oLocal.call( _nLocal ) ).done ) &&
										( _lLocal.push( _rLocal.value ),
										_lLocal.length !== _tLocal2 );
										_cLocal = ! 0
									) {}
								}
							} catch ( _eLocal3 ) {
								_uLocal = ! 0;
								_aLocal = _eLocal3;
							} finally {
								try {
									if (
										! _cLocal &&
										( ( _nullableValue ) =>
											_nullableValue !== null &&
											_nullableValue !== void 0 )(
											_nLocal.return
										) &&
										( ( _iLocal = _nLocal.return() ),
										Object( _iLocal ) !== _iLocal )
									) {
										return;
									}
								} finally {
									if ( _uLocal ) {
										throw _aLocal;
									}
								}
							}
							return _lLocal;
						}
					} )( e, _tLocal ) ||
					( function ( _eLocal4, _tLocal3 ) {
						if ( _eLocal4 ) {
							if ( 'string' === typeof _eLocal4 ) {
								return $K( _eLocal4, _tLocal3 );
							}
							var _nLocal2 = {}.toString
								.call( _eLocal4 )
								.slice( 8, -1 );
							return (
								'Object' === _nLocal2 &&
									_eLocal4.constructor &&
									( _nLocal2 = _eLocal4.constructor.name ),
								( () => {
									if (
										'Map' === _nLocal2 ||
										'Set' === _nLocal2
									) {
										return Array.from( _eLocal4 );
									}
									if (
										'Arguments' === _nLocal2 ||
										/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(
											_nLocal2
										)
									) {
										return $K( _eLocal4, _tLocal3 );
									}
									return void 0;
								} )()
							 );
						}
					} )( e, _tLocal ) ||
					( function () {
						throw new TypeError(
							'Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
						);
					} )()
				);
			} )( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			p = m[ 0 ],
			f = m[ 1 ],
			v = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getAccountPrivacySettings();
			}, [] );
		return (
			( 0, ReactHooks.useEffect )( function () {
				var e = ( function () {
					var _eLocal5,
						_tLocal4 =
							( ( _eLocal5 = YK().m( function _eLocal6() {
								var _tLocal5;
								return YK().w( function ( _eLocal7 ) {
									for (;;) {
										switch ( _eLocal7.n ) {
											case 0:
												return (
													d.setLoadingSetting( ! 0 ),
													( _eLocal7.n = 1 ),
													l()( {
														path: 'ohmylms/v1/settings/account-and-privacy',
													} )
												 );
											case 1:
												_tLocal5 = _eLocal7.v;
												d.setAccountPrivacySettings(
													_tLocal5
												);
												d.setLoadingSetting( ! 1 ); // Falls through to the shared async state cleanup.
											case 2:
												return _eLocal7.a( 2 );
										}
									}
								}, _eLocal6 );
							} ) ),
							function ( ..._args ) {
								var _tLocal6 = this,
									_nLocal3 = _args;
								return new Promise( function (
									_rLocal2,
									_aLocal2
								) {
									var _oLocal2 = _eLocal5.apply(
										_tLocal6,
										_nLocal3
									);
									function _iLocal2( _eLocal8 ) {
										ZK(
											_oLocal2,
											_rLocal2,
											_aLocal2,
											_iLocal2,
											_lLocal2,
											'next',
											_eLocal8
										);
									}
									function _lLocal2( _eLocal9 ) {
										ZK(
											_oLocal2,
											_rLocal2,
											_aLocal2,
											_iLocal2,
											_lLocal2,
											'throw',
											_eLocal9
										);
									}
									_iLocal2( void 0 );
								} );
							} );
					return function ( ..._args2 ) {
						return _tLocal4.apply( this, _args2 );
					};
				} )();
				e();
			}, [] ),
			(
				<React.Fragment>
					<Ea
						isBorderless={ ! 0 }
						variant={ 'secondary' }
						className={ 'ohmylms-full-screen-height' }
					>
						<Controls.SpacerWP
							padding={ 4 }
							marginTop={ 0 }
							marginBottom={ 0 }
						>
							<Controls.CardWP isBorderless={ ! 0 }>
								<Controls.SpacerWP
									paddingY={ 4 }
									paddingX={ 2 }
									marginTop={ 0 }
									marginBottom={ 4 }
								>
									<Controls.SpacerWP
										paddingX={ 4 }
										paddingTop={ 4 }
										paddingBottom={ 4 }
										marginBottom={ 0 }
									>
										{ React.createElement(
											zm,
											{
												title: ( 0, I18n.__ )(
													'Allow Guest Checkout',
													'ohmylms'
												),
												description: ( 0, I18n.__ )(
													'Allow visitors to purchase courses without creating an account first.',
													'ohmylms'
												),
												variant: 'v2',
												align: 'flex-start',
											},
											<Controls.SwitchWP
												checked={
													'yes' ===
													( v === null ||
													v === void 0 ||
													null ===
														( t =
															v.ohmylms_allow_purchase_without_login ) ||
													void 0 === t
														? void 0
														: t.value )
												}
												onChange={ function ( e ) {
													return ( function (
														_eLocal0
													) {
														d.updateAccountPrivacySettings(
															{
																ohmylms_allow_purchase_without_login:
																	{
																		value: _eLocal0
																			? 'yes'
																			: 'no',
																	},
															}
														);
													} )( e );
												} }
												isDefaultStyle={ ! 0 }
											/>
										) }
									</Controls.SpacerWP>
									<Controls.SpacerWP
										paddingX={ 4 }
										paddingTop={ 4 }
										paddingBottom={ 4 }
										marginBottom={ 0 }
									>
										{ React.createElement(
											zm,
											{
												title: ( 0, I18n.__ )(
													'Require Email Verification',
													'ohmylms'
												),
												description: ( 0, I18n.__ )(
													'Students must verify their email address before accessing course content.',
													'ohmylms'
												),
												variant: 'v2',
												align: 'flex-start',
											},
											<Controls.SwitchWP
												checked={
													'yes' ===
													( v === null ||
													v === void 0 ||
													null ===
														( n =
															v.ohmylms_require_email_verification ) ||
													void 0 === n
														? void 0
														: n.value )
												}
												onChange={ function ( e ) {
													return ( function (
														_eLocal1
													) {
														d.updateAccountPrivacySettings(
															{
																ohmylms_require_email_verification:
																	{
																		value: _eLocal1
																			? 'yes'
																			: 'no',
																	},
															}
														);
													} )( e );
												} }
												isDefaultStyle={ ! 0 }
											/>
										) }
									</Controls.SpacerWP>
									<Controls.SpacerWP
										paddingX={ 4 }
										paddingTop={ 4 }
										paddingBottom={ 4 }
										marginBottom={ 0 }
									>
										{ React.createElement(
											zm,
											{
												title: ( 0, I18n.__ )(
													'Checkout Phone Field',
													'ohmylms'
												),
												description: ( 0, I18n.__ )(
													'Show the billing phone number as optional, force it to be required, or hide it. Some payment gateways (e.g. Authorize.Net) can still make it required on their own.',
													'ohmylms'
												),
												variant: 'v2',
												align: 'flex-start',
											},
											<Controls.SelectWP
												value={
													( v === null ||
													v === void 0 ||
													null ===
														( r =
															v.ohmylms_checkout_phone_field ) ||
													void 0 === r
														? void 0
														: r.value ) ||
													'optional'
												}
												options={ [
													{
														label: ( 0, I18n.__ )(
															'Optional',
															'ohmylms'
														),
														value: 'optional',
													},
													{
														label: ( 0, I18n.__ )(
															'Required',
															'ohmylms'
														),
														value: 'required',
													},
													{
														label: ( 0, I18n.__ )(
															'Hidden',
															'ohmylms'
														),
														value: 'hidden',
													},
												] }
												onChange={ function ( e ) {
													return ( function (
														_eLocal10
													) {
														d.updateAccountPrivacySettings(
															{
																ohmylms_checkout_phone_field:
																	{
																		value: _eLocal10,
																	},
															}
														);
													} )( e );
												} }
											/>
										) }
									</Controls.SpacerWP>
									<Pf
										title={ ( 0, I18n.__ )(
											'Privacy Policy Message',
											'ohmylms'
										) }
										description={ ( 0, I18n.__ )(
											'Display a short note to assure students about your data privacy practices.',
											'ohmylms'
										) }
										inputType={ 'textarea' }
										onChange={ function ( e ) {
											d.updateAccountPrivacySettings( {
												ohmylms_privacy_policy_message:
													{
														value: e,
													},
											} );
										} }
										value={ Ge(
											v === null ||
												v === void 0 ||
												null ===
													( a =
														v.ohmylms_privacy_policy_message ) ||
												void 0 === a
												? void 0
												: a.value
										) }
									/>
								</Controls.SpacerWP>
							</Controls.CardWP>
							<MemoSettingsActionBar
								activeTab={ o }
								handleSave={ i }
								handleMigration={ c }
								selectedCourses={ u }
								isSaving={ s }
							/>
						</Controls.SpacerWP>
					</Ea>
					{ p && (
						<React.Fragment>
							<He.default isOpen={ p } onClose={ f } />
						</React.Fragment>
					) }
				</React.Fragment>
			 )
		 );
	};
}
