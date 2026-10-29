// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- the recovered application renders this experimental component; markup parity is tested.
import { __experimentalHeading as Heading } from '@wordpress/components';
import { cloneElement, createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { omitProps } from './model.mjs';

const OWN_PROPS = [
	'icon',
	'title',
	'description',
	'actionComponent',
	'className',
	'titleLevel',
];
const ICON_SIZE = 48;

/**
 * Empty state with an optional icon, heading, description and action.
 *
 * @param {Object}                       props                   Component props.
 * @param {import('react').ReactElement} [props.icon]            Icon element, shown at 48px.
 * @param {string}                       [props.title]           Heading. Defaults to "Nothing here yet".
 * @param {import('react').ReactNode}    [props.description]     Description text.
 * @param {import('react').ReactNode}    [props.actionComponent] Action shown below.
 * @param {string}                       [props.className]       Extra class name. Defaults to "".
 * @param {string}                       [props.titleLevel]      Heading level. Defaults to "4".
 * @return {import('react').ReactElement} Empty state region.
 */
export function EmptyWP( props ) {
	const {
		icon,
		title = __( 'Nothing here yet', 'ohmylms' ),
		description,
		actionComponent,
		className = '',
		titleLevel = '4',
	} = props;

	return createElement(
		'div',
		{
			className: `ohmylms-empty-state ${ className }`,
			role: 'region',
			'aria-label': title || __( 'Empty state section', 'ohmylms' ),
			...omitProps( props, OWN_PROPS ),
		},
		icon &&
			createElement(
				'div',
				{ className: 'ohmylms-empty-icon' },
				cloneElement( icon, {
					width: ICON_SIZE,
					height: ICON_SIZE,
				} )
			),
		title &&
			createElement(
				Heading,
				{ className: 'ohmylms-empty-title', level: titleLevel },
				title
			),
		description &&
			createElement(
				'p',
				{ className: 'ohmylms-empty-description' },
				description
			),
		actionComponent &&
			createElement(
				'div',
				{ className: 'ohmylms-empty-action' },
				actionComponent
			)
	);
}
