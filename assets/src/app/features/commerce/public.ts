import { lazy } from '@wordpress/element';

/** Point price of a course, for the course editor's Settings tab (render inside `Suspense`). */
export const PointPricePanel = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-point-price" */ './points/PointPricePanel'
	).then( ( module ) => ( { default: module.PointPricePanel } ) )
);
