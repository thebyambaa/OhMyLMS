import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Open the WordPress media library without requiring a Gutenberg editor provider.
 * @param root0
 * @param root0.allowedTypes
 * @param root0.onSelect
 * @param root0.render
 */
export function QuestionMediaUpload( {
	allowedTypes = [ 'image' ],
	onSelect,
	render,
} ) {
	const frame = useRef( null );
	const select = useRef( onSelect );
	select.current = onSelect;
	useEffect(
		() => () => {
			frame.current?.off( 'select' );
			frame.current?.close();
		},
		[]
	);
	const open = () => {
		if ( ! window.wp?.media ) {
			return;
		}
		// Recovered vendors expose Lodash as `_`; WordPress media needs Underscore's context argument.
		// noConflict restores the previous global without changing the vendors' imported Lodash instance.
		while ( window._?.VERSION?.startsWith( '4.' ) && window._.noConflict ) {
			const previous = window._;
			previous.noConflict();
			if ( previous === window._ ) {
				break;
			}
		}
		if ( ! frame.current ) {
			frame.current = window.wp.media( {
				title: __( 'Choose question media', 'ohmylms' ),
				button: { text: __( 'Use this media', 'ohmylms' ) },
				library: { type: allowedTypes },
				multiple: false,
			} );
			frame.current.on( 'select', () => {
				const attachment = frame.current
					.state()
					.get( 'selection' )
					.first();
				if ( attachment ) {
					select.current( attachment.toJSON() );
				}
			} );
		}
		frame.current.open();
	};
	return render( { open } );
}
