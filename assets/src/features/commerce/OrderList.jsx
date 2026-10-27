/**
 * OrderList component (replaces recovered binding ZY).
 * Displays order management table, filters, pagination, and bulk delete operations.
 */
import { createElement } from '@wordpress/element';

export function createOrderList( readRuntime ) {
	return function OrderList() {
		const {
			Br: ViewIcon,
			Cm: SearchInput,
			Ea: ContentCard,
			I: Controls,
			Ie: ConfirmDialog,
			Ne: MenuIcon,
			React,
			T: StoreModule,
			VY: formatDateTime,
			We: DeleteIcon,
			YG: PageHeader,
			YH: PriceDisplay,
			ZU: DateFilter,
			b: I18n,
			df: EmptyIcon,
			f: Router,
			fN: Pagination,
			g: ReactHooks,
			hN: BulkActionsBar,
			q: IconWrapper,
			sN: TableModule,
			sn: moment,
			uf: EmptyContainer,
			v: RouterLink,
			vn: SelectControl,
			xq: isCustomDateRange,
			y: WordPressData,
			z: Notifications,
		} = readRuntime();

		const dispatch = WordPressData.useDispatch( StoreModule.default );
		const notificationMessage = WordPressData.useSelect(
			( select ) =>
				select( StoreModule.default ).getNotificationMessage(),
			[]
		);
		const notificationStatus = WordPressData.useSelect(
			( select ) => select( StoreModule.default ).getNotificationStatus(),
			[]
		);
		const ordersPagination = WordPressData.useSelect(
			( select ) =>
				select( StoreModule.default ).selectOrdersPagination(),
			[]
		);
		const totalOrders = ordersPagination?.totalOrders || 0;
		const orders = WordPressData.useSelect(
			( select ) => select( StoreModule.default ).selectOrders(),
			[]
		);
		const currency = WordPressData.useSelect(
			( select ) => select( StoreModule.default ).getCurrency(),
			[]
		);

		const [ loading, setLoading ] = ReactHooks.useState( true );
		const [ selectedRowKeys, setSelectedRowKeys ] = ReactHooks.useState(
			[]
		);
		const [ isDeleteModalOpen, setIsDeleteModalOpen ] =
			ReactHooks.useState( false );
		const [ currentPage, setCurrentPage ] = ReactHooks.useState( 1 );
		const [ perPage ] = ReactHooks.useState( 10 );
		const [ searchTerm, setSearchTerm ] = ReactHooks.useState( '' );
		const [ statusFilter, setStatusFilter ] = ReactHooks.useState( 'any' );
		const [ targetDeleteId, setTargetDeleteId ] =
			ReactHooks.useState( null );
		const [ , setHoveredRowId ] = ReactHooks.useState( null );
		const [ dateFilter, setDateFilter ] = ReactHooks.useState( 'all' );
		const [ paymentMethodFilter, setPaymentMethodFilter ] =
			ReactHooks.useState( '' );
		const [ sorting, setSorting ] = ReactHooks.useState( {
			field: 'date',
			order: 'DESC',
		} );

		const notifications = Notifications.A();
		const openNotification = notifications.openNotificationWithIcon;
		const contextHolder = notifications.contextHolder;
		const navigate = Router.Zp();

		const handleSearch = ReactHooks.useCallback( ( value ) => {
			setSearchTerm( value );
			setCurrentPage( 1 );
		}, [] );

		const handleStatusFilter = ReactHooks.useCallback( ( value ) => {
			setStatusFilter( value );
			setCurrentPage( 1 );
		}, [] );

		const handleDateFilter = ReactHooks.useCallback( ( value ) => {
			setDateFilter( value );
			setCurrentPage( 1 );
		}, [] );

		const handlePaymentFilter = ReactHooks.useCallback( ( value ) => {
			setPaymentMethodFilter( value );
			setCurrentPage( 1 );
		}, [] );

		const handlePageChange = ReactHooks.useCallback( ( page ) => {
			setCurrentPage( page );
			setSelectedRowKeys( [] );
		}, [] );

		const handleEditOrder = ReactHooks.useCallback(
			( id ) => {
				navigate( `/order-edit/${ id }` );
			},
			[ navigate ]
		);

		const fetchOrdersList = ReactHooks.useCallback(
			async ( orderby = sorting.field, order = sorting.order ) => {
				setLoading( true );
				const params = {
					offset: ( currentPage - 1 ) * perPage,
					order,
					page: currentPage,
					per_page: perPage,
					search: searchTerm,
					orderby,
					post_status: statusFilter,
					date_filter: dateFilter,
					payment_method: paymentMethodFilter,
				};

				if ( isCustomDateRange( dateFilter ) ) {
					params.date_filter = 'custom';
					params.start_date = moment()( dateFilter[ 0 ] ).format(
						'YYYY-MM-DD'
					);
					params.end_date = moment()( dateFilter[ 1 ] ).format(
						'YYYY-MM-DD'
					);
				}

				try {
					await dispatch.fetchOrders( params );
				} catch ( error ) {
					openNotification(
						'error',
						error?.message ||
							I18n.__( 'Could not load orders.', 'ohmylms' )
					);
				} finally {
					setLoading( false );
				}
			},
			[
				currentPage,
				perPage,
				searchTerm,
				statusFilter,
				dateFilter,
				paymentMethodFilter,
				sorting,
				dispatch,
			]
		);

		const openDeleteSingle = ReactHooks.useCallback( ( id ) => {
			setIsDeleteModalOpen( true );
			setTargetDeleteId( id );
		}, [] );

		const closeDeleteModal = ReactHooks.useCallback( () => {
			setIsDeleteModalOpen( false );
			setTargetDeleteId( null );
		}, [] );

		const handleDeleteConfirm = ReactHooks.useCallback( async () => {
			const ids = targetDeleteId ? [ targetDeleteId ] : selectedRowKeys;
			await dispatch.trashBulkOrdersAction( ids );
			fetchOrdersList();
			setSelectedRowKeys( [] );
			setCurrentPage( 1 );
			setTargetDeleteId( null );
			setIsDeleteModalOpen( false );
		}, [ dispatch, selectedRowKeys, targetDeleteId, fetchOrdersList ] );

		const handleTableChange = ReactHooks.useCallback(
			( pagination, filters, sorter ) => {
				const fieldMap = {
					student_email: 'student_name',
					name: 'title',
				};
				const field =
					fieldMap[ sorter.field ] || sorter.field || 'date';
				const order = sorter.order === 'ascend' ? 'ASC' : 'DESC';
				setCurrentPage( 1 );
				setSorting( { field, order } );
			},
			[ fetchOrdersList ]
		);

		const bulkActions = ReactHooks.useMemo(
			() => [
				{
					label: I18n.__( 'Delete', 'ohmylms' ),
					value: 'delete',
					action: () => setIsDeleteModalOpen( true ),
				},
			],
			[]
		);

		const paymentOptions = ReactHooks.useMemo(
			() => [
				{
					value: '',
					label: I18n.__( 'All Payment Method', 'ohmylms' ),
				},
				{ value: 'stripe', label: I18n.__( 'Stripe', 'ohmylms' ) },
				{ value: 'paypal', label: I18n.__( 'PayPal', 'ohmylms' ) },
				{ value: 'offline', label: I18n.__( 'Offline', 'ohmylms' ) },
				{
					value: 'authorize_net',
					label: I18n.__( 'Authorize.Net', 'ohmylms' ),
				},
			],
			[]
		);

		const statusOptions = ReactHooks.useMemo(
			() => [
				{ value: 'any', label: I18n.__( 'All Status', 'ohmylms' ) },
				{
					value: 'ohmylms-completed',
					label: I18n.__( 'Completed', 'ohmylms' ),
				},
				{
					value: 'ohmylms-pending',
					label: I18n.__( 'Pending', 'ohmylms' ),
				},
				{
					value: 'ohmylms-on-hold',
					label: I18n.__( 'On Hold', 'ohmylms' ),
				},
				{
					value: 'ohmylms-processing',
					label: I18n.__( 'Processing', 'ohmylms' ),
				},
				{
					value: 'ohmylms-cancelled',
					label: I18n.__( 'Cancelled', 'ohmylms' ),
				},
				{
					value: 'ohmylms-refunded',
					label: I18n.__( 'Refunded', 'ohmylms' ),
				},
			],
			[]
		);

		const rowSelection = ReactHooks.useMemo(
			() => ( {
				selectedRowKeys,
				onChange: setSelectedRowKeys,
			} ),
			[ selectedRowKeys ]
		);

		const columns = [
			{
				title: I18n.__( 'Order', 'ohmylms' ),
				dataIndex: 'id',
				key: 'id',
				sorter: true,
				render: ( id ) => (
					<RouterLink.Link to={ `/order-edit/${ id }` }>
						#{ id || '#' }
					</RouterLink.Link>
				),
			},
			{
				title: I18n.__( 'Date', 'ohmylms' ),
				dataIndex: 'date_created',
				key: 'date_created',
				sorter: true,
				render: ( date ) => formatDateTime( date ) || '-',
			},
			{
				title: I18n.__( 'Student', 'ohmylms' ),
				dataIndex: 'student_email',
				key: 'student_email',
				sorter: true,
				render: ( email ) => <span>{ email || '-' }</span>,
			},
			{
				title: I18n.__( 'Payment Method', 'ohmylms' ),
				dataIndex: 'payment_method',
				key: 'payment_method',
				render: ( method ) => (
					<span style={ { textTransform: 'capitalize' } }>
						{ method === 'offline_payment'
							? 'Offline'
							: method || '-' }
					</span>
				),
			},
			{
				title: I18n.__( 'Purchased By', 'ohmylms' ),
				dataIndex: 'purchased_by',
				key: 'purchased_by',
				render: ( purchaser ) => (
					<span style={ { textTransform: 'capitalize' } }>
						{ purchaser }
					</span>
				),
			},
			{
				title: I18n.__( 'Subscription Relationship', 'ohmylms' ),
				dataIndex: 'subscription_relationship',
				key: 'subscription_relationship',
				render: ( _, record ) => {
					if ( record?.is_renewal_order ) {
						return 'Renewal order';
					}
					if ( record?.is_parent_order ) {
						return 'Parent order';
					}
					return '-';
				},
			},
			{
				title: I18n.__( 'Total', 'ohmylms' ),
				dataIndex: 'total',
				key: 'total',
				sorter: true,
				render: ( _, record ) => {
					if ( record?.refunds && record.refunds.length > 0 ) {
						return (
							<React.Fragment>
								<span>
									<PriceDisplay
										currency={ currency?.currency || '$' }
										currency_pos={
											currency?.currency_pos || 'left'
										}
										price={ Number( record?.total || 0 ) }
									/>
								</span>
								<del>
									<PriceDisplay
										currency={ currency?.currency || '$' }
										currency_pos={
											currency?.currency_pos || 'left'
										}
										price={ Number( record?.total || 0 ) }
									/>
								</del>
							</React.Fragment>
						);
					}
					return (
						<span
							dangerouslySetInnerHTML={ {
								__html: record?.formattedTotal,
							} }
						/>
					);
				},
			},
			{
				title: I18n.__( 'Status', 'ohmylms' ),
				dataIndex: 'status',
				key: 'status',
				render: ( status ) => {
					let variant = 'default';
					switch ( status ) {
						case 'completed':
							variant = 'success';
							break;
						case 'pending':
						case 'on-hold':
							variant = 'warning';
							break;
						case 'cancelled':
						case 'refunded':
							variant = 'danger';
							break;
						case 'processing':
							variant = 'secondary';
							break;
						default:
							variant = 'default';
					}
					return (
						<Controls.BadgeWP
							isBorderLess={ true }
							variant={ variant }
							style={ { textTransform: 'capitalize' } }
						>
							{ status }
						</Controls.BadgeWP>
					);
				},
			},
			{
				title: I18n.__( 'Action', 'ohmylms' ),
				dataIndex: 'action',
				key: 'action',
				render: ( _, record ) => (
					<Controls.DropdownMenuWP
						controls={ [
							{
								title: I18n.__( 'View', 'ohmylms' ),
								key: 'view',
								onClick: () => handleEditOrder( record?.id ),
								icon: <ViewIcon />,
							},
							{
								title: I18n.__( 'Delete', 'ohmylms' ),
								key: 'delete',
								onClick: () => openDeleteSingle( record?.id ),
								icon: <DeleteIcon />,
							},
						] }
						icon={ <IconWrapper.Icon icon={ MenuIcon.A } /> }
					/>
				),
			},
		];

		ReactHooks.useEffect( () => {
			let isMounted = true;
			if ( isMounted ) {
				fetchOrdersList();
			}
			return () => {
				isMounted = false;
			};
		}, [
			currentPage,
			perPage,
			searchTerm,
			statusFilter,
			dateFilter,
			paymentMethodFilter,
		] );

		ReactHooks.useEffect( () => {
			if ( ! loading && notificationMessage ) {
				openNotification( notificationStatus, notificationMessage );
			}
		}, [
			notificationMessage,
			notificationStatus,
			loading,
			openNotification,
		] );

		return (
			<React.Fragment>
				{ contextHolder }
				<Controls.ContainerWP>
					<PageHeader
						title={ I18n.__( 'Order Management', 'ohmylms' ) }
					/>
					<ContentCard
						isBorderless={ true }
						minHeight="calc(100vh - 200px)"
					>
						<Controls.SpacerWP padding={ 5 }>
							<Controls.SpacerWP marginBottom={ 4 }>
								{ selectedRowKeys.length > 0 ? (
									<BulkActionsBar
										items={ selectedRowKeys }
										setItems={ setSelectedRowKeys }
										bulksActions={ bulkActions }
									/>
								) : (
									<Controls.FlexWP
										align="center"
										justify="start"
										gap="2"
										wrap="wrap"
									>
										<Controls.FlexItemWP>
											<SearchInput
												placeholder={ I18n.__(
													'Search Orders',
													'ohmylms'
												) }
												onChange={ handleSearch }
											/>
										</Controls.FlexItemWP>
										<Controls.FlexItemWP>
											<SelectControl.A
												placeholder={ I18n.__(
													'Filter By Status',
													'ohmylms'
												) }
												onChange={ handleStatusFilter }
												value={ statusFilter }
												options={ statusOptions }
											/>
										</Controls.FlexItemWP>
										<Controls.FlexItemWP>
											<SelectControl.A
												placeholder={ I18n.__(
													'Payment Type',
													'ohmylms'
												) }
												onChange={ handlePaymentFilter }
												value={ paymentMethodFilter }
												options={ paymentOptions }
											/>
										</Controls.FlexItemWP>
										<Controls.FlexItemWP>
											<DateFilter
												placeholder={ I18n.__(
													'Filter By Days',
													'ohmylms'
												) }
												onChange={ ( val ) => {
													if (
														val !== 'custom_range'
													) {
														handleDateFilter( val );
													}
												} }
												onRangeChange={
													handleDateFilter
												}
											/>
										</Controls.FlexItemWP>
									</Controls.FlexWP>
								) }
							</Controls.SpacerWP>
							<TableModule.A
								rowKey="id"
								columns={ columns }
								dataSource={ orders || [] }
								rowSelection={ rowSelection }
								pagination={ false }
								loading={ loading }
								onChange={ handleTableChange }
								onRowMouseEnter={ ( record ) =>
									setHoveredRowId( record?.id )
								}
								onRowMouseLeave={ () =>
									setHoveredRowId( null )
								}
								locale={ {
									emptyText: (
										<EmptyContainer
											icon={ <EmptyIcon /> }
											title={ I18n.__(
												'No Order yet!',
												'ohmylms'
											) }
										/>
									),
								} }
							/>
							{ ! loading && Number( totalOrders ) > perPage && (
								<Pagination
									total={ totalOrders }
									currentPage={ currentPage }
									onPageChange={ handlePageChange }
									perPage={ perPage }
								/>
							) }
						</Controls.SpacerWP>
					</ContentCard>
				</Controls.ContainerWP>
				{ isDeleteModalOpen && (
					<ConfirmDialog
						title={
							selectedRowKeys.length > 1
								? I18n.__( 'Delete Orders', 'ohmylms' )
								: I18n.__( 'Delete order', 'ohmylms' )
						}
						description={
							selectedRowKeys.length > 1
								? I18n.__(
										'Are you sure you want to delete these orders?',
										'ohmylms'
									)
								: I18n.__(
										'Are you sure you want to delete order?',
										'ohmylms'
									)
						}
						onClose={ closeDeleteModal }
						onDelete={ handleDeleteConfirm }
						isOpen={ isDeleteModalOpen }
						isDelete={ true }
					/>
				) }
			</React.Fragment>
		);
	};
}
