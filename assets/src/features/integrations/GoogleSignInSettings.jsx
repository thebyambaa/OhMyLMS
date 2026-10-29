/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createGoogleSignInSettings( readRuntime ) {
	return function GoogleSignInSettings( props ) {
		const {
			E7,
			Pf,
			React,
			S7,
			_7,
			b: I18n,
			b7,
			g: ReactHooks,
			g7,
			l,
		} = readRuntime();
		var t = props.onSave,
			n = props.onCancel,
			r = S7(
				( 0, ReactHooks.useState )( {
					client_id: '',
					client_secret: '',
				} ),
				2
			),
			a = r[ 0 ],
			o = r[ 1 ],
			i = S7( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			c = i[ 0 ],
			u = i[ 1 ],
			s = S7( ( 0, ReactHooks.useState )( '' ), 2 ),
			d = s[ 0 ],
			m = s[ 1 ],
			p = S7( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			f = p[ 0 ],
			v = p[ 1 ],
			h = S7( ( 0, ReactHooks.useState )( {} ), 2 ),
			y = h[ 0 ],
			_ = h[ 1 ],
			w = S7( ( 0, ReactHooks.useState )( ! 0 ), 2 ),
			E = w[ 0 ],
			S = w[ 1 ];
		( 0, ReactHooks.useEffect )( function () {
			var e = ! 0;
			function _tLocal( ..._args ) {
				return ( _tLocal = E7(
					g7().m( function _tLocal2() {
						var _nLocal;
						return g7().w(
							function ( _tLocal3 ) {
								for (;;) {
									switch ( ( _tLocal3.p = _tLocal3.n ) ) {
										case 0:
											return (
												( _tLocal3.p = 0 ),
												( _tLocal3.n = 1 ),
												l()( {
													path: '/ohmylms/v1/auth/google/settings',
												} )
											 );
										case 1:
											_nLocal = _tLocal3.v;
											if ( e && _nLocal ) {
												{
													o( function ( _eLocal ) {
														return b7(
															b7( {}, _eLocal ),
															{},
															{
																client_id:
																	_nLocal.client_id ||
																	'',
															}
														);
													} );
													u(
														!! _nLocal.has_client_secret
													);
													m(
														_nLocal.redirect_uri ||
															''
													);
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
												e && S( ! 1 ),
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
		var R = function () {
				var e = {};
				return (
					( a.client_id && a.client_id.trim() ) ||
						( e.client_id = ( 0, I18n.__ )(
							'Client ID is required',
							'ohmylms'
						) ),
					c ||
						( a.client_secret && a.client_secret.trim() ) ||
						( e.client_secret = ( 0, I18n.__ )(
							'Client Secret is required',
							'ohmylms'
						) ),
					e
				 );
			},
			x = function ( e, _tLocal4 ) {
				o( function ( _nLocal2 ) {
					return b7( b7( {}, _nLocal2 ), {}, _7( {}, e, _tLocal4 ) );
				} );
				_( function ( _tLocal5 ) {
					return b7( b7( {}, _tLocal5 ), {}, _7( {}, e, void 0 ) );
				} );
			},
			C = ( function () {
				var e = E7(
					g7().m( function _eLocal2() {
						var _nLocal3, _rLocal, _iLocal;
						return g7().w(
							function ( _eLocal3 ) {
								for (;;) {
									switch ( ( _eLocal3.p = _eLocal3.n ) ) {
										case 0:
											if (
												( ( _nLocal3 = R() ),
												_( _nLocal3 ),
												! (
													Object.keys( _nLocal3 )
														.length > 0
												) )
											) {
												_eLocal3.n = 1;
												break;
											}
											return _eLocal3.a( 2 );
										case 1:
											return (
												v( ! 0 ),
												( _eLocal3.p = 2 ),
												( _eLocal3.n = 3 ),
												l()( {
													path: '/ohmylms/v1/auth/google/settings',
													method: 'POST',
													data: {
														client_id: a.client_id,
														client_secret:
															a.client_secret,
													},
												} )
											 );
										case 3:
											_rLocal = _eLocal3.v;
											u(
												! (
													_rLocal === null ||
													_rLocal === void 0 ||
													! _rLocal.has_client_secret
												)
											);
											m(
												( _rLocal === null ||
												_rLocal === void 0
													? void 0
													: _rLocal.redirect_uri ) ||
													d
											);
											o( function ( _eLocal4 ) {
												return b7(
													b7( {}, _eLocal4 ),
													{},
													{
														client_secret: '',
													}
												);
											} );
											if ( t ) {
												t(
													'success',
													( 0, I18n.__ )(
														'Google Sign-In settings saved.',
														'ohmylms'
													)
												);
											}
											_eLocal3.n = 5;
											break;
										case 4:
											_eLocal3.p = 4;
											_iLocal = _eLocal3.v;
											if ( t ) {
												t(
													'error',
													( _iLocal === null ||
													_iLocal === void 0
														? void 0
														: _iLocal.message ) ||
														( 0, I18n.__ )(
															'Failed to save Google Sign-In settings.',
															'ohmylms'
														)
												);
											}
										// Falls through to the shared async state cleanup.
										case 5:
											return (
												( _eLocal3.p = 5 ),
												v( ! 1 ),
												_eLocal3.f( 5 )
											 );
										case 6:
											return _eLocal3.a( 2 );
									}
								}
							},
							_eLocal2,
							null,
							[ [ 2, 4, 5, 6 ] ]
						);
					} )
				);
				return function ( ..._args3 ) {
					return e.apply( this, _args3 );
				};
			} )();
		return E ? (
			<div>
				{ ( 0, I18n.__ )(
					'Loading Google Sign-In settings...',
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
								return x( 'client_id', e );
							} }
							placeholder={ ( 0, I18n.__ )(
								'Enter your Google Client ID',
								'ohmylms'
							) }
							spacerMarginBottom={ 0 }
							error={ y.client_id }
						/>
						<Pf
							title={ ( 0, I18n.__ )(
								'Client Secret',
								'ohmylms'
							) }
							inputType={ 'text' }
							value={ a.client_secret }
							onChange={ function ( e ) {
								return x( 'client_secret', e );
							} }
							placeholder={
								c
									? ( 0, I18n.__ )(
											'Leave blank to keep the current secret',
											'ohmylms'
										)
									: ( 0, I18n.__ )(
											'Enter your Google Client Secret',
											'ohmylms'
										)
							}
							spacerMarginBottom={ 0 }
							error={ y.client_secret }
						/>
						<Controls.SpacerWP padding={ 4 } marginBottom={ 0 }>
							<Controls.TextWP
								as={ 'p' }
								size={ '14px' }
								weight={ 600 }
								color={ '#000D25' }
							>
								{ ( 0, I18n.__ )(
									'Authorized redirect URI',
									'ohmylms'
								) }
							</Controls.TextWP>
							<Controls.SpacerWP marginBottom={ 2 } />
							<Controls.FlexWP gap={ 2 } align={ 'center' }>
								<Controls.FlexItemWP isBlock={ ! 0 }>
									<Controls.InputWP
										type={ 'text' }
										value={ d }
										readOnly={ ! 0 }
									/>
								</Controls.FlexItemWP>
								<Controls.CopyToClipboard textToCopy={ d } />
							</Controls.FlexWP>
							<Controls.SpacerWP marginBottom={ 2 } />
							<Controls.TextWP
								as={ 'p' }
								size={ '13px' }
								color={ '#687784' }
							>
								{ ( 0, I18n.__ )(
									"Paste this into your Google Cloud OAuth client's Authorized redirect URIs.",
									'ohmylms'
								) }
							</Controls.TextWP>
						</Controls.SpacerWP>
					</Controls.SpacerWP>
				</Controls.CardWP>
				<Controls.FlexWP justify={ 'flex-end' } gap={ 2 }>
					<Controls.ButtonWP isSecondary={ ! 0 } onClick={ n }>
						{ ( 0, I18n.__ )( 'Cancel', 'ohmylms' ) }
					</Controls.ButtonWP>
					<Controls.ButtonWP
						isPrimary={ ! 0 }
						onClick={ C }
						disabled={ f }
					>
						{ f
							? ( 0, I18n.__ )( 'Saving...', 'ohmylms' )
							: ( 0, I18n.__ )( 'Save', 'ohmylms' ) }
					</Controls.ButtonWP>
				</Controls.FlexWP>
			</React.Fragment>
		);
	};
}
