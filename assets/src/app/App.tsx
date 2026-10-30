import { useMemo } from '@wordpress/element';
import type { ExtensionRegistry } from './extensions/registry.mjs';
import { BootstrapProvider } from './bootstrap/BootstrapContext';
import type { AppBootstrap } from './bootstrap/config';
import { ExtensionsProvider } from './extensions/context';
import { features as registeredFeatures } from './features/registry';
import type { FeatureDefinition } from './features/types';
import { extensionGroupOf } from './extensions/adminPages';
import type { AddressOptions, AdminPlace } from './routing/address';
import { QueryRouter } from './routing/QueryRouter';
import { AppRoutes } from './routes';

/**
 * Application root. Each of the six admin pages mounts it with the page, tab and section the server
 * printed; the router is keyed on the query string (routing/address.ts).
 *
 * @param props            Component props.
 * @param props.bootstrap  Validated bootstrap data.
 * @param props.place      Where the server placed the document.
 * @param props.extensions Extension registry published by the host entry; empty when absent.
 * @param props.features   Features to serve; defaults to the registry.
 */
export function App( {
	bootstrap,
	place,
	extensions,
	features = registeredFeatures,
}: {
	bootstrap: AppBootstrap;
	place: AdminPlace;
	extensions?: ExtensionRegistry;
	features?: readonly FeatureDefinition[];
} ) {
	const options = useMemo< AddressOptions >(
		() => ( {
			extensionGroup: ( id ) => extensionGroupOf( extensions, id ),
		} ),
		[ extensions ]
	);
	const routes = (
		<QueryRouter features={ features } place={ place } options={ options }>
			<AppRoutes features={ features } />
		</QueryRouter>
	);
	return (
		<BootstrapProvider value={ bootstrap }>
			{ extensions ? (
				<ExtensionsProvider value={ extensions }>
					{ routes }
				</ExtensionsProvider>
			) : (
				routes
			) }
		</BootstrapProvider>
	);
}
