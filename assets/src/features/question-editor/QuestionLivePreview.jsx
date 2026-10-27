import { ExtendedPreview } from './ExtendedPreview';
import { isExtendedType } from './extendedModel.mjs';
/** @jsx createElement */
import {
	createElement,
	RawHTML,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { questionPreviewModel } from './questionBlocks.mjs';
import { InlineBlankPreview } from './InlineBlankPreview';
import { InteractivePreview } from './InteractiveEditors';

/**
 * Interactive learner appearance, built from the current unsaved question rather than a preview page.
 * @param root0
 * @param root0.question
 * @param root0.showNote
 */
export function QuestionLivePreview( { question, showNote = true } ) {
	const source = JSON.stringify( question );
	const randomized = Boolean( question.settings?.template );
	const seed = useRef( Math.floor( Math.random() * 2147483647 ) );
	const [ result, setResult ] = useState( null );
	useEffect( () => {
		if ( ! randomized ) {
			return;
		}
		let disposed = false;
		window.wp
			.apiFetch( {
				path: '/ohmylms/v1/question-template/preview',
				method: 'POST',
				data: {
					...JSON.parse( source ),
					render: true,
					seed: seed.current,
				},
			} )
			.then( ( response ) => {
				if ( ! disposed ) {
					setResult( { source, ...response } );
				}
			} )
			.catch( () => {
				if ( ! disposed ) {
					setResult( {
						source,
						valid: false,
						message: __(
							'Could not generate the preview. Try reopening it.',
							'ohmylms'
						),
					} );
				}
			} );
		return () => {
			disposed = true;
		};
	}, [ source, randomized ] );
	if ( randomized && ( result?.source !== source || ! result.valid ) ) {
		return (
			<p role="status">
				{ result?.source === source
					? result.message
					: __( 'Generating randomized preview…', 'ohmylms' ) }
			</p>
		);
	}
	return (
		<QuestionPreviewContent
			key={ source }
			question={
				randomized ? { ...question, ...result.question } : question
			}
			showNote={ showNote }
		/>
	);
}

/**
 * Render a concrete question after server-side variable substitution.
 * @param root0
 * @param root0.question
 * @param root0.showNote
 */
function QuestionPreviewContent( { question, showNote } ) {
	const view = questionPreviewModel( question );
	const group = useRef(
		`question-preview-${ Math.random().toString( 36 ).slice( 2 ) }`
	);
	const [ order, setOrder ] = useState( () => [ ...view.options ].reverse() );
	const move = ( index, direction ) =>
		setOrder( ( old ) => {
			const next = [ ...old ];
			[ next[ index ], next[ index + direction ] ] = [
				next[ index + direction ],
				next[ index ],
			];
			return next;
		} );
	const answerInput = ( label, multiline = false ) => (
		<label
			className="ohmylms-preview-input"
			htmlFor={ `${ group.current }-${ label }` }
		>
			{ label }
			{ multiline ? (
				<textarea
					id={ `${ group.current }-${ label }` }
					aria-label={ label }
					rows={ 4 }
					placeholder={ __( 'Type your answer here …', 'ohmylms' ) }
				/>
			) : (
				<input
					id={ `${ group.current }-${ label }` }
					aria-label={ label }
					type="text"
					placeholder={ __( 'Type your answer here …', 'ohmylms' ) }
				/>
			) }
		</label>
	);
	return (
		<section
			key={ JSON.stringify( question ) }
			className="ohmylms-question-live-preview"
			aria-label={ __( 'Question preview', 'ohmylms' ) }
		>
			{ showNote && (
				<p className="ohmylms-preview-note">
					{ __(
						'Learner preview · responses here are not saved or graded.',
						'ohmylms'
					) }
				</p>
			) }
			{ view.type === 'fill-in-the-blank' &&
			view.title.some( ( part ) => part.blank ) ? (
				<InlineBlankPreview text={ question.name } />
			) : (
				! question.settings?.question_code && (
					<h3>
						{ view.title.map( ( part, index ) =>
							part.blank ? (
								<input
									key={ index }
									aria-label={ sprintf(
										/* translators: %d: blank position. */
										__( 'Blank %d', 'ohmylms' ),
										index + 1
									) }
									className="ohmylms-preview-blank"
								/>
							) : (
								<span key={ index }>
									{ part.text ||
										__( 'Untitled question', 'ohmylms' ) }
								</span>
							)
						) }
					</h3>
				)
			) }
			{ ! (
				view.type === 'fill-in-the-blank' &&
				question.settings?.question_code
			) && <RawHTML>{ view.body }</RawHTML> }
			{ question.image_src && (
				<img
					className="ohmylms-question-legacy-media"
					src={ question.image_src }
					alt={ __( 'Question image', 'ohmylms' ) }
				/>
			) }
			{ question.video_src && (
				<video
					className="ohmylms-question-legacy-media"
					src={ question.video_src }
					controls
				/>
			) }
			{ [ 'single-choice', 'multiple-choice', 'true-false' ].includes(
				view.type
			) && (
				<div className="ohmylms-preview-options">
					{ view.options.map( ( option ) => (
						<label
							htmlFor={ `${ group.current }-${ option.id }` }
							key={ option.id }
							className="ohmylms-preview-choice"
						>
							<input
								id={ `${ group.current }-${ option.id }` }
								type={
									view.type === 'multiple-choice'
										? 'checkbox'
										: 'radio'
								}
								name={ group.current }
								value={ option.id }
							/>
							{ option.answer ||
								__( 'Answer option', 'ohmylms' ) }
							{ option.imageUrl && (
								<img src={ option.imageUrl } alt="" />
							) }
						</label>
					) ) }
				</div>
			) }
			{ [ 'short-text', 'statement', 'numerical' ].includes(
				view.type
			) &&
				answerInput(
					view.type === 'numerical'
						? __( 'Number', 'ohmylms' )
						: __( 'Your answer', 'ohmylms' )
				) }
			{ view.type === 'numerical' && view.unit && (
				<span>{ view.unit }</span>
			) }
			{ view.type === 'long-text' &&
				answerInput( __( 'Your answer', 'ohmylms' ), true ) }
			{ view.type === 'fill-in-the-blank' &&
				! view.title.some( ( part ) => part.blank ) &&
				answerInput( __( 'Your answer', 'ohmylms' ) ) }
			{ view.type === 'matching' &&
				view.options.map( ( option ) => (
					<label
						className="ohmylms-preview-choice"
						key={ option.id }
						htmlFor={ `${ group.current }-${ option.id }` }
					>
						{ option.answer ||
							__( 'Matching item is empty', 'ohmylms' ) }
						<select
							id={ `${ group.current }-${ option.id }` }
							aria-label={ sprintf(
								/* translators: %s: matching item. */
								__( 'Match for %s', 'ohmylms' ),
								option.answer
							) }
							defaultValue=""
						>
							<option value="">
								{ __( 'Choose a match', 'ohmylms' ) }
							</option>
							{ [ ...view.options ].reverse().map( ( match ) => (
								<option key={ match.id } value={ match.id }>
									{ match.match ||
										__(
											'Matching definition is empty',
											'ohmylms'
										) }
								</option>
							) ) }
						</select>
					</label>
				) ) }
			{ view.type === 'reorder' && (
				<ol>
					{ order.map( ( option, index ) => (
						<li key={ option.id }>
							{ option.answer ||
								__( 'Ordering item is empty', 'ohmylms' ) }
							<Button
								disabled={ index === 0 }
								onClick={ () => move( index, -1 ) }
							>
								{ __( 'Move up', 'ohmylms' ) }
							</Button>
							<Button
								disabled={ index === order.length - 1 }
								onClick={ () => move( index, 1 ) }
							>
								{ __( 'Move down', 'ohmylms' ) }
							</Button>
						</li>
					) ) }
				</ol>
			) }
			{ isExtendedType( view.type ) && (
				<ExtendedPreview
					type={ view.type }
					settings={ question.settings || {} }
				/>
			) }
			{ view.interactive && (
				<InteractivePreview
					type={ view.type }
					settings={ view.interactive }
				/>
			) }
			{ view.type === 'structured' &&
				view.parts.map( ( part ) => (
					<fieldset key={ part.id }>
						<legend>
							{ part.label } · { part.marks }{ ' ' }
							{ __( 'marks', 'ohmylms' ) }
						</legend>
						{ part.kind === 'question' ? (
							<QuestionLivePreview
								question={ {
									...question.settings.parts.find(
										( item ) => item.id === part.id
									)?.question,
									name: part.prompt || '',
									description: '',
								} }
								showNote={ false }
							/>
						) : (
							<RawHTML>{ part.prompt }</RawHTML>
						) }
						{ part.kind !== 'question' &&
							answerInput(
								sprintf(
									/* translators: %s: part label. */
									__( 'Part %s answer', 'ohmylms' ),
									part.label
								),
								part.kind === 'written'
							) }
					</fieldset>
				) ) }
		</section>
	);
}
