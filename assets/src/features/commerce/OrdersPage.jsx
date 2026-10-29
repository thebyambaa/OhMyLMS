/**
 * OrdersPage component (replaces recovered binding KY).
 * Main route element for /orders and /orders/:page.
 */
import { createElement } from '@wordpress/element';
import { highlightAdminMenu } from '../../shared/utils';

export function createOrdersPage( readRuntime ) {
	return function OrdersPage() {
		const { $Y: OrderListMemo, React } = readRuntime();
		highlightAdminMenu( 'ohmylms', 'orders' );

		return (
			<React.Fragment>
				<OrderListMemo />
			</React.Fragment>
		);
	};
}
