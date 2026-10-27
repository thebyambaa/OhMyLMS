/** @jsx createElement */
import { createElement } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { PaperHeaderView } from './PaperBlockViews';
import { paperQuestion } from './paperModel.mjs';

/**
 * Answer sheet for the choice questions of a paper version: a row of lettered circles per
 * question, numbered as the paper prints them. The marking sheet fills the correct circles.
 *
 * @param {Object} props         Properties.
 * @param {Object} props.context Print context: data, settings, version, answers, derived values.
 * @return {Element} Answer sheet.
 */
export function AnswerSheet( { context } ) {
	const { data, settings, version, showAnswers, derived } = context;
	const rows = [];
	const elsewhere = [];
	for ( const item of derived.questions ) {
		const label = derived.labels.get( item );
		if ( ! label ) {
			continue;
		}
		const paper = paperQuestion( item.question, {
			settings,
			showAnswers,
			version,
		} );
		if ( paper.layout === 'choice' ) {
			rows.push( { label, paper } );
		} else {
			elsewhere.push( label );
		}
	}
	const subtitle = showAnswers
		? __( 'Marking sheet', 'ohmylms' )
		: __( 'Answer sheet', 'ohmylms' );
	return (
		<div className="ohmylms-answer-sheet">
			<PaperHeaderView
				attributes={ {
					fields: [ 'name', 'number' ],
					showTime: false,
					showMarks: false,
				} }
				title={ data.title }
				minutes={ 0 }
				total={ 0 }
				version={ settings.versions > 1 ? version : '' }
				text={ ( key, Tag, className ) =>
					key === 'subtitle' ? (
						<Tag className={ className }>{ subtitle }</Tag>
					) : null
				}
			/>
			{ ! showAnswers && rows.length > 0 && (
				<p className="ohmylms-print-instruction">
					{ __(
						'Fill in the circle for your answer. Where a question asks you to select all that apply, fill in every circle you choose.',
						'ohmylms'
					) }
				</p>
			) }
			{ rows.length > 0 ? (
				<ol className="ohmylms-answer-sheet-rows">
					{ rows.map( ( row ) => (
						<li
							key={ row.label }
							className={
								row.paper.multiple ? 'is-multiple' : ''
							}
						>
							<span className="ohmylms-print-number">
								{ row.label }
							</span>
							{ row.paper.options.map( ( option ) => (
								<span
									key={ option.letter }
									className={ `ohmylms-answer-sheet-bubble${
										option.correct ? ' is-filled' : ''
									}` }
								>
									{ option.letter }
								</span>
							) ) }
						</li>
					) ) }
				</ol>
			) : (
				<p>
					{ __(
						'This exam has no multiple choice or true or false questions for an answer sheet.',
						'ohmylms'
					) }
				</p>
			) }
			{ rows.length > 0 && elsewhere.length > 0 && (
				<p className="ohmylms-print-instruction">
					{ sprintf(
						/* translators: %s: question numbers. */
						_n(
							'Answer question %s on the question paper.',
							'Answer questions %s on the question paper.',
							elsewhere.length,
							'ohmylms'
						),
						elsewhere.join( ', ' )
					) }
				</p>
			) }
		</div>
	);
}
