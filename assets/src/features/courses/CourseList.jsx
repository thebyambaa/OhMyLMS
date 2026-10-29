import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement, useId } from '@wordpress/element';
import { ViewAsModal, canViewAs } from '../schools/ViewAsModal';
import { CourseGradebook } from './CourseGradebook';
import { useNotifications } from '../../shared/notices';
export function createCourseList( readRuntime ) {
	return function CourseList() {
		const _controlId = useId();
		const {
			Br,
			Ea,
			Gq,
			He,
			I: Controls,
			Ie,
			Ne,
			Ot,
			React,
			T: StoreModule,
			VG,
			We,
			YG,
			YH,
			aY,
			b: I18n,
			cY,
			dY,
			df,
			f: Router,
			fN,
			g: ReactHooks,
			hN,
			iN,
			iY,
			l,
			lU,
			lY,
			mY,
			oY,
			q,
			sN,
			sn,
			uf,
			v,
			xq,
			y: WordPressData,
			yG,
			yc,
		} = readRuntime();
		const [ viewAs, setViewAs ] = ReactHooks.useState( null );
		const [ gradebook, setGradebook ] = ReactHooks.useState( null );
		var e = true,
			t = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			n = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
				return _eLocal( StoreModule.default ).selectCourses();
			}, [] ),
			r = ( 0, WordPressData.useSelect )( function ( _eLocal2 ) {
				return _eLocal2(
					StoreModule.default
				).selectCoursesPagination();
			}, [] ),
			a = ( r.totalPages, r.totalCourses ),
			o = ( 0, WordPressData.useSelect )( function ( _eLocal3 ) {
				return _eLocal3( StoreModule.default ).getNotificationMessage();
			}, [] ),
			i = ( 0, WordPressData.useSelect )( function ( _eLocal4 ) {
				return _eLocal4( StoreModule.default ).getNotificationStatus();
			}, [] ),
			c = ( 0, WordPressData.useSelect )( function ( _eLocal5 ) {
				return _eLocal5( StoreModule.default ).getAllCategories();
			}, [] ),
			u = mY( ( 0, ReactHooks.useState )( '' ), 2 ),
			s = u[ 0 ],
			d = u[ 1 ],
			m = mY( ( 0, ReactHooks.useState )( 'all' ), 2 ),
			p = m[ 0 ],
			h = m[ 1 ],
			_ = mY( ( 0, ReactHooks.useState )( '' ), 2 ),
			w = _[ 0 ],
			E = _[ 1 ],
			S = mY( ( 0, ReactHooks.useState )( 'all' ), 2 ),
			R = S[ 0 ],
			x = S[ 1 ],
			C = mY( ( 0, ReactHooks.useState )( '' ), 2 ),
			P = C[ 0 ],
			O = C[ 1 ],
			k = mY( ( 0, ReactHooks.useState )( 1 ), 2 ),
			j = k[ 0 ],
			A = k[ 1 ],
			M = mY( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			F = M[ 0 ],
			N = M[ 1 ],
			D = mY( ( 0, ReactHooks.useState )( [] ), 2 ),
			W = D[ 0 ],
			B = D[ 1 ],
			V = mY( ( 0, ReactHooks.useState )( null ), 2 ),
			H = V[ 0 ],
			G = V[ 1 ],
			U = mY( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			Y = U[ 0 ],
			Q = U[ 1 ],
			Z = mY( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			$ = Z[ 0 ],
			K = Z[ 1 ],
			J = mY( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			X = J[ 0 ],
			ee = ( J[ 1 ], mY( ( 0, ReactHooks.useState )( ! 1 ), 2 ) ),
			te = ee[ 0 ],
			ne = ee[ 1 ],
			re = mY( ( 0, ReactHooks.useState )( null ), 2 ),
			ae = re[ 0 ],
			oe = re[ 1 ],
			ie = mY( ( 0, ReactHooks.useState )( 5 ), 2 ),
			le =
				( ie[ 0 ],
				ie[ 1 ],
				mY( ( 0, ReactHooks.useState )( ! 1 ), 2 ) ),
			ce = le[ 0 ],
			ue = le[ 1 ],
			se = mY( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			de = se[ 0 ],
			me = se[ 1 ],
			pe = mY( ( 0, ReactHooks.useState )( '1.2' ), 2 ),
			fe = pe[ 0 ],
			ve = pe[ 1 ],
			ge = mY( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			he = ge[ 0 ],
			ye = ge[ 1 ],
			be = mY( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			_e = be[ 0 ],
			we = be[ 1 ],
			Ee = mY( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			Se = Ee[ 0 ],
			Re = Ee[ 1 ],
			xe = mY( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			Ce = xe[ 0 ],
			Pe = xe[ 1 ],
			Oe = mY( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			ke =
				( Oe[ 0 ],
				Oe[ 1 ],
				mY( ( 0, ReactHooks.useState )( ! 1 ), 2 ) ),
			je = ke[ 0 ],
			Ae = ke[ 1 ],
			Me = ( function () {
				var _eLocal6Value = ( function ( _eLocal7Value, _tValue ) {
						return (
							( function ( _eLocal8 ) {
								if ( Array.isArray( _eLocal8 ) ) {
									return _eLocal8;
								}
							} )( _eLocal7Value ) ||
							( function ( _eLocal9, _tLocal ) {
								var _nValue =
									_eLocal9 === null || _eLocal9 === void 0
										? null
										: ( 'undefined' !== typeof Symbol &&
												_eLocal9[ Symbol.iterator ] ) ||
											_eLocal9[ '@@iterator' ];
								if ( _nValue !== null && _nValue !== void 0 ) {
									var _rValue,
										_aLocal,
										_oLocal,
										_iLocal,
										_lLocal = [],
										_cLocal = ! 0,
										_uLocal = ! 1;
									try {
										if (
											( ( _oLocal = ( _nValue =
												_nValue.call( _eLocal9 ) )
												.next ),
											0 === _tLocal )
										) {
											if (
												Object( _nValue ) !== _nValue
											) {
												return;
											}
											_cLocal = ! 1;
										} else {
											for (
												;
												! ( _cLocal = ( _rValue =
													_oLocal.call( _nValue ) )
													.done ) &&
												( _lLocal.push( _rValue.value ),
												_lLocal.length !== _tLocal );
												_cLocal = ! 0
											) {}
										}
									} catch ( _eLocal0 ) {
										_uLocal = ! 0;
										_aLocal = _eLocal0;
									} finally {
										try {
											if (
												! _cLocal &&
												( ( _nullableValue ) =>
													_nullableValue !== null &&
													_nullableValue !== void 0 )(
													_nValue.return
												) &&
												( ( _iLocal =
													_nValue.return() ),
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
							} )( _eLocal7Value, _tValue ) ||
							( function ( _eLocal1, _tLocal2 ) {
								if ( _eLocal1 ) {
									if ( 'string' === typeof _eLocal1 ) {
										return iY( _eLocal1, _tLocal2 );
									}
									var _nValue2 = {}.toString
										.call( _eLocal1 )
										.slice( 8, -1 );
									return (
										'Object' === _nValue2 &&
											_eLocal1.constructor &&
											( _nValue2 =
												_eLocal1.constructor.name ),
										( () => {
											if (
												'Map' === _nValue2 ||
												'Set' === _nValue2
											) {
												return Array.from( _eLocal1 );
											}
											if (
												'Arguments' === _nValue2 ||
												/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(
													_nValue2
												)
											) {
												return iY( _eLocal1, _tLocal2 );
											}
											return void 0;
										} )()
									 );
								}
							} )( _eLocal7Value, _tValue ) ||
							( function () {
								throw new TypeError(
									'Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
								);
							} )()
						);
					} )( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
					_tLocal3 = _eLocal6Value[ 0 ],
					_nLocal = _eLocal6Value[ 1 ],
					_rLocal = ( 0, ReactHooks.useCallback )( function (
						..._args3
					) {
						let [ _eLocal10 ] = _args3;
						var _tLocal4 =
							_args3.length > 1 && void 0 !== _args3[ 1 ]
								? _args3[ 1 ]
								: 'download_'.concat(
										new Date().toISOString(),
										'.json'
									);
						try {
							_nLocal( ! 0 );
							var _rLocal2 =
									'object' === oY( _eLocal10 )
										? JSON.stringify( _eLocal10, null, 2 )
										: _eLocal10,
								_aLocal2 = new Blob( [ _rLocal2 ], {
									type: 'application/json',
								} ),
								_oLocal2 =
									window.URL.createObjectURL( _aLocal2 ),
								_iLocal2 = document.createElement( 'a' );
							return (
								( _iLocal2.href = _oLocal2 ),
								( _iLocal2.download = _tLocal4 ),
								document.body.appendChild( _iLocal2 ),
								_iLocal2.click(),
								document.body.removeChild( _iLocal2 ),
								window.URL.revokeObjectURL( _oLocal2 ),
								_nLocal( ! 1 ),
								! 0
							 );
						} catch ( _eLocal11 ) {
							return (
								showRequestError(
									'Download failed:',
									_eLocal11
								),
								_nLocal( ! 1 ),
								! 1
							 );
						}
					}, [] );
				return {
					isDownloading: _tLocal3,
					downloadFile: _rLocal,
				};
			} )(),
			Te = ( Me.isDownloading, Me.downloadFile ),
			Fe = useNotifications(),
			openNotificationWithIcon = Fe.openNotificationWithIcon,
			contextHolder = Fe.contextHolder,
			Be = ( 0, Router.Zp )(),
			Le = ( 0, Router.zy )();
		( 0, ReactHooks.useEffect )(
			function () {
				if (
					'true' ===
					new URLSearchParams( Le.search ).get( 'openAddCourseModal' )
				) {
					{
						Ae( ! 0 );
						Be( '/courses', {
							replace: ! 0,
						} );
					}
				}
			},
			[ Le.search, Be ]
		);
		var Ve = ( 0, ReactHooks.useCallback )(
				dY(
					cY().m( function _eLocal12( ..._args ) {
						var _nLocal2,
							_rLocal3,
							_aLocal3,
							_oLocal3 = _args;
						return cY().w( function ( _eLocal13 ) {
							for (;;) {
								switch ( _eLocal13.n ) {
									case 0:
										_nLocal2 =
											_oLocal3.length > 0 &&
											void 0 !== _oLocal3[ 0 ]
												? _oLocal3[ 0 ]
												: 'date';
										_rLocal3 =
											_oLocal3.length > 1 &&
											void 0 !== _oLocal3[ 1 ]
												? _oLocal3[ 1 ]
												: 'DESC';
										N( ! 0 );
										_aLocal3 = {
											offset: 5 * ( j - 1 ),
											order: _rLocal3,
											page: j,
											per_page: 5,
											search: P,
											post_status: R,
											orderby: _nLocal2,
											date_filter: s,
											price_type: p,
											category_id: w,
										};
										if ( xq( s ) ) {
											{
												_aLocal3.date_filter = 'custom';
												_aLocal3.start_date = sn()(
													s[ 0 ]
												).format( 'YYYY-MM-DD' );
												_aLocal3.end_date = sn()(
													s[ 1 ]
												).format( 'YYYY-MM-DD' );
											}
										}
										t.fetchCourses( _aLocal3 ).finally(
											function () {
												N( ! 1 );
											}
										);
									// Falls through to the shared async state cleanup.
									// Falls through to the shared async state cleanup.
									case 1:
										return _eLocal13.a( 2 );
								}
							}
						}, _eLocal12 );
					} )
				),
				[ j, P, s, p, w, R ]
			),
			Ge = ( 0, ReactHooks.useCallback )(
				dY(
					cY().m( function _eLocal14() {
						return cY().w( function ( _eLocal15 ) {
							for (;;) {
								switch ( _eLocal15.n ) {
									case 0:
										Ae( ! 0 );
									// Falls through to the shared async state cleanup.
									// Falls through to the shared async state cleanup.
									case 1:
										return _eLocal15.a( 2 );
								}
							}
						}, _eLocal14 );
					} )
				),
				[]
			),
			Ue = ( 0, ReactHooks.useCallback )( function ( _eLocal16 ) {
				O( _eLocal16 );
				A( 1 );
			}, [] ),
			qe = ( 0, ReactHooks.useCallback )( function ( _eLocal17 ) {
				d( _eLocal17 );
				A( 1 );
			}, [] ),
			Ye = ( 0, ReactHooks.useCallback )( function ( _eLocal18 ) {
				h( _eLocal18 );
				A( 1 );
			}, [] ),
			Qe = ( 0, ReactHooks.useCallback )( function ( _eLocal19 ) {
				E( _eLocal19 );
				A( 1 );
			}, [] ),
			Ze = ( 0, ReactHooks.useCallback )( function ( _eLocal20 ) {
				x( _eLocal20 );
				A( 1 );
			}, [] ),
			$e = ( 0, ReactHooks.useCallback )( function ( _eLocal21 ) {
				A( _eLocal21 );
				B( [] );
			}, [] ),
			Ke = ( 0, ReactHooks.useCallback )(
				function ( _eLocal22, _tLocal5, _nLocal3 ) {
					var _rLocal4 =
							{
								date_created: 'date',
								total_enrollment: 'enrollment',
								status: 'post_status',
								price: 'price',
								courseDetails: 'title',
							}[ _nLocal3.field ] || _nLocal3.field,
						_aLocal4 =
							{
								ascend: 'ASC',
								descend: 'DESC',
							}[ _nLocal3.order ] || _nLocal3.order;
					A( 1 );
					Ve( _rLocal4, _aLocal4 );
				},
				[ Ve, A ]
			),
			Je = ( 0, ReactHooks.useCallback )( function ( _eLocal23 ) {
				window.open( _eLocal23, '_blank' );
			}, [] ),
			Xe = ( 0, ReactHooks.useCallback )( function ( _eLocal24 ) {
				ne( ! 0 );
				oe( _eLocal24 );
			}, [] ),
			et = ( 0, ReactHooks.useCallback )( function () {
				ne( ! 1 );
			}, [] ),
			tt = ( 0, ReactHooks.useCallback )(
				dY(
					cY().m( function _eLocal25() {
						return cY().w( function ( _eLocal26 ) {
							for (;;) {
								switch ( _eLocal26.n ) {
									case 0:
										return (
											( _eLocal26.n = 1 ),
											t.handleBulkDelete( 'courses', {
												course_ids: ae ? [ ae ] : W,
											} )
										 );
									case 1:
										Ve();
										B( [] );
										A( 1 );
										oe( null );
										ne( ! 1 );
									// Falls through to the shared async state cleanup.
									case 2:
										return _eLocal26.a( 2 );
								}
							}
						}, _eLocal25 );
					} )
				),
				[ W, ae, Ve ]
			),
			nt = ( 0, ReactHooks.useCallback )(
				( function () {
					var _nLocal4 = dY(
						cY().m( function _nLocal5( _rLocal5 ) {
							var _aLocal5, _oLocal4;
							return cY().w( function ( _nLocal6 ) {
								for (;;) {
									switch ( _nLocal6.n ) {
										case 0: {
											_nLocal6.n = 1;
											break;
										}
										case 1:
											if ( ! _rLocal5 ) {
												_nLocal6.n = 3;
												break;
											}
											return (
												( _nLocal6.n = 2 ),
												t.exportCourses( [ _rLocal5 ] )
											 );
										case 2:
											_aLocal5 = _nLocal6.v;
											_nLocal6.n = 5;
											break;
										case 3:
											return (
												( _nLocal6.n = 4 ),
												t.exportCourses( W )
											 );
										case 4:
											_aLocal5 = _nLocal6.v;
										// Falls through to the shared async state cleanup.
										case 5:
											_oLocal4 = null;
											if ( _aLocal5 ) {
												_oLocal4 = Te(
													_aLocal5,
													'courses_export_'.concat(
														new Date().toISOString(),
														'.json'
													)
												);
											}
											if ( _oLocal4 ) {
												t.showNotification(
													( 0, I18n.__ )(
														'Exported successfully.',
														'ohmylms'
													),
													'success'
												);
											}
											ue( ! 1 );
											B( [] );
										// Falls through to the shared async state cleanup.
										case 6:
											return _nLocal6.a( 2 );
									}
								}
							}, _nLocal5 );
						} )
					);
					return function ( ..._args4 ) {
						return _nLocal4.apply( this, _args4 );
					};
				} )(),
				[ e, Te, W ]
			),
			rt = ( 0, ReactHooks.useCallback )( function () {
				ue( ! 1 );
			}, [] ),
			at = ( 0, ReactHooks.useCallback )(
				dY(
					cY().m( function _nLocal7() {
						var _rLocal6, _aLocal6;
						return cY().w(
							function ( _nLocal8 ) {
								for (;;) {
									switch ( ( _nLocal8.p = _nLocal8.n ) ) {
										case 0: {
											_nLocal8.n = 1;
											break;
										}
										case 1:
											if (
												( ye( ! 0 ),
												( _nLocal8.p = 2 ),
												! ae )
											) {
												_nLocal8.n = 4;
												break;
											}
											return (
												( _nLocal8.n = 3 ),
												t.exportCoursesAsScorm(
													[ ae ],
													fe
												)
											 );
										case 3:
											_aLocal6 = _nLocal8.v;
											_nLocal8.n = 6;
											break;
										case 4:
											return (
												( _nLocal8.n = 5 ),
												t.exportCoursesAsScorm( W, fe )
											 );
										case 5:
											_aLocal6 = _nLocal8.v;
										// Falls through to the shared async state cleanup.
										case 6:
											if (
												null !==
													( _rLocal6 = _aLocal6 ) &&
												void 0 !== _rLocal6 &&
												_rLocal6.success
											) {
												{
													t.showNotification(
														( 0, I18n.__ )(
															'SCORM package is being downloaded...',
															'ohmylms'
														),
														'success'
													);
													me( ! 1 );
													B( [] );
													oe( null );
												}
											} else {
												t.showNotification(
													( 0, I18n.__ )(
														'SCORM export failed. Please try again.',
														'ohmylms'
													),
													'error'
												);
											}
											_nLocal8.n = 8;
											break;
										case 7:
											_nLocal8.p = 7;
											t.showNotification(
												( 0, I18n.__ )(
													'SCORM export failed. Please try again.',
													'ohmylms'
												),
												'error'
											);
										// Falls through to the shared async state cleanup.
										case 8:
											return (
												( _nLocal8.p = 8 ),
												ye( ! 1 ),
												_nLocal8.f( 8 )
											 );
										case 9:
											return _nLocal8.a( 2 );
									}
								}
							},
							_nLocal7,
							null,
							[ [ 2, 7, 8, 9 ] ]
						);
					} )
				),
				[ e, W, fe, ae ]
			),
			ot = ( 0, ReactHooks.useCallback )( function () {
				me( ! 1 );
				ve( '1.2' );
				oe( null );
			}, [] ),
			it = ( 0, ReactHooks.useCallback )(
				function ( ..._args2 ) {
					var _tLocal6 =
						_args2.length > 0 && void 0 !== _args2[ 0 ]
							? _args2[ 0 ]
							: null;
					if ( _tLocal6 ) {
						oe( _tLocal6 );
					}
					me( ! 0 );
				},
				[ e ]
			),
			lt = ( 0, ReactHooks.useCallback )(
				( function () {
					var _eLocal28 = dY(
						cY().m( function _eLocal29( _tLocal7 ) {
							var _nLocal9, _rLocal7;
							return cY().w( function ( _eLocal30 ) {
								for (;;) {
									switch ( _eLocal30.n ) {
										case 0:
											return (
												( _nLocal9 = {
													status: 'publish',
												} ),
												( _eLocal30.n = 1 ),
												l()( {
													path: '/ohmylms/v1/courses/'.concat(
														_tLocal7,
														'/status'
													),
													method: 'POST',
													headers: {
														'Content-Type':
															'application/json',
													},
													body: JSON.stringify(
														_nLocal9
													),
												} )
											 );
										case 1:
											if (
												'success' ===
												( ( ( _nullableValue2 ) =>
													_nullableValue2 === null ||
													_nullableValue2 ===
														void 0 )(
													( _rLocal7 = _eLocal30.v )
												)
													? void 0
													: _rLocal7.status )
											) {
												{
													openNotificationWithIcon(
														'success',
														( 0, I18n.__ )(
															'Course published successfully.',
															'ohmylms'
														)
													);
													Ve();
												}
											}
										// Falls through to the shared async state cleanup.
										case 2:
											return _eLocal30.a( 2 );
									}
								}
							}, _eLocal29 );
						} )
					);
					return function ( ..._args5 ) {
						return _eLocal28.apply( this, _args5 );
					};
				} )(),
				[]
			),
			ct = ( 0, ReactHooks.useCallback )(
				dY(
					cY().m( function _tLocal9() {
						var _nLocal0, _rLocal8;
						return cY().w(
							function ( _tLocal0 ) {
								for (;;) {
									switch ( ( _tLocal0.p = _tLocal0.n ) ) {
										case 0:
											if ( ! Se ) {
												_tLocal0.n = 1;
												break;
											}
											return _tLocal0.a( 2 );
										case 1:
											return (
												( _tLocal0.p = 1 ),
												Re( ! 0 ),
												( _tLocal0.n = 2 ),
												l()( {
													path: '/ohmylms/v1/courses/'.concat(
														ae,
														'/clone'
													),
													method: 'POST',
													headers: {
														'Content-Type':
															'application/json',
													},
												} )
											 );
										case 2:
											if (
												'success' ===
												( ( ( _nullableValue3 ) =>
													_nullableValue3 === null ||
													_nullableValue3 ===
														void 0 )(
													( _nLocal0 = _tLocal0.v )
												)
													? void 0
													: _nLocal0.status )
											) {
												openNotificationWithIcon(
													'success',
													( 0, I18n.__ )(
														'Course duplicated successfully.',
														'ohmylms'
													)
												);
											}
											_tLocal0.n = 4;
											break;
										case 3:
											_tLocal0.p = 3;
											_rLocal8 = _tLocal0.v;
											showRequestError( _rLocal8 );
											openNotificationWithIcon(
												'error',
												( 0, I18n.__ )(
													'Something went wrong!',
													'ohmylms'
												)
											);
										// Falls through to the shared async state cleanup.
										case 4:
											return (
												( _tLocal0.p = 4 ),
												Ve(),
												oe( null ),
												we( ! 1 ),
												Re( ! 1 ),
												_tLocal0.f( 4 )
											 );
										case 5:
											return _tLocal0.a( 2 );
									}
								}
							},
							_tLocal9,
							null,
							[ [ 1, 3, 4, 5 ] ]
						);
					} )
				),
				[ ae, Se, e ]
			),
			ut = ( 0, ReactHooks.useCallback )( function () {
				oe( null );
				we( ! 1 );
			}, [] ),
			st = ( 0, ReactHooks.useCallback )( function ( _tLocal1 ) {
				we( ! 0 );
				oe( _tLocal1 );
			}, [] ),
			dt = ( 0, ReactHooks.useCallback )( function () {
				Q( ! 0 );
			}, [] ),
			mt = ( 0, ReactHooks.useCallback )( function () {
				Q( ! 1 );
				K( ! 1 );
			}, [] ),
			pt = ( 0, ReactHooks.useCallback )(
				( function () {
					var _eLocal31 = dY(
						cY().m( function _eLocal32( ..._args6 ) {
							let [ _nLocal1 ] = _args6;
							var _rLocal9,
								_aLocal7,
								_oLocal5,
								_iLocal3,
								_cLocal2,
								_uLocal2 = _args6;
							return cY().w(
								function ( _eLocal33 ) {
									for (;;) {
										switch (
											( _eLocal33.p = _eLocal33.n )
										) {
											case 0:
												if (
													( ( _rLocal9 =
														_uLocal2.length > 1 &&
														void 0 !== _uLocal2[ 1 ]
															? _uLocal2[ 1 ]
															: 'json' ),
													_nLocal1 )
												) {
													_eLocal33.n = 1;
													break;
												}
												return _eLocal33.a( 2 );
											case 1:
												if (
													( ( _eLocal33.p = 1 ),
													K( ! 0 ),
													'scorm' !== _rLocal9 )
												) {
													_eLocal33.n = 3;
													break;
												}
												return (
													( _eLocal33.n = 2 ),
													t.importScormCourse(
														_nLocal1
													)
												 );
											case 2:
												_oLocal5 = _eLocal33.v;
												_eLocal33.n = 5;
												break;
											case 3:
												return (
													( _eLocal33.n = 4 ),
													t.importCourse( _nLocal1 )
												 );
											case 4:
												_oLocal5 = _eLocal33.v;
											// Falls through to the shared async state cleanup.
											case 5:
												if (
													null !==
														( _aLocal7 =
															_oLocal5 ) &&
													void 0 !== _aLocal7 &&
													_aLocal7.success
												) {
													{
														Q( ! 1 );
														Ve();
														if (
															'scorm' ===
																_rLocal9 &&
															null !==
																( _iLocal3 =
																	_oLocal5 ) &&
															void 0 !==
																_iLocal3 &&
															_iLocal3.data
														) {
															{
																void _oLocal5.data;
															}
														}
													}
												}
												_eLocal33.n = 7;
												break;
											case 6:
												_eLocal33.p = 6;
												_cLocal2 = _eLocal33.v;
												showRequestError( _cLocal2 );
												openNotificationWithIcon(
													'error',
													_cLocal2.message ||
														( 0, I18n.__ )(
															'Import failed',
															'ohmylms'
														)
												);
											// Falls through to the shared async state cleanup.
											case 7:
												return (
													( _eLocal33.p = 7 ),
													K( ! 1 ),
													_eLocal33.f( 7 )
												 );
											case 8:
												return _eLocal33.a( 2 );
										}
									}
								},
								_eLocal32,
								null,
								[ [ 1, 6, 7, 8 ] ]
							);
						} )
					);
					return function ( ..._args7 ) {
						return _eLocal31.apply( this, _args7 );
					};
				} )(),
				[ t, Ve, openNotificationWithIcon ]
			),
			ft = ( 0, ReactHooks.useMemo )( function () {
				return [
					{
						value: 'last_30_days',
						label: ( 0, I18n.__ )( 'Last 30 days', 'ohmylms' ),
					},
					{
						value: 'current_month',
						label: ( 0, I18n.__ )( 'Current month', 'ohmylms' ),
					},
					{
						value: 'previous_month',
						label: ( 0, I18n.__ )( 'Previous month', 'ohmylms' ),
					},
					{
						value: 'current_year',
						label: ( 0, I18n.__ )( 'Current year', 'ohmylms' ),
					},
					{
						value: 'last_12_months',
						label: ( 0, I18n.__ )( 'Last 12 months', 'ohmylms' ),
					},
				];
			}, [] ),
			vt = ( 0, ReactHooks.useMemo )( function () {
				return [
					{
						value: 'all',
						label: ( 0, I18n.__ )( 'All Price Type', 'ohmylms' ),
					},
					{
						value: 'free',
						label: ( 0, I18n.__ )( 'Free', 'ohmylms' ),
					},
					{
						value: 'paid',
						label: ( 0, I18n.__ )( 'Paid', 'ohmylms' ),
					},
				];
			}, [] ),
			gt = ( 0, ReactHooks.useMemo )(
				function () {
					return {
						label: ( 0, I18n.__ )( 'Add Course', 'ohmylms' ),
						onClick: Ge,
						loading: X,
					};
				},
				[ X, Ge ]
			),
			ht = ( 0, ReactHooks.useMemo )( function () {
				return [
					{
						value: 'all',
						label: ( 0, I18n.__ )( 'All Status', 'ohmylms' ),
					},
					{
						value: 'publish',
						label: ( 0, I18n.__ )( 'Published', 'ohmylms' ),
					},
					{
						value: 'future',
						label: ( 0, I18n.__ )( 'Scheduled', 'ohmylms' ),
					},
					{
						value: 'draft',
						label: ( 0, I18n.__ )( 'Draft', 'ohmylms' ),
					},
				];
			}, [] ),
			yt = ( 0, ReactHooks.useMemo )(
				function () {
					return {
						selectedRowKeys: W,
						onChange: B,
					};
				},
				[ W ]
			),
			bt = [
				{
					title: ( 0, I18n.__ )( 'Course Details', 'ohmylms' ),
					dataIndex: 'courseDetails',
					key: 'courseDetails',
					sorter: ! 0,
					width: '420px',
					render( _eLocal34, _tLocal11 ) {
						var _nLocal10 = H === _tLocal11.id;
						return React.createElement( yG, {
							course: _tLocal11,
							isHover: _nLocal10,
						} );
					},
				},
				{
					title: ( 0, I18n.__ )( 'Course Type', 'ohmylms' ),
					dataIndex: 'type',
					key: 'type',
					width: 150,
					render( _eLocal35 ) {
						return 'cohort-based' === _eLocal35 ? (
							<Controls.BadgeWP
								variant={ 'secondary' }
								isBorderLess={ ! 0 }
							>
								{ ( 0, I18n.__ )( 'Cohort', 'ohmylms' ) }
							</Controls.BadgeWP>
						) : (
							<Controls.BadgeWP
								variant={ 'secondary' }
								isBorderLess={ ! 0 }
							>
								{ ( 0, I18n.__ )( 'Self-paced', 'ohmylms' ) }
							</Controls.BadgeWP>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Date', 'ohmylms' ),
					dataIndex: 'date_created',
					key: 'date_created',
					sorter: ! 0,
					width: null,
					render( _eLocal36 ) {
						return (
							<Controls.BadgeWP
								variant={ 'secondary' }
								isBorderLess={ ! 0 }
							>
								{ sn()(
									_eLocal36 === null || _eLocal36 === void 0
										? void 0
										: _eLocal36.date
								).format( 'MMMM DD, YYYY' ) || '-' }
							</Controls.BadgeWP>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Total Enrolled', 'ohmylms' ),
					dataIndex: 'total_enrollment',
					key: 'total_enrollment',
					sorter: ! 0,
					width: null,
					render( _eLocal37, _tLocal12 ) {
						return (
							<Controls.BadgeWP
								variant={ 'secondary' }
								isBorderLess={ ! 0 }
							>
								{ _eLocal37 ? (
									<v.Link
										to={ ''.concat(
											_tLocal12 === null ||
												_tLocal12 === void 0
												? void 0
												: _tLocal12.id,
											'/students'
										) }
									>
										{ _eLocal37 }{ ' ' }
									</v.Link>
								) : (
									'-'
								) }
							</Controls.BadgeWP>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Status', 'ohmylms' ),
					dataIndex: 'status',
					key: 'status',
					width: null,
					render( _eLocal38 ) {
						return (
							<Controls.BadgeWP
								isBorderLess={ ! 0 }
								variant={ ( () => {
									if ( 'publish' === _eLocal38 ) {
										return 'success';
									}
									if ( 'future' === _eLocal38 ) {
										return 'warning';
									}
									return 'secondary';
								} )() }
								style={ {
									textTransform: 'capitalize',
								} }
							>
								{ ( () => {
									if ( 'publish' === _eLocal38 ) {
										return ( 0, I18n.__ )(
											'Published',
											'ohmylms'
										);
									}
									if ( 'future' === _eLocal38 ) {
										return ( 0, I18n.__ )(
											'Scheduled',
											'ohmylms'
										);
									}
									return ( 0, I18n.__ )( 'Draft', 'ohmylms' );
								} )() }
							</Controls.BadgeWP>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Price', 'ohmylms' ),
					dataIndex: 'price',
					key: 'price',
					width: null,
					render( _eLocal39, _tLocal14 ) {
						return 'paid' ===
							( _tLocal14 === null || _tLocal14 === void 0
								? void 0
								: _tLocal14.price_type ) ? (
							<Controls.BadgeWP
								variant={ 'secondary' }
								isBorderLess={ ! 0 }
							>
								{ _tLocal14.sale_price ? (
									<React.Fragment>
										<YH
											currency={
												( _tLocal14 === null ||
												_tLocal14 === void 0
													? void 0
													: _tLocal14.currency ) ||
												'$'
											}
											currency_pos={
												( _tLocal14 === null ||
												_tLocal14 === void 0
													? void 0
													: _tLocal14.currency_pos ) ||
												'left'
											}
											price={ Number(
												( _tLocal14 === null ||
												_tLocal14 === void 0
													? void 0
													: _tLocal14.sale_price ) ||
													'0'
											) }
										/>
										{ ' ' }
										<del>
											<YH
												currency={
													( _tLocal14 === null ||
													_tLocal14 === void 0
														? void 0
														: _tLocal14.currency ) ||
													'$'
												}
												currency_pos={
													( _tLocal14 === null ||
													_tLocal14 === void 0
														? void 0
														: _tLocal14.currency_pos ) ||
													'left'
												}
												price={ Number(
													( _tLocal14 === null ||
													_tLocal14 === void 0
														? void 0
														: _tLocal14.regular_price ) ||
														'0'
												) }
												del={ ! 0 }
											/>
										</del>
									</React.Fragment>
								) : (
									<YH
										currency={
											( _tLocal14 === null ||
											_tLocal14 === void 0
												? void 0
												: _tLocal14.currency ) || '$'
										}
										currency_pos={
											( _tLocal14 === null ||
											_tLocal14 === void 0
												? void 0
												: _tLocal14.currency_pos ) ||
											'left'
										}
										price={ Number(
											( _tLocal14 === null ||
											_tLocal14 === void 0
												? void 0
												: _tLocal14.price ) || '0'
										) }
									/>
								) }
							</Controls.BadgeWP>
						) : (
							<Controls.BadgeWP
								variant={ 'secondary' }
								isBorderLess={ ! 0 }
							>
								{ ( 0, I18n.__ )( 'Free', 'ohmylms' ) }
							</Controls.BadgeWP>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Action', 'ohmylms' ),
					dataIndex: 'action',
					key: 'action',
					width: null,
					render( _eLocal40, _tLocal15 ) {
						return (
							<Controls.DropdownMenuWP
								controls={ [
									{
										title: ( 0, I18n.__ )(
											'Gradebook',
											'ohmylms'
										),
										onClick: () =>
											setGradebook(
												Number( _tLocal15.id )
											),
									},
									...( canViewAs()
										? [ 'student', 'instructor' ].map(
												( role ) => ( {
													title: ( 0, I18n.__ )(
														role === 'student'
															? 'View as student'
															: 'View as instructor',
														'ohmylms'
													),
													onClick: () =>
														setViewAs( {
															role,
															courseId: Number(
																_tLocal15.id
															),
														} ),
												} )
											)
										: [] ),
									{
										title: ( 0, I18n.__ )(
											'View',
											'ohmylms'
										),
										onClick() {
											return Je(
												_tLocal15 === null ||
													_tLocal15 === void 0
													? void 0
													: _tLocal15.course_url
											);
										},
										icon: <Br />,
									},
								].concat(
									lY(
										'publish' !==
											( _tLocal15 === null ||
											_tLocal15 === void 0
												? void 0
												: _tLocal15.status )
											? [
													{
														title: ( 0, I18n.__ )(
															'Publish',
															'ohmylms'
														),
														onClick() {
															return lt(
																_tLocal15 ===
																	null ||
																	_tLocal15 ===
																		void 0
																	? void 0
																	: _tLocal15.id
															);
														},
														icon: <Ot />,
													},
												]
											: []
									),
									[
										{
											title: ( 0, I18n.__ )(
												'Duplicate',
												'ohmylms'
											),
											onClick() {
												return st(
													_tLocal15 === null ||
														_tLocal15 === void 0
														? void 0
														: _tLocal15.id
												);
											},
											icon: React.createElement(
												yc,
												null
											),
											isPro: ! 0,
										},
										{
											title: ( 0, I18n.__ )(
												'Export',
												'ohmylms'
											),
											onClick() {
												return nt(
													_tLocal15 === null ||
														_tLocal15 === void 0
														? void 0
														: _tLocal15.id
												);
											},
											icon: React.createElement(
												iN,
												null
											),
											isPro: ! 0,
										},
										{
											title: ( 0, I18n.__ )(
												'Export as SCORM',
												'ohmylms'
											),
											onClick() {
												return it(
													_tLocal15 === null ||
														_tLocal15 === void 0
														? void 0
														: _tLocal15.id
												);
											},
											icon: React.createElement(
												iN,
												null
											),
											isPro: ! 0,
										},
										{
											title: ( 0, I18n.__ )(
												'Delete',
												'ohmylms'
											),
											onClick() {
												return Xe(
													_tLocal15 === null ||
														_tLocal15 === void 0
														? void 0
														: _tLocal15.id
												);
											},
											icon: <We />,
										},
									]
								) }
								icon={ <q.Icon icon={ Ne.A } /> }
							/>
						);
					},
				},
			],
			_t = ( 0, ReactHooks.useMemo )(
				function () {
					return [
						{
							label: ( 0, I18n.__ )( 'Delete', 'ohmylms' ),
							value: 'delete',
							action() {
								ne( ! 0 );
							},
						},
						{
							label: ( 0, I18n.__ )( 'Export', 'ohmylms' ),
							value: 'export',
							disabled: ! e,
							action() {
								ue( ! 0 );
							},
						},
					];
				},
				[ W, e ]
			);
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					var _eLocal41 = ! 0;
					return (
						_eLocal41 && Ve(),
						function () {
							_eLocal41 = ! 1;
						}
					 );
				},
				[ j, P, s, p, w, R ]
			),
			( 0, ReactHooks.useEffect )(
				function () {
					if ( ! F && o ) {
						openNotificationWithIcon( i, o );
					}
				},
				[ o ]
			),
			(
				<React.Fragment>
					{ contextHolder }
					{ gradebook && (
						<CourseGradebook
							courseId={ gradebook }
							onClose={ () => setGradebook( null ) }
						/>
					) }
					{ viewAs && (
						<ViewAsModal
							{ ...viewAs }
							onClose={ () => setViewAs( null ) }
						/>
					) }
					<Controls.ContainerWP>
						<YG
							title={ ( 0, I18n.__ )( 'All Courses', 'ohmylms' ) }
							showAddButton={ ! 0 }
							addButtonConfig={ gt }
						>
							<Gq onClick={ dt } />
						</YG>
						<Ea
							isBorderless={ ! 0 }
							minHeight={ 'calc(100vh - 200px)' }
						>
							<Controls.SpacerWP padding={ 5 }>
								{ W.length > 0 ? (
									<React.Fragment>
										{ React.createElement( hN, {
											items: W,
											setItems: B,
											bulksActions: _t,
										} ) }
									</React.Fragment>
								) : (
									<React.Fragment>
										{ React.createElement( aY, {
											handleSearch: Ue,
											searchPlaceholder: ( 0, I18n.__ )(
												'Search Course',
												'ohmylms'
											),
											handleFilterByDays: qe,
											handleFilterByPriceType: Ye,
											handleFilterByCategory: Qe,
											filterByDays: s,
											filterByPriceType: p,
											filterByCategory: w,
											filterByDaysOptions: ft,
											filterByPriceTypeOptions: vt,
											categories: c,
											currentPage: j,
											totalItems: a,
											handleFilterByStatus: Ze,
											filterByStatus: R,
											filterByStatusOptions: ht,
										} ) }
									</React.Fragment>
								) }
								<sN.A
									rowKey={ 'id' }
									columns={ bt }
									dataSource={ n || [] }
									rowSelection={ yt }
									pagination={ ! 1 }
									loading={ F }
									onChange={ Ke }
									scroll={ {
										x: 'max-content',
									} }
									onRowMouseEnter={ function ( _eLocal42 ) {
										return G(
											_eLocal42 === null ||
												_eLocal42 === void 0
												? void 0
												: _eLocal42.id
										);
									} }
									onRowMouseLeave={ function () {
										return G( null );
									} }
									locale={ {
										emptyText: React.createElement( uf, {
											icon: React.createElement(
												df,
												null
											),
											title: ( 0, I18n.__ )(
												'No courses yet!',
												'ohmylms'
											),
											description: ( 0, I18n.__ )(
												'Start building your first course and it’ll show up here as soon as you hit publish.',
												'ohmylms'
											),
											ctaText: ( 0, I18n.__ )(
												'Add Course',
												'ohmylms'
											),
											ctaHandler: Ge,
										} ),
									} }
								/>
								{ ! F &&
									Number( a ) > 5 &&
									React.createElement( fN, {
										total: a,
										currentPage: j,
										onPageChange: $e,
										perPage: 5,
									} ) }
							</Controls.SpacerWP>
						</Ea>
					</Controls.ContainerWP>
					{ Y && (
						<React.Fragment>
							<VG
								onClose={ mt }
								onAction={ pt }
								isOpen={ Y }
								cancelBtnText={ ( 0, I18n.__ )(
									'Cancel',
									'ohmylms'
								) }
								actionBtnText={ ( 0, I18n.__ )(
									'Import',
									'ohmylms'
								) }
								loading={ $ }
								supportScorm={ ! 0 }
								jsonImportEnabled={ e }
							/>
						</React.Fragment>
					) }
					{ te && (
						<React.Fragment>
							<Ie
								title={
									W.length > 1
										? ( 0, I18n.__ )(
												'Delete Courses',
												'ohmylms'
											)
										: ( 0, I18n.__ )(
												'Delete Course',
												'ohmylms'
											)
								}
								description={
									W.length > 1
										? ( 0, I18n.__ )(
												'Are you sure you want to delete these courses?',
												'ohmylms'
											)
										: ( 0, I18n.__ )(
												'Are you sure you want to delete course?',
												'ohmylms'
											)
								}
								onClose={ et }
								onDelete={ tt }
								isOpen={ te }
								isDelete={ ! 0 }
							/>
						</React.Fragment>
					) }
					{ ce && (
						<React.Fragment>
							<Ie
								title={ ( 0, I18n.__ )(
									'Export Courses',
									'ohmylms'
								) }
								description={
									<React.Fragment>
										{ ( 0, I18n.__ )(
											'You are about to export the selected courses.',
											'ohmylms'
										) }
										<br />
										{ ( 0, I18n.__ )(
											' Do you want to proceed?',
											'ohmylms'
										) }
									</React.Fragment>
								}
								onClose={ rt }
								onDelete={ function () {
									return nt();
								} }
								isOpen={ ce }
								type={ 'warning' }
								actionBtnText={ 'Export' }
							/>
						</React.Fragment>
					) }
					{ de && (
						<React.Fragment>
							<Ie
								title={ ( 0, I18n.__ )(
									'Export as SCORM Package',
									'ohmylms'
								) }
								description={
									<React.Fragment>
										<div
											style={ {
												marginBottom: '15px',
											} }
										>
											{ W.length > 0
												? ( 0, I18n.__ )(
														'You are about to export the selected courses as a SCORM package.',
														'ohmylms'
													)
												: ( 0, I18n.__ )(
														'You are about to export this course as a SCORM package.',
														'ohmylms'
													) }
										</div>
										<div
											style={ {
												marginBottom: '15px',
											} }
										>
											<label
												style={ {
													display: 'block',
													marginBottom: '8px',
													fontWeight: '500',
												} }
												htmlFor={
													_controlId + '-field-1'
												}
											>
												{ ( 0, I18n.__ )(
													'Select SCORM Version:',
													'ohmylms'
												) }
											</label>
											<select
												value={ fe }
												onChange={ function (
													_eLocal43
												) {
													return ve(
														_eLocal43.target.value
													);
												} }
												style={ {
													width: '100%',
													padding: '8px 12px',
													border: '1px solid #ddd',
													borderRadius: '4px',
													fontSize: '14px',
												} }
												id={ _controlId + '-field-1' }
											>
												<option value={ '1.2' }>
													{ 'SCORM 1.2' }
												</option>
												<option value={ '2004' }>
													{ 'SCORM 2004' }
												</option>
											</select>
										</div>
										<div
											style={ {
												fontSize: '12px',
												color: '#666',
											} }
										>
											{ ( 0, I18n.__ )(
												'The SCORM package will be compatible with major LMS platforms like Moodle, Canvas, and Blackboard.',
												'ohmylms'
											) }
										</div>
									</React.Fragment>
								}
								onClose={ ot }
								onDelete={ function () {
									return at();
								} }
								isOpen={ de }
								type={ 'warning' }
								actionBtnText={ ( 0, I18n.__ )(
									'Export SCORM',
									'ohmylms'
								) }
								loading={ he }
							/>
						</React.Fragment>
					) }
					{ _e && (
						<React.Fragment>
							<Ie
								title={ ( 0, I18n.__ )(
									'Duplicate this course',
									'ohmylms'
								) }
								description={
									<React.Fragment>
										{ ( 0, I18n.__ )(
											'You are about to duplicate the selected course.',
											'ohmylms'
										) }
										<br />
										{ ( 0, I18n.__ )(
											' Do you want to proceed?',
											'ohmylms'
										) }
									</React.Fragment>
								}
								onClose={ ut }
								onDelete={ ct }
								isOpen={ _e }
								type={ 'warning' }
								actionBtnText={ 'Duplicate' }
								loading={ Se }
							/>
						</React.Fragment>
					) }
					{ Ce && (
						<React.Fragment>
							<He.default isOpen={ Ce } onClose={ Pe } />
						</React.Fragment>
					) }
					{ je &&
						React.createElement( lU, {
							isOpen: je,
							onClose() {
								return Ae( ! 1 );
							},
						} ) }
				</React.Fragment>
			 )
		 );
	};
}
