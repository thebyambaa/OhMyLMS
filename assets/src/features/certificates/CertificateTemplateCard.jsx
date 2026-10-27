import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createCertificateTemplateCard( readRuntime ) {
	return function CertificateTemplateCard( props ) {
		const {
			CB,
			I: Controls,
			Kee,
			React,
			T: StoreModule,
			Xee,
			b: I18n,
			ete,
			f: Router,
			g: ReactHooks,
			l,
			y: WordPressData,
		} = readRuntime();
		var t = props.template,
			n = props.setOpenModal,
			r =
				( ( 0, WordPressData.useDispatch )( StoreModule.default ),
				( 0, WordPressData.useSelect )( function ( e ) {
					return e( StoreModule.default ).selectCertificates();
				}, [] ),
				( 0, WordPressData.useSelect )( function ( e ) {
					return e(
						StoreModule.default
					).selectTotalCertificatesNumber();
				}, [] ),
				ete( ( 0, ReactHooks.useState )( ! 1 ), 2 ) ),
			a = ( r[ 0 ], r[ 1 ], ete( ( 0, ReactHooks.useState )( ! 1 ), 2 ) ),
			o = a[ 0 ],
			i = a[ 1 ],
			c = ( 0, Router.Zp )(),
			u = ( function () {
				var e,
					_rLocal =
						( ( e = Kee().m( function _eLocal() {
							var _rLocal2, _aLocal, _oLocal, _uLocal;
							return Kee().w(
								function ( _eLocal2 ) {
									for (;;) {
										switch ( ( _eLocal2.p = _eLocal2.n ) ) {
											case 0:
												return (
													( _eLocal2.p = 0 ),
													i( ! 0 ),
													( _aLocal = {
														name: 'Untitled',
														status: 'publish',
														contents:
															( t === null ||
															t === void 0
																? void 0
																: t.contents ) ||
															{},
														html_contents: CB(
															t === null ||
																t === void 0 ||
																null ===
																	( _rLocal2 =
																		t.contents ) ||
																void 0 ===
																	_rLocal2
																? void 0
																: _rLocal2.elements
														),
													} ),
													( _eLocal2.n = 1 ),
													l()( {
														path: '/ohmylms/v1/certificates',
														method: 'POST',
														headers: {
															'Content-Type':
																'application/json',
														},
														body: JSON.stringify(
															_aLocal
														),
													} )
												 );
											case 1:
												_oLocal = _eLocal2.v;
												n( ! 1 );
												c(
													'/certificate-edit/'.concat(
														_oLocal.id
													)
												);
												_eLocal2.n = 3;
												break;
											case 2:
												_eLocal2.p = 2;
												_uLocal = _eLocal2.v;
												showRequestError( _uLocal );
											// Falls through to the shared async state cleanup.
											case 3:
												return (
													( _eLocal2.p = 3 ),
													i( ! 1 ),
													_eLocal2.f( 3 )
												 );
											case 4:
												return _eLocal2.a( 2 );
										}
									}
								},
								_eLocal,
								null,
								[ [ 0, 2, 3, 4 ] ]
							);
						} ) ),
						function ( ..._args ) {
							var _tLocal = this,
								_nLocal = _args;
							return new Promise( function (
								_rLocal3,
								_aLocal2
							) {
								var _oLocal2 = e.apply( _tLocal, _nLocal );
								function _iLocal( _eLocal3 ) {
									Xee(
										_oLocal2,
										_rLocal3,
										_aLocal2,
										_iLocal,
										_lLocal,
										'next',
										_eLocal3
									);
								}
								function _lLocal( _eLocal4 ) {
									Xee(
										_oLocal2,
										_rLocal3,
										_aLocal2,
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
					return _rLocal.apply( this, _args2 );
				};
			} )();
		return (
			<React.Fragment>
				<Controls.CardWP>
					<Controls.SpacerWP padding={ 2 }>
						<Controls.FlexWP
							align={ 'center' }
							justify={ 'center' }
							direction={ 'column' }
							gap={ 2 }
						>
							<img
								src={
									t === null || t === void 0
										? void 0
										: t.image_src
								}
								alt={ ( 0, I18n.__ )(
									'Certificate Template',
									'ohmylms'
								) }
								style={ {
									maxWidth: '100%',
									objectFit: 'contain',
								} }
							/>
							<Controls.ButtonWP
								variant={ 'primary' }
								onClick={ function () {
									return u(
										t === null || t === void 0
											? void 0
											: t.id
									);
								} }
								loading={ o }
								tabIndex={ 0 }
								data-template-btn={ ! 0 }
								onKeyDown={ function ( e ) {
									if ( ! (
										'Enter' !== e.key && ' ' !== e.key
									) ) {
										u(
											t === null || t === void 0
												? void 0
												: t.id
										);
									}
								} }
							>
								{ ( 0, I18n.__ )(
									'Use this template',
									'ohmylms'
								) }
							</Controls.ButtonWP>
						</Controls.FlexWP>
					</Controls.SpacerWP>
				</Controls.CardWP>
			</React.Fragment>
		);
	};
}
