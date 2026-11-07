import { __ } from '@wordpress/i18n';
import { Button } from '../../ui/Button';
import { Input } from '../../ui/Input';
import { Select } from '../../ui/Select';
import { Textarea } from '../../ui/Textarea';
import { type Question, TYPES, blank, studioType } from './quiz-model';

/**
 * Right panel: type, skill, hint and explanation for the current question, and its preview.
 *
 * @param props           Props.
 * @param props.question  The question being edited.
 * @param props.skills    Skill choices.
 * @param props.readOnly  Whether editing is off.
 * @param props.onChange  New question value.
 * @param props.onPreview Open the preview.
 * @param props.onRemove  Remove the question; absent when the person may not edit.
 */
export function QuizPanel( {
	question,
	skills,
	readOnly,
	onChange,
	onPreview,
	onRemove,
}: {
	question: Question;
	skills: Array< { id: number; name: string } >;
	readOnly: boolean;
	onChange: ( next: Question ) => void;
	onPreview: () => void;
	onRemove?: () => void;
} ) {
	return (
		<aside
			className="oml-qe-panel"
			aria-label={ __( 'Question settings', 'ohmylms' ) }
		>
			<h2>{ __( 'Question settings', 'ohmylms' ) }</h2>
			<Select
				label={ __( 'Type', 'ohmylms' ) }
				help={
					question.id
						? __(
								'Changing the type resets the answers.',
								'ohmylms'
							)
						: undefined
				}
				disabled={ readOnly }
				value={ studioType( question.type ) }
				onChange={ ( event ) =>
					onChange( {
						...blank( event.target.value ),
						id: question.id,
						prompt: question.prompt,
						hint: question.hint,
						explanation: question.explanation,
						skill: question.skill,
					} )
				}
				options={ TYPES.map( ( [ value, label ] ) => ( {
					value,
					label,
				} ) ) }
			/>
			<Select
				label={ __( 'Skill', 'ohmylms' ) }
				disabled={ readOnly }
				value={ question.skill }
				onChange={ ( event ) =>
					onChange( {
						...question,
						skill: Number( event.target.value ),
					} )
				}
			>
				<option value={ 0 }>{ __( 'None', 'ohmylms' ) }</option>
				{ skills.map( ( skill ) => (
					<option key={ skill.id } value={ skill.id }>
						{ skill.name }
					</option>
				) ) }
			</Select>
			<Input
				label={ __( 'Hint', 'ohmylms' ) }
				disabled={ readOnly }
				value={ question.hint }
				onChange={ ( event ) =>
					onChange( { ...question, hint: event.target.value } )
				}
			/>
			<Textarea
				label={ __( 'Explanation', 'ohmylms' ) }
				rows={ 3 }
				disabled={ readOnly }
				value={ question.explanation }
				onChange={ ( event ) =>
					onChange( { ...question, explanation: event.target.value } )
				}
			/>
			{ onRemove && (
				<Button variant="tertiary" onClick={ onRemove }>
					{ __( 'Remove question', 'ohmylms' ) }
				</Button>
			) }
			<Button onClick={ onPreview }>
				{ __( 'Preview question', 'ohmylms' ) }
			</Button>
		</aside>
	);
}
