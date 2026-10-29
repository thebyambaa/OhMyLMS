import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { CourseGradebook } from '../courses/CourseGradebook';
import { useNotifications } from '../../shared/notices';
import { Avatar } from '../../shared/avatar';
import * as Controls from '../../shared/controls';
import { highlightAdminMenu } from '../../shared/utils';
import { EmptyState, NoDataIllustration, PlusIcon } from '../../shared/media';
export function createCourseStudents( readRuntime ) {
	return function CourseStudents() {
		const {
			Cm,
			He,
			Ie,
			JU,
			React,
			T: StoreModule,
			b: I18n,
			dee,
			f: Router,
			fN,
			fee,
			g: ReactHooks,
			iee,
			l,
			lN,
			oK,
			sN,
			sn,
			uee,
			v,
			vee,
			vn,
			y: WordPressData,
			zn,
		} = readRuntime();
		const [ gradebookOpen, setGradebookOpen ] =
			ReactHooks.useState( false );
		highlightAdminMenu( 'ohmylms', 'courses' );
		var e = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			t = ( 0, Router.Zp )(),
			n = useNotifications(),
			openNotificationWithIcon = n.openNotificationWithIcon,
			contextHolder = n.contextHolder,
			o = true,
			i = ( 0, Router.g )().id,
			c = vee( ( 0, ReactHooks.useState )( ! 0 ), 2 ),
			u = c[ 0 ],
			s = c[ 1 ],
			d = vee( ( 0, ReactHooks.useState )( [] ), 2 ),
			m = d[ 0 ],
			p = d[ 1 ],
			h = vee( ( 0, ReactHooks.useState )( '' ), 2 ),
			_ = h[ 0 ],
			w = h[ 1 ],
			E = vee( ( 0, ReactHooks.useState )( 1 ), 2 ),
			S = E[ 0 ],
			R = E[ 1 ],
			x = vee( ( 0, ReactHooks.useState )( 1 ), 2 ),
			C = x[ 0 ],
			P = x[ 1 ],
			O = vee( ( 0, ReactHooks.useState )( 5 ), 2 ),
			k = O[ 0 ],
			j = ( O[ 1 ], vee( ( 0, ReactHooks.useState )( '' ), 2 ) ),
			A = j[ 0 ],
			M = j[ 1 ],
			F = vee( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			N = F[ 0 ],
			D = F[ 1 ],
			W = vee( ( 0, ReactHooks.useState )( '' ), 2 ),
			B = W[ 0 ],
			V = W[ 1 ],
			H = vee( ( 0, ReactHooks.useState )( 'name_asc' ), 2 ),
			G = H[ 0 ],
			U = H[ 1 ],
			q = vee( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			Y = q[ 0 ],
			Q = q[ 1 ],
			Z = vee( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			$ = Z[ 0 ],
			K = Z[ 1 ],
			J = vee( ( 0, ReactHooks.useState )( [] ), 2 ),
			X = J[ 0 ],
			ee = J[ 1 ],
			te = vee( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			ne = te[ 0 ],
			re = te[ 1 ],
			ae = ( 0, ReactHooks.useMemo )( function () {
				return [
					{
						label: ( 0, I18n.__ )( 'Name (A-Z)', 'ohmylms' ),
						value: 'name_asc',
					},
					{
						label: ( 0, I18n.__ )( 'Name (Z-A)', 'ohmylms' ),
						value: 'name_desc',
					},
					{
						label: ( 0, I18n.__ )(
							'Date Enrolled (Newest)',
							'ohmylms'
						),
						value: 'date_desc',
					},
					{
						label: ( 0, I18n.__ )(
							'Date Enrolled (Oldest)',
							'ohmylms'
						),
						value: 'date_asc',
					},
				];
			}, [] ),
			oe = [
				{
					key: 'student_name',
					title: ( 0, I18n.__ )( 'Student Name', 'ohmylms' ),
					dataIndex: 'student_name',
					className: 'student-name',
					render( _eLocal, _tLocal ) {
						return (
							<Controls.FlexWP gap={ 4 } justify={ 'flex-start' }>
								{ _tLocal !== null &&
								_tLocal !== void 0 &&
								_tLocal.student_img ? (
									<Avatar
										size={ 40 }
										src={
											_tLocal === null ||
											_tLocal === void 0
												? void 0
												: _tLocal.student_img
										}
									/>
								) : (
									<span className={ 'ohmylms-col-avatar' }>
										<JU />
									</span>
								) }
								<span className={ 'ohmylms-title-text' }>
									{ _tLocal === null || _tLocal === void 0
										? void 0
										: _tLocal.student_name }
								</span>
							</Controls.FlexWP>
						);
					},
				},
				{
					key: 'student_email',
					title: ( 0, I18n.__ )( 'Email', 'ohmylms' ),
					dataIndex: 'student_email',
					className: 'student-email',
				},
				{
					key: 'registration_date',
					title: ( 0, I18n.__ )( 'Registration Date', 'ohmylms' ),
					dataIndex: 'registration_date',
					className: 'reg-date',
					isExpandable: ! 0,
					render( _eLocal2 ) {
						return (
							<React.Fragment>
								{ sn()( _eLocal2 ).format( 'DD MMMM, YYYY' ) }
							</React.Fragment>
						);
					},
				},
				{
					key: 'action',
					title: 'Action',
					dataIndex: 'action',
					className: 'action',
					render( _eLocal3, _tLocal2 ) {
						return (
							<React.Fragment>
								<Controls.ButtonWP
									variant={ 'link' }
									icon={ React.createElement( zn, null ) }
									className={ 'ohmylms-delete-btn' }
									onClick={ function () {
										return ie(
											_tLocal2 === null ||
												_tLocal2 === void 0
												? void 0
												: _tLocal2.user_id
										);
									} }
								/>
							</React.Fragment>
						);
					},
				},
			],
			ie = ( 0, ReactHooks.useCallback )( function ( _eLocal4 ) {
				K( ! 0 );
				ee( [ _eLocal4 ] );
			}, [] ),
			le = ( 0, ReactHooks.useCallback )( function () {
				K( ! 1 );
			}, [] ),
			ce = ( 0, ReactHooks.useCallback )(
				fee(
					dee().m( function _eLocal5() {
						var _tLocal3, _nLocal;
						return dee().w(
							function ( _eLocal6 ) {
								for (;;) {
									switch ( ( _eLocal6.p = _eLocal6.n ) ) {
										case 0:
											return (
												( _eLocal6.p = 0 ),
												( _eLocal6.n = 1 ),
												l()( {
													path: '/ohmylms/v1/courses/'
														.concat( i, '/enroll/' )
														.concat( X[ 0 ] ),
													method: 'DELETE',
												} )
											 );
										case 1:
											if (
												( ( _nullableValue ) =>
													_nullableValue !== null &&
													_nullableValue !== void 0 )(
													( _tLocal3 = _eLocal6.v )
												) &&
												_tLocal3.success
											) {
												w(
													( 0, I18n.__ )(
														'Unenrolled Successfully',
														'ohmylms'
													)
												);
											} else {
												w(
													( 0, I18n.__ )(
														'Failed to unenroll',
														'ohmylms'
													)
												);
											}
											_eLocal6.n = 3;
											break;
										case 2:
											_eLocal6.p = 2;
											_nLocal = _eLocal6.v;
											showRequestError( _nLocal );
											w( _nLocal.message );
										// Falls through to the shared async state cleanup.
										case 3:
											return (
												( _eLocal6.p = 3 ),
												K( ! 1 ),
												de( {
													page: 1,
												} ),
												R( 1 ),
												_eLocal6.f( 3 )
											 );
										case 4:
											return _eLocal6.a( 2 );
									}
								}
							},
							_eLocal5,
							null,
							[ [ 0, 2, 3, 4 ] ]
						);
					} )
				),
				[ X ]
			),
			ue =
				( ( 0, ReactHooks.useMemo )(
					function () {
						return {
							total: C,
							current: S,
							perPage: k,
							onChange( _eLocal7 ) {
								return R( _eLocal7 );
							},
						};
					},
					[ C, S, k ]
				),
				( 0, ReactHooks.useCallback )( function ( _eLocal8 ) {
					U( _eLocal8 );
				}, [] ) ),
			se = ( 0, ReactHooks.useCallback )(
				function () {
					D( ! 0 );
				},
				[ o, e ]
			),
			de = ( function () {
				var _eLocal9 = fee(
					dee().m( function _eLocal0( ..._args ) {
						var _tLocal4,
							_nLocal2,
							r,
							a,
							_oLocal,
							_cLocal,
							_uLocal,
							_dLocal,
							f,
							_vLocal,
							g = _args;
						return dee().w(
							function ( _eLocal1 ) {
								for (;;) {
									switch ( ( _eLocal1.p = _eLocal1.n ) ) {
										case 0:
											return (
												( _tLocal4 =
													g.length > 0 &&
													void 0 !== g[ 0 ]
														? g[ 0 ]
														: {} ),
												( _eLocal1.p = 1 ),
												0 ===
												( m === null || m === void 0
													? void 0
													: m.length )
													? s( ! 0 )
													: Q( ! 0 ),
												( a = uee(
													{
														course_id: i,
														page: S,
														per_page: k,
														search: B,
														offset: ( S - 1 ) * k,
														order_by:
															G.split( '_' )[ 0 ],
														order: G.split(
															'_'
														)[ 1 ],
													},
													_tLocal4
												) ),
												( _eLocal1.n = 2 ),
												l()( {
													path: ( 0,
													lN.addQueryArgs )(
														'/ohmylms/v1/students',
														a
													),
													method: 'GET',
													parse: ! 1,
													headers: {
														'Content-Type':
															'application/json',
													},
												} )
											 );
										case 2:
											if ( ( f = _eLocal1.v ) ) {
												_eLocal1.n = 3;
												break;
											}
											f = [];
										// Falls through to the shared async state cleanup.
										case 3:
											return (
												( _oLocal = f ),
												( _eLocal1.n = 4 ),
												_oLocal.json()
											 );
										case 4:
											_cLocal = _eLocal1.v;
											_uLocal =
												_oLocal === null ||
												_oLocal === void 0 ||
												null ===
													( _nLocal2 =
														_oLocal.headers ) ||
												void 0 === _nLocal2
													? void 0
													: _nLocal2.get(
															'X-WP-Total'
														);
											_dLocal =
												_oLocal === null ||
												_oLocal === void 0 ||
												null ===
													( r = _oLocal.headers ) ||
												void 0 === r
													? void 0
													: r.get(
															'X-WP-Course-Name'
														);
											M( _dLocal );
											P( _uLocal );
											p( _cLocal || [] );
											_eLocal1.n = 6;
											break;
										case 5:
											_eLocal1.p = 5;
											_vLocal = _eLocal1.v;
											showRequestError( _vLocal );
										// Falls through to the shared async state cleanup.
										case 6:
											return (
												( _eLocal1.p = 6 ),
												s( ! 1 ),
												Q( ! 1 ),
												_eLocal1.f( 6 )
											 );
										case 7:
											return _eLocal1.a( 2 );
									}
								}
							},
							_eLocal0,
							null,
							[ [ 1, 5, 6, 7 ] ]
						);
					} )
				);
				return function ( ..._args2 ) {
					return _eLocal9.apply( this, _args2 );
				};
			} )(),
			me = ( 0, ReactHooks.useCallback )( function ( _eLocal10 ) {
				V( _eLocal10 );
				R( 1 );
			}, [] );
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					if ( i ) {
						de();
					} else {
						t( '/courses' );
					}
				},
				[ i, S, k, B, G ]
			),
			( 0, ReactHooks.useEffect )(
				function () {
					if ( ! u && _ ) {
						if ( _.includes( 'wrong' ) ) {
							openNotificationWithIcon( 'error', _ );
						} else {
							openNotificationWithIcon( 'success', _ );
						}
					}
				},
				[ _ ]
			),
			(
				<React.Fragment>
					<Controls.ContainerWP>
						{ contextHolder }
						{ gradebookOpen && (
							<CourseGradebook
								courseId={ Number( i ) }
								onClose={ () => {
									setGradebookOpen( false );
									de();
								} }
							/>
						) }
						<Controls.SpacerWP marginY={ 4 }>
							<Controls.FlexWP
								gap={ 4 }
								justify={ 'space-between' }
							>
								<Controls.CardWP>
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
												<v.Link to={ '/courses' }>
													<Controls.FlexWP
														justify={ 'flex-start' }
														align={ 'center' }
														gap={ 1 }
													>
														{ React.createElement(
															oK,
															null
														) }
														{ ( 0, I18n.__ )(
															'Courses /',
															'ohmylms'
														) }
													</Controls.FlexWP>
												</v.Link>
												<Controls.TextWP as={ 'span' }>
													{ A }
												</Controls.TextWP>
											</Controls.FlexWP>
										</Controls.SpacerWP>
									</Controls.FlexWP>
								</Controls.CardWP>
								<Controls.ButtonWP
									variant="secondary"
									onClick={ () => setGradebookOpen( true ) }
								>
									{ ( 0, I18n.__ )(
										'Gradebook / Add class',
										'ohmylms'
									) }
								</Controls.ButtonWP>
								<Controls.ButtonWP
									variant={ 'primary' }
									onClick={ se }
									title={ ( 0, I18n.__ )(
										'Enroll a Student',
										'ohmylms'
									) }
								>
									{ React.createElement( PlusIcon, null ) }
									{ ' ' }
									{ ( 0, I18n.__ )(
										'Enroll a Student',
										'ohmylms'
									) }
								</Controls.ButtonWP>
							</Controls.FlexWP>
							<Controls.CardWP
								className={ 'ohmylms-data-table-wrapper' }
							>
								<Controls.SpacerWP
									padding={ 5 }
									marginTop={ 4 }
								>
									<Controls.FlexWP
										gap={ 4 }
										justify={ 'space-between' }
									>
										<Cm
											placeholder={ ( 0, I18n.__ )(
												'Search Students',
												'ohmylms'
											) }
											onChange={ me }
										/>
										<vn.A
											placeholder={ ( 0, I18n.__ )(
												'Sort',
												'ohmylms'
											) }
											className={
												'ohmylms-selectbox-filter'
											}
											onChange={ ue }
											value={ G }
											options={ ae }
										/>
									</Controls.FlexWP>
									<Controls.SpacerWP marginBottom={ 4 } />
									<sN.A
										rowKey={ 'user_id' }
										columns={ oe }
										dataSource={ m || [] }
										pagination={ ! 1 }
										loading={ u || Y }
										scroll={ {
											x: 'max-content',
										} }
										locale={ {
											emptyText: React.createElement(
												EmptyState,
												{
													icon: React.createElement(
														NoDataIllustration,
														null
													),
													title: ( 0, I18n.__ )(
														'No Students yet!',
														'ohmylms'
													),
													description: ( 0, I18n.__ )(
														'Create your first student and it’ll show up here as soon as you hit publish.',
														'ohmylms'
													),
												}
											),
										} }
									/>
									{ ! u &&
										! Y &&
										Number( C ) > k &&
										React.createElement( fN, {
											total: C,
											currentPage: S,
											onPageChange( _eLocal11 ) {
												return R( _eLocal11 );
											},
											perPage: k,
										} ) }
								</Controls.SpacerWP>
							</Controls.CardWP>
						</Controls.SpacerWP>
					</Controls.ContainerWP>
					{ $ && (
						<React.Fragment>
							<Ie
								title={ ( 0, I18n.__ )(
									'Unenroll Student',
									'ohmylms'
								) }
								description={ ( 0, I18n.__ )(
									'Are you sure you want to unenroll this student?',
									'ohmylms'
								) }
								onClose={ le }
								onDelete={ ce }
								isOpen={ $ }
							/>
						</React.Fragment>
					) }
					{ N &&
						React.createElement( iee, {
							isOpen: N,
							setIsOpen: D,
							courseId: i,
							setNotification: w,
							onEnrollment: de,
						} ) }
					{ ne && (
						<React.Fragment>
							<He.default isOpen={ ne } onClose={ re } />
						</React.Fragment>
					) }
				</React.Fragment>
			 )
		 );
	};
}
