/**
 * SubscriptionsPage component (replaces recovered binding GQ).
 * Main route view for /subscriptions and /subscriptions/:page.
 */
import { createElement } from '@wordpress/element';

export function createSubscriptionsPage( readRuntime ) {
	return function SubscriptionsPage() {
		const {
			HG: setScreenId,
			HQ: SubscriptionListMemo,
			React,
		} = readRuntime();

		setScreenId( 'ohmylms', 'subscriptions' );

		return (
			<React.Fragment>
				<SubscriptionListMemo />
			</React.Fragment>
		);
	};
}
