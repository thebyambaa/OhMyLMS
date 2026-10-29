import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createIntegrationCard( readRuntime ) {
	return function IntegrationCard( props ) {
		const { F6, N6, React, T6, b: I18n, g: ReactHooks } = readRuntime();
		var t = props.integration,
			n = props.onToggle,
			r = props.onManage,
			a = t.label,
			o = t.description,
			i = t.icon,
			l = t.hasSettings,
			c = t.is_enable,
			u = t.dependency,
			s = ( function ( e, _tLocal ) {
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
								_oLocal,
								_iLocal,
								_lLocal = [],
								_cLocal = ! 0,
								_uLocal = ! 1;
							try {
								if (
									( ( _oLocal = ( _nLocal =
										_nLocal.call( _eLocal2 ) ).next ),
									0 === _tLocal2 )
								) {
									if ( Object( _nLocal ) !== _nLocal ) {
										return;
									}
									_cLocal = ! 1;
								} else {
									for (
										;
										! ( _cLocal = ( _rLocal =
											_oLocal.call( _nLocal ) ).done ) &&
										( _lLocal.push( _rLocal.value ),
										_lLocal.length !== _tLocal2 );
										_cLocal = ! 0
									) {}
								}
							} catch ( _eLocal3 ) {
								_uLocal = ! 0;
								_aLocal = _eLocal3;
							} finally {
								try {
									if (
										! _cLocal &&
										( ( _nullableValue ) =>
											_nullableValue !== null &&
											_nullableValue !== void 0 )(
											_nLocal.return
										) &&
										( ( _iLocal = _nLocal.return() ),
										Object( _iLocal ) !== _iLocal )
									) {
										return;
									}
								} finally {
									if ( _uLocal ) {
										throw _aLocal;
									}
								}
							}
							return _lLocal;
						}
					} )( e, _tLocal ) ||
					( function ( _eLocal4, _tLocal3 ) {
						if ( _eLocal4 ) {
							if ( 'string' === typeof _eLocal4 ) {
								return N6( _eLocal4, _tLocal3 );
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
										return N6( _eLocal4, _tLocal3 );
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
			} )( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			d = s[ 0 ],
			m = s[ 1 ],
			p = ( function () {
				var e,
					_tLocal4 =
						( ( e = T6().m( function _eLocal5( _tLocal5 ) {
							var _rLocal2;
							return T6().w(
								function ( _eLocal6 ) {
									for (;;) {
										switch ( ( _eLocal6.p = _eLocal6.n ) ) {
											case 0:
												return (
													( _eLocal6.p = 0 ),
													m( ! 0 ),
													( _eLocal6.n = 1 ),
													n( _tLocal5 )
												 );
											case 1:
												_eLocal6.n = 3;
												break;
											case 2:
												_eLocal6.p = 2;
												_rLocal2 = _eLocal6.v;
												showRequestError(
													'Error toggling integration:',
													_rLocal2
												);
											// Falls through to the shared async state cleanup.
											case 3:
												return (
													( _eLocal6.p = 3 ),
													m( ! 1 ),
													_eLocal6.f( 3 )
												 );
											case 4:
												return _eLocal6.a( 2 );
										}
									}
								},
								_eLocal5,
								null,
								[ [ 0, 2, 3, 4 ] ]
							);
						} ) ),
						function ( ..._args ) {
							var _tLocal6 = this,
								_nLocal3 = _args;
							return new Promise( function (
								_rLocal3,
								_aLocal2
							) {
								var _oLocal2 = e.apply( _tLocal6, _nLocal3 );
								function _iLocal2( _eLocal7 ) {
									F6(
										_oLocal2,
										_rLocal3,
										_aLocal2,
										_iLocal2,
										_lLocal2,
										'next',
										_eLocal7
									);
								}
								function _lLocal2( _eLocal8 ) {
									F6(
										_oLocal2,
										_rLocal3,
										_aLocal2,
										_iLocal2,
										_lLocal2,
										'throw',
										_eLocal8
									);
								}
								_iLocal2( void 0 );
							} );
						} );
				return function ( ..._args2 ) {
					return _tLocal4.apply( this, _args2 );
				};
			} )();
		return (
			<Controls.CardWP isBorderless={ ! 0 } variant={ 'secondary' }>
				<Controls.SpacerWP
					padding={ 5 }
					margin={ 0 }
					marginBottom={ 0 }
				>
					<Controls.FlexWP gap={ 2 } justify={ 'space-between' }>
						<Controls.FlexWP gap={ 2 } justify={ 'flex-start' }>
							<img src={ i } alt={ a } />
							<Controls.HeadingWP
								level={ 3 }
								size={ 18 }
								weight={ 500 }
								color={ '#000D25' }
							>
								{ ( 0, I18n.__ )( a, 'ohmylms' ) }
							</Controls.HeadingWP>
						</Controls.FlexWP>
						{ u && (
							<Controls.FlexWP
								justify={ 'flex-end' }
								align={ 'center' }
								gap={ 0 }
								style={ {
									marginTop: '5px',
								} }
							>
								<svg
									width={ '16' }
									height={ '16' }
									viewBox={ '0 0 16 16' }
									fill={ 'none' }
									xmlns={ 'http://www.w3.org/2000/svg' }
									style={ {
										marginRight: '4px',
									} }
								>
									<path
										d={
											'M8 15C11.866 15 15 11.866 15 8C15 4.13401 11.866 1 8 1C4.13401 1 1 4.13401 1 8C1 11.866 4.13401 15 8 15Z'
										}
										stroke={ '#F59E0B' }
										strokeWidth={ '1.5' }
										strokeLinecap={ 'round' }
										strokeLinejoin={ 'round' }
									/>
									<path
										d={ 'M8 5.5V8.5' }
										stroke={ '#F59E0B' }
										strokeWidth={ '1.5' }
										strokeLinecap={ 'round' }
										strokeLinejoin={ 'round' }
									/>
									<path
										d={
											'M8 11.5C8.41421 11.5 8.75 11.1642 8.75 10.75C8.75 10.3358 8.41421 10 8 10C7.58579 10 7.25 10.3358 7.25 10.75C7.25 11.1642 7.58579 11.5 8 11.5Z'
										}
										fill={ '#F59E0B' }
									/>
								</svg>
								<Controls.TextWP
									size={ 13 }
									style={ {
										color: '#F59E0B',
									} }
								>
									{ u }
								</Controls.TextWP>
							</Controls.FlexWP>
						) }
					</Controls.FlexWP>
					<Controls.SpacerWP marginBottom={ 2 } />
					<Controls.TextWP size={ 14 } variant={ 'muted' }>
						{ o }
					</Controls.TextWP>
					<Controls.SpacerWP marginBottom={ 7 } />
					<Controls.FlexWP
						gap={ 3 }
						align={ 'center' }
						justify={ 'space-between' }
					>
						<Controls.FlexWP
							gap={ 1 }
							align={ 'center' }
							justify={ 'flex-start' }
						>
							{ l && (
								<Controls.ButtonWP
									variant={ 'secondary' }
									onClick={ function () {
										return r( t );
									} }
									aria-label={ ( 0, I18n.__ )(
										'Manage',
										'ohmylms'
									) }
									style={ {
										padding: '7px 10px',
										gap: '6px',
										borderRadius: '4px',
										height: 'auto',
										minHeight: 'auto',
									} }
								>
									<svg
										width={ '16' }
										height={ '16' }
										fill={ 'none' }
										viewBox={ '0 0 16 16' }
										xmlns={ 'http://www.w3.org/2000/svg' }
									>
										<path
											fill={ '#6E42D3' }
											fillRule={ 'evenodd' }
											d={
												'M6.29.836A1 1 0 017.274 0H8.58a1 1 0 01.987.836l.244 1.466c.787.26 1.503.679 2.108 1.218l1.393-.522a1 1 0 011.216.437l.653 1.13a1 1 0 01-.23 1.273l-1.148.944a6.026 6.026 0 010 2.435l1.15.946a1 1 0 01.23 1.272l-.654 1.13a1 1 0 01-1.216.437l-1.394-.522c-.605.54-1.32.958-2.108 1.218l-.244 1.466A1 1 0 018.58 16H7.275a1 1 0 01-.986-.836l-.244-1.466a5.994 5.994 0 01-2.108-1.218l-1.394.522a1 1 0 01-1.217-.436l-.653-1.131a1 1 0 01.23-1.272l1.15-.946a6.026 6.026 0 010-2.435l-1.15-.944a1 1 0 01-.23-1.272l.653-1.131a1 1 0 011.217-.437l1.393.522a5.994 5.994 0 012.108-1.218L6.29.836zM10.93 8a3 3 0 11-6 0 3 3 0 016 0z'
											}
											clipRule={ 'evenodd' }
										/>
									</svg>
									{ ( 0, I18n.__ )( 'Manage', 'ohmylms' ) }
								</Controls.ButtonWP>
							) }
						</Controls.FlexWP>
						<Controls.FlexWP justify={ 'flex-end' } gap={ 2 }>
							<Controls.SwitchWP
								checked={ c !== null && c !== void 0 && c }
								onChange={ function () {
									return p( t );
								} }
								loading={ d }
							/>
						</Controls.FlexWP>
					</Controls.FlexWP>
				</Controls.SpacerWP>
			</Controls.CardWP>
		);
	};
}
