/**
 * OrderDetails component (replaces recovered binding FQ).
 * Full edit view for an order (/order-edit/:id).
 */
import { createElement } from '@wordpress/element';

export function createOrderDetails( readRuntime ) {
	return function OrderDetails( { id = null } ) {
		const {
			CQ: CustomerProfile,
			EQ: OrderStatus,
			Ge: decodeEntities,
			I: Controls,
			Nr: BackButton,
			PQ: OrderGeneral,
			RQ: CustomerHistory,
			React,
			T: StoreModule,
			XY: OrderHeader,
			_: SkeletonModule,
			b: I18n,
			cQ: OrderItems,
			eQ: OrderBilling,
			f: Router,
			fQ: OrderNotes,
			g: ReactHooks,
			kQ: RelatedOrders,
			y: WordPressData,
			z: Notifications,
		} = readRuntime();

		const navigate = Router.Zp();
		const order = WordPressData.useSelect(
			( select ) => select( StoreModule.default ).getOrder(),
			[ id ]
		);
		const { fetchOrder } = WordPressData.useDispatch( StoreModule.default );

		const [ loading, setLoading ] = ReactHooks.useState( true );
		const [ notFound, setNotFound ] = ReactHooks.useState( false );

		const notifications = Notifications.A();
		const openNotification = notifications.openNotificationWithIcon;
		const contextHolder = notifications.contextHolder;
		const notificationMessage = WordPressData.useSelect(
			( select ) =>
				select( StoreModule.default ).getNotificationMessage(),
			[]
		);
		const notificationStatus = WordPressData.useSelect(
			( select ) => select( StoreModule.default ).getNotificationStatus(),
			[]
		);

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

		ReactHooks.useEffect( () => {
			let isMounted = true;
			const load = async () => {
				if ( ! id ) {
					if ( isMounted ) {
						setLoading( false );
					}
					return;
				}
				setLoading( true );
				try {
					await fetchOrder( id );
					if ( isMounted ) {
						setLoading( false );
						setNotFound( false );
					}
				} catch ( err ) {
					if ( isMounted ) {
						setLoading( false );
						if ( err?.status === 404 ) {
							setNotFound( true );
						}
					}
				}
			};
			load();
			return () => {
				isMounted = false;
			};
		}, [ id, fetchOrder ] );

		if ( loading ) {
			return <SkeletonModule.A active={ true } />;
		}

		if ( ! order || notFound ) {
			return (
				<Controls.CardWP>
					<Controls.SpacerWP padding={ 6 } marginBottom={ 0 }>
						<Controls.EmptyWP
							description={ I18n.__(
								'No Orders Found',
								'ohmylms'
							) }
						/>
					</Controls.SpacerWP>
				</Controls.CardWP>
			);
		}

		return (
			<Controls.ContainerWP isFullWidth={ true }>
				<Controls.SpacerWP paddingTop={ 5 } />
				{ contextHolder }
				<Controls.FlexWP gap={ 2 } align="center" justify="flex-start">
					<BackButton onClick={ () => navigate( '/orders' ) } />
					<Controls.HeadingWP
						level={ 3 }
						size={ 18 }
						weight={ 600 }
						color="#000D25"
					>
						{ I18n.__( 'Order Details', 'ohmylms' ) }
					</Controls.HeadingWP>
				</Controls.FlexWP>
				<Controls.SpacerWP marginBottom={ 3 } />
				<Controls.CardWP isBorderless={ true }>
					<Controls.SpacerWP padding={ 6 } marginBottom={ 0 }>
						<Controls.FlexWP
							className="ohmylms-order-details"
							justify="start"
							align="start"
							gap={ 3 }
						>
							<Controls.FlexItemWP
								className="ohmylms-order-details-left"
								style={ { width: 'calc(70% - 12px)' } }
							>
								<Controls.CardWP
									isBorderless={ true }
									variant="secondary"
								>
									<Controls.SpacerWP
										padding={ 4 }
										marginBottom={ 0 }
									>
										<OrderHeader
											order={ order }
											status={ order.status }
										/>
									</Controls.SpacerWP>
								</Controls.CardWP>
								<Controls.SpacerWP marginBottom={ 3 } />
								<Controls.FlexWP
									justify="space-between"
									align="stretch"
									gap={ 3 }
									className="ohmylms-order-details-general-billing"
								>
									<Controls.FlexBlockWP>
										<Controls.CardWP
											isBorderless={ true }
											variant="secondary"
											style={ { height: '100%' } }
										>
											<Controls.SpacerWP
												paddingX={ 4 }
												paddingY={ 5 }
												marginBottom={ 0 }
											>
												<OrderGeneral
													student_name={ decodeEntities(
														order.student_name
													) }
													student_email={
														order.student_email
													}
													student_id={
														order.student_id
													}
												/>
											</Controls.SpacerWP>
										</Controls.CardWP>
									</Controls.FlexBlockWP>
									<Controls.FlexBlockWP>
										<Controls.CardWP
											isBorderless={ true }
											variant="secondary"
											style={ { height: '100%' } }
										>
											<Controls.SpacerWP
												paddingX={ 4 }
												paddingY={ 5 }
												marginBottom={ 0 }
											>
												<OrderBilling
													order={ order }
													address={ order.address }
													email={
														order.student_email
													}
												/>
											</Controls.SpacerWP>
										</Controls.CardWP>
									</Controls.FlexBlockWP>
								</Controls.FlexWP>
								<Controls.SpacerWP marginBottom={ 3 } />
								<Controls.CardWP
									isBorderless={ true }
									variant="secondary"
								>
									<Controls.SpacerWP
										paddingY={ 6 }
										paddingX={ 4 }
										marginBottom={ 0 }
									>
										<OrderItems
											order={ order }
											items={ order.line_items }
											coupon={ order.coupon_lines }
											subtotal={ order.subtotal }
											total={ order.total }
											paid={ order.paid }
											discount={ order.cart_discount }
											taxAmount={ order.tax_amount }
											taxRate={ order.tax_rate }
										/>
									</Controls.SpacerWP>
								</Controls.CardWP>
								<Controls.SpacerWP marginBottom={ 3 } />
								{ Array.isArray( order.related_orders ) &&
									order.related_orders.length > 0 && (
										<Controls.CardWP
											isBorderless={ true }
											variant="secondary"
										>
											<Controls.SpacerWP
												paddingY={ 6 }
												paddingX={ 4 }
												marginBottom={ 0 }
											>
												<RelatedOrders
													relatedOrders={
														order.related_orders
													}
												/>
											</Controls.SpacerWP>
										</Controls.CardWP>
									) }
							</Controls.FlexItemWP>
							<Controls.FlexItemWP
								className="ohmylms-order-details-right"
								style={ { width: '30%' } }
							>
								<Controls.CardWP
									isBorderless={ true }
									variant="secondary"
								>
									<Controls.SpacerWP
										paddingX={ 4 }
										paddingY={ 5 }
										marginBottom={ 0 }
									>
										<OrderStatus
											status={ order.status }
											order={ order }
										/>
									</Controls.SpacerWP>
								</Controls.CardWP>
								<Controls.SpacerWP marginBottom={ 3 } />
								<Controls.CardWP
									isBorderless={ true }
									variant="secondary"
								>
									<Controls.SpacerWP
										paddingX={ 4 }
										paddingY={ 5 }
										marginBottom={ 0 }
									>
										<CustomerProfile
											student_name={ decodeEntities(
												order.student_name
											) }
											student_email={
												order.student_email
											}
											student_id={ order.student_id }
											student_image={
												order.student_image
											}
										/>
									</Controls.SpacerWP>
								</Controls.CardWP>
								<Controls.SpacerWP marginBottom={ 3 } />
								<Controls.CardWP
									isBorderless={ true }
									variant="secondary"
								>
									<Controls.SpacerWP
										paddingX={ 4 }
										paddingY={ 5 }
										marginBottom={ 0 }
									>
										<CustomerHistory order={ order } />
									</Controls.SpacerWP>
								</Controls.CardWP>
								<Controls.SpacerWP marginBottom={ 3 } />
								<Controls.CardWP
									isBorderless={ true }
									variant="secondary"
								>
									<Controls.SpacerWP
										paddingX={ 4 }
										paddingY={ 5 }
										marginBottom={ 0 }
									>
										<OrderNotes
											notes={ order.order_notes }
											order={ order }
										/>
									</Controls.SpacerWP>
								</Controls.CardWP>
							</Controls.FlexItemWP>
						</Controls.FlexWP>
					</Controls.SpacerWP>
				</Controls.CardWP>
			</Controls.ContainerWP>
		);
	};
}
