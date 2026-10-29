import { Button, Notice, Spinner } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { useContentTypes } from './hooks';

/**
 * Demo route: shows the loading, error, empty and loaded states of a store-backed list, and raises a
 * notice through the shared `core/notices` store.
 */
export default function HomePage() {
	const { status, items, error, reload } = useContentTypes();
	const { createSuccessNotice } = useDispatch( noticesStore );

	return (
		<>
			<h2>{ __( 'Welcome to the new OhMyLMS admin', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'This page demonstrates the app shell, the API client and a feature store.',
					'ohmylms'
				) }
			</p>
			<p>
				<Button
					variant="secondary"
					onClick={ () =>
						void createSuccessNotice(
							__(
								'This is a notice from the shared store.',
								'ohmylms'
							)
						)
					}
				>
					{ __( 'Show a notice', 'ohmylms' ) }
				</Button>
			</p>

			<section aria-labelledby="ohmylms-home-types">
				<h3 id="ohmylms-home-types">
					{ __( 'Content types on this site', 'ohmylms' ) }
				</h3>
				{ ( 'idle' === status || 'loading' === status ) && (
					<p role="status">
						<Spinner />
						{ __( 'Loading…', 'ohmylms' ) }
					</p>
				) }
				{ 'error' === status && error && (
					<Notice status="error" isDismissible={ false }>
						{ sprintf(
							/* translators: %s: error message from the server. */
							__( 'Could not load content types: %s', 'ohmylms' ),
							error.message
						) }{ ' ' }
						<Button variant="link" onClick={ reload }>
							{ __( 'Try again', 'ohmylms' ) }
						</Button>
					</Notice>
				) }
				{ 'ready' === status && 0 === items.length && (
					<p>{ __( 'No content types found.', 'ohmylms' ) }</p>
				) }
				{ 'ready' === status && 0 < items.length && (
					<ul>
						{ items.map( ( item ) => (
							<li key={ item.slug }>
								<strong>{ item.name }</strong>{ ' ' }
								<code>{ item.slug }</code>
							</li>
						) ) }
					</ul>
				) }
			</section>
		</>
	);
}
