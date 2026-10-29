import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createGoogleMeetSettings( readRuntime ) {
	return function GoogleMeetSettings( props ) {
		const {
			J6,
			K6,
			Pf,
			React,
			X6,
			b: I18n,
			g: ReactHooks,
			l,
			n7,
			r7,
		} = readRuntime();
		var t = props.onSave,
			n = props.onCancel,
			r = r7(
				( 0, ReactHooks.useState )( {
					client_id: '',
					client_secret: '',
					redirect_url: '',
				} ),
				2
			),
			a = r[ 0 ],
			o = r[ 1 ],
			i = r7( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			c = i[ 0 ],
			u = i[ 1 ],
			s = r7( ( 0, ReactHooks.useState )( {} ), 2 ),
			d = s[ 0 ],
			m = s[ 1 ],
			p = r7( ( 0, ReactHooks.useState )( ! 0 ), 2 ),
			f = p[ 0 ],
			v = p[ 1 ],
			h = r7( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			y = h[ 0 ],
			_ = h[ 1 ],
			w = r7( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			E = w[ 0 ],
			S = w[ 1 ],
			R = r7( ( 0, ReactHooks.useState )( 'unknown' ), 2 ),
			x = R[ 0 ],
			C = R[ 1 ];
		( 0, ReactHooks.useEffect )( function () {
			var e = ! 0;
			function _tLocal( ..._args ) {
				return ( _tLocal = n7(
					X6().m( function _tLocal2() {
						var _nLocal, _rLocal;
						return X6().w(
							function ( _tLocal3 ) {
								for (;;) {
									switch ( ( _tLocal3.p = _tLocal3.n ) ) {
										case 0:
											return (
												( _tLocal3.p = 0 ),
												( _tLocal3.n = 1 ),
												l()( {
													path: '/ohmylms/v1/googlemeet/settings/credentials',
													method: 'GET',
												} )
											 );
										case 1:
											_nLocal = _tLocal3.v;
											if (
												e &&
												_nLocal !== null &&
												_nLocal !== void 0 &&
												_nLocal.data
											) {
												{
													o( {
														client_id:
															_nLocal.data
																.client_id ||
															'',
														client_secret:
															_nLocal.data
																.client_secret ||
															'',
														redirect_url:
															_nLocal.data
																.redirect_url ||
															'',
													} );
													if (
														_nLocal !== null &&
														_nLocal !== void 0 &&
														null !==
															( _rLocal =
																_nLocal.data ) &&
														void 0 !== _rLocal &&
														null !==
															( _rLocal =
																_rLocal.tokens ) &&
														void 0 !== _rLocal &&
														_rLocal.access_token
													) {
														C( 'valid' );
													}
												}
											}
											_tLocal3.n = 3;
											break;
										case 2:
											_tLocal3.p = 2;
										// Falls through to the shared async state cleanup.
										case 3:
											return (
												( _tLocal3.p = 3 ),
												e && v( ! 1 ),
												_tLocal3.f( 3 )
											 );
										case 4:
											return _tLocal3.a( 2 );
									}
								}
							},
							_tLocal2,
							null,
							[ [ 0, 2, 3, 4 ] ]
						);
					} )
				) ).apply( this, _args );
			}
			return (
				( function ( ..._args2 ) {
					_tLocal.apply( this, _args2 );
				} )(),
				function () {
					e = ! 1;
				}
			 );
		}, [] );
		var P = function ( ..._args3 ) {
				var e =
						_args3.length > 0 && void 0 !== _args3[ 0 ]
							? _args3[ 0 ]
							: a,
					_tLocal4 = {};
				return (
					( e.client_id && e.client_id.trim() ) ||
						( _tLocal4.client_id = ( 0, I18n.__ )(
							'Client ID is required',
							'ohmylms'
						) ),
					( e.client_secret && e.client_secret.trim() ) ||
						( _tLocal4.client_secret = ( 0, I18n.__ )(
							'Client Secret is required',
							'ohmylms'
						) ),
					_tLocal4
				 );
			},
			O = ( function () {
				var e = n7(
					X6().m( function _eLocal() {
						var _nLocal2, _rLocal2, _oLocal;
						return X6().w(
							function ( _eLocal2 ) {
								for (;;) {
									switch ( ( _eLocal2.p = _eLocal2.n ) ) {
										case 0:
											if (
												( ( _nLocal2 = P() ),
												m( _nLocal2 ),
												! (
													Object.keys( _nLocal2 )
														.length > 0
												) )
											) {
												_eLocal2.n = 1;
												break;
											}
											return _eLocal2.a( 2 );
										case 1:
											return (
												u( ! 0 ),
												( _eLocal2.p = 2 ),
												( _eLocal2.n = 3 ),
												l()( {
													path: '/ohmylms/v1/googlemeet/settings/credentials',
													method: 'POST',
													data: a,
												} )
											 );
										case 3:
											if (
												( ( _nullableValue ) =>
													_nullableValue !== null &&
													_nullableValue !== void 0 )(
													( _rLocal2 = _eLocal2.v )
												) &&
												_rLocal2.success
											) {
												{
													_( ! 0 );
													if ( t ) {
														t(
															'success',
															( 0, I18n.__ )(
																'Google Meet credentials saved successfully. Please authenticate with Google.',
																'ohmylms'
															)
														);
													}
												}
											}
											_eLocal2.n = 5;
											break;
										case 4:
											_eLocal2.p = 4;
											_oLocal = _eLocal2.v;
											if ( t ) {
												t(
													'error',
													( 0, I18n.__ )(
														'Something went wrong.',
														'ohmylms'
													)
												);
											}
											showRequestError( _oLocal );
										// Falls through to the shared async state cleanup.
										case 5:
											return (
												( _eLocal2.p = 5 ),
												u( ! 1 ),
												_eLocal2.f( 5 )
											 );
										case 6:
											return _eLocal2.a( 2 );
									}
								}
							},
							_eLocal,
							null,
							[ [ 2, 4, 5, 6 ] ]
						);
					} )
				);
				return function ( ..._args4 ) {
					return e.apply( this, _args4 );
				};
			} )(),
			k = ( function () {
				var e = n7(
					X6().m( function _eLocal3() {
						var _nLocal3, _rLocal3;
						return X6().w(
							function ( _eLocal4 ) {
								for (;;) {
									switch ( ( _eLocal4.p = _eLocal4.n ) ) {
										case 0:
											return (
												S( ! 0 ),
												( _eLocal4.p = 1 ),
												( _eLocal4.n = 2 ),
												l()( {
													path: '/ohmylms/v1/googlemeet/settings/oauth-url',
													method: 'GET',
												} )
											 );
										case 2:
											if (
												( ( _nullableValue2 ) =>
													_nullableValue2 === null ||
													_nullableValue2 ===
														void 0 )(
													( _nLocal3 = _eLocal4.v )
												) ||
												! _nLocal3.success ||
												! _nLocal3.auth_url
											) {
												_eLocal4.n = 3;
												break;
											}
											window.location.href =
												_nLocal3.auth_url;
											_eLocal4.n = 4;
											break;
										case 3:
											throw new Error(
												( _nLocal3 === null ||
												_nLocal3 === void 0
													? void 0
													: _nLocal3.message ) ||
													'OAuth URL not found'
											);
										case 4:
											_eLocal4.n = 6;
											break;
										case 5:
											_eLocal4.p = 5;
											_rLocal3 = _eLocal4.v;
											showRequestError(
												'OAuth authentication failed:',
												_rLocal3
											);
											if ( t ) {
												t(
													'error',
													( 0, I18n.__ )(
														'Failed to start authentication.',
														'ohmylms'
													)
												);
											}
										// Falls through to the shared async state cleanup.
										case 6:
											return (
												( _eLocal4.p = 6 ),
												S( ! 1 ),
												_eLocal4.f( 6 )
											 );
										case 7:
											return _eLocal4.a( 2 );
									}
								}
							},
							_eLocal3,
							null,
							[ [ 1, 5, 6, 7 ] ]
						);
					} )
				);
				return function ( ..._args5 ) {
					return e.apply( this, _args5 );
				};
			} )(),
			j = function ( e, _tLocal5 ) {
				o( function ( _nLocal4 ) {
					return K6( K6( {}, _nLocal4 ), {}, J6( {}, e, _tLocal5 ) );
				} );
				m( function ( _tLocal6 ) {
					return K6( K6( {}, _tLocal6 ), {}, J6( {}, e, void 0 ) );
				} );
			};
		return f ? (
			<div>
				{ ( 0, I18n.__ )(
					'Loading Google Meet settings...',
					'ohmylms'
				) }
			</div>
		) : (
			<React.Fragment>
				<Controls.CardWP
					variant={ 'secondary' }
					isBorderless={ ! 0 }
					style={ {
						minHeight: '220px',
					} }
				>
					<Controls.SpacerWP
						marginBottom={ 5 }
						marginTop={ 5 }
						padding={ 1 }
					>
						<Pf
							title={ ( 0, I18n.__ )( 'Client ID', 'ohmylms' ) }
							inputType={ 'text' }
							value={ a.client_id }
							onChange={ function ( e ) {
								return j( 'client_id', e );
							} }
							placeholder={ ( 0, I18n.__ )(
								'Enter your Google Client ID',
								'ohmylms'
							) }
							spacerMarginBottom={ 0 }
							error={ d.client_id }
						/>
						<Pf
							title={ ( 0, I18n.__ )(
								'Client Secret',
								'ohmylms'
							) }
							inputType={ 'text' }
							value={ a.client_secret }
							onChange={ function ( e ) {
								return j( 'client_secret', e );
							} }
							placeholder={ ( 0, I18n.__ )(
								'Enter your Google Client Secret',
								'ohmylms'
							) }
							spacerMarginBottom={ 0 }
							error={ d.client_secret }
						/>
						<Pf
							title={ ( 0, I18n.__ )(
								'Redirect URL',
								'ohmylms'
							) }
							inputType={ 'text' }
							value={ a.redirect_url }
							onChange={ function ( e ) {
								return j( 'redirect_url', e );
							} }
							placeholder={ ( 0, I18n.__ )(
								'Enter your Google Redirect URL',
								'ohmylms'
							) }
							spacerMarginBottom={ 0 }
							error={ d.redirect_url }
						/>
					</Controls.SpacerWP>
				</Controls.CardWP>
				<Controls.FlexWP justify={ 'flex-end' } gap={ 2 }>
					<Controls.ButtonWP isSecondary={ ! 0 } onClick={ n }>
						{ ( 0, I18n.__ )( 'Cancel', 'ohmylms' ) }
					</Controls.ButtonWP>
					<Controls.ButtonWP
						isPrimary={ ! 0 }
						onClick={ O }
						disabled={ c }
					>
						{ c
							? ( 0, I18n.__ )( 'Saving...', 'ohmylms' )
							: ( 0, I18n.__ )( 'Save', 'ohmylms' ) }
					</Controls.ButtonWP>
					{ 'valid' === x && ! y && (
						<Controls.ButtonWP isPrimary={ ! 0 } disabled={ ! 0 }>
							{ ( 0, I18n.__ )(
								'Google Meet Authenticated',
								'ohmylms'
							) }
						</Controls.ButtonWP>
					) }
					{ y && (
						<Controls.ButtonWP
							isPrimary={ ! 0 }
							onClick={ k }
							disabled={ E }
						>
							{ E
								? ( 0, I18n.__ )( 'Redirecting...', 'ohmylms' )
								: ( 0, I18n.__ )(
										'Authenticate with Google',
										'ohmylms'
									) }
						</Controls.ButtonWP>
					) }
					{ 'error' === x && (
						<Controls.ButtonWP isPrimary={ ! 0 } disabled={ ! 0 }>
							{ ( 0, I18n.__ )(
								'Error checking authentication',
								'ohmylms'
							) }
						</Controls.ButtonWP>
					) }
				</Controls.FlexWP>
			</React.Fragment>
		);
	};
}
