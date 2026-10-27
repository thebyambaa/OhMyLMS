/** Membership sections and the existing addresses retained for links and bookmarks. */
export const MEMBERSHIP_TABS = [
	{ id: 'plans', path: '/memberships', source: '/memberships' },
	{ id: 'coupons', path: '/memberships/coupons', source: '/coupons' },
	{ id: 'orders', path: '/memberships/orders', source: '/orders' },
	{
		id: 'subscriptions',
		path: '/memberships/subscriptions',
		source: '/subscriptions',
	},
];

export const MEMBERSHIP_SCREENS = {
	'/memberships': 'plans',
	'/coupons': 'coupons',
	'/orders': 'orders',
	'/orders/:page': 'orders',
	'/order-edit/:id': 'orders',
	'/subscriptions': 'subscriptions',
	'/subscriptions/:page': 'subscriptions',
	'/subscription-edit/:id': 'subscriptions',
};

export function membershipRoutes( routes, screen ) {
	return MEMBERSHIP_TABS.filter( ( tab ) => tab.path !== tab.source ).flatMap(
		( tab ) => {
			const route = routes.find( ( entry ) => entry.path === tab.source );
			if ( ! route ) {
				return [];
			}
			const result = [
				{ path: tab.path, element: screen( route.element, tab.id ) },
			];
			const paginated = routes.find(
				( entry ) => entry.path === `${ tab.source }/:page`
			);
			if ( paginated ) {
				result.push( {
					path: `${ tab.path }/:page`,
					element: screen( paginated.element, tab.id ),
				} );
			}
			return result;
		}
	);
}
