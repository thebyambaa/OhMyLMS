import { createElement } from '@wordpress/element';
import { ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { choiceModePatch } from './choiceModel.mjs';

/**
 * Selection and partial-credit controls for the unified choice editor.
 *
 * @param {Object}               props          Component properties.
 * @param {Object}               props.question Current question.
 * @param {(patch:Object)=>void} props.onChange Update the question.
 * @param {boolean}              props.readOnly Whether editing is disabled.
 */
export function ChoiceControls( { question, onChange, readOnly } ) {
	const multiple = question.settings?.type === 'multiple-choice';
	return createElement(
		'div',
		{ className: 'ohmylms-choice-controls' },
		createElement( ToggleControl, {
			label: __( 'Multiple correct answers', 'ohmylms' ),
			checked: multiple,
			disabled: readOnly,
			onChange: ( enabled ) =>
				onChange( choiceModePatch( question, enabled ) ),
		} ),
		multiple &&
			createElement( ToggleControl, {
				label: __( 'Allow partial grading', 'ohmylms' ),
				checked: Boolean( question.settings?.partial_credit ),
				disabled: readOnly,
				help: __(
					'Correct selections earn credit; incorrect selections subtract credit. Scores never fall below zero.',
					'ohmylms'
				),
				onChange: ( partialCredit ) =>
					onChange( {
						settings: {
							...question.settings,
							partial_credit: partialCredit,
						},
					} ),
			} )
	);
}
