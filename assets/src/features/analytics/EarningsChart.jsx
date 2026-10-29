/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { Skeleton } from '../../shared/skeleton';
export function createEarningsChart( readRuntime ) {
	return function EarningsChart( props ) {
		const {
			BU,
			DU,
			FU,
			NU,
			React,
			T: StoreModule,
			b: I18n,
			g: ReactHooks,
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
			m = props.graphData,
			p = props.currency,
			f = props.currency_pos,
			v = props.filterTypeParam,
			h = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getDashboardLoader();
			}, [] ),
			w =
				v !== null && v !== void 0
					? v
					: ( 0, WordPressData.useSelect )( function ( e ) {
							return e(
								StoreModule.default
							).getDashboardFilter();
						}, [] ),
			E = DU( ( 0, ReactHooks.useState )( {} ), 2 ),
			S = E[ 0 ],
			R = E[ 1 ],
			x = DU( ( 0, ReactHooks.useState )( null ), 2 ),
			C = ( x[ 0 ], x[ 1 ] );
		function P( e ) {
			return /^\d{4}$/.test( e )
				? 'YYYY'
				: ( () => {
						if ( /^\d{4}-\d{2}$/.test( e ) ) {
							return 'YYYY-MM';
						}
						if ( /^\d{4}-\d{2}-\d{2}$/.test( e ) ) {
							return 'YYYY-MM-DD';
						}
						return 'Invalid format';
					} )();
		}
		var O = [
				'Jan',
				'Feb',
				'Mar',
				'Apr',
				'May',
				'Jun',
				'Jul',
				'Aug',
				'Sep',
				'Oct',
				'Nov',
				'Dec',
			],
			k = function ( e, _tLocal, _nLocal ) {
				_nLocal.labels.push( e );
				_nLocal.values.push( _tLocal );
			},
			j = function ( e, _tLocal2, _nLocal2, _rLocal, _aLocal ) {
				_aLocal[ e ] = _aLocal[ e ] || {
					earning: 0,
					refund: 0,
					net: 0,
				};
				_aLocal[ e ].earning += _tLocal2;
				_aLocal[ e ].refund += _nLocal2;
				_aLocal[ e ].net += _rLocal;
			},
			A = function ( e, _tLocal3, _nLocal3 ) {
				if ( 'YYYY-MM' === _tLocal3 ) {
					var _rLocal2 = DU( e.split( '-' ), 2 ),
						_aLocal2 = _rLocal2[ 0 ],
						_oLocal = _rLocal2[ 1 ];
					return 'yearly' === _nLocal3
						? ''.concat( O[ parseInt( _oLocal, 10 ) - 1 ] )
						: ''
								.concat( O[ parseInt( _oLocal, 10 ) - 1 ], ' ' )
								.concat( _aLocal2 );
				}
				if ( 'YYYY-MM-DD' === _tLocal3 ) {
					if ( 'monthly' === _nLocal3 ) {
						return parseInt( e.split( '-' )[ 2 ], 10 );
					}
					if ( 'custom' === _nLocal3 ) {
						var _iLocal = DU( e.split( '-' ), 3 ),
							_lLocal = ( _iLocal[ 0 ], _iLocal[ 1 ] ),
							_cLocal = _iLocal[ 2 ];
						return ''
							.concat( O[ parseInt( _lLocal, 10 ) - 1 ], ' ' )
							.concat( parseInt( _cLocal, 10 ) );
					}
				}
				return 'YYYY' === _tLocal3 ? e : null;
			};
		( 0, ReactHooks.useEffect )(
			function () {
				if ( m ) {
					R(
						( function ( e, _tLocal4 ) {
							var _nLocal4 = {
									labels: [],
									values: [],
								},
								_rLocal3 = {
									labels: [],
									values: [],
								},
								_aLocal3 = {
									labels: [],
									values: [],
								};
							if (
								'object' !== NU( e ) ||
								Array.isArray( e ) ||
								null === e
							) {
								return (
									void 0,
									{
										revenue: _nLocal4,
										refund: _rLocal3,
										net_amount: _aLocal3,
									}
								 );
							}
							switch ( _tLocal4 ) {
								case 'yearly':
									C( null );
									( function (
										_eLocal,
										_tLocal5,
										_nLocal5,
										_rLocal4
									) {
										for (
											var _aLocal4 = 0,
												_oLocal2 =
													Object.entries( _eLocal );
											_aLocal4 < _oLocal2.length;
											_aLocal4++
										) {
											var _iLocal2 = DU(
													_oLocal2[ _aLocal4 ],
													2
												),
												_lLocal2 = _iLocal2[ 0 ],
												_cLocal2 = _iLocal2[ 1 ],
												_uLocal = P( _lLocal2 );
											if (
												'Invalid format' !== _uLocal
											) {
												var _sLocal = A(
													_lLocal2,
													_uLocal,
													'yearly'
												);
												if ( _sLocal ) {
													var _dLocal =
															_cLocal2.earning,
														_mLocal =
															void 0 === _dLocal
																? 0
																: _dLocal,
														_pLocal =
															_cLocal2.refund,
														_fLocal =
															void 0 === _pLocal
																? 0
																: _pLocal,
														_vLocal = _cLocal2.net,
														g =
															void 0 === _vLocal
																? 0
																: _vLocal;
													k(
														_sLocal,
														_mLocal,
														_tLocal5
													);
													k(
														_sLocal,
														_fLocal,
														_nLocal5
													);
													k( _sLocal, g, _rLocal4 );
												} else {
												}
											} else {
											}
										}
									} )( e, _nLocal4, _rLocal3, _aLocal3 );
									break;
								case 'monthly':
									( function (
										_eLocal2,
										_tLocal6,
										_nLocal6,
										_rLocal5
									) {
										for (
											var _aLocal5 = {},
												_oLocal3 = 0,
												_iLocal3 =
													Object.entries( _eLocal2 );
											_oLocal3 < _iLocal3.length;
											_oLocal3++
										) {
											var _lLocal3 = DU(
													_iLocal3[ _oLocal3 ],
													2
												),
												_cLocal3 = _lLocal3[ 0 ],
												_uLocal2 = _lLocal3[ 1 ],
												_sLocal2 = P( _cLocal3 );
											if (
												'Invalid format' !== _sLocal2
											) {
												var _dLocal2 = A(
													_cLocal3,
													_sLocal2,
													'monthly'
												);
												if ( _dLocal2 ) {
													var _mLocal2 =
														parseInt(
															_cLocal3.split(
																'-'
															)[ 1 ],
															10
														) - 1;
													if (
														( C( O[ _mLocal2 ] ),
														'object' !==
															NU( _uLocal2 ) ||
															Array.isArray(
																_uLocal2
															) ||
															null === _uLocal2 )
													) {
													} else {
														var _pLocal2 =
																_uLocal2.earning,
															_fLocal2 =
																void 0 ===
																_pLocal2
																	? 0
																	: _pLocal2,
															_vLocal2 =
																_uLocal2.refund,
															g =
																void 0 ===
																_vLocal2
																	? 0
																	: _vLocal2,
															_hLocal =
																_uLocal2.net;
														j(
															_dLocal2,
															_fLocal2,
															g,
															void 0 === _hLocal
																? 0
																: _hLocal,
															_aLocal5
														);
													}
												} else {
												}
											} else {
											}
										}
										for (
											var y = 0,
												b = Object.entries( _aLocal5 );
											y < b.length;
											y++
										) {
											var _Local = DU( b[ y ], 2 ),
												_wLocal = _Local[ 0 ],
												_ELocal = _Local[ 1 ],
												_SLocal = _ELocal.earning,
												_RLocal = _ELocal.refund,
												_xLocal = _ELocal.net;
											k( _wLocal, _SLocal, _tLocal6 );
											k( _wLocal, _RLocal, _nLocal6 );
											k( _wLocal, _xLocal, _rLocal5 );
										}
									} )( e, _nLocal4, _rLocal3, _aLocal3 );
									break;
								case 'custom':
									C( null );
									( function (
										_eLocal3,
										_tLocal7,
										_nLocal7,
										_rLocal6
									) {
										for (
											var _aLocal6 = {},
												_oLocal4 = 0,
												_iLocal4 =
													Object.entries( _eLocal3 );
											_oLocal4 < _iLocal4.length;
											_oLocal4++
										) {
											var _lLocal4 = DU(
													_iLocal4[ _oLocal4 ],
													2
												),
												_cLocal4 = _lLocal4[ 0 ],
												_uLocal3 = _lLocal4[ 1 ],
												_sLocal3 = P( _cLocal4 );
											if (
												'Invalid format' !== _sLocal3
											) {
												var _dLocal3 = A(
													_cLocal4,
													_sLocal3,
													'custom'
												);
												if ( _dLocal3 ) {
													if (
														'object' !==
															NU( _uLocal3 ) ||
														Array.isArray(
															_uLocal3
														) ||
														null === _uLocal3
													) {
													} else {
														var _mLocal3 =
																_uLocal3.earning,
															_pLocal3 =
																void 0 ===
																_mLocal3
																	? 0
																	: _mLocal3,
															_fLocal3 =
																_uLocal3.refund,
															_vLocal3 =
																void 0 ===
																_fLocal3
																	? 0
																	: _fLocal3,
															g = _uLocal3.net;
														j(
															_dLocal3,
															_pLocal3,
															_vLocal3,
															void 0 === g
																? 0
																: g,
															_aLocal6
														);
													}
												} else {
												}
											} else {
											}
										}
										for (
											var _hLocal2 = 0,
												y = Object.keys( _aLocal6 );
											_hLocal2 < y.length;
											_hLocal2++
										) {
											var b = y[ _hLocal2 ];
											k(
												b,
												_aLocal6[ b ].earning,
												_tLocal7
											);
											k(
												b,
												_aLocal6[ b ].refund,
												_nLocal7
											);
											k( b, _aLocal6[ b ].net, _rLocal6 );
										}
									} )( e, _nLocal4, _rLocal3, _aLocal3 );
									break;
								default:
							}
							return {
								revenue: _nLocal4,
								refund: _rLocal3,
								net_amount: _aLocal3,
							};
						} )( m, w === null || w === void 0 ? void 0 : w.type )
					);
				}
			},
			[ m ]
		);
		var M = {
				first: ( 0, I18n.__ )( 'Income', 'ohmylms' ),
				second: ( 0, I18n.__ )( 'Refund', 'ohmylms' ),
				third: ( 0, I18n.__ )( 'Net Income', 'ohmylms' ),
			},
			I =
				S !== null &&
				S !== void 0 &&
				null !== ( t = S.revenue ) &&
				void 0 !== t &&
				t.values
					? Math.max(
							...FU(
								S === null ||
									S === void 0 ||
									null === ( n = S.revenue ) ||
									void 0 === n
									? void 0
									: n.values
							)
						)
					: 0,
			F =
				S !== null &&
				S !== void 0 &&
				null !== ( r = S.refund ) &&
				void 0 !== r &&
				r.values
					? Math.max(
							...FU(
								S === null ||
									S === void 0 ||
									null === ( a = S.refund ) ||
									void 0 === a
									? void 0
									: a.values
							)
						)
					: 0,
			N =
				S !== null &&
				S !== void 0 &&
				null !== ( o = S.net_amount ) &&
				void 0 !== o &&
				o.values
					? Math.max(
							...FU(
								S === null ||
									S === void 0 ||
									null === ( i = S.net_amount ) ||
									void 0 === i
									? void 0
									: i.values
							)
						)
					: 0,
			D = Math.max( I, F, N ) + 10,
			W = Math.ceil( D / 5 );
		return (
			<React.Fragment>
				{ h ? (
					<React.Fragment>
						<Skeleton
							paragraph={ {
								rows: 6,
							} }
							active={ ! 0 }
							style={ {
								padding: '20px',
							} }
						/>
					</React.Fragment>
				) : (
					<React.Fragment>
						<ReactHooks.Suspense
							fallback={ <div>{ 'Loading chart...' }</div> }
						>
							<BU
								labels={
									( S === null ||
									S === void 0 ||
									null === ( l = S.refund ) ||
									void 0 === l
										? void 0
										: l.labels ) || []
								}
								revenueData={
									( S === null ||
									S === void 0 ||
									null === ( c = S.revenue ) ||
									void 0 === c
										? void 0
										: c.values ) || []
								}
								refundData={
									( S === null ||
									S === void 0 ||
									null === ( u = S.refund ) ||
									void 0 === u
										? void 0
										: u.values ) || []
								}
								netAmountData={
									( S === null ||
									S === void 0 ||
									null === ( s = S.net_amount ) ||
									void 0 === s
										? void 0
										: s.values ) || []
								}
								stepSize={ W }
								maxStep={ D + W }
								tooltipTitle={ M }
								currency={
									p ||
									( null === ( d = window ) ||
									void 0 === d ||
									null === ( d = d.ohmylms_params ) ||
									void 0 === d
										? void 0
										: d.currency )
								}
								currencyPos={ f || 'left' }
							/>
						</ReactHooks.Suspense>
					</React.Fragment>
				) }
			</React.Fragment>
		);
	};
}
