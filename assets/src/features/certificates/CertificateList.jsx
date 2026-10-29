/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
import { EditIcon } from '../../shared/icons';
import * as Controls from '../../shared/controls';
export function createCertificateList( readRuntime ) {
	return function CertificateList() {
		const {
			$ee: MemoCertificateNameCell,
			Ea,
			Ge,
			Ie,
			Ne,
			React,
			T: StoreModule,
			We,
			YG,
			aY,
			b: I18n,
			cte,
			df,
			dte,
			f: Router,
			fN,
			fte,
			g: ReactHooks,
			hN,
			l,
			lte: MemoCertificateTemplateDialog,
			mte,
			pZ,
			pte,
			q,
			sN,
			sn,
			uf,
			y: WordPressData,
		} = readRuntime();
		var e = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			t = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
				return _eLocal( StoreModule.default ).getNotificationMessage();
			}, [] ),
			n = ( 0, WordPressData.useSelect )( function ( _eLocal2 ) {
				return _eLocal2( StoreModule.default ).getNotificationStatus();
			}, [] ),
			r = ( 0, WordPressData.useSelect )( function ( _eLocal3 ) {
				return _eLocal3(
					StoreModule.default
				).selectCertificatePagination();
			}, [] ),
			a = r.totalItems,
			o =
				( r.totalPages,
				( 0, WordPressData.useSelect )( function ( _eLocal4 ) {
					return _eLocal4( StoreModule.default ).selectCertificates();
				}, [] ) ),
			i = mte( ( 0, ReactHooks.useState )( ! 0 ), 2 ),
			c = i[ 0 ],
			u = i[ 1 ],
			s = mte( ( 0, ReactHooks.useState )( [] ), 2 ),
			d = s[ 0 ],
			m = s[ 1 ],
			p = mte( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			v = p[ 0 ],
			h = p[ 1 ],
			_ = mte( ( 0, ReactHooks.useState )( 1 ), 2 ),
			w = _[ 0 ],
			E = _[ 1 ],
			S = mte( ( 0, ReactHooks.useState )( 5 ), 2 ),
			R = S[ 0 ],
			x = ( S[ 1 ], mte( ( 0, ReactHooks.useState )( '' ), 2 ) ),
			C = x[ 0 ],
			P = x[ 1 ],
			O = mte( ( 0, ReactHooks.useState )( '' ), 2 ),
			k = O[ 0 ],
			j = O[ 1 ],
			A = mte( ( 0, ReactHooks.useState )( 'all' ), 2 ),
			M = A[ 0 ],
			F = A[ 1 ],
			N = mte( ( 0, ReactHooks.useState )( null ), 2 ),
			D = N[ 0 ],
			W = N[ 1 ],
			B = mte( ( 0, ReactHooks.useState )( null ), 2 ),
			L = B[ 0 ],
			V = B[ 1 ],
			H = mte( ( 0, ReactHooks.useState )( [] ), 2 ),
			G = H[ 0 ],
			U = H[ 1 ],
			Y = mte( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			Q = Y[ 0 ],
			Z = Y[ 1 ],
			$ = useNotifications(),
			K = $.openNotificationWithIcon,
			J = $.contextHolder,
			X = ( 0, Router.Zp )(),
			ee = ( 0, ReactHooks.useCallback )( function ( _eLocal5 ) {
				P( _eLocal5 );
				E( 1 );
			}, [] ),
			te = ( 0, ReactHooks.useCallback )( function ( _eLocal6 ) {
				j( _eLocal6 );
				E( 1 );
			}, [] ),
			ne = ( 0, ReactHooks.useCallback )( function ( _eLocal7 ) {
				F( _eLocal7 );
				E( 1 );
			}, [] ),
			re = ( 0, ReactHooks.useCallback )( function ( _eLocal8 ) {
				E( _eLocal8 );
				m( [] );
			}, [] ),
			ae = ( 0, ReactHooks.useCallback )(
				dte(
					cte().m( function _tLocal( ..._args ) {
						var _nLocal,
							_rLocal,
							_aLocal,
							_oLocal = _args;
						return cte().w( function ( _tLocal2 ) {
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
											offset: ( w - 1 ) * R,
											order: _rLocal,
											page: w,
											per_page: R,
											search: C,
											post_status: M,
											orderby: _nLocal,
											course_id: k,
										};
										e.fetchCertificates( _aLocal ).finally(
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
				[ w, R, C, M, k ]
			),
			oe = ( 0, ReactHooks.useCallback )( function ( _eLocal9 ) {
				h( ! 0 );
				W( _eLocal9 );
			}, [] ),
			ie = ( 0, ReactHooks.useCallback )( function () {
				h( ! 1 );
			}, [] ),
			le = ( 0, ReactHooks.useCallback )(
				dte(
					cte().m( function _tLocal3() {
						return cte().w( function ( _tLocal4 ) {
							for (;;) {
								switch ( _tLocal4.n ) {
									case 0:
										return (
											( _tLocal4.n = 1 ),
											e.handleBulkDelete(
												'certificates',
												{
													certificate_ids: D
														? [ D ]
														: d,
												}
											)
										 );
									case 1:
										ae();
										m( [] );
										E( 1 );
										W( null );
										h( ! 1 ); // Falls through to the shared async state cleanup.
									// Falls through to the shared async state cleanup.
									case 2:
										return _tLocal4.a( 2 );
								}
							}
						}, _tLocal3 );
					} )
				),
				[ d, D, ae ]
			),
			ce = ( 0, ReactHooks.useCallback )(
				function ( _eLocal0, _tLocal5, _nLocal2 ) {
					var _rLocal2 =
							{
								date_created: 'date',
								name: 'title',
							}[ _nLocal2.field ] || _nLocal2.field,
						_aLocal2 =
							{
								ascend: 'ASC',
								descend: 'DESC',
							}[ _nLocal2.order ] || _nLocal2.order;
					E( 1 );
					ae( _rLocal2, _aLocal2 );
				},
				[ ae, E ]
			),
			ue = ( 0, ReactHooks.useCallback )(
				dte(
					cte().m( function _eLocal1() {
						var _tLocal6;
						return cte().w( function ( _eLocal10 ) {
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
										U( _tLocal6 ); // Falls through to the shared async state cleanup.
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
			se = ( 0, ReactHooks.useMemo )(
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
										return fte( _eLocal12 );
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
								pte( _eLocal11 ) ||
								( function () {
									throw new TypeError(
										'Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
									);
								} )()
							);
						} )(
							G.map( function ( _eLocal14 ) {
								return {
									label: Ge(
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
				[ G ]
			),
			de = ( 0, ReactHooks.useMemo )(
				function () {
					return [
						{
							label: ( 0, I18n.__ )( 'Delete', 'ohmylms' ),
							value: 'delete',
							action() {
								h( ! 0 );
							},
						},
					];
				},
				[ d ]
			),
			me = ( 0, ReactHooks.useMemo )( function () {
				return [
					{
						value: 'all',
						label: ( 0, I18n.__ )( 'All Status', 'ohmylms' ),
					},
					{
						value: 'publish',
						label: ( 0, I18n.__ )( 'Publish', 'ohmylms' ),
					},
					{
						value: 'draft',
						label: ( 0, I18n.__ )( 'Draft', 'ohmylms' ),
					},
				];
			}, [] ),
			pe = ( 0, ReactHooks.useMemo )(
				function () {
					return {
						selectedRowKeys: d,
						onChange: m,
					};
				},
				[ d ]
			),
			fe = ( 0, ReactHooks.useMemo )( function () {
				return {
					label: ( 0, I18n.__ )( 'Add Certificate', 'ohmylms' ),
					onClick() {
						return Z( ! 0 );
					},
				};
			}, [] ),
			ve = [
				{
					title: 'Certificate',
					dataIndex: 'name',
					key: 'name',
					sorter: ! 0,
					width: '340px',
					render( _eLocal15, _tLocal7 ) {
						var _nLocal3 = L === _tLocal7.id;
						return (
							<MemoCertificateNameCell
								record={ _tLocal7 }
								isHover={ _nLocal3 }
							/>
						);
					},
				},
				{
					title: 'Date',
					dataIndex: 'date_created',
					key: 'date_created',
					sorter: ! 0,
					render( _eLocal16 ) {
						return (
							<Controls.BadgeWP
								isBorderLess={ ! 0 }
								variant={ 'secondary' }
							>
								{ sn()(
									_eLocal16 === null || _eLocal16 === void 0
										? void 0
										: _eLocal16.date
								).format( 'MMMM DD, YYYY' ) || '-' }
							</Controls.BadgeWP>
						);
					},
				},
				{
					title: 'Course name',
					dataIndex: 'courses',
					key: 'courses',
					render( _eLocal17 ) {
						var _nLocal4 =
							( _eLocal17 === null || _eLocal17 === void 0
								? void 0
								: _eLocal17.map( function ( _eLocal18 ) {
										return _eLocal18 === null ||
											_eLocal18 === void 0
											? void 0
											: _eLocal18.name;
									} ) ) || [];
						return React.createElement( pZ, {
							items: _nLocal4,
							showAllOnHover: ! 0,
							maxVisible: 3,
						} );
					},
				},
				{
					title: 'Status',
					dataIndex: 'status',
					key: 'status',
					render( _eLocal19 ) {
						return (
							<Controls.BadgeWP
								isBorderLess={ ! 0 }
								variant={
									'publish' === _eLocal19
										? 'success'
										: 'secondary'
								}
								style={ {
									textTransform: 'capitalize',
								} }
							>
								{ ( () => {
									if ( 'publish' === _eLocal19 ) {
										return ( 0, I18n.__ )(
											'Published',
											'ohmylms'
										);
									}
									if ( 'future' === _eLocal19 ) {
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
					title: 'Action',
					dataIndex: 'action',
					key: 'action',
					render( _eLocal20, _tLocal0 ) {
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
											return X(
												'/certificate-edit/'.concat(
													_tLocal0 === null ||
														_tLocal0 === void 0
														? void 0
														: _tLocal0.id
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
											'Delete',
											'ohmylms'
										),
										key: 'delete',
										onClick() {
											return oe(
												_tLocal0 === null ||
													_tLocal0 === void 0
													? void 0
													: _tLocal0.id
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
					var _eLocal21 = ! 0;
					return (
						_eLocal21 && ae(),
						function () {
							_eLocal21 = ! 1;
						}
					 );
				},
				[ w, R, C, e, M, k ]
			),
			( 0, ReactHooks.useEffect )( function () {
				var _eLocal22 = ! 0;
				return (
					_eLocal22 && ue(),
					function () {
						_eLocal22 = ! 1;
					}
				 );
			}, [] ),
			( 0, ReactHooks.useEffect )(
				function () {
					if ( ! c && t ) {
						K( n, t );
					}
				},
				[ t ]
			),
			(
				<React.Fragment>
					{ J }
					<Controls.ContainerWP>
						<YG
							title={ ( 0, I18n.__ )(
								'All Certificates',
								'ohmylms'
							) }
							showAddButton={ ! 0 }
							addButtonConfig={ fe }
						/>
						<Ea
							isBorderless={ ! 0 }
							minHeight={ 'calc(100vh - 200px)' }
						>
							<Controls.SpacerWP padding={ 5 }>
								{ d.length > 0
									? React.createElement( hN, {
											items: d,
											setItems: m,
											bulksActions: de,
										} )
									: React.createElement( aY, {
											handleSearch: ee,
											searchPlaceholder: ( 0, I18n.__ )(
												'Search certificate',
												'ohmylms'
											),
											showFilterByDays: ! 1,
											showFilterByPriceType: ! 1,
											handleFilterByCategory: te,
											filterByCategory: k,
											categories: se,
											categoryPrefix: ( 0, I18n.__ )(
												'Course',
												'ohmylms'
											),
											currentPage: w,
											totalItems: a,
											handleFilterByStatus: ne,
											filterByStatus: M,
											filterByStatusOptions: me,
											formateCategory: ! 1,
											showFilterByStatus: ! 1,
											className:
												'ohmylms-certificate-listing-filter-card',
										} ) }
								<sN.A
									rowKey={ 'id' }
									columns={ ve }
									dataSource={ o || [] }
									rowSelection={ pe }
									pagination={ ! 1 }
									loading={ c }
									onChange={ ce }
									onRowMouseEnter={ function ( _eLocal23 ) {
										return V(
											_eLocal23 === null ||
												_eLocal23 === void 0
												? void 0
												: _eLocal23.id
										);
									} }
									onRowMouseLeave={ function () {
										return V( null );
									} }
									className={ 'certificate-listing-table' }
									locale={ {
										emptyText: React.createElement( uf, {
											icon: React.createElement(
												df,
												null
											),
											title: ( 0, I18n.__ )(
												'No Certificates yet!',
												'ohmylms'
											),
											description: ( 0, I18n.__ )(
												'Start building your first certificate and it’ll show up here as soon as you hit publish.',
												'ohmylms'
											),
										} ),
									} }
								/>
								{ ! c &&
									Number( a ) > 5 &&
									React.createElement( fN, {
										total: a,
										currentPage: w,
										onPageChange: re,
										perPage: R,
									} ) }
							</Controls.SpacerWP>
						</Ea>
					</Controls.ContainerWP>
					{ v && (
						<Ie
							title={
								d.length > 1
									? ( 0, I18n.__ )(
											'Delete Certificates',
											'ohmylms'
										)
									: ( 0, I18n.__ )(
											'Delete Certificate',
											'ohmylms'
										)
							}
							description={
								d.length > 1
									? ( 0, I18n.__ )(
											'Are you sure you want to delete these certificates?',
											'ohmylms'
										)
									: ( 0, I18n.__ )(
											'Are you sure you want to delete certificate?',
											'ohmylms'
										)
							}
							onClose={ ie }
							onDelete={ le }
							isOpen={ v }
							isDelete={ ! 0 }
						/>
					) }
					{ Q && (
						<MemoCertificateTemplateDialog
							openModal={ Q }
							setOpenModal={ Z }
						/>
					) }
				</React.Fragment>
			 )
		 );
	};
}
