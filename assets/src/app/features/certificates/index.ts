import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/** Certificate template list at `#/certificates`. */
export const certificates: FeatureDefinition = {
	id: 'certificates',
	title: __( 'Certificates', 'ohmylms' ),
	path: '/certificates',
	group: 'engagement',
	tab: 'gamification',
	section: 'certificates',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-certificates" */ './CertificatesPage'
			)
	),
};

/**
 * Issued certificates at `#/certificates/issued` (Gamification › Issued certificates); the old
 * `/certificates?list=issued` opens it. More literal segments than the editor's path, so it wins.
 */
export const certificatesIssued: FeatureDefinition = {
	id: 'certificates-issued',
	title: __( 'Issued certificates', 'ohmylms' ),
	path: '/certificates/issued',
	group: 'engagement',
	tab: 'gamification',
	section: 'issued-certificates',
	capability: 'manage_options',
	hidden: true,
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-certificates" */ './IssuedCertificatesList'
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
	group: 'engagement',
	tab: 'gamification',
	section: 'certificates',
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
