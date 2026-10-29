import { createElement } from '@wordpress/element';
import { progressClass, clampPercent } from './components.mjs';

/**
 * Shared progress bar. Always pass a label so assistive technology can name it.
 *
 * @param {Object} props
 * @param {number} props.value     0 to 100.
 * @param {string} props.label     Accessible name.
 * @param {string} props.tone      primary (default) or success.
 * @param          props.className
 */
export function Progress( { value, label, tone, className, ...rest } ) {
	const percent = clampPercent( value );
	return createElement(
		'div',
		{
			...rest,
			className: progressClass( { tone, className } ),
			role: 'progressbar',
			'aria-label': label,
			'aria-valuemin': 0,
			'aria-valuemax': 100,
			'aria-valuenow': percent,
		},
		createElement( 'span', {
			className: 'oml-progress-bar',
			style: { width: percent + '%' },
		} )
	);
}
