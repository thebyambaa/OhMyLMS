import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Pagination } from '../../components/Pagination';
import { normalizeApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { FormFrame } from './FormFrame';
import {
	getHubIcons,
	hubIconsDirty,
	hubIconsDraft,
	listImages,
	saveHubIcons,
	type HubIconSlot,
	type HubIconsDraft,
	type ImageOption,
} from './hubIcons';
import { useResource } from './useResource';

/**
 * Hub icons tab: media library images for the learner hub's activity kinds and rail logos.
 *
 * @param props         Component props.
 * @param props.active  Whether the tab is selected (loads on first selection).
 * @param props.onDirty Reports unsaved edits.
 */
export function HubIconsForm( {
	active,
	onDirty,
}: {
	active: boolean;
	onDirty: ( dirty: boolean ) => void;
} ) {
	const state = useResource< HubIconSlot[], HubIconsDraft >(
		{
			load: getHubIcons,
			toDraft: hubIconsDraft,
			isDirty: hubIconsDirty,
			validate: () => ( {} ),
			save: async ( _stored, draft, signal ) => ( {
				snapshot: await saveHubIcons( draft, signal ),
				changed: [ 'hub-icons' ],
			} ),
		},
		active
	);
	useEffect( () => onDirty( state.dirty ), [ state.dirty, onDirty ] );
	const [ picking, setPicking ] = useState< HubIconSlot | null >( null );

	const choose = ( key: string, id: number, url: string ) =>
		state.update( ( current ) => ( { ...current, [ key ]: { id, url } } ) );

	function slots( group: HubIconSlot[ 'group' ] ) {
		const draft = state.draft;
		return ( state.snapshot ?? [] )
			.filter( ( slot ) => slot.group === group )
			.map( ( slot ) => {
				const choice = draft?.[ slot.key ] ?? { id: 0, url: '' };
				return (
					<li key={ slot.key }>
						<strong>{ slot.label }</strong>{ ' ' }
						{ choice.id ? (
							<img
								src={ choice.url }
								alt=""
								width={ 32 }
								height={ 32 }
							/>
						) : (
							<span>{ __( 'Built-in icon', 'ohmylms' ) }</span>
						) }{ ' ' }
						<Button
							variant="secondary"
							onClick={ () => setPicking( slot ) }
							aria-label={ sprintf(
								/* translators: %s: icon slot. */
								__( 'Choose an image for %s', 'ohmylms' ),
								slot.label
							) }
						>
							{ __( 'Choose image', 'ohmylms' ) }
						</Button>{ ' ' }
						{ choice.id > 0 && (
							<Button
								variant="tertiary"
								onClick={ () => choose( slot.key, 0, '' ) }
								aria-label={ sprintf(
									/* translators: %s: icon slot. */
									__(
										'Use the built-in icon for %s',
										'ohmylms'
									),
									slot.label
								) }
							>
								{ __( 'Use built-in', 'ohmylms' ) }
							</Button>
						) }
					</li>
				);
			} );
	}

	return (
		<FormFrame
			title={ __( 'Hub icons', 'ohmylms' ) }
			description={ __(
				'Images from the media library that replace the learner hub’s activity icons and rail logo.',
				'ohmylms'
			) }
			state={ state }
		>
			<h4>{ __( 'Activities', 'ohmylms' ) }</h4>
			<ul>{ slots( 'activity' ) }</ul>
			<h4>{ __( 'Logos', 'ohmylms' ) }</h4>
			<ul>{ slots( 'logo' ) }</ul>
			{ picking && (
				<ImagePicker
					title={ sprintf(
						/* translators: %s: icon slot. */
						__( 'Image for %s', 'ohmylms' ),
						picking.label
					) }
					onClose={ () => setPicking( null ) }
					onSelect={ ( image ) => {
						choose( picking.key, image.id, image.thumbnail );
						setPicking( null );
					} }
				/>
			) }
		</FormFrame>
	);
}

// Pick an image from the media library through the core REST route; the server checks it again.
function ImagePicker( {
	title,
	onSelect,
	onClose,
}: {
	title: string;
	onSelect: ( image: ImageOption ) => void;
	onClose: () => void;
} ) {
	const [ query, setQuery ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ data, setData ] = useState< Collection< ImageOption > >( {
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
		listImages( search, page, controller.signal ).then(
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
			<div role="search">
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Search images', 'ohmylms' ) }
					value={ query }
					onChange={ setQuery }
				/>
				<Button
					variant="secondary"
					disabled={ loading }
					onClick={ () => {
						setPage( 1 );
						setSearch( query );
					} }
				>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</div>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ loading && (
				<p role="status">{ __( 'Loading images…', 'ohmylms' ) }</p>
			) }
			{ ! loading && ! error && ! data.items.length && (
				<p>{ __( 'No images found.', 'ohmylms' ) }</p>
			) }
			{ ! loading && data.items.length > 0 && (
				<ul aria-label={ __( 'Images', 'ohmylms' ) }>
					{ data.items.map( ( image ) => {
						const name = image.title || `#${ image.id }`;
						return (
							<li key={ image.id }>
								<Button
									variant="secondary"
									onClick={ () => onSelect( image ) }
									aria-label={ sprintf(
										/* translators: %s: image title. */
										__( 'Use image %s', 'ohmylms' ),
										name
									) }
								>
									<img
										src={ image.thumbnail }
										alt=""
										width={ 48 }
										height={ 48 }
										loading="lazy"
									/>{ ' ' }
									{ name }
								</Button>
							</li>
						);
					} ) }
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
