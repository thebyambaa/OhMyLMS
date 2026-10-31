import { useMemo } from '@wordpress/element';
import { draftQuestion } from './model';
import { QuestionWorkspace } from './QuestionWorkspace';
import type { QuestionCreateInput, QuestionType } from './types';

/** Props of {@link QuestionDraftEditor}. */
export interface QuestionDraftEditorProps< T > {
	/** Starting name of the new question. */
	name: string;
	/** Registered type of the new question; it cannot change later. */
	type: string;
	/** Registered question types, as `listQuestionTypes()` returns them. */
	types: QuestionType[];
	/** Creates the question; rejects with an `ApiError` as `POST /questions` would. */
	save: ( input: QuestionCreateInput, signal: AbortSignal ) => Promise< T >;
	/** Receives what `save` resolved to. */
	onSaved: ( result: T ) => void;
	/** Leaves the draft without saving. */
	onCancel: () => void;
	/** Label of the save button. */
	saveLabel: string;
	/** Reports unsaved edits so the host can guard them; pass a stable function. */
	onDirtyChange?: ( dirty: boolean ) => void;
	/** A post the user may edit (such as the hosting quiz) for the learner preview of the draft. */
	previewPostId?: number;
}

function noop() {
	return undefined;
}

/**
 * The question bank's draft editor hosted by another screen: the same form, type editors, client
 * validation and server error mapping as `#/content-hub/question-bank/new`, but the first save goes
 * through `save` (for example "create and place in this quiz") and the editor never navigates.
 *
 * @param props
 * @param props.name          Starting name.
 * @param props.type          Question type.
 * @param props.types         Registered types.
 * @param props.save          Creates the question.
 * @param props.onSaved       Receives the save result.
 * @param props.onCancel      Leaves the draft.
 * @param props.saveLabel     Label of the save button.
 * @param props.onDirtyChange Reports unsaved edits.
 * @param props.previewPostId Post for the learner preview of the draft.
 */
export function QuestionDraftEditor< T >( {
	name,
	type,
	types,
	save,
	onSaved,
	onCancel,
	saveLabel,
	onDirtyChange,
	previewPostId,
}: QuestionDraftEditorProps< T > ) {
	const question = useMemo(
		() => draftQuestion( name, type ),
		[ name, type ]
	);
	return (
		<QuestionWorkspace
			draft
			question={ question }
			types={ types }
			onSaved={ noop }
			reload={ noop }
			onHandoff={ noop }
			target={ {
				save,
				done: ( result ) => onSaved( result as T ),
				cancel: onCancel,
				saveLabel,
				onDirtyChange,
				previewPostId,
			} }
		/>
	);
}
