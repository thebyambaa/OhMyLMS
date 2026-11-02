import { Button, Notice } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import { previewQuestion, type PreviewQuestion } from './api';

/**
 * The question as learners see it, rendered by the real quiz player (`/question-preview`) from the
 * edits on screen, saved or not. Answers given in the preview are neither saved nor graded.
 *
 * @param props          Component props.
 * @param props.postId   Document of the preview: the saved question, or the quiz a draft is created in;
 *                       0 when there is none yet.
 * @param props.question Builds the question to preview from the current edits.
 */
export function PlayerPreview( {
	postId,
	question,
}: {
	postId: number;
	question: () => PreviewQuestion;
} ) {
	const [ url, setUrl ] = useState< string | null >( null );
	const [ busy, setBusy ] = useState( false );
	const [ failure, setFailure ] = useState< string | null >( null );
	const [ height, setHeight ] = useState( 240 );
	const pending = useRef< AbortController | null >( null );
	const observer = useRef< ResizeObserver | null >( null );
	useEffect(
		() => () => {
			pending.current?.abort();
			observer.current?.disconnect();
		},
		[]
	);

	if ( ! postId ) {
		return (
			<section aria-labelledby="oml-question-preview">
				<h3 id="oml-question-preview">
					{ __( 'Learner preview', 'ohmylms' ) }
				</h3>
				<p>
					{ __(
						'Save the question to preview it in the learner player.',
						'ohmylms'
					) }
				</p>
			</section>
		);
	}

	function open() {
		pending.current?.abort();
		const controller = new AbortController();
		pending.current = controller;
		setBusy( true );
		setFailure( null );
		previewQuestion( postId, question(), controller.signal ).then(
			( address ) => {
				if ( ! controller.signal.aborted ) {
					setUrl( address );
					setBusy( false );
				}
			},
			( problem: unknown ) => {
				if ( ! controller.signal.aborted ) {
					setFailure( normalizeApiError( problem ).message );
					setBusy( false );
				}
			}
		);
	}

	function measure( frame: HTMLIFrameElement ) {
		observer.current?.disconnect();
		const content = frame.contentDocument?.querySelector(
			'.ohmylms-question-preview-content'
		);
		if ( ! content || 'undefined' === typeof ResizeObserver ) {
			return;
		}
		const fit = () =>
			setHeight(
				Math.max(
					120,
					Math.ceil( content.getBoundingClientRect().height ) + 32
				)
			);
		observer.current = new ResizeObserver( fit );
		observer.current.observe( content );
		fit();
	}

	return (
		<section aria-labelledby="oml-question-preview">
			<h3 id="oml-question-preview">
				{ __( 'Learner preview', 'ohmylms' ) }
			</h3>
			<p>
				{ __(
					'The quiz player shows the question with your current edits, saved or not. Answers you give here are not saved or graded.',
					'ohmylms'
				) }
			</p>
			<Button
				variant="secondary"
				isBusy={ busy }
				disabled={ busy }
				onClick={ open }
			>
				{ url
					? __( 'Refresh preview', 'ohmylms' )
					: __( 'Preview as a learner', 'ohmylms' ) }
			</Button>
			{ failure && (
				<Notice status="error" isDismissible={ false }>
					{ failure }
				</Notice>
			) }
			{ url && (
				<iframe
					className="ohmylms-question-player-preview"
					title={ __( 'Learner preview of the question', 'ohmylms' ) }
					src={ url }
					width="100%"
					height={ height }
					onLoad={ ( event ) => measure( event.currentTarget ) }
				/>
			) }
		</section>
	);
}
