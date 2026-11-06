import { __, sprintf } from '@wordpress/i18n';
import { Button } from '../../ui/Button';
import { Input } from '../../ui/Input';
import { type Choice, type Question, hasTiles, studioType } from './quiz-model';

const LETTERS = [ 'A', 'B', 'C', 'D', 'E', 'F' ];

/**
 * Letter of an answer position.
 *
 * @param index Zero-based position.
 */
function letterOf( index: number ) {
	return LETTERS[ index ] ?? String( index + 1 );
}

/**
 * Centre canvas: the question and its answers as large tiles. The check marks the correct answer(s).
 *
 * @param props          Props.
 * @param props.question The question being edited.
 * @param props.readOnly Whether editing is off.
 * @param props.onChange New question value.
 */
export function QuizCanvas( {
	question,
	readOnly,
	onChange,
}: {
	question: Question;
	readOnly: boolean;
	onChange: ( next: Question ) => void;
} ) {
	const type = studioType( question.type );
	const multiple = type === 'multiple_choice';
	const fixedChoices = type === 'true_false';

	function setChoice( index: number, patch: Partial< Choice > ) {
		const choices = question.choices.map( ( choice, at ) => {
			if ( at === index ) {
				return { ...choice, ...patch };
			}
			// One correct answer unless the type allows several.
			return patch.correct && ! multiple
				? { ...choice, correct: false }
				: choice;
		} );
		onChange( { ...question, choices } );
	}

	return (
		<div className="oml-qe-canvas">
			<Input
				label={ __( 'Question', 'ohmylms' ) }
				placeholder={ __( 'Type your question here', 'ohmylms' ) }
				size="lg"
				disabled={ readOnly }
				value={ question.prompt }
				onChange={ ( event ) =>
					onChange( { ...question, prompt: event.target.value } )
				}
			/>
			{ hasTiles( type ) ? (
				<ul
					className="oml-qe-tiles"
					aria-label={ __( 'Answers', 'ohmylms' ) }
				>
					{ question.choices.map( ( choice, index ) => (
						<li
							key={ choice.id || `c-${ index }` }
							className={
								choice.correct
									? `oml-qe-tile is-correct oml-qe-tone-${ index % 4 }`
									: `oml-qe-tile oml-qe-tone-${ index % 4 }`
							}
						>
							<span className="oml-qe-letter" aria-hidden="true">
								{ letterOf( index ) }
							</span>
							<Input
								label={ sprintf(
									/* translators: %s: answer letter. */
									__( 'Answer %s', 'ohmylms' ),
									letterOf( index )
								) }
								placeholder={ __(
									'Add answer text',
									'ohmylms'
								) }
								disabled={ readOnly || fixedChoices }
								value={ choice.text }
								onChange={ ( event ) =>
									setChoice( index, {
										text: event.target.value,
									} )
								}
							/>
							<button
								type="button"
								className="oml-qe-check"
								aria-pressed={ choice.correct }
								aria-label={ sprintf(
									/* translators: %s: answer letter. */
									__(
										'Mark answer %s as correct',
										'ohmylms'
									),
									letterOf( index )
								) }
								disabled={ readOnly }
								onClick={ () =>
									setChoice( index, {
										correct: multiple
											? ! choice.correct
											: true,
									} )
								}
							>
								<span aria-hidden="true">
									{ choice.correct ? '✓' : '' }
								</span>
							</button>
							{ ! readOnly &&
								! fixedChoices &&
								! choice.id &&
								question.choices.length > 2 && (
									<Button
										variant="tertiary"
										size="sm"
										onClick={ () =>
											onChange( {
												...question,
												choices:
													question.choices.filter(
														( _, at ) =>
															at !== index
													),
											} )
										}
									>
										{ __( 'Remove answer', 'ohmylms' ) }
									</Button>
								) }
						</li>
					) ) }
				</ul>
			) : (
				<Input
					label={ __( 'Correct answer', 'ohmylms' ) }
					help={
						type === 'numerical'
							? __( 'The number learners must enter.', 'ohmylms' )
							: __( 'The text learners must enter.', 'ohmylms' )
					}
					disabled={ readOnly }
					value={ question.choices[ 0 ]?.text ?? '' }
					onChange={ ( event ) =>
						onChange( {
							...question,
							choices: [
								{
									...question.choices[ 0 ],
									text: event.target.value,
									correct: true,
								},
							],
						} )
					}
				/>
			) }
			{ ! readOnly &&
				hasTiles( type ) &&
				! fixedChoices &&
				question.choices.length < 6 && (
					<Button
						variant="tertiary"
						onClick={ () =>
							onChange( {
								...question,
								choices: question.choices.concat( [
									{ text: '', correct: false },
								] ),
							} )
						}
					>
						{ __( 'Add answer', 'ohmylms' ) }
					</Button>
				) }
		</div>
	);
}
