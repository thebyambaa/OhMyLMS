/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
import { decodeHtmlEntities } from '../../shared/utils';
export function createPrerequisiteSettings( readRuntime ) {
	return function PrerequisiteSettings( props ) {
		const {
			Kt,
			React,
			an,
			b: I18n,
			g: ReactHooks,
			nn,
			on,
			tn,
		} = readRuntime();
		var title = props.title,
			tooltip = props.tooltip,
			onChange = props.onChange,
			isChecked = props.isChecked,
			o =
				( props.childTitle,
				props.childPlaceholder,
				props.childNotFoundMessage,
				props.isMultiple,
				props.onSearch ),
			onChildChange = props.onChildChange,
			defaultValue = props.defaultValue,
			showDivider = props.showDivider,
			u = void 0 === showDivider || showDivider,
			s = ( function ( e, t ) {
				return (
					( function ( _eLocal ) {
						if ( Array.isArray( _eLocal ) ) {
							return _eLocal;
						}
					} )( e ) ||
					( function ( _eLocal2, _tLocal ) {
						var n =
							_eLocal2 === null || _eLocal2 === void 0
								? null
								: ( 'undefined' !== typeof Symbol &&
										_eLocal2[ Symbol.iterator ] ) ||
									_eLocal2[ '@@iterator' ];
						if ( n !== null && n !== void 0 ) {
							var r,
								a,
								_oLocal,
								i,
								l = [],
								c = ! 0,
								_uLocal = ! 1;
							try {
								if (
									( ( _oLocal = ( n = n.call( _eLocal2 ) )
										.next ),
									0 === _tLocal )
								) {
									if ( Object( n ) !== n ) {
										return;
									}
									c = ! 1;
								} else {
									for (
										;
										! ( c = ( r = _oLocal.call( n ) )
											.done ) &&
										( l.push( r.value ),
										l.length !== _tLocal );
										c = ! 0
									) {}
								}
							} catch ( _eLocal3 ) {
								_uLocal = ! 0;
								a = _eLocal3;
							} finally {
								try {
									if (
										! c &&
										( ( _nullableValue ) =>
											_nullableValue !== null &&
											_nullableValue !== void 0 )(
											n.return
										) &&
										( ( i = n.return() ),
										Object( i ) !== i )
									) {
										return;
									}
								} finally {
									if ( _uLocal ) {
										throw a;
									}
								}
							}
							return l;
						}
					} )( e, t ) ||
					( function ( _eLocal4, _tLocal2 ) {
						if ( _eLocal4 ) {
							if ( 'string' === typeof _eLocal4 ) {
								return on( _eLocal4, _tLocal2 );
							}
							var n = {}.toString.call( _eLocal4 ).slice( 8, -1 );
							return (
								'Object' === n &&
									_eLocal4.constructor &&
									( n = _eLocal4.constructor.name ),
								( () => {
									if ( 'Map' === n || 'Set' === n ) {
										return Array.from( _eLocal4 );
									}
									if (
										'Arguments' === n ||
										/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(
											n
										)
									) {
										return on( _eLocal4, _tLocal2 );
									}
									return void 0;
								} )()
							 );
						}
					} )( e, t ) ||
					( function () {
						throw new TypeError(
							'Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
						);
					} )()
				);
			} )(
				( 0, ReactHooks.useState )(
					( 0, I18n.__ )(
						'Please enter 3 or more characters...',
						'ohmylms'
					)
				),
				2
			),
			d = s[ 0 ],
			m = s[ 1 ],
			p = ( function () {
				var e,
					t =
						( ( e = nn().m( function _eLocal5( _tLocal3 ) {
							var n, r, a, i;
							return nn().w( function ( _eLocal6 ) {
								for (;;) {
									switch ( _eLocal6.n ) {
										case 0:
											if ( ! ( _tLocal3.length >= 3 ) ) {
												_eLocal6.n = 4;
												break;
											}
											return (
												( _eLocal6.n = 1 ),
												o( _tLocal3 )
											 );
										case 1:
											if (
												0 !==
												( ( ( _nullableValue2 ) =>
													_nullableValue2 === null ||
													_nullableValue2 ===
														void 0 )(
													( r = _eLocal6.v )
												) ||
												null === ( n = r.data ) ||
												void 0 === n
													? void 0
													: n.length )
											) {
												_eLocal6.n = 2;
												break;
											}
											return (
												m(
													( 0, I18n.__ )(
														'No Content Found! Try to search another one',
														'ohmylms'
													)
												),
												_eLocal6.a( 2, [] )
											 );
										case 2:
											return (
												m(
													( 0, I18n.__ )(
														'Please enter 3 or more characters...',
														'ohmylms'
													)
												),
												( i =
													r === null ||
													r === void 0 ||
													null === ( a = r.data ) ||
													void 0 === a
														? void 0
														: a.map(
																function (
																	_eLocal7
																) {
																	return {
																		label: decodeHtmlEntities(
																			_eLocal7 ===
																				null ||
																				_eLocal7 ===
																					void 0
																				? void 0
																				: _eLocal7.label
																		),
																		value:
																			_eLocal7 ===
																				null ||
																			_eLocal7 ===
																				void 0
																				? void 0
																				: _eLocal7.value,
																	};
																}
															) ),
												_eLocal6.a( 2, i || [] )
											 );
										case 3:
											_eLocal6.n = 5;
											break;
										case 4:
											return _eLocal6.a( 2, [] );
										case 5:
											return _eLocal6.a( 2 );
									}
								}
							}, _eLocal5 );
						} ) ),
						function ( ..._args ) {
							var _tLocal4 = this,
								n = _args;
							return new Promise( function ( r, a ) {
								var _oLocal2 = e.apply( _tLocal4, n );
								function i( _eLocal8 ) {
									an(
										_oLocal2,
										r,
										a,
										i,
										l,
										'next',
										_eLocal8
									);
								}
								function l( _eLocal9 ) {
									an(
										_oLocal2,
										r,
										a,
										i,
										l,
										'throw',
										_eLocal9
									);
								}
								i( void 0 );
							} );
						} );
				return function ( ..._args2 ) {
					return t.apply( this, _args2 );
				};
			} )();
		return (
			<React.Fragment>
				<Kt
					title={ title }
					tooltip={ tooltip }
					customClass={
						'ohmylms-lesson-settings-prerequisites-button'
					}
					onChange={ onChange }
					isChecked={ isChecked }
					showDivider={ u }
					conditionalChild={
						<React.Fragment>
							<Controls.SpacerWP marginBottom={ 3 } />
							<Controls.CardWP
								isBorderless={ ! 0 }
								variant={ 'secondary' }
							>
								<Controls.SpacerWP
									marginBottom={ 0 }
									padding={ 4 }
								>
									{ React.createElement( tn, {
										description: ( 0, I18n.__ )(
											'Members can access this content if they have completed all of the following content:',
											'ohmylms'
										),
										spacerMarginBottom: 0,
										value: defaultValue,
										onChange( e ) {
											onChildChange( e );
										},
										cacheOptions: ! 0,
										loadOptions: p,
										noOptionsMessage() {
											return d;
										},
										closeMenuOnSelect: ! 1,
										padding: 0,
										direction: 'column',
										gap: 1,
										flexItemWidth: '100%',
									} ) }
								</Controls.SpacerWP>
							</Controls.CardWP>
						</React.Fragment>
					}
				/>
			</React.Fragment>
		);
	};
}
