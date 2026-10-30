import { createElement } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';
import { typeCounts } from './quizExchange.mjs';

export const CSV_TEMPLATE = [
	'type,question,points,correct,option_1,option_2,option_3,option_4',
	'single-choice,What is 2 + 2?,1,2,3,4,5,6',
	'multiple-choice,Which numbers are even?,2,1|3,2,3,4,5',
	'true-false,The sum of two odd numbers is even.,1,true,,,,',
	'short-text,Name a prime number.,1,,,,,',
].join( '\n' );

/**
 * What a checked file holds: counts, the question types, and anything worth a second look.
 *
 * @param {Object} props
 * @param {Object} props.preview Dry-run response.
 */
export function ImportPreview( { preview } ) {
	const { summary, warnings } = preview;
	const types = typeCounts( summary );
	return (
		<div className="ohmylms-import-preview">
			<p className="ohmylms-import-preview__headline">
				{ sprintf(
					/* translators: 1: number of questions, 2: total points */
					_n(
						'%1$d question, %2$s points',
						'%1$d questions, %2$s points',
						summary.questions,
						'ohmylms'
					),
					summary.questions,
					String( summary.points )
				) }
				{ summary.randomized > 0 &&
					sprintf(
						/* translators: %d: number of randomized questions */
						__( ', %d with randomized numbers', 'ohmylms' ),
						summary.randomized
					) }
			</p>
			<div className="ohmylms-quiz-skills">
				{ types.map( ( row ) => (
					<span className="ohmylms-quiz-skill-chip" key={ row.type }>
						{ row.label } × { row.count }
					</span>
				) ) }
			</div>
			{ preview.renamed && (
				<p>
					{ sprintf(
						/* translators: %s: new quiz name */
						__(
							'A quiz with this name exists, so the copy will be called "%s".',
							'ohmylms'
						),
						preview.name
					) }
				</p>
			) }
			{ warnings?.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					<ul className="ohmylms-import-list">
						{ warnings.map( ( warning ) => (
							<li key={ warning }>{ warning }</li>
						) ) }
					</ul>
				</Notice>
			) }
		</div>
	);
}
