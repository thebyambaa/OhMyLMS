import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
import { EditIcon } from '../../shared/icons';
import * as Controls from '../../shared/controls';
export function createMembershipList( readRuntime ) {
	return function MembershipList() {
		const {
			D: Buttons,
			Ea,
			Ge,
			H8,
			He,
			Ie,
			L8: MembershipEditor,
			Ne,
			React,
			T: StoreModule,
			V8,
			We,
			Y8,
			YG,
			YH,
			aY,
			b: I18n,
			df,
			f: Router,
			fN,
			g: ReactHooks,
			hN,
			l,
			q,
			q8,
			sN,
			sn,
			uf,
			wq,
			xq,
			y: WordPressData,
		} = readRuntime();
		var e = ( 0, WordPressData.useDispatch )( StoreModule.default );
		var t = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
			return _eLocal( StoreModule.default ).getNotificationMessage();
		}, [] );
		var n = ( 0, WordPressData.useSelect )( function ( _eLocal2 ) {
			return _eLocal2( StoreModule.default ).getNotificationStatus();
		}, [] );
		var r = ( 0, WordPressData.useSelect )( function ( _eLocal3 ) {
			return _eLocal3( StoreModule.default ).getMembershipPagination();
		}, [] );
		var totalPlans = r.totalPlans;
		var o =
			( r.totalPages,
			( 0, WordPressData.useSelect )( function ( _eLocal4 ) {
				return _eLocal4( StoreModule.default ).getMemberships();
			}, [] ) );
		var c = Y8( ( 0, ReactHooks.useState )( ! 0 ), 2 );
		var u = c[ 0 ];
		var s = c[ 1 ];
		var d = Y8( ( 0, ReactHooks.useState )( [] ), 2 );
		var m = d[ 0 ];
		var p = d[ 1 ];
		var v = Y8( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var h = v[ 0 ];
		var _ = v[ 1 ];
		var w = Y8( ( 0, ReactHooks.useState )( 1 ), 2 );
		var E = w[ 0 ];
		var S = w[ 1 ];
		var R = Y8( ( 0, ReactHooks.useState )( 10 ), 2 );
		var x = R[ 0 ];
		var C = ( R[ 1 ], Y8( ( 0, ReactHooks.useState )( '' ), 2 ) );
		var P = C[ 0 ];
		var O = C[ 1 ];
		var k = Y8( ( 0, ReactHooks.useState )( 'All' ), 2 );
		var j = k[ 0 ];
		var A = k[ 1 ];
		var M = Y8( ( 0, ReactHooks.useState )( null ), 2 );
		var F = M[ 0 ];
		var N = M[ 1 ];
		var W = Y8( ( 0, ReactHooks.useState )( null ), 2 );
		var B = ( W[ 0 ], W[ 1 ] );
		var V = Y8( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var H = V[ 0 ];
		var G = V[ 1 ];
		var U = Y8( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var Y = U[ 0 ];
		var Q = U[ 1 ];
		var Z = Y8( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var $ = Z[ 0 ];
		var K = Z[ 1 ];
		var J = Y8( ( 0, ReactHooks.useState )( 'all' ), 2 );
		var X = J[ 0 ];
		var ee = J[ 1 ];
		var te = Y8( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var ne = te[ 0 ];
		var re = te[ 1 ];
		var ae = useNotifications();
		var openNotificationWithIcon = ae.openNotificationWithIcon;
		var contextHolder = ae.contextHolder;
		var le =
			( ( 0, Router.Zp )(),
			( 0, ReactHooks.useCallback )( function ( _eLocal5 ) {
				O( _eLocal5 );
				S( 1 );
			}, [] ) );
		var ce = ( 0, ReactHooks.useCallback )( function ( _eLocal6 ) {
			A( _eLocal6 );
			S( 1 );
		}, [] );
		var ue = ( 0, ReactHooks.useCallback )( function ( _eLocal7 ) {
			ee( _eLocal7 );
			S( 1 );
		}, [] );
		var se = ( 0, ReactHooks.useCallback )( function ( _eLocal8 ) {
			S( _eLocal8 );
			p( [] );
		}, [] );
		var de = ( 0, ReactHooks.useCallback )(
			( function () {
				var _tLocal = q8(
					H8().m( function _tLocal2( _nLocal ) {
						var _rLocal, a;
						return H8().w(
							function ( _tLocal3 ) {
								for (;;) {
									switch ( ( _tLocal3.p = _tLocal3.n ) ) {
										case 0:
											{
												_tLocal3.n = 1;
												break;
											}
											return (
												re( ! 0 ),
												_tLocal3.a( 2 )
											 );
										case 1:
											return (
												( _tLocal3.p = 1 ),
												G( ! 0 ),
												Q( ! 0 ),
												( _tLocal3.n = 2 ),
												l()( {
													path: '/ohmylms/v1/membership/'.concat(
														_nLocal
													),
													method: 'GET',
													headers: {
														'Content-Type':
															'application/json',
													},
												} )
											 );
										case 2:
											_rLocal = _tLocal3.v;
											e.addFullMembershipPlan( _rLocal );
											_tLocal3.n = 4;
											break;
										case 3:
											_tLocal3.p = 3;
											a = _tLocal3.v;
											showRequestError( a );
										// Falls through to the shared async state cleanup.
										case 4:
											return (
												( _tLocal3.p = 4 ),
												Q( ! 1 ),
												_tLocal3.f( 4 )
											 );
										case 5:
											return _tLocal3.a( 2 );
									}
								}
							},
							_tLocal2,
							null,
							[ [ 1, 3, 4, 5 ] ]
						);
					} )
				);
				return function ( ..._args2 ) {
					return _tLocal.apply( this, _args2 );
				};
			} )(),
			[]
		);
		var me = ( 0, ReactHooks.useCallback )(
			q8(
				H8().m( function _tLocal4( ..._args ) {
					var _nLocal2,
						_rLocal2,
						a,
						_oLocal = _args;
					return H8().w( function ( _tLocal5 ) {
						for (;;) {
							switch ( _tLocal5.n ) {
								case 0:
									{
										_nLocal2 =
											_oLocal.length > 0 &&
											void 0 !== _oLocal[ 0 ]
												? _oLocal[ 0 ]
												: 'date';
										_rLocal2 =
											_oLocal.length > 1 &&
											void 0 !== _oLocal[ 1 ]
												? _oLocal[ 1 ]
												: 'DESC';
										{
											_tLocal5.n = 1;
											break;
										}
									}
									return ( s( ! 1 ), _tLocal5.a( 2 ) );
								case 1:
									s( ! 0 );
									a = {
										offset: ( E - 1 ) * x,
										order: _rLocal2,
										page: E,
										per_page: x,
										search: P,
										post_status: j,
										orderby: _nLocal2,
										date_filter: X,
									};
									if ( xq( X ) ) {
										{
											a.date_filter = 'custom';
											a.start_date = sn()(
												X[ 0 ]
											).format( 'YYYY-MM-DD' );
											a.end_date = sn()( X[ 1 ] ).format(
												'YYYY-MM-DD'
											);
										}
									}
									e.fetchMembershipPlans( a ).finally(
										function () {
											s( ! 1 );
										}
									);
								// Falls through to the shared async state cleanup.
								// Falls through to the shared async state cleanup.
								case 2:
									return _tLocal5.a( 2 );
							}
						}
					}, _tLocal4 );
				} )
			),
			[ E, x, P, j, X ]
		);
		var pe = ( 0, ReactHooks.useCallback )( function ( _eLocal0 ) {
			_( ! 0 );
			N( _eLocal0 );
		}, [] );
		var fe = ( 0, ReactHooks.useCallback )( function () {
			_( ! 1 );
		}, [] );
		var ve = ( 0, ReactHooks.useCallback )(
			q8(
				H8().m( function _tLocal6() {
					return H8().w( function ( _tLocal7 ) {
						for (;;) {
							switch ( _tLocal7.n ) {
								case 0:
									return (
										( _tLocal7.n = 1 ),
										e.handleBulkDelete( 'membership', {
											membership_ids: F ? [ F ] : m,
										} )
									 );
								case 1:
									me();
									p( [] );
									S( 1 );
									N( null );
									_( ! 1 );
									K( ! 0 );
								// Falls through to the shared async state cleanup.
								// Falls through to the shared async state cleanup.
								case 2:
									return _tLocal7.a( 2 );
							}
						}
					}, _tLocal6 );
				} )
			),
			[ m, F, me ]
		);
		var ge = ( 0, ReactHooks.useCallback )(
			function ( _eLocal1, _tLocal8, _nLocal3 ) {
				var _rLocal3 =
						{
							number_of_submissions: 'number_of_submissions',
							name: 'title',
						}[ _nLocal3.field ] || _nLocal3.field,
					a =
						{
							ascend: 'ASC',
							descend: 'DESC',
						}[ _nLocal3.order ] || _nLocal3.order;
				S( 1 );
				me( _rLocal3, a );
			},
			[ me, S ]
		);
		var he = ( 0, ReactHooks.useMemo )(
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
			[ m ]
		);
		var ye = ( 0, ReactHooks.useMemo )( function () {
			return [
				{
					value: 'all',
					label: ( 0, I18n.__ )( 'All', 'ohmylms' ),
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
		}, [] );
		var be = ( 0, ReactHooks.useMemo )( function () {
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
		}, [] );
		var _e = ( 0, ReactHooks.useMemo )(
			function () {
				return {
					selectedRowKeys: m,
					onChange: p,
				};
			},
			[ m ]
		);
		var we = ( 0, ReactHooks.useMemo )( function () {
			return {
				label: ( 0, I18n.__ )( 'Add Membership', 'ohmylms' ),
				onClick() {
					return G( ! 0 );
				},
			};
		}, [] );
		var Ee = [
			{
				title: ( 0, I18n.__ )( 'ID', 'ohmylms' ),
				dataIndex: 'id',
				key: 'id',
				sorter: ! 0,
				render( _eLocal10 ) {
					return (
						<Buttons.A
							variant={ 'link' }
							onClick={ function () {
								return de( _eLocal10 );
							} }
						>
							{ '#' }
							{ _eLocal10 || '#' }
						</Buttons.A>
					);
				},
			},
			{
				title: ( 0, I18n.__ )( 'Membership Plan', 'ohmylms' ),
				dataIndex: 'name',
				key: 'name',
				sorter: ! 0,
				render( _eLocal11 ) {
					return <span>{ Ge( _eLocal11 ) || 'Untitled' }</span>;
				},
			},
			{
				title: ( 0, I18n.__ )( 'Price', 'ohmylms' ),
				dataIndex: 'price',
				key: 'price',
				sorter: ! 0,
				render( _eLocal12, _tLocal9 ) {
					return (
						<Controls.BadgeWP
							variant={ 'secondary' }
							isBorderLess={ ! 0 }
						>
							{ _tLocal9 !== null &&
							_tLocal9 !== void 0 &&
							_tLocal9.sale_price !== '' &&
							_tLocal9.sale_price !== null &&
							_tLocal9.sale_price !== undefined ? (
								<React.Fragment>
									<span>
										<YH
											currency={
												( _tLocal9 === null ||
												_tLocal9 === void 0
													? void 0
													: _tLocal9.currency ) || '$'
											}
											currency_pos={
												( _tLocal9 === null ||
												_tLocal9 === void 0
													? void 0
													: _tLocal9.currency_pos ) ||
												'left'
											}
											price={ Number(
												( _tLocal9 === null ||
												_tLocal9 === void 0
													? void 0
													: _tLocal9.sale_price ) ||
													'0'
											) }
										/>
										{ ' ' }
										<del>
											<YH
												currency={
													( _tLocal9 === null ||
													_tLocal9 === void 0
														? void 0
														: _tLocal9.currency ) ||
													'$'
												}
												currency_pos={
													( _tLocal9 === null ||
													_tLocal9 === void 0
														? void 0
														: _tLocal9.currency_pos ) ||
													'left'
												}
												price={ Number(
													( _tLocal9 === null ||
													_tLocal9 === void 0
														? void 0
														: _tLocal9.regular_price ) ||
														'0'
												) }
												del={ ! 0 }
											/>
										</del>
									</span>
								</React.Fragment>
							) : (
								<YH
									currency={
										( _tLocal9 === null ||
										_tLocal9 === void 0
											? void 0
											: _tLocal9.currency ) || '$'
									}
									currency_pos={
										( _tLocal9 === null ||
										_tLocal9 === void 0
											? void 0
											: _tLocal9.currency_pos ) || 'left'
									}
									price={ Number(
										( _tLocal9 === null ||
										_tLocal9 === void 0
											? void 0
											: _tLocal9.price ) || '0'
									) }
								/>
							) }
						</Controls.BadgeWP>
					);
				},
			},
			{
				title: ( 0, I18n.__ )( 'Billing Cycle', 'ohmylms' ),
				dataIndex: 'subscription_period',
				key: 'subscription_period',
				sorter: ! 0,
				render( _eLocal13 ) {
					var _tLocal0 = {
						day: ( 0, I18n.__ )( 'Daily', 'ohmylms' ),
						week: ( 0, I18n.__ )( 'Weekly', 'ohmylms' ),
						month: ( 0, I18n.__ )( 'Monthly', 'ohmylms' ),
						year: ( 0, I18n.__ )( 'Yearly', 'ohmylms' ),
						one_time: ( 0, I18n.__ )( 'One Time', 'ohmylms' ),
					};
					return (
						<Controls.BadgeWP
							isBorderLess={ ! 0 }
							variant={ 'secondary' }
						>
							{ _tLocal0[ _eLocal13 ] || _eLocal13 }
						</Controls.BadgeWP>
					);
				},
			},
			{
				title: ( 0, I18n.__ )( 'Subscribers', 'ohmylms' ),
				dataIndex: 'members',
				key: 'members',
				sorter: ! 0,
				render( _eLocal14 ) {
					return (
						<Controls.BadgeWP
							isBorderLess={ ! 0 }
							variant={ 'secondary' }
						>
							{ _eLocal14 || 0 }
						</Controls.BadgeWP>
					);
				},
			},
			{
				title: ( 0, I18n.__ )( 'Courses', 'ohmylms' ),
				dataIndex: 'courses',
				key: 'courses',
				sorter: ! 0,
				render( _eLocal15 ) {
					return (
						<Controls.BadgeWP
							isBorderLess={ ! 0 }
							variant={ 'secondary' }
						>
							{ _eLocal15 || 0 }
						</Controls.BadgeWP>
					);
				},
			},
			{
				title: ( 0, I18n.__ )( 'Status', 'ohmylms' ),
				dataIndex: 'status',
				key: 'status',
				render( _eLocal16 ) {
					return (
						<Controls.BadgeWP
							isBorderLess={ ! 0 }
							variant={
								'publish' === _eLocal16
									? 'success'
									: 'secondary'
							}
							style={ {
								textTransform: 'capitalize',
							} }
						>
							{ ( () => {
								if ( 'publish' === _eLocal16 ) {
									return ( 0, I18n.__ )(
										'Published',
										'ohmylms'
									);
								}
								if ( 'future' === _eLocal16 ) {
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
				title: ( 0, I18n.__ )( 'Last Updated', 'ohmylms' ),
				dataIndex: 'date_modified',
				key: 'date_modified',
				render( _eLocal17 ) {
					var _tLocal1,
						_nLocal4 = '',
						_rLocal4 =
							( null === ( _tLocal1 = window.ohmylms_params ) ||
							void 0 === _tLocal1
								? void 0
								: _tLocal1.date_format ) || 'F j, Y',
						a = ''.concat( _rLocal4 );
					return (
						_eLocal17 &&
						'object' === V8( _eLocal17 ) &&
						_eLocal17.date
							? ( _nLocal4 = ( 0, wq.dateI18n )(
									a,
									_eLocal17.date
								) )
							: 'string' === typeof _eLocal17 &&
								( _nLocal4 = ( 0, wq.dateI18n )(
									a,
									_eLocal17
								) ),
						(
							<Controls.BadgeWP
								isBorderLess={ ! 0 }
								variant={ 'secondary' }
							>
								{ _nLocal4 || '-' }
							</Controls.BadgeWP>
						 )
					 );
				},
			},
			{
				title: 'Action',
				dataIndex: 'action',
				key: 'action',
				render( _eLocal18, _tLocal10 ) {
					return (
						<Controls.DropdownMenuWP
							controls={ [
								{
									title: ( 0, I18n.__ )( 'Edit', 'ohmylms' ),
									key: 'edit',
									onClick() {
										return de(
											_tLocal10 === null ||
												_tLocal10 === void 0
												? void 0
												: _tLocal10.id
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
										return pe(
											_tLocal10 === null ||
												_tLocal10 === void 0
												? void 0
												: _tLocal10.id
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
					var _eLocal19 = ! 0;
					return (
						_eLocal19 && me(),
						function () {
							_eLocal19 = ! 1;
						}
					 );
				},
				[ E, x, P, e, j, X, $ ]
			),
			( 0, ReactHooks.useEffect )(
				function () {
					if ( ! u && t ) {
						openNotificationWithIcon( n, t );
					}
				},
				[ t ]
			),
			(
				<React.Fragment>
					{ contextHolder }
					<Controls.ContainerWP>
						<YG
							title={ ( 0, I18n.__ )(
								'All Memberships',
								'ohmylms'
							) }
							showAddButton={ ! 0 }
							addButtonConfig={ we }
						/>
						<Ea
							isBorderless={ ! 0 }
							minHeight={ 'calc(100vh - 200px)' }
						>
							<Controls.SpacerWP padding={ 5 }>
								{ m.length > 0
									? React.createElement( hN, {
											items: m,
											setItems: p,
											bulksActions: he,
										} )
									: React.createElement( aY, {
											handleSearch: le,
											searchPlaceholder: ( 0, I18n.__ )(
												'Search Membership',
												'ohmylms'
											),
											handleFilterByDays: ue,
											filterByDaysOptions: be,
											filterByDays: X,
											handleFilterByStatus: ce,
											filterByStatusOptions: ye,
											filterByStatus: j,
											currentPage: E,
											totalItems: totalPlans,
											perPage: x,
											showFilterByCategory: ! 1,
											showFilterByPriceType: ! 1,
											showFilterByStatus: ! 1,
										} ) }
								<sN.A
									rowKey={ 'id' }
									columns={ Ee }
									dataSource={ o || [] }
									rowSelection={ _e }
									pagination={ ! 1 }
									loading={ u }
									onChange={ ge }
									onRowMouseEnter={ function ( _eLocal20 ) {
										return B(
											_eLocal20 === null ||
												_eLocal20 === void 0
												? void 0
												: _eLocal20.id
										);
									} }
									onRowMouseLeave={ function () {
										return B( null );
									} }
									locale={ {
										emptyText: React.createElement( uf, {
											icon: React.createElement(
												df,
												null
											),
											title: ( 0, I18n.__ )(
												'No Memberships yet!',
												'ohmylms'
											),
											description: ( 0, I18n.__ )(
												'Start building your first membership and it’ll show up here as soon as you hit publish.',
												'ohmylms'
											),
											ctaText: ( 0, I18n.__ )(
												'Add Membership',
												'ohmylms'
											),
											ctaHandler() {
												return G( ! 0 );
											},
										} ),
									} }
								/>
								{ u &&
									Number( totalPlans ) > x &&
									React.createElement( fN, {
										total: totalPlans,
										currentPage: E,
										onPageChange: se,
										perPage: x,
									} ) }
							</Controls.SpacerWP>
						</Ea>
					</Controls.ContainerWP>
					{ h && (
						<Ie
							title={
								m.length > 1
									? ( 0, I18n.__ )(
											'Delete Memberships',
											'ohmylms'
										)
									: ( 0, I18n.__ )(
											'Delete Membership',
											'ohmylms'
										)
							}
							description={
								m.length > 1
									? ( 0, I18n.__ )(
											'Are you sure you want to delete these memberships?',
											'ohmylms'
										)
									: ( 0, I18n.__ )(
											'Are you sure you want to delete membership?',
											'ohmylms'
										)
							}
							onClose={ fe }
							onDelete={ ve }
							isOpen={ h }
							isDelete={ ! 0 }
						/>
					) }
					{ H && (
						<MembershipEditor
							isOpen={ H }
							setIsOpen={ G }
							isFetch={ $ }
							setIsFetch={ K }
							isLoading={ Y }
						/>
					) }
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
