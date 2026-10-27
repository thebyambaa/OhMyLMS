import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Select a presentation design without changing assessment pagination.
 * @param root0
 * @param root0.value
 * @param root0.onChange
 */
export function PlayerTemplatePicker( { value = 'classic', onChange } ) {
	const choices = window.ohmylmsPlayerTemplates || [];
	return createElement(
		'fieldset',
		{ className: 'ohmylms-player-template-picker' },
		createElement(
			'legend',
			null,
			__( 'Quiz player template', 'ohmylms' )
		),
		createElement(
			'p',
			null,
			__(
				'Choose the look learners see. Question layout and grading stay the same.',
				'ohmylms'
			)
		),
		createElement(
			'div',
			{ className: 'ohmylms-player-template-choices' },
			choices.map( ( choice ) =>
				createElement(
					'label',
					{
						key: choice.value,
						className: `ohmylms-player-template-choice is-${ choice.value }`,
					},
					createElement( 'input', {
						type: 'radio',
						name: 'ohmylms-player-template',
						value: choice.value,
						checked: value === choice.value,
						onChange: () => onChange( choice.value ),
					} ),
					createElement(
						'span',
						{
							className: 'ohmylms-player-template-swatch',
							'aria-hidden': true,
						},
						createElement( 'i' ),
						createElement( 'i' ),
						createElement( 'i' ),
						createElement( 'i' )
					),
					createElement( 'strong', null, choice.label ),
					createElement( 'small', null, choice.description )
				)
			)
		)
	);
}
