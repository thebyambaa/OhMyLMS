import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { ApiError } from '../../lib/api';
import { describeError, isRetryable } from './model';

/**
 * Loading and error feedback of a request. Data from an earlier request stays visible below it,
 * so a failed refresh never blanks the page.
 *
 * @param props         Component props.
 * @param props.loading A request is running.
 * @param props.error   Error of the last request.
 * @param props.label   Translated loading message.
 * @param props.onRetry Runs the request again.
 */
export function RequestStatus( {
	loading,
	error,
	label,
	onRetry,
}: {
	loading: boolean;
	error: ApiError | null;
	label: string;
	onRetry: () => void;
} ) {
	return (
		<>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ describeError( error ) }{ ' ' }
					{ isRetryable( error ) && (
						<Button variant="link" onClick={ onRetry }>
							{ __( 'Try again', 'ohmylms' ) }
						</Button>
					) }
				</Notice>
			) }
			{ loading && <p role="status">{ label }</p> }
		</>
	);
}
