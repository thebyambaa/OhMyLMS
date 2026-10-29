/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createGeneralSettingsFields( readRuntime ) {
	return function GeneralSettingsFields( props ) {
		const {
			Nm,
			React,
			T: StoreModule,
			b: I18n,
			bK,
			gK,
			hK,
			y: WordPressData,
		} = readRuntime();
		var t = props.pages,
			n = void 0 === t ? [] : t,
			r = props.setPages,
			a = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getGeneralSettings();
			}, [] ),
			o = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			i = function ( e, _tLocal ) {
				o.updateGeneralSettings(
					( function ( _eLocal, _tLocal2, _nLocal ) {
						return (
							( _tLocal2 = ( function ( _eLocal2 ) {
								var _tLocal3 = ( function ( _eLocal3 ) {
									if (
										'object' !== gK( _eLocal3 ) ||
										! _eLocal3
									) {
										return _eLocal3;
									}
									var _tLocal4 =
										_eLocal3[ Symbol.toPrimitive ];
									if ( void 0 !== _tLocal4 ) {
										var _nLocal2 = _tLocal4.call(
											_eLocal3,
											'string'
										);
										if ( 'object' !== gK( _nLocal2 ) ) {
											return _nLocal2;
										}
										throw new TypeError(
											'@@toPrimitive must return a primitive value.'
										);
									}
									return String( _eLocal3 );
								} )( _eLocal2 );
								return 'symbol' === gK( _tLocal3 )
									? _tLocal3
									: _tLocal3 + '';
							} )( _tLocal2 ) ) in _eLocal
								? Object.defineProperty( _eLocal, _tLocal2, {
										value: _nLocal,
										enumerable: ! 0,
										configurable: ! 0,
										writable: ! 0,
									} )
								: ( _eLocal[ _tLocal2 ] = _nLocal ),
							_eLocal
						 );
					} )( {}, e, {
						value:
							_tLocal === null || _tLocal === void 0
								? void 0
								: _tLocal.value,
						meta_data:
							_tLocal === null || _tLocal === void 0
								? void 0
								: _tLocal.label,
					} )
				);
			},
			l = ( function () {
				var e,
					_tLocal5 =
						( ( e = hK().m( function _eLocal4( _tLocal6 ) {
							var _rLocal;
							return hK().w( function ( _eLocal5 ) {
								for (;;) {
									if ( 0 === _eLocal5.n ) {
										return (
											( _rLocal = n.filter(
												function ( _eLocal6 ) {
													return _eLocal6.label
														.toLowerCase()
														.includes(
															_tLocal6.toLowerCase()
														);
												}
											) ),
											_eLocal5.a( 2, _rLocal )
										 );
									}
								}
							}, _eLocal4 );
						} ) ),
						function ( ..._args ) {
							var _tLocal7 = this,
								_nLocal3 = _args;
							return new Promise( function ( _rLocal2, _aLocal ) {
								var _oLocal = e.apply( _tLocal7, _nLocal3 );
								function _iLocal( _eLocal7 ) {
									bK(
										_oLocal,
										_rLocal2,
										_aLocal,
										_iLocal,
										_lLocal,
										'next',
										_eLocal7
									);
								}
								function _lLocal( _eLocal8 ) {
									bK(
										_oLocal,
										_rLocal2,
										_aLocal,
										_iLocal,
										_lLocal,
										'throw',
										_eLocal8
									);
								}
								_iLocal( void 0 );
							} );
						} );
				return function ( ..._args2 ) {
					return _tLocal5.apply( this, _args2 );
				};
			} )(),
			c = function ( e ) {
				var _tLocal8 = ( function ( _eLocal0 ) {
					var _tLocal9 =
						a === null || a === void 0 ? void 0 : a[ _eLocal0 ];
					return ( () => {
						if ( _tLocal9 ) {
							if (
								'object' === gK( _tLocal9 ) &&
								void 0 !== _tLocal9.value
							) {
								return _tLocal9.value;
							}
							return _tLocal9;
						}
						return null;
					} )();
				} )( e );
				if ( ! _tLocal8 || '-1' === _tLocal8 || -1 === _tLocal8 ) {
					return [];
				}
				var _rLocal3 = n.find( function ( _eLocal1 ) {
					return (
						_eLocal1.value === _tLocal8 ||
						_eLocal1.value === String( _tLocal8 )
					);
				} );
				return _rLocal3 ? [ _rLocal3 ] : [];
			};
		return (
			<React.Fragment>
				<div className={ 'ohmylms-settings-general-card-wrapper' }>
					<Controls.CardWP isBorderless={ ! 0 }>
						<Controls.SpacerWP
							paddingY={ 4 }
							paddingX={ 2 }
							marginTop={ 0 }
							marginBottom={ 4 }
						>
							<Nm
								title={ ( 0, I18n.__ )(
									'Course Archive Page',
									'ohmylms'
								) }
								description={ ( 0, I18n.__ )(
									'Select the page where all your available courses will be displayed.',
									'ohmylms'
								) }
								placeholder={ ( 0, I18n.__ )(
									'Type to Select Option',
									'ohmylms'
								) }
								data={ n }
								setData={ r }
								onChange={ function ( e ) {
									return i( 'ohmylms_course_page_id', e );
								} }
								staticSearch={ ! 1 }
								value={ c( 'ohmylms_course_page_id' ) }
								defaultOptions={ n }
								loadOptions={ l }
								isClearable={ ! 1 }
								isSearchable={ ! 0 }
								isMulti={ ! 1 }
								showDivider={ ! 0 }
							/>
							<Nm
								title={ ( 0, I18n.__ )(
									'Checkout Page',
									'ohmylms'
								) }
								description={ ( 0, I18n.__ )(
									'Choose the page students will use to complete their course purchases.',
									'ohmylms'
								) }
								placeholder={ ( 0, I18n.__ )(
									'Type to Select Option',
									'ohmylms'
								) }
								data={ n }
								setData={ r }
								onChange={ function ( e ) {
									return i( 'ohmylms_checkout_page_id', e );
								} }
								staticSearch={ ! 1 }
								value={ c( 'ohmylms_checkout_page_id' ) }
								defaultOptions={ n }
								loadOptions={ l }
								isClearable={ ! 1 }
								isSearchable={ ! 0 }
								isMulti={ ! 1 }
								showDivider={ ! 0 }
							/>
							<Nm
								title={ ( 0, I18n.__ )(
									'Student Dashboard',
									'ohmylms'
								) }
								description={ ( 0, I18n.__ )(
									'Select the page where students can access their dashboard and overview.',
									'ohmylms'
								) }
								placeholder={ ( 0, I18n.__ )(
									'Type to Select Option',
									'ohmylms'
								) }
								data={ n }
								setData={ r }
								onChange={ function ( e ) {
									return i(
										'ohmylms_student_dashboard_page_id',
										e
									);
								} }
								value={ c(
									'ohmylms_student_dashboard_page_id'
								) }
								defaultOptions={ n }
								loadOptions={ l }
								isClearable={ ! 1 }
								isSearchable={ ! 0 }
								isMulti={ ! 1 }
								showDivider={ ! 0 }
							/>
							<Nm
								title={ ( 0, I18n.__ )(
									'Student Courses Page',
									'ohmylms'
								) }
								description={ ( 0, I18n.__ )(
									'Select the page where students can view and manage their enrolled courses.',
									'ohmylms'
								) }
								placeholder={ ( 0, I18n.__ )(
									'Type to Select Option',
									'ohmylms'
								) }
								data={ n }
								setData={ r }
								onChange={ function ( e ) {
									return i(
										'ohmylms_student_courses_page_id',
										e
									);
								} }
								value={ c( 'ohmylms_student_courses_page_id' ) }
								defaultOptions={ n }
								loadOptions={ l }
								isClearable={ ! 1 }
								isSearchable={ ! 0 }
								isMulti={ ! 1 }
								showDivider={ ! 0 }
							/>
							<Nm
								title={ ( 0, I18n.__ )(
									'Student Profile Page',
									'ohmylms'
								) }
								description={ ( 0, I18n.__ )(
									'Select the page where students can manage their profile information and settings.',
									'ohmylms'
								) }
								placeholder={ ( 0, I18n.__ )(
									'Type to Select Option',
									'ohmylms'
								) }
								data={ n }
								setData={ r }
								onChange={ function ( e ) {
									return i(
										'ohmylms_student_profile_page_id',
										e
									);
								} }
								value={ c( 'ohmylms_student_profile_page_id' ) }
								defaultOptions={ n }
								loadOptions={ l }
								isClearable={ ! 1 }
								isSearchable={ ! 0 }
								isMulti={ ! 1 }
								showDivider={ ! 0 }
							/>
							<Nm
								title={ ( 0, I18n.__ )(
									'Terms and Conditions Page',
									'ohmylms'
								) }
								description={ ( 0, I18n.__ )(
									'Assign the page that outlines your terms and conditions.',
									'ohmylms'
								) }
								placeholder={ ( 0, I18n.__ )(
									'Type to Select Option',
									'ohmylms'
								) }
								data={ n }
								setData={ r }
								onChange={ function ( e ) {
									return i( 'ohmylms_terms_page_id', e );
								} }
								value={ c( 'ohmylms_terms_page_id' ) }
								defaultOptions={ n }
								loadOptions={ l }
								isClearable={ ! 1 }
								isSearchable={ ! 0 }
								isMulti={ ! 1 }
							/>
						</Controls.SpacerWP>
					</Controls.CardWP>
				</div>
			</React.Fragment>
		);
	};
}
