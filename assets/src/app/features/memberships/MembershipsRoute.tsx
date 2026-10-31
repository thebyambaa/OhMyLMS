import { Suspense } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Link, useSearchParams } from 'react-router-dom';
import { AutomationsPanel, CohortsPanel } from '../commerce/public';
import MembershipsPage from './MembershipsPage';

const PANELS = [
	{ id: '', label: () => __( 'Plans', 'ohmylms' ) },
	{ id: 'cohorts', label: () => __( 'Cohorts', 'ohmylms' ) },
	{ id: 'automations', label: () => __( 'Automations', 'ohmylms' ) },
] as const;

/**
 * Commerce › Memberships: plans, cohorts and automations as in-page panels (`panel=`), until the
 * admin navigation gives cohorts and automations their own tabs.
 */
export default function MembershipsRoute() {
	const [ params ] = useSearchParams();
	const current =
		PANELS.find( ( panel ) => panel.id === params.get( 'panel' ) )?.id ??
		'';
	return (
		<>
			<ul
				className="subsubsub"
				aria-label={ __( 'Memberships', 'ohmylms' ) }
			>
				{ PANELS.map( ( panel, index ) => (
					<li key={ panel.id }>
						<Link
							to={
								panel.id
									? `/memberships?panel=${ panel.id }`
									: '/memberships'
							}
							className={
								current === panel.id ? 'current' : undefined
							}
							aria-current={
								current === panel.id ? 'page' : undefined
							}
						>
							{ panel.label() }
						</Link>
						{ index < PANELS.length - 1 ? ' |' : '' }
					</li>
				) ) }
			</ul>
			<div className="clear" />
			<Suspense
				fallback={
					<p role="status">{ __( 'Loading…', 'ohmylms' ) }</p>
				}
			>
				{ 'cohorts' === current && <CohortsPanel /> }
				{ 'automations' === current && <AutomationsPanel /> }
			</Suspense>
			{ '' === current && <MembershipsPage /> }
		</>
	);
}
