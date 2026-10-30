import { HashRouter } from 'react-router-dom';
import type { ExtensionRegistry } from '../extensions/registry.mjs';
import { BootstrapProvider } from './bootstrap/BootstrapContext';
import type { AppBootstrap } from './bootstrap/config';
import { ExtensionsProvider } from './extensions/context';
import { features as registeredFeatures } from './features/registry';
import type { FeatureDefinition } from './features/types';
import { AppRoutes } from './routes';

/**
 * Application root. A hash router is used because the page lives at `admin.php?page=ohmylms-app` and the
 * server must not see client routes.
 *
 * @param props            Component props.
 * @param props.bootstrap  Validated bootstrap data.
 * @param props.extensions Extension registry published by the host entry; empty when absent.
 * @param props.features   Features to serve; defaults to the registry.
 */
export function App( {
	bootstrap,
	extensions,
	features = registeredFeatures,
}: {
	bootstrap: AppBootstrap;
	extensions?: ExtensionRegistry;
	features?: readonly FeatureDefinition[];
} ) {
	const routes = (
		<HashRouter>
			<AppRoutes features={ features } />
		</HashRouter>
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
