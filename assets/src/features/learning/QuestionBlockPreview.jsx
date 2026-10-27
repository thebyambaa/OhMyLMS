import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Exact student rendering of an unselected, unsaved question in an isolated frame.
 *
 * @param {Object} props          Properties.
 * @param {Object} props.question Current question, including unsaved edits.
 * @param {string} props.clientId Native block ID.
 * @return {Element} Student document with an editing action over it.
 */
export function QuestionBlockPreview( { question, clientId } ) {
	const { selectBlock } = useDispatch( 'core/block-editor' );
	const documentSettings = useSelect(
		( store ) => {
			const editor = store( 'core/editor' );
			const quiz = editor.getEditedPostAttribute( 'ohmylms_quiz' ) || {};
			const collect = ( blocks ) =>
				blocks.flatMap( ( block ) => [
					...( [
						'ohmylms/shared-multiple-choice',
						'ohmylms/quiz-question-reference',
					].includes( block.name )
						? [ block.clientId ]
						: [] ),
					...collect( block.innerBlocks || [] ),
				] );
			const blocks = collect( store( 'core/block-editor' ).getBlocks() );
			return {
				post_id: editor.getCurrentPostId(),
				ordinal: Math.max( 1, blocks.indexOf( clientId ) + 1 ),
				question_count: Math.max( 1, blocks.length ),
				settings: quiz.settings || {},
				kind: quiz.assessment?.kind || 'quiz',
			};
		},
		[ clientId ]
	);
	const source = JSON.stringify( { ...documentSettings, question } );
	const seed = useRef( Math.floor( Math.random() * 2147483647 ) );
	const [ preview, setPreview ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ height, setHeight ] = useState( 160 );
	const observer = useRef( null );
	useEffect( () => {
		let active = true;
		const controller = new AbortController();
		setPreview( null );
		setError( '' );
		const timer = setTimeout( () => {
			apiFetch( {
				path: '/ohmylms/v1/question-preview',
				method: 'POST',
				signal: controller.signal,
				data: { ...JSON.parse( source ), seed: seed.current },
			} )
				.then( ( result ) => active && setPreview( result.url ) )
				.catch(
					( cause ) =>
						active &&
						setError(
							cause.message ||
								__(
									'Could not load the student preview. Open the question to try again.',
									'ohmylms'
								)
						)
				);
		}, 150 );
		return () => {
			active = false;
			clearTimeout( timer );
			controller.abort();
		};
	}, [ source ] );
	useEffect( () => () => observer.current?.disconnect(), [] );
	const resize = ( event ) => {
		observer.current?.disconnect();
		const frame = event.currentTarget;
		const content = frame.contentDocument?.querySelector(
			'.ohmylms-question-preview-content'
		);
		if ( ! content ) {
			setError(
				__(
					'The student preview expired. Open the question to refresh it.',
					'ohmylms'
				)
			);
			return;
		}
		const measure = () =>
			setHeight(
				Math.max(
					80,
					Math.ceil( content.getBoundingClientRect().height )
				)
			);
		observer.current = new ResizeObserver( measure );
		observer.current.observe( content );
		measure();
	};
	const edit = () => selectBlock( clientId, 0 );
	return (
		<div className="ohmylms-question-block-preview">
			{ error && <p role="status">{ error }</p> }
			{ ! error && ! preview && <Spinner /> }
			{ preview && (
				<div inert="" aria-hidden="true">
					<iframe
						className="ohmylms-question-student-frame"
						title={ __( 'Student question preview', 'ohmylms' ) }
						src={ preview }
						tabIndex={ -1 }
						height={ height }
						onLoad={ resize }
					/>
				</div>
			) }
			<button
				type="button"
				className="ohmylms-question-block-edit"
				onClick={ edit }
				onFocus={ edit }
			>
				<span className="ohmylms-question-block-edit-label">
					{ __( 'Edit question', 'ohmylms' ) }
				</span>
			</button>
		</div>
	);
}
