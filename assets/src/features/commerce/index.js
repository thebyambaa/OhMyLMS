import { createOrdersPage } from './OrdersPage';
import { createOrderList } from './OrderList';
import { createOrderHeader } from './OrderHeader';
import { createOrderBilling } from './OrderBilling';
import { createOrderItems } from './OrderItems';
import { createOrderNotes } from './OrderNotes';
import { createOrderStatus } from './OrderStatus';
import { createCustomerProfile } from './CustomerProfile';
import { createCustomerHistory } from './CustomerHistory';
import { createOrderGeneral } from './OrderGeneral';
import { createRelatedOrders } from './RelatedOrders';
import { createOrderDetails } from './OrderDetails';
import { createSubscriptionsPage } from './SubscriptionsPage';
import { createSubscriptionList } from './SubscriptionList';
import { createSubscriptionStatus } from './SubscriptionStatus';
import { createSubscriptionNotes } from './SubscriptionNotes';
import { createSubscriptionDetails } from './SubscriptionDetails';
import { createSubscriptionEditPage } from './SubscriptionEditPage';
import { createCouponRefreshIcon } from './CouponRefreshIcon';
import { createCouponModal } from './CouponModal';
import { createCouponList } from './CouponList';
import { createCouponsPage } from './CouponsPage';
export const commerceComponents = {
	OrdersPage: createOrdersPage,
	OrderList: createOrderList,
	OrderHeader: createOrderHeader,
	OrderBilling: createOrderBilling,
	OrderItems: createOrderItems,
	OrderNotes: createOrderNotes,
	OrderStatus: createOrderStatus,
	CustomerProfile: createCustomerProfile,
	CustomerHistory: createCustomerHistory,
	OrderGeneral: createOrderGeneral,
	RelatedOrders: createRelatedOrders,
	OrderDetails: createOrderDetails,
	SubscriptionsPage: createSubscriptionsPage,
	SubscriptionList: createSubscriptionList,
	SubscriptionStatus: createSubscriptionStatus,
	SubscriptionNotes: createSubscriptionNotes,
	SubscriptionDetails: createSubscriptionDetails,
	SubscriptionEditPage: createSubscriptionEditPage,
	CouponRefreshIcon: createCouponRefreshIcon,
	CouponModal: createCouponModal,
	CouponList: createCouponList,
	CouponsPage: createCouponsPage,
};
