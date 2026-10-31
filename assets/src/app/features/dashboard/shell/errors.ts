import { __ } from '@wordpress/i18n';
import type { ApiError } from '../../../lib/api';

/**
 * User-facing text for a failed dashboard request.
 *
 * @param error Typed API error.
 * @return Message for the notice.
 */
export function describeWidgetError( error: ApiError ): string {
	if ( 'ohmylms_dashboard_forbidden_scope' === error.code ) {
		return __( 'You cannot view this school or class.', 'ohmylms' );
	}
	if ( 'ohmylms_dashboard_scope_not_found' === error.code ) {
		return __(
			'This school or class no longer exists or is not active.',
			'ohmylms'
		);
	}
	if ( 401 === error.status || 403 === error.status ) {
		return __( 'You do not have permission to view this.', 'ohmylms' );
	}
	return (
		error.message || __( 'This could not be loaded. Try again.', 'ohmylms' )
	);
}

/**
 * Whether retrying could help.
 *
 * @param error Typed API error.
 * @return False for permission and missing-scope errors.
 */
export function isWidgetErrorRetryable( error: ApiError ): boolean {
	return 401 !== error.status && 403 !== error.status && 404 !== error.status;
}
