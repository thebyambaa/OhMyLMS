import { render } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import {
	createRegistry,
	type ExtensionRegistry,
} from '../../extensions/registry.mjs';
import { BootstrapProvider } from '../bootstrap/BootstrapContext';
import type { AppBootstrap } from '../bootstrap/config';
import { ExtensionsProvider } from '../extensions/context';
import type { FeatureDefinition } from '../features/types';
import { AppRoutes } from '../routes';

export function createBootstrap(
	capabilities: Record< string, boolean > = {}
): AppBootstrap {
	return {
		restRoot: 'http://example.test/wp-json/',
		restNonce: 'nonce',
		locale: 'en_US',
		adminUrl: 'http://example.test/wp-admin/',
		version: 'test',
		user: { id: 1, capabilities },
	};
}

/**
 * Render the app shell with the given features inside a memory router.
 *
 * @param features             Features to register.
 * @param options              Initial route, capabilities and extensions.
 * @param options.route        Initial router location.
 * @param options.capabilities Capabilities granted to the test user.
 * @param options.extensions   Extension registry; empty by default.
 * @return Testing Library render result.
 */
export function renderShell(
	features: readonly FeatureDefinition[],
	{
		route = '/',
		capabilities = {},
		extensions = createRegistry(),
	}: {
		route?: string;
		capabilities?: Record< string, boolean >;
		extensions?: ExtensionRegistry;
	} = {}
) {
	return render(
		<BootstrapProvider value={ createBootstrap( capabilities ) }>
			<ExtensionsProvider value={ extensions }>
				<MemoryRouter initialEntries={ [ route ] }>
					<AppRoutes features={ features } />
				</MemoryRouter>
			</ExtensionsProvider>
		</BootstrapProvider>
	);
}
