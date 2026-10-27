import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createSetupNiche( readRuntime ) {
	return function SetupNiche( props ) {
		const {
			Cne,
			Ene,
			Gte,
			I: Controls,
			Pne,
			React,
			T: StoreModule,
			X0,
			_ne,
			b: I18n,
			g: ReactHooks,
			hB,
			m,
			une,
			wne,
			xne,
			y: WordPressData,
		} = readRuntime();
		var t,
			n,
			r,
			a,
			o = props.onTabChange,
			i = props.onWizardSkip,
			l = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			c = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getSetupWizardData();
			}, [] ),
			u = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getCurrencySettings();
			}, [] ),
			s = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getDesignSettings();
			}, [] ),
			d = ( function ( e, _tLocal ) {
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
					Pne( e, _tLocal ) ||
					( function () {
						throw new TypeError(
							'Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
						);
					} )()
				);
			} )( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			p = d[ 0 ],
			f = d[ 1 ],
			v = [].concat(
				Cne(
					null !== ( t = window ) &&
						void 0 !== t &&
						null !== ( t = t.ohmylms_params ) &&
						void 0 !== t &&
						t.is_tutor_lms_active
						? [
								{
									label: ( 0, I18n.__ )(
										'Tutor LMS',
										'ohmylms'
									),
									value: 'tutorLMS',
								},
							]
						: []
				),
				Cne(
					null !== ( n = window ) &&
						void 0 !== n &&
						null !== ( n = n.ohmylms_params ) &&
						void 0 !== n &&
						n.is_learndash_lms_active
						? [
								{
									label: ( 0, I18n.__ )(
										'LearnDash',
										'ohmylms'
									),
									value: 'learnDash',
								},
							]
						: []
				),
				Cne(
					null !== ( r = window ) &&
						void 0 !== r &&
						null !== ( r = r.ohmylms_params ) &&
						void 0 !== r &&
						r.is_learnpress_active
						? [
								{
									label: ( 0, I18n.__ )(
										'LearnPress',
										'ohmylms'
									),
									value: 'learnPress',
								},
							]
						: []
				),
				Cne(
					null !== ( a = window ) &&
						void 0 !== a &&
						null !== ( a = a.ohmylms_params ) &&
						void 0 !== a &&
						a.is_masterstudy_active
						? [
								{
									label: ( 0, I18n.__ )(
										'MasterStudy LMS',
										'ohmylms'
									),
									value: 'masterStudy',
								},
							]
						: []
				)
			),
			h = v.length > 0,
			_ = ( function () {
				var e = xne(
					Ene().m( function _eLocal4() {
						var _tLocal3, _nLocal2, _rLocal2;
						return Ene().w(
							function ( _eLocal5 ) {
								for (;;) {
									switch ( ( _eLocal5.p = _eLocal5.n ) ) {
										case 0:
											if ( c.certificate ) {
												_eLocal5.n = 1;
												break;
											}
											return _eLocal5.a( 2, null );
										case 1:
											if (
												( ( _eLocal5.p = 1 ),
												( _tLocal3 = hB.find(
													function ( _eLocal6 ) {
														return (
															_eLocal6.id ===
															c.certificate
														);
													}
												) ) )
											) {
												_eLocal5.n = 2;
												break;
											}
											return _eLocal5.a( 2, null );
										case 2:
											return (
												( _eLocal5.n = 3 ),
												m( {
													path: '/ohmylms/v1/certificates/',
													method: 'POST',
													data: {
														name: 'Certificate Template '.concat(
															c.certificate
														),
														status: 'publish',
														contents:
															_tLocal3.contents,
														template_thumbnail:
															_tLocal3.image_src,
													},
												} )
											 );
										case 3:
											return (
												( _nLocal2 = _eLocal5.v ),
												_eLocal5.a(
													2,
													( _nLocal2 === null ||
													_nLocal2 === void 0
														? void 0
														: _nLocal2.id ) || null
												)
											 );
										case 4:
											return (
												( _eLocal5.p = 4 ),
												( _rLocal2 = _eLocal5.v ),
												showRequestError(
													'Error creating certificate:',
													_rLocal2
												),
												_eLocal5.a( 2, null )
											 );
									}
								}
							},
							_eLocal4,
							null,
							[ [ 1, 4 ] ]
						);
					} )
				);
				return function ( ..._args ) {
					return e.apply( this, _args );
				};
			} )(),
			w = ( function () {
				var e = xne(
					Ene().m( function _eLocal7() {
						var _tLocal4,
							_nLocal3,
							_rLocal3,
							_aLocal2,
							_oLocal2,
							_iLocal2,
							_dLocal,
							_mLocal,
							_pLocal,
							_fLocal,
							_vLocal,
							g,
							_hLocal,
							y,
							b,
							_wLocal;
						return Ene().w(
							function ( _eLocal8 ) {
								for (;;) {
									switch ( ( _eLocal8.p = _eLocal8.n ) ) {
										case 0:
											return (
												( _eLocal8.p = 0 ),
												( _eLocal8.n = 1 ),
												_()
											 );
										case 1:
											return (
												( y = _eLocal8.v ),
												( b = {
													optin: {
														ohmylms_allow_tracking:
															c !== null &&
															c !== void 0 &&
															c.isOptEnabled
																? 'yes'
																: 'no',
													},
													language:
														null !==
															( _tLocal4 =
																c.language ) &&
														void 0 !== _tLocal4
															? _tLocal4
															: 'en_US',
													certificate: c.certificate,
													certificate_id: y,
													niche: c.niche
														? [ c.niche ]
														: [],
													level: c.level,
													design: {
														ohmylms_archive_page_layout:
															( () => {
																if (
																	null !==
																		( _nLocal3 =
																			c.archive_page_layout ) &&
																	void 0 !==
																		_nLocal3
																) {
																	return _nLocal3;
																}
																if (
																	null ===
																		( _rLocal3 =
																			s.ohmylms_archive_page_layout ) ||
																	void 0 ===
																		_rLocal3
																) {
																	return void 0;
																}
																return _rLocal3.value;
															} )(),
														ohmylms_columns_per_row:
															c.courses_per_row ||
															( null ===
																( _aLocal2 =
																	s.ohmylms_columns_per_row ) ||
															void 0 === _aLocal2
																? void 0
																: _aLocal2.value ) ||
															4,
														ohmylms_courses_per_page:
															c.courses_per_page ||
															( null ===
																( _oLocal2 =
																	s.ohmylms_courses_per_page ) ||
															void 0 === _oLocal2
																? void 0
																: _oLocal2.value ) ||
															10,
													},
													currency: {
														ohmylms_currency:
															( () => {
																if (
																	null !==
																		( _iLocal2 =
																			c.currency ) &&
																	void 0 !==
																		_iLocal2
																) {
																	return _iLocal2;
																}
																if (
																	u ===
																		null ||
																	u ===
																		void 0 ||
																	null ===
																		( _dLocal =
																			u.ohmylms_currency ) ||
																	void 0 ===
																		_dLocal
																) {
																	return void 0;
																}
																return _dLocal.value;
															} )(),
														ohmylms_currency_pos:
															( u === null ||
															u === void 0 ||
															null ===
																( _mLocal =
																	u.ohmylms_currency_pos ) ||
															void 0 === _mLocal
																? void 0
																: _mLocal.value ) ||
															'left',
														ohmylms_price_thousand_sep:
															( u === null ||
															u === void 0 ||
															null ===
																( _pLocal =
																	u.ohmylms_price_thousand_sep ) ||
															void 0 === _pLocal
																? void 0
																: _pLocal.value ) ||
															',',
														ohmylms_price_decimal_sep:
															( u === null ||
															u === void 0 ||
															null ===
																( _fLocal =
																	u.ohmylms_price_decimal_sep ) ||
															void 0 === _fLocal
																? void 0
																: _fLocal.value ) ||
															'.',
														ohmylms_price_num_decimals:
															( u === null ||
															u === void 0 ||
															null ===
																( _vLocal =
																	u.ohmylms_price_num_decimals ) ||
															void 0 === _vLocal
																? void 0
																: _vLocal.value ) ||
															'2',
													},
													contact: {
														email: ( () => {
															if (
																c !== null &&
																c !== void 0 &&
																c.isOptEnabled
															) {
																if (
																	null ===
																		( g =
																			window.ohmylms_params ) ||
																	void 0 === g
																) {
																	return void 0;
																}
																return g.admin_email;
															}
															return '';
														} )(),
														name: ( () => {
															if (
																c !== null &&
																c !== void 0 &&
																c.isOptEnabled
															) {
																if (
																	null ===
																		( _hLocal =
																			window.ohmylms_params ) ||
																	void 0 ===
																		_hLocal
																) {
																	return void 0;
																}
																return _hLocal.admin_name;
															}
															return '';
														} )(),
													},
													wizard_data: c,
												} ),
												( _eLocal8.n = 2 ),
												l.saveSetup( b )
											 );
										case 2:
											return (
												( _eLocal8.n = 3 ),
												S(
													c === null || c === void 0
														? void 0
														: c.niche
												)
											 );
										case 3:
											if ( y ) {
												l.setSetupWizardData( {
													certificate_id: y,
												} );
											}
											_eLocal8.n = 5;
											break;
										case 4:
											_eLocal8.p = 4;
											_wLocal = _eLocal8.v;
											showRequestError(
												'Error saving setup wizard data:',
												_wLocal
											);
										// Falls through to the shared async state cleanup.
										case 5:
											return _eLocal8.a( 2 );
									}
								}
							},
							_eLocal7,
							null,
							[ [ 0, 4 ] ]
						);
					} )
				);
				return function ( ..._args2 ) {
					return e.apply( this, _args2 );
				};
			} )(),
			E = function () {
				if (
					'beginner' ===
					( c === null || c === void 0 ? void 0 : c.level )
				) {
					o( 'wizard-preferences' );
				} else {
					( () => {
						if (
							'intermediate' ===
							( c === null || c === void 0 ? void 0 : c.level )
						) {
							return o( 'wizard-completion' );
						}
						if ( c !== null && c !== void 0 && c.migrate_courses ) {
							return (
								h &&
									1 === v.length &&
									l.setSetupWizardData( {
										selectedPlatform: v[ 0 ].value,
										skipPlatformSelection: ! 0,
									} ),
								o( 'wizard-creation' )
							 );
						}
						return o( 'wizard-completion' );
					} )();
				}
			},
			S = ( function () {
				var e = xne(
					Ene().m( function _eLocal9( _tLocal5 ) {
						var _nLocal4, _rLocal4, _aLocal3, _oLocal3;
						return Ene().w(
							function ( _eLocal0 ) {
								for (;;) {
									switch ( ( _eLocal0.p = _eLocal0.n ) ) {
										case 0:
											if (
												_tLocal5 &&
												'others' !== _tLocal5 &&
												une[ _tLocal5 ]
											) {
												_eLocal0.n = 1;
												break;
											}
											return _eLocal0.a( 2 );
										case 1:
											return (
												( _eLocal0.p = 1 ),
												( _rLocal4 =
													une[ _tLocal5 ].data ),
												( _eLocal0.n = 2 ),
												m( {
													path: '/ohmylms/v1/setup-wizard/import-course',
													method: 'POST',
													data: _rLocal4,
													headers: {
														nonce: window
															.ohmylms_params
															.setup_wizard_nonce,
													},
												} )
											 );
										case 2:
											if (
												( ( _nullableValue2 ) =>
													_nullableValue2 !== null &&
													_nullableValue2 !==
														void 0 )(
													( _aLocal3 = _eLocal0.v )
												) &&
												null !==
													( _nLocal4 =
														_aLocal3.course_ids ) &&
												void 0 !== _nLocal4 &&
												_nLocal4.length
											) {
												l.setSetupWizardData( {
													imported_course_ids:
														_aLocal3.course_ids,
												} );
											}
											_eLocal0.n = 4;
											break;
										case 3:
											_eLocal0.p = 3;
											_oLocal3 = _eLocal0.v;
											showRequestError(
												'Error importing sample course:',
												_oLocal3
											);
										// Falls through to the shared async state cleanup.
										case 4:
											return _eLocal0.a( 2 );
									}
								}
							},
							_eLocal9,
							null,
							[ [ 1, 3 ] ]
						);
					} )
				);
				return function ( ..._args5 ) {
					return e.apply( this, _args5 );
				};
			} )(),
			R = ( function () {
				var e = xne(
					Ene().m( function _eLocal1() {
						return Ene().w( function ( _eLocal10 ) {
							for (;;) {
								switch ( _eLocal10.n ) {
									case 0:
										if ( ! p ) {
											_eLocal10.n = 1;
											break;
										}
										return _eLocal10.a( 2 );
									case 1:
										if (
											'intermediate' !==
												( c === null || c === void 0
													? void 0
													: c.level ) &&
											( 'experienced' !==
												( c === null || c === void 0
													? void 0
													: c.level ) ||
												( c !== null &&
													c !== void 0 &&
													c.migrate_courses &&
													h ) )
										) {
											_eLocal10.n = 3;
											break;
										}
										return (
											f( ! 0 ),
											( _eLocal10.n = 2 ),
											w()
										 );
									case 2:
										f( ! 1 );
									// Falls through to the shared async state cleanup.
									case 3:
										E();
									// Falls through to the shared async state cleanup.
									case 4:
										return _eLocal10.a( 2 );
								}
							}
						}, _eLocal1 );
					} )
				);
				return function ( ..._args3 ) {
					return e.apply( this, _args3 );
				};
			} )(),
			x = ( function () {
				var e = xne(
					Ene().m( function _eLocal11() {
						var _tLocal7,
							_nLocal5,
							_rLocal5,
							_aLocal4,
							_oLocal4,
							_iLocal3,
							_dLocal2,
							_mLocal2,
							_pLocal2,
							_fLocal2,
							_vLocal2,
							g,
							_hLocal2,
							y,
							b,
							_wLocal2;
						return Ene().w(
							function ( _eLocal12 ) {
								for (;;) {
									switch ( ( _eLocal12.p = _eLocal12.n ) ) {
										case 0:
											return (
												( _eLocal12.p = 0 ),
												( _eLocal12.n = 1 ),
												_()
											 );
										case 1:
											return (
												( y = _eLocal12.v ),
												( b = {
													optin: {
														ohmylms_allow_tracking:
															c !== null &&
															c !== void 0 &&
															c.isOptEnabled
																? 'yes'
																: 'no',
													},
													language:
														null !==
															( _tLocal7 =
																c.language ) &&
														void 0 !== _tLocal7
															? _tLocal7
															: 'en_US',
													certificate: c.certificate,
													certificate_id: y,
													level: c.level,
													design: {
														ohmylms_archive_page_layout:
															( () => {
																if (
																	null !==
																		( _nLocal5 =
																			c.archive_page_layout ) &&
																	void 0 !==
																		_nLocal5
																) {
																	return _nLocal5;
																}
																if (
																	null ===
																		( _rLocal5 =
																			s.ohmylms_archive_page_layout ) ||
																	void 0 ===
																		_rLocal5
																) {
																	return void 0;
																}
																return _rLocal5.value;
															} )(),
														ohmylms_columns_per_row:
															c.courses_per_row ||
															( null ===
																( _aLocal4 =
																	s.ohmylms_columns_per_row ) ||
															void 0 === _aLocal4
																? void 0
																: _aLocal4.value ) ||
															4,
														ohmylms_courses_per_page:
															c.courses_per_page ||
															( null ===
																( _oLocal4 =
																	s.ohmylms_courses_per_page ) ||
															void 0 === _oLocal4
																? void 0
																: _oLocal4.value ) ||
															10,
													},
													currency: {
														ohmylms_currency:
															( () => {
																if (
																	null !==
																		( _iLocal3 =
																			c.currency ) &&
																	void 0 !==
																		_iLocal3
																) {
																	return _iLocal3;
																}
																if (
																	u ===
																		null ||
																	u ===
																		void 0 ||
																	null ===
																		( _dLocal2 =
																			u.ohmylms_currency ) ||
																	void 0 ===
																		_dLocal2
																) {
																	return void 0;
																}
																return _dLocal2.value;
															} )(),
														ohmylms_currency_pos:
															( u === null ||
															u === void 0 ||
															null ===
																( _mLocal2 =
																	u.ohmylms_currency_pos ) ||
															void 0 === _mLocal2
																? void 0
																: _mLocal2.value ) ||
															'left',
														ohmylms_price_thousand_sep:
															( u === null ||
															u === void 0 ||
															null ===
																( _pLocal2 =
																	u.ohmylms_price_thousand_sep ) ||
															void 0 === _pLocal2
																? void 0
																: _pLocal2.value ) ||
															',',
														ohmylms_price_decimal_sep:
															( u === null ||
															u === void 0 ||
															null ===
																( _fLocal2 =
																	u.ohmylms_price_decimal_sep ) ||
															void 0 === _fLocal2
																? void 0
																: _fLocal2.value ) ||
															'.',
														ohmylms_price_num_decimals:
															( u === null ||
															u === void 0 ||
															null ===
																( _vLocal2 =
																	u.ohmylms_price_num_decimals ) ||
															void 0 === _vLocal2
																? void 0
																: _vLocal2.value ) ||
															'2',
													},
													contact: {
														email: ( () => {
															if (
																c !== null &&
																c !== void 0 &&
																c.isOptEnabled
															) {
																if (
																	null ===
																		( g =
																			window.ohmylms_params ) ||
																	void 0 === g
																) {
																	return void 0;
																}
																return g.admin_email;
															}
															return '';
														} )(),
														name: ( () => {
															if (
																c !== null &&
																c !== void 0 &&
																c.isOptEnabled
															) {
																if (
																	null ===
																		( _hLocal2 =
																			window.ohmylms_params ) ||
																	void 0 ===
																		_hLocal2
																) {
																	return void 0;
																}
																return _hLocal2.admin_name;
															}
															return '';
														} )(),
													},
													wizard_data: c,
												} ),
												( _eLocal12.n = 2 ),
												l.saveSetup( b )
											 );
										case 2:
											if ( y ) {
												l.setSetupWizardData( {
													certificate_id: y,
												} );
											}
											_eLocal12.n = 4;
											break;
										case 3:
											_eLocal12.p = 3;
											_wLocal2 = _eLocal12.v;
											showRequestError(
												'Error saving setup wizard data:',
												_wLocal2
											);
										// Falls through to the shared async state cleanup.
										case 4:
											return _eLocal12.a( 2 );
									}
								}
							},
							_eLocal11,
							null,
							[ [ 0, 3 ] ]
						);
					} )
				);
				return function ( ..._args4 ) {
					return e.apply( this, _args4 );
				};
			} )(),
			C = [
				{
					label: ( 0, I18n.__ )(
						'Digital Marketing & Growth',
						'ohmylms'
					),
					value: 'digital-marketing-growth',
				},
				{
					label: ( 0, I18n.__ )( 'Tech Skills', 'ohmylms' ),
					value: 'tech-skills',
				},
				{
					label: ( 0, I18n.__ )( 'Creator Economy', 'ohmylms' ),
					value: 'creator-economy',
				},
				{
					label: ( 0, I18n.__ )( 'Others', 'ohmylms' ),
					value: 'others',
				},
			],
			P = [
				{
					label: h
						? ( 0, I18n.__ )( 'Yes, migrate my content', 'ohmylms' )
						: ( 0, I18n.__ )( 'Yes, import a course', 'ohmylms' ),
					id: ! 0,
				},
				{
					label: ( 0, I18n.__ )( 'No, start fresh', 'ohmylms' ),
					id: ! 1,
				},
			];
		return (
			<React.Fragment>
				<Gte
					level={ c === null || c === void 0 ? void 0 : c.level }
					currentStep={
						'experienced' ===
							( c === null || c === void 0 ? void 0 : c.level ) ||
						'intermediate' ===
							( c === null || c === void 0 ? void 0 : c.level )
							? 1
							: 0
					}
					isShowIndicator={ ! 0 }
					onSkip={ function () {
						return i === null || i === void 0
							? void 0
							: i( 'niche' );
					} }
				/>
				<Controls.ContainerWP>
					<div
						className={
							'ohmylms-setup-wizard-level-selection-wrapper ohmylms-setup-wizard-card-wrapper'
						}
					>
						<div className={ 'ohmylms-setup-wizard__container' }>
							<div className={ 'ohmylms-setup-wizard__header' }>
								<Controls.HeadingWP
									as={ 'h2' }
									color={ '#000d25' }
									size={ '24' }
									align={ 'center' }
									weight={ '600' }
								>
									{ ( 0, I18n.__ )(
										'Your journey starts here!',
										'ohmylms'
									) }
								</Controls.HeadingWP>
								<Controls.TextWP
									as={ 'p' }
									size={ '18' }
									color={ '#687784' }
									align={ 'center' }
									weight={ '400' }
									style={ {
										maxWidth: '400px',
										margin: 'auto',
									} }
								>
									{ ( 0, I18n.__ )(
										"Tell us what you want to achieve first. We'll guide you step by step",
										'ohmylms'
									) }
								</Controls.TextWP>
							</div>
							<Controls.FlexWP
								direction={ 'column' }
								gap={ 6 }
								style={ {
									minWidth: '780px',
								} }
							>
								<Controls.CardWP isBorderless={ ! 0 }>
									<Controls.SpacerWP
										padding={ 6 }
										marginBottom={ 0 }
									>
										<Controls.FlexWP
											gap={ 3 }
											direction={ 'column' }
										>
											<X0.A
												as={ 'h3' }
												size={ '18' }
												color={ '#000D25' }
												weight={ '600' }
											>
												{ ( 0, I18n.__ )(
													"What's your course niche?",
													'ohmylms'
												) }
											</X0.A>
											<wne.A
												selected={
													( c === null || c === void 0
														? void 0
														: c.niche ) || ''
												}
												onChange={ function ( e ) {
													return l.setSetupWizardData(
														{
															niche: e,
														}
													);
												} }
												options={ C }
												className={
													'ohmylms-setup-wizard-niche-options'
												}
											/>
											<Controls.TextWP
												as={ 'p' }
												size={ '12' }
												weight={ '400' }
												color={ '#687784' }
											>
												{ ( 0, I18n.__ )(
													"Don't overthink it - you can change this anytime.",
													'ohmylms'
												) }
											</Controls.TextWP>
										</Controls.FlexWP>
									</Controls.SpacerWP>
								</Controls.CardWP>
								{ 'experienced' ===
									( c === null || c === void 0
										? void 0
										: c.level ) &&
									h && (
										<Controls.CardWP
											isBorderless={ ! 0 }
											style={ {
												width: '768px',
											} }
										>
											<Controls.SpacerWP
												padding={ 6 }
												marginBottom={ 0 }
											>
												<Controls.FlexWP
													gap={ 3 }
													direction={ 'column' }
												>
													<X0.A
														as={ 'h3' }
														size={ '18' }
														color={ '#000D25' }
														weight={ '600' }
													>
														{ ( function () {
															if ( h ) {
																var e,
																	_tLocal8 =
																		v.map(
																			function (
																				_eLocal13
																			) {
																				return _eLocal13.label;
																			}
																		);
																return (
																	( e =
																		( () => {
																			if (
																				1 ===
																				_tLocal8.length
																			) {
																				return _tLocal8[ 0 ];
																			}
																			if (
																				2 ===
																				_tLocal8.length
																			) {
																				return _tLocal8.join(
																					' or '
																				);
																			}
																			return (
																				_tLocal8
																					.slice(
																						0,
																						-1
																					)
																					.join(
																						', '
																					) +
																				', or ' +
																				_tLocal8[
																					_tLocal8.length -
																						1
																				]
																			);
																		} )() ),
																	( 0,
																	I18n.__ )(
																		'Do you want to migrate your courses from '.concat(
																			e,
																			'?'
																		),
																		'ohmylms'
																	)
																 );
															}
															return ( 0,
															I18n.__ )(
																'Do you want to import a course?',
																'ohmylms'
															);
														} )() }
													</X0.A>
													<Controls.FlexWP
														flexWrap={ 'wrap' }
														gap={ 3 }
														align={ 'start' }
														justify={ 'start' }
													>
														{ P.map(
															function (
																e,
																_tLocal9
															) {
																return (
																	<_ne
																		key={
																			_tLocal9
																		}
																		checked={
																			e.id ===
																			( c ===
																				null ||
																			c ===
																				void 0
																				? void 0
																				: c.migrate_courses )
																		}
																		onChange={ function () {
																			return l.setSetupWizardData(
																				{
																					migrate_courses:
																						e.id,
																				}
																			);
																		} }
																		label={
																			e.label
																		}
																		id={
																			e.id
																		}
																	/>
																);
															}
														) }
													</Controls.FlexWP>
													<Controls.TextWP
														as={ 'p' }
														size={ '12' }
														weight={ '400' }
														color={
															void 0 ===
															c.migrate_courses
																? '#687784'
																: '#6E42D3'
														}
													>
														{ c.migrate_courses
															? ( 0, I18n.__ )(
																	'Nothing will be imported without your approval.',
																	'ohmylms'
																)
															: ( () => {
																	if (
																		! 1 ===
																		c.migrate_courses
																	) {
																		if (
																			h
																		) {
																			return ( 0,
																			I18n.__ )(
																				'You can always migrate later if you change your mind.',
																				'ohmylms'
																			);
																		}
																		return ( 0,
																		I18n.__ )(
																			'You can always import later if you change your mind.',
																			'ohmylms'
																		);
																	}
																	return ( 0,
																	I18n.__ )(
																		'This is completely optional — you can start fresh if you like.',
																		'ohmylms'
																	);
																} )() }
													</Controls.TextWP>
												</Controls.FlexWP>
											</Controls.SpacerWP>
										</Controls.CardWP>
									) }
							</Controls.FlexWP>
						</div>
					</div>
					<Controls.SpacerWP marginBottom={ 0 } marginTop={ 6 }>
						<Controls.FlexWP
							items={ 'center' }
							justify={ 'between' }
							gap={ 4 }
							style={ {
								maxWidth: '846px',
								justifyContent: 'space-between',
								margin: '0 auto',
							} }
						>
							<Controls.ButtonWP
								variant={ 'secondary' }
								onClick={ function () {
									if (
										'beginner' ===
										( c === null || c === void 0
											? void 0
											: c.level )
									) {
										o( 'wizard-level-selection' );
									} else {
										o( 'wizard-preferences' );
									}
								} }
							>
								{ ( 0, I18n.__ )( 'Back', 'ohmylms' ) }
							</Controls.ButtonWP>
							<Controls.FlexWP
								items={ 'center' }
								justify={ 'end' }
								gap={ 6 }
							>
								<Controls.ButtonWP
									variant={ 'tertiary' }
									onClick={ function () {
										l.setSetupWizardData( {
											niche: null,
										} );
										x();
										E();
									} }
									disabled={ p }
								>
									{ ( 0, I18n.__ )(
										'Skip this step',
										'ohmylms'
									) }
								</Controls.ButtonWP>
								<Controls.ButtonWP
									variant={ 'primary' }
									onClick={ R }
									isBusy={ p }
								>
									{ ( 0, I18n.__ )( 'Continue', 'ohmylms' ) }
								</Controls.ButtonWP>
							</Controls.FlexWP>
						</Controls.FlexWP>
					</Controls.SpacerWP>
				</Controls.ContainerWP>
			</React.Fragment>
		);
	};
}
