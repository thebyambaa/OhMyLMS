import { Notice } from '@wordpress/components';
import { Suspense, useCallback, useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useSearchParams } from 'react-router-dom';
import { ErrorBoundary } from '../../layout/ErrorBoundary';
import { getScopes } from './shell/api';
import { describeWidgetError } from './shell/errors';
import { chooseScope, parsePeriod } from './shell/model';
import type { PeriodDays, WidgetContext } from './shell/model';
import { PeriodSwitcher } from './shell/PeriodSwitcher';
import { ScopePicker } from './shell/ScopePicker';
import { WidgetCard } from './shell/WidgetCard';
import { useRequest } from './useRequest';
import { dashboardWidgets } from './widgets';
import type { WidgetRegistry } from './widgets/registry';
import './dashboard.css';

/**
 * The dashboard: a scope picker (from `GET /dashboard/scopes`), a 7/30/90-day period switcher and every
 * widget of the registry that applies to the scope, each in its own error boundary. Scope and period live
 * in the URL (`?scope=school:3&period=7`), so reloads and shared links keep them.
 *
 * @param props          Component props.
 * @param props.registry Widgets to render; the built-in registry by default.
 */
export function DashboardShell( {
	registry = dashboardWidgets,
}: {
	registry?: WidgetRegistry;
} ) {
	const [ params, setParams ] = useSearchParams();
	const scopes = useRequest( ( signal ) => getScopes( signal ), 'scopes' );
	const period = parsePeriod( params.get( 'period' ) );
	const scope = scopes.data
		? chooseScope( scopes.data, params.get( 'scope' ) )
		: null;
	const siteAdmin = scopes.data?.siteAdmin ?? false;

	const update = useCallback(
		( name: string, value: string ) => {
			const next = new URLSearchParams( params );
			next.set( name, value );
			setParams( next, { replace: true } );
		},
		[ params, setParams ]
	);

	const context = useMemo< WidgetContext | null >(
		() => ( scope ? { scope, period, siteAdmin } : null ),
		[ scope, period, siteAdmin ]
	);

	return (
		<div className="ohmylms-dashboard">
			{ scopes.loading && ! scopes.data && (
				<p role="status">{ __( 'Loading dashboard…', 'ohmylms' ) }</p>
			) }
			{ scopes.error && (
				<Notice status="error" isDismissible={ false }>
					{ describeWidgetError( scopes.error ) }
				</Notice>
			) }
			{ scopes.data && ! context && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'There is no school or class for you to follow yet.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ context && scopes.data && (
				<>
					<div className="ohmylms-dashboard-toolbar">
						<p className="ohmylms-dashboard-toolbar__summary">
							{ sprintf(
								/* translators: 1: school, class or "All schools"; 2: number of days. */
								__(
									'%1$s · last %2$d days, compared with the %2$d days before.',
									'ohmylms'
								),
								context.scope.label,
								context.period
							) }
						</p>
						<ScopePicker
							scopes={ scopes.data.scopes }
							value={ context.scope.id }
							onChange={ ( value ) => update( 'scope', value ) }
						/>
						<PeriodSwitcher
							value={ context.period }
							onChange={ ( value: PeriodDays ) =>
								update( 'period', String( value ) )
							}
						/>
					</div>
					<div className="ohmylms-dashboard-grid">
						{ registry.list( context ).map( ( widget ) => {
							const Widget = widget.component;
							return (
								<div
									key={ widget.id }
									className={ `ohmylms-dashboard-grid__cell is-${
										widget.size ?? 'wide'
									}` }
								>
									<ErrorBoundary
										resetKey={ `${ context.scope.id }|${ context.period }` }
									>
										<Suspense
											fallback={
												<WidgetCard
													id={ widget.id }
													title={ widget.title }
													loading
												/>
											}
										>
											<Widget context={ context } />
										</Suspense>
									</ErrorBoundary>
								</div>
							);
						} ) }
					</div>
				</>
			) }
		</div>
	);
}

/** The `/dashboard` route. */
export default function DashboardPage() {
	return <DashboardShell />;
}
