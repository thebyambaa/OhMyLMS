/**
 * Save the native document only after private question writes have completed.
 *
 * @param {Object}                 root0          Preview dependencies.
 * @param {Object}                 root0.editor   Native editor selectors.
 * @param {() => Promise<unknown>} root0.savePost Native document save action.
 * @return {Promise<boolean>} Whether the saved quiz can be previewed.
 */
export async function saveQuizForPreview( { editor, savePost } ) {
	if ( editor.isPostSavingLocked() || editor.isSavingPost() ) {
		return false;
	}
	if ( editor.isEditedPostDirty() ) {
		await savePost();
		if ( editor.didPostSaveRequestFail() || editor.isEditedPostDirty() ) {
			throw new Error( 'quiz-preview-save-failed' );
		}
	}
	return ! editor.isPostSavingLocked() && ! editor.isSavingPost();
}
