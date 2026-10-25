/** @jsx createElement */
import { createElement, RawHTML, useEffect, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { loadMath, mathEnabled } from '../math/loader.mjs';
import { paperQuestion } from './paperModel.mjs';
import { TYPED_LAYOUTS, TypedBody } from './PaperTypedViews';

/**
 * Typeset math inside a printed region with the shared math runtime.
 *
 * @param {string} source Content that should be typeset again when it changes.
 * @return {Object} Ref for the region.
 */
function useMath( source ) {
	const host = useRef( null );
	useEffect( () => {
		if ( ! mathEnabled() ) {
			return undefined;
		}
		let active = true;
		let cleanup;
		loadMath()
			.then( ( runtime ) => {
				if ( active && host.current ) {
					cleanup = runtime.mount( host.current );
				}
			} )
			.catch( () => {} );
		return () => {
			active = false;
			cleanup?.();
		};
	}, [ source ] );
	return host;
}

/**
 * Bracketed marks, right-aligned as on exam papers.
 *
 * @param {Object}  props       Properties.
 * @param {number}  props.marks Marks.
 * @param {boolean} props.total Whether this is a question total.
 * @return {Element|null} Marks label.
 */
function Marks( { marks, total = false } ) {
	if ( ! marks ) {
		return null;
	}
	return (
		<span className="ohmylms-print-marks">
			{ total
				? sprintf(
						/* translators: %d: total marks for the question. */
						__( '[Total: %d]', 'ohmylms' ),
						marks
					)
				: `[${ marks }]` }
		</span>
	);
}

/**
 * Answer key entries; only rendered for the answer key view.
 *
 * @param {Object}   props         Properties.
 * @param {string[]} props.answers Accepted answers.
 * @param {string}   props.rubric  Marking notes.
 * @return {Element|null} Key.
 */
function Key( { answers = [], rubric = '' } ) {
	if ( ! answers.length && ! rubric ) {
		return null;
	}
	return (
		<div className="ohmylms-print-key">
			{ answers.length > 0 && (
				<p>
					{ sprintf(
						/* translators: %s: accepted answers separated by commas. */
						__( 'Answer: %s', 'ohmylms' ),
						answers.join( ', ' )
					) }
				</p>
			) }
			{ rubric && <p>{ rubric }</p> }
		</div>
	);
}

/**
 * Space to write an answer: an answer line or ruled lines.
 *
 * @param {Object} props      Properties.
 * @param {Object} props.item Question or part model.
 * @return {Element} Answer space.
 */
function AnswerSpace( { item } ) {
	if ( item.layout === 'answer-line' ) {
		return (
			<div className="ohmylms-print-answer-line">
				<span className="ohmylms-print-line" />
				{ item.unit && (
					<span className="ohmylms-print-unit">{ item.unit }</span>
				) }
			</div>
		);
	}
	return (
		<div
			className="ohmylms-print-lines"
			style={ { '--ohmylms-paper-lines': item.lines } }
			aria-hidden="true"
		/>
	);
}

/**
 * The printed form of one question, shared by the editor's paper canvas and the print page.
 *
 * @param {Object}  props             Properties.
 * @param {Object}  props.question    Editor question: settings and options.
 * @param {string}  props.prompt      Statement HTML.
 * @param {string}  props.label       Printed question number, empty for none.
 * @param {Object}  props.settings    Normalized paper settings.
 * @param {boolean} props.showAnswers Whether to show the answer key.
 * @param {boolean} props.editor      Whether editor-only notes may show.
 * @param {string}  props.version     Paper version letter.
 * @return {Element} Printed question.
 */
export function PaperQuestion( {
	question,
	prompt,
	label,
	settings,
	showAnswers = false,
	editor = false,
	version = 'A',
} ) {
	const paper = paperQuestion( question, {
		settings,
		showAnswers,
		prompt,
		version,
	} );
	const host = useMath( JSON.stringify( paper ) );
	const marks = settings.show_marks;
	return (
		<div
			ref={ host }
			className={ `ohmylms-print-question is-${ paper.layout }` }
			data-ohmylms-latex
		>
			<div className="ohmylms-print-head">
				{ paper.layout !== 'text' && label && (
					<span className="ohmylms-print-number">{ label }</span>
				) }
				<div className="ohmylms-print-prompt">
					<RawHTML>{ paper.prompt }</RawHTML>
				</div>
				{ marks && paper.layout !== 'parts' && (
					<Marks marks={ paper.marks } />
				) }
			</div>
			{ paper.layout === 'choice' && (
				<ol
					className={ `ohmylms-print-options${
						paper.multiple ? ' is-multiple' : ''
					}` }
				>
					{ paper.options.map( ( option ) => (
						<li
							key={ option.letter }
							className={ option.correct ? 'is-correct' : '' }
						>
							<span className="ohmylms-print-letter">
								{ option.letter }
							</span>
							<span className="ohmylms-print-option">
								<RawHTML>{ option.text }</RawHTML>
								{ option.image && (
									<img src={ option.image } alt="" />
								) }
							</span>
						</li>
					) ) }
				</ol>
			) }
			{ [ 'answer-line', 'lines' ].includes( paper.layout ) && (
				<>
					<AnswerSpace item={ paper } />
					{ showAnswers && <Key answers={ paper.answers } /> }
				</>
			) }
			{ paper.layout === 'parts' && (
				<>
					{ paper.parts.map( ( part ) => (
						<div className="ohmylms-print-part" key={ part.id }>
							<div className="ohmylms-print-head">
								<span className="ohmylms-print-part-label">
									{ part.label && `(${ part.label })` }
								</span>
								<div className="ohmylms-print-prompt">
									{ question.public ||
									question.figure_part_html ? (
										<RawHTML>{ part.prompt }</RawHTML>
									) : (
										part.prompt
									) }
								</div>
								{ marks && <Marks marks={ part.marks } /> }
							</div>
							<AnswerSpace item={ part } />
							{ showAnswers && (
								<Key
									answers={ part.answers }
									rubric={ part.rubric }
								/>
							) }
						</div>
					) ) }
					{ marks && paper.parts.length > 1 && (
						<div className="ohmylms-print-total">
							<Marks marks={ paper.marks } total />
						</div>
					) }
				</>
			) }
			{ TYPED_LAYOUTS.includes( paper.layout ) && (
				<TypedBody paper={ paper } />
			) }
			{ paper.layout === 'unsupported' && (
				<>
					{ editor && (
						<p className="ohmylms-print-note">
							{ __(
								'This question type has no printed form yet. Its statement prints with blank answer space.',
								'ohmylms'
							) }
						</p>
					) }
					<AnswerSpace item={ { layout: 'lines', lines: 4 } } />
				</>
			) }
		</div>
	);
}
