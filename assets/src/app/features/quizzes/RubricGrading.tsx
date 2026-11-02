import { RadioControl, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { formatMarks, parseMarks, rubricMarks, scoresLabel } from './model';
import type { AttemptQuestion, RubricCriterion, RubricScores } from './types';

/**
 * The marking rubric of a short or long text answer, with a score per criterion (a level, or points
 * when the criterion has no levels), starting from the breakdown saved with the current grade. Once
 * every criterion is scored, the marks of the question are filled in from the total and the breakdown
 * is saved with them; marks typed over the rubric score are saved without a breakdown.
 *
 * @param props          Component props.
 * @param props.question Question of the attempt (with its rubric).
 * @param props.rubric   Its criteria.
 * @param props.scores   Points chosen per criterion.
 * @param props.marks    Marks typed for the question.
 * @param props.saved    Breakdown saved with the current grade, or `null`.
 * @param props.disabled Whether grading is off.
 * @param props.onChange Receives the new scores.
 */
export function RubricGrading( {
	question,
	rubric,
	scores,
	marks,
	saved,
	disabled,
	onChange,
}: {
	question: AttemptQuestion;
	rubric: RubricCriterion[];
	scores: RubricScores;
	marks: string;
	saved: Record< string, number > | null;
	disabled: boolean;
	onChange: ( next: RubricScores ) => void;
} ) {
	const result = rubricMarks( rubric, scores, question.max_marks );
	const typed = parseMarks( marks, question.max_marks );
	const overridden =
		result &&
		'marks' in result &&
		'marks' in typed &&
		Math.abs( typed.marks - result.marks ) > 0.00005;
	const set = ( id: string, value: string ) =>
		onChange( { ...scores, [ id ]: value } );
	let summary: string = __(
		'Score every criterion to fill in the marks.',
		'ohmylms'
	);
	if ( result && 'error' in result ) {
		summary = result.error;
	} else if ( result ) {
		summary =
			Math.abs( result.total - question.max_marks ) < 0.00005
				? sprintf(
						/* translators: 1: rubric score, 2: rubric total. */
						__( 'Rubric score: %1$s of %2$s.', 'ohmylms' ),
						formatMarks( result.score ),
						formatMarks( result.total )
					)
				: sprintf(
						/* translators: 1: rubric score, 2: rubric total, 3: marks, 4: maximum marks in the quiz. */
						__(
							'Rubric score: %1$s of %2$s, which is %3$s of %4$s marks in this quiz.',
							'ohmylms'
						),
						formatMarks( result.score ),
						formatMarks( result.total ),
						formatMarks( result.marks ),
						formatMarks( question.max_marks )
					);
	}
	return (
		<fieldset disabled={ disabled }>
			<legend>
				{ sprintf(
					/* translators: %s: question title. */
					__( 'Rubric for %s', 'ohmylms' ),
					question.title || question.type
				) }
			</legend>
			{ rubric.map( ( criterion ) => {
				const label = sprintf(
					/* translators: 1: criterion title, 2: its points. */
					__( '%1$s (out of %2$s)', 'ohmylms' ),
					criterion.title,
					formatMarks( criterion.points )
				);
				return (
					<div key={ criterion.id }>
						{ criterion.levels.length ? (
							<RadioControl
								label={ label }
								help={ criterion.description || undefined }
								selected={ scores[ criterion.id ] ?? '' }
								options={ criterion.levels.map( ( level ) => ( {
									value: formatMarks( level.points ),
									label: sprintf(
										/* translators: 1: points, 2: level description. */
										__( '%1$s — %2$s', 'ohmylms' ),
										formatMarks( level.points ),
										level.description
									),
								} ) ) }
								onChange={ ( value ) =>
									set( criterion.id, value )
								}
							/>
						) : (
							<TextControl
								label={ label }
								help={ criterion.description || undefined }
								value={ scores[ criterion.id ] ?? '' }
								inputMode="decimal"
								onChange={ ( value ) =>
									set( criterion.id, value )
								}
							/>
						) }
					</div>
				);
			} ) }
			<p role="status">
				{ summary }
				{ overridden &&
					' ' +
						__(
							'The marks differ from the rubric score, so this breakdown will not be saved.',
							'ohmylms'
						) }
			</p>
			{ saved && (
				<p>
					{ sprintf(
						/* translators: %s: saved points per criterion, such as "Reasoning: 3, Clarity: 1". */
						__( 'Saved with the current marks: %s.', 'ohmylms' ),
						scoresLabel( rubric, saved )
					) }
				</p>
			) }
		</fieldset>
	);
}
