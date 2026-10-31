/** A file chosen in the WordPress media library. */
export interface ChosenFile {
	id: number;
	url: string;
	name: string;
	size: string;
}

interface MediaAttachment {
	toJSON: () => {
		id?: unknown;
		url?: unknown;
		title?: unknown;
		filename?: unknown;
		filesizeHumanReadable?: unknown;
	};
}

interface MediaFrame {
	on: ( event: 'select' | 'close', callback: () => void ) => void;
	open: () => void;
	state: () => {
		get: ( key: 'selection' ) => {
			toArray: () => MediaAttachment[];
		};
	};
}

type MediaFactory = ( options: {
	title: string;
	button: { text: string };
	multiple: true;
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

function text( value: unknown ): string {
	return 'string' === typeof value ? value : '';
}

/**
 * Open the media library to upload or choose files of any type.
 *
 * @param title  Modal title.
 * @param button Select button label.
 * @return The chosen files; empty when the modal closes without a choice.
 */
export function chooseFiles(
	title: string,
	button: string
): Promise< ChosenFile[] > {
	const create = factory();
	if ( ! create ) {
		return Promise.resolve( [] );
	}
	return new Promise( ( resolve ) => {
		const frame = create( {
			title,
			button: { text: button },
			multiple: true,
		} );
		let chosen: ChosenFile[] = [];
		frame.on( 'select', () => {
			chosen = frame
				.state()
				.get( 'selection' )
				.toArray()
				.map( ( item ) => item.toJSON() )
				.filter(
					( file ) =>
						Number.isSafeInteger( file.id ) &&
						Number( file.id ) > 0 &&
						'string' === typeof file.url
				)
				.map( ( file ) => ( {
					id: Number( file.id ),
					url: text( file.url ),
					name: text( file.title ) || text( file.filename ),
					size: text( file.filesizeHumanReadable ),
				} ) );
			resolve( chosen );
		} );
		frame.on( 'close', () => {
			// The select button closes the frame before it triggers `select`.
			window.setTimeout( () => resolve( chosen ), 0 );
		} );
		frame.open();
	} );
}
