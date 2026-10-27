import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { normalizeQuizReport } from './model.mjs';
export function createQuizGrading( readRuntime ) {
	return function QuizGrading() {
		const {
			B$,
			D$,
			Ge: decodeHtml,
			HG: useAdminScreen,
			I: Controls,
			I$: QuizQuestionResults,
			L$,
			N$: QuizResultSummary,
			QZ: BackIcon,
			R$,
			React,
			b: I18n,
			f: Router,
			g: ReactHooks,
			j$,
			l: ApiFetchModule,
			r$: DateIcon,
			sn: DateLibrary,
			v: RouterLinks,
		} = readRuntime();
		var e, t, n, r, a, o, i, c, u, s, d;
		useAdminScreen( 'ohmylms', 'quizzes' );
		var m = L$( ( 0, ReactHooks.useState )( [] ), 2 ),
			attempt = m[ 0 ],
			setAttempt = m[ 1 ],
			y = L$( ( 0, ReactHooks.useState )( ! 0 ), 2 ),
			loading = y[ 0 ],
			setLoading = y[ 1 ],
			navigate = ( 0, Router.Zp )(),
			params = ( 0, Router.g )(),
			quizId = params.id,
			attemptId = params.quizId,
			fetchReport = ( function () {
				var _eLocal = B$(
					D$().m( function _eLocal2() {
						var _tLocal, _nLocal;
						return D$().w(
							function ( _eLocal3 ) {
								for (;;) {
									switch ( ( _eLocal3.p = _eLocal3.n ) ) {
										case 0:
											return (
												( _eLocal3.p = 0 ),
												setLoading( ! 0 ),
												( _eLocal3.n = 1 ),
												ApiFetchModule()( {
													path: '/ohmylms/v1/quiz/'
														.concat(
															quizId,
															'/report/'
														)
														.concat( attemptId ),
													method: 'GET',
													headers: {
														'Content-Type':
															'application/json',
													},
												} )
											 );
										case 1:
											if ( ( _tLocal = _eLocal3.v ) ) {
												_eLocal3.n = 2;
												break;
											}
											_tLocal = [];
										// Falls through to the shared async state cleanup.
										case 2:
											setAttempt(
												normalizeQuizReport( _tLocal )
											);
											_eLocal3.n = 4;
											break;
										case 3:
											_eLocal3.p = 3;
											_nLocal = _eLocal3.v;
											showRequestError( _nLocal );
										// Falls through to the shared async state cleanup.
										case 4:
											return (
												( _eLocal3.p = 4 ),
												setLoading( ! 1 ),
												_eLocal3.f( 4 )
											 );
										case 5:
											return _eLocal3.a( 2 );
									}
								}
							},
							_eLocal2,
							null,
							[ [ 0, 3, 4, 5 ] ]
						);
					} )
				);
				return function ( ..._args ) {
					return _eLocal.apply( this, _args );
				};
			} )();
		( 0, ReactHooks.useEffect )( function () {
			fetchReport();
		}, [] );
		var P,
			O,
			saveGrade = ( function () {
				var _eLocal4 = B$(
					D$().m( function _eLocal5() {
						var _tLocal2, _nLocal2;
						return D$().w(
							function ( _eLocal6 ) {
								for (;;) {
									switch ( ( _eLocal6.p = _eLocal6.n ) ) {
										case 0:
											return (
												( _eLocal6.p = 0 ),
												( _eLocal6.n = 1 ),
												ApiFetchModule()( {
													path: '/ohmylms/v1/quiz/'
														.concat(
															quizId,
															'/report/'
														)
														.concat( attemptId ),
													method: 'POST',
													headers: {
														'Content-Type':
															'application/json',
													},
													body: JSON.stringify(
														attempt
													),
												} )
											 );
										case 1:
											if (
												'success' ===
												( ( ( _nullableValue ) =>
													_nullableValue === null ||
													_nullableValue === void 0 )(
													( _tLocal2 = _eLocal6.v )
												)
													? void 0
													: _tLocal2.status )
											) {
												window.location.reload();
											}
											_eLocal6.n = 3;
											break;
										case 2:
											_eLocal6.p = 2;
											_nLocal2 = _eLocal6.v;
											showRequestError( _nLocal2 );
										// Falls through to the shared async state cleanup.
										case 3:
											return _eLocal6.a( 2 );
									}
								}
							},
							_eLocal5,
							null,
							[ [ 0, 2 ] ]
						);
					} )
				);
				return function ( ..._args2 ) {
					return _eLocal4.apply( this, _args2 );
				};
			} )();
		return (
			<React.Fragment>
				<Controls.ContainerWP
					className={ 'ohmylms-quiz-report-details' }
				>
					<Controls.SpacerWP marginY={ 5 }>
						<Controls.FlexWP
							gap={ 3 }
							align={ 'center' }
							justify={ 'space-between' }
						>
							<Controls.FlexItemWP>
								<Controls.CardWP isBorderless={ ! 0 }>
									<Controls.FlexWP
										justify={ 'center' }
										align={ 'center' }
										gap={ 2 }
									>
										<Controls.SpacerWP
											padding={ 2 }
											marginBottom={ 0 }
										>
											<Controls.FlexWP
												justify={ 'center' }
												align={ 'center' }
												gap={ 2 }
											>
												<RouterLinks.Link
													to={ '/quiz-edit/'.concat(
														quizId
													) }
												>
													<Controls.FlexWP
														justify={ 'flex-start' }
														align={ 'center' }
														gap={ 1 }
													>
														<BackIcon />
														<Controls.TextWP
															size={ 15 }
														>
															{ ( 0, I18n.__ )(
																'Quiz /',
																'ohmylms'
															) }
														</Controls.TextWP>
													</Controls.FlexWP>
												</RouterLinks.Link>
												<RouterLinks.Link
													to={ '#' }
													onClick={ function () {
														navigate( -1 );
													} }
												>
													<Controls.TextWP
														size={ 15 }
													>
														{ ( 0, I18n.__ )(
															'Result /',
															'ohmylms'
														) }
													</Controls.TextWP>
												</RouterLinks.Link>
												<Controls.TextWP size={ 15 }>
													{ decodeHtml(
														attempt === null ||
															attempt ===
																void 0 ||
															null ===
																( e =
																	attempt.student ) ||
															void 0 === e
															? void 0
															: e.name
													) }
												</Controls.TextWP>
											</Controls.FlexWP>
										</Controls.SpacerWP>
									</Controls.FlexWP>
								</Controls.CardWP>
							</Controls.FlexItemWP>
							<Controls.FlexItemWP>
								<Controls.FlexWP
									gap={ 4 }
									justify={ 'flex-start' }
								>
									{ ( attempt === null || attempt === void 0
										? void 0
										: attempt.end_date ) && (
										<Controls.FlexItemWP>
											<Controls.FlexWP
												gap={ 2 }
												justify={ 'center' }
											>
												<DateIcon />
												<time
													style={ {
														fontSize: '15px',
														color: 'var(--wp-components-color-foreground)',
													} }
												>
													{ ( 0, I18n.__ )(
														'Date submitted',
														'ohmylms'
													) }
													{ ': ' }
													{ DateLibrary()(
														attempt === null ||
															attempt === void 0
															? void 0
															: attempt.end_date
													).format(
														'MMM D, YYYY h:mm A'
													) }
												</time>
											</Controls.FlexWP>
										</Controls.FlexItemWP>
									) }
									{ ( attempt === null ||
									attempt === void 0 ||
									null === ( t = attempt.report ) ||
									void 0 === t
										? void 0
										: t.status ) && (
										<Controls.FlexItemWP>
											<Controls.BadgeWP
												style={ {
													textTransform: 'capitalize',
												} }
												isBorderLess={ ! 0 }
												variant={ ( () => {
													if (
														'in-review' ===
														( attempt === null ||
														attempt === void 0 ||
														null ===
															( n =
																attempt.report ) ||
														void 0 === n
															? void 0
															: n.status )
													) {
														return 'warning';
													}
													if (
														'completed' ===
														( attempt === null ||
														attempt === void 0 ||
														null ===
															( r =
																attempt.report ) ||
														void 0 === r
															? void 0
															: r.status )
													) {
														return 'success';
													}
													return 'danger';
												} )() }
											>
												{ ( () => {
													if (
														'in-review' ===
														( attempt === null ||
														attempt === void 0 ||
														null ===
															( a =
																attempt.report ) ||
														void 0 === a
															? void 0
															: a.status )
													) {
														return 'Pending';
													}
													if (
														attempt === null ||
														attempt === void 0 ||
														null ===
															( o =
																attempt.report ) ||
														void 0 === o
													) {
														return void 0;
													}
													return o.status;
												} )() }
											</Controls.BadgeWP>
										</Controls.FlexItemWP>
									) }
								</Controls.FlexWP>
							</Controls.FlexItemWP>
							<Controls.ButtonWP
								onClick={ saveGrade }
								variant={ 'primary' }
							>
								{ ( 0, I18n.__ )( 'Upgrade Grade', 'ohmylms' ) }
							</Controls.ButtonWP>
						</Controls.FlexWP>
						<Controls.SpacerWP marginBottom={ 10 } />
						<div
							className={ 'ohmylms-quiz-report-content-wrapper' }
						>
							{ loading ? (
								<Controls.SkeletonWP
									active={ ! 0 }
									rows={ 10 }
								/>
							) : (
								<React.Fragment>
									<Controls.FlexWP
										gap={ 6 }
										align={ 'flex-start' }
										justify={ 'space-between' }
									>
										<Controls.FlexBlockWP
											style={ {
												flex: '5',
											} }
										>
											<QuizQuestionResults
												setData={ setAttempt }
												data={
													attempt === null ||
													attempt === void 0 ||
													null ===
														( i =
															attempt.report ) ||
													void 0 === i
														? void 0
														: i.questions
												}
												fetchData={ fetchReport }
											/>
										</Controls.FlexBlockWP>
										<Controls.FlexBlockWP
											style={ {
												flex: '2',
											} }
										>
											<QuizResultSummary
												data={ {
													score:
														( attempt === null ||
														attempt === void 0
															? void 0
															: attempt.score ) ||
														0,
													correct: ''
														.concat(
															( ( P =
																attempt ===
																	null ||
																attempt ===
																	void 0 ||
																null ===
																	( c =
																		attempt.report ) ||
																void 0 === c
																	? void 0
																	: c.questions ),
															( O = 0 ),
															P && P.length
																? ( P.forEach(
																		function (
																			_eLocal7
																		) {
																			if (
																				'correct' ===
																				( function (
																					_eLocal8
																				) {
																					var _tLocal3,
																						_nLocal3 =
																							_eLocal8.given_answer,
																						_rLocal =
																							_eLocal8.questions,
																						_aLocal =
																							_eLocal8 ===
																								null ||
																							_eLocal8 ===
																								void 0 ||
																							null ===
																								( _tLocal3 =
																									_eLocal8.settings ) ||
																							void 0 ===
																								_tLocal3
																								? void 0
																								: _tLocal3.type;
																					// Versioned reports carry the server's grading verdict.
																					if (
																						'boolean' ===
																						typeof ( _eLocal8 ===
																							null ||
																						_eLocal8 ===
																							void 0
																							? void 0
																							: _eLocal8.correct )
																					) {
																						return _eLocal8.correct
																							? 'correct'
																							: 'incorrect';
																					}
																					if (
																						'true-false' ===
																							_aLocal ||
																						'short-text' ===
																							_aLocal ||
																						'long-text' ===
																							_aLocal ||
																						'fill-in-the-blank' ===
																							_aLocal ||
																						'statement' ===
																							_aLocal
																					) {
																						return ( _eLocal8 ===
																							null ||
																						_eLocal8 ===
																							void 0
																							? void 0
																							: _eLocal8.achive_mark ) >
																							0
																							? 'correct'
																							: 'incorrect';
																					}
																					if (
																						'reorder' ===
																						_aLocal
																					) {
																						return R$(
																							_nLocal3,
																							_rLocal
																						)
																							? 'correct'
																							: 'incorrect';
																					}
																					if (
																						'matching' ===
																						_aLocal
																					) {
																						return j$(
																							_nLocal3
																						)
																							? 'correct'
																							: 'incorrect';
																					}
																					if (
																						! _nLocal3 ||
																						! Array.isArray(
																							_nLocal3
																						)
																					) {
																						return 'incorrect';
																					}
																					var _oLocal =
																							_rLocal
																								.filter(
																									function (
																										_eLocal9
																									) {
																										return (
																											'1' ===
																											_eLocal9.is_correct
																										);
																									}
																								)
																								.map(
																									function (
																										_eLocal0
																									) {
																										return _eLocal0.id;
																									}
																								),
																						_iLocal =
																							_nLocal3.every(
																								function (
																									_eLocal1
																								) {
																									return _oLocal.includes(
																										_eLocal1
																									);
																								}
																							);
																					return ( () => {
																						if (
																							'single-choice' ===
																							_eLocal8
																								.settings
																								.type
																						) {
																							if (
																								_iLocal &&
																								1 ===
																									_nLocal3.length &&
																								_oLocal.includes(
																									_nLocal3[ 0 ]
																								)
																							) {
																								return 'correct';
																							}
																							return 'incorrect';
																						}
																						return ( () => {
																							if (
																								'multiple-choice' ===
																								_eLocal8
																									.settings
																									.type
																							) {
																								if (
																									_iLocal &&
																									_nLocal3.length ===
																										_oLocal.length
																								) {
																									return 'correct';
																								}
																								return 'incorrect';
																							}
																							if (
																								'statement' ===
																									_eLocal8
																										.settings
																										.type &&
																								_iLocal &&
																								_nLocal3.length ===
																									_oLocal.length
																							) {
																								return 'correct';
																							}
																							return 'incorrect';
																						} )();
																					} )();
																				} )(
																					_eLocal7
																				)
																			) {
																				O++;
																			}
																		}
																	),
																	O )
																: O ),
															'/'
														)
														.concat(
															attempt === null ||
																attempt ===
																	void 0
																? void 0
																: attempt.total_question
														),
													isPass:
														Number(
															attempt === null ||
																attempt ===
																	void 0
																? void 0
																: attempt.score
														) >=
														Number(
															attempt === null ||
																attempt ===
																	void 0
																? void 0
																: attempt.passing_mark
														),
													status:
														attempt === null ||
														attempt === void 0 ||
														null ===
															( u =
																attempt.report ) ||
														void 0 === u
															? void 0
															: u.status,
													student_name: decodeHtml(
														attempt === null ||
															attempt ===
																void 0 ||
															null ===
																( s =
																	attempt.student ) ||
															void 0 === s
															? void 0
															: s.name
													),
													course_name: decodeHtml(
														attempt === null ||
															attempt ===
																void 0 ||
															null ===
																( d =
																	attempt.course ) ||
															void 0 === d
															? void 0
															: d.name
													),
												} }
											/>
										</Controls.FlexBlockWP>
									</Controls.FlexWP>
								</React.Fragment>
							) }
						</div>
					</Controls.SpacerWP>
				</Controls.ContainerWP>
			</React.Fragment>
		);
	};
}
