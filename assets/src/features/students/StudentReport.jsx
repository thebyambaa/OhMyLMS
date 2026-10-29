import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createStudentReport( readRuntime ) {
	return function StudentReport() {
		const {
			BZ,
			DZ,
			FZ,
			GZ,
			HG,
			HZ,
			Nr,
			React,
			T: StoreModule,
			TZ,
			_U,
			b: I18n,
			f: Router,
			g: ReactHooks,
			gU,
			sn,
			y: WordPressData,
			zZ,
		} = readRuntime();
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
								return GZ( _eLocal5, _tLocal3 );
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
										return GZ( _eLocal5, _tLocal3 );
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
			o = ( 0, WordPressData.useSelect )( function ( _eLocal6 ) {
				return _eLocal6( StoreModule.default ).getStudent();
			}, [] );
		HG( 'ohmylms', 'students' );
		var i = ( 0, ReactHooks.useCallback )(
			HZ(
				BZ().m( function _nLocal3() {
					var _rLocal;
					return BZ().w(
						function ( _nLocal4 ) {
							for (;;) {
								switch ( ( _nLocal4.p = _nLocal4.n ) ) {
									case 0:
										return (
											a( ! 0 ),
											( _nLocal4.p = 1 ),
											( _nLocal4.n = 2 ),
											t.fetchSingleStudent( e )
										 );
									case 2:
										_nLocal4.n = 4;
										break;
									case 3:
										_nLocal4.p = 3;
										_rLocal = _nLocal4.v;
										showRequestError(
											'Error fetching student data:',
											_rLocal
										);
									// Falls through to the shared async state cleanup.
									case 4:
										return (
											( _nLocal4.p = 4 ),
											a( ! 1 ),
											_nLocal4.f( 4 )
										 );
									case 5:
										return _nLocal4.a( 2 );
								}
							}
						},
						_nLocal3,
						null,
						[ [ 1, 3, 4, 5 ] ]
					);
				} )
			),
			[ e ]
		);
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					if ( e ) {
						i();
					}
				},
				[ e ]
			),
			e ? (
				( ( 0, Router.Zp )(),
				(
					<Controls.SurfaceWP>
						<Controls.ContainerWP>
							<Controls.SpacerWP paddingY={ 5 }>
								<Controls.FlexWP
									justify={ 'start' }
									align={ 'center' }
									gap={ 3 }
								>
									<Nr />
									<Controls.HeadingWP level={ 2 } size={ 20 }>
										{ ( 0, I18n.__ )(
											'Student Analytics',
											'ohmylms'
										) }
									</Controls.HeadingWP>
								</Controls.FlexWP>
								<Controls.CardWP
									isBorderless={ ! 0 }
									variant={ 'secondary' }
								>
									<Controls.SpacerWP
										padding={ 5 }
										marginTop={ 3 }
									>
										<Controls.FlexWP
											direction={ 'column' }
											gap={ 4 }
										>
											<Controls.FlexWP
												justify={ 'space-between' }
												align={ 'center' }
												gap={ 3 }
											>
												<Controls.FlexItemWP flex={ 1 }>
													<Controls.HeadingWP
														level={ 3 }
														size={ 16 }
													>
														{ ( 0, I18n.__ )(
															'Journey Mapping',
															'ohmylms'
														) }
													</Controls.HeadingWP>
												</Controls.FlexItemWP>
												<Controls.FlexItemWP>
													<Controls.FlexWP
														gap={ 2 }
														justify={ 'end' }
														align={ 'center' }
													>
														<Controls.BadgeWP
															isBorderLess={ ! 0 }
														>
															<Controls.TextWP
																variant={
																	'muted'
																}
															>
																{ ( 0,
																I18n.__ )(
																	'Email:',
																	'ohmylms'
																) }
															</Controls.TextWP>
															<Controls.TextWP>
																{ o === null ||
																o === void 0
																	? void 0
																	: o.student_email }
															</Controls.TextWP>
														</Controls.BadgeWP>
														{ ( o === null ||
														o === void 0
															? void 0
															: o.student_phone ) && (
															<Controls.BadgeWP
																isBorderLess={
																	! 0
																}
															>
																<Controls.TextWP
																	variant={
																		'muted'
																	}
																>
																	{ ( 0,
																	I18n.__ )(
																		'Phone:',
																		'ohmylms'
																	) }
																</Controls.TextWP>
																<Controls.TextWP>
																	{
																		o.student_phone
																	}
																</Controls.TextWP>
															</Controls.BadgeWP>
														) }
														{ ( o === null ||
														o === void 0
															? void 0
															: o.student_whatsapp ) && (
															<Controls.BadgeWP
																isBorderLess={
																	! 0
																}
															>
																<Controls.TextWP
																	variant={
																		'muted'
																	}
																>
																	{ ( 0,
																	I18n.__ )(
																		'WhatsApp:',
																		'ohmylms'
																	) }
																</Controls.TextWP>
																<Controls.TextWP>
																	{
																		o.student_whatsapp
																	}
																</Controls.TextWP>
															</Controls.BadgeWP>
														) }
														{ ( o === null ||
														o === void 0
															? void 0
															: o.student_timezone ) && (
															<Controls.BadgeWP
																isBorderLess={
																	! 0
																}
															>
																<Controls.TextWP
																	variant={
																		'muted'
																	}
																>
																	{ ( 0,
																	I18n.__ )(
																		'Timezone:',
																		'ohmylms'
																	) }
																</Controls.TextWP>
																<Controls.TextWP>
																	{
																		o.student_timezone
																	}
																</Controls.TextWP>
															</Controls.BadgeWP>
														) }
														<Controls.BadgeWP
															isBorderLess={ ! 0 }
														>
															<Controls.TextWP
																variant={
																	'muted'
																}
															>
																{ ( 0,
																I18n.__ )(
																	'Reg. Date:',
																	'ohmylms'
																) }
															</Controls.TextWP>
															<Controls.TextWP>
																{ sn()(
																	o ===
																		null ||
																		o ===
																			void 0
																		? void 0
																		: o.enrollment_date
																).format(
																	'YYYY-MM-DD'
																) || '-' }
															</Controls.TextWP>
														</Controls.BadgeWP>
													</Controls.FlexWP>
												</Controls.FlexItemWP>
											</Controls.FlexWP>
											<Controls.CardWP
												isBorderless={ ! 0 }
											>
												<Controls.SpacerWP
													marginBottom={ 0 }
													padding={ 6 }
												>
													<Controls.CardWP
														isBorderless={ ! 0 }
														variant={ 'secondary' }
													>
														<Controls.SpacerWP
															marginBottom={ 0 }
															padding={ 4 }
														>
															<Controls.FlexWP
																align={
																	'start'
																}
																gap={ 4 }
																justify={
																	'start'
																}
															>
																<Controls.FlexItemWP
																	isBlock={
																		! 0
																	}
																>
																	{ React.createElement(
																		gU,
																		{
																			title: ( 0,
																			I18n.__ )(
																				'Enrolled Courses',
																				'ohmylms'
																			),
																			cardNumber:
																				( o ===
																					null ||
																				o ===
																					void 0
																					? void 0
																					: o.enrolled_courses ) ||
																				'0',
																			icon: React.createElement(
																				zZ,
																				null
																			),
																		}
																	) }
																</Controls.FlexItemWP>
																<Controls.FlexItemWP
																	isBlock={
																		! 0
																	}
																>
																	{ React.createElement(
																		gU,
																		{
																			title: ( 0,
																			I18n.__ )(
																				'In Progress Courses',
																				'ohmylms'
																			),
																			cardNumber:
																				( o ===
																					null ||
																				o ===
																					void 0
																					? void 0
																					: o.in_progress_courses ) ||
																				'0',
																			icon: (
																				<DZ />
																			),
																		}
																	) }
																</Controls.FlexItemWP>
																<Controls.FlexItemWP
																	isBlock={
																		! 0
																	}
																>
																	{ React.createElement(
																		gU,
																		{
																			title: ( 0,
																			I18n.__ )(
																				'Complete Courses',
																				'ohmylms'
																			),
																			cardNumber:
																				( o ===
																					null ||
																				o ===
																					void 0
																					? void 0
																					: o.completed_courses ) ||
																				'0',
																			icon: (
																				<_U />
																			),
																		}
																	) }
																</Controls.FlexItemWP>
																<Controls.FlexItemWP
																	isBlock={
																		! 0
																	}
																>
																	{ React.createElement(
																		gU,
																		{
																			title: ( 0,
																			I18n.__ )(
																				'Total Memberships',
																				'ohmylms'
																			),
																			cardNumber:
																				( o ===
																					null ||
																				o ===
																					void 0
																					? void 0
																					: o.total_membership ) ||
																				'0',
																			icon: (
																				<FZ />
																			),
																		}
																	) }
																</Controls.FlexItemWP>
															</Controls.FlexWP>
														</Controls.SpacerWP>
													</Controls.CardWP>
												</Controls.SpacerWP>
											</Controls.CardWP>
											<TZ
												studentData={ o }
												loading={ r }
											/>
										</Controls.FlexWP>
									</Controls.SpacerWP>
								</Controls.CardWP>
							</Controls.SpacerWP>
						</Controls.ContainerWP>
						<Controls.SpacerWP
							marginBottom={ 0 }
							paddingBottom={ 5 }
						/>
					</Controls.SurfaceWP>
				 ) )
			) : (
				<Router.C5 to={ '/accounthub' } />
			)
		 );
	};
}
