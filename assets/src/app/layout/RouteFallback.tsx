import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/** Shown by Suspense while a lazy route loads. */
export function RouteFallback() {
	return (
		<p role="status" className="ohmylms-app-loading">
			<Spinner />
			{ __( 'Loading…', 'ohmylms' ) }
		</p>
	);
}
