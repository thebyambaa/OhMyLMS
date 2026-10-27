import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createSetupWizardController( readRuntime ) {
	return function SetupWizardController() {
		const {
			Fne: MemoSetupCompletion,
			Nte,
			React,
			T: StoreModule,
			Zte: MemoSetupWelcome,
			ane: MemoSetupLevelSelection,
			b: I18n,
			cre: MemoCourseImport,
			f: Router,
			g: ReactHooks,
			hne: MemoSetupPreferences,
			jne: MemoSetupNiche,
			m,
			mre,
			pre,
			ure,
			vre,
			y: WordPressData,
		} = readRuntime();
		var e = pre( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			t = e[ 0 ],
			n = e[ 1 ],
			r = pre( ( 0, ReactHooks.useState )( 'welcome' ), 2 ),
			a = r[ 0 ],
			o = r[ 1 ],
			i = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			l =
				( ( 0, WordPressData.useSelect )( function ( _eLocal ) {
					return _eLocal( StoreModule.default ).getSetupWizardData();
				}, [] ),
				( 0, Router.Zp )() ),
			c = function ( _eLocal2 ) {
				o( vre[ _eLocal2 ] || _eLocal2 );
			},
			u = ( function () {
				var _eLocal3 = mre(
					ure().m( function _eLocal4( _tLocal ) {
						var _nLocal;
						return ure().w(
							function ( _eLocal5 ) {
								for (;;) {
									switch ( ( _eLocal5.p = _eLocal5.n ) ) {
										case 0:
											return (
												( _eLocal5.p = 0 ),
												( _eLocal5.n = 1 ),
												m( {
													path: 'ohmylms/v1/setup-wizard/onboarding-skipped',
													method: 'POST',
													data: {
														step: _tLocal,
													},
													headers: {
														nonce: window
															.ohmylms_params
															.setup_wizard_nonce,
													},
												} )
											 );
										case 1:
											_eLocal5.n = 3;
											break;
										case 2:
											_eLocal5.p = 2;
											_nLocal = _eLocal5.v;
											showRequestError(
												'Failed to track onboarding skipped:',
												_nLocal
											);
										// Falls through to the shared async state cleanup.
										case 3:
											l( '/dashboard' );
										// Falls through to the shared async state cleanup.
										case 4:
											return _eLocal5.a( 2 );
									}
								}
							},
							_eLocal4,
							null,
							[ [ 0, 2 ] ]
						);
					} )
				);
				return function ( ..._args3 ) {
					return _eLocal3.apply( this, _args3 );
				};
			} )();
		return (
			( 0, ReactHooks.useEffect )( function () {
				m( {
					path: 'ohmylms/v1/setup-wizard/onboarding-started',
					method: 'POST',
					headers: {
						nonce: window.ohmylms_params.setup_wizard_nonce,
					},
				} ).catch( function ( _eLocal6 ) {
					showRequestError(
						'Failed to track onboarding started:',
						_eLocal6
					);
				} );
			}, [] ),
			( 0, ReactHooks.useEffect )( function () {
				var _eLocal7 = ( function () {
					var _eLocal8 = mre(
						ure().m( function _eLocal9() {
							var _tLocal3, _rLocal, _aLocal, _oLocal;
							return ure().w(
								function ( _eLocal0 ) {
									for (;;) {
										switch ( ( _eLocal0.p = _eLocal0.n ) ) {
											case 0:
												return (
													( _eLocal0.n = 1 ),
													( 0, Nte.nb )( {
														plugin: 'ohmylms',
														version: '1.1.16',
														theme: {
															color: '#6E42D3',
														},
														steps: [
															{
																id: 'welcome',
																title: ( 0,
																I18n.__ )(
																	'Welcome',
																	'ohmylms'
																),
																canSkip: ! 1,
																canGoBack: ! 1,
															},
															{
																id: 'level-selection',
																title: ( 0,
																I18n.__ )(
																	'Experience Level',
																	'ohmylms'
																),
																canSkip: ! 1,
																canGoBack: ! 0,
															},
															{
																id: 'preferences',
																title: ( 0,
																I18n.__ )(
																	'Preferences',
																	'ohmylms'
																),
																canSkip: ! 1,
																canGoBack: ! 0,
															},
															{
																id: 'niche',
																title: ( 0,
																I18n.__ )(
																	'Niche',
																	'ohmylms'
																),
																canSkip: ! 1,
																canGoBack: ! 0,
															},
															{
																id: 'course-creation',
																title: ( 0,
																I18n.__ )(
																	'Course Import',
																	'ohmylms'
																),
																canSkip: ! 0,
																canGoBack: ! 0,
															},
															{
																id: 'completion',
																title: ( 0,
																I18n.__ )(
																	'Complete',
																	'ohmylms'
																),
																canSkip: ! 1,
																canGoBack: ! 1,
															},
														],
														firstStrike: {
															label: ( 0,
															I18n.__ )(
																'Setup Wizard Completed',
																'ohmylms'
															),
															verify: ( function () {
																var _eLocal1 =
																	mre(
																		ure().m(
																			function _eLocal10() {
																				var _tLocal4;
																				return ure().w(
																					function (
																						_eLocal11
																					) {
																						for (;;) {
																							if (
																								0 ===
																								_eLocal11.n
																							) {
																								return (
																									( _tLocal4 =
																										Nte.Hi.getProgress() ),
																									_eLocal11.a(
																										2,
																										_tLocal4 &&
																											100 ===
																												_tLocal4.percent
																									)
																								 );
																							}
																						}
																					},
																					_eLocal10
																				);
																			}
																		)
																	);
																return function (
																	..._args
																) {
																	return _eLocal1.apply(
																		this,
																		_args
																	);
																};
															} )(),
															successRedirect:
																'/dashboard',
														},
														telemetry: {
															onSetupCompleted:
																( function () {
																	var _eLocal12 =
																		mre(
																			ure().m(
																				function _eLocal13() {
																					var _nLocal2;
																					return ure().w(
																						function (
																							_eLocal14
																						) {
																							for (;;) {
																								switch (
																									( _eLocal14.p =
																										_eLocal14.n )
																								) {
																									case 0:
																										return (
																											( _eLocal14.p = 0 ),
																											( _eLocal14.n = 1 ),
																											m(
																												{
																													path: 'ohmylms/v1/setup-wizard/onboarding-completed',
																													method: 'POST',
																													headers:
																														{
																															nonce: window
																																.ohmylms_params
																																.setup_wizard_nonce,
																														},
																												}
																											)
																										 );
																									case 1:
																										_eLocal14.n = 3;
																										break;
																									case 2:
																										_eLocal14.p = 2;
																										_nLocal2 =
																											_eLocal14.v;
																										showRequestError(
																											'Failed to track onboarding completed:',
																											_nLocal2
																										);
																									// Falls through to the shared async state cleanup.
																									case 3:
																										return _eLocal14.a(
																											2
																										);
																								}
																							}
																						},
																						_eLocal13,
																						null,
																						[
																							[
																								0,
																								2,
																							],
																						]
																					);
																				}
																			)
																		);
																	return function (
																		..._args4
																	) {
																		return _eLocal12.apply(
																			this,
																			_args4
																		);
																	};
																} )(),
															onFirstStrikeCompleted:
																( function () {
																	var _eLocal15 =
																		mre(
																			ure().m(
																				function _eLocal16() {
																					return ure().w(
																						function (
																							_eLocal17
																						) {
																							for (;;) {
																								switch (
																									_eLocal17.n
																								) {
																									case 0:
																										l(
																											'/dashboard'
																										);
																									// Falls through to the shared async state cleanup.
																									case 1:
																										return _eLocal17.a(
																											2
																										);
																								}
																							}
																						},
																						_eLocal16
																					);
																				}
																			)
																		);
																	return function (
																		..._args5
																	) {
																		return _eLocal15.apply(
																			this,
																			_args5
																		);
																	};
																} )(),
														},
													} )
												 );
											case 1:
												return (
													( _eLocal0.n = 2 ),
													Nte.Hi.start()
												 );
											case 2:
												return (
													n( ! 0 ),
													Nte.Ft.on(
														'onboarding_completed',
														( function () {
															var _eLocal18 = mre(
																ure().m(
																	function _eLocal19() {
																		var _nLocal3;
																		return ure().w(
																			function (
																				_eLocal20
																			) {
																				for (;;) {
																					switch (
																						( _eLocal20.p =
																							_eLocal20.n )
																					) {
																						case 0:
																							return (
																								( _eLocal20.p = 0 ),
																								( _eLocal20.n = 1 ),
																								m(
																									{
																										path: 'ohmylms/v1/setup-wizard/onboarding-completed',
																										method: 'POST',
																										headers:
																											{
																												nonce: window
																													.ohmylms_params
																													.setup_wizard_nonce,
																											},
																									}
																								)
																							 );
																						case 1:
																							_eLocal20.n = 3;
																							break;
																						case 2:
																							_eLocal20.p = 2;
																							_nLocal3 =
																								_eLocal20.v;
																							showRequestError(
																								'Failed to track onboarding completed:',
																								_nLocal3
																							);
																						// Falls through to the shared async state cleanup.
																						case 3:
																							return _eLocal20.a(
																								2
																							);
																					}
																				}
																			},
																			_eLocal19,
																			null,
																			[
																				[
																					0,
																					2,
																				],
																			]
																		);
																	}
																)
															);
															return function (
																..._args6
															) {
																return _eLocal18.apply(
																	this,
																	_args6
																);
															};
														} )()
													),
													i.setLoadingSetting( ! 0 ),
													( _eLocal0.p = 3 ),
													( _eLocal0.n = 4 ),
													m( {
														path: 'ohmylms/v1/settings/design',
													} )
												 );
											case 4:
												return (
													( _tLocal3 = _eLocal0.v ),
													i.setDesignSettings(
														_tLocal3
													),
													( _eLocal0.n = 5 ),
													m( {
														path: 'ohmylms/v1/settings/currency',
													} )
												 );
											case 5:
												_rLocal = _eLocal0.v;
												i.setCurrencySettings(
													_rLocal
												);
												_aLocal = {};
												if ( ! (
													_rLocal === null ||
													_rLocal === void 0
												) ) {
													_rLocal.forEach(
														function ( _eLocal21 ) {
															_aLocal[
																_eLocal21 ===
																	null ||
																_eLocal21 ===
																	void 0
																	? void 0
																	: _eLocal21.id
															] =
																_eLocal21 ===
																	null ||
																_eLocal21 ===
																	void 0
																	? void 0
																	: _eLocal21.value;
														}
													);
												}
												i.setGlobalDataViaKey(
													'currency_settings',
													_aLocal
												);
												_eLocal0.n = 7;
												break;
											case 6:
												_eLocal0.p = 6;
												_oLocal = _eLocal0.v;
												showRequestError(
													'Failed to load settings:',
													_oLocal
												);
											// Falls through to the shared async state cleanup.
											case 7:
												i.setLoadingSetting( ! 1 );
											// Falls through to the shared async state cleanup.
											case 8:
												return _eLocal0.a( 2 );
										}
									}
								},
								_eLocal9,
								null,
								[ [ 3, 6 ] ]
							);
						} )
					);
					return function ( ..._args2 ) {
						return _eLocal8.apply( this, _args2 );
					};
				} )();
				_eLocal7();
			}, [] ),
			t ? (
				<div className={ 'linno-onboarding-wrapper' }>
					{ ( function () {
						var _eLocal22 = {
							onTabChange: c,
							onWizardSkip: u,
						};
						switch ( a ) {
							case 'welcome':
							default:
								return <MemoSetupWelcome { ..._eLocal22 } />;
							case 'level-selection':
								return (
									<MemoSetupLevelSelection { ..._eLocal22 } />
								);
							case 'preferences':
								return (
									<MemoSetupPreferences { ..._eLocal22 } />
								);
							case 'niche':
								return <MemoSetupNiche { ..._eLocal22 } />;
							case 'course-creation':
								return <MemoCourseImport { ..._eLocal22 } />;
							case 'completion':
								return <MemoSetupCompletion { ..._eLocal22 } />;
						}
					} )() }
				</div>
			) : null
		 );
	};
}
