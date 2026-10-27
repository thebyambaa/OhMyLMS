/**
 * OrdersPage component (replaces recovered binding KY).
 * Main route element for /orders and /orders/:page.
 */
import { createElement } from '@wordpress/element';

export function createOrdersPage( readRuntime ) {
	return function OrdersPage() {
		const { $Y: OrderListMemo, HG: setScreenId, React } = readRuntime();
		setScreenId( 'ohmylms', 'orders' );

		return (
			<React.Fragment>
				<OrderListMemo />
			</React.Fragment>
		);
	};
}
