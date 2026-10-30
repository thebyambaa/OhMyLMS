import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

export const taxonomies: FeatureDefinition = {
	id: 'taxonomies',
	title: __( 'Categories and tags', 'ohmylms' ),
	path: '/taxonomies',
	group: 'content',
	tab: 'curriculum',
	section: 'taxonomies',
	capability: 'edit_posts',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-taxonomies" */ './TaxonomiesPage'
			)
	),
};
