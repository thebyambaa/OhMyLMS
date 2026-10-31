import { lazy } from '@wordpress/element';

/** Point price of a course, for the course editor's Settings tab (render inside `Suspense`). */
export const PointPricePanel = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-point-price" */ './points/PointPricePanel'
	).then( ( module ) => ( { default: module.PointPricePanel } ) )
);

/** Cohorts screen, shown as a panel of Commerce › Memberships. */
export const CohortsPanel = lazy(
	() =>
		import(
			/* webpackChunkName: "admin-app-cohorts" */ './cohorts/CohortsPanel'
		)
);

/** Automations screen, shown as a panel of Commerce › Memberships. */
export const AutomationsPanel = lazy(
	() =>
		import(
			/* webpackChunkName: "admin-app-automations" */ './automations/AutomationsPanel'
		)
);
