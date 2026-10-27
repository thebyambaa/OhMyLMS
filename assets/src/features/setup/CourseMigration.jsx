import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createCourseMigration( readRuntime ) {
	return function CourseMigration( props ) {
		const {
			Dne,
			Gne,
			Gte,
			Hne,
			I: Controls,
			Lne,
			React,
			T: StoreModule,
			Vne,
			Wne,
			b: I18n,
			g: ReactHooks,
			hB,
			l,
			qne,
			une,
			y: WordPressData,
		} = readRuntime();
		var t = props.onTabChange,
			n = props.handleBack,
			r = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			a = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getSetupWizardData();
			}, [] ),
			o = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getCurrencySettings();
			}, [] ),
			i = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getDesignSettings();
			}, [] ),
			c = Vne( ( 0, ReactHooks.useState )( [] ), 2 ),
			u = c[ 0 ],
			s = c[ 1 ],
			d = Vne( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			m = d[ 0 ],
			p = d[ 1 ],
			f = Vne( ( 0, ReactHooks.useState )( [] ), 2 ),
			v = f[ 0 ],
			h = f[ 1 ],
			_ = Vne( ( 0, ReactHooks.useState )( 'selection' ), 2 ),
			w = _[ 0 ],
			E = _[ 1 ],
			S = Vne( ( 0, ReactHooks.useState )( 0 ), 2 ),
			R = S[ 0 ],
			x = S[ 1 ],
			C = Vne( ( 0, ReactHooks.useState )( -1 ), 2 ),
			P = ( C[ 0 ], C[ 1 ] ),
			O = ( 0, ReactHooks.useMemo )(
				function () {
					var e,
						_tLocal,
						_nLocal =
							a === null || a === void 0
								? void 0
								: a.selectedPlatform,
						_rLocal = {
							label: _nLocal,
							icon:
								( null === ( e = window.ohmylms_params ) ||
								void 0 === e
									? void 0
									: e.plugin_assets ) +
								'images/creator-logo.svg',
						},
						_oLocal =
							( null === ( _tLocal = window.ohmylms_params ) ||
							void 0 === _tLocal
								? void 0
								: _tLocal.plugin_assets ) + 'images/';
					return (
						'tutorLMS' === _nLocal
							? ( _rLocal = {
									label: 'Tutor LMS',
									icon: _oLocal + 'tutor_icon.svg',
								} )
							: ( () => {
									if ( 'learnDash' === _nLocal ) {
										return ( _rLocal = {
											label: 'LearnDash',
											icon:
												_oLocal + 'learndash_icon.svg',
										} );
									}
									if ( 'learnPress' === _nLocal ) {
										return ( _rLocal = {
											label: 'LearnPress',
											icon:
												_oLocal + 'learnpress_icon.svg',
										} );
									}
									return (
										'masterStudy' === _nLocal &&
										( _rLocal = {
											label: 'MasterStudy LMS',
											icon:
												_oLocal +
												'masterstudy_icon.svg',
										} )
									);
								} )(),
						_rLocal
					 );
				},
				[ a === null || a === void 0 ? void 0 : a.selectedPlatform ]
			);
		( 0, ReactHooks.useEffect )(
			function () {
				var e = ( function () {
					var _eLocal = Lne(
						Wne().m( function _eLocal2() {
							var _tLocal2, _nLocal2;
							return Wne().w(
								function ( _eLocal3 ) {
									for (;;) {
										switch ( ( _eLocal3.p = _eLocal3.n ) ) {
											case 0:
												if (
													a !== null &&
													a !== void 0 &&
													a.selectedPlatform
												) {
													_eLocal3.n = 1;
													break;
												}
												return _eLocal3.a( 2 );
											case 1:
												return (
													p( ! 0 ),
													( _eLocal3.p = 2 ),
													( _eLocal3.n = 3 ),
													l()( {
														path: '/ohmylms/v1/migrations/'.concat(
															a.selectedPlatform,
															'/courses'
														),
													} )
												 );
											case 3:
												if (
													( ( _nullableValue ) =>
														_nullableValue !==
															null &&
														_nullableValue !==
															void 0 )(
														( _tLocal2 =
															_eLocal3.v )
													) &&
													_tLocal2.courses
												) {
													{
														s( _tLocal2.courses );
														h(
															_tLocal2.courses.map(
																function (
																	_eLocal4
																) {
																	return _eLocal4.id;
																}
															)
														);
													}
												}
												_eLocal3.n = 5;
												break;
											case 4:
												_eLocal3.p = 4;
												_nLocal2 = _eLocal3.v;
												showRequestError(
													'Failed to fetch courses',
													_nLocal2
												);
											// Falls through to the shared async state cleanup.
											case 5:
												return (
													( _eLocal3.p = 5 ),
													p( ! 1 ),
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
					return function ( ..._args ) {
						return _eLocal.apply( this, _args );
					};
				} )();
				e();
			},
			[ a === null || a === void 0 ? void 0 : a.selectedPlatform ]
		);
		var k = ( function () {
				var e = Lne(
					Wne().m( function _eLocal5() {
						var _tLocal3, _nLocal3, _rLocal2;
						return Wne().w(
							function ( _eLocal6 ) {
								for (;;) {
									switch ( ( _eLocal6.p = _eLocal6.n ) ) {
										case 0:
											if ( a.certificate ) {
												_eLocal6.n = 1;
												break;
											}
											return _eLocal6.a( 2, null );
										case 1:
											if (
												( ( _eLocal6.p = 1 ),
												( _tLocal3 = hB.find(
													function ( _eLocal7 ) {
														return (
															_eLocal7.id ===
															a.certificate
														);
													}
												) ) )
											) {
												_eLocal6.n = 2;
												break;
											}
											return _eLocal6.a( 2, null );
										case 2:
											return (
												( _eLocal6.n = 3 ),
												l()( {
													path: '/ohmylms/v1/certificates/',
													method: 'POST',
													data: {
														name: 'Certificate Template '.concat(
															a.certificate
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
												( _nLocal3 = _eLocal6.v ),
												_eLocal6.a(
													2,
													( _nLocal3 === null ||
													_nLocal3 === void 0
														? void 0
														: _nLocal3.id ) || null
												)
											 );
										case 4:
											return (
												( _eLocal6.p = 4 ),
												( _rLocal2 = _eLocal6.v ),
												showRequestError(
													'Error creating certificate:',
													_rLocal2
												),
												_eLocal6.a( 2, null )
											 );
									}
								}
							},
							_eLocal5,
							null,
							[ [ 1, 4 ] ]
						);
					} )
				);
				return function ( ..._args2 ) {
					return e.apply( this, _args2 );
				};
			} )(),
			j = ( function () {
				var e = Lne(
					Wne().m( function _eLocal8( _tLocal4 ) {
						var _nLocal4, _aLocal, _oLocal2, _iLocal;
						return Wne().w(
							function ( _eLocal9 ) {
								for (;;) {
									switch ( ( _eLocal9.p = _eLocal9.n ) ) {
										case 0:
											if (
												_tLocal4 &&
												'others' !== _tLocal4 &&
												une[ _tLocal4 ]
											) {
												_eLocal9.n = 1;
												break;
											}
											return _eLocal9.a( 2 );
										case 1:
											return (
												( _eLocal9.p = 1 ),
												( _aLocal =
													une[ _tLocal4 ].data ),
												( _eLocal9.n = 2 ),
												l()( {
													path: '/ohmylms/v1/setup-wizard/import-course',
													method: 'POST',
													data: _aLocal,
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
													( _oLocal2 = _eLocal9.v )
												) &&
												null !==
													( _nLocal4 =
														_oLocal2.course_ids ) &&
												void 0 !== _nLocal4 &&
												_nLocal4.length
											) {
												r.setSetupWizardData( {
													imported_course_ids:
														_oLocal2.course_ids,
												} );
											}
											_eLocal9.n = 4;
											break;
										case 3:
											_eLocal9.p = 3;
											_iLocal = _eLocal9.v;
											showRequestError(
												'Error importing sample course:',
												_iLocal
											);
										// Falls through to the shared async state cleanup.
										case 4:
											return _eLocal9.a( 2 );
									}
								}
							},
							_eLocal8,
							null,
							[ [ 1, 3 ] ]
						);
					} )
				);
				return function ( ..._args6 ) {
					return e.apply( this, _args6 );
				};
			} )(),
			A = ( function () {
				var e = Lne(
					Wne().m( function _eLocal0() {
						var _tLocal6,
							_nLocal5,
							_lLocal,
							_cLocal,
							_uLocal,
							_sLocal,
							_dLocal,
							_mLocal,
							_pLocal,
							_fLocal,
							_vLocal,
							g,
							_hLocal,
							y,
							b,
							_Local;
						return Wne().w(
							function ( _eLocal1 ) {
								for (;;) {
									switch ( ( _eLocal1.p = _eLocal1.n ) ) {
										case 0:
											return (
												( _eLocal1.p = 0 ),
												( _eLocal1.n = 1 ),
												k()
											 );
										case 1:
											return (
												( y = _eLocal1.v ),
												( b = {
													optin: {
														ohmylms_allow_tracking:
															a !== null &&
															a !== void 0 &&
															a.isOptEnabled
																? 'yes'
																: 'no',
													},
													language:
														null !==
															( _tLocal6 =
																a.language ) &&
														void 0 !== _tLocal6
															? _tLocal6
															: 'en_US',
													certificate: a.certificate,
													certificate_id: y,
													niche: a.niche
														? [ a.niche ]
														: [],
													level: a.level,
													design: {
														ohmylms_archive_page_layout:
															( () => {
																if (
																	null !==
																		( _nLocal5 =
																			a.archive_page_layout ) &&
																	void 0 !==
																		_nLocal5
																) {
																	return _nLocal5;
																}
																if (
																	null ===
																		( _lLocal =
																			i.ohmylms_archive_page_layout ) ||
																	void 0 ===
																		_lLocal
																) {
																	return void 0;
																}
																return _lLocal.value;
															} )(),
														ohmylms_columns_per_row:
															a.courses_per_row ||
															( null ===
																( _cLocal =
																	i.ohmylms_columns_per_row ) ||
															void 0 === _cLocal
																? void 0
																: _cLocal.value ) ||
															4,
														ohmylms_courses_per_page:
															a.courses_per_page ||
															( null ===
																( _uLocal =
																	i.ohmylms_courses_per_page ) ||
															void 0 === _uLocal
																? void 0
																: _uLocal.value ) ||
															10,
													},
													currency: {
														ohmylms_currency:
															( () => {
																if (
																	null !==
																		( _sLocal =
																			a.currency ) &&
																	void 0 !==
																		_sLocal
																) {
																	return _sLocal;
																}
																if (
																	o ===
																		null ||
																	o ===
																		void 0 ||
																	null ===
																		( _dLocal =
																			o.ohmylms_currency ) ||
																	void 0 ===
																		_dLocal
																) {
																	return void 0;
																}
																return _dLocal.value;
															} )(),
														ohmylms_currency_pos:
															( o === null ||
															o === void 0 ||
															null ===
																( _mLocal =
																	o.ohmylms_currency_pos ) ||
															void 0 === _mLocal
																? void 0
																: _mLocal.value ) ||
															'left',
														ohmylms_price_thousand_sep:
															( o === null ||
															o === void 0 ||
															null ===
																( _pLocal =
																	o.ohmylms_price_thousand_sep ) ||
															void 0 === _pLocal
																? void 0
																: _pLocal.value ) ||
															',',
														ohmylms_price_decimal_sep:
															( o === null ||
															o === void 0 ||
															null ===
																( _fLocal =
																	o.ohmylms_price_decimal_sep ) ||
															void 0 === _fLocal
																? void 0
																: _fLocal.value ) ||
															'.',
														ohmylms_price_num_decimals:
															( o === null ||
															o === void 0 ||
															null ===
																( _vLocal =
																	o.ohmylms_price_num_decimals ) ||
															void 0 === _vLocal
																? void 0
																: _vLocal.value ) ||
															'2',
													},
													contact: {
														email: ( () => {
															if (
																a !== null &&
																a !== void 0 &&
																a.isOptEnabled
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
																a !== null &&
																a !== void 0 &&
																a.isOptEnabled
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
													wizard_data: a,
												} ),
												( _eLocal1.n = 2 ),
												r.saveSetup( b )
											 );
										case 2:
											return (
												( _eLocal1.n = 3 ),
												j(
													a === null || a === void 0
														? void 0
														: a.niche
												)
											 );
										case 3:
											if ( y ) {
												r.setSetupWizardData( {
													certificate_id: y,
												} );
											}
											_eLocal1.n = 5;
											break;
										case 4:
											_eLocal1.p = 4;
											_Local = _eLocal1.v;
											showRequestError(
												'Error saving setup wizard data:',
												_Local
											);
										// Falls through to the shared async state cleanup.
										case 5:
											return _eLocal1.a( 2 );
									}
								}
							},
							_eLocal0,
							null,
							[ [ 0, 4 ] ]
						);
					} )
				);
				return function ( ..._args3 ) {
					return e.apply( this, _args3 );
				};
			} )(),
			M = ( function () {
				var e = Lne(
					Wne().m( function _eLocal10() {
						var _nLocal6, _rLocal3, _oLocal3;
						return Wne().w(
							function ( _eLocal11 ) {
								for (;;) {
									switch ( ( _eLocal11.p = _eLocal11.n ) ) {
										case 0:
											return (
												E( 'migrating' ),
												( _eLocal11.n = 1 ),
												new Promise( function (
													_eLocal12
												) {
													return setTimeout(
														_eLocal12,
														800
													);
												} )
											 );
										case 1:
											return (
												x( 1 ),
												( _eLocal11.n = 2 ),
												new Promise( function (
													_eLocal13
												) {
													return setTimeout(
														_eLocal13,
														800
													);
												} )
											 );
										case 2:
											x( 2 );
											_nLocal6 = 0;
										// Falls through to the shared async state cleanup.
										case 3:
											if ( ! ( _nLocal6 < v.length ) ) {
												_eLocal11.n = 9;
												break;
											}
											return (
												( _rLocal3 = v[ _nLocal6 ] ),
												P( _nLocal6 ),
												x( 3 + _nLocal6 ),
												( _eLocal11.p = 4 ),
												( _eLocal11.n = 5 ),
												l()( {
													path: '/ohmylms/v1/migrations/'.concat(
														a.selectedPlatform
													),
													method: 'POST',
													data: Dne(
														{},
														a.selectedPlatform,
														{
															course_id: _rLocal3,
														}
													),
												} )
											 );
										case 5:
											_eLocal11.n = 7;
											break;
										case 6:
											_eLocal11.p = 6;
											_oLocal3 = _eLocal11.v;
											showRequestError(
												'Failed to migrate course '.concat(
													_rLocal3,
													':'
												),
												_oLocal3
											);
										// Falls through to the shared async state cleanup.
										case 7:
											return (
												( _eLocal11.n = 8 ),
												new Promise( function (
													_eLocal14
												) {
													return setTimeout(
														_eLocal14,
														500
													);
												} )
											 );
										case 8:
											_nLocal6++;
											_eLocal11.n = 3;
											break;
										case 9:
											return (
												( _eLocal11.n = 10 ),
												A()
											 );
										case 10:
											return (
												( _eLocal11.n = 11 ),
												new Promise( function (
													_eLocal15
												) {
													return setTimeout(
														_eLocal15,
														500
													);
												} )
											 );
										case 11:
											t( 'wizard-completion' );
										// Falls through to the shared async state cleanup.
										case 12:
											return _eLocal11.a( 2 );
									}
								}
							},
							_eLocal10,
							null,
							[ [ 4, 6 ] ]
						);
					} )
				);
				return function ( ..._args4 ) {
					return e.apply( this, _args4 );
				};
			} )(),
			F = ( function () {
				var e = Lne(
					Wne().m( function _eLocal16() {
						return Wne().w( function ( _eLocal17 ) {
							for (;;) {
								switch ( _eLocal17.n ) {
									case 0:
										return (
											t( 'wizard-completion' ),
											( _eLocal17.n = 1 ),
											A()
										 );
									case 1:
										return _eLocal17.a( 2 );
								}
							}
						}, _eLocal16 );
					} )
				);
				return function ( ..._args5 ) {
					return e.apply( this, _args5 );
				};
			} )(),
			N = u.length > 0 && v.length === u.length,
			D = 'M10.5303 0.53033L3.53033 7.53033L0.53033 4.53033';
		return 'selection' === w ? (
			<React.Fragment>
				<Gte
					level={ a === null || a === void 0 ? void 0 : a.level }
					currentStep={
						'experienced' ===
							( a === null || a === void 0 ? void 0 : a.level ) ||
						'intermediate' ===
							( a === null || a === void 0 ? void 0 : a.level )
							? 2
							: 0
					}
					isShowIndicator={ ! 0 }
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
										'Let’s Start Your Course Draft',
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
										maxWidth: '500px',
										margin: 'auto',
									} }
								>
									{ ( 0, I18n.__ )(
										"We've fetched your courses to help you build a draft. Nothing is live yet - you're in control.",
										'ohmylms'
									) }
								</Controls.TextWP>
							</div>
							<Controls.FlexWP
								direction={ 'column' }
								gap={ 6 }
								items={ 'center' }
								justify={ 'center' }
								style={ {
									width: '790px',
									margin: 'auto',
									alignItems: 'center',
									justifyContent: 'center',
								} }
							>
								<div
									style={ {
										background: 'white',
										borderRadius: '8px',
										border: '1px solid rgba(200, 210, 233, 0.5)',
										display: 'flex',
										alignItems: 'center',
										justifyContent: 'space-between',
										padding: '8px 9px',
										width: '100%',
										maxWidth: '614px',
									} }
								>
									<div
										style={ {
											display: 'flex',
											alignItems: 'center',
											gap: '10px',
											padding: '8px',
										} }
									>
										<img
											src={ O.icon }
											alt={ O.label }
											style={ {
												width: '29px',
												height: '29px',
											} }
										/>
										<Controls.TextWP
											size={ '18' }
											weight={ '600' }
											color={ '#000d25' }
										>
											{ O.label }
										</Controls.TextWP>
									</div>
									<div
										style={ {
											display: 'flex',
											flexDirection: 'column',
											alignItems: 'flex-end',
											padding: '8px',
										} }
									>
										<Controls.TextWP
											size={ '14' }
											color={ '#444d5e' }
										>
											{ ( 0, I18n.__ )(
												'Migrating from',
												'ohmylms'
											) }
										</Controls.TextWP>
										<Controls.TextWP
											size={ '16' }
											weight={ '500' }
											color={ '#444d5e' }
										>
											{ O.label }
										</Controls.TextWP>
									</div>
								</div>
								<div
									style={ {
										background: 'white',
										borderRadius: '8px',
										boxShadow:
											'0px 4px 10px 0px rgba(110, 66, 211, 0.14)',
										padding: '24px',
										width: '100%',
										maxWidth: '614px',
										boxSizing: 'border-box',
									} }
								>
									<div
										style={ {
											background: '#f4f5f7',
											borderRadius: '2px',
											display: 'flex',
											alignItems: 'center',
											gap: '42px',
											height: '40px',
											padding: '16px 24px 16px 12px',
											boxSizing: 'border-box',
										} }
									>
										<div
											className={
												'ohmylms-setup-wizard-checkbox'
											}
											onClick={ function () {
												if ( v.length === u.length ) {
													h( [] );
												} else {
													h(
														u.map( function ( e ) {
															return e.id;
														} )
													);
												}
											} }
											style={ {
												position: 'relative',
												width: '16px',
												height: '16px',
												background: N
													? '#6e42d3'
													: 'white',
												border: '1px solid #6e42d3',
												borderRadius: '4px',
												flexShrink: 0,
												cursor: 'pointer',
											} }
											role="checkbox"
											tabIndex={ 0 }
											onKeyDown={ ( event ) => {
												if (
													event.key === 'Enter' ||
													event.key === ' '
												) {
													event.preventDefault();
													event.currentTarget.click();
												}
											} }
											aria-checked={ N }
											aria-label={ I18n.__(
												'Select all courses',
												'ohmylms'
											) }
										>
											{ N && (
												<div
													style={ {
														position: 'absolute',
														left: '50%',
														top: 'calc(50%)',
														transform:
															'translate(-50%, -50%)',
														width: '10px',
														height: '7px',
													} }
												>
													<svg
														style={ {
															display: 'block',
															width: '100%',
															height: '100%',
														} }
														fill={ 'none' }
														preserveAspectRatio={
															'none'
														}
														viewBox={
															'0 0 11.0607 8.59099'
														}
													>
														<path
															d={ D }
															stroke={ '#FFF' }
															strokeWidth={
																'1.5'
															}
														/>
													</svg>
												</div>
											) }
										</div>
										<div
											style={ {
												display: 'flex',
												alignItems: 'center',
												gap: '8px',
											} }
										>
											<span
												style={ {
													background:
														'rgba(200, 210, 233, 0.5)',
													borderRadius: '20px',
													padding: '0 8px',
													fontSize: '12px',
													color: '#6e42d3',
													fontWeight: 600,
												} }
											>
												{ v.length }
											</span>
											<Controls.TextWP
												size={ '12' }
												weight={ '500' }
												color={ '#7a8b9a' }
											>
												{ ( 0, I18n.__ )(
													'Courses selected',
													'ohmylms'
												) }
											</Controls.TextWP>
										</div>
									</div>
									{ m ? (
										<Controls.FlexWP
											justify={ 'center' }
											align={ 'center' }
											style={ {
												padding: '40px',
											} }
										>
											<Controls.SpinWP />
										</Controls.FlexWP>
									) : (
										<div
											style={ {
												maxHeight: '400px',
												overflowY: 'auto',
											} }
										>
											{ u.map( function ( e ) {
												var _nLocal7 = v.includes(
													e.id
												);
												return (
													<div
														key={ e.id }
														style={ {
															borderBottom:
																'0.5px solid rgba(200, 210, 233, 0.5)',
															padding: '12px',
															display: 'flex',
															alignItems:
																'center',
															gap: '30px',
														} }
													>
														<div
															className={
																'ohmylms-setup-wizard-checkbox'
															}
															onClick={ function () {
																return (
																	( _tLocal8 =
																		e.id ),
																	void ( v.includes(
																		_tLocal8
																	)
																		? h(
																				v.filter(
																					function (
																						_eLocal18
																					) {
																						return (
																							_eLocal18 !==
																							_tLocal8
																						);
																					}
																				)
																			)
																		: h(
																				[].concat(
																					( function (
																						_eLocal19
																					) {
																						return (
																							( function (
																								_eLocal20
																							) {
																								if (
																									Array.isArray(
																										_eLocal20
																									)
																								) {
																									return Gne(
																										_eLocal20
																									);
																								}
																							} )(
																								_eLocal19
																							) ||
																							( function (
																								_eLocal21
																							) {
																								if (
																									( 'undefined' !==
																										typeof Symbol &&
																										( (
																											_nullableValue3
																										) =>
																											_nullableValue3 !==
																												null &&
																											_nullableValue3 !==
																												void 0 )(
																											_eLocal21[
																												Symbol
																													.iterator
																											]
																										) ) ||
																									( (
																										_nullableValue4
																									) =>
																										_nullableValue4 !==
																											null &&
																										_nullableValue4 !==
																											void 0 )(
																										_eLocal21[
																											'@@iterator'
																										]
																									)
																								) {
																									return Array.from(
																										_eLocal21
																									);
																								}
																							} )(
																								_eLocal19
																							) ||
																							Hne(
																								_eLocal19
																							) ||
																							( function () {
																								throw new TypeError(
																									'Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
																								);
																							} )()
																						);
																					} )(
																						v
																					),
																					[
																						_tLocal8,
																					]
																				)
																			) )
																 );
																var _tLocal8;
															} }
															style={ {
																position:
																	'relative',
																width: '16px',
																height: '16px',
																background:
																	_nLocal7
																		? '#6e42d3'
																		: 'white',
																border: '1px solid #6e42d3',
																borderRadius:
																	'4px',
																flexShrink: 0,
																cursor: 'pointer',
															} }
															role="checkbox"
															tabIndex={ 0 }
															onKeyDown={ (
																event
															) => {
																if (
																	event.key ===
																		'Enter' ||
																	event.key ===
																		' '
																) {
																	event.preventDefault();
																	event.currentTarget.click();
																}
															} }
															aria-checked={
																_nLocal7
															}
															aria-label={
																e.title ||
																e.label
															}
														>
															{ _nLocal7 && (
																<div
																	style={ {
																		position:
																			'absolute',
																		left: '50%',
																		top: 'calc(50%)',
																		transform:
																			'translate(-50%, -50%)',
																		width: '10px',
																		height: '7px',
																	} }
																>
																	<svg
																		style={ {
																			display:
																				'block',
																			width: '100%',
																			height: '100%',
																		} }
																		fill={
																			'none'
																		}
																		preserveAspectRatio={
																			'none'
																		}
																		viewBox={
																			'0 0 11.0607 8.59099'
																		}
																	>
																		<path
																			d={
																				D
																			}
																			stroke={
																				'white'
																			}
																			strokeWidth={
																				'1.5'
																			}
																		/>
																	</svg>
																</div>
															) }
														</div>
														<div
															style={ {
																display: 'flex',
																gap: '20px',
																alignItems:
																	'center',
															} }
														>
															<div
																style={ {
																	width: '80px',
																	height: '50px',
																	background:
																		'#e1e1e1',
																	borderRadius:
																		'4px',
																	overflow:
																		'hidden',
																} }
															>
																{ e.thumbnail && (
																	<img
																		src={
																			e.thumbnail
																		}
																		style={ {
																			width: '100%',
																			height: '100%',
																			objectFit:
																				'cover',
																		} }
																		alt=""
																	/>
																) }
															</div>
															<div>
																<Controls.TextWP
																	size={
																		'14'
																	}
																	weight={
																		'700'
																	}
																	color={
																		'#000d25'
																	}
																>
																	{ e.title ||
																		e.label }
																</Controls.TextWP>
															</div>
														</div>
													</div>
												);
											} ) }
											{ 0 === u.length && ! m && (
												<div
													style={ {
														padding: '20px',
														textAlign: 'center',
													} }
												>
													<Controls.TextWP>
														{ ( 0, I18n.__ )(
															'No courses found to migrate.',
															'ohmylms'
														) }
													</Controls.TextWP>
												</div>
											) }
										</div>
									) }
								</div>
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
								onClick={ n }
							>
								{ ( 0, I18n.__ )( 'Back', 'ohmylms' ) }
							</Controls.ButtonWP>
							<Controls.FlexWP
								items={ 'center' }
								justify={ 'end' }
								gap={ 3 }
							>
								{ 0 !== u.length || m ? (
									<Controls.ButtonWP
										variant={ 'primary' }
										onClick={ M }
										disabled={ 0 === v.length || m }
									>
										{ ( 0, I18n.__ )(
											'Continue',
											'ohmylms'
										) }
									</Controls.ButtonWP>
								) : (
									<Controls.ButtonWP
										variant={ 'primary' }
										onClick={ F }
									>
										{ ( 0, I18n.__ )( 'Skip', 'ohmylms' ) }
									</Controls.ButtonWP>
								) }
							</Controls.FlexWP>
						</Controls.FlexWP>
					</Controls.SpacerWP>
				</Controls.ContainerWP>
			</React.Fragment>
		) : (
			<React.Fragment>
				<Gte
					level={ a === null || a === void 0 ? void 0 : a.level }
					currentStep={
						'experienced' ===
							( a === null || a === void 0 ? void 0 : a.level ) ||
						'intermediate' ===
							( a === null || a === void 0 ? void 0 : a.level )
							? 2
							: 0
					}
					isShowIndicator={ ! 0 }
				/>
				<Controls.ContainerWP>
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
									'🤝 Your Migration Assistant',
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
									'Your data is safe. We migrate with care.',
									'ohmylms'
								) }
							</Controls.TextWP>
						</div>
						<Controls.FlexWP
							direction={ 'column' }
							gap={ 6 }
							items={ 'center' }
							justify={ 'center' }
							style={ {
								alignItems: 'center',
								justifyContent: 'center',
							} }
						>
							<Controls.CardWP
								isBorderless={ ! 0 }
								style={ {
									width: '768px',
									padding: '32px',
								} }
							>
								<div
									style={ {
										background: 'white',
										borderRadius: '8px',
										border: '1px solid rgba(200, 210, 233, 0.5)',
										display: 'flex',
										alignItems: 'center',
										justifyContent: 'space-between',
										padding: '8px 9px',
										width: '100%',
										marginBottom: '20px',
									} }
								>
									<div
										style={ {
											display: 'flex',
											alignItems: 'center',
											gap: '10px',
											padding: '8px',
										} }
									>
										<img
											src={ O.icon }
											alt={ O.label }
											style={ {
												width: '29px',
												height: '29px',
											} }
										/>
										<Controls.TextWP
											size={ '18' }
											weight={ '600' }
											color={ '#000d25' }
										>
											{ O.label }
										</Controls.TextWP>
									</div>
									<div
										style={ {
											display: 'flex',
											flexDirection: 'column',
											alignItems: 'flex-end',
											padding: '8px',
										} }
									>
										<Controls.TextWP
											size={ '12' }
											color={ '#687784' }
										>
											{ ( 0, I18n.__ )(
												'Progress',
												'ohmylms'
											) }
										</Controls.TextWP>
										<Controls.TextWP
											size={ '16' }
											weight={ '600' }
											color={
												R >= 2 + v.length
													? '#22C55E'
													: '#6e42d3'
											}
										>
											{ Math.min( R, 2 + v.length ) }
											{ '/' }
											{ 2 + v.length }
										</Controls.TextWP>
									</div>
								</div>
								<div
									style={ {
										height: '8px',
										background: '#F0F0F1',
										borderRadius: '4px',
										overflow: 'hidden',
									} }
								>
									<div
										style={ {
											height: '100%',
											width: ''.concat(
												( Math.min( R, 2 + v.length ) /
													( 2 + v.length ) ) *
													100,
												'%'
											),
											background: '#22C55E',
											transition: 'width 0.5s ease',
										} }
									/>
								</div>
							</Controls.CardWP>
							<Controls.CardWP
								isBorderless={ ! 0 }
								style={ {
									width: '704px',
									padding: '32px',
									background: '#F6F7F9',
								} }
							>
								<Controls.HeadingWP
									size={ '18' }
									weight={ '600' }
									style={ {
										marginBottom: '20px',
									} }
								>
									{ ( 0, I18n.__ )(
										'Migration Checklist',
										'ohmylms'
									) }
								</Controls.HeadingWP>
								<Controls.FlexWP
									direction={ 'column' }
									gap={ 4 }
								>
									{ React.createElement( qne, {
										title: ( 0, I18n.__ )(
											'Platform Connection',
											'ohmylms'
										),
										desc: ( 0, I18n.__ )(
											'Securely connecting to your account',
											'ohmylms'
										),
										status: ( () => {
											if ( R >= 1 ) {
												return 'complete';
											}
											if ( 0 === R ) {
												return 'processing';
											}
											return 'pending';
										} )(),
									} ) }
									{ React.createElement( qne, {
										title: ( 0, I18n.__ )(
											'Content Found',
											'ohmylms'
										),
										desc: ( 0, I18n.__ )(
											'Found '
												.concat( v.length, ' course' )
												.concat(
													1 !== v.length ? 's' : '',
													' to import'
												),
											'ohmylms'
										),
										status: ( () => {
											if ( R >= 2 ) {
												return 'complete';
											}
											if ( 1 === R ) {
												return 'processing';
											}
											return 'pending';
										} )(),
									} ) }
									{ v.map( function ( e, _tLocal9 ) {
										var _nLocal8 = u.find(
												function ( _tLocal0 ) {
													return _tLocal0.id === e;
												}
											),
											_rLocal4 = 3 + _tLocal9,
											_aLocal2 = 'pending';
										return (
											R > _rLocal4
												? ( _aLocal2 = 'complete' )
												: R === _rLocal4 &&
													( _aLocal2 = 'processing' ),
											React.createElement( qne, {
												key: e,
												title: ( 0, I18n.__ )(
													'Importing Course',
													'ohmylms'
												),
												desc:
													( _nLocal8 === null ||
													_nLocal8 === void 0
														? void 0
														: _nLocal8.title ) ||
													'Course '.concat( e ),
												status: _aLocal2,
											} )
										 );
									} ) }
								</Controls.FlexWP>
							</Controls.CardWP>
						</Controls.FlexWP>
					</div>
				</Controls.ContainerWP>
			</React.Fragment>
		);
	};
}
