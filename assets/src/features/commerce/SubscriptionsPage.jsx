/**
 * SubscriptionsPage component (replaces recovered binding GQ).
 * Main route view for /subscriptions and /subscriptions/:page.
 */
import { createElement } from '@wordpress/element';
import { highlightAdminMenu } from '../../shared/utils';

export function createSubscriptionsPage( readRuntime ) {
	return function SubscriptionsPage() {
		const { HQ: SubscriptionListMemo, React } = readRuntime();

		highlightAdminMenu( 'ohmylms', 'subscriptions' );

		return (
			<React.Fragment>
				<SubscriptionListMemo />
			</React.Fragment>
		);
	};
}
