import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Pagination } from '../../components/Pagination';
import { normalizeApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { listMedia } from './api';
import type { MediaImage } from './types';

interface Props {
	title: string;
	onSelect: ( image: MediaImage ) => void;
	onClose: () => void;
}

// Pick an image from the media library through the core REST route. Only JPEG, PNG, GIF and WebP
// images are listed; the server verifies the choice again when the template is saved.
export function MediaPicker( { title, onSelect, onClose }: Props ) {
	const [ query, setQuery ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ data, setData ] = useState< Collection< MediaImage > >( {
		items: [],
		total: 0,
		totalPages: 0,
	} );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState< string | null >( null );
	useEffect( () => {
		const controller = new AbortController();
		setLoading( true );
		setError( null );
		listMedia( search, page, controller.signal ).then(
			( next ) => {
				if ( ! controller.signal.aborted ) {
					setData( next );
					setLoading( false );
				}
			},
			( failure: unknown ) => {
				if ( ! controller.signal.aborted ) {
					setError( normalizeApiError( failure ).message );
					setLoading( false );
				}
			}
		);
		return () => controller.abort();
	}, [ search, page ] );
	return (
		<Modal title={ title } onRequestClose={ onClose }>
			<form
				role="search"
				aria-label={ __( 'Search images', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					setPage( 1 );
					setSearch( query );
				} }
			>
				<TextControl
					label={ __( 'Search images', 'ohmylms' ) }
					value={ query }
					onChange={ setQuery }
				/>
				<Button type="submit" variant="secondary" disabled={ loading }>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ loading && (
				<p role="status">{ __( 'Loading images…', 'ohmylms' ) }</p>
			) }
			{ ! loading && ! error && ! data.items.length && (
				<p>
					{ __(
						'No images found. Upload JPEG, PNG, GIF or WebP images in the Media Library first.',
						'ohmylms'
					) }
				</p>
			) }
			{ ! loading && data.items.length > 0 && (
				<ul aria-label={ __( 'Images', 'ohmylms' ) }>
					{ data.items.map( ( image ) => (
						<li key={ image.id }>
							<Button
								variant="secondary"
								onClick={ () => onSelect( image ) }
								aria-label={ sprintf(
									/* translators: %s: image title. */
									__( 'Use image %s', 'ohmylms' ),
									image.title || String( image.id )
								) }
							>
								<img
									src={ image.thumbnail_url }
									alt=""
									width={ 48 }
									height={ 48 }
									loading="lazy"
								/>{ ' ' }
								{ image.title || `#${ image.id }` }
							</Button>
						</li>
					) ) }
				</ul>
			) }
			<Pagination
				page={ page }
				pages={ data.totalPages }
				busy={ loading }
				onChange={ setPage }
			/>
			<Button variant="tertiary" onClick={ onClose }>
				{ __( 'Cancel', 'ohmylms' ) }
			</Button>
		</Modal>
	);
}
