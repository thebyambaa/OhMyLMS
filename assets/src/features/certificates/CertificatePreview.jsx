/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createCertificatePreview( readRuntime ) {
	return function CertificatePreview( props ) {
		const {
			GL,
			React,
			T: StoreModule,
			UL,
			g: ReactHooks,
			y: WordPressData,
		} = readRuntime();
		var t,
			n = props.certificateRef,
			r = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).selectCertificate();
			}, [] ),
			a = ( function ( e, _tLocal ) {
				return (
					( function ( _eLocal ) {
						if ( Array.isArray( _eLocal ) ) {
							return _eLocal;
						}
					} )( e ) ||
					( function ( _eLocal2, _tLocal2 ) {
						var _nLocal =
							_eLocal2 === null || _eLocal2 === void 0
								? null
								: ( 'undefined' !== typeof Symbol &&
										_eLocal2[ Symbol.iterator ] ) ||
									_eLocal2[ '@@iterator' ];
						if ( _nLocal !== null && _nLocal !== void 0 ) {
							var _rLocal,
								_aLocal,
								o,
								i,
								l = [],
								c = ! 0,
								u = ! 1;
							try {
								if (
									( ( o = ( _nLocal =
										_nLocal.call( _eLocal2 ) ).next ),
									0 === _tLocal2 )
								) {
									if ( Object( _nLocal ) !== _nLocal ) {
										return;
									}
									c = ! 1;
								} else {
									for (
										;
										! ( c = ( _rLocal = o.call( _nLocal ) )
											.done ) &&
										( l.push( _rLocal.value ),
										l.length !== _tLocal2 );
										c = ! 0
									) {}
								}
							} catch ( _eLocal3 ) {
								u = ! 0;
								_aLocal = _eLocal3;
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
										throw _aLocal;
									}
								}
							}
							return l;
						}
					} )( e, _tLocal ) ||
					( function ( _eLocal4, _tLocal3 ) {
						if ( _eLocal4 ) {
							if ( 'string' === typeof _eLocal4 ) {
								return UL( _eLocal4, _tLocal3 );
							}
							var _nLocal2 = {}.toString
								.call( _eLocal4 )
								.slice( 8, -1 );
							return (
								'Object' === _nLocal2 &&
									_eLocal4.constructor &&
									( _nLocal2 = _eLocal4.constructor.name ),
								( () => {
									if (
										'Map' === _nLocal2 ||
										'Set' === _nLocal2
									) {
										return Array.from( _eLocal4 );
									}
									if (
										'Arguments' === _nLocal2 ||
										/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(
											_nLocal2
										)
									) {
										return UL( _eLocal4, _tLocal3 );
									}
									return void 0;
								} )()
							 );
						}
					} )( e, _tLocal ) ||
					( function () {
						throw new TypeError(
							'Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
						);
					} )()
				);
			} )( ( 0, ReactHooks.useState )( 1 ), 2 ),
			o = a[ 0 ],
			i = a[ 1 ];
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					var e = function () {
						var _eLocal5 =
								n === null || n === void 0 ? void 0 : n.current,
							_tLocal4 =
								_eLocal5 === null || _eLocal5 === void 0
									? void 0
									: _eLocal5.parentElement;
						if ( _eLocal5 && _tLocal4 ) {
							var _rLocal2 =
								_tLocal4.clientWidth / _eLocal5.scrollWidth -
								0.07;
							i( Math.min( 1, _rLocal2 ) );
						}
					};
					return (
						e(),
						window.addEventListener( 'resize', e ),
						function () {
							window.removeEventListener( 'resize', e );
						}
					 );
				},
				[ n ]
			),
			(
				<Controls.SpacerWP padding={ 4 }>
					<Controls.FlexWP align={ 'center' } justify={ 'center' }>
						<div
							ref={ n }
							style={ {
								transform: 'scale('.concat( o, ')' ),
								transformOrigin: 'top',
							} }
						>
							{ ( r === null ||
							r === void 0 ||
							null === ( t = r.contents ) ||
							void 0 === t
								? void 0
								: t.elements
							).map( function ( e, _tLocal5 ) {
								return (
									<React.Fragment key={ _tLocal5 }>
										{ GL( e, _tLocal5.toString() ) }
									</React.Fragment>
								);
							} ) }
						</div>
					</Controls.FlexWP>
				</Controls.SpacerWP>
			 )
		 );
	};
}
