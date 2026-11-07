import { useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { usePaperView } from '../paper/public.mjs';

/** Printable-paper blocks; the toolbar menus insert them, so they only need to be hidden from the block list. */
const PAPER_BLOCKS = [
	'ohmylms/paper-header',
	'ohmylms/paper-instructions',
	'ohmylms/paper-section',
	'ohmylms/paper-columns',
	'ohmylms/paper-page-break',
	'ohmylms/paper-answer-space',
	'ohmylms/paper-formula-sheet',
];

/**
 * Keep the quiz editor to what a quiz needs: no Categories or Tags panels, and the printable-paper blocks in
 * the block list only while the paper view is open or the quiz is presented as paper.
 *
 * @param {boolean} active Whether the open post is a quiz.
 */
export function useQuizEditorTidy( active ) {
	const paperView = usePaperView();
	const paperPresentation = useSelect(
		( getStore ) =>
			'paper' ===
			getStore( 'core/editor' ).getEditedPostAttribute( 'ohmylms_quiz' )
				?.settings?.presentation,
		[]
	);
	// Core has these actions; the bundled type definitions lag behind it.
	const { removeEditorPanel } =
		/** @type {{ removeEditorPanel: ( panel: string ) => void }} */ (
			/** @type {unknown} */ ( useDispatch( 'core/editor' ) )
		);
	const { hideBlockTypes, showBlockTypes } =
		/** @type {{ hideBlockTypes: ( names: string[] ) => void, showBlockTypes: ( names: string[] ) => void }} */ (
			/** @type {unknown} */ ( useDispatch( 'core/edit-post' ) )
		);
	useEffect( () => {
		if ( active ) {
			removeEditorPanel( 'taxonomy-panel-category' );
			removeEditorPanel( 'taxonomy-panel-post_tag' );
		}
	}, [ active, removeEditorPanel ] );
	const showPaper = Boolean( paperView || paperPresentation );
	useEffect( () => {
		if ( active && hideBlockTypes && showBlockTypes ) {
			( showPaper ? showBlockTypes : hideBlockTypes )( PAPER_BLOCKS );
		}
	}, [ active, showPaper, hideBlockTypes, showBlockTypes ] );
}
