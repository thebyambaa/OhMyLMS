import { createElement, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, SelectControl, TextControl } from '@wordpress/components';
import { mathsPreset } from './mathsGenerator.mjs';

/**
 * Topic and range wizard that writes ordinary numerical/template settings.
 *
 * @param {Object}                   props            Component properties.
 * @param {Object}                   props.value      Existing settings.
 * @param {(preset: Object) => void} props.onGenerate Apply the authored definition.
 * @return {Element} Maths generator controls.
 */
export function MathsGenerator( { value, onGenerate } ) {
	const saved = value.maths_generator || {};
	const [ kind, setKind ] = useState( saved.kind || 'addition' );
	const [ min, setMin ] = useState( saved.min || 1 );
	const [ max, setMax ] = useState( saved.max || 12 );
	const preset = mathsPreset( kind, Number( min ), Number( max ) );
	return (
		<details className="ohmylms-maths-generator">
			<summary>{ __( 'Maths generator', 'ohmylms' ) }</summary>
			<p>
				{ __(
					'Choose a topic and number range. Apply to create a numerical question with fresh numbers for each issued attempt. Variables and the calculated answer stay private in the learner player.',
					'ohmylms'
				) }
			</p>
			<SelectControl
				label={ __( 'Maths topic', 'ohmylms' ) }
				value={ kind }
				onChange={ setKind }
				options={ [
					{ value: 'addition', label: __( 'Addition', 'ohmylms' ) },
					{
						value: 'subtraction',
						label: __( 'Subtraction', 'ohmylms' ),
					},
					{
						value: 'multiplication',
						label: __( 'Multiplication', 'ohmylms' ),
					},
					{
						value: 'division',
						label: __( 'Exact division', 'ohmylms' ),
					},
					{
						value: 'percentage',
						label: __( 'Percentages', 'ohmylms' ),
					},
					{
						value: 'fractions',
						label: __( 'Fractions (decimal answer)', 'ohmylms' ),
					},
					{
						value: 'algebra',
						label: __( 'Linear equations', 'ohmylms' ),
					},
					{
						value: 'pythagorean',
						label: __( 'Pythagorean triples', 'ohmylms' ),
					},
					{
						value: 'quadratic',
						label: __(
							'Quadratic, two distinct solutions',
							'ohmylms'
						),
					},
					{
						value: 'quadratic-repeated',
						label: __(
							'Quadratic, one repeated solution',
							'ohmylms'
						),
					},
					{
						value: 'discriminant',
						label: __( 'Quadratic discriminant', 'ohmylms' ),
					},
					{
						value: 'pythagorean-leg',
						label: __(
							'Pythagoras, find a shorter side',
							'ohmylms'
						),
					},
					{
						value: 'simultaneous',
						label: __( 'Simultaneous equations', 'ohmylms' ),
					},
					{
						value: 'equivalent-fraction',
						label: __(
							'Fractions: equivalent fraction',
							'ohmylms'
						),
					},
					{
						value: 'simplify-fraction',
						label: __( 'Fractions: simplify', 'ohmylms' ),
					},
					{
						value: 'unlike-fractions',
						label: __(
							'Fractions: add with different denominators',
							'ohmylms'
						),
					},
					{
						value: 'percent-increase',
						label: __( 'Percentages: increase', 'ohmylms' ),
					},
					{
						value: 'percent-decrease',
						label: __( 'Percentages: decrease', 'ohmylms' ),
					},
					{
						value: 'reverse-percentage',
						label: __(
							'Percentages: find the original',
							'ohmylms'
						),
					},
					{
						value: 'simple-interest',
						label: __( 'Percentages: simple interest', 'ohmylms' ),
					},
					{
						value: 'gradient',
						label: __(
							'Graphs: gradient from two points',
							'ohmylms'
						),
					},
					{
						value: 'y-intercept',
						label: __( 'Graphs: y-intercept', 'ohmylms' ),
					},
					{
						value: 'midpoint',
						label: __( 'Graphs: midpoint', 'ohmylms' ),
					},
					{
						value: 'share-ratio',
						label: __( 'Ratio: share an amount', 'ohmylms' ),
					},
					{
						value: 'speed',
						label: __( 'Speed from distance and time', 'ohmylms' ),
					},
				] }
			/>
			<TextControl
				label={ __( 'Minimum generated number', 'ohmylms' ) }
				type="number"
				min={ 1 }
				max={ 1000 }
				value={ min }
				onChange={ setMin }
			/>
			<TextControl
				label={ __( 'Maximum generated number', 'ohmylms' ) }
				type="number"
				min={ 1 }
				max={ 1000 }
				value={ max }
				onChange={ setMax }
			/>
			{ preset ? (
				<p>{ preset.prompt }</p>
			) : (
				<p role="alert">
					{ __(
						'Use integers from 1 to 1000, with minimum no greater than maximum.',
						'ohmylms'
					) }
				</p>
			) }
			<details className="ohmylms-maths-generator-notes">
				<summary>{ __( 'How each topic works', 'ohmylms' ) }</summary>
				<p>
					{ __(
						'Division generates an exact product divided by a nonzero factor. Subtraction excludes negative results. Fractions use a shared denominator from 2 to 12. Linear equations use nonzero coefficients from 1 to 9. Pythagorean triples are whole-number right triangles built from two coprime numbers in the range (needs at least two numbers). Quadratics are built from two different whole-number solutions between minus the maximum and the maximum, each at least the minimum away from zero, so the equation always has two distinct whole solutions. A repeated solution is one whole number p with x² - 2px + p². The discriminant uses a from the range and b, c from minus the maximum to the maximum, never zero. Finding a shorter side uses the same triples. Simultaneous equations pick whole x and y in the range and coefficients from 1 to 9 that make the pair solvable. Fraction topics need two to three numbers in the range; percentage topics use prices of 20 times the range and steps of 5%; graph topics use gradients from -9 to 9; ratio and speed topics build the total or distance from the answer. Apply replaces the current numerical answer and variable definition.',
						'ohmylms'
					) }
				</p>
			</details>
			<Button
				variant="secondary"
				disabled={ ! preset }
				onClick={ () => onGenerate( preset ) }
			>
				{ __( 'Apply generated question', 'ohmylms' ) }
			</Button>
		</details>
	);
}
