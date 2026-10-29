/**
 * SubscriptionList component (replaces recovered binding VQ).
 * Displays subscriptions table, Pro overlay gating, search, and pagination.
 */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
import * as Controls from '../../shared/controls';
import { EmptyState, NoDataIllustration, PageHeader } from '../../shared/media';
export function createSubscriptionList( readRuntime ) {
	return function SubscriptionList() {
		const {
			Br: ViewIcon,
			Cm: SearchInput,
			Ea: ContentCard,
			React,
			T: StoreModule,
			VY: formatDateTime,
			b: I18n,
			f: Router,
			fN: Pagination,
			g: ReactHooks,
			hN: BulkActionsBar,
			v: RouterLink,
			y: WordPressData,
		} = readRuntime();
		const dispatch = WordPressData.useDispatch( StoreModule.default );
		const navigate = Router.Zp();
		const notifications = useNotifications();
		const openNotification = notifications.openNotificationWithIcon;
		const contextHolder = notifications.contextHolder;
		const subscriptions = WordPressData.useSelect(
			( select ) => select( StoreModule.default ).selectSubscriptions(),
			[]
		);
		const pagination = WordPressData.useSelect(
			( select ) =>
				select( StoreModule.default ).selectSubscriptionsPagination(),
			[]
		);
		const totalSubscriptions = pagination?.totalSubscriptions || 0;
		const totalPages = pagination?.totalPages || 1;
		const loading = WordPressData.useSelect(
			( select ) =>
				select( StoreModule.default ).selectSubscriptionsLoading(),
			[]
		);
		const notificationMessage = WordPressData.useSelect(
			( select ) =>
				select( StoreModule.default ).getNotificationMessage(),
			[]
		);
		const notificationStatus = WordPressData.useSelect(
			( select ) => select( StoreModule.default ).getNotificationStatus(),
			[]
		);
		const [ selectedRowKeys, setSelectedRowKeys ] = ReactHooks.useState(
			[]
		);
		const [ currentPage, setCurrentPage ] = ReactHooks.useState( 1 );
		const [ perPage ] = ReactHooks.useState( 10 );
		const [ searchTerm, setSearchTerm ] = ReactHooks.useState( '' );
		const [ , setIsDeleteModalOpen ] = ReactHooks.useState( false );
		const [ sorting, setSorting ] = ReactHooks.useState( {
			field: 'start_date',
			order: 'DESC',
		} );
		const fetchSubscriptionsList = ReactHooks.useCallback(
			( orderby = sorting.field, order = sorting.order ) => {
				const params = {
					page: currentPage,
					per_page: perPage,
					search: searchTerm,
					orderby,
					order,
				};
				dispatch.fetchSubscriptions( params );
			},
			[ dispatch, currentPage, perPage, searchTerm, sorting ]
		);
		ReactHooks.useEffect( () => {
			{
				fetchSubscriptionsList();
			}
		}, [ fetchSubscriptionsList ] );
		ReactHooks.useEffect( () => {
			if (
				! loading &&
				notificationMessage &&
				notificationMessage.length > 0
			) {
				openNotification( notificationStatus, notificationMessage );
			}
		}, [
			loading,
			notificationMessage,
			notificationStatus,
			openNotification,
		] );
		const handleSearch = ReactHooks.useCallback( ( value ) => {
			setSearchTerm( value );
			setCurrentPage( 1 );
		}, [] );
		const handlePageChange = ReactHooks.useCallback( ( page ) => {
			setCurrentPage( page );
			setSelectedRowKeys( [] );
		}, [] );
		const handleViewSubscription = ReactHooks.useCallback(
			( id ) => {
				navigate( `/subscription-edit/${ id }` );
			},
			[ navigate ]
		);
		const handleTableChange = ReactHooks.useCallback(
			( paginationInfo, filters, sorter ) => {
				const field = sorter.field || 'start_date';
				const order = sorter.order === 'ascend' ? 'ASC' : 'DESC';
				setCurrentPage( 1 );
				setSorting( {
					field,
					order,
				} );
			},
			[ fetchSubscriptionsList ]
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
		const columns = ReactHooks.useMemo(
			() => [
				{
					title: I18n.__( 'ID', 'ohmylms' ),
					dataIndex: 'id',
					key: 'id',
					sorter: true,
					render: ( id ) => (
						<RouterLink.Link to={ `/subscription-edit/${ id }` }>
							#{ id || 'N/A' }
						</RouterLink.Link>
					),
				},
				{
					title: I18n.__( 'Student', 'ohmylms' ),
					dataIndex: 'student_name',
					key: 'student_name',
					sorter: true,
					render: ( name, record ) => {
						if ( record.student_id ) {
							return (
								<RouterLink.Link
									to={ `/students/${ record.student_id }/report` }
								>
									{ name ||
										record.customer_name ||
										I18n.__( 'N/A', 'ohmylms' ) }
								</RouterLink.Link>
							);
						}
						return (
							name ||
							record.customer_name ||
							I18n.__( 'N/A', 'ohmylms' )
						);
					},
				},
				{
					title: I18n.__( 'Start Date', 'ohmylms' ),
					dataIndex: 'schedule_start_date',
					key: 'schedule_start_date',
					sorter: true,
					render: ( date ) => formatDateTime( date ) || '-',
				},
				{
					title: I18n.__( 'Next Payment', 'ohmylms' ),
					dataIndex: 'schedule_next_payment_date',
					key: 'schedule_next_payment_date',
					sorter: true,
					render: ( date ) => formatDateTime( date ) || '-',
				},
				{
					title: I18n.__( 'End Date', 'ohmylms' ),
					dataIndex: 'schedule_end_date',
					key: 'schedule_end_date',
					sorter: true,
					render: ( date ) => formatDateTime( date ) || '-',
				},
				{
					title: I18n.__( 'Last Payment Date', 'ohmylms' ),
					dataIndex: 'last_payment_date',
					key: 'last_payment_date',
					sorter: true,
					render: ( date ) => formatDateTime( date ) || '-',
				},
				{
					title: I18n.__( 'Status', 'ohmylms' ),
					dataIndex: 'status',
					key: 'status',
					render: ( status ) => {
						let variant = 'default';
						switch ( status ) {
							case 'active':
								variant = 'success';
								break;
							case 'pending':
							case 'on-hold':
								variant = 'warning';
								break;
							case 'cancelled':
							case 'expired':
								variant = 'danger';
								break;
							default:
								variant = 'default';
						}
						return (
							<Controls.BadgeWP
								isBorderLess={ true }
								variant={ variant }
								style={ {
									textTransform: 'capitalize',
								} }
							>
								{ status
									? status
											.replace( 'ohmylms-', '' )
											.replace( '-', ' ' )
											.replace( /^(\w)/, ( c ) =>
												c.toUpperCase()
											)
									: I18n.__( 'N/A', 'ohmylms' ) }
							</Controls.BadgeWP>
						);
					},
				},
				{
					title: I18n.__( 'Action', 'ohmylms' ),
					key: 'action',
					render: ( _, record ) => (
						<Controls.ButtonWP
							onClick={ () =>
								handleViewSubscription( record.id )
							}
						>
							<ViewIcon />
						</Controls.ButtonWP>
					),
				},
			],
			[ handleViewSubscription ]
		);
		ReactHooks.useMemo(
			() => ( {
				selectedRowKeys,
				onChange: setSelectedRowKeys,
			} ),
			[ selectedRowKeys ]
		);
		return (
			<React.Fragment>
				{ contextHolder }
				<Controls.ContainerWP>
					<PageHeader
						title={ I18n.__( 'Subscriptions', 'ohmylms' ) }
					/>
					<ContentCard
						isBorderless={ true }
						style={ {
							minHeight: '408px',
						} }
					>
						<Controls.SpacerWP padding={ 5 } marginBottom={ 0 }>
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
													'Search Subscriptions',
													'ohmylms'
												) }
												onChange={ handleSearch }
											/>
										</Controls.FlexItemWP>
									</Controls.FlexWP>
								) }
							</Controls.SpacerWP>
							<Controls.TableWP
								rowKey="id"
								columns={ columns }
								dataSource={ subscriptions || [] }
								pagination={ false }
								loading={ loading }
								onChange={ handleTableChange }
								locale={ {
									emptyText: (
										<EmptyState
											icon={ <NoDataIllustration /> }
											title={ I18n.__(
												'No Subscriptions Found',
												'ohmylms'
											) }
											text={
												searchTerm
													? I18n.__(
															'Try adjusting your search or filters.',
															'ohmylms'
														)
													: I18n.__(
															'There are no subscriptions to display yet.',
															'ohmylms'
														)
											}
										/>
									),
								} }
							/>
							{ ! loading &&
								totalSubscriptions > 0 &&
								totalPages > 1 && (
									<Pagination
										total={ totalSubscriptions }
										currentPage={ currentPage }
										onPageChange={ handlePageChange }
										perPage={ perPage }
									/>
								) }
						</Controls.SpacerWP>
					</ContentCard>
				</Controls.ContainerWP>
			</React.Fragment>
		);
	};
}
