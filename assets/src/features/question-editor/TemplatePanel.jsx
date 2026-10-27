import { createElement, Fragment, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import {
	VARIABLE_TYPES,
	defaultTemplate,
	nextVariableName,
	parseLines,
	parseValues,
	setPathSuggestions,
	templateIssues,
	variableDefaults,
} from './templateModel.mjs';
import { FormulaControl } from '../math/FormulaControl';
const variableLabels = {
	int: __( 'Whole number', 'ohmylms' ),
	decimal: __( 'Decimal', 'ohmylms' ),
	choice: __( 'Pick from a list', 'ohmylms' ),
	expr: __( 'Calculated', 'ohmylms' ),
};
const request = ( options ) => window.wp.apiFetch( options );

/**
 * Question text without markup, for a one-line example.
 * @param html
 */
const plain = ( html ) =>
	String( html || '' )
		.replace( /<[^>]+>/g, ' ' )
		.replace( /\s+/g, ' ' )
		.trim();

/**
 * The example's wording: the question text where it carries the question, else the title.
 * @param sample
 */
export const exampleText = ( sample ) => plain( sample.body ) || sample.title;

/**
 * A number field that stores a number, or nothing while empty.
 * @param value
 * @param onChange
 * @param extra
 */
const number = ( value, onChange, extra = {} ) => ( {
	type: 'number',
	step: 'any',
	value: value ?? '',
	onChange: ( text ) => onChange( text === '' ? undefined : Number( text ) ),
	...extra,
} );

/**
 * Text kept as typed while a parsed value is stored (so a trailing comma or newline is not eaten).
 * @param root0
 * @param root0.value
 * @param root0.format
 * @param root0.parse
 * @param root0.onChange
 * @param root0.multiline
 * @param root0.label
 * @param root0.help
 */
function ParsedField( {
	value,
	format,
	parse,
	onChange,
	multiline,
	label,
	help,
} ) {
	const [ text, setText ] = useState( () => format( value ) );
	const shown =
		format( parse( text ) ) === format( value ) ? text : format( value );
	const Control = multiline ? TextareaControl : TextControl;
	return (
		<Control
			label={ label }
			help={ help }
			value={ shown }
			onChange={ ( next ) => {
				setText( next );
				onChange( parse( next ) );
			} }
		/>
	);
}

/**
 * "Randomize numbers": turns a question into a template that draws new numbers every time
 * it is issued, with the learner's worked solution and answer following the same numbers.
 * @param root0
 * @param root0.question
 * @param root0.onChange
 * @param root0.readOnly
 */
export function TemplatePanel( { question, onChange, readOnly } ) {
	const settings = question.settings || {};
	const template = settings.template;
	const [ preview, setPreview ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ sampleCount, setSampleCount ] = useState( 5 );
	const save = ( next ) =>
		onChange( {
			settings: next
				? {
						...settings,
						template: next,
					}
				: ( ( { template: removed, ...rest } ) => rest )( settings ),
		} );
	const patch = ( fields ) =>
		save( {
			...template,
			...fields,
		} );
	if ( ! template ) {
		return (
			<details className="ohmylms-template-panel">
				<summary>{ __( 'Randomize numbers', 'ohmylms' ) }</summary>
				<p>
					{ __(
						'Make this one question endless: every learner, attempt and practice round gets different numbers, and the answer and worked solution follow them.',
						'ohmylms'
					) }
				</p>
				<Button
					variant="secondary"
					disabled={ readOnly }
					onClick={ () => save( defaultTemplate() ) }
				>
					{ __( 'Use random numbers', 'ohmylms' ) }
				</Button>
			</details>
		);
	}
	const variables = template.variables || [];
	const setVariable = ( index, fields ) =>
		patch( {
			variables: variables.map( ( v, i ) =>
				i === index
					? {
							...v,
							...fields,
						}
					: v
			),
		} );
	const rules = template.set || [];
	const setRule = ( index, fields ) =>
		patch( {
			set: rules.map( ( r, i ) =>
				i === index
					? {
							...r,
							...fields,
						}
					: r
			),
		} );
	const issues = templateIssues( template );
	const suggestions = setPathSuggestions( settings.type );
	const showExamples = async () => {
		setBusy( true );
		try {
			setPreview(
				await request( {
					path: '/ohmylms/v1/question-template/preview',
					method: 'POST',
					data: {
						name: question.name || '',
						description: question.description || '',
						settings,
						questions: question.questions || [],
						count: sampleCount,
					},
				} )
			);
		} catch ( error ) {
			setPreview( {
				valid: false,
				message:
					error?.message ||
					__( 'Could not build examples.', 'ohmylms' ),
				samples: [],
			} );
		}
		setBusy( false );
	};
	return (
		<details className="ohmylms-template-panel" open>
			<summary>{ __( 'Randomize numbers', 'ohmylms' ) }</summary>
			<p>
				{ __(
					'Write {{a}} anywhere in the question, answers, hint or worked solution. {{a*b}} calculates, {{x:2}} shows two decimals and {{b:+}} always shows a sign, so 3x{{b:+}} reads 3x-4 or 3x+4.',
					'ohmylms'
				) }
			</p>
			<h4>{ __( 'Numbers to draw', 'ohmylms' ) }</h4>
			{ variables.map( ( variable, index ) => (
				<fieldset key={ index } className="ohmylms-interactive-row">
					<TextControl
						label={ __( 'Letter', 'ohmylms' ) }
						maxLength={ 1 }
						value={ variable.name || '' }
						disabled={ readOnly }
						onChange={ ( name ) =>
							setVariable( index, {
								name: name.toLowerCase().slice( -1 ),
							} )
						}
					/>
					<SelectControl
						label={ __( 'Kind', 'ohmylms' ) }
						value={ variable.type }
						disabled={ readOnly }
						options={ VARIABLE_TYPES.map(
							( [ value, label ] ) => ( {
								value,
								label: variableLabels[ value ] || label,
							} )
						) }
						onChange={ ( type ) =>
							setVariable(
								index,
								variableDefaults( type, variable.name )
							)
						}
					/>
					{ [ 'int', 'decimal' ].includes( variable.type ) && (
						<Fragment>
							<TextControl
								label={ __( 'From', 'ohmylms' ) }
								{ ...number( variable.min, ( min ) =>
									setVariable( index, {
										min,
									} )
								) }
							/>
							<TextControl
								label={ __( 'To', 'ohmylms' ) }
								{ ...number( variable.max, ( max ) =>
									setVariable( index, {
										max,
									} )
								) }
							/>
						</Fragment>
					) }
					{ variable.type === 'int' && (
						<Fragment>
							<TextControl
								label={ __( 'Step', 'ohmylms' ) }
								{ ...number(
									variable.step,
									( step ) =>
										setVariable( index, {
											step,
										} ),
									{
										min: 1,
										step: 1,
									}
								) }
							/>
							<ParsedField
								label={ __(
									'Excluded values (comma separated)',
									'ohmylms'
								) }
								value={ variable.exclude || [] }
								format={ ( list ) => list.join( ', ' ) }
								parse={ ( text ) =>
									text
										.split( ',' )
										.filter( ( item ) => item.trim() )
										.map( Number )
										.filter( Number.isFinite )
								}
								onChange={ ( exclude ) =>
									setVariable( index, {
										exclude,
									} )
								}
							/>
						</Fragment>
					) }
					{ variable.type === 'decimal' && (
						<TextControl
							label={ __( 'Decimal places', 'ohmylms' ) }
							{ ...number(
								variable.places,
								( places ) =>
									setVariable( index, {
										places,
									} ),
								{
									min: 0,
									max: 6,
									step: 1,
								}
							) }
						/>
					) }
					{ variable.type === 'choice' && (
						<ParsedField
							label={ __(
								'Options (separated by commas)',
								'ohmylms'
							) }
							value={ variable.values }
							format={ ( list ) => ( list || [] ).join( ', ' ) }
							parse={ parseValues }
							onChange={ ( values ) =>
								setVariable( index, {
									values,
								} )
							}
						/>
					) }
					{ variable.type === 'expr' && (
						<Fragment>
							<FormulaControl
								label={ __( 'Formula', 'ohmylms' ) }
								help={ __(
									'Uses the letters above it, e.g. a*b or gcd(a,b).',
									'ohmylms'
								) }
								value={ variable.expr || '' }
								onChange={ ( expr ) =>
									setVariable( index, {
										expr,
									} )
								}
							/>
							<TextControl
								label={ __( 'Round to places', 'ohmylms' ) }
								{ ...number(
									variable.places,
									( places ) =>
										setVariable( index, {
											places,
										} ),
									{
										min: 0,
										max: 9,
										step: 1,
									}
								) }
							/>
						</Fragment>
					) }
					<Button
						isDestructive
						variant="tertiary"
						disabled={ readOnly || variables.length <= 1 }
						onClick={ () =>
							patch( {
								variables: variables.filter(
									( _, i ) => i !== index
								),
							} )
						}
					>
						{ __( 'Remove', 'ohmylms' ) }
					</Button>
				</fieldset>
			) ) }
			<Button
				variant="secondary"
				disabled={ readOnly || ! nextVariableName( variables ) }
				onClick={ () =>
					patch( {
						variables: [
							...variables,
							variableDefaults(
								'int',
								nextVariableName( variables )
							),
						],
					} )
				}
			>
				{ __( 'Add a number', 'ohmylms' ) }
			</Button>
			<ParsedField
				multiline
				label={ __( 'Conditions (one per line, optional)', 'ohmylms' ) }
				help={ __(
					'Every line must hold: a>b, gcd(a,b)=1 (no common factor), mod(a,b)=0 (divides exactly), a!=b.',
					'ohmylms'
				) }
				value={ template.constraints }
				format={ ( list ) => ( list || [] ).join( '\n' ) }
				parse={ parseLines }
				onChange={ ( constraints ) =>
					patch( {
						constraints,
					} )
				}
			/>
			<h4>{ __( 'Computed answers', 'ohmylms' ) }</h4>
			<p>
				{ __(
					'Fields that take a number, like the correct answer or a target, are set by a formula here. The value typed in the field itself is replaced.',
					'ohmylms'
				) }
			</p>
			{ rules.map( ( rule, index ) => (
				<div key={ index } className="ohmylms-interactive-row">
					<TextControl
						label={ __( 'Setting', 'ohmylms' ) }
						list={ `ohmylms-set-paths-${ settings.type }` }
						value={ rule.path || '' }
						onChange={ ( path ) =>
							setRule( index, {
								path,
							} )
						}
					/>
					<FormulaControl
						label={ __( 'Formula', 'ohmylms' ) }
						value={ rule.expr || '' }
						onChange={ ( expr ) =>
							setRule( index, {
								expr,
							} )
						}
					/>
					<Button
						isDestructive
						variant="tertiary"
						onClick={ () =>
							patch( {
								set: rules.filter( ( _, i ) => i !== index ),
							} )
						}
					>
						{ __( 'Remove', 'ohmylms' ) }
					</Button>
				</div>
			) ) }
			<datalist id={ `ohmylms-set-paths-${ settings.type }` }>
				{ suggestions.map( ( path ) => (
					<option key={ path } value={ path } />
				) ) }
			</datalist>
			<Button
				variant="secondary"
				disabled={ readOnly }
				onClick={ () =>
					patch( {
						set: [
							...rules,
							{
								path: suggestions[ 0 ] || '',
								expr: '',
							},
						],
					} )
				}
			>
				{ __( 'Add a computed answer', 'ohmylms' ) }
			</Button>
			{ issues.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Check the letters (single letters a–z except e, each used once), the ranges, and that every formula is filled in.',
						'ohmylms'
					) }
				</Notice>
			) }
			<TextControl
				label={ __( 'Number of examples', 'ohmylms' ) }
				type="number"
				min={ 1 }
				max={ 8 }
				value={ sampleCount }
				onChange={ ( count ) =>
					setSampleCount(
						Math.max( 1, Math.min( 8, Number( count ) || 1 ) )
					)
				}
			/>
			<p>
				<Button
					variant="primary"
					isBusy={ busy }
					disabled={ busy }
					onClick={ showExamples }
				>
					{ __( 'Show examples', 'ohmylms' ) }
				</Button>{ ' ' }
				<Button
					variant="tertiary"
					isDestructive
					disabled={ readOnly }
					onClick={ () => {
						setPreview( null );
						save( null );
					} }
				>
					{ __( 'Stop randomizing', 'ohmylms' ) }
				</Button>
			</p>
			{ preview && (
				<div className="ohmylms-template-preview" aria-live="polite">
					<Notice
						status={ preview.valid ? 'success' : 'error' }
						isDismissible={ false }
					>
						{ preview.valid
							? __(
									'This template works: every example below is a valid question.',
									'ohmylms'
								)
							: preview.message }
					</Notice>
					<ol>
						{ ( preview.samples || [] ).map( ( sample ) => (
							<li key={ sample.seed }>
								<strong>{ exampleText( sample ) }</strong>
								{ sample.expected !== '' && (
									<span>
										{ ' — ' }
										{ sprintf(
											/* translators: %s: computed expected answer. */ __(
												'answer: %s',
												'ohmylms'
											),
											sample.expected
										) }
									</span>
								) }
								{ sample.errors?.length > 0 && (
									<em>
										{ ' ' }
										{ sprintf(
											/* translators: %s: math evaluation error message. */ __(
												'(cannot work out: %s)',
												'ohmylms'
											),
											sample.errors.join( ', ' )
										) }
									</em>
								) }
							</li>
						) ) }
					</ol>
				</div>
			) }
		</details>
	);
}
