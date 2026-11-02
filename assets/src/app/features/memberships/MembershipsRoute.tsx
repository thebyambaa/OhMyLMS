import { Navigate, useSearchParams } from 'react-router-dom';
import MembershipsPage from './MembershipsPage';

/** Former `panel=` values of Commerce › Memberships, now Commerce tabs with these routes. */
const PANEL_ROUTES: Readonly< Record< string, string > > = {
	cohorts: '/cohorts',
	automations: '/automations',
};

/**
 * Commerce › Memberships: the plan list. Old `?panel=cohorts|automations` addresses open the Cohorts
 * or Automations tab with the rest of their query.
 */
export default function MembershipsRoute() {
	const [ params ] = useSearchParams();
	const panel = params.get( 'panel' ) ?? '';
	if ( Object.prototype.hasOwnProperty.call( PANEL_ROUTES, panel ) ) {
		const rest = new URLSearchParams( params );
		rest.delete( 'panel' );
		const search = rest.toString();
		return (
			<Navigate
				replace
				to={ `${ PANEL_ROUTES[ panel ] }${ search ? `?${ search }` : '' }` }
			/>
		);
	}
	return <MembershipsPage />;
}
