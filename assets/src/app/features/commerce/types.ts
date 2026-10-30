export type SortOrder = 'asc' | 'desc';

export type OrderStatus =
	| 'pending'
	| 'processing'
	| 'on-hold'
	| 'completed'
	| 'cancelled'
	| 'failed'
	| 'refunded';
export type OrderOrderBy = 'date' | 'id' | 'total';

/** A row of `GET /orders`. Money is a decimal string in `currency`. */
export interface OrderSummary {
	id: number;
	number: string;
	status: OrderStatus;
	currency: string;
	total: string;
	date_created: string | null;
	payment_method: string;
	payment_method_title: string;
	student_id: number;
	student_name: string;
}

export interface Billing {
	first_name: string;
	last_name: string;
	email: string;
	phone: string;
	address: string;
	city: string;
	state: string;
	postcode: string;
	country: string;
	vat_number: string;
}

export interface LineItem {
	id: number;
	course_id: number;
	name: string;
	quantity: number;
	subtotal: string;
	total: string;
}

export interface CouponLine {
	code: string;
	discount: string;
}

export interface RefundRecord {
	id: number;
	amount: string;
	reason: string;
	date_created: string | null;
}

export interface RelatedOrder {
	id: number;
	type: 'order' | 'subscription';
	relationship: string;
	status: string;
	total: string;
	date_created: string | null;
}

/** The body of `GET /orders/{id}`. */
export interface OrderDetail extends OrderSummary {
	parent_id: number;
	subtotal: string;
	discount_total: string;
	tax_amount: string;
	date_paid: string | null;
	date_completed: string | null;
	transaction_id: string;
	billing: Billing;
	line_items: LineItem[];
	coupon_lines: CouponLine[];
	refunds: RefundRecord[];
	total_refunded: string;
	remaining_refundable: string;
	related_orders: RelatedOrder[];
	allowed_statuses: OrderStatus[];
}

export interface OrderNote {
	id: number;
	content: string;
	added_by: string;
	date_created: string;
}

export interface OrderQuery {
	page: number;
	search: string;
	status: OrderStatus | 'any';
	paymentMethod: string;
	after: string;
	before: string;
	orderby: OrderOrderBy;
	order: SortOrder;
}

export type StatusCounts = Record< OrderStatus, number >;

export type DiscountType = 'percent' | 'flat-rate';
export type CouponStatus = 'publish' | 'draft';
export type CourseScope = 'all' | 'selected_course';
export type CouponOrderBy = 'date' | 'code' | 'usage_count';

export interface Coupon {
	id: number;
	code: string;
	title: string;
	description: string;
	status: CouponStatus;
	discount_type: DiscountType;
	amount: string;
	date_start: string | null;
	date_expires: string | null;
	usage_count: number;
	usage_limit: number | null;
	usage_limit_per_user: number | null;
	individual_use: boolean;
	course_id_type: CourseScope;
	course_ids: number[];
	date_created: string;
	currency: string;
}

/** Form state of the coupon editor. Dates use the `datetime-local` format (`YYYY-MM-DDTHH:mm`). */
export interface CouponForm {
	code: string;
	title: string;
	description: string;
	status: CouponStatus;
	discount_type: DiscountType;
	amount: string;
	date_start: string;
	date_expires: string;
	usage_limit: string;
	usage_limit_per_user: string;
	individual_use: boolean;
	course_id_type: CourseScope;
	course_ids: number[];
}

/** JSON body sent to create or update a coupon. */
export interface CouponPayload {
	code: string;
	title: string;
	description: string;
	status: CouponStatus;
	discount_type: DiscountType;
	amount: string;
	date_start: string | null;
	date_expires: string | null;
	usage_limit: number | null;
	usage_limit_per_user: number | null;
	individual_use: boolean;
	course_id_type: CourseScope;
	course_ids: number[];
}

export type CouponField = keyof CouponForm;

export interface CouponQuery {
	page: number;
	search: string;
	status: 'any' | CouponStatus;
	orderby: CouponOrderBy;
	order: SortOrder;
}

export interface CourseOption {
	id: number;
	title: string;
	status: string;
}
