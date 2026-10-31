import { Button, CheckboxControl, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { MediaChoice } from './MediaChoice';
import {
	correctMode,
	MAX_ANSWER,
	MAX_LABEL,
	MAX_OPTIONS,
	moveOption,
	newOption,
	setCorrect,
} from './model';
import type { FormErrors, FormOption } from './types';

/**
 * Element id of a field's error message.
 *
 * @param key Error key such as `name` or `options.2.label`.
 * @return The id.
 */
export function errorId( key: string ): string {
	return `oml-question-${ key.replace( /\./g, '-' ) }-error`;
}

/**
 * A field's error message, announced when it appears.
 *
 * @param props         Component props.
 * @param props.field   Error key.
 * @param props.message Message, if any.
 */
export function FieldError( {
	field,
	message,
}: {
	field: string;
	message?: string | undefined;
} ) {
	return message ? (
		<p id={ errorId( field ) } role="alert">
			{ message }
		</p>
	) : null;
}

/**
 * Answer options of the built-in option types: text, images, correct answers (one or many, or none
 * for reorder and matching), matching labels and images, order, and adding or removing options.
 *
 * @param props          Component props.
 * @param props.type     Question type id.
 * @param props.options  Options in order.
 * @param props.errors   Field errors (`options`, `options.<i>.answer`, `options.<i>.label`).
 * @param props.disabled Whether editing is off.
 * @param props.onChange Receives the new option list.
 */
export function OptionsEditor( {
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
	const mode = correctMode( type );
	const fixed = 'true-false' === type;
	const update = ( index: number, change: Partial< FormOption > ) =>
		onChange(
			options.map( ( item, position ) =>
				position === index ? { ...item, ...change } : item
			)
		);
	return (
		<fieldset
			disabled={ disabled }
			aria-describedby={
				errors.options ? errorId( 'options' ) : undefined
			}
		>
			<legend>{ __( 'Answer options', 'ohmylms' ) }</legend>
			<p>
				{ 'single' === mode &&
					__( 'Mark the one correct option.', 'ohmylms' ) }
				{ 'multiple' === mode &&
					__( 'Mark every correct option.', 'ohmylms' ) }
				{ 'reorder' === type &&
					__(
						'Enter the options in the correct order. Learners see them shuffled.',
						'ohmylms'
					) }
				{ 'matching' === type &&
					__(
						'Enter each option and the answer it matches.',
						'ohmylms'
					) }
			</p>
			<FieldError field="options" message={ errors.options } />
			<ol className="ohmylms-question-options">
				{ options.map( ( item, index ) => {
					const number = index + 1;
					const answerKey = `options.${ index }.answer`;
					const labelKey = `options.${ index }.label`;
					return (
						<li
							key={ item.key }
							className="ohmylms-question-option"
						>
							<TextControl
								label={ sprintf(
									/* translators: %d: option number. */
									__( 'Option %d', 'ohmylms' ),
									number
								) }
								value={ item.answer }
								maxLength={ MAX_ANSWER }
								aria-invalid={
									errors[ answerKey ] ? true : undefined
								}
								aria-describedby={
									errors[ answerKey ]
										? errorId( answerKey )
										: undefined
								}
								onChange={ ( answer ) =>
									update( index, { answer } )
								}
							/>
							<FieldError
								field={ answerKey }
								message={ errors[ answerKey ] }
							/>
							{ ! fixed && (
								<MediaChoice
									kind="image"
									label={ sprintf(
										/* translators: %d: option number. */
										__( 'image of option %d', 'ohmylms' ),
										number
									) }
									value={ {
										id: item.image_id,
										url: item.image_url,
									} }
									disabled={ disabled }
									onChange={ ( image ) =>
										update( index, {
											image_id: image.id,
											image_url: image.url,
										} )
									}
								/>
							) }
							{ 'matching' === type && (
								<>
									<TextControl
										label={ sprintf(
											/* translators: %d: option number. */
											__(
												'Match for option %d',
												'ohmylms'
											),
											number
										) }
										value={ item.matching?.label ?? '' }
										maxLength={ MAX_LABEL }
										aria-invalid={
											errors[ labelKey ]
												? true
												: undefined
										}
										aria-describedby={
											errors[ labelKey ]
												? errorId( labelKey )
												: undefined
										}
										onChange={ ( label ) =>
											update( index, {
												matching: {
													...( item.matching ?? {} ),
													label,
												},
											} )
										}
									/>
									<FieldError
										field={ labelKey }
										message={ errors[ labelKey ] }
									/>
									<MediaChoice
										kind="image"
										label={ sprintf(
											/* translators: %d: option number. */
											__(
												'image of the match for option %d',
												'ohmylms'
											),
											number
										) }
										value={ {
											id: 0,
											url: item.matching?.image_url ?? '',
										} }
										disabled={ disabled }
										onChange={ ( image ) =>
											update( index, {
												matching: {
													...( item.matching ?? {} ),
													image_url: image.url,
												},
											} )
										}
									/>
								</>
							) }
							{ 'none' !== mode && (
								<CheckboxControl
									label={ sprintf(
										/* translators: %d: option number. */
										__( 'Option %d is correct', 'ohmylms' ),
										number
									) }
									checked={ item.is_correct }
									onChange={ ( checked ) =>
										onChange(
											setCorrect(
												options,
												index,
												checked,
												type
											)
										)
									}
								/>
							) }
							<div className="ohmylms-question-option-actions">
								<Button
									variant="secondary"
									size="small"
									disabled={ 0 === index }
									aria-label={ sprintf(
										/* translators: %d: option number. */
										__( 'Move option %d up', 'ohmylms' ),
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
										/* translators: %d: option number. */
										__( 'Move option %d down', 'ohmylms' ),
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
								{ ! fixed && (
									<Button
										variant="secondary"
										size="small"
										isDestructive
										disabled={ options.length <= 2 }
										aria-label={ sprintf(
											/* translators: %d: option number. */
											__( 'Remove option %d', 'ohmylms' ),
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
								) }
							</div>
						</li>
					);
				} ) }
			</ol>
			{ ! fixed && (
				<Button
					variant="secondary"
					disabled={ options.length >= MAX_OPTIONS }
					onClick={ () =>
						onChange( [ ...options, newOption( type ) ] )
					}
				>
					{ __( 'Add option', 'ohmylms' ) }
				</Button>
			) }
		</fieldset>
	);
}
