import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { chooseMedia, mediaLibraryAvailable } from './media';
import type { FormMedia } from './types';

/**
 * Choose, replace or remove one image or video from the media library, with a preview.
 *
 * @param props          Component props.
 * @param props.kind     `image` or `video`.
 * @param props.label    Accessible name of what the file is for, such as "Question image".
 * @param props.value    Current file; id 0 and an empty URL for none (some files are stored by URL only).
 * @param props.disabled Whether editing is off.
 * @param props.onChange Receives the chosen file, or `{ id: 0, url: '' }` when removed.
 */
export function MediaChoice( {
	kind,
	label,
	value,
	disabled,
	onChange,
}: {
	kind: 'image' | 'video';
	label: string;
	value: FormMedia;
	disabled: boolean;
	onChange: ( next: FormMedia ) => void;
} ) {
	const [ opening, setOpening ] = useState( false );
	const available = mediaLibraryAvailable();
	const chosen = value.id > 0 || '' !== value.url;
	let action: string = __( 'Choose video', 'ohmylms' );
	if ( chosen ) {
		action = __( 'Replace', 'ohmylms' );
	} else if ( 'image' === kind ) {
		action = __( 'Choose image', 'ohmylms' );
	}
	async function choose() {
		setOpening( true );
		try {
			const file = await chooseMedia(
				kind,
				label,
				__( 'Use this file', 'ohmylms' )
			);
			if ( file ) {
				onChange( file );
			}
		} finally {
			setOpening( false );
		}
	}
	return (
		<div className="ohmylms-question-media">
			{ chosen && value.url && 'image' === kind && (
				<img src={ value.url } alt={ label } loading="lazy" />
			) }
			{ chosen && value.url && 'video' === kind && (
				<video src={ value.url } controls preload="metadata">
					<track kind="captions" />
				</video>
			) }
			{ available ? (
				<Button
					variant="secondary"
					size="small"
					disabled={ disabled || opening }
					aria-label={
						chosen
							? sprintf(
									/* translators: %s: what the file is for, such as "question image". */
									__( 'Replace %s', 'ohmylms' ),
									label
								)
							: sprintf(
									/* translators: %s: what the file is for, such as "question image". */
									__( 'Choose %s', 'ohmylms' ),
									label
								)
					}
					onClick={ () => void choose() }
				>
					{ action }
				</Button>
			) : (
				! chosen && (
					<p>
						{ __(
							'The media library is not available on this screen.',
							'ohmylms'
						) }
					</p>
				)
			) }
			{ chosen && (
				<>
					{ ' ' }
					<Button
						variant="secondary"
						size="small"
						isDestructive
						disabled={ disabled }
						aria-label={ sprintf(
							/* translators: %s: what the file is for, such as "Question image". */
							__( 'Remove %s', 'ohmylms' ),
							label
						) }
						onClick={ () => onChange( { id: 0, url: '' } ) }
					>
						{ __( 'Remove', 'ohmylms' ) }
					</Button>
				</>
			) }
		</div>
	);
}
