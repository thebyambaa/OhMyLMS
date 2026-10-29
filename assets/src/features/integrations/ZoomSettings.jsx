import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createZoomSettings( readRuntime ) {
	return function ZoomSettings( props ) {
		const {
			H6,
			L6,
			Pf,
			React,
			V6,
			Y6,
			b: I18n,
			g: ReactHooks,
			l,
			q6,
		} = readRuntime();
		var t = props.onSave,
			n = props.onCancel,
			r = Y6(
				( 0, ReactHooks.useState )( {
					account_id: '',
					client_id: '',
					client_secret: '',
					webhook_secret_token: '',
				} ),
				2
			),
			a = r[ 0 ],
			o = r[ 1 ],
			i = Y6( ( 0, ReactHooks.useState )( '' ), 2 ),
			c = i[ 0 ],
			u = i[ 1 ],
			s = Y6( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			d = s[ 0 ],
			m = s[ 1 ],
			p = Y6( ( 0, ReactHooks.useState )( {} ), 2 ),
			f = p[ 0 ],
			v = p[ 1 ],
			h = Y6( ( 0, ReactHooks.useState )( ! 0 ), 2 ),
			y = h[ 0 ],
			_ = h[ 1 ];
		( 0, ReactHooks.useEffect )( function () {
			var e = ! 0;
			function _tLocal( ..._args ) {
				return ( _tLocal = q6(
					H6().m( function _tLocal2() {
						var _nLocal;
						return H6().w(
							function ( _tLocal3 ) {
								for (;;) {
									switch ( ( _tLocal3.p = _tLocal3.n ) ) {
										case 0:
											return (
												( _tLocal3.p = 0 ),
												( _tLocal3.n = 1 ),
												l()( {
													path: '/ohmylms/v1/zoom/settings/credentials',
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
														account_id:
															_nLocal.data
																.account_id ||
															'',
														client_id:
															_nLocal.data
																.client_id ||
															'',
														client_secret:
															_nLocal.data
																.client_secret ||
															'',
														webhook_secret_token:
															_nLocal.data
																.webhook_secret_token ||
															'',
													} );
													u(
														_nLocal.data
															.webhook_url || ''
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
												e && _( ! 1 ),
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
		var w = function ( ..._args3 ) {
				var e =
						_args3.length > 0 && void 0 !== _args3[ 0 ]
							? _args3[ 0 ]
							: a,
					_tLocal4 = {};
				return (
					( e.account_id && e.account_id.trim() ) ||
						( _tLocal4.account_id = ( 0, I18n.__ )(
							'Account ID is required',
							'ohmylms'
						) ),
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
			E = ( function () {
				var e = q6(
					H6().m( function _eLocal() {
						var _nLocal2, _rLocal, _oLocal;
						return H6().w(
							function ( _eLocal2 ) {
								for (;;) {
									switch ( ( _eLocal2.p = _eLocal2.n ) ) {
										case 0:
											if (
												( ( _nLocal2 = w() ),
												v( _nLocal2 ),
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
												m( ! 0 ),
												( _eLocal2.p = 2 ),
												( _eLocal2.n = 3 ),
												l()( {
													path: '/ohmylms/v1/zoom/settings/credentials',
													method: 'POST',
													data: a,
												} )
											 );
										case 3:
											if (
												( ( _nullableValue ) =>
													_nullableValue !== null &&
													_nullableValue !== void 0 )(
													( _rLocal = _eLocal2.v )
												) &&
												_rLocal.success &&
												t
											) {
												t(
													'success',
													( 0, I18n.__ )(
														'Zoom credentials saved successfully.',
														'ohmylms'
													)
												);
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
												m( ! 1 ),
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
			S = function ( e, _tLocal5 ) {
				o( function ( _nLocal3 ) {
					return L6( L6( {}, _nLocal3 ), {}, V6( {}, e, _tLocal5 ) );
				} );
				v( function ( _tLocal6 ) {
					return L6( L6( {}, _tLocal6 ), {}, V6( {}, e, void 0 ) );
				} );
			};
		return y ? (
			<div>
				{ ( 0, I18n.__ )( 'Loading Zoom settings...', 'ohmylms' ) }
			</div>
		) : (
			<React.Fragment>
				<Controls.CardWP
					variant={ 'secondary' }
					isBorderless={ ! 0 }
					style={ {
						minHeight: '280px',
					} }
				>
					<Controls.SpacerWP
						marginBottom={ 5 }
						marginTop={ 5 }
						padding={ 1 }
					>
						<Pf
							title={ ( 0, I18n.__ )( 'Account ID', 'ohmylms' ) }
							inputType={ 'text' }
							value={ a.account_id }
							onChange={ function ( e ) {
								return S( 'account_id', e );
							} }
							placeholder={ ( 0, I18n.__ )(
								'Enter your Zoom Account ID',
								'ohmylms'
							) }
							spacerMarginBottom={ 0 }
							error={ f.account_id }
						/>
						<Pf
							title={ ( 0, I18n.__ )( 'Client ID', 'ohmylms' ) }
							inputType={ 'text' }
							value={ a.client_id }
							onChange={ function ( e ) {
								return S( 'client_id', e );
							} }
							placeholder={ ( 0, I18n.__ )(
								'Enter your Zoom Client ID',
								'ohmylms'
							) }
							spacerMarginBottom={ 0 }
							error={ f.client_id }
						/>
						<Pf
							title={ ( 0, I18n.__ )(
								'Client Secret',
								'ohmylms'
							) }
							inputType={ 'textarea' }
							value={ a.client_secret }
							onChange={ function ( e ) {
								return S( 'client_secret', e );
							} }
							placeholder={ ( 0, I18n.__ )(
								'Enter your Zoom Client Secret',
								'ohmylms'
							) }
							spacerMarginBottom={ 0 }
							error={ f.client_secret }
						/>
						{ c && (
							<Pf
								title={ ( 0, I18n.__ )(
									'Webhook URL',
									'ohmylms'
								) }
								inputType={ 'text' }
								value={ c }
								readOnly={ ! 0 }
								spacerMarginBottom={ 0 }
								description={ ( 0, I18n.__ )(
									'Paste this into your Zoom app\'s Event Subscriptions endpoint URL, subscribed to the "Recording Completed" event.',
									'ohmylms'
								) }
							/>
						) }
						<Pf
							title={ ( 0, I18n.__ )(
								'Webhook Secret Token',
								'ohmylms'
							) }
							inputType={ 'text' }
							value={ a.webhook_secret_token }
							onChange={ function ( e ) {
								return S( 'webhook_secret_token', e );
							} }
							placeholder={ ( 0, I18n.__ )(
								"Enter the Secret Token from your Zoom app's Event Subscriptions page",
								'ohmylms'
							) }
							spacerMarginBottom={ 0 }
							description={ ( 0, I18n.__ )(
								'Required for auto-attaching cloud recordings — without it, recordings must be added by hand.',
								'ohmylms'
							) }
						/>
					</Controls.SpacerWP>
				</Controls.CardWP>
				<Controls.FlexWP justify={ 'flex-end' } gap={ 2 }>
					<Controls.ButtonWP isSecondary={ ! 0 } onClick={ n }>
						{ ( 0, I18n.__ )( 'Cancel', 'ohmylms' ) }
					</Controls.ButtonWP>
					<Controls.ButtonWP
						isPrimary={ ! 0 }
						onClick={ E }
						disabled={ d }
					>
						{ d
							? ( 0, I18n.__ )( 'Saving...', 'ohmylms' )
							: ( 0, I18n.__ )( 'Save', 'ohmylms' ) }
					</Controls.ButtonWP>
				</Controls.FlexWP>
			</React.Fragment>
		);
	};
}
