import { Suspense } from '@wordpress/element';
import { Route, Routes, useLocation, useParams } from 'react-router-dom';
import { useBootstrap } from './bootstrap/BootstrapContext';
import { ExtensionSlot } from './extensions/ExtensionSlot';
import { getVisibleFeatures } from './features/registry';
import type { FeatureDefinition } from './features/types';
import { AppLayout } from './layout/AppLayout';
import { ErrorBoundary } from './layout/ErrorBoundary';
import { NotFound } from './layout/NotFound';
import { RouteFallback } from './layout/RouteFallback';

/**
 * One screen with its extension surfaces: `admin.screen.before`/`after` slots, the editor panels registered
 * for its extension route and, on Integrations, integration panels. Extensions receive the route params,
 * `route` (the extension route pattern) and `hash`: the screen's route as the hash router had it
 * (`#/quizzes/5`), so extension API v1 code that reads it keeps working.
 *
 * @param props         Component props.
 * @param props.feature Feature to render.
 */
function FeatureRoute( { feature }: { feature: FeatureDefinition } ) {
	const Page = feature.component;
	const params = useParams();
	const { pathname, search } = useLocation();
	const route = feature.extensionRoute ?? feature.path;
	const context = { ...params, route, hash: `#${ pathname }${ search }` };
	return (
		<ErrorBoundary resetKey={ feature.id }>
			<ExtensionSlot name="admin.screen.before" context={ context } />
			<Suspense fallback={ <RouteFallback /> }>
				<Page />
			</Suspense>
			<ExtensionSlot name="admin.screen.after" context={ context } />
			<ExtensionSlot
				kind="editor-panel"
				name={ route }
				context={ context }
			/>
			{ '/integrations' === route && (
				<ExtensionSlot
					kind="integration"
					name="integrations"
					context={ context }
				/>
			) }
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
			<Route element={ <AppLayout /> }>
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
