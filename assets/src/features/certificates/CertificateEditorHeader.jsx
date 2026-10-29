import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
import { decodeHtmlEntities } from '../../shared/utils';
export function createCertificateEditorHeader( readRuntime ) {
	return function CertificateEditorHeader( props ) {
		const {
			Nr,
			Re,
			React,
			T: StoreModule,
			b: I18n,
			dL,
			f: Router,
			g: ReactHooks,
			gL,
			hL,
			mL,
			pL,
			sL,
			y: WordPressData,
		} = readRuntime();
		var t = props.elementRef,
			n = props.onClose,
			r = props.setShowCoursesModal,
			a = props.componentFrom,
			o = ( 0, Router.Zp )(),
			i = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			l = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).selectCertificate();
			}, [] ),
			c = hL( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			u = c[ 0 ],
			s = c[ 1 ],
			d = hL(
				( 0, ReactHooks.useState )(
					( l === null || l === void 0 ? void 0 : l.name ) ||
						'Untitled Template'
				),
				2
			),
			m = d[ 0 ],
			p = d[ 1 ],
			v = hL( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			_ = v[ 0 ],
			w = v[ 1 ],
			E = hL( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			S = E[ 0 ],
			R = E[ 1 ],
			x = hL( ( 0, ReactHooks.useState )( '' ), 2 ),
			C = x[ 0 ],
			P = x[ 1 ],
			O = hL( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			k = O[ 0 ],
			j = O[ 1 ],
			A = function () {
				s( ! 1 );
				i.updateContent( {
					name: m,
				} );
			},
			M = ( function () {
				var e = gL(
					pL().m( function _eLocal( ..._args ) {
						var _nLocal,
							_rLocal,
							_aLocal,
							_oLocal = _args;
						return pL().w(
							function ( _eLocal2 ) {
								for (;;) {
									switch ( ( _eLocal2.p = _eLocal2.n ) ) {
										case 0:
											return (
												( _nLocal =
													_oLocal.length > 0 &&
													void 0 !== _oLocal[ 0 ] &&
													_oLocal[ 0 ] ),
												( _eLocal2.p = 1 ),
												w( ! 0 ),
												( _eLocal2.n = 2 ),
												F()
											 );
										case 2:
											return (
												( ( _nullableValue ) =>
													_nullableValue !== null &&
													_nullableValue !== void 0 )(
													( _rLocal = _eLocal2.v )
												) &&
													_rLocal.attachment_id &&
													( l.thumbnail_id =
														_rLocal === null ||
														_rLocal === void 0
															? void 0
															: _rLocal.attachment_id ),
												( _eLocal2.n = 3 ),
												t.current.innerHTML
											 );
										case 3:
											return (
												( l.html_contents =
													_eLocal2.v ),
												( _eLocal2.n = 4 ),
												i.updateCertificate(
													l === null || l === void 0
														? void 0
														: l.id,
													l,
													_nLocal
												)
											 );
										case 4:
											_eLocal2.n = 6;
											break;
										case 5:
											_eLocal2.p = 5;
											_aLocal = _eLocal2.v;
											showRequestError( _aLocal );
										// Falls through to the shared async state cleanup.
										case 6:
											return (
												( _eLocal2.p = 6 ),
												w( ! 1 ),
												_eLocal2.f( 6 )
											 );
										case 7:
											return _eLocal2.a( 2 );
									}
								}
							},
							_eLocal,
							null,
							[ [ 1, 5, 6, 7 ] ]
						);
					} )
				);
				return function ( ..._args2 ) {
					return e.apply( this, _args2 );
				};
			} )(),
			F = ( function () {
				var e = gL(
					pL().m( function _eLocal3() {
						var _nLocal2, _rLocal2, _aLocal2, _oLocal2;
						return pL().w(
							function ( _eLocal4 ) {
								for (;;) {
									switch ( ( _eLocal4.p = _eLocal4.n ) ) {
										case 0:
											return (
												( _eLocal4.p = 0 ),
												j( ! 0 ),
												( _eLocal4.n = 1 ),
												sL( t.current )
											 );
										case 1:
											if (
												( ( _rLocal2 = _eLocal4.v ),
												0 ===
													( l === null || l === void 0
														? void 0
														: l.thumbnail_id ) )
											) {
												_eLocal4.n = 4;
												break;
											}
											return (
												( _eLocal4.n = 2 ),
												mL(
													l === null || l === void 0
														? void 0
														: l.thumbnail_id
												)
											 );
										case 2:
											return (
												( _eLocal4.n = 3 ),
												dL( _rLocal2 )
											 );
										case 3:
											_aLocal2 = _eLocal4.v;
											_eLocal4.n = 6;
											break;
										case 4:
											return (
												( _eLocal4.n = 5 ),
												dL( _rLocal2 )
											 );
										case 5:
											_aLocal2 = _eLocal4.v;
										// Falls through to the shared async state cleanup.
										case 6:
											if (
												null ===
													( _nLocal2 = _aLocal2 ) ||
												void 0 === _nLocal2 ||
												! _nLocal2.attachment_id
											) {
												_eLocal4.n = 7;
												break;
											}
											return (
												i.updateContent( {
													thumbnail_id:
														_aLocal2.attachment_id,
												} ),
												_eLocal4.a( 2, _aLocal2 )
											 );
										case 7:
											_eLocal4.n = 9;
											break;
										case 8:
											_eLocal4.p = 8;
											_oLocal2 = _eLocal4.v;
											showRequestError( _oLocal2 );
										// Falls through to the shared async state cleanup.
										case 9:
											return (
												( _eLocal4.p = 9 ),
												j( ! 1 ),
												_eLocal4.f( 9 )
											 );
										case 10:
											return _eLocal4.a( 2 );
									}
								}
							},
							_eLocal3,
							null,
							[ [ 0, 8, 9, 10 ] ]
						);
					} )
				);
				return function ( ..._args3 ) {
					return e.apply( this, _args3 );
				};
			} )(),
			N = ( function () {
				var e = gL(
					pL().m( function _eLocal5() {
						var _nLocal3, _rLocal3, _aLocal3, _oLocal3;
						return pL().w(
							function ( _eLocal6 ) {
								for (;;) {
									switch ( ( _eLocal6.p = _eLocal6.n ) ) {
										case 0:
											return (
												R( ! 0 ),
												( _eLocal6.p = 1 ),
												( _eLocal6.n = 2 ),
												F()
											 );
										case 2:
											return (
												( ( _nullableValue2 ) =>
													_nullableValue2 !== null &&
													_nullableValue2 !==
														void 0 )(
													( _rLocal3 = _eLocal6.v )
												) &&
													_rLocal3.attachment_id &&
													( l.thumbnail_id =
														_rLocal3 === null ||
														_rLocal3 === void 0
															? void 0
															: _rLocal3.attachment_id ),
												( _eLocal6.n = 3 ),
												t === null ||
												t === void 0 ||
												null ===
													( _nLocal3 = t.current ) ||
												void 0 === _nLocal3
													? void 0
													: _nLocal3.innerHTML
											 );
										case 3:
											if ( ( _aLocal3 = _eLocal6.v ) ) {
												_eLocal6.n = 4;
												break;
											}
											_aLocal3 = C;
										// Falls through to the shared async state cleanup.
										case 4:
											return (
												( l.html_contents = _aLocal3 ),
												( _eLocal6.n = 5 ),
												i.updateCertificate(
													l === null || l === void 0
														? void 0
														: l.id,
													l,
													! 0
												)
											 );
										case 5:
											_eLocal6.n = 7;
											break;
										case 6:
											_eLocal6.p = 6;
											_oLocal3 = _eLocal6.v;
											showRequestError( _oLocal3 );
										// Falls through to the shared async state cleanup.
										case 7:
											return (
												( _eLocal6.p = 7 ),
												R( ! 1 ),
												_eLocal6.f( 7 )
											 );
										case 8:
											return _eLocal6.a( 2 );
									}
								}
							},
							_eLocal5,
							null,
							[ [ 1, 6, 7, 8 ] ]
						);
					} )
				);
				return function ( ..._args4 ) {
					return e.apply( this, _args4 );
				};
			} )(),
			D = function ( e ) {
				if ( _ || S ) {
					{
						e.preventDefault();
						e.returnValue = '';
					}
				}
				if ( n ) {
					{
						e.preventDefault();
						e.returnValue = '';
						n();
					}
				}
			},
			W = function ( e ) {
				e.preventDefault();
				if ( ! ( _ || S ) ) {
					if ( n ) {
						{
							window.history.pushState(
								null,
								null,
								window.location.href
							);
							n();
						}
					}
				}
			},
			z = function ( e ) {
				e.preventDefault();
				if ( ! ( _ || S ) ) {
					{
						window.history.pushState(
							null,
							null,
							window.location.href
						);
						n();
					}
				}
			};
		return (
			( 0, ReactHooks.useEffect )( function () {
				N();
			}, [] ),
			( 0, ReactHooks.useEffect )(
				function () {
					return (
						n && window.addEventListener( 'popstate', z ),
						function () {
							window.removeEventListener( 'popstate', z );
						}
					 );
				},
				[ n ]
			),
			( 0, ReactHooks.useEffect )(
				function () {
					return (
						_ || S
							? ( window.addEventListener( 'beforeunload', D ),
								window.history.pushState(
									null,
									null,
									window.location.href
								),
								window.addEventListener( 'popstate', W ) )
							: ( window.removeEventListener( 'beforeunload', D ),
								window.removeEventListener( 'popstate', W ) ),
						function () {
							window.removeEventListener( 'beforeunload', D );
							window.removeEventListener( 'popstate', W );
						}
					 );
				},
				[ _, S ]
			),
			( 0, ReactHooks.useEffect )(
				function () {
					var e;
					P(
						t === null ||
							t === void 0 ||
							null === ( e = t.current ) ||
							void 0 === e
							? void 0
							: e.innerHTML
					);
				},
				[ t.current ]
			),
			(
				<React.Fragment>
					<Controls.CardWP
						style={ {
							borderRadius: 0,
							width: '100%',
						} }
					>
						<Controls.SpacerWP padding={ 4 }>
							<Controls.FlexWP>
								<Controls.FlexItemWP isBlock={ ! 0 }>
									<Controls.FlexWP
										justify={ 'start' }
										align={ 'center' }
										gap={ 2 }
									>
										<Nr
											onClick={ function () {
												if ( n ) {
													n();
												} else {
													o( '/certificates' );
												}
											} }
											disabled={ k || _ || S }
										/>
										<Controls.FlexWP
											style={ {
												gap: '10px',
											} }
											align={ 'center' }
											justify={ 'start' }
										>
											{ u ? (
												<Controls.InputWP
													value={ decodeHtmlEntities(
														m
													) }
													onChange={ function ( e ) {
														return p( e );
													} }
													onPressEnter={ A }
													onBlur={ A }
												/>
											) : (
												<React.Fragment>
													<span
														title={ decodeHtmlEntities(
															m
														) }
													>
														{ decodeHtmlEntities(
															m
														) }
													</span>
													<Controls.ButtonWP
														variant={ 'tertiary' }
														icon={ <Re /> }
														onClick={
															u
																? A
																: function () {
																		s(
																			! 0
																		);
																	}
														}
													/>
												</React.Fragment>
											) }
										</Controls.FlexWP>
									</Controls.FlexWP>
								</Controls.FlexItemWP>
								<Controls.FlexItemWP>
									<Controls.FlexWP gap={ 3 }>
										{ 'course-editor' !== a && (
											<Controls.ButtonWP
												variant={ 'secondary' }
												onClick={ function () {
													r( ! 0 );
												} }
											>
												{ ( 0, I18n.__ )(
													'Courses',
													'ohmylms'
												) }
											</Controls.ButtonWP>
										) }
										<Controls.ButtonWP
											variant={ 'primary' }
											loading={ _ }
											onClick={ function () {
												return M( ! 1 );
											} }
										>
											{ ( 0, I18n.__ )(
												'Update',
												'ohmylms'
											) }
										</Controls.ButtonWP>
									</Controls.FlexWP>
								</Controls.FlexItemWP>
							</Controls.FlexWP>
						</Controls.SpacerWP>
					</Controls.CardWP>
				</React.Fragment>
			 )
		 );
	};
}
