import { createElement, Fragment, useEffect, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import {
	PLACE_VALUES,
	addCategory,
	parseAmounts,
	removeCategory,
} from './interactiveModel.mjs';
import { numberOrFormula } from './formulaInput.mjs';

/**
 * Min, max, step and the correct value on a scale: number line and jug.
 * @param root0
 * @param root0.value
 * @param root0.onChange
 * @param root0.jug
 */
function ScaleEditor( { value, onChange, jug } ) {
	return (
		<div className="ohmylms-interactive-editor">
			<div className="ohmylms-interactive-row">
				<TextControl
					label={ __( 'Minimum', 'ohmylms' ) }
					{ ...numberOrFormula( value.min, ( min ) =>
						onChange( {
							min,
						} )
					) }
				/>
				<TextControl
					label={ __( 'Maximum', 'ohmylms' ) }
					{ ...numberOrFormula( value.max, ( max ) =>
						onChange( {
							max,
						} )
					) }
				/>
				<TextControl
					label={
						jug
							? __( 'Minor tick', 'ohmylms' )
							: __( 'Tick step', 'ohmylms' )
					}
					{ ...numberOrFormula(
						value.step,
						( step ) =>
							onChange( {
								step,
							} ),
						{
							min: 0,
						}
					) }
				/>
			</div>
			<div className="ohmylms-interactive-row">
				<TextControl
					label={ __( 'Correct value', 'ohmylms' ) }
					help={ __(
						'Randomized question? Use a formula such as {{a+b}}.',
						'ohmylms'
					) }
					{ ...numberOrFormula( value.target, ( target ) =>
						onChange( {
							target,
						} )
					) }
				/>
				<TextControl
					label={ __( 'Accepted distance', 'ohmylms' ) }
					help={
						jug
							? __( 'Empty means one minor tick.', 'ohmylms' )
							: __( 'Empty means half a tick.', 'ohmylms' )
					}
					{ ...numberOrFormula(
						value.tolerance,
						( tolerance ) =>
							onChange( {
								tolerance,
							} ),
						{
							min: 0,
						}
					) }
				/>
				{ ! jug && (
					<TextControl
						label={ __( 'Snap to', 'ohmylms' ) }
						help={ __( 'Empty means the tick step.', 'ohmylms' ) }
						{ ...numberOrFormula(
							value.snap,
							( snap ) =>
								onChange( {
									snap,
								} ),
							{
								min: 0,
							}
						) }
					/>
				) }
				{ jug && (
					<TextControl
						label={ __( 'Unit', 'ohmylms' ) }
						value={ value.unit || '' }
						onChange={ ( unit ) =>
							onChange( {
								unit,
							} )
						}
					/>
				) }
			</div>
		</div>
	);
}
export const NumberLineEditor = ( props ) => <ScaleEditor { ...props } />;
export const FillLevelEditor = ( props ) => <ScaleEditor { ...props } jug />;

/**
 * Equal parts of a bar, grid or circle and how many must be shaded.
 * @param root0
 * @param root0.value
 * @param root0.onChange
 */
export function ShadeModelEditor( { value, onChange } ) {
	return (
		<div className="ohmylms-interactive-editor ohmylms-interactive-row">
			<SelectControl
				label={ __( 'Shape', 'ohmylms' ) }
				value={ value.shape || 'bar' }
				options={ [
					{
						value: 'bar',
						label: __( 'Bar', 'ohmylms' ),
					},
					{
						value: 'grid',
						label: __( 'Grid', 'ohmylms' ),
					},
					{
						value: 'circle',
						label: __( 'Circle', 'ohmylms' ),
					},
				] }
				onChange={ ( shape ) =>
					onChange( {
						shape,
					} )
				}
			/>
			<TextControl
				label={ __( 'Equal parts', 'ohmylms' ) }
				{ ...numberOrFormula(
					value.parts,
					( parts ) =>
						onChange( {
							parts,
						} ),
					{
						min: 2,
						max: 100,
					}
				) }
			/>
			{ value.shape === 'grid' && (
				<TextControl
					label={ __( 'Columns', 'ohmylms' ) }
					{ ...numberOrFormula(
						value.cols,
						( cols ) =>
							onChange( {
								cols,
							} ),
						{
							min: 1,
						}
					) }
				/>
			) }
			<TextControl
				label={ __( 'Parts to shade', 'ohmylms' ) }
				help={ __(
					'Any parts count; the learner may shade any of them.',
					'ohmylms'
				) }
				{ ...numberOrFormula(
					value.answer,
					( answer ) =>
						onChange( {
							answer,
						} ),
					{
						min: 0,
					}
				) }
			/>
		</div>
	);
}

/**
 * Number to build and the place values on offer.
 * @param root0
 * @param root0.value
 * @param root0.onChange
 */
export function CountBlocksEditor( { value, onChange } ) {
	const places = value.places || [];
	return (
		<div className="ohmylms-interactive-editor">
			<fieldset className="ohmylms-interactive-row">
				<legend>{ __( 'Blocks on offer', 'ohmylms' ) }</legend>
				{ Object.keys( PLACE_VALUES ).map( ( place ) => (
					<CheckboxControl
						key={ place }
						label={ place }
						checked={ places.includes( place ) }
						onChange={ ( on ) =>
							onChange( {
								places: Object.keys( PLACE_VALUES ).filter(
									( p ) =>
										p === place ? on : places.includes( p )
								),
							} )
						}
					/>
				) ) }
			</fieldset>
			<div className="ohmylms-interactive-row">
				<TextControl
					label={ __( 'Number to build', 'ohmylms' ) }
					{ ...numberOrFormula(
						value.target,
						( target ) =>
							onChange( {
								target,
							} ),
						{
							min: 1,
							step: 1,
						}
					) }
				/>
				<TextControl
					label={ __( 'Most blocks of one kind', 'ohmylms' ) }
					{ ...numberOrFormula(
						value.max_per_place,
						( _maxPerPlace ) =>
							onChange( {
								max_per_place: _maxPerPlace,
							} ),
						{
							min: 1,
							max: 50,
							step: 1,
						}
					) }
				/>
			</div>
			<CheckboxControl
				label={ __(
					'Require standard form (at most 9 of any kind, so 243 cannot be 1 hundred and 14 tens)',
					'ohmylms'
				) }
				checked={ !! value.canonical }
				onChange={ ( canonical ) =>
					onChange( {
						canonical,
					} )
				}
			/>
		</div>
	);
}

/**
 * Target time and how exact the learner must be.
 * @param root0
 * @param root0.value
 * @param root0.onChange
 */
export function SetClockEditor( { value, onChange } ) {
	return (
		<div className="ohmylms-interactive-editor">
			<div className="ohmylms-interactive-row">
				<TextControl
					label={ __( 'Hour (1–12)', 'ohmylms' ) }
					{ ...numberOrFormula(
						value.hour,
						( hour ) =>
							onChange( {
								hour,
							} ),
						{
							min: 1,
							max: 12,
							step: 1,
						}
					) }
				/>
				<TextControl
					label={ __( 'Minutes (0–59)', 'ohmylms' ) }
					{ ...numberOrFormula(
						value.minute,
						( minute ) =>
							onChange( {
								minute,
							} ),
						{
							min: 0,
							max: 59,
							step: 1,
						}
					) }
				/>
				<TextControl
					label={ __( 'Accepted error (minutes)', 'ohmylms' ) }
					{ ...numberOrFormula(
						value.tolerance,
						( tolerance ) =>
							onChange( {
								tolerance,
							} ),
						{
							min: 0,
							step: 1,
						}
					) }
				/>
				<SelectControl
					label={ __( 'Hand moves in steps of', 'ohmylms' ) }
					value={ String( value.snap || 1 ) }
					options={ [
						{
							value: '1',
							label: __( '1 minute', 'ohmylms' ),
						},
						{
							value: '5',
							label: __( '5 minutes', 'ohmylms' ),
						},
						{
							value: '15',
							label: __( '15 minutes', 'ohmylms' ),
						},
					] }
					onChange={ ( snap ) =>
						onChange( {
							snap: Number( snap ),
						} )
					}
				/>
			</div>
			<CheckboxControl
				label={ __(
					'Show the digital time while the learner sets the hands',
					'ohmylms'
				) }
				checked={ !! value.show_digital }
				onChange={ ( _showDigital ) =>
					onChange( {
						show_digital: _showDigital,
					} )
				}
			/>
		</div>
	);
}

/**
 * Bills and coins on offer and the amount to make.
 * @param root0
 * @param root0.value
 * @param root0.onChange
 */
export function MakeAmountEditor( { value, onChange } ) {
	return (
		<div className="ohmylms-interactive-editor ohmylms-interactive-row">
			<TextControl
				label={ __(
					'Bills and coins (separated by commas)',
					'ohmylms'
				) }
				value={ ( value.denominations || [] ).join( ', ' ) }
				onChange={ ( text ) =>
					onChange( {
						denominations: parseAmounts( text ),
					} )
				}
			/>
			<TextControl
				label={ __( 'Currency symbol', 'ohmylms' ) }
				value={ value.symbol || '' }
				onChange={ ( symbol ) =>
					onChange( {
						symbol,
					} )
				}
			/>
			<TextControl
				label={ __( 'Amount to make', 'ohmylms' ) }
				{ ...numberOrFormula(
					value.target,
					( target ) =>
						onChange( {
							target,
						} ),
					{
						min: 1,
						step: 1,
					}
				) }
			/>
		</div>
	);
}

/**
 * Bars, their labels and the heights the learner has to draw.
 * @param root0
 * @param root0.value
 * @param root0.onChange
 */
export function BuildChartEditor( { value, onChange } ) {
	const categories = value.categories || [];
	const setCategory = ( id, patch ) =>
		onChange( {
			categories: categories.map( ( category ) =>
				category.id === id
					? {
							...category,
							...patch,
						}
					: category
			),
		} );
	return (
		<div className="ohmylms-interactive-editor">
			<div className="ohmylms-interactive-row">
				<TextControl
					label={ __( 'Chart maximum', 'ohmylms' ) }
					{ ...numberOrFormula(
						value.max,
						( max ) =>
							onChange( {
								max,
							} ),
						{
							min: 1,
						}
					) }
				/>
				<TextControl
					label={ __( 'Step', 'ohmylms' ) }
					{ ...numberOrFormula(
						value.step,
						( step ) =>
							onChange( {
								step,
							} ),
						{
							min: 0,
						}
					) }
				/>
				<TextControl
					label={ __( 'Unit (optional)', 'ohmylms' ) }
					value={ value.unit || '' }
					onChange={ ( unit ) =>
						onChange( {
							unit,
						} )
					}
				/>
			</div>
			{ categories.map( ( category, index ) => (
				<div key={ category.id } className="ohmylms-interactive-row">
					<TextControl
						label={ sprintf(
							/* translators: %d: bar number. */ __(
								'Bar %d label',
								'ohmylms'
							),
							index + 1
						) }
						value={ category.label || '' }
						onChange={ ( label ) =>
							setCategory( category.id, {
								label,
							} )
						}
					/>
					<TextControl
						label={ __( 'Correct height', 'ohmylms' ) }
						{ ...numberOrFormula(
							value.values?.[ category.id ],
							( height ) =>
								onChange( {
									values: {
										...value.values,
										[ category.id ]: height,
									},
								} )
						) }
					/>
					<Button
						isDestructive
						variant="tertiary"
						disabled={ categories.length <= 1 }
						onClick={ () =>
							onChange( removeCategory( value, category.id ) )
						}
					>
						{ __( 'Remove bar', 'ohmylms' ) }
					</Button>
				</div>
			) ) }
			<Button
				variant="secondary"
				onClick={ () => onChange( addCategory( value ) ) }
			>
				{ __( 'Add bar', 'ohmylms' ) }
			</Button>
			<CheckboxControl
				label={ __(
					'Show the data as a table above the chart',
					'ohmylms'
				) }
				help={ __(
					'Turn this off when the question text gives the data, so the table does not reveal the answer.',
					'ohmylms'
				) }
				checked={ !! value.show_table }
				onChange={ ( _showTable ) =>
					onChange( {
						show_table: _showTable,
					} )
				}
			/>
		</div>
	);
}

/**
 * Grid size and the conditions the chosen squares must meet.
 * @param root0
 * @param root0.value
 * @param root0.onChange
 */
export function GridBuildEditor( { value, onChange } ) {
	const constraints = value.constraints || {};
	const set = ( patch ) =>
		onChange( {
			constraints: Object.fromEntries(
				Object.entries( {
					...constraints,
					...patch,
				} ).filter( ( [ , v ] ) => v !== undefined && v !== false )
			),
		} );
	return (
		<div className="ohmylms-interactive-editor">
			<div className="ohmylms-interactive-row">
				<TextControl
					label={ __( 'Rows', 'ohmylms' ) }
					{ ...numberOrFormula(
						value.rows,
						( rows ) =>
							onChange( {
								rows,
							} ),
						{
							min: 1,
							max: 20,
							step: 1,
						}
					) }
				/>
				<TextControl
					label={ __( 'Columns', 'ohmylms' ) }
					{ ...numberOrFormula(
						value.cols,
						( cols ) =>
							onChange( {
								cols,
							} ),
						{
							min: 1,
							max: 20,
							step: 1,
						}
					) }
				/>
			</div>
			<p>
				{ __(
					'The learner must choose squares that meet all of these:',
					'ohmylms'
				) }
			</p>
			<div className="ohmylms-interactive-row">
				<TextControl
					label={ __( 'Area (squares)', 'ohmylms' ) }
					{ ...numberOrFormula(
						constraints.area,
						( area ) =>
							set( {
								area,
							} ),
						{
							min: 1,
							step: 1,
						}
					) }
				/>
				<TextControl
					label={ __( 'Perimeter', 'ohmylms' ) }
					{ ...numberOrFormula(
						constraints.perimeter,
						( perimeter ) =>
							set( {
								perimeter,
							} ),
						{
							min: 1,
							step: 1,
						}
					) }
				/>
			</div>
			<CheckboxControl
				label={ __( 'Must be a rectangle', 'ohmylms' ) }
				checked={ !! constraints.rectangle }
				onChange={ ( rectangle ) =>
					set( {
						rectangle,
					} )
				}
			/>
			<CheckboxControl
				label={ __( 'Squares must be connected', 'ohmylms' ) }
				checked={ !! constraints.connected }
				onChange={ ( connected ) =>
					set( {
						connected,
					} )
				}
			/>
			<CheckboxControl
				label={ __(
					'Show the learner their current area and perimeter',
					'ohmylms'
				) }
				checked={ !! value.show_measures }
				onChange={ ( _showMeasures ) =>
					onChange( {
						show_measures: _showMeasures,
					} )
				}
			/>
		</div>
	);
}

/**
 * The learner's widget built from the unsaved settings, using the same script learners get.
 * Falls back to a note when the player scripts are not on the page.
 * @param root0
 * @param root0.type
 * @param root0.settings
 */
export function VisualPreview( { type, settings = {} } ) {
	const holder = useRef( null );
	const signature = JSON.stringify( [ type, settings ] );
	useEffect( () => {
		const player = window.OhMyLMSInteractive;
		const node = holder.current;
		if ( ! node || ! player ) {
			return undefined;
		}
		node.textContent = '';
		const config = {
			...settings,
		};
		// A {{formula}} has no value until a learner gets a question, so the preview skips it.
		[ 'min', 'max', 'step', 'snap', 'tolerance', 'target' ].forEach(
			( name ) => {
				if ( typeof config[ name ] === 'string' ) {
					delete config[ name ];
				}
			}
		);
		if ( type === 'build-chart' && settings.show_table ) {
			config.table = ( settings.categories || [] ).map(
				( category ) => ( {
					label: category.label,
					// A {{variable}} has no value yet, so the preview shows a bar of zero.
					value:
						typeof settings.values?.[ category.id ] === 'number'
							? settings.values[ category.id ]
							: 0,
				} )
			);
		}
		try {
			player.render( node, type, config );
		} catch {
			node.textContent = '';
		}
		return () => {
			node.textContent = '';
		};
	}, [ signature ] ); // eslint-disable-line react-hooks/exhaustive-deps
	return (
		<Fragment>
			<div ref={ holder } className="ohmylms-visual-preview" />
			{ ! window.OhMyLMSInteractive && (
				<p>
					{ __(
						'The interactive preview is not available on this screen.',
						'ohmylms'
					) }
				</p>
			) }
		</Fragment>
	);
}
