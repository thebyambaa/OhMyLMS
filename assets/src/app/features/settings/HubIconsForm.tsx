import { useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { MediaPicker } from '../../components/MediaPicker';
import { FormFrame } from './FormFrame';
import {
	getHubIcons,
	hubIconsDirty,
	hubIconsDraft,
	saveHubIcons,
	type HubIconSlot,
	type HubIconsDraft,
} from './hubIcons';
import { invalidResponse } from './model';
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
						<strong>{ slot.label }</strong>
						<MediaPicker
							label={ sprintf(
								/* translators: %s: icon slot. */
								__( 'Image for %s', 'ohmylms' ),
								slot.label
							) }
							chooseText={ __( 'Choose image', 'ohmylms' ) }
							chooseLabel={ sprintf(
								/* translators: %s: icon slot. */
								__( 'Choose an image for %s', 'ohmylms' ),
								slot.label
							) }
							removeText={ __( 'Use built-in', 'ohmylms' ) }
							removeLabel={ sprintf(
								/* translators: %s: icon slot. */
								__( 'Use the built-in icon for %s', 'ohmylms' ),
								slot.label
							) }
							description={
								choice.id
									? undefined
									: __( 'Built-in icon', 'ohmylms' )
							}
							previewSize={ 32 }
							value={ choice }
							invalid={ invalidResponse }
							onChange={ ( image ) =>
								choose(
									slot.key,
									image.id,
									image.thumbnail_url
								)
							}
							onRemove={ () => choose( slot.key, 0, '' ) }
						/>
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
		</FormFrame>
	);
}
