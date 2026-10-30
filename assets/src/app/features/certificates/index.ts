import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/** Certificate template list at `#/certificates`. */
export const certificates: FeatureDefinition = {
	id: 'certificates',
	title: __( 'Certificates', 'ohmylms' ),
	path: '/certificates',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-certificates" */ './CertificatesPage'
			)
	),
};

/**
 * Template editor at `#/certificates/<id>`. A separate route entry because the registry does not
 * accept wildcard paths; it shares the chunk with the list.
 */
export const certificateEditor: FeatureDefinition = {
	id: 'certificates-editor',
	title: __( 'Certificate template', 'ohmylms' ),
	path: '/certificates/:id',
	extensionRoute: '/certificate-edit/:id',
	hidden: true,
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-certificates" */ './CertificateEditorPage'
			)
	),
};
