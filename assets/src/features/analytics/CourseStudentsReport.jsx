import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createCourseStudentsReport( readRuntime ) {
	return function CourseStudentsReport( props ) {
		const {
			Cm,
			D: Buttons,
			Ge,
			I: Controls,
			React,
			T: StoreModule,
			UU: StudentReminderDialog,
			ZU: AnalyticsDateFilter,
			aN,
			aq,
			b: I18n,
			df: EmptyIcon,
			eq: ReportStudentCell,
			f: Router,
			g: ReactHooks,
			kt,
			l,
			lN,
			oq,
			sN: TableModule,
			tq,
			uf: EmptyState,
			vn,
			y: WordPressData,
			z: Notifications,
		} = readRuntime();
		var t = true,
			n = props.students,
			r = ( 0, Router.g )().id,
			a = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			o = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getNotificationMessage();
			}, [] ),
			i = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getNotificationStatus();
			}, [] ),
			c = oq( ( 0, ReactHooks.useState )( n || [] ), 2 ),
			u = c[ 0 ],
			s = c[ 1 ],
			d = oq( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			m = d[ 0 ],
			p = d[ 1 ],
			v = oq( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			h = v[ 0 ],
			_ = v[ 1 ],
			w = oq( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			E = w[ 0 ],
			S = w[ 1 ],
			R = oq( ( 0, ReactHooks.useState )( '' ), 2 ),
			x = R[ 0 ],
			C = R[ 1 ],
			P = oq( ( 0, ReactHooks.useState )( 1 ), 2 ),
			O = ( P[ 0 ], P[ 1 ] ),
			k = oq( ( 0, ReactHooks.useState )( '' ), 2 ),
			j = k[ 0 ],
			A = k[ 1 ],
			M = oq( ( 0, ReactHooks.useState )( 'all' ), 2 ),
			F = M[ 0 ],
			N = M[ 1 ],
			W = oq( ( 0, ReactHooks.useState )( '' ), 2 ),
			B = W[ 0 ],
			V = W[ 1 ],
			H = oq( ( 0, ReactHooks.useState )( '' ), 2 ),
			G = H[ 0 ],
			U = H[ 1 ],
			q = oq( ( 0, ReactHooks.useState )( '' ), 2 ),
			Y = q[ 0 ],
			Q = q[ 1 ],
			Z = oq( ( 0, ReactHooks.useState )( null ), 2 ),
			$ = Z[ 0 ],
			K = Z[ 1 ],
			J = oq( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			X = J[ 0 ],
			ee = J[ 1 ],
			te = ( 0, Notifications.A )(),
			ne = te.openNotificationWithIcon,
			re = te.contextHolder,
			ae = oq( ( 0, ReactHooks.useState )( null ), 2 ),
			oe = ae[ 0 ],
			ie = ae[ 1 ],
			le = [
				{
					title: 'Student Name',
					dataIndex: 'student-name',
					key: 'student-name',
					className: 'col-student-name',
					sorter: ! 0,
					render( e, _tLocal ) {
						var _nLocal = oe === _tLocal.id;
						return (
							<ReportStudentCell
								studentData={ _tLocal }
								isHover={ _nLocal }
							/>
						);
					},
				},
				{
					title: 'Skipped Quizzes',
					dataIndex: 'skipped_quizzes',
					key: 'skipped_quizzes',
					className: 'col-skipped-quizzes',
					render( e ) {
						return (
							<React.Fragment>
								{ e.length > 0
									? e.map( function ( _eLocal, _tLocal2 ) {
											return (
												<kt.A key={ _tLocal2 }>
													{ Ge(
														_eLocal === null ||
															_eLocal === void 0
															? void 0
															: _eLocal.name
													) }
												</kt.A>
											);
										} )
									: '--' }
							</React.Fragment>
						);
					},
				},
				{
					title: 'Skipped Assignment ',
					dataIndex: 'skipped_assignments',
					key: 'skipped_assignments',
					className: 'col-skipped-assignment',
					render( e ) {
						return (
							<React.Fragment>
								{ e.length > 0
									? e.map( function ( _eLocal2, _tLocal3 ) {
											return (
												<kt.A key={ _tLocal3 }>
													{ Ge(
														_eLocal2 === null ||
															_eLocal2 === void 0
															? void 0
															: _eLocal2.name
													) }
												</kt.A>
											);
										} )
									: '--' }
							</React.Fragment>
						);
					},
				},
				{
					title: 'Completed All? ',
					dataIndex: 'completion_rate',
					key: 'completion_rate',
					className: 'col-completed-all',
					render( e ) {
						return (
							<React.Fragment>
								<div
									className={ 'course-status-wrapper '
										.concat(
											Number( e ) === 100
												? 'status-completed'
												: 'status-in-progress',
											' '
										)
										.concat(
											Number( e ) >= 50
												? 'progress-green'
												: ''
										) }
								>
									<kt.A>
										{ Number( e ) === 100
											? ( 0, I18n.__ )( 'Yes', 'ohmylms' )
											: ( 0, I18n.__ )(
													'No',
													'ohmylms'
												) }
									</kt.A>
									{ ' ' }
									<span
										className={ 'status-progressbar' }
										data-percent={ ''.concat( e ) }
									>
										<span
											className={
												'status-progressbar-inner'
											}
											style={ {
												width: 'calc('.concat(
													e,
													'% + 2px)'
												),
											} }
										/>
										<span
											className={
												'progressbar-percentage'
											}
										>
											{ ''.concat( e ) }
											{ '%' }
										</span>
									</span>
								</div>
							</React.Fragment>
						);
					},
				},
				{
					title: 'Enrolled Date',
					dataIndex: 'start_date',
					key: 'start_date',
					className: 'col-enrolled-date',
					sorter: ! 0,
					render( e ) {
						return (
							<span>
								{ aN()( e ).format( 'MMMM DD, YYYY' ) || '-' }
							</span>
						);
					},
				},
				{
					title: 'Action',
					dataIndex: 'action',
					key: 'action',
					className: 'col-table-action',
					render( e, _tLocal4 ) {
						return (
							<Buttons.A
								className={ 'send-reminder' }
								onClick={ function () {
									return ce(
										_tLocal4 === null || _tLocal4 === void 0
											? void 0
											: _tLocal4.email,
										_tLocal4 === null || _tLocal4 === void 0
											? void 0
											: _tLocal4.student_id
									);
								} }
								disabled={
									Number(
										_tLocal4 === null || _tLocal4 === void 0
											? void 0
											: _tLocal4.completion_rate
									) === 100
								}
								variant={ 'secondary' }
							>
								{ ( 0, I18n.__ )( 'Send Reminder', 'ohmylms' ) }
							</Buttons.A>
						);
					},
				},
			],
			ce = ( 0, ReactHooks.useCallback )( function ( e, _nLocal2 ) {
				V( e );
				S( ! 0 );
				K( _nLocal2 );
			}, [] ),
			ue = ( 0, ReactHooks.useCallback )(
				aq(
					tq().m( function e() {
						var _tLocal5, _nLocal3;
						return tq().w(
							function ( _eLocal3 ) {
								for (;;) {
									switch ( ( _eLocal3.p = _eLocal3.n ) ) {
										case 0:
											if ( r && $ && B && G ) {
												_eLocal3.n = 1;
												break;
											}
											return (
												ne(
													'error',
													'Subject and Email are required'
												),
												_eLocal3.a( 2 )
											 );
										case 1:
											return (
												( _tLocal5 = {
													student_id: $,
													course_id: r,
													email: B,
													subject: G,
													message:
														tinymce
															.get(
																'new-message'
															)
															.getContent() || Y,
												} ),
												( _eLocal3.p = 2 ),
												ee( ! 0 ),
												( _eLocal3.n = 3 ),
												l()( {
													path: '/ohmylms/v1/notification/course/'
														.concat(
															r,
															'/student/'
														)
														.concat( $ ),
													method: 'POST',
													headers: {
														'Content-Type':
															'application/json',
													},
													body: JSON.stringify(
														_tLocal5
													),
												} )
											 );
										case 3:
											if (
												( ( _nullableValue ) =>
													_nullableValue !== null &&
													_nullableValue !== void 0 )(
													( _nLocal3 = _eLocal3.v )
												) &&
												_nLocal3.status
											) {
												a.showNotification(
													( 0, I18n.__ )(
														'Email sent successfully',
														'ohmylms'
													),
													'success'
												);
											} else {
												a.showNotification(
													( 0, I18n.__ )(
														'Email not sent',
														'ohmylms'
													),
													'error'
												);
											}
											_eLocal3.n = 5;
											break;
										case 4:
											_eLocal3.p = 4;
											a.showNotification(
												( 0, I18n.__ )(
													'Error sending email',
													'ohmylms'
												),
												'error'
											);
										// Falls through to the shared async state cleanup.
										case 5:
											return (
												( _eLocal3.p = 5 ),
												ee( ! 1 ),
												S( ! 1 ),
												V( '' ),
												U( '' ),
												Q( '' ),
												_eLocal3.f( 5 )
											 );
										case 6:
											return _eLocal3.a( 2 );
									}
								}
							},
							e,
							null,
							[ [ 2, 4, 5, 6 ] ]
						);
					} )
				),
				[ B, G, Y ]
			),
			se = ( 0, ReactHooks.useCallback )( function () {
				S( ! 1 );
				V( '' );
				U( '' );
				Q( '' );
			}, [] ),
			de = ( 0, ReactHooks.useMemo )( function () {
				return [
					{
						value: 'all',
						label: ( 0, I18n.__ )( 'All', 'ohmylms' ),
					},
					{
						value: 'completed',
						label: ( 0, I18n.__ )( 'Completed', 'ohmylms' ),
					},
					{
						value: 'not_completed',
						label: ( 0, I18n.__ )( 'In Progress', 'ohmylms' ),
					},
				];
			}, [] ),
			me = ( 0, ReactHooks.useCallback )( function ( e ) {
				C( e );
				O( 1 );
				p( ! 0 );
			}, [] ),
			pe = ( 0, ReactHooks.useCallback )( function ( e ) {
				A( e );
				O( 1 );
				p( ! 0 );
			}, [] ),
			fe = ( 0, ReactHooks.useCallback )( function ( e ) {
				N( e );
				O( 1 );
				p( ! 0 );
			}, [] ),
			ve = ( 0, ReactHooks.useCallback )(
				aq(
					tq().m( function e( ..._args ) {
						var _tLocal6,
							_nLocal4,
							_aLocal,
							_oLocal,
							_iLocal,
							_cLocal,
							_uLocal = _args;
						return tq().w(
							function ( _eLocal4 ) {
								for (;;) {
									switch ( ( _eLocal4.p = _eLocal4.n ) ) {
										case 0:
											return (
												( _tLocal6 =
													_uLocal.length > 0 &&
													void 0 !== _uLocal[ 0 ]
														? _uLocal[ 0 ]
														: 'date' ),
												( _nLocal4 =
													_uLocal.length > 1 &&
													void 0 !== _uLocal[ 1 ]
														? _uLocal[ 1 ]
														: 'DESC' ),
												_( ! 0 ),
												( _eLocal4.p = 1 ),
												( _oLocal = {
													sort_by: _nLocal4,
													filter: j || '',
													search: x || '',
													order: _tLocal6,
													completion_type: F,
													data_type: 'student',
												} ),
												'string' !== typeof j &&
													( ( _oLocal.filter =
														'custom' ),
													( _oLocal.start_date = aN()(
														j[ 0 ]
													).format( 'YYYY-MM-DD' ) ),
													( _oLocal.end_date = aN()(
														j[ 1 ]
													).format(
														'YYYY-MM-DD'
													) ) ),
												( _eLocal4.n = 2 ),
												l()( {
													path: ( 0,
													lN.addQueryArgs )(
														'/ohmylms/v1/analytics/course/'.concat(
															r
														),
														_oLocal
													),
													method: 'GET',
													headers: {
														'Content-Type':
															'application/json',
													},
												} )
											 );
										case 2:
											if ( ! (
												( ( _nullableValue2 ) =>
													_nullableValue2 !== null &&
													_nullableValue2 !==
														void 0 )(
													( _iLocal = _eLocal4.v )
												) &&
												null !==
													( _aLocal =
														_iLocal.students ) &&
												void 0 !== _aLocal &&
												_aLocal.errors
											) ) {
												s(
													_iLocal === null ||
														_iLocal === void 0
														? void 0
														: _iLocal.students
												);
											}
											_eLocal4.n = 4;
											break;
										case 3:
											_eLocal4.p = 3;
											_cLocal = _eLocal4.v;
											showRequestError(
												'Error fetching data:',
												_cLocal
											);
										// Falls through to the shared async state cleanup.
										case 4:
											return (
												( _eLocal4.p = 4 ),
												_( ! 1 ),
												_eLocal4.f( 4 )
											 );
										case 5:
											return _eLocal4.a( 2 );
									}
								}
							},
							e,
							null,
							[ [ 1, 3, 4, 5 ] ]
						);
					} )
				),
				[ r, j, x, F ]
			);
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					var e = ! 0;
					return (
						e && m && ve(),
						function () {
							e = ! 1;
						}
					 );
				},
				[ j, x, r, F ]
			),
			( 0, ReactHooks.useEffect )(
				function () {
					if ( n && ! m ) {
						{
							s( n );
							p( ! 0 );
						}
					}
				},
				[ n ]
			),
			( 0, ReactHooks.useEffect )(
				function () {
					if ( ! h && o ) {
						ne( i, o );
					}
				},
				[ o ]
			),
			(
				<React.Fragment>
					{ re }
					<div className={ 'ohmylms-course-report-table-wrapper' }>
						<Controls.FlexWP gap={ 4 } justify={ 'flex-start' }>
							<Controls.FlexItemWP>
								<Cm
									placeholder={ ( 0, I18n.__ )(
										'Search',
										'ohmylms'
									) }
									onChange={ me }
									className={ 'ohmylms-filter-report-search' }
								/>
							</Controls.FlexItemWP>
							<Controls.FlexItemWP>
								<AnalyticsDateFilter
									placeholder={ ( 0, I18n.__ )(
										'Filter By Days',
										'ohmylms'
									) }
									className={
										'ohmylms-filter-report-by-days'
									}
									popupClassName={
										'ohmylms-custom-daterange'
									}
									onChange={ function ( e ) {
										if ( 'custom_range' !== e ) {
											pe( e );
										}
									} }
									onRangeChange={ pe }
								/>
							</Controls.FlexItemWP>
							<Controls.FlexItemWP>
								<vn.A
									placeholder={ ( 0, I18n.__ )(
										'Status',
										'ohmylms'
									) }
									className={ 'ohmylms-filter-report-status' }
									onChange={ fe }
									value={ F }
									options={ de }
								/>
							</Controls.FlexItemWP>
						</Controls.FlexWP>
						<Controls.SpacerWP marginBottom={ 5 } />

						<TableModule.A
							rowKey={ 'student_id' }
							columns={ le }
							dataSource={ u !== null && u !== void 0 ? u : [] }
							pagination={ ! 1 }
							loading={ h }
							scroll={ {
								x: 'max-content',
							} }
							onMouseEnterOnRow={ function ( e ) {
								return ie(
									e === null || e === void 0 ? void 0 : e.id
								);
							} }
							onMouseLeaveOnRow={ function () {
								return ie( null );
							} }
							locale={ {
								emptyText: (
									<EmptyState
										icon={ <EmptyIcon /> }
										title={ ( 0, I18n.__ )(
											'Things are quiet for now',
											'ohmylms'
										) }
										description={ ( 0, I18n.__ )(
											'Once learners start engaging, you’ll see their actions and progress right here.',
											'ohmylms'
										) }
									/>
								),
							} }
						/>
						{ E && t && (
							<StudentReminderDialog
								isOpen={ E }
								handleCancel={ se }
								handleOk={ ue }
								email={ B }
								isEmailDisabled={ ! 0 }
								onSubjectChange={ U }
								subject={ G }
								onEmailBodyChange={ Q }
								emailBody={ Y }
								loading={ X }
							/>
						) }
					</div>
				</React.Fragment>
			 )
		 );
	};
}
