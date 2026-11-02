import { Button, Notice } from '@wordpress/components';
import { useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useParams, useSearchParams } from 'react-router-dom';
import { useRequest } from '../../hooks/useRequest';
import { listQuestionTypes } from './api';
import { draftQuestion } from './model';
import { useQuestionEditor, type QuestionHandoff } from './useQuestionEditor';
import { BackLink, QuestionWorkspace } from './QuestionWorkspace';

function routeId( value: string | undefined ): number | null {
	return value && /^[1-9]\d*$/.test( value ) ? Number( value ) : null;
}

/**
 * Single-question editor (`#/content-hub/question-bank/<id>`): name, text, points and difficulty for
 * every type; options for the built-in option types; the registered `question-editor` for the other
 * types (the built-in settings editors, which load lazily, and add-on editors). Saves send only
 * changed fields and are refused when someone else saved first. Leaving with unsaved changes asks
 * first. Approving and duplicating work on the saved question.
 *
 * `#/content-hub/question-bank/new?type=<id>&name=<name>` is an unsaved draft of that type, started
 * from the type's authored defaults; its first save creates the question and replaces the route with
 * the question's own, keeping the editor state the server returned.
 */
export default function QuestionEditorPage() {
	const params = useParams();
	const [ handoff, setHandoff ] = useState< QuestionHandoff | null >( null );
	if ( 'new' === params.id ) {
		return <NewQuestion onHandoff={ setHandoff } />;
	}
	return (
		<SavedQuestion
			key={ params.id }
			route={ params.id }
			handoff={ handoff }
			onHandoff={ setHandoff }
		/>
	);
}

function NewQuestion( {
	onHandoff,
}: {
	onHandoff: ( handoff: QuestionHandoff ) => void;
} ) {
	const [ search ] = useSearchParams();
	const type = search.get( 'type' ) ?? '';
	const name = search.get( 'name' ) ?? '';
	const types = useRequest( listQuestionTypes );
	const draft = useMemo( () => draftQuestion( name, type ), [ name, type ] );
	if ( types.loading ) {
		return (
			<>
				<h2>{ __( 'New question', 'ohmylms' ) }</h2>
				<p role="status">
					{ __( 'Loading question types…', 'ohmylms' ) }
				</p>
			</>
		);
	}
	if ( types.error || ! types.data ) {
		return (
			<>
				<h2>{ __( 'New question', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ types.error ||
						__( 'Question types could not be loaded.', 'ohmylms' ) }
				</Notice>
				<p>
					<Button variant="secondary" onClick={ types.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>{ ' ' }
					<BackLink />
				</p>
			</>
		);
	}
	if ( ! types.data.some( ( item ) => item.id === type ) ) {
		return (
			<>
				<h2>{ __( 'New question', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ __(
						'This question type is not available. Start the question again from the question bank.',
						'ohmylms'
					) }
				</Notice>
				<BackLink />
			</>
		);
	}
	return (
		<QuestionWorkspace
			draft
			question={ draft }
			types={ types.data }
			onSaved={ () => undefined }
			reload={ () => undefined }
			onHandoff={ onHandoff }
		/>
	);
}

function SavedQuestion( {
	route,
	handoff,
	onHandoff,
}: {
	route: string | undefined;
	handoff: QuestionHandoff | null;
	onHandoff: ( handoff: QuestionHandoff ) => void;
} ) {
	const id = routeId( route );
	const editor = useQuestionEditor( id, handoff );
	const [ initialMessage ] = useState( () =>
		handoff && handoff.question.id === id ? handoff.message : undefined
	);

	if ( null === id ) {
		return (
			<>
				<h2>{ __( 'Question', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ __( 'This question link is invalid.', 'ohmylms' ) }
				</Notice>
				<BackLink />
			</>
		);
	}
	if ( editor.loading && ! editor.question ) {
		return (
			<>
				<h2>{ __( 'Question', 'ohmylms' ) }</h2>
				<p role="status">{ __( 'Loading question…', 'ohmylms' ) }</p>
			</>
		);
	}
	if ( editor.error || ! editor.question || ! editor.types ) {
		return (
			<>
				<h2>{ __( 'Question', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ editor.error?.message ??
						__( 'The question could not be loaded.', 'ohmylms' ) }
				</Notice>
				<p>
					{ ( ! editor.error ||
						( 403 !== editor.error.status &&
							404 !== editor.error.status ) ) && (
						<>
							<Button
								variant="secondary"
								onClick={ editor.reload }
							>
								{ __( 'Try again', 'ohmylms' ) }
							</Button>{ ' ' }
						</>
					) }
					<BackLink />
				</p>
			</>
		);
	}
	return (
		<QuestionWorkspace
			question={ editor.question }
			types={ editor.types }
			onSaved={ editor.setQuestion }
			reload={ editor.reload }
			onHandoff={ onHandoff }
			initialMessage={ initialMessage }
		/>
	);
}
