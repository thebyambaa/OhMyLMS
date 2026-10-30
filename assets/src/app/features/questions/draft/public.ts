/**
 * The hosted question draft editor as a default export, so other features can load it lazily
 * (`lazy( () => import( '../questions/draft/public' ) )`) instead of bundling the question editor.
 */
export { QuestionDraftEditor as default } from '../QuestionDraftEditor';
