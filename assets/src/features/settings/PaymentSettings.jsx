import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createPaymentSettings( readRuntime ) {
	return function PaymentSettings() {
		const {
			B1: MemoTaxSettings,
			G1,
			I: Controls,
			P1: MemoCurrencySettings,
			Q1,
			React,
			SK: MemoSettingsActionBar,
			T: StoreModule,
			V1,
			Y1,
			Z1,
			b: I18n,
			ep,
			f: Router,
			g: ReactHooks,
			l,
			y: WordPressData,
			y1: MemoPaymentGatewaysSettings,
			z: Notifications,
		} = readRuntime();
		var e = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			t = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
				return _eLocal( StoreModule.default ).isSettingsLoading();
			}, [] ),
			n = ( 0, WordPressData.useSelect )( function ( _eLocal2 ) {
				return _eLocal2( StoreModule.default ).getPaymentSettings();
			}, [] ),
			r = ( 0, WordPressData.useSelect )( function ( _eLocal3 ) {
				return _eLocal3( StoreModule.default ).getCurrencySettings();
			}, [] ),
			a = ( 0, WordPressData.useSelect )( function ( _eLocal4 ) {
				return _eLocal4( StoreModule.default ).getTaxSettings();
			}, [] ),
			o = ( 0, WordPressData.useSelect )( function ( _eLocal5 ) {
				return _eLocal5( StoreModule.default ).getNotificationMessage();
			}, [] ),
			i = ( 0, WordPressData.useSelect )( function ( _eLocal6 ) {
				return _eLocal6( StoreModule.default ).getNotificationStatus();
			}, [] ),
			c = ( 0, Notifications.A )(),
			u = c.openNotificationWithIcon,
			s = c.contextHolder,
			d = ( 0, Router.Zp )(),
			m = ( 0, Router.g )(),
			p = m.tab,
			v = ( m.subTab, Z1( ( 0, ReactHooks.useState )( ! 1 ), 2 ) ),
			h = v[ 0 ],
			_ = v[ 1 ],
			w = Z1( ( 0, ReactHooks.useState )( 'payments' ), 2 ),
			E = w[ 0 ],
			S = w[ 1 ];
		function R( _eLocal7 ) {
			var _tLocal = {};
			for ( var _nLocal in _eLocal7 ) {
				if ( _eLocal7.hasOwnProperty( _nLocal ) ) {
					_tLocal[ _nLocal ] = _eLocal7[ _nLocal ];
				}
			}
			return _tLocal;
		}
		function x( _eLocal8 ) {
			var _tLocal2 = {};
			function _nLocal2( _eLocal9 ) {
				for (
					;
					'object' === Q1( _eLocal9 ) &&
					null !== _eLocal9 &&
					'value' in _eLocal9;
				) {
					_eLocal9 = _eLocal9.value;
				}
				return _eLocal9;
			}
			for ( var _rLocal in _eLocal8 ) {
				if ( _eLocal8.hasOwnProperty( _rLocal ) ) {
					_tLocal2[ _rLocal ] = _nLocal2( _eLocal8[ _rLocal ].value );
				}
			}
			return _tLocal2;
		}
		var C = ( function () {
				var _eLocal0 = Y1(
					G1().m( function _eLocal1( _tLocal3 ) {
						var _rLocal2, _aLocal, _oLocal;
						return G1().w(
							function ( _eLocal10 ) {
								for (;;) {
									switch ( ( _eLocal10.p = _eLocal10.n ) ) {
										case 0:
											return (
												( _eLocal10.p = 0 ),
												_( ! 0 ),
												( _rLocal2 = R( n ) ),
												_tLocal3 !== null &&
													_tLocal3 !== void 0 &&
													_tLocal3.key &&
													_tLocal3 !== null &&
													_tLocal3 !== void 0 &&
													_tLocal3.value &&
													( _rLocal2[
														_tLocal3 === null ||
														_tLocal3 === void 0
															? void 0
															: _tLocal3.key
													] =
														_tLocal3 === null ||
														_tLocal3 === void 0
															? void 0
															: _tLocal3.value ),
												( _eLocal10.n = 1 ),
												l()( {
													path: '/ohmylms/v1/settings/payment-gateway',
													method: 'POST',
													headers: {
														'Content-Type':
															'application/json',
													},
													body: JSON.stringify(
														_rLocal2
													),
												} )
											 );
										case 1:
											return (
												( ( _nullableValue ) =>
													_nullableValue !== null &&
													_nullableValue !== void 0 )(
													( _aLocal = _eLocal10.v )
												) &&
													_aLocal.success &&
													u(
														'success',
														'Payment Settings Saved Successfully.'
													),
												_eLocal10.a( 2, _aLocal )
											 );
										case 2:
											_eLocal10.p = 2;
											_oLocal = _eLocal10.v;
											showRequestError( _oLocal );
											u(
												'error',
												'Failed to update settings. Please try again.'
											);
										// Falls through to the shared async state cleanup.
										case 3:
											return (
												( _eLocal10.p = 3 ),
												_( ! 1 ),
												_eLocal10.f( 3 )
											 );
										case 4:
											return _eLocal10.a( 2 );
									}
								}
							},
							_eLocal1,
							null,
							[ [ 0, 2, 3, 4 ] ]
						);
					} )
				);
				return function ( ..._args ) {
					return _eLocal0.apply( this, _args );
				};
			} )(),
			P = ( 0, ReactHooks.useCallback )(
				Y1(
					G1().m( function _tLocal5() {
						var _nLocal3, _aLocal2, _oLocal2;
						return G1().w(
							function ( _tLocal6 ) {
								for (;;) {
									switch ( ( _tLocal6.p = _tLocal6.n ) ) {
										case 0:
											return (
												( _tLocal6.p = 0 ),
												_( ! 0 ),
												( _nLocal3 = x( r ) ),
												( _tLocal6.n = 1 ),
												l()( {
													path: '/ohmylms/v1/settings/currency',
													method: 'POST',
													headers: {
														'Content-Type':
															'application/json',
													},
													body: JSON.stringify(
														_nLocal3
													),
												} )
											 );
										case 1:
											if (
												( ( _nullableValue2 ) =>
													_nullableValue2 !== null &&
													_nullableValue2 !==
														void 0 )(
													( _aLocal2 = _tLocal6.v )
												) &&
												_aLocal2.success
											) {
												{
													e.setGlobalDataViaKey(
														'currency_settings',
														_nLocal3
													);
													u(
														'success',
														'Currency settings updated successfully.'
													);
												}
											}
											_tLocal6.n = 3;
											break;
										case 2:
											_tLocal6.p = 2;
											_oLocal2 = _tLocal6.v;
											showRequestError( _oLocal2 );
											u(
												'error',
												'Failed to update settings. Please try again.'
											);
										// Falls through to the shared async state cleanup.
										case 3:
											return (
												( _tLocal6.p = 3 ),
												_( ! 1 ),
												_tLocal6.f( 3 )
											 );
										case 4:
											return _tLocal6.a( 2 );
									}
								}
							},
							_tLocal5,
							null,
							[ [ 0, 2, 3, 4 ] ]
						);
					} )
				),
				[ r ]
			),
			O = ( 0, ReactHooks.useCallback )(
				Y1(
					G1().m( function _tLocal7() {
						var _nLocal4,
							_rLocal3,
							_oLocal3,
							_iLocal,
							_cLocal,
							_sLocal,
							_dLocal,
							_mLocal,
							_pLocal;
						return G1().w(
							function ( _tLocal8 ) {
								for (;;) {
									switch ( ( _tLocal8.p = _tLocal8.n ) ) {
										case 0:
											return (
												( _tLocal8.p = 0 ),
												_( ! 0 ),
												( _oLocal3 = x( a ) ),
												( _iLocal =
													( a === null ||
													a === void 0 ||
													null ===
														( _nLocal4 =
															a.ohmylms_existing_tax_rates ) ||
													void 0 === _nLocal4
														? void 0
														: _nLocal4.value ) ||
													[] ),
												( _cLocal =
													( a === null ||
													a === void 0 ||
													null ===
														( _rLocal3 =
															a.ohmylms_new_tax_rates ) ||
													void 0 === _rLocal3
														? void 0
														: _rLocal3.value ) ||
													[] ),
												( _sLocal = [] ),
												_iLocal.forEach(
													function ( _eLocal11 ) {
														if (
															_eLocal11.country &&
															void 0 !==
																_eLocal11.rate &&
															'' !==
																_eLocal11.rate
														) {
															_sLocal.push( {
																id:
																	_eLocal11.id ||
																	Date.now() +
																		Math.random(),
																country:
																	_eLocal11.country,
																state:
																	_eLocal11.state ||
																	'',
																countryWide:
																	_eLocal11.countryWide ||
																	! 1,
																rate:
																	parseFloat(
																		_eLocal11.rate
																	) || 0,
															} );
														}
													}
												),
												_cLocal.forEach(
													function ( _eLocal12 ) {
														if (
															_eLocal12.country &&
															_eLocal12.rate &&
															parseFloat(
																_eLocal12.rate
															) >= 0 &&
															parseFloat(
																_eLocal12.rate
															) <= 100
														) {
															_sLocal.push( {
																id:
																	_eLocal12.id ||
																	Date.now() +
																		Math.random(),
																country:
																	_eLocal12.country,
																state:
																	_eLocal12.state ||
																	'',
																countryWide:
																	_eLocal12.countryWide ||
																	! 1,
																rate: parseFloat(
																	_eLocal12.rate
																),
															} );
														}
													}
												),
												( _dLocal = V1(
													V1(
														{
															ohmylms_tax_enabled:
																_oLocal3.ohmylms_tax_enabled ||
																'no',
															ohmylms_tax_label:
																_oLocal3.ohmylms_tax_label ||
																'Tax',
															ohmylms_prices_include_tax:
																_oLocal3.ohmylms_prices_include_tax ||
																'no',
															ohmylms_eu_vat_enabled:
																_oLocal3.ohmylms_eu_vat_enabled ||
																'no',
															ohmylms_disable_vat_validation:
																_oLocal3.ohmylms_disable_vat_validation ||
																'no',
															ohmylms_vat_number_label:
																_oLocal3.ohmylms_vat_number_label ||
																'VAT Number',
															ohmylms_fallback_tax_rate:
																_oLocal3.ohmylms_fallback_tax_rate ||
																'0.00',
														},
														_oLocal3
													),
													{},
													{
														ohmylms_tax_rates:
															_sLocal,
													}
												) ),
												( _tLocal8.n = 1 ),
												l()( {
													path: '/ohmylms/v1/settings/tax',
													method: 'POST',
													headers: {
														'Content-Type':
															'application/json',
													},
													body: JSON.stringify(
														_dLocal
													),
												} )
											 );
										case 1:
											return (
												( ( _nullableValue3 ) =>
													_nullableValue3 !== null &&
													_nullableValue3 !==
														void 0 )(
													( _mLocal = _tLocal8.v )
												) &&
													_mLocal.success &&
													( u(
														'success',
														'Tax settings updated successfully.'
													),
													e.updateTaxSettings( {
														new_tax_rates: {
															value: [],
														},
													} ) ),
												_tLocal8.a( 2, _mLocal )
											 );
										case 2:
											_tLocal8.p = 2;
											_pLocal = _tLocal8.v;
											showRequestError( _pLocal );
											u(
												'error',
												'Failed to update settings. Please try again.'
											);
										// Falls through to the shared async state cleanup.
										case 3:
											return (
												( _tLocal8.p = 3 ),
												_( ! 1 ),
												_tLocal8.f( 3 )
											 );
										case 4:
											return _tLocal8.a( 2 );
									}
								}
							},
							_tLocal7,
							null,
							[ [ 0, 2, 3, 4 ] ]
						);
					} )
				),
				[ a ]
			),
			k = [
				{
					label: (
						<React.Fragment>
							{ ( 0, I18n.__ )( 'Payments', 'ohmylms' ) }
						</React.Fragment>
					),
					key: 'payments',
					children: (
						<MemoPaymentGatewaysSettings
							isLoading={ h }
							setIsLoading={ _ }
							handleSave={ C }
						/>
					),
				},
				{
					label: (
						<React.Fragment>
							{ ( 0, I18n.__ )( 'Currency Settings', 'ohmylms' ) }
						</React.Fragment>
					),
					key: 'currency',
					children: <MemoCurrencySettings formatData={ x } />,
				},
				{
					label: (
						<React.Fragment>
							{ ( 0, I18n.__ )( 'Taxes', 'ohmylms' ) }
						</React.Fragment>
					),
					key: 'tax',
					children: <MemoTaxSettings formatData={ x } />,
				},
			];
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					if ( ! t && o ) {
						u( i, o );
					}
				},
				[ o ]
			),
			(
				<React.Fragment>
					{ s }
					<Controls.CardWP
						isBorderless={ ! 0 }
						variant={ 'secondary' }
						className={ 'ohmylms-full-screen-height' }
					>
						<Controls.SpacerWP
							padding={ 4 }
							paddingTop={ 1 }
							marginTop={ 4 }
							marginBottom={ 0 }
						>
							<ep.A
								items={ k }
								className={ 'ohmylms-monetization-tabs' }
								onChange={ function ( _eLocal13 ) {
									S( _eLocal13 );
									d(
										'/settings/'
											.concat( p, '/' )
											.concat( _eLocal13 )
									);
								} }
								activekey={ E }
							/>
							<Controls.SpacerWP
								marginBottom={ 0 }
								marginTop={ 4 }
							>
								<MemoSettingsActionBar
									activeTab={ E }
									handleSave={ ( () => {
										if ( 'currency' === E ) {
											return P;
										}
										if ( 'tax' === E ) {
											return O;
										}
										return C;
									} )() }
									isSaving={ h }
								/>
							</Controls.SpacerWP>
						</Controls.SpacerWP>
					</Controls.CardWP>
				</React.Fragment>
			 )
		 );
	};
}
