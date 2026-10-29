import {
	cloneElement,
	createElement,
	isValidElement,
	memo,
} from '@wordpress/element';
import {
	FlexWP,
	HeadingWP,
	SpacerWP,
	TextWP,
} from '../controls/layout/index.js';
import { AddButton } from './AddButton.mjs';

const noop = () => {};

/**
 * Centered empty state: optional picture, title, description and call-to-action button.
 *
 * @param {Object}                    props
 * @param {import('react').ReactNode} [props.icon=null]      Picture; an element is given a fixed key.
 * @param {string}                    [props.title='']       Heading.
 * @param {string}                    [props.description=''] Muted text.
 * @param {string}                    [props.ctaText='']     Button label; no button when empty.
 * @param {() => void}                [props.ctaHandler]     Button click handler.
 * @return {import('react').ReactElement} Empty state.
 */
function EmptyStateBase( {
	icon = null,
	title = '',
	description = '',
	ctaText = '',
	ctaHandler = noop,
} ) {
	return createElement(
		SpacerWP,
		{ marginBottom: 0, paddingY: 5, paddingX: 3 },
		createElement(
			FlexWP,
			{
				direction: 'column',
				align: 'center',
				justify: 'center',
				gap: '3',
				style: { minHeight: '230px' },
			},
			icon && isValidElement( icon )
				? cloneElement( icon, { key: 'no-data-icon' } )
				: icon,
			title && createElement( HeadingWP, { level: '4' }, title ),
			description &&
				createElement(
					TextWP,
					{ variant: 'muted', align: 'center' },
					description
				),
			ctaText &&
				createElement( AddButton, {
					label: ctaText,
					onClick: ctaHandler,
				} )
		)
	);
}

export const EmptyState = memo( EmptyStateBase );
