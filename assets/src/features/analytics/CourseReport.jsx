import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
import {
	PriceDisplay,
	decodeHtmlEntities,
	highlightAdminMenu,
} from '../../shared/utils';
export function createCourseReport( readRuntime ) {
	return function CourseReport() {
		const {
			CU,
			EU,
			IU,
			LU: EarningsChart,
			MU,
			Nr: BackButton,
			OU,
			RU,
			React,
			T: StoreModule,
			_U,
			b: I18n,
			cq: CourseStudentsReport,
			dq,
			f: Router,
			g: ReactHooks,
			gU: ReportMetricCard,
			gq,
			hq,
			jU,
			l,
			lN,
			mq,
			pq,
			y: WordPressData,
			yU,
		} = readRuntime();
		var e,
			t,
			n,
			r,
			a,
			o,
			i,
			c,
			u,
			s,
			d,
			m,
			p,
			v,
			h,
			_,
			w,
			E,
			S,
			R,
			x = ( 0, Router.g )().id;
		if ( ! x ) {
		}
		highlightAdminMenu( 'ohmylms', 'course' );
		( 0, WordPressData.useDispatch )( StoreModule.default );
		var C = hq( ( 0, ReactHooks.useState )( {} ), 2 ),
			P = C[ 0 ],
			O = C[ 1 ],
			k =
				( ( 0, WordPressData.useSelect )( function ( _eLocal ) {
					return _eLocal( StoreModule.default ).getDashboardFilter();
				}, [] ),
				hq( ( 0, ReactHooks.useState )( ! 0 ), 2 ) ),
			j = k[ 0 ],
			A = k[ 1 ],
			M = hq( ( 0, ReactHooks.useState )( '' ), 2 ),
			F = M[ 0 ],
			N = ( M[ 1 ], hq( ( 0, ReactHooks.useState )( 'all' ), 2 ) ),
			D = N[ 0 ],
			W = ( N[ 1 ], hq( ( 0, ReactHooks.useState )( 'all' ), 2 ) ),
			z = W[ 0 ],
			B =
				( W[ 1 ],
				( 0, Router.Zp )(),
				( 0, ReactHooks.useCallback )(
					gq(
						pq().m( function _eLocal2( ..._args ) {
							var _tLocal,
								_nLocal,
								_rLocal,
								_aLocal,
								_oLocal,
								_iLocal = _args;
							return pq().w(
								function ( _eLocal3 ) {
									for (;;) {
										switch ( ( _eLocal3.p = _eLocal3.n ) ) {
											case 0:
												return (
													( _tLocal =
														_iLocal.length > 0 &&
														void 0 !== _iLocal[ 0 ]
															? _iLocal[ 0 ]
															: 'date' ),
													( _nLocal =
														_iLocal.length > 1 &&
														void 0 !== _iLocal[ 1 ]
															? _iLocal[ 1 ]
															: 'DESC' ),
													A( ! 0 ),
													( _eLocal3.p = 1 ),
													( _rLocal = {
														sort_by: _nLocal,
														filter: D,
														search: F || '',
														order: _tLocal,
														completion_type: z,
													} ),
													( _eLocal3.n = 2 ),
													l()( {
														path: ( 0,
														lN.addQueryArgs )(
															'/ohmylms/v1/analytics/course/'.concat(
																x
															),
															_rLocal
														),
														method: 'GET',
														headers: {
															'Content-Type':
																'application/json',
														},
													} )
												 );
											case 2:
												_aLocal = _eLocal3.v;
												O( _aLocal );
												_eLocal3.n = 4;
												break;
											case 3:
												_eLocal3.p = 3;
												_oLocal = _eLocal3.v;
												showRequestError(
													'Error fetching data:',
													_oLocal
												);
											// Falls through to the shared async state cleanup.
											case 4:
												return (
													( _eLocal3.p = 4 ),
													A( ! 1 ),
													_eLocal3.f( 4 )
												 );
											case 5:
												return _eLocal3.a( 2 );
										}
									}
								},
								_eLocal2,
								null,
								[ [ 1, 3, 4, 5 ] ]
							);
						} )
					),
					[ x, D, F, z ]
				) );
		( 0, ReactHooks.useEffect )(
			function () {
				var _eLocal4 = ! 0;
				return (
					_eLocal4 && B(),
					function () {
						_eLocal4 = ! 1;
					}
				 );
			},
			[ D, F, x, z ]
		);
		var L = {
			background: '#6e42d3',
			borderRadius: '2px',
			width: '10px',
			height: '10px',
			display: 'inline-block',
			marginInlineEnd: '15px',
		};
		return (
			<Controls.SurfaceWP>
				<Controls.ContainerWP>
					<Controls.SpacerWP paddingY={ 6 }>
						<Controls.FlexWP gap={ 4 } justify={ 'flex-start' }>
							<BackButton />
							<Controls.HeadingWP level={ 2 } size={ 20 }>
								{ ( 0, I18n.__ )(
									'Course Analytics',
									'ohmylms'
								) }
								{ '   ' }
								{ ! j && (
									<small>
										{ '(' }
										{ decodeHtmlEntities(
											P === null || P === void 0
												? void 0
												: P.title
										) }
										{ ')' }
									</small>
								) }
							</Controls.HeadingWP>
						</Controls.FlexWP>
					</Controls.SpacerWP>
					<Controls.CardWP
						isBorderless={ ! 0 }
						variant={ 'secondary' }
					>
						<Controls.SpacerWP padding={ 6 } marginBottom={ 0 }>
							<Controls.HeadingWP level={ 3 } size={ 16 }>
								{ ( 0, I18n.__ )(
									'Journey Mapping',
									'ohmylms'
								) }
							</Controls.HeadingWP>
							<Controls.SpacerWP marginBottom={ 3 } />
							<Controls.FlexWP
								wrap={ ! 0 }
								gap={ 5 }
								align={ 'stretch' }
								justify={ 'space-between' }
								className={
									'ohmylms-course-journey-card-wrapper'
								}
							>
								<Controls.FlexItemWP
									style={ {
										width: 'calc(41% - 11px)',
									} }
									className={
										'ohmylms-course-journey-left-card'
									}
								>
									<Controls.CardWP
										isBorderless={ ! 0 }
										fullHeight={ ! 0 }
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
													padding={ 5 }
													marginBottom={ 0 }
												>
													{ j ? (
														<Controls.SkeletonWP
															paragraph={ {
																rows: 3,
															} }
															active={ ! 0 }
														/>
													) : (
														<React.Fragment>
															<Controls.FlexWP
																gap={ 4 }
															>
																<Controls.FlexBlockWP>
																	<ReportMetricCard
																		title={ ( 0,
																		I18n.__ )(
																			'Total Students',
																			'ohmylms'
																		) }
																		cardNumber={
																			( P ===
																				null ||
																			P ===
																				void 0 ||
																			null ===
																				( e =
																					P.content_data ) ||
																			void 0 ===
																				e
																				? void 0
																				: e.total_enrollment ) ||
																			0
																		}
																		icon={ React.createElement(
																			yU,
																			null
																		) }
																	/>
																</Controls.FlexBlockWP>
																<Controls.FlexBlockWP>
																	<ReportMetricCard
																		title={ ( 0,
																		I18n.__ )(
																			'Course Completion',
																			'ohmylms'
																		) }
																		cardNumber={
																			( P ===
																				null ||
																			P ===
																				void 0 ||
																			null ===
																				( t =
																					P.content_data ) ||
																			void 0 ===
																				t
																				? void 0
																				: t.completed_students ) ||
																			0
																		}
																		icon={
																			<_U />
																		}
																	/>
																</Controls.FlexBlockWP>
															</Controls.FlexWP>
															<Controls.SpacerWP
																marginBottom={
																	4
																}
															/>
															<Controls.FlexWP
																gap={ 4 }
															>
																<Controls.FlexBlockWP>
																	<ReportMetricCard
																		title={ ( 0,
																		I18n.__ )(
																			'Students In Progress',
																			'ohmylms'
																		) }
																		cardNumber={
																			( P ===
																				null ||
																			P ===
																				void 0 ||
																			null ===
																				( n =
																					P.content_data ) ||
																			void 0 ===
																				n
																				? void 0
																				: n.in_progress_students ) ||
																			0
																		}
																		icon={
																			<EU />
																		}
																	/>
																</Controls.FlexBlockWP>
																<Controls.FlexBlockWP>
																	<ReportMetricCard
																		title={ ( 0,
																		I18n.__ )(
																			'Ratings',
																			'ohmylms'
																		) }
																		cardNumber={
																			( P ===
																				null ||
																			P ===
																				void 0 ||
																			null ===
																				( r =
																					P.content_data ) ||
																			void 0 ===
																				r
																				? void 0
																				: r.ratings ) ||
																			0
																		}
																		icon={
																			<RU />
																		}
																	/>
																</Controls.FlexBlockWP>
															</Controls.FlexWP>
														</React.Fragment>
													) }
												</Controls.SpacerWP>
											</Controls.CardWP>
											<Controls.SpacerWP
												marginBottom={ 5 }
											/>
											<Controls.CardWP
												isBorderless={ ! 0 }
												variant={ 'secondary' }
											>
												<Controls.SpacerWP
													padding={ 5 }
													marginBottom={ 0 }
												>
													{ j ? (
														<Controls.SkeletonWP
															paragraph={ {
																rows: 3,
															} }
															active={ ! 0 }
														/>
													) : (
														<React.Fragment>
															<Controls.FlexWP
																gap={ 4 }
															>
																<Controls.FlexBlockWP>
																	<ReportMetricCard
																		title={ ( 0,
																		I18n.__ )(
																			'Chapters',
																			'ohmylms'
																		) }
																		cardNumber={
																			( P ===
																				null ||
																			P ===
																				void 0 ||
																			null ===
																				( a =
																					P.content_data ) ||
																			void 0 ===
																				a
																				? void 0
																				: a.chapters ) ||
																			0
																		}
																		icon={
																			<CU />
																		}
																	/>
																</Controls.FlexBlockWP>
																<Controls.FlexBlockWP>
																	<ReportMetricCard
																		title={ ( 0,
																		I18n.__ )(
																			'Lessons',
																			'ohmylms'
																		) }
																		cardNumber={
																			( P ===
																				null ||
																			P ===
																				void 0 ||
																			null ===
																				( o =
																					P.content_data ) ||
																			void 0 ===
																				o
																				? void 0
																				: o.lessons ) ||
																			0
																		}
																		icon={
																			<OU />
																		}
																	/>
																</Controls.FlexBlockWP>
															</Controls.FlexWP>
															<Controls.SpacerWP
																marginBottom={
																	4
																}
															/>
															<Controls.FlexWP
																gap={ 4 }
															>
																<Controls.FlexBlockWP>
																	<ReportMetricCard
																		title={ ( 0,
																		I18n.__ )(
																			'Quizzes',
																			'ohmylms'
																		) }
																		cardNumber={
																			( P ===
																				null ||
																			P ===
																				void 0 ||
																			null ===
																				( i =
																					P.content_data ) ||
																			void 0 ===
																				i
																				? void 0
																				: i.quizzes ) ||
																			0
																		}
																		icon={
																			<MU />
																		}
																	/>
																</Controls.FlexBlockWP>
																<Controls.FlexBlockWP>
																	<ReportMetricCard
																		title={ ( 0,
																		I18n.__ )(
																			'Assignments',
																			'ohmylms'
																		) }
																		cardNumber={
																			( P ===
																				null ||
																			P ===
																				void 0 ||
																			null ===
																				( c =
																					P.content_data ) ||
																			void 0 ===
																				c
																				? void 0
																				: c.assignments ) ||
																			0
																		}
																		icon={ React.createElement(
																			jU,
																			null
																		) }
																	/>
																</Controls.FlexBlockWP>
															</Controls.FlexWP>
														</React.Fragment>
													) }
												</Controls.SpacerWP>
											</Controls.CardWP>
										</Controls.SpacerWP>
									</Controls.CardWP>
								</Controls.FlexItemWP>
								<Controls.FlexItemWP
									style={ {
										width: 'calc(59% - 11px)',
									} }
									className={
										'ohmylms-course-journey-right-card'
									}
								>
									<Controls.CardWP
										isBorderless={ ! 0 }
										fullHeight={ ! 0 }
										fullWidth={ ! 0 }
									>
										<Controls.SpacerWP
											marginBottom={ 0 }
											padding={ 5 }
										>
											<Controls.HeadingWP
												level={ 3 }
												size={ 16 }
											>
												{ ( 0, I18n.__ )(
													'Earnings',
													'ohmylms'
												) }
											</Controls.HeadingWP>
											<Controls.SpacerWP
												marginBottom={ 5 }
											/>
											{ j ? (
												<Controls.SkeletonWP
													paragraph={ {
														rows: 3,
													} }
													active={ ! 0 }
												/>
											) : (
												<React.Fragment>
													<Controls.CardWP
														isBorderless={ ! 0 }
														variant={ 'secondary' }
													>
														<Controls.SpacerWP
															padding={ 4 }
														>
															<Controls.FlexWP
																gap={ 4 }
															>
																<ReportMetricCard
																	title={ ( 0,
																	I18n.__ )(
																		'Income',
																		'ohmylms'
																	) }
																	icon={
																		<React.Fragment>
																			<IU
																				iconColor={
																					'#6e42d3'
																				}
																			/>
																		</React.Fragment>
																	}
																	children={
																		<span
																			className={
																				'course-report-card-value'
																			}
																			style={ {
																				fontSize:
																					'30px',
																				marginLeft:
																					'40px',
																			} }
																		>
																			<PriceDisplay
																				currency={
																					P ===
																						null ||
																					P ===
																						void 0 ||
																					null ===
																						( u =
																							P.earning ) ||
																					void 0 ===
																						u
																						? void 0
																						: u.currency
																				}
																				currency_pos={
																					P ===
																						null ||
																					P ===
																						void 0 ||
																					null ===
																						( s =
																							P.earning ) ||
																					void 0 ===
																						s
																						? void 0
																						: s.currency_pos
																				}
																				price={ Number(
																					P ===
																						null ||
																						P ===
																							void 0 ||
																						null ===
																							( d =
																								P.earning ) ||
																						void 0 ===
																							d
																						? void 0
																						: d.total_earning
																				) }
																			/>
																		</span>
																	}
																/>
																<ReportMetricCard
																	className={
																		'ohmylms-refund'
																	}
																	title={ ( 0,
																	I18n.__ )(
																		'Refund',
																		'ohmylms'
																	) }
																	icon={
																		<React.Fragment>
																			<IU
																				iconColor={
																					'#ff4955'
																				}
																			/>
																		</React.Fragment>
																	}
																	children={
																		<span
																			className={
																				'course-report-card-value'
																			}
																			style={ {
																				fontSize:
																					'30px',
																				marginLeft:
																					'40px',
																			} }
																		>
																			<PriceDisplay
																				currency={
																					P ===
																						null ||
																					P ===
																						void 0 ||
																					null ===
																						( m =
																							P.earning ) ||
																					void 0 ===
																						m
																						? void 0
																						: m.currency
																				}
																				currency_pos={
																					P ===
																						null ||
																					P ===
																						void 0 ||
																					null ===
																						( p =
																							P.earning ) ||
																					void 0 ===
																						p
																						? void 0
																						: p.currency_pos
																				}
																				price={ Number(
																					P ===
																						null ||
																						P ===
																							void 0 ||
																						null ===
																							( v =
																								P.earning ) ||
																						void 0 ===
																							v
																						? void 0
																						: v.total_refund
																				) }
																			/>
																		</span>
																	}
																/>
																<ReportMetricCard
																	className={
																		'ohmylms-net-income'
																	}
																	title={ ( 0,
																	I18n.__ )(
																		'Net Income',
																		'ohmylms'
																	) }
																	icon={
																		<React.Fragment>
																			<IU
																				iconColor={
																					'#33A646'
																				}
																			/>
																		</React.Fragment>
																	}
																	children={
																		<span
																			className={
																				'course-report-card-value'
																			}
																			style={ {
																				fontSize:
																					'30px',
																				marginLeft:
																					'40px',
																			} }
																		>
																			<PriceDisplay
																				currency={
																					P ===
																						null ||
																					P ===
																						void 0 ||
																					null ===
																						( h =
																							P.earning ) ||
																					void 0 ===
																						h
																						? void 0
																						: h.currency
																				}
																				currency_pos={
																					P ===
																						null ||
																					P ===
																						void 0 ||
																					null ===
																						( _ =
																							P.earning ) ||
																					void 0 ===
																						_
																						? void 0
																						: _.currency_pos
																				}
																				price={ Number(
																					P ===
																						null ||
																						P ===
																							void 0 ||
																						null ===
																							( w =
																								P.earning ) ||
																						void 0 ===
																							w
																						? void 0
																						: w.net_amount
																				) }
																			/>
																		</span>
																	}
																/>
															</Controls.FlexWP>
														</Controls.SpacerWP>
													</Controls.CardWP>
													<Controls.SpacerWP
														marginBottom={ 5 }
													/>
													<Controls.SpacerWP
														marginBottom={ 0 }
														className={
															'ohmylms-course-report-chart'
														}
													>
														<Controls.SpacerWP
															marginBottom={ 0 }
															style={ {
																height: '320px',
															} }
														>
															<EarningsChart
																currency={
																	P ===
																		null ||
																	P ===
																		void 0 ||
																	null ===
																		( E =
																			P.earning ) ||
																	void 0 === E
																		? void 0
																		: E.currency
																}
																currency_pos={
																	P ===
																		null ||
																	P ===
																		void 0 ||
																	null ===
																		( S =
																			P.earning ) ||
																	void 0 === S
																		? void 0
																		: S.currency_pos
																}
																graphData={
																	( P ===
																		null ||
																	P ===
																		void 0 ||
																	null ===
																		( R =
																			P.earning ) ||
																	void 0 === R
																		? void 0
																		: R.graph_data ) ||
																	{}
																}
																filterTypeParam={ {
																	type: 'custom',
																} }
															/>
														</Controls.SpacerWP>
														<Controls.SpacerWP
															marginBottom={ 5 }
														/>
														<Controls.FlexWP
															gap={ 5 }
															align={ 'center' }
															justify={ 'center' }
														>
															<Controls.FlexItemWP
																style={ mq(
																	{},
																	'--base-color',
																	'#6e42d3'
																) }
															>
																<span
																	style={ L }
																/>
																{ ( 0,
																I18n.__ )(
																	'Income',
																	'ohmylms'
																) }
															</Controls.FlexItemWP>
															<Controls.FlexItemWP
																style={ mq(
																	{},
																	'--base-color',
																	'#FF4955'
																) }
															>
																<span
																	style={ dq(
																		dq(
																			{},
																			L
																		),
																		{},
																		{
																			background:
																				'#FF4955',
																		}
																	) }
																/>
																{ ( 0,
																I18n.__ )(
																	'Refund',
																	'ohmylms'
																) }
															</Controls.FlexItemWP>
															<Controls.FlexItemWP
																style={ mq(
																	{},
																	'--base-color',
																	'#33A646'
																) }
															>
																<span
																	style={ dq(
																		dq(
																			{},
																			L
																		),
																		{},
																		{
																			background:
																				'#33A646',
																		}
																	) }
																/>
																{ ( 0,
																I18n.__ )(
																	'Net Income',
																	'ohmylms'
																) }
															</Controls.FlexItemWP>
														</Controls.FlexWP>
													</Controls.SpacerWP>
												</React.Fragment>
											) }
										</Controls.SpacerWP>
									</Controls.CardWP>
								</Controls.FlexItemWP>
							</Controls.FlexWP>
							<Controls.SpacerWP marginBottom={ 5 } />
							<Controls.CardWP isBorderless={ ! 0 }>
								<Controls.SpacerWP
									marginBottom={ 0 }
									padding={ 5 }
								>
									<CourseStudentsReport
										students={
											P === null || P === void 0
												? void 0
												: P.students
										}
									/>
								</Controls.SpacerWP>
							</Controls.CardWP>
						</Controls.SpacerWP>
					</Controls.CardWP>
				</Controls.ContainerWP>
				<Controls.SpacerWP marginBottom={ 0 } paddingBottom={ 5 } />
			</Controls.SurfaceWP>
		);
	};
}
