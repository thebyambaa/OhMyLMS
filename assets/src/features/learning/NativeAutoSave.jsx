import { useEffect, useRef } from '@wordpress/element';
import { useDispatch, useSelect, select as readStore } from '@wordpress/data';
import { registerPlugin } from '@wordpress/plugins';
import { AUTOSAVE_DELAY, leavesPage, shouldAutosave } from './autosave.mjs';

/**
 * The editor's state as `shouldAutosave` reads it.
 *
 * @param {string} failedSignature Fingerprint of the edits whose last save failed.
 * @return {import('./autosave.mjs').EditorState} State.
 */
function readState( failedSignature ) {
	const editor = readStore( 'core/editor' );
	return {
		type: editor.getCurrentPostType() || '',
		status: editor.getEditedPostAttribute( 'status' ) || '',
		title: String( editor.getEditedPostAttribute( 'title' ) || '' ),
		dirty: editor.isEditedPostDirty(),
		saving: editor.isSavingPost(),
		locked: editor.isPostSavingLocked(),
		signature: JSON.stringify(
			/** @type {{ getPostEdits: () => object }} */ (
				/** @type {unknown} */ ( editor )
			).getPostEdits()
		),
		failedSignature,
	};
}

/**
 * Lessons, quizzes and questions save themselves: shortly after an edit, when the tab is hidden or closed,
 * and before a link that leaves the editor (the back button) is followed. Nothing is published by this; a
 * draft stays a draft.
 *
 * @return {null} Renders nothing.
 */
function NativeAutoSave() {
	const { savePost } = useDispatch( 'core/editor' );
	const { type, dirty, saving, locked } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return {
			type: editor.getCurrentPostType(),
			dirty: editor.isEditedPostDirty(),
			saving: editor.isSavingPost(),
			locked: editor.isPostSavingLocked(),
		};
	}, [] );
	const failed = useRef( '' );

	/** Save now when the state calls for it, remembering a failure so it is not retried unchanged. */
	const flush = useRef( async () => {} );
	flush.current = async () => {
		const state = readState( failed.current );
		if ( ! shouldAutosave( state ) ) {
			return;
		}
		await savePost();
		if ( readStore( 'core/editor' ).didPostSaveRequestFail() ) {
			failed.current = state.signature;
		}
	};

	useEffect( () => {
		if ( ! dirty || saving || locked ) {
			return undefined;
		}
		const timer = window.setTimeout(
			() => void flush.current(),
			AUTOSAVE_DELAY
		);
		return () => window.clearTimeout( timer );
	}, [ type, dirty, saving, locked ] );

	useEffect( () => {
		const hide = () => {
			if ( 'hidden' === document.visibilityState ) {
				void flush.current();
			}
		};
		const close = () => void flush.current();
		/** @param {MouseEvent} event Click. */
		const click = ( event ) => {
			const link =
				event.target instanceof Element
					? event.target.closest( 'a[href]' )
					: null;
			if (
				! link ||
				event.defaultPrevented ||
				! shouldAutosave( readState( failed.current ) ) ||
				! leavesPage(
					{
						href: /** @type {HTMLAnchorElement} */ ( link ).href,
						target: link.getAttribute( 'target' ) || '',
						download: link.hasAttribute( 'download' ),
						modified:
							event.ctrlKey ||
							event.metaKey ||
							event.shiftKey ||
							event.altKey ||
							0 !== event.button,
					},
					window.location.href
				)
			) {
				return;
			}
			event.preventDefault();
			event.stopPropagation();
			const href = /** @type {HTMLAnchorElement} */ ( link ).href;
			// Leave once the save is done; a failed save leaves the browser's own warning to catch it.
			void flush
				.current()
				.catch( () => {} )
				.then( () => window.location.assign( href ) );
		};
		document.addEventListener( 'visibilitychange', hide );
		window.addEventListener( 'pagehide', close );
		document.addEventListener( 'click', click, true );
		return () => {
			document.removeEventListener( 'visibilitychange', hide );
			window.removeEventListener( 'pagehide', close );
			document.removeEventListener( 'click', click, true );
		};
	}, [] );
	return null;
}

registerPlugin( 'ohmylms-native-autosave', { render: NativeAutoSave } );
