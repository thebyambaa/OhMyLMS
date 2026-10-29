import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
import { decodeHtmlEntities } from '../../shared/utils';
export function createCertificateCourseSelector( readRuntime ) {
	return function CertificateCourseSelector( props ) {
		const {
			JL,
			React,
			T: StoreModule,
			ZL,
			b: I18n,
			g: ReactHooks,
			l,
			nV,
			tV,
			tn,
			y: WordPressData,
		} = readRuntime();
		var t = props.onClose,
			n = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			r = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).selectCertificate();
			}, [] ),
			a = nV( ( 0, ReactHooks.useState )( [] ), 2 ),
			o = a[ 0 ],
			i = a[ 1 ],
			c = nV(
				( 0, ReactHooks.useState )(
					( 0, I18n.__ )(
						'Please enter 3 or more characters...',
						'ohmylms'
					)
				),
				2
			),
			u = c[ 0 ],
			s = c[ 1 ],
			d = ( function () {
				var e = tV(
					ZL().m( function _eLocal() {
						var _aLocal;
						return ZL().w( function ( _eLocal2 ) {
							for (;;) {
								switch ( _eLocal2.n ) {
									case 0:
										return (
											( _aLocal = JL(
												JL( {}, r ),
												{},
												{
													courses: o.map(
														function ( _eLocal3 ) {
															return {
																id:
																	_eLocal3 ===
																		null ||
																	_eLocal3 ===
																		void 0
																		? void 0
																		: _eLocal3.value,
															};
														}
													),
													course_count: o.length,
												}
											) ),
											( _eLocal2.n = 1 ),
											n.updateCertificate(
												r === null || r === void 0
													? void 0
													: r.id,
												_aLocal
											)
										 );
									case 1:
										t();
									// Falls through to the shared async state cleanup.
									case 2:
										return _eLocal2.a( 2 );
								}
							}
						}, _eLocal );
					} )
				);
				return function ( ..._args ) {
					return e.apply( this, _args );
				};
			} )(),
			m = ( function () {
				var e = tV(
					ZL().m( function _eLocal4( _tLocal ) {
						var _nLocal, _rLocal, _aLocal2;
						return ZL().w(
							function ( _eLocal5 ) {
								for (;;) {
									switch ( ( _eLocal5.p = _eLocal5.n ) ) {
										case 0:
											return (
												( _eLocal5.p = 0 ),
												( _eLocal5.n = 1 ),
												l()( {
													path: '/ohmylms/v1/courses?search='.concat(
														_tLocal
													),
													method: 'GET',
													headers: {
														'Content-Type':
															'application/json',
													},
												} )
											 );
										case 1:
											return (
												( _nLocal = _eLocal5.v ),
												( _rLocal = _nLocal.map(
													function ( _eLocal6 ) {
														return {
															value:
																_eLocal6 ===
																	null ||
																_eLocal6 ===
																	void 0
																	? void 0
																	: _eLocal6.id,
															label:
																_eLocal6 ===
																	null ||
																_eLocal6 ===
																	void 0
																	? void 0
																	: _eLocal6.name,
														};
													}
												) ),
												_eLocal5.a( 2, _rLocal )
											 );
										case 2:
											_eLocal5.p = 2;
											_aLocal2 = _eLocal5.v;
											showRequestError( _aLocal2 );
										// Falls through to the shared async state cleanup.
										case 3:
											return _eLocal5.a( 2 );
									}
								}
							},
							_eLocal4,
							null,
							[ [ 0, 2 ] ]
						);
					} )
				);
				return function ( ..._args2 ) {
					return e.apply( this, _args2 );
				};
			} )(),
			p = ( function () {
				var e = tV(
					ZL().m( function _eLocal7( _tLocal3 ) {
						var _nLocal2, _rLocal2;
						return ZL().w( function ( _eLocal8 ) {
							for (;;) {
								switch ( _eLocal8.n ) {
									case 0:
										if ( ! ( _tLocal3.length >= 3 ) ) {
											_eLocal8.n = 4;
											break;
										}
										return (
											( _eLocal8.n = 1 ),
											m( _tLocal3 )
										 );
									case 1:
										if (
											0 !==
											( _nLocal2 = _eLocal8.v ).length
										) {
											_eLocal8.n = 2;
											break;
										}
										return (
											s(
												( 0, I18n.__ )(
													'No Course Found! Try to search another one',
													'ohmylms'
												)
											),
											_eLocal8.a( 2, [] )
										 );
									case 2:
										return (
											s(
												( 0, I18n.__ )(
													'Please enter 3 or more characters...',
													'ohmylms'
												)
											),
											( _rLocal2 =
												_nLocal2 === null ||
												_nLocal2 === void 0
													? void 0
													: _nLocal2.map(
															function (
																_eLocal9
															) {
																return {
																	label: decodeHtmlEntities(
																		_eLocal9 ===
																			null ||
																			_eLocal9 ===
																				void 0
																			? void 0
																			: _eLocal9.label
																	),
																	value:
																		_eLocal9 ===
																			null ||
																		_eLocal9 ===
																			void 0
																			? void 0
																			: _eLocal9.value,
																};
															}
														) ),
											_eLocal8.a( 2, _rLocal2 )
										 );
									case 3:
										_eLocal8.n = 5;
										break;
									case 4:
										return _eLocal8.a( 2, [] );
									case 5:
										return _eLocal8.a( 2 );
								}
							}
						}, _eLocal7 );
					} )
				);
				return function ( ..._args3 ) {
					return e.apply( this, _args3 );
				};
			} )();
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					var e;
					if (
						r !== null &&
						r !== void 0 &&
						null !== ( e = r.courses ) &&
						void 0 !== e &&
						e.length
					) {
						var _tLocal5 = r.courses
							.filter( function ( _eLocal0 ) {
								return _eLocal0 && _eLocal0.id && _eLocal0.name;
							} )
							.map( function ( _eLocal1 ) {
								return {
									label: decodeHtmlEntities(
										_eLocal1.name
									).trim(),
									value: _eLocal1.id,
								};
							} )
							.filter( function ( _eLocal10 ) {
								return _eLocal10.label && _eLocal10.value;
							} );
						i( _tLocal5 );
					}
				},
				[ r ]
			),
			(
				<React.Fragment>
					<Controls.ModalWP
						title={ ( 0, I18n.__ )( 'Select Course', 'ohmylms' ) }
						shouldCloseOnEsc={ ! 0 }
						shouldCloseOnClickOutside={ ! 0 }
						onRequestClose={ t }
					>
						{ React.createElement( tn, {
							description: ( 0, I18n.__ )(
								'Select courses for which the certificate will be applicable.',
								'ohmylms'
							),
							spacerMarginBottom: 0,
							value: o,
							onChange( e ) {
								return (
									( _tLocal6 = e.filter( Boolean ) ),
									n.updateContent( {
										courses: _tLocal6.map(
											function ( _eLocal11 ) {
												return {
													id:
														_eLocal11 === null ||
														_eLocal11 === void 0
															? void 0
															: _eLocal11.value,
													name:
														_eLocal11 === null ||
														_eLocal11 === void 0
															? void 0
															: _eLocal11.label,
												};
											}
										),
										course_count: _tLocal6.length,
									} ),
									void i( _tLocal6 )
								 );
								var _tLocal6;
							},
							loadOptions: p,
							noOptionsMessage() {
								return u;
							},
							padding: 0,
							direction: 'column',
							gap: 1,
							flexItemWidth: '100%',
						} ) }
						<Controls.FlexWP
							justify={ 'flex-end' }
							gap={ 4 }
							style={ {
								marginTop: '16px',
							} }
						>
							<Controls.ButtonWP
								key={ 'cancel' }
								onClick={ t }
								variant={ 'secondary' }
							>
								{ ( 0, I18n.__ )( 'Cancel', 'ohmylms' ) }
							</Controls.ButtonWP>
							<Controls.ButtonWP
								key={ 'Save' }
								variant={ 'primary' }
								onClick={ d }
								className={
									'ohmylms-course-settings-modal-save-btn'
								}
							>
								{ ( 0, I18n.__ )( 'Save', 'ohmylms' ) }
							</Controls.ButtonWP>
						</Controls.FlexWP>
					</Controls.ModalWP>
				</React.Fragment>
			 )
		 );
	};
}
