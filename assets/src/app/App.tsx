import { HashRouter } from 'react-router-dom';
import { BootstrapProvider } from './bootstrap/BootstrapContext';
import type { AppBootstrap } from './bootstrap/config';
import { features as registeredFeatures } from './features/registry';
import type { FeatureDefinition } from './features/types';
import { AppRoutes } from './routes';

/**
 * Application root. A hash router is used because the page lives at `admin.php?page=ohmylms-app` and the
 * server must not see client routes.
 *
 * @param props           Component props.
 * @param props.bootstrap Validated bootstrap data.
 * @param props.features  Features to serve; defaults to the registry.
 */
export function App( {
	bootstrap,
	features = registeredFeatures,
}: {
	bootstrap: AppBootstrap;
	features?: readonly FeatureDefinition[];
} ) {
	return (
		<BootstrapProvider value={ bootstrap }>
			<HashRouter>
				<AppRoutes features={ features } />
			</HashRouter>
		</BootstrapProvider>
	);
}
