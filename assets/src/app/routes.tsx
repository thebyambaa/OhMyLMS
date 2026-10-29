import { Suspense } from '@wordpress/element';
import { Route, Routes } from 'react-router-dom';
import { useBootstrap } from './bootstrap/BootstrapContext';
import { getVisibleFeatures } from './features/registry';
import type { FeatureDefinition } from './features/types';
import { AppLayout } from './layout/AppLayout';
import { ErrorBoundary } from './layout/ErrorBoundary';
import { NotFound } from './layout/NotFound';
import { RouteFallback } from './layout/RouteFallback';

function FeatureRoute( { feature }: { feature: FeatureDefinition } ) {
	const Page = feature.component;
	return (
		<ErrorBoundary resetKey={ feature.id }>
			<Suspense fallback={ <RouteFallback /> }>
				<Page />
			</Suspense>
		</ErrorBoundary>
	);
}

/**
 * Route table: one lazy, error-isolated route per visible feature, and a 404.
 * Router-agnostic, so tests render it inside a `MemoryRouter`.
 *
 * @param props          Component props.
 * @param props.features Registered features, before capability filtering.
 */
export function AppRoutes( {
	features,
}: {
	features: readonly FeatureDefinition[];
} ) {
	const { user } = useBootstrap();
	const visible = getVisibleFeatures( features, user.capabilities );

	return (
		<Routes>
			<Route element={ <AppLayout features={ visible } /> }>
				{ visible.map( ( feature ) => (
					<Route
						key={ feature.id }
						path={ feature.path }
						element={ <FeatureRoute feature={ feature } /> }
					/>
				) ) }
				<Route
					path="*"
					element={
						<NotFound homeTo={ visible[ 0 ]?.path ?? '/' } />
					}
				/>
			</Route>
		</Routes>
	);
}
