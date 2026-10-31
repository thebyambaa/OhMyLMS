import {
	Button,
	CheckboxControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	flag,
	inlineBlanks,
	MAX_ANSWER,
	MAX_OPTIONS,
	moveOption,
	newOption,
} from './model';
import { errorId, FieldError } from './OptionsEditor';
import type { FormErrors, FormOption, QuestionSettings } from './types';

/**
 * Expected typed answers: one text box per answer, all correct. Statement questions show learners one
 * box per answer and compare in order; fill-in-the-blank questions without `{brackets}` accept them.
 *
 * @param props          Component props.
 * @param props.type     Question type id.
 * @param props.options  Answers in order.
 * @param props.errors   Field errors.
 * @param props.disabled Whether editing is off.
 * @param props.onChange Receives the new answer list.
 */
export function AcceptedAnswers( {
	type,
	options,
	errors,
	disabled,
	onChange,
}: {
	type: string;
	options: FormOption[];
	errors: FormErrors;
	disabled: boolean;
	onChange: ( next: FormOption[] ) => void;
} ) {
	const statement = 'statement' === type;
	const update = ( index: number, answer: string ) =>
		onChange(
			options.map( ( item, position ) =>
				position === index
					? { ...item, answer, is_correct: true }
					: item
			)
		);
	return (
		<fieldset
			disabled={ disabled }
			aria-describedby={
				errors.options ? errorId( 'options' ) : undefined
			}
		>
			<legend>
				{ statement
					? __( 'Expected answers', 'ohmylms' )
					: __( 'Accepted answers', 'ohmylms' ) }
			</legend>
			<p>
				{ statement
					? __(
							'Learners get one answer box per expected answer and must type each exactly as written here, in order.',
							'ohmylms'
						)
					: __(
							'Learners type each answer in its own box, in this order.',
							'ohmylms'
						) }
			</p>
			<FieldError field="options" message={ errors.options } />
			<ol className="ohmylms-question-options">
				{ options.map( ( item, index ) => {
					const key = `options.${ index }.answer`;
					const number = index + 1;
					return (
						<li
							key={ item.key }
							className="ohmylms-question-option"
						>
							<TextControl
								label={ sprintf(
									/* translators: %d: answer number. */
									__( 'Answer %d', 'ohmylms' ),
									number
								) }
								value={ item.answer }
								maxLength={ MAX_ANSWER }
								aria-invalid={
									errors[ key ] ? true : undefined
								}
								aria-describedby={
									errors[ key ] ? errorId( key ) : undefined
								}
								onChange={ ( answer ) =>
									update( index, answer )
								}
							/>
							<FieldError
								field={ key }
								message={ errors[ key ] }
							/>
							<div className="ohmylms-question-option-actions">
								<Button
									variant="secondary"
									size="small"
									disabled={ 0 === index }
									aria-label={ sprintf(
										/* translators: %d: answer number. */
										__( 'Move answer %d up', 'ohmylms' ),
										number
									) }
									onClick={ () =>
										onChange(
											moveOption( options, index, -1 )
										)
									}
								>
									{ __( 'Move up', 'ohmylms' ) }
								</Button>
								<Button
									variant="secondary"
									size="small"
									disabled={ index === options.length - 1 }
									aria-label={ sprintf(
										/* translators: %d: answer number. */
										__( 'Move answer %d down', 'ohmylms' ),
										number
									) }
									onClick={ () =>
										onChange(
											moveOption( options, index, 1 )
										)
									}
								>
									{ __( 'Move down', 'ohmylms' ) }
								</Button>
								<Button
									variant="secondary"
									size="small"
									isDestructive
									disabled={
										statement && options.length <= 1
									}
									aria-label={ sprintf(
										/* translators: %d: answer number. */
										__( 'Remove answer %d', 'ohmylms' ),
										number
									) }
									onClick={ () =>
										onChange(
											options.filter(
												( _, position ) =>
													position !== index
											)
										)
									}
								>
									{ __( 'Remove', 'ohmylms' ) }
								</Button>
							</div>
						</li>
					);
				} ) }
			</ol>
			<Button
				variant="secondary"
				disabled={ options.length >= MAX_OPTIONS }
				onClick={ () => onChange( [ ...options, newOption( type ) ] ) }
			>
				{ __( 'Add answer', 'ohmylms' ) }
			</Button>
		</fieldset>
	);
}

/**
 * Fill-in-the-blank answers: `{answers}` written in the name (shown here as detected blanks, with
 * drag-or-type and case options), or, without brackets, accepted answers.
 *
 * @param props            Component props.
 * @param props.name       Current question name.
 * @param props.settings   Current settings.
 * @param props.options    Accepted answers.
 * @param props.errors     Field errors.
 * @param props.disabled   Whether editing is off.
 * @param props.onSettings Merges a settings patch.
 * @param props.onOptions  Receives the new accepted answers.
 */
export function BlankAnswers( {
	name,
	settings,
	options,
	errors,
	disabled,
	onSettings,
	onOptions,
}: {
	name: string;
	settings: QuestionSettings;
	options: FormOption[];
	errors: FormErrors;
	disabled: boolean;
	onSettings: ( patch: QuestionSettings ) => void;
	onOptions: ( next: FormOption[] ) => void;
} ) {
	const blanks = inlineBlanks( name );
	return (
		<>
			<fieldset disabled={ disabled }>
				<legend>{ __( 'Blanks', 'ohmylms' ) }</legend>
				<p id="oml-question-blanks-help">
					{ __(
						'Write each correct answer inside {curly brackets} in the Name field, for example: The capital of France is {Paris}. Learners see a blank for each one.',
						'ohmylms'
					) }
				</p>
				<p role="status">
					{ blanks.length
						? sprintf(
								/* translators: 1: number of blanks, 2: the answers, comma separated. */
								_n(
									'%1$d blank found: %2$s',
									'%1$d blanks found: %2$s',
									blanks.length,
									'ohmylms'
								),
								blanks.length,
								blanks.join( ', ' )
							)
						: __(
								'No blanks in the name yet. Without blanks, learners type into one box per accepted answer below.',
								'ohmylms'
							) }
				</p>
				<SelectControl
					label={ __( 'How learners fill the blanks', 'ohmylms' ) }
					value={ 'drag' === settings.blank_mode ? 'drag' : 'type' }
					options={ [
						{
							value: 'type',
							label: __( 'Type the answers', 'ohmylms' ),
						},
						{
							value: 'drag',
							label: __(
								'Drag the answers from a word bank',
								'ohmylms'
							),
						},
					] }
					onChange={ ( mode ) => onSettings( { blank_mode: mode } ) }
				/>
				<CheckboxControl
					label={ __( 'Answers are case-sensitive', 'ohmylms' ) }
					checked={ flag( settings.case_sensitive, true ) }
					onChange={ ( checked ) =>
						onSettings( { case_sensitive: checked } )
					}
				/>
			</fieldset>
			{ ! blanks.length && (
				<AcceptedAnswers
					type="fill-in-the-blank"
					options={ options }
					errors={ errors }
					disabled={ disabled }
					onChange={ onOptions }
				/>
			) }
		</>
	);
}

/**
 * Short and long answers have no answer key: a teacher marks each response.
 *
 * @param props      Component props.
 * @param props.type Question type id.
 */
export function ManualAnswer( { type }: { type: string } ) {
	return (
		<fieldset>
			<legend>{ __( 'Answer', 'ohmylms' ) }</legend>
			<p>
				{ 'long-text' === type
					? __(
							'Learners write a longer answer. A teacher marks it in the quiz attempts; the length limit is a quiz setting.',
							'ohmylms'
						)
					: __(
							'Learners type a short answer. A teacher marks it in the quiz attempts; the length limit is a quiz setting.',
							'ohmylms'
						) }
			</p>
		</fieldset>
	);
}
