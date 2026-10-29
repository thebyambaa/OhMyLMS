/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { InfoIcon } from '../../shared/icons';
export function createCertificateTextField( readRuntime ) {
	return function CertificateTextField( props ) {
		const {
			EL,
			I: Controls,
			PL,
			React,
			SL,
			b: I18n,
			g: ReactHooks,
			xL,
		} = readRuntime();
		var t = true,
			n = props.label,
			r = props.value,
			a = props.color,
			o = props.onValueChange,
			i = props.onColorChange,
			l = props.placeholder,
			c = ( props.presets, props.isItProFeature ),
			u = ( function ( e, _tLocal ) {
				if ( e === null || e === void 0 ) {
					return {};
				}
				var _nLocal,
					_rLocal,
					_aLocal = ( function ( _eLocal, _tLocal2 ) {
						if ( _eLocal === null || _eLocal === void 0 ) {
							return {};
						}
						var _nLocal2 = {};
						for ( var _rLocal2 in _eLocal ) {
							if ( {}.hasOwnProperty.call( _eLocal, _rLocal2 ) ) {
								if ( -1 !== _tLocal2.indexOf( _rLocal2 ) ) {
									continue;
								}
								_nLocal2[ _rLocal2 ] = _eLocal[ _rLocal2 ];
							}
						}
						return _nLocal2;
					} )( e, _tLocal );
				if ( Object.getOwnPropertySymbols ) {
					var _oLocal = Object.getOwnPropertySymbols( e );
					for ( _rLocal = 0; _rLocal < _oLocal.length; _rLocal++ ) {
						_nLocal = _oLocal[ _rLocal ];
						if (
							-1 === _tLocal.indexOf( _nLocal ) &&
							{}.propertyIsEnumerable.call( e, _nLocal )
						) {
							_aLocal[ _nLocal ] = e[ _nLocal ];
						}
					}
				}
				return _aLocal;
			} )( props, EL ),
			s = ( function ( e, _tLocal3 ) {
				return (
					( function ( _eLocal2 ) {
						if ( Array.isArray( _eLocal2 ) ) {
							return _eLocal2;
						}
					} )( e ) ||
					( function ( _eLocal3, _tLocal4 ) {
						var _nLocal3 =
							_eLocal3 === null || _eLocal3 === void 0
								? null
								: ( 'undefined' !== typeof Symbol &&
										_eLocal3[ Symbol.iterator ] ) ||
									_eLocal3[ '@@iterator' ];
						if ( _nLocal3 !== null && _nLocal3 !== void 0 ) {
							var _rLocal3,
								_aLocal2,
								_oLocal2,
								_iLocal,
								_lLocal = [],
								_cLocal = ! 0,
								_uLocal = ! 1;
							try {
								if (
									( ( _oLocal2 = ( _nLocal3 =
										_nLocal3.call( _eLocal3 ) ).next ),
									0 === _tLocal4 )
								) {
									if ( Object( _nLocal3 ) !== _nLocal3 ) {
										return;
									}
									_cLocal = ! 1;
								} else {
									for (
										;
										! ( _cLocal = ( _rLocal3 =
											_oLocal2.call( _nLocal3 ) )
											.done ) &&
										( _lLocal.push( _rLocal3.value ),
										_lLocal.length !== _tLocal4 );
										_cLocal = ! 0
									) {}
								}
							} catch ( _eLocal4 ) {
								_uLocal = ! 0;
								_aLocal2 = _eLocal4;
							} finally {
								try {
									if (
										! _cLocal &&
										( ( _nullableValue ) =>
											_nullableValue !== null &&
											_nullableValue !== void 0 )(
											_nLocal3.return
										) &&
										( ( _iLocal = _nLocal3.return() ),
										Object( _iLocal ) !== _iLocal )
									) {
										return;
									}
								} finally {
									if ( _uLocal ) {
										throw _aLocal2;
									}
								}
							}
							return _lLocal;
						}
					} )( e, _tLocal3 ) ||
					( function ( _eLocal5, _tLocal5 ) {
						if ( _eLocal5 ) {
							if ( 'string' === typeof _eLocal5 ) {
								return PL( _eLocal5, _tLocal5 );
							}
							var _nLocal4 = {}.toString
								.call( _eLocal5 )
								.slice( 8, -1 );
							return (
								'Object' === _nLocal4 &&
									_eLocal5.constructor &&
									( _nLocal4 = _eLocal5.constructor.name ),
								( () => {
									if (
										'Map' === _nLocal4 ||
										'Set' === _nLocal4
									) {
										return Array.from( _eLocal5 );
									}
									if (
										'Arguments' === _nLocal4 ||
										/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(
											_nLocal4
										)
									) {
										return PL( _eLocal5, _tLocal5 );
									}
									return void 0;
								} )()
							 );
						}
					} )( e, _tLocal3 ) ||
					( function () {
						throw new TypeError(
							'Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
						);
					} )()
				);
			} )( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		return (
			s[ 0 ],
			s[ 1 ],
			(
				<React.Fragment>
					<Controls.FlexWP direction={ 'column' } gap={ '2' }>
						<Controls.HeadingWP level={ 4 }>
							<Controls.FlexWP gap={ 1 } justify={ 'start' }>
								{ n }
								{ c && ! t && (
									<React.Fragment>
										<Controls.TooltipWP
											title={ ( 0, I18n.__ )(
												'Enable PRO plugin for access this feature.',
												'ohmylms'
											) }
										>
											<InfoIcon />
										</Controls.TooltipWP>
									</React.Fragment>
								) }
							</Controls.FlexWP>
						</Controls.HeadingWP>
						<Controls.FlexWP justify={ 'flex-start' } gap={ '2' }>
							<Controls.FlexItemWP isBlock={ ! 0 }>
								<Controls.InputWP
									{ ...SL(
										{
											value: r,
											onChange( e ) {
												return o( e );
											},
											type: 'text',
											placeholder: l,
											style: xL(
												xL(
													{},
													u === null || u === void 0
														? void 0
														: u.style
												),
												{},
												{
													width: '100%',
												}
											),
											disabled: c && ! t,
										},
										u
									) }
								/>
							</Controls.FlexItemWP>
							<Controls.FlexItemWP>
								<Controls.ColorPickerWP
									initialColor={ a }
									onChange={ function ( e ) {
										i(
											e === null || e === void 0
												? void 0
												: e.hex
										);
									} }
									disabled={ c && ! t }
								/>
							</Controls.FlexItemWP>
						</Controls.FlexWP>
					</Controls.FlexWP>
				</React.Fragment>
			 )
		 );
	};
}
