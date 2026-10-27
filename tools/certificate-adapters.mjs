import { createComponentAdapter } from './component-adapter.mjs';

export const adaptCertificates = createComponentAdapter( {
	manifest: new URL(
		'../assets/src/features/certificates/components.json',
		import.meta.url
	),
	namespace: 'certificateComponents',
	label: 'certificate',
	declarations: true,
} );
