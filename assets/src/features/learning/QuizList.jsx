/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { ImportButton } from '../content-hub/ImportButton';
import { useHubAction } from '../content-hub/useHubAction.js';
import { useNotifications } from '../../shared/notices';
import {
	ExportIcon,
	useAddQuiz,
	useExportQuiz,
} from '../content-hub/QuizActions';
import { EditIcon } from '../../shared/icons';
import * as Controls from '../../shared/controls';
import { decodeHtmlEntities } from '../../shared/utils';
import { EmptyState, NoDataIllustration, PageHeader } from '../../shared/media';
export function createQuizList( readRuntime ) {
	return function QuizList() {
		const {
			Ea,
			Ie,
			Ne,
			React,
			T: StoreModule,
			We,
			_Z,
			aY,
			b: I18n,
			bZ,
			f: Router,
			fN,
			fZ,
			g: ReactHooks,
			hN,
			hZ,
			l,
			pZ,
			q,
			sN,
			sZ: QuizNameCell,
			v,
			y: WordPressData,
			yZ,
		} = readRuntime();
		const addQuiz = useAddQuiz();
		// Ctrl+K opens `#/content-hub/quizzes?action=new`.
		useHubAction( { new: addQuiz.onClick } );
		// `openNotificationWithIcon` is declared further down; it is read when an export finishes.
		const quizExport = useExportQuiz( ( status, message ) =>
			openNotificationWithIcon( status, message )
		);
		var e = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			t = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
				return _eLocal( StoreModule.default ).getNotificationMessage();
			}, [] ),
			n = ( 0, WordPressData.useSelect )( function ( _eLocal2 ) {
				return _eLocal2( StoreModule.default ).getNotificationStatus();
			}, [] ),
			r = ( 0, WordPressData.useSelect )( function ( _eLocal3 ) {
				return _eLocal3( StoreModule.default ).getQuizzesPagination();
			}, [] ),
			a = ( r.totalQuizzes, r.totalPages, r.filteredQuizzes ),
			o = ( 0, WordPressData.useSelect )( function ( _eLocal4 ) {
				return _eLocal4( StoreModule.default ).getQuizzes();
			}, [] ),
			i = yZ( ( 0, ReactHooks.useState )( ! 0 ), 2 ),
			c = i[ 0 ],
			u = i[ 1 ],
			s = yZ( ( 0, ReactHooks.useState )( [] ), 2 ),
			d = s[ 0 ],
			m = s[ 1 ],
			p = yZ( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			h = p[ 0 ],
			_ = p[ 1 ],
			w = yZ( ( 0, ReactHooks.useState )( 1 ), 2 ),
			E = w[ 0 ],
			S = w[ 1 ],
			R = yZ( ( 0, ReactHooks.useState )( 5 ), 2 ),
			x = R[ 0 ],
			C = ( R[ 1 ], yZ( ( 0, ReactHooks.useState )( '' ), 2 ) ),
			P = C[ 0 ],
			O = C[ 1 ],
			k = yZ( ( 0, ReactHooks.useState )( '' ), 2 ),
			j = k[ 0 ],
			A = k[ 1 ],
			M = yZ( ( 0, ReactHooks.useState )( 'All' ), 2 ),
			F = M[ 0 ],
			N = M[ 1 ],
			D = yZ( ( 0, ReactHooks.useState )( null ), 2 ),
			W = D[ 0 ],
			B = D[ 1 ],
			L = yZ( ( 0, ReactHooks.useState )( null ), 2 ),
			V = L[ 0 ],
			H = L[ 1 ],
			G = yZ( ( 0, ReactHooks.useState )( [] ), 2 ),
			U = G[ 0 ],
			Y = G[ 1 ],
			Q = useNotifications(),
			openNotificationWithIcon = Q.openNotificationWithIcon,
			contextHolder = Q.contextHolder,
			K = ( 0, Router.Zp )(),
			J = ( 0, ReactHooks.useCallback )( function ( _eLocal5 ) {
				O( _eLocal5 );
				S( 1 );
			}, [] ),
			X = ( 0, ReactHooks.useCallback )( function ( _eLocal6 ) {
				A( _eLocal6 );
				S( 1 );
			}, [] ),
			ee = ( 0, ReactHooks.useCallback )( function ( _eLocal7 ) {
				N( _eLocal7 );
				S( 1 );
			}, [] ),
			te = ( 0, ReactHooks.useCallback )( function ( _eLocal8 ) {
				S( _eLocal8 );
				m( [] );
			}, [] ),
			ne = ( 0, ReactHooks.useCallback )(
				hZ(
					fZ().m( function _tLocal( ..._args ) {
						var _nLocal,
							_rLocal,
							_aLocal,
							_oLocal = _args;
						return fZ().w( function ( _tLocal2 ) {
							for (;;) {
								switch ( _tLocal2.n ) {
									case 0:
										_nLocal =
											_oLocal.length > 0 &&
											void 0 !== _oLocal[ 0 ]
												? _oLocal[ 0 ]
												: 'date';
										_rLocal =
											_oLocal.length > 1 &&
											void 0 !== _oLocal[ 1 ]
												? _oLocal[ 1 ]
												: 'DESC';
										u( ! 0 );
										_aLocal = {
											offset: 5 * ( E - 1 ),
											order: _rLocal,
											page: E,
											per_page: x,
											search: P,
											post_status: F,
											orderby: _nLocal,
											course_id: j,
										};
										e.fetchQuizzes( _aLocal ).finally(
											function () {
												u( ! 1 );
											}
										); // Falls through to the shared async state cleanup.
									// Falls through to the shared async state cleanup.
									case 1:
										return _tLocal2.a( 2 );
								}
							}
						}, _tLocal );
					} )
				),
				[ E, x, P, F, j ]
			),
			re = ( 0, ReactHooks.useCallback )( function ( _eLocal9 ) {
				_( ! 0 );
				B( _eLocal9 );
			}, [] ),
			ae = ( 0, ReactHooks.useCallback )( function () {
				_( ! 1 );
			}, [] ),
			oe = ( 0, ReactHooks.useCallback )(
				hZ(
					fZ().m( function _tLocal3() {
						return fZ().w( function ( _tLocal4 ) {
							for (;;) {
								switch ( _tLocal4.n ) {
									case 0:
										return (
											( _tLocal4.n = 1 ),
											e.handleBulkDelete( 'quiz', {
												quiz_ids: W ? [ W ] : d,
											} )
										 );
									case 1:
										ne();
										m( [] );
										S( 1 );
										B( null );
										_( ! 1 );
									// Falls through to the shared async state cleanup.
									case 2:
										return _tLocal4.a( 2 );
								}
							}
						}, _tLocal3 );
					} )
				),
				[ d, W, ne ]
			),
			ie = ( 0, ReactHooks.useCallback )(
				function ( _eLocal0, _tLocal5, _nLocal2 ) {
					var _rLocal2 =
							{
								number_of_submissions: 'number_of_submissions',
								quiz_name: 'title',
							}[ _nLocal2.field ] || _nLocal2.field,
						_aLocal2 =
							{
								ascend: 'ASC',
								descend: 'DESC',
							}[ _nLocal2.order ] || _nLocal2.order;
					S( 1 );
					ne( _rLocal2, _aLocal2 );
				},
				[ ne, S ]
			),
			le = ( 0, ReactHooks.useCallback )(
				hZ(
					fZ().m( function _eLocal1() {
						var _tLocal6;
						return fZ().w( function ( _eLocal10 ) {
							for (;;) {
								switch ( _eLocal10.n ) {
									case 0:
										return (
											( _eLocal10.n = 1 ),
											l()( {
												path: '/ohmylms/v1/courses',
											} )
										 );
									case 1:
										_tLocal6 = _eLocal10.v;
										Y( _tLocal6 );
									// Falls through to the shared async state cleanup.
									case 2:
										return _eLocal10.a( 2 );
								}
							}
						}, _eLocal1 );
					} )
				),
				[]
			),
			ce = ( 0, ReactHooks.useMemo )(
				function () {
					return [
						{
							label: ( 0, I18n.__ )( 'All Courses', 'ohmylms' ),
							value: '',
						},
					].concat(
						( function ( _eLocal11 ) {
							return (
								( function ( _eLocal12 ) {
									if ( Array.isArray( _eLocal12 ) ) {
										return _Z( _eLocal12 );
									}
								} )( _eLocal11 ) ||
								( function ( _eLocal13 ) {
									if (
										( 'undefined' !== typeof Symbol &&
											( ( _nullableValue ) =>
												_nullableValue !== null &&
												_nullableValue !== void 0 )(
												_eLocal13[ Symbol.iterator ]
											) ) ||
										( ( _nullableValue2 ) =>
											_nullableValue2 !== null &&
											_nullableValue2 !== void 0 )(
											_eLocal13[ '@@iterator' ]
										)
									) {
										return Array.from( _eLocal13 );
									}
								} )( _eLocal11 ) ||
								bZ( _eLocal11 ) ||
								( function () {
									throw new TypeError(
										'Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
									);
								} )()
							);
						} )(
							U.map( function ( _eLocal14 ) {
								return {
									label: decodeHtmlEntities(
										_eLocal14 === null ||
											_eLocal14 === void 0
											? void 0
											: _eLocal14.name
									),
									value:
										_eLocal14 === null ||
										_eLocal14 === void 0
											? void 0
											: _eLocal14.id,
								};
							} )
						)
					);
				},
				[ U ]
			),
			ue = ( 0, ReactHooks.useMemo )(
				function () {
					return [
						{
							label: ( 0, I18n.__ )( 'Delete', 'ohmylms' ),
							value: 'delete',
							action() {
								_( ! 0 );
							},
						},
					];
				},
				[ d ]
			),
			se = ( 0, ReactHooks.useMemo )( function () {
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
						value: 'draft',
						label: ( 0, I18n.__ )( 'Draft', 'ohmylms' ),
					},
				];
			}, [] ),
			de = ( 0, ReactHooks.useMemo )(
				function () {
					return {
						selectedRowKeys: d,
						onChange: m,
					};
				},
				[ d ]
			),
			me = [
				{
					title: ( 0, I18n.__ )( 'Quiz name', 'ohmylms' ),
					dataIndex: 'quiz_name',
					key: 'quiz_name',
					sorter: ! 0,
					width: '340px',
					render( _eLocal15, _tLocal7 ) {
						var _nLocal3 = V === _tLocal7.id;
						return (
							<QuizNameCell
								record={ _tLocal7 }
								isHover={ _nLocal3 }
							/>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Course name', 'ohmylms' ),
					dataIndex: 'courses',
					key: 'courses',
					render( _eLocal16 ) {
						return React.createElement( pZ, {
							items: [
								_eLocal16 === null || _eLocal16 === void 0
									? void 0
									: _eLocal16.course_name,
							],
							showAllOnHover: ! 0,
							maxVisible: 3,
						} );
					},
				},
				{
					title: ( 0, I18n.__ )( 'No. of Submissions', 'ohmylms' ),
					dataIndex: 'number_of_submissions',
					key: 'number_of_submissions',
					sorter: ! 0,
					render( _eLocal17, _tLocal9 ) {
						return (
							<Controls.BadgeWP
								variant={ 'secondary' }
								isBorderLess={ ! 0 }
							>
								<v.Link
									to={ '/quiz-report/'.concat(
										_tLocal9 === null || _tLocal9 === void 0
											? void 0
											: _tLocal9.id
									) }
								>
									{ _eLocal17 }{ ' ' }
								</v.Link>
							</Controls.BadgeWP>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Status', 'ohmylms' ),
					dataIndex: 'status',
					key: 'status',
					render( _eLocal18 ) {
						return (
							<Controls.BadgeWP
								isBorderLess={ ! 0 }
								variant={
									'publish' === _eLocal18
										? 'success'
										: 'secondary'
								}
								style={ {
									textTransform: 'capitalize',
								} }
							>
								{ ( () => {
									if ( 'publish' === _eLocal18 ) {
										return ( 0, I18n.__ )(
											'Published',
											'ohmylms'
										);
									}
									if ( 'future' === _eLocal18 ) {
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
					title: ( 0, I18n.__ )( 'Action', 'ohmylms' ),
					dataIndex: 'action',
					key: 'action',
					render( _eLocal19, _tLocal1 ) {
						return (
							<Controls.DropdownMenuWP
								controls={ [
									{
										title: ( 0, I18n.__ )(
											'Edit',
											'ohmylms'
										),
										key: 'edit',
										onClick() {
											return K(
												'/quiz-edit/'.concat(
													_tLocal1 === null ||
														_tLocal1 === void 0
														? void 0
														: _tLocal1.id
												)
											);
										},
										icon: (
											<span>
												<EditIcon />
											</span>
										),
									},
									{
										title: ( 0, I18n.__ )(
											'Export',
											'ohmylms'
										),
										key: 'export',
										onClick() {
											return quizExport.exportQuiz(
												_tLocal1 === null ||
													_tLocal1 === void 0
													? void 0
													: _tLocal1.id
											);
										},
										icon: (
											<span>
												<ExportIcon />
											</span>
										),
									},
									{
										title: ( 0, I18n.__ )(
											'Delete',
											'ohmylms'
										),
										key: 'delete',
										onClick() {
											return re(
												_tLocal1 === null ||
													_tLocal1 === void 0
													? void 0
													: _tLocal1.id
											);
										},
										icon: <We />,
									},
								] }
								icon={ <q.Icon icon={ Ne.A } /> }
							/>
						);
					},
				},
			];
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					var _eLocal20 = ! 0;
					return (
						_eLocal20 && ne(),
						function () {
							_eLocal20 = ! 1;
						}
					 );
				},
				[ E, x, P, e, F, j ]
			),
			( 0, ReactHooks.useEffect )( function () {
				var _eLocal21 = ! 0;
				return (
					_eLocal21 && le(),
					function () {
						_eLocal21 = ! 1;
					}
				 );
			}, [] ),
			( 0, ReactHooks.useEffect )(
				function () {
					if ( ! c && t ) {
						openNotificationWithIcon( n, t );
					}
				},
				[ t ]
			),
			(
				<React.Fragment>
					{ contextHolder }
					<Controls.ContainerWP>
						<PageHeader
							title={ ( 0, I18n.__ )( 'All Quizzes', 'ohmylms' ) }
							showAddButton={ ! 0 }
							addButtonConfig={ addQuiz }
						>
							<ImportButton target="quiz" />
						</PageHeader>
						<Ea
							isBorderless={ ! 0 }
							minHeight={ 'calc(100vh - 200px)' }
						>
							<Controls.SpacerWP padding={ 5 }>
								{ d.length > 0 ? (
									<React.Fragment>
										{ React.createElement( hN, {
											items: d,
											setItems: m,
											bulksActions: ue,
										} ) }
									</React.Fragment>
								) : (
									<React.Fragment>
										{ React.createElement( aY, {
											handleSearch: J,
											searchPlaceholder: ( 0, I18n.__ )(
												'Search Quiz',
												'ohmylms'
											),
											showFilterByDays: ! 1,
											showFilterByPriceType: ! 1,
											handleFilterByCategory: X,
											filterByCategory: j,
											categories: ce,
											categoryPrefix: ( 0, I18n.__ )(
												'Course',
												'ohmylms'
											),
											currentPage: E,
											totalItems: a,
											handleFilterByStatus: ee,
											filterByStatus: F,
											filterByStatusOptions: se,
											formateCategory: ! 1,
											className:
												'ohmylms-quiz-listing-filter-card',
										} ) }
									</React.Fragment>
								) }
								<sN.A
									rowKey={ 'id' }
									columns={ me }
									dataSource={ o || [] }
									rowSelection={ de }
									pagination={ ! 1 }
									loading={ c }
									onChange={ ie }
									onRowMouseEnter={ function ( _eLocal22 ) {
										return H(
											_eLocal22 === null ||
												_eLocal22 === void 0
												? void 0
												: _eLocal22.id
										);
									} }
									onRowMouseLeave={ function () {
										return H( null );
									} }
									className={ 'quiz-listing-table' }
									locale={ {
										emptyText: React.createElement(
											EmptyState,
											{
												icon: React.createElement(
													NoDataIllustration,
													null
												),
												title: ( 0, I18n.__ )(
													'No Quizzes yet!',
													'ohmylms'
												),
												description: ( 0, I18n.__ )(
													'Start building your first quiz and it’ll show up here as soon as you hit publish.',
													'ohmylms'
												),
											}
										),
									} }
								/>
								{ ! c &&
									Number( a ) > 5 &&
									React.createElement( fN, {
										total: a,
										currentPage: E,
										onPageChange: te,
										perPage: x,
									} ) }
							</Controls.SpacerWP>
						</Ea>
					</Controls.ContainerWP>
					{ h && (
						<React.Fragment>
							<Ie
								title={
									d.length > 1
										? ( 0, I18n.__ )(
												'Delete Quizzes',
												'ohmylms'
											)
										: ( 0, I18n.__ )(
												'Delete Quiz',
												'ohmylms'
											)
								}
								description={
									d.length > 1
										? ( 0, I18n.__ )(
												'Are you sure you want to delete these Quizzes?',
												'ohmylms'
											)
										: ( 0, I18n.__ )(
												'Are you sure you want to delete quiz?',
												'ohmylms'
											)
								}
								onClose={ ae }
								onDelete={ oe }
								isOpen={ h }
								isDelete={ ! 0 }
							/>
						</React.Fragment>
					) }
				</React.Fragment>
			 )
		 );
	};
}
