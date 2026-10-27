import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useMenuHighlight } from '../menuHighlight';
import { MEMBERSHIP_TABS } from './membershipRoutes.mjs';
import { MovableTabs } from '../navigation/MovableTabs';

/**
 * Share Membership navigation across plans, commerce lists and their detail screens.
 * @param Screen
 * @param active
 */
export function membershipScreen( Screen, active ) {
	function MembershipScreen( props ) {
		useMenuHighlight( 'a[href$="#/memberships"]' );
		const labels = {
			plans: __( 'Plans', 'ohmylms' ),
			coupons: __( 'Coupons', 'ohmylms' ),
			orders: __( 'Orders', 'ohmylms' ),
			subscriptions: __( 'Subscriptions', 'ohmylms' ),
		};
		return (
			<section className="ohmylms-membership">
				<header className="ohmylms-content-hub-header">
					<h1>{ __( 'Membership', 'ohmylms' ) }</h1>
				</header>
				<MovableTabs
					scope="memberships"
					tabs={ MEMBERSHIP_TABS }
					labels={ labels }
					active={ active }
					label={ __( 'Membership sections', 'ohmylms' ) }
				/>
				<Screen { ...props } />
			</section>
		);
	}
	MembershipScreen.displayName = `Membership(${ active })`;
	return MembershipScreen;
}
