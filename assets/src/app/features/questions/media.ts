import type { FormMedia } from './types';

interface MediaAttachment {
	toJSON: () => { id?: unknown; url?: unknown };
}

interface MediaFrame {
	on: ( event: 'select' | 'close', callback: () => void ) => void;
	open: () => void;
	state: () => {
		get: ( key: 'selection' ) => {
			first: () => MediaAttachment | undefined;
		};
	};
}

type MediaFactory = ( options: {
	title: string;
	button: { text: string };
	library: { type: string };
	multiple: false;
} ) => MediaFrame;

function factory(): MediaFactory | null {
	const media = ( window as unknown as { wp?: { media?: unknown } } ).wp
		?.media;
	return 'function' === typeof media ? ( media as MediaFactory ) : null;
}

/**
 * Whether the media library modal is loaded on this screen (the app screen enqueues it).
 *
 * @return True when `wp.media` is available.
 */
export function mediaLibraryAvailable(): boolean {
	return null !== factory();
}

/**
 * Open the WordPress media library, where the user can upload or pick one file of a type.
 *
 * @param type   MIME type group: `image` or `video`.
 * @param title  Modal title.
 * @param button Select button label.
 * @return The chosen file, or `null` when the modal closes without a choice.
 */
export function chooseMedia(
	type: 'image' | 'video',
	title: string,
	button: string
): Promise< FormMedia | null > {
	const create = factory();
	if ( ! create ) {
		return Promise.resolve( null );
	}
	return new Promise( ( resolve ) => {
		const frame = create( {
			title,
			button: { text: button },
			library: { type },
			multiple: false,
		} );
		let chosen: FormMedia | null = null;
		frame.on( 'select', () => {
			const file = frame.state().get( 'selection' ).first()?.toJSON();
			if (
				file &&
				Number.isSafeInteger( file.id ) &&
				Number( file.id ) > 0 &&
				'string' === typeof file.url
			) {
				chosen = { id: Number( file.id ), url: file.url };
			}
			resolve( chosen );
		} );
		frame.on( 'close', () => {
			// The select button closes the frame before it triggers `select`.
			window.setTimeout( () => resolve( chosen ), 0 );
		} );
		frame.open();
	} );
}
