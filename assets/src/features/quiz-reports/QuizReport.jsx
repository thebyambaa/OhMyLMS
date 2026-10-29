import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { Table } from '../../shared/table';
export function createQuizReport( readRuntime ) {
	return function QuizReport() {
		const {
			Cm: SearchInput,
			HG: useAdminScreen,
			I: Controls,
			JZ,
			KZ,
			QZ: BackIcon,
			React,
			ZZ,
			b: I18n,
			df: EmptyIcon,
			f: Router,
			fN: Pagination,
			g: ReactHooks,
			l: ApiFetchModule,
			sn: DateLibrary,
			uf: EmptyState,
			v: RouterLinks,
		} = readRuntime();
		useAdminScreen( 'ohmylms', 'quizzes' );
		var e = JZ( ( 0, ReactHooks.useState )( '' ), 2 ),
			search = e[ 0 ],
			setSearch = e[ 1 ],
			r = JZ( ( 0, ReactHooks.useState )( 1 ), 2 ),
			page = r[ 0 ],
			setPage = r[ 1 ],
			i = JZ( ( 0, ReactHooks.useState )( [] ), 2 ),
			submissions = i[ 0 ],
			setSubmissions = i[ 1 ],
			s = JZ( ( 0, ReactHooks.useState )( ! 0 ), 2 ),
			loading = s[ 0 ],
			setLoading = s[ 1 ],
			perPage = JZ( ( 0, ReactHooks.useState )( 10 ), 1 )[ 0 ],
			navigate = ( 0, Router.Zp )(),
			y = JZ( ( 0, ReactHooks.useState )( 0 ), 2 ),
			passingMark = y[ 0 ],
			setPassingMark = y[ 1 ],
			E = JZ( ( 0, ReactHooks.useState )( 0 ), 2 ),
			setTotalMarks = ( E[ 0 ], E[ 1 ] ),
			quizId = ( 0, Router.g )().id;
		( 0, ReactHooks.useEffect )(
			function () {
				var _eLocal = ( function () {
					var _eLocal2,
						t =
							( ( _eLocal2 = ZZ().m( function _eLocal3() {
								var _tLocal, n, _rLocal;
								return ZZ().w(
									function ( _eLocal4 ) {
										for (;;) {
											switch (
												( _eLocal4.p = _eLocal4.n )
											) {
												case 0:
													return (
														( _eLocal4.p = 0 ),
														setLoading( ! 0 ),
														( _eLocal4.n = 1 ),
														ApiFetchModule()( {
															path: '/ohmylms/v1/quiz/'.concat(
																quizId,
																'/report'
															),
															method: 'GET',
															headers: {
																'Content-Type':
																	'application/json',
															},
														} )
													 );
												case 1:
													if ( ( n = _eLocal4.v ) ) {
														_eLocal4.n = 2;
														break;
													}
													n = [];
												// Falls through to the shared async state cleanup.
												case 2:
													setSubmissions(
														( ( (
															_nullableValue
														) =>
															_nullableValue ===
																null ||
															_nullableValue ===
																void 0 )(
															( _tLocal = n )
														)
															? void 0
															: _tLocal.report ) ||
															[]
													);
													setPassingMark(
														Number(
															_tLocal === null ||
																_tLocal ===
																	void 0
																? void 0
																: _tLocal.passing_mark
														) || 0
													);
													setTotalMarks(
														Number(
															_tLocal === null ||
																_tLocal ===
																	void 0
																? void 0
																: _tLocal.question_total_marks
														) || 0
													);
													_eLocal4.n = 4;
													break;
												case 3:
													_eLocal4.p = 3;
													_rLocal = _eLocal4.v;
													showRequestError( _rLocal );
												// Falls through to the shared async state cleanup.
												case 4:
													return (
														( _eLocal4.p = 4 ),
														setLoading( ! 1 ),
														_eLocal4.f( 4 )
													 );
												case 5:
													return _eLocal4.a( 2 );
											}
										}
									},
									_eLocal3,
									null,
									[ [ 0, 3, 4, 5 ] ]
								);
							} ) ),
							function ( ..._args ) {
								var _tLocal2 = this,
									n = _args;
								return new Promise( function ( _rLocal2, a ) {
									var o = _eLocal2.apply( _tLocal2, n );
									function _iLocal( _eLocal5 ) {
										KZ(
											o,
											_rLocal2,
											a,
											_iLocal,
											l,
											'next',
											_eLocal5
										);
									}
									function l( _eLocal6 ) {
										KZ(
											o,
											_rLocal2,
											a,
											_iLocal,
											l,
											'throw',
											_eLocal6
										);
									}
									_iLocal( void 0 );
								} );
							} );
					return function ( ..._args2 ) {
						return t.apply( this, _args2 );
					};
				} )();
				_eLocal();
			},
			[ quizId ]
		);
		const onPageChange = ( 0, ReactHooks.useCallback )( function (
				_eLocal7
			) {
				setPage( _eLocal7 );
			}, [] ),
			filteredSubmissions = submissions.filter( function ( _eLocal8 ) {
				return Object.values( _eLocal8 ).some( function ( _eLocal9 ) {
					return String( _eLocal9 ).toLowerCase().includes( search );
				} );
			} ),
			columns = [
				{
					title: 'Name',
					dataIndex: 'student_name',
					key: 'student_name',
				},
				{
					title: 'Email',
					dataIndex: 'student_email',
					key: 'student_email',
				},
				{
					title: 'Date Submitted',
					dataIndex: 'end_date',
					key: 'end_date',
					render( _eLocal0 ) {
						return DateLibrary()( _eLocal0 ).format(
							'MMM D, YYYY h:mm A'
						);
					},
				},
				{
					title: 'Score',
					dataIndex: 'score',
					key: 'score',
					render( _eLocal1, t ) {
						return (
							<React.Fragment>{ t.total_marks }</React.Fragment>
						);
					},
				},
				{
					title: 'Result',
					dataIndex: 'result',
					key: 'result',
					render( _eLocal10, t ) {
						var n =
							Number(
								t === null || t === void 0
									? void 0
									: t.total_marks
							) >= Number( passingMark );
						return (
							<Controls.BadgeWP
								variant={ ( () => {
									if (
										'in-review' ===
										( t === null || t === void 0
											? void 0
											: t.status )
									) {
										return 'warning';
									}
									if ( n ) {
										return 'success';
									}
									return 'danger';
								} )() }
							>
								{ ( () => {
									if (
										'in-review' ===
										( t === null || t === void 0
											? void 0
											: t.status )
									) {
										return ( 0, I18n.__ )(
											'Pending',
											'ohmylms'
										);
									}
									if ( n ) {
										return ( 0, I18n.__ )(
											'Pass',
											'ohmylms'
										);
									}
									return ( 0, I18n.__ )( 'Fail', 'ohmylms' );
								} )() }
							</Controls.BadgeWP>
						);
					},
				},
				{
					title: '',
					key: 'action',
					render( _eLocal11, t ) {
						return (
							<Controls.ButtonWP
								variant={ 'primary' }
								onClick={ function () {
									return (
										( _eLocal12 =
											t === null || t === void 0
												? void 0
												: t.quiz_attempt_id ),
										void navigate(
											'grade-quiz/'.concat( _eLocal12 )
										)
									 );
									var _eLocal12;
								} }
							>
								{ ( 0, I18n.__ )( 'Grade Quiz', 'ohmylms' ) }
							</Controls.ButtonWP>
						);
					},
				},
			];
		return (
			<Controls.ContainerWP>
				<Controls.SpacerWP marginY={ 5 }>
					<div>
						<Controls.FlexWP
							align={ 'center' }
							justify={ 'space-between' }
							gap={ 2 }
						>
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
											<RouterLinks.Link to={ '/quizzes' }>
												<Controls.FlexWP
													justify={ 'flex-start' }
													align={ 'center' }
													gap={ 1 }
												>
													<BackIcon />
													{ ( 0, I18n.__ )(
														'Quiz /',
														'ohmylms'
													) }
												</Controls.FlexWP>
											</RouterLinks.Link>
											<Controls.TextWP as={ 'span' }>
												{ ( 0, I18n.__ )(
													'Result',
													'ohmylms'
												) }
											</Controls.TextWP>
										</Controls.FlexWP>
									</Controls.SpacerWP>
								</Controls.FlexWP>
							</Controls.CardWP>
							<Controls.CardWP isBorderless={ ! 0 }>
								<Controls.SpacerWP
									padding={ 2 }
									marginBottom={ 0 }
								>
									<SearchInput
										placeholder={ ( 0, I18n.__ )(
											'Search Submission',
											'ohmylms'
										) }
										onChange={ function ( _eLocal13 ) {
											setSearch(
												_eLocal13.toLowerCase()
											);
											setPage( 1 );
										} }
									/>
								</Controls.SpacerWP>
							</Controls.CardWP>
						</Controls.FlexWP>
						<Controls.CardWP isBorderless={ ! 0 }>
							<Controls.SpacerWP padding={ 5 } marginTop={ 4 }>
								<Table
									columns={ columns }
									rowKey={ 'quiz_attempt_id' }
									dataSource={ filteredSubmissions.slice(
										( page - 1 ) * perPage,
										page * perPage
									) }
									loading={ loading }
									pagination={ ! 1 }
									locale={ {
										emptyText: (
											<EmptyState
												icon={ <EmptyIcon /> }
												title={ ( 0, I18n.__ )(
													'No submission yet!',
													'ohmylms'
												) }
											/>
										),
									} }
								/>
								{ filteredSubmissions.length > perPage && (
									<Pagination
										total={ filteredSubmissions.length }
										currentPage={ page }
										onPageChange={ onPageChange }
										perPage={ perPage }
									/>
								) }
							</Controls.SpacerWP>
						</Controls.CardWP>
					</div>
				</Controls.SpacerWP>
			</Controls.ContainerWP>
		);
	};
}
