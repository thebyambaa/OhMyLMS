import { Button, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { ReactNode } from 'react';
import type { ApiError } from '../../../lib/api';
import { describeWidgetError, isWidgetErrorRetryable } from './errors';

/**
 * A widget in WordPress postbox markup (`.postbox`, `.postbox-header`, `h2.hndle`, `.inside`) with its
 * loading, error and empty states. Data from an earlier request stays visible while the next loads.
 *
 * @param props          Component props.
 * @param props.id       Widget id (`ohmylms-widget-<id>` is the element id).
 * @param props.title    Heading.
 * @param props.meta     Short text next to the heading.
 * @param props.loading  A request is running.
 * @param props.error    Error of the last request.
 * @param props.onRetry  Runs the request again.
 * @param props.empty    Message shown instead of the content when there is nothing to show.
 * @param props.children Content.
 */
export function WidgetCard( {
	id,
	title,
	meta,
	loading = false,
	error = null,
	onRetry,
	empty,
	children,
}: {
	id: string;
	title: string;
	meta?: ReactNode;
	loading?: boolean;
	error?: ApiError | null;
	onRetry?: () => void;
	empty?: string | false;
	children?: ReactNode;
} ) {
	const headingId = `ohmylms-widget-${ id }-title`;
	return (
		<section
			id={ `ohmylms-widget-${ id }` }
			className="postbox ohmylms-dashboard-widget"
			aria-labelledby={ headingId }
			aria-busy={ loading }
		>
			<div className="postbox-header">
				<h2 className="hndle" id={ headingId }>
					{ title }
				</h2>
				{ meta && (
					<span className="ohmylms-dashboard-widget__meta">
						{ meta }
					</span>
				) }
			</div>
			<div className="inside">
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ describeWidgetError( error ) }{ ' ' }
						{ onRetry && isWidgetErrorRetryable( error ) && (
							<Button variant="link" onClick={ onRetry }>
								{ __( 'Try again', 'ohmylms' ) }
							</Button>
						) }
					</Notice>
				) }
				{ loading && (
					<p
						role="status"
						className="ohmylms-dashboard-widget__loading"
					>
						<Spinner /> { __( 'Loading…', 'ohmylms' ) }
					</p>
				) }
				{ empty ? <p>{ empty }</p> : children }
			</div>
		</section>
	);
}
