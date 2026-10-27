import { useEffect, useRef } from '@wordpress/element';
import { useDispatch, select, dispatch } from '@wordpress/data';

let nextLock = 0;

/**
 * Lock native publication and browser navigation while independent settings are unsaved.
 * @param root0
 * @param root0.dirty
 * @param root0.busy
 */
export function NativeSaveGuard( { dirty, busy = false } ) {
	const key = useRef( `ohmylms-settings-${ ++nextLock }` );
	const { lockPostSaving, unlockPostSaving } = useDispatch( 'core/editor' );
	useEffect( () => {
		const lock = key.current;
		const blocked = dirty || busy;
		if ( blocked ) {
			lockPostSaving( lock );
		}
		const leave = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		if ( blocked ) {
			window.addEventListener( 'beforeunload', leave );
		}
		return () => {
			unlockPostSaving( lock );
			window.removeEventListener( 'beforeunload', leave );
		};
	}, [ dirty, busy, lockPostSaving, unlockPostSaving ] );
	return null;
}

/** Mark successful external assessment writes for the next native publication. */
export function markNativeQuizDirty() {
	const editor = select( 'core/editor' );
	if ( editor?.getCurrentPostType() === 'ohmylms-quiz' ) {
		dispatch( 'core/editor' ).editPost( {
			ohmylms_authoring_revision:
				Number(
					editor.getEditedPostAttribute(
						'ohmylms_authoring_revision'
					) || 0
				) + 1,
		} );
	}
}
