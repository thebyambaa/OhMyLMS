import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { home as homeIcon } from '@wordpress/icons';
import type { FeatureDefinition } from '../types';

export const home: FeatureDefinition = {
	id: 'home',
	title: __( 'Home', 'ohmylms' ),
	path: '/',
	icon: homeIcon,
	component: lazy(
		() => import( /* webpackChunkName: "admin-app-home" */ './HomePage' )
	),
};
