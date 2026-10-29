import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
export function createCertificateEditPage( readRuntime ) {
	return function CertificateEditPage() {
		const {
			Ete,
			HG,
			React,
			T: StoreModule,
			_,
			bte,
			f: Router,
			g: ReactHooks,
			uV,
			wte,
			y: WordPressData,
		} = readRuntime();
		HG( 'ohmylms', 'certificates' );
		var e = ( 0, Router.g )().id,
			t = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			n = ( function ( _eLocal, _tLocal ) {
				return (
					( function ( _eLocal2 ) {
						if ( Array.isArray( _eLocal2 ) ) {
							return _eLocal2;
						}
					} )( _eLocal ) ||
					( function ( _eLocal3, _tLocal2 ) {
						var _nLocal =
							_eLocal3 === null || _eLocal3 === void 0
								? null
								: ( 'undefined' !== typeof Symbol &&
										_eLocal3[ Symbol.iterator ] ) ||
									_eLocal3[ '@@iterator' ];
						if ( _nLocal !== null && _nLocal !== void 0 ) {
							var r,
								a,
								o,
								i,
								l = [],
								c = ! 0,
								u = ! 1;
							try {
								if (
									( ( o = ( _nLocal =
										_nLocal.call( _eLocal3 ) ).next ),
									0 === _tLocal2 )
								) {
									if ( Object( _nLocal ) !== _nLocal ) {
										return;
									}
									c = ! 1;
								} else {
									for (
										;
										! ( c = ( r = o.call( _nLocal ) )
											.done ) &&
										( l.push( r.value ),
										l.length !== _tLocal2 );
										c = ! 0
									) {}
								}
							} catch ( _eLocal4 ) {
								u = ! 0;
								a = _eLocal4;
							} finally {
								try {
									if (
										! c &&
										( ( _nullableValue ) =>
											_nullableValue !== null &&
											_nullableValue !== void 0 )(
											_nLocal.return
										) &&
										( ( i = _nLocal.return() ),
										Object( i ) !== i )
									) {
										return;
									}
								} finally {
									if ( u ) {
										throw a;
									}
								}
							}
							return l;
						}
					} )( _eLocal, _tLocal ) ||
					( function ( _eLocal5, _tLocal3 ) {
						if ( _eLocal5 ) {
							if ( 'string' === typeof _eLocal5 ) {
								return Ete( _eLocal5, _tLocal3 );
							}
							var _nLocal2 = {}.toString
								.call( _eLocal5 )
								.slice( 8, -1 );
							return (
								'Object' === _nLocal2 &&
									_eLocal5.constructor &&
									( _nLocal2 = _eLocal5.constructor.name ),
								( () => {
									if (
										'Map' === _nLocal2 ||
										'Set' === _nLocal2
									) {
										return Array.from( _eLocal5 );
									}
									if (
										'Arguments' === _nLocal2 ||
										/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(
											_nLocal2
										)
									) {
										return Ete( _eLocal5, _tLocal3 );
									}
									return void 0;
								} )()
							 );
						}
					} )( _eLocal, _tLocal ) ||
					( function () {
						throw new TypeError(
							'Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
						);
					} )()
				);
			} )( ( 0, ReactHooks.useState )( ! 0 ), 2 ),
			r = n[ 0 ],
			a = n[ 1 ],
			o = ( 0, Router.Zp )(),
			i = useNotifications(),
			l = i.openNotificationWithIcon,
			c = i.contextHolder,
			u = ( 0, WordPressData.useSelect )( function ( _eLocal6 ) {
				return _eLocal6( StoreModule.default ).getNotificationMessage();
			}, [] ),
			s = ( 0, WordPressData.useSelect )( function ( _eLocal7 ) {
				return _eLocal7( StoreModule.default ).getNotificationStatus();
			}, [] ),
			d = ( function () {
				var _nLocal3,
					_rLocal =
						( ( _nLocal3 = bte().m( function _nLocal4() {
							var _rLocal2, _iLocal;
							return bte().w(
								function ( _nLocal5 ) {
									for (;;) {
										switch ( ( _nLocal5.p = _nLocal5.n ) ) {
											case 0:
												return (
													( _nLocal5.p = 0 ),
													a( ! 0 ),
													( _nLocal5.n = 1 ),
													t.getCertificateById( e )
												 );
											case 1:
												_rLocal2 = _nLocal5.v;
												a( ! 1 );
												if ( ! _rLocal2 ) {
													o( '/certificates' );
												}
												_nLocal5.n = 3;
												break;
											case 2:
												_nLocal5.p = 2;
												_iLocal = _nLocal5.v;
												showRequestError( _iLocal );
											// Falls through to the shared async state cleanup.
											case 3:
												return _nLocal5.a( 2 );
										}
									}
								},
								_nLocal4,
								null,
								[ [ 0, 2 ] ]
							);
						} ) ),
						function ( ..._args ) {
							var _eLocal8 = this,
								_tLocal4 = _args;
							return new Promise( function ( _rLocal3, _aLocal ) {
								var _oLocal = _nLocal3.apply(
									_eLocal8,
									_tLocal4
								);
								function _iLocal2( _eLocal9 ) {
									wte(
										_oLocal,
										_rLocal3,
										_aLocal,
										_iLocal2,
										_lLocal,
										'next',
										_eLocal9
									);
								}
								function _lLocal( _eLocal0 ) {
									wte(
										_oLocal,
										_rLocal3,
										_aLocal,
										_iLocal2,
										_lLocal,
										'throw',
										_eLocal0
									);
								}
								_iLocal2( void 0 );
							} );
						} );
				return function ( ..._args2 ) {
					return _rLocal.apply( this, _args2 );
				};
			} )();
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					if ( e ) {
						d();
					} else {
						o( '/certificates' );
					}
				},
				[ e ]
			),
			( 0, ReactHooks.useEffect )(
				function () {
					if ( ! r && u ) {
						l( s, u );
					}
				},
				[ u ]
			),
			(
				<React.Fragment>
					<div>
						{ c }
						{ r ? (
							<_.A active={ ! 0 } />
						) : (
							React.createElement( uV, null )
						) }
					</div>
				</React.Fragment>
			 )
		 );
	};
}
