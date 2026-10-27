import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createSetupPreferences( readRuntime ) {
	return function SetupPreferences( props ) {
		const {
			Gte,
			I: Controls,
			React,
			T: StoreModule,
			b: I18n,
			cne,
			fne,
			g: ReactHooks,
			hB,
			ine,
			m,
			pne,
			sne,
			une,
			y: WordPressData,
		} = readRuntime();
		var t,
			n,
			r,
			a,
			o,
			i,
			l,
			c,
			u,
			s,
			d,
			p,
			f = props.onTabChange,
			v = props.onWizardSkip,
			h = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			_ = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getSetupWizardData();
			}, [] ),
			w = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getCurrencySettings();
			}, [] ),
			E = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getDesignSettings();
			}, [] ),
			S = fne( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			R = S[ 0 ],
			x = S[ 1 ],
			C = ( function () {
				var e = pne(
					sne().m( function _eLocal() {
						var _tLocal, _nLocal, _rLocal;
						return sne().w(
							function ( _eLocal2 ) {
								for (;;) {
									switch ( ( _eLocal2.p = _eLocal2.n ) ) {
										case 0:
											if ( _.certificate ) {
												_eLocal2.n = 1;
												break;
											}
											return _eLocal2.a( 2, null );
										case 1:
											if (
												( ( _eLocal2.p = 1 ),
												( _tLocal = hB.find(
													function ( _eLocal3 ) {
														return (
															_eLocal3.id ===
															_.certificate
														);
													}
												) ) )
											) {
												_eLocal2.n = 2;
												break;
											}
											return _eLocal2.a( 2, null );
										case 2:
											return (
												( _eLocal2.n = 3 ),
												m( {
													path: '/ohmylms/v1/certificates/',
													method: 'POST',
													data: {
														name: 'Certificate Template '.concat(
															_.certificate
														),
														status: 'publish',
														contents:
															_tLocal.contents,
														template_thumbnail:
															_tLocal.image_src,
													},
												} )
											 );
										case 3:
											return (
												( _nLocal = _eLocal2.v ),
												_eLocal2.a(
													2,
													( _nLocal === null ||
													_nLocal === void 0
														? void 0
														: _nLocal.id ) || null
												)
											 );
										case 4:
											return (
												( _eLocal2.p = 4 ),
												( _rLocal = _eLocal2.v ),
												showRequestError(
													'Error creating certificate:',
													_rLocal
												),
												_eLocal2.a( 2, null )
											 );
									}
								}
							},
							_eLocal,
							null,
							[ [ 1, 4 ] ]
						);
					} )
				);
				return function ( ..._args ) {
					return e.apply( this, _args );
				};
			} )(),
			P = ( function () {
				var e = pne(
					sne().m( function _eLocal4( _tLocal2 ) {
						var _nLocal2, _rLocal2, _aLocal, _oLocal;
						return sne().w(
							function ( _eLocal5 ) {
								for (;;) {
									switch ( ( _eLocal5.p = _eLocal5.n ) ) {
										case 0:
											if (
												_tLocal2 &&
												'others' !== _tLocal2 &&
												une[ _tLocal2 ]
											) {
												_eLocal5.n = 1;
												break;
											}
											return _eLocal5.a( 2 );
										case 1:
											return (
												( _eLocal5.p = 1 ),
												( _rLocal2 =
													une[ _tLocal2 ].data ),
												( _eLocal5.n = 2 ),
												m( {
													path: '/ohmylms/v1/setup-wizard/import-course',
													method: 'POST',
													data: _rLocal2,
													headers: {
														nonce: window
															.ohmylms_params
															.setup_wizard_nonce,
													},
												} )
											 );
										case 2:
											if (
												( ( _nullableValue ) =>
													_nullableValue !== null &&
													_nullableValue !== void 0 )(
													( _aLocal = _eLocal5.v )
												) &&
												null !==
													( _nLocal2 =
														_aLocal.course_ids ) &&
												void 0 !== _nLocal2 &&
												_nLocal2.length
											) {
												h.setSetupWizardData( {
													imported_course_ids:
														_aLocal.course_ids,
												} );
											}
											_eLocal5.n = 4;
											break;
										case 3:
											_eLocal5.p = 3;
											_oLocal = _eLocal5.v;
											showRequestError(
												'Error importing sample course:',
												_oLocal
											);
										// Falls through to the shared async state cleanup.
										case 4:
											return _eLocal5.a( 2 );
									}
								}
							},
							_eLocal4,
							null,
							[ [ 1, 3 ] ]
						);
					} )
				);
				return function ( ..._args7 ) {
					return e.apply( this, _args7 );
				};
			} )(),
			O = ( function () {
				var e = pne(
					sne().m( function _eLocal6() {
						var _tLocal4,
							_nLocal3,
							_rLocal3,
							_aLocal2,
							_oLocal2,
							_iLocal,
							_lLocal,
							_cLocal,
							_uLocal,
							_sLocal,
							_dLocal,
							_mLocal;
						return sne().w(
							function ( _eLocal7 ) {
								for (;;) {
									switch ( ( _eLocal7.p = _eLocal7.n ) ) {
										case 0:
											return (
												( _eLocal7.p = 0 ),
												( _eLocal7.n = 1 ),
												C()
											 );
										case 1:
											return (
												( _sLocal = _eLocal7.v ),
												( _dLocal = {
													optin: {
														ohmylms_allow_tracking:
															_ !== null &&
															_ !== void 0 &&
															_.isOptEnabled
																? 'yes'
																: 'no',
													},
													language:
														null !==
															( _tLocal4 =
																_.language ) &&
														void 0 !== _tLocal4
															? _tLocal4
															: 'en_US',
													certificate: _.certificate,
													certificate_id: _sLocal,
													niche: _.niche
														? [ _.niche ]
														: [],
													level: _.level,
													currency: {
														ohmylms_currency:
															( () => {
																if (
																	null !==
																		( _nLocal3 =
																			_.currency ) &&
																	void 0 !==
																		_nLocal3
																) {
																	return _nLocal3;
																}
																if (
																	w ===
																		null ||
																	w ===
																		void 0 ||
																	null ===
																		( _rLocal3 =
																			w.ohmylms_currency ) ||
																	void 0 ===
																		_rLocal3
																) {
																	return void 0;
																}
																return _rLocal3.value;
															} )(),
														ohmylms_currency_pos:
															( w === null ||
															w === void 0 ||
															null ===
																( _aLocal2 =
																	w.ohmylms_currency_pos ) ||
															void 0 === _aLocal2
																? void 0
																: _aLocal2.value ) ||
															'left',
														ohmylms_price_thousand_sep:
															( w === null ||
															w === void 0 ||
															null ===
																( _oLocal2 =
																	w.ohmylms_price_thousand_sep ) ||
															void 0 === _oLocal2
																? void 0
																: _oLocal2.value ) ||
															',',
														ohmylms_price_decimal_sep:
															( w === null ||
															w === void 0 ||
															null ===
																( _iLocal =
																	w.ohmylms_price_decimal_sep ) ||
															void 0 === _iLocal
																? void 0
																: _iLocal.value ) ||
															'.',
														ohmylms_price_num_decimals:
															( w === null ||
															w === void 0 ||
															null ===
																( _lLocal =
																	w.ohmylms_price_num_decimals ) ||
															void 0 === _lLocal
																? void 0
																: _lLocal.value ) ||
															'2',
													},
													contact: {
														email: ( () => {
															if (
																_ !== null &&
																_ !== void 0 &&
																_.isOptEnabled
															) {
																if (
																	null ===
																		( _cLocal =
																			window.ohmylms_params ) ||
																	void 0 ===
																		_cLocal
																) {
																	return void 0;
																}
																return _cLocal.admin_email;
															}
															return '';
														} )(),
														name: ( () => {
															if (
																_ !== null &&
																_ !== void 0 &&
																_.isOptEnabled
															) {
																if (
																	null ===
																		( _uLocal =
																			window.ohmylms_params ) ||
																	void 0 ===
																		_uLocal
																) {
																	return void 0;
																}
																return _uLocal.admin_name;
															}
															return '';
														} )(),
													},
													wizard_data: _,
												} ),
												( _eLocal7.n = 2 ),
												h.saveSetup( _dLocal )
											 );
										case 2:
											return (
												( _eLocal7.n = 3 ),
												P(
													_ === null || _ === void 0
														? void 0
														: _.niche
												)
											 );
										case 3:
											if ( _sLocal ) {
												h.setSetupWizardData( {
													certificate_id: _sLocal,
												} );
											}
											_eLocal7.n = 5;
											break;
										case 4:
											_eLocal7.p = 4;
											_mLocal = _eLocal7.v;
											showRequestError(
												'Error saving setup wizard data:',
												_mLocal
											);
										// Falls through to the shared async state cleanup.
										case 5:
											return _eLocal7.a( 2 );
									}
								}
							},
							_eLocal6,
							null,
							[ [ 0, 4 ] ]
						);
					} )
				);
				return function ( ..._args2 ) {
					return e.apply( this, _args2 );
				};
			} )(),
			k = ( function () {
				var e = pne(
					sne().m( function _eLocal8() {
						var _tLocal5, _nLocal4, _rLocal4, _aLocal3;
						return sne().w(
							function ( _eLocal9 ) {
								for (;;) {
									switch ( ( _eLocal9.p = _eLocal9.n ) ) {
										case 0:
											return (
												( _eLocal9.p = 0 ),
												( _rLocal4 = {
													optin: {
														ohmylms_allow_tracking:
															_ !== null &&
															_ !== void 0 &&
															_.isOptEnabled
																? 'yes'
																: 'no',
													},
													niche: _.niche
														? [ _.niche ]
														: [],
													level: _.level,
													contact: {
														email: ( () => {
															if (
																_ !== null &&
																_ !== void 0 &&
																_.isOptEnabled
															) {
																if (
																	null ===
																		( _tLocal5 =
																			window.ohmylms_params ) ||
																	void 0 ===
																		_tLocal5
																) {
																	return void 0;
																}
																return _tLocal5.admin_email;
															}
															return '';
														} )(),
														name: ( () => {
															if (
																_ !== null &&
																_ !== void 0 &&
																_.isOptEnabled
															) {
																if (
																	null ===
																		( _nLocal4 =
																			window.ohmylms_params ) ||
																	void 0 ===
																		_nLocal4
																) {
																	return void 0;
																}
																return _nLocal4.admin_name;
															}
															return '';
														} )(),
													},
													wizard_data: _,
												} ),
												( _eLocal9.n = 1 ),
												h.saveSetup( _rLocal4 )
											 );
										case 1:
											return (
												( _eLocal9.n = 2 ),
												P(
													_ === null || _ === void 0
														? void 0
														: _.niche
												)
											 );
										case 2:
											_eLocal9.n = 4;
											break;
										case 3:
											_eLocal9.p = 3;
											_aLocal3 = _eLocal9.v;
											showRequestError(
												'Error saving setup wizard data:',
												_aLocal3
											);
										// Falls through to the shared async state cleanup.
										case 4:
											return _eLocal9.a( 2 );
									}
								}
							},
							_eLocal8,
							null,
							[ [ 0, 3 ] ]
						);
					} )
				);
				return function ( ..._args3 ) {
					return e.apply( this, _args3 );
				};
			} )(),
			j = ( function () {
				var e = pne(
					sne().m( function _eLocal0() {
						return sne().w( function ( _eLocal1 ) {
							for (;;) {
								switch ( _eLocal1.n ) {
									case 0:
										if ( ! R ) {
											_eLocal1.n = 1;
											break;
										}
										return _eLocal1.a( 2 );
									case 1:
										if ( 'beginner' !== _.level ) {
											_eLocal1.n = 3;
											break;
										}
										return (
											x( ! 0 ),
											( _eLocal1.n = 2 ),
											O()
										 );
									case 2:
										x( ! 1 );
									// Falls through to the shared async state cleanup.
									case 3:
										F();
									// Falls through to the shared async state cleanup.
									case 4:
										return _eLocal1.a( 2 );
								}
							}
						}, _eLocal0 );
					} )
				);
				return function ( ..._args4 ) {
					return e.apply( this, _args4 );
				};
			} )(),
			A = ( function () {
				var e = pne(
					sne().m( function _eLocal10() {
						return sne().w( function ( _eLocal11 ) {
							for (;;) {
								switch ( _eLocal11.n ) {
									case 0:
										return (
											( _eLocal11.n = 1 ),
											h.setSetupWizardData( {
												currency: void 0,
												language: void 0,
												archive_page_layout: void 0,
												courses_per_row: void 0,
												courses_per_page: void 0,
												certificate_enabled: void 0,
												certificate: void 0,
											} )
										 );
									case 1:
										return _eLocal11.a( 2 );
								}
							}
						}, _eLocal10 );
					} )
				);
				return function ( ..._args5 ) {
					return e.apply( this, _args5 );
				};
			} )(),
			M = ( function () {
				var e = pne(
					sne().m( function _eLocal12() {
						return sne().w( function ( _eLocal13 ) {
							for (;;) {
								switch ( _eLocal13.n ) {
									case 0:
										return ( ( _eLocal13.n = 1 ), A() );
									case 1:
										if ( 'beginner' === _.level ) {
											k();
										}
										F();
									// Falls through to the shared async state cleanup.
									case 2:
										return _eLocal13.a( 2 );
								}
							}
						}, _eLocal12 );
					} )
				);
				return function ( ..._args6 ) {
					return e.apply( this, _args6 );
				};
			} )(),
			F = function () {
				if (
					'beginner' ===
					( _ === null || _ === void 0 ? void 0 : _.level )
				) {
					f( 'wizard-completion' );
				} else {
					f( 'wizard-niche' );
				}
			},
			N = [
				{
					label: ( 0, I18n.__ )(
						'English (United States)',
						'ohmylms'
					),
					value: 'en_US',
				},
				{
					label: ( 0, I18n.__ )( 'Spanish (Spain)', 'ohmylms' ),
					value: 'es_ES',
				},
				{
					label: ( 0, I18n.__ )( 'French (France)', 'ohmylms' ),
					value: 'fr_FR',
				},
				{
					label: ( 0, I18n.__ )( 'German (Germany)', 'ohmylms' ),
					value: 'de_DE',
				},
				{
					label: ( 0, I18n.__ )( 'Italian (Italy)', 'ohmylms' ),
					value: 'it_IT',
				},
				{
					label: ( 0, I18n.__ )( 'Portuguese (Brazil)', 'ohmylms' ),
					value: 'pt_BR',
				},
				{
					label: ( 0, I18n.__ )( 'Dutch (Netherlands)', 'ohmylms' ),
					value: 'nl_NL',
				},
				{
					label: ( 0, I18n.__ )( 'Russian (Russia)', 'ohmylms' ),
					value: 'ru_RU',
				},
				{
					label: ( 0, I18n.__ )( 'Chinese (Simplified)', 'ohmylms' ),
					value: 'zh_CN',
				},
				{
					label: ( 0, I18n.__ )( 'Japanese (Japan)', 'ohmylms' ),
					value: 'ja',
				},
				{
					label: ( 0, I18n.__ )( 'Korean (Korea)', 'ohmylms' ),
					value: 'ko_KR',
				},
				{
					label: ( 0, I18n.__ )( 'Arabic (Saudi Arabia)', 'ohmylms' ),
					value: 'ar',
				},
				{
					label: ( 0, I18n.__ )( 'Hindi (India)', 'ohmylms' ),
					value: 'hi_IN',
				},
				{
					label: ( 0, I18n.__ )( 'Bengali (Bangladesh)', 'ohmylms' ),
					value: 'bn_BD',
				},
				{
					label: ( 0, I18n.__ )( 'Turkish (Turkey)', 'ohmylms' ),
					value: 'tr_TR',
				},
			],
			D = ( function () {
				var e = pne(
					sne().m( function _eLocal14( _tLocal6 ) {
						var _nLocal5, _rLocal5, _aLocal4, _oLocal3;
						return sne().w( function ( _eLocal15 ) {
							for (;;) {
								if ( 0 === _eLocal15.n ) {
									return (
										( _rLocal5 = Object.entries(
											w === null ||
												w === void 0 ||
												null ===
													( _nLocal5 =
														w.ohmylms_currency ) ||
												void 0 === _nLocal5
												? void 0
												: _nLocal5.options
										).map( function ( _eLocal16 ) {
											var _tLocal7 = fne( _eLocal16, 2 ),
												_nLocal6 = _tLocal7[ 0 ];
											return {
												label: _tLocal7[ 1 ],
												value: _nLocal6,
											};
										} ) ),
										( _aLocal4 = _rLocal5.filter(
											function ( _eLocal17 ) {
												return _eLocal17.label
													.toLowerCase()
													.includes(
														_tLocal6.toLowerCase()
													);
											}
										) ),
										( _oLocal3 = _aLocal4.map(
											function ( _eLocal18 ) {
												return {
													label: (
														<span
															dangerouslySetInnerHTML={ {
																__html: _eLocal18.label,
															} }
														/>
													),
													value: _eLocal18.value,
												};
											}
										) ),
										_eLocal15.a( 2, _oLocal3 )
									 );
								}
							}
						}, _eLocal14 );
					} )
				);
				return function ( ..._args8 ) {
					return e.apply( this, _args8 );
				};
			} )();
		return (
			( 0, ReactHooks.useEffect )( function () {
				var e;
				if ( ! (
					_ !== null &&
					_ !== void 0 &&
					_.archive_page_layout
				) ) {
					h.setSetupWizardData( {
						archive_page_layout:
							( null === ( e = E.ohmylms_archive_page_layout ) ||
							void 0 === e
								? void 0
								: e.value ) || 'grid',
					} );
				}
			}, [] ),
			(
				<React.Fragment>
					<Gte
						level={ _ === null || _ === void 0 ? void 0 : _.level }
						currentStep={
							'experienced' ===
								( _ === null || _ === void 0
									? void 0
									: _.level ) ||
							'intermediate' ===
								( _ === null || _ === void 0
									? void 0
									: _.level )
								? 0
								: 1
						}
						isShowIndicator={ ! 0 }
						onSkip={ function () {
							return v === null || v === void 0
								? void 0
								: v( 'preferences' );
						} }
					/>
					<Controls.ContainerWP>
						<div
							className={
								'ohmylms-setup-wizard-level-selection-wrapper ohmylms-setup-wizard-card-wrapper ohmylms-preference-screen-wrapper'
							}
						>
							<div
								className={ 'ohmylms-setup-wizard__container' }
								style={ {
									gap: '0',
								} }
							>
								<div
									className={ 'ohmylms-setup-wizard__header' }
								>
									<Controls.HeadingWP
										as={ 'h2' }
										color={ '#000d25' }
										size={ '24' }
										align={ 'center' }
										weight={ '600' }
									>
										{ ( 0, I18n.__ )(
											"Let's set your preferences",
											'ohmylms'
										) }
									</Controls.HeadingWP>
									<Controls.TextWP
										as={ 'p' }
										size={ '18' }
										color={ '#687784' }
										align={ 'center' }
										weight={ '400' }
										style={ {
											maxWidth: '400px',
											margin: 'auto',
										} }
									>
										{ ( 0, I18n.__ )(
											"Choose what works best for your region. We'll take care of the rest.",
											'ohmylms'
										) }
									</Controls.TextWP>
								</div>
								<Controls.CardWP
									isBorderless={ ! 0 }
									style={ {
										width: '788px',
										margin: '56px auto 0 auto',
									} }
								>
									<Controls.SpacerWP
										padding={ 6 }
										marginBottom={ 0 }
									>
										<Controls.FlexWP
											direction={ 'column' }
											gap={ 6 }
										>
											<Controls.FlexWP
												gap={ 8 }
												align={ 'center' }
												justify={ 'space-between' }
											>
												<Controls.FlexItemWP
													isBlock={ ! 0 }
												>
													<Controls.FlexWP
														align={ 'center' }
														gap={ '1' }
														justify={ 'flex-start' }
													>
														<Controls.HeadingWP
															level={ '4' }
														>
															{ ( 0, I18n.__ )(
																'Language',
																'ohmylms'
															) }
														</Controls.HeadingWP>
													</Controls.FlexWP>
													<Controls.SpacerWP
														marginBottom={ 1 }
													/>
												</Controls.FlexItemWP>
												<Controls.FlexItemWP
													isBlock={ ! 0 }
												>
													<Controls.SearchSelectWP
														placeholder={ ( 0,
														I18n.__ )(
															'Select Language',
															'ohmylms'
														) }
														onChange={ function (
															e
														) {
															h.setSetupWizardData(
																{
																	language:
																		e.value,
																}
															);
														} }
														value={ [
															N.find(
																function ( e ) {
																	return (
																		e.value ===
																		( ( _ ===
																			null ||
																		_ ===
																			void 0
																			? void 0
																			: _.language ) ||
																			'en_US' )
																	);
																}
															) || N[ 0 ],
														] }
														defaultOptions={ N }
														isClearable={ ! 1 }
														isSearchable={ ! 0 }
														isMulti={ ! 1 }
													/>
												</Controls.FlexItemWP>
											</Controls.FlexWP>
											<Controls.FlexWP
												gap={ 8 }
												align={ 'center' }
												justify={ 'space-between' }
											>
												<Controls.FlexItemWP
													isBlock={ ! 0 }
												>
													<Controls.FlexWP
														align={ 'center' }
														gap={ '1' }
														justify={ 'flex-start' }
													>
														<Controls.HeadingWP
															level={ '4' }
														>
															{ ( 0, I18n.__ )(
																'Currency Symbol',
																'ohmylms'
															) }
														</Controls.HeadingWP>
													</Controls.FlexWP>
													<Controls.SpacerWP
														marginBottom={ 1 }
													/>
												</Controls.FlexItemWP>
												<Controls.FlexItemWP
													isBlock={ ! 0 }
												>
													<Controls.SearchSelectWP
														placeholder={ ( 0,
														I18n.__ )(
															'Type to Select Currency',
															'ohmylms'
														) }
														onChange={ function (
															e
														) {
															h.setSetupWizardData(
																{
																	currency:
																		e.value,
																}
															);
														} }
														value={ [
															{
																label: (
																	<span
																		dangerouslySetInnerHTML={ {
																			__html: ( () => {
																				if (
																					null !==
																						( t =
																							w ===
																								null ||
																							w ===
																								void 0 ||
																							null ===
																								( n =
																									w.ohmylms_currency ) ||
																							void 0 ===
																								n
																								? void 0
																								: n
																										.options[
																										_ ===
																											null ||
																										_ ===
																											void 0
																											? void 0
																											: _.currency
																									] ) &&
																					void 0 !==
																						t
																				) {
																					return t;
																				}
																				if (
																					w ===
																						null ||
																					w ===
																						void 0 ||
																					null ===
																						( r =
																							w.ohmylms_currency ) ||
																					void 0 ===
																						r
																				) {
																					return void 0;
																				}
																				return r
																					.options[
																					w ===
																						null ||
																					w ===
																						void 0 ||
																					null ===
																						( a =
																							w.ohmylms_currency ) ||
																					void 0 ===
																						a
																						? void 0
																						: a.value
																				];
																			} )(),
																		} }
																	/>
																),
																value: ( () => {
																	if (
																		null !==
																			( o =
																				_ ===
																					null ||
																				_ ===
																					void 0
																					? void 0
																					: _.currency ) &&
																		void 0 !==
																			o
																	) {
																		return o;
																	}
																	if (
																		w ===
																			null ||
																		w ===
																			void 0 ||
																		null ===
																			( i =
																				w.ohmylms_currency ) ||
																		void 0 ===
																			i
																	) {
																		return void 0;
																	}
																	return i.value;
																} )(),
															},
														] }
														defaultOptions={
															( ( p =
																w === null ||
																w === void 0 ||
																null ===
																	( l =
																		w.ohmylms_currency ) ||
																void 0 === l
																	? void 0
																	: l.options ),
															p
																? Object.entries(
																		p
																	).map(
																		function (
																			e
																		) {
																			var _tLocal9 =
																					fne(
																						e,
																						2
																					),
																				_nLocal7 =
																					_tLocal9[ 0 ],
																				_rLocal6 =
																					_tLocal9[ 1 ];
																			return {
																				label: (
																					<span
																						dangerouslySetInnerHTML={ {
																							__html: _rLocal6,
																						} }
																					/>
																				),
																				value: _nLocal7,
																			};
																		}
																	)
																: [] )
														}
														loadOptions={ D }
														isClearable={ ! 1 }
														isSearchable={ ! 0 }
														isMulti={ ! 1 }
													/>
												</Controls.FlexItemWP>
											</Controls.FlexWP>
										</Controls.FlexWP>
									</Controls.SpacerWP>
								</Controls.CardWP>
								{ 'beginner' !== _.level && (
									<Controls.CardWP
										isBorderless={ ! 0 }
										style={ {
											width: '788px',
											margin: '24px auto 0 auto',
										} }
									>
										<Controls.SpacerWP
											padding={ 6 }
											marginBottom={ 0 }
										>
											<Controls.FlexWP
												direction={ 'column' }
												gap={ 6 }
											>
												<Controls.FlexWP
													gap={ 8 }
													align={ 'center' }
													justify={ 'space-between' }
												>
													<Controls.FlexItemWP
														isBlock={ ! 0 }
													>
														<Controls.FlexWP
															align={ 'center' }
															gap={ '1' }
															justify={
																'flex-start'
															}
														>
															<Controls.HeadingWP
																level={ '4' }
															>
																{ ( 0,
																I18n.__ )(
																	'Archive Page Layout',
																	'ohmylms'
																) }
															</Controls.HeadingWP>
														</Controls.FlexWP>
														<Controls.SpacerWP
															marginBottom={ 1 }
														/>
													</Controls.FlexItemWP>
													<Controls.FlexItemWP
														isBlock={ ! 0 }
													>
														<Controls.FlexWP
															align={ 'flex-end' }
															justify={
																'flex-end'
															}
														>
															<Controls.RadioGroupWP
																onChange={ function (
																	e
																) {
																	h.setSetupWizardData(
																		{
																			archive_page_layout:
																				e,
																		}
																	);
																} }
																value={ ( () => {
																	if (
																		null !==
																			( c =
																				_ ===
																					null ||
																				_ ===
																					void 0
																					? void 0
																					: _.archive_page_layout ) &&
																		void 0 !==
																			c
																	) {
																		return c;
																	}
																	if (
																		null ===
																			( u =
																				E.ohmylms_archive_page_layout ) ||
																		void 0 ===
																			u
																	) {
																		return void 0;
																	}
																	return u.value;
																} )() }
																options={ [
																	{
																		value: 'grid',
																		label: ( 0,
																		I18n.__ )(
																			'Grid View',
																			'ohmylms'
																		),
																	},
																	{
																		value: 'list',
																		label: ( 0,
																		I18n.__ )(
																			'List View',
																			'ohmylms'
																		),
																	},
																] }
																isBlock={ ! 1 }
															/>
														</Controls.FlexWP>
													</Controls.FlexItemWP>
												</Controls.FlexWP>
												{ 'list' !==
													( _ === null || _ === void 0
														? void 0
														: _.archive_page_layout ) && (
													<React.Fragment>
														{ React.createElement(
															cne,
															{
																onChange( e ) {
																	h.setSetupWizardData(
																		{
																			courses_per_row:
																				e,
																		}
																	);
																},
																defaultValue:
																	( () => {
																		if (
																			null !==
																				( s =
																					_ ===
																						null ||
																					_ ===
																						void 0
																						? void 0
																						: _.courses_per_row ) &&
																			void 0 !==
																				s
																		) {
																			return s;
																		}
																		if (
																			null ===
																				( d =
																					E.ohmylms_columns_per_row ) ||
																			void 0 ===
																				d
																		) {
																			return void 0;
																		}
																		return d.value;
																	} )(),
															}
														) }
													</React.Fragment>
												) }
											</Controls.FlexWP>
										</Controls.SpacerWP>
									</Controls.CardWP>
								) }
								<Controls.CardWP
									isBorderless={ ! 0 }
									style={ {
										width: '788px',
										margin: '24px auto 0 auto',
									} }
								>
									<Controls.SpacerWP
										padding={ 6 }
										marginBottom={ 0 }
									>
										<Controls.FlexWP
											direction={ 'column' }
											gap={ 6 }
										>
											<Controls.FlexWP
												gap={ 8 }
												align={ 'center' }
												justify={ 'space-between' }
											>
												<Controls.FlexItemWP
													isBlock={ ! 0 }
												>
													<Controls.FlexWP
														align={ 'center' }
														gap={ '1' }
														justify={ 'flex-start' }
													>
														<Controls.HeadingWP
															level={ '4' }
														>
															{ ( 0, I18n.__ )(
																'Enable certificate',
																'ohmylms'
															) }
														</Controls.HeadingWP>
													</Controls.FlexWP>
													<Controls.SpacerWP
														marginBottom={ 1 }
													/>
												</Controls.FlexItemWP>
												<Controls.FlexItemWP
													isBlock={ ! 0 }
												>
													<Controls.FlexWP
														align={ 'flex-end' }
														justify={ 'flex-end' }
													>
														<Controls.SwitchWP
															checked={
																'yes' ===
																( _ === null ||
																_ === void 0
																	? void 0
																	: _.certificate_enabled )
															}
															onChange={ function (
																e
															) {
																var _tLocal0 = {
																	certificate_enabled:
																		e
																			? 'yes'
																			: 'no',
																};
																if (
																	e &&
																	! _.certificate
																) {
																	_tLocal0.certificate =
																		hB[ 0 ].id;
																}
																h.setSetupWizardData(
																	_tLocal0
																);
															} }
														/>
													</Controls.FlexWP>
												</Controls.FlexItemWP>
											</Controls.FlexWP>
											{ 'yes' ===
												( _ === null || _ === void 0
													? void 0
													: _.certificate_enabled ) &&
												React.createElement(
													ine,
													null
												) }
										</Controls.FlexWP>
									</Controls.SpacerWP>
								</Controls.CardWP>
							</div>
						</div>
						<Controls.SpacerWP
							marginBottom={ 4 }
							marginTop={ 6 }
							paddingY={ 4 }
						>
							<Controls.FlexWP
								items={ 'center' }
								justify={ 'between' }
								gap={ 4 }
								style={ {
									maxWidth: '846px',
									justifyContent: 'space-between',
									margin: '0 auto',
								} }
							>
								<Controls.ButtonWP
									variant={ 'secondary' }
									onClick={ function () {
										if (
											'beginner' ===
											( _ === null || _ === void 0
												? void 0
												: _.level )
										) {
											f( 'wizard-niche' );
										} else {
											f( 'wizard-level-selection' );
										}
									} }
									disabled={ R }
								>
									{ ( 0, I18n.__ )( 'Back', 'ohmylms' ) }
								</Controls.ButtonWP>
								<Controls.FlexWP
									items={ 'center' }
									justify={ 'end' }
									gap={ 6 }
								>
									<Controls.ButtonWP
										variant={ 'tertiary' }
										onClick={ M }
										disabled={ R }
									>
										{ ( 0, I18n.__ )(
											'Skip this step',
											'ohmylms'
										) }
									</Controls.ButtonWP>
									<Controls.ButtonWP
										variant={ 'primary' }
										onClick={ j }
										isBusy={ R }
									>
										{ ( 0, I18n.__ )(
											'Continue',
											'ohmylms'
										) }
									</Controls.ButtonWP>
								</Controls.FlexWP>
							</Controls.FlexWP>
						</Controls.SpacerWP>
					</Controls.ContainerWP>
				</React.Fragment>
			 )
		 );
	};
}
