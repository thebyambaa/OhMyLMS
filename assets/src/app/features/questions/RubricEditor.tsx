import { Button, TextControl, TextareaControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { errorId, FieldError } from './OptionsEditor';
import { newCriterion, newLevel, RUBRIC_LIMITS, rubricTotal } from './rubric';
import type { FormErrors, RubricCriterionForm, RubricLevelForm } from './types';

function move< T >( rows: readonly T[], index: number, offset: number ): T[] {
	const next = [ ...rows ];
	const moved = next[ index ];
	const other = next[ index + offset ];
	if ( undefined === moved || undefined === other ) {
		return next;
	}
	next[ index ] = other;
	next[ index + offset ] = moved;
	return next;
}

/**
 * The marking rubric of a short or long text question: criteria with a title, a description and
 * points, each optionally described by performance levels. Graders see it when they mark answers and
 * can score each criterion; learners never see it. With criteria, the question's points are their total.
 *
 * @param props          Component props.
 * @param props.rows     Criteria in order.
 * @param props.errors   Field errors (`rubric`, `rubric.<i>.<field>`, `rubric.<i>.levels.<j>.<field>`).
 * @param props.disabled Whether editing is off.
 * @param props.onChange Receives the new criteria.
 */
export function RubricEditor( {
	rows,
	errors,
	disabled,
	onChange,
}: {
	rows: RubricCriterionForm[];
	errors: FormErrors;
	disabled: boolean;
	onChange: ( next: RubricCriterionForm[] ) => void;
} ) {
	const total = rubricTotal( rows );
	const update = ( index: number, patch: Partial< RubricCriterionForm > ) =>
		onChange(
			rows.map( ( row, position ) =>
				position === index ? { ...row, ...patch } : row
			)
		);
	const updateLevel = (
		index: number,
		levels: RubricLevelForm[],
		position: number,
		patch: Partial< RubricLevelForm >
	) =>
		update( index, {
			levels: levels.map( ( level, at ) =>
				at === position ? { ...level, ...patch } : level
			),
		} );
	const field = ( key: string ) => ( {
		'aria-invalid': errors[ key ] ? true : undefined,
		'aria-describedby': errors[ key ] ? errorId( key ) : undefined,
	} );
	return (
		<fieldset
			disabled={ disabled }
			aria-describedby={ errors.rubric ? errorId( 'rubric' ) : undefined }
		>
			<legend>{ __( 'Marking rubric', 'ohmylms' ) }</legend>
			<p>
				{ __(
					'Optional. Graders see the rubric when they mark answers and can score each criterion; learners never see it. With a rubric, the question’s points are the total of its criteria.',
					'ohmylms'
				) }
			</p>
			<FieldError field="rubric" message={ errors.rubric } />
			{ rows.length > 0 && (
				<ol className="ohmylms-question-options">
					{ rows.map( ( row, index ) => {
						const number = index + 1;
						const path = `rubric.${ index }`;
						return (
							<li
								key={ row.key }
								className="ohmylms-question-option"
							>
								<TextControl
									label={ sprintf(
										/* translators: %d: criterion number. */
										__( 'Criterion %d', 'ohmylms' ),
										number
									) }
									value={ row.title }
									maxLength={ RUBRIC_LIMITS.title }
									required
									{ ...field( `${ path }.title` ) }
									onChange={ ( title ) =>
										update( index, { title } )
									}
								/>
								<FieldError
									field={ `${ path }.title` }
									message={ errors[ `${ path }.title` ] }
								/>
								<TextareaControl
									label={ sprintf(
										/* translators: %d: criterion number. */
										__(
											'What criterion %d looks for',
											'ohmylms'
										),
										number
									) }
									value={ row.description }
									rows={ 2 }
									maxLength={ RUBRIC_LIMITS.description }
									{ ...field( `${ path }.description` ) }
									onChange={ ( description ) =>
										update( index, { description } )
									}
								/>
								<FieldError
									field={ `${ path }.description` }
									message={
										errors[ `${ path }.description` ]
									}
								/>
								<TextControl
									label={ sprintf(
										/* translators: %d: criterion number. */
										__(
											'Points of criterion %d',
											'ohmylms'
										),
										number
									) }
									type="number"
									min={ 0 }
									max={ RUBRIC_LIMITS.points }
									step="any"
									value={ row.points }
									{ ...field( `${ path }.points` ) }
									onChange={ ( points ) =>
										update( index, { points } )
									}
								/>
								<FieldError
									field={ `${ path }.points` }
									message={ errors[ `${ path }.points` ] }
								/>
								{ row.levels.length > 0 && (
									<ul
										aria-label={ sprintf(
											/* translators: %d: criterion number. */
											__(
												'Levels of criterion %d',
												'ohmylms'
											),
											number
										) }
									>
										{ row.levels.map(
											( level, position ) => {
												const levelPath = `${ path }.levels.${ position }`;
												const label = sprintf(
													/* translators: 1: level number, 2: criterion number. */
													__(
														'level %1$d of criterion %2$d',
														'ohmylms'
													),
													position + 1,
													number
												);
												return (
													<li key={ level.key }>
														<TextControl
															label={ sprintf(
																/* translators: %s: level, such as "level 1 of criterion 2". */
																__(
																	'Points for %s',
																	'ohmylms'
																),
																label
															) }
															type="number"
															min={ 0 }
															step="any"
															value={
																level.points
															}
															{ ...field(
																`${ levelPath }.points`
															) }
															onChange={ (
																points
															) =>
																updateLevel(
																	index,
																	row.levels,
																	position,
																	{ points }
																)
															}
														/>
														<FieldError
															field={ `${ levelPath }.points` }
															message={
																errors[
																	`${ levelPath }.points`
																]
															}
														/>
														<TextControl
															label={ sprintf(
																/* translators: %s: level, such as "level 1 of criterion 2". */
																__(
																	'Description of %s',
																	'ohmylms'
																),
																label
															) }
															value={
																level.description
															}
															maxLength={
																RUBRIC_LIMITS.level
															}
															{ ...field(
																`${ levelPath }.description`
															) }
															onChange={ (
																description
															) =>
																updateLevel(
																	index,
																	row.levels,
																	position,
																	{
																		description,
																	}
																)
															}
														/>
														<FieldError
															field={ `${ levelPath }.description` }
															message={
																errors[
																	`${ levelPath }.description`
																]
															}
														/>
														<Button
															variant="secondary"
															size="small"
															isDestructive
															aria-label={ sprintf(
																/* translators: %s: level, such as "level 1 of criterion 2". */
																__(
																	'Remove %s',
																	'ohmylms'
																),
																label
															) }
															onClick={ () =>
																update( index, {
																	levels: row.levels.filter(
																		(
																			_,
																			at
																		) =>
																			at !==
																			position
																	),
																} )
															}
														>
															{ __(
																'Remove level',
																'ohmylms'
															) }
														</Button>
													</li>
												);
											}
										) }
									</ul>
								) }
								<div className="ohmylms-question-option-actions">
									<Button
										variant="secondary"
										size="small"
										disabled={
											row.levels.length >=
											RUBRIC_LIMITS.levels
										}
										aria-label={ sprintf(
											/* translators: %d: criterion number. */
											__(
												'Add a level to criterion %d',
												'ohmylms'
											),
											number
										) }
										onClick={ () =>
											update( index, {
												levels: [
													...row.levels,
													newLevel(),
												],
											} )
										}
									>
										{ __( 'Add level', 'ohmylms' ) }
									</Button>
									<Button
										variant="secondary"
										size="small"
										disabled={ 0 === index }
										aria-label={ sprintf(
											/* translators: %d: criterion number. */
											__(
												'Move criterion %d up',
												'ohmylms'
											),
											number
										) }
										onClick={ () =>
											onChange( move( rows, index, -1 ) )
										}
									>
										{ __( 'Move up', 'ohmylms' ) }
									</Button>
									<Button
										variant="secondary"
										size="small"
										disabled={ index === rows.length - 1 }
										aria-label={ sprintf(
											/* translators: %d: criterion number. */
											__(
												'Move criterion %d down',
												'ohmylms'
											),
											number
										) }
										onClick={ () =>
											onChange( move( rows, index, 1 ) )
										}
									>
										{ __( 'Move down', 'ohmylms' ) }
									</Button>
									<Button
										variant="secondary"
										size="small"
										isDestructive
										aria-label={ sprintf(
											/* translators: %d: criterion number. */
											__(
												'Remove criterion %d',
												'ohmylms'
											),
											number
										) }
										onClick={ () =>
											onChange(
												rows.filter(
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
			) }
			{ rows.length > 0 && (
				<p role="status">
					{ null === total
						? __(
								'Enter the points of every criterion to see the total.',
								'ohmylms'
							)
						: sprintf(
								/* translators: %s: total points. */
								__( 'Rubric total: %s points', 'ohmylms' ),
								String( total )
							) }
				</p>
			) }
			<Button
				variant="secondary"
				disabled={ rows.length >= RUBRIC_LIMITS.criteria }
				onClick={ () => onChange( [ ...rows, newCriterion( rows ) ] ) }
			>
				{ __( 'Add criterion', 'ohmylms' ) }
			</Button>
		</fieldset>
	);
}
