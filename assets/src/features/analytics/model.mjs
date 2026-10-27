import { showRequestError } from '../requestFeedback.mjs';
/**
 * Preserve the dashboard's REST filter/date contract.
 * @param filter
 * @param formatDate
 */
export function dashboardPath( filter, formatDate ) {
	let path = `ohmylms/v1/dashboard?filter=${ filter?.type }`;
	if ( filter?.type === 'custom' && filter.startDate && filter.endDate ) {
		path += `&start_date=${ encodeURIComponent( formatDate( filter.startDate.date ) ) }&end_date=${ encodeURIComponent( formatDate( filter.endDate.date ) ) }`;
	}
	return path;
}
export function sortRecentCourses( courses = [] ) {
	return [ ...courses ].sort(
		( left, right ) => right?.total_sales_count - left?.total_sales_count
	);
}

/**
 * Ignore obsolete responses after changing the date filter or leaving the screen.
 * @param apiFetch
 * @param actions
 * @param filter
 * @param formatDate
 * @param onError
 */
export function loadDashboard(
	apiFetch,
	actions,
	filter,
	formatDate,
	onError = showRequestError
) {
	let active = true;
	actions.setDashboardLoader( true );
	const done = ( async () => {
		try {
			const data = await apiFetch( {
				path: dashboardPath( filter, formatDate ),
			} );
			if ( active ) {
				actions.setDashboardOverview( data );
				actions.setDashboardAll( data );
			}
		} catch ( error ) {
			if ( active ) {
				onError( error );
			}
		} finally {
			if ( active ) {
				actions.setDashboardLoader( false );
			}
		}
	} )();
	return {
		done,
		cancel() {
			active = false;
		},
	};
}
