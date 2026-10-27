import { useEffect, useRef } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * New quizzes and lessons are stored with the title "Untitled", which the editor then shows as real text.
 * Clicking into it put the cursor inside the word and typing produced "UntiAlgebra Checkpointtled".
 * Blank the stored placeholder once, so the editor's own "Add title" placeholder shows instead.
 */
export function useBlankPlaceholderTitle() {
	const { title, postType } = useSelect(
		( select ) => ( {
			title: select( 'core/editor' ).getEditedPostAttribute( 'title' ),
			postType: select( 'core/editor' ).getCurrentPostType(),
		} ),
		[]
	);
	const { editPost } = useDispatch( 'core/editor' );
	const done = useRef( false );
	useEffect( () => {
		if (
			! done.current &&
			String( postType ).startsWith( 'ohmylms-' ) &&
			title === __( 'Untitled', 'ohmylms' )
		) {
			done.current = true;
			editPost( { title: '' }, { undoIgnore: true } );
		}
	}, [ title, postType, editPost ] );
}
