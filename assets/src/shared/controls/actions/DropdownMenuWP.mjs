import { DropdownMenu, MenuItem } from '@wordpress/components';
import { createElement, Fragment, memo } from '@wordpress/element';
import {
	getMenuItemKey,
	getMenuItemProps,
	omitProps,
	selectMenuItem,
} from './model.mjs';

const stopPropagation = ( event ) => event.stopPropagation();

/**
 * Dropdown menu opened from an icon button. Clicks do not bubble to parents
 * (for example a clickable table row).
 *
 * @param {Object}                              props          Component props.
 * @param {import('react').ReactElement|string} props.icon     Toggle icon.
 * @param {Object[]}                            props.controls Entries: `title`, `icon`, `value`, `disabled`, `onClick`. A disabled entry is dimmed and inert; other entries run `onClick` and close the menu.
 * @return {import('react').ReactElement} Dropdown menu.
 */
function DropdownMenuWPBase( props ) {
	const { icon, controls } = props;

	return createElement(
		'div',
		{ onClick: stopPropagation },
		createElement(
			DropdownMenu,
			{
				toggleProps: { onClick: stopPropagation },
				icon,
				...omitProps( props, [ 'icon', 'controls' ] ),
			},
			( { onClose } ) =>
				createElement(
					Fragment,
					null,
					controls.map( ( item, index ) =>
						createElement(
							MenuItem,
							{
								key: getMenuItemKey( item, index ),
								onClick: () => selectMenuItem( item, onClose ),
								...getMenuItemProps( item ),
							},
							item?.title
						)
					)
				)
		)
	);
}

export const DropdownMenuWP = memo( DropdownMenuWPBase );
