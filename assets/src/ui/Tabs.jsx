import { createElement, useId, useState } from '@wordpress/element';
import { tabIndexForKey } from './navigation.mjs';

/**
 * Accessible local panels with automatic activation and roving keyboard focus.
 *
 * @param {Object}               props              Component properties.
 * @param {Array}                props.items        Items with id, label, content and optional disabled.
 * @param {string}               props.label        Accessible tab-list label.
 * @param {string}               props.value        Controlled selected ID.
 * @param {string}               props.defaultValue Initial uncontrolled ID.
 * @param {(id: string) => void} props.onChange     Selection callback.
 * @param {string}               props.orientation  Horizontal (default) or vertical.
 * @param {string}               props.className    Additional wrapper classes.
 */
export function Tabs( {
	items = [],
	label,
	value,
	defaultValue,
	onChange,
	orientation = 'horizontal',
	className = '',
} ) {
	const id = useId();
	const [ selected, setSelected ] = useState( defaultValue );
	const enabled = items.filter( ( item ) => ! item.disabled );
	const active = enabled.some( ( item ) => item.id === ( value ?? selected ) )
		? ( value ?? selected )
		: enabled[ 0 ]?.id;
	const select = ( item ) => {
		if ( value === undefined ) {
			setSelected( item.id );
		}
		onChange?.( item.id );
	};
	const vertical = orientation === 'vertical';
	return createElement(
		'div',
		{ className: `oml-tabs ${ className }` },
		createElement(
			'div',
			{
				className: 'oml-tab-list',
				role: 'tablist',
				'aria-label': label,
				'aria-orientation': vertical ? 'vertical' : 'horizontal',
			},
			...items.map( ( item, index ) =>
				createElement(
					'button',
					{
						key: item.id,
						type: 'button',
						role: 'tab',
						className: 'oml-tab',
						id: `${ id }-tab-${ index }`,
						'aria-controls': `${ id }-panel-${ index }`,
						'aria-selected': active === item.id,
						disabled: item.disabled,
						tabIndex: active === item.id ? 0 : -1,
						onClick: () => select( item ),
						onKeyDown: ( event ) => {
							const next = tabIndexForKey(
								enabled.findIndex(
									( entry ) => entry.id === item.id
								),
								enabled.length,
								event.key,
								vertical,
								event.currentTarget.closest( '[dir="rtl"]' ) !==
									null ||
									window.getComputedStyle(
										event.currentTarget
									).direction === 'rtl'
							);
							if ( next === null ) {
								return;
							}
							event.preventDefault();
							select( enabled[ next ] );
							const target = items.indexOf( enabled[ next ] );
							event.currentTarget.parentElement
								.querySelectorAll( '[role="tab"]' )
								[ target ].focus();
						},
					},
					item.label
				)
			)
		),
		...items.map( ( item, index ) =>
			createElement(
				'div',
				{
					key: item.id,
					className: 'oml-tab-panel',
					role: 'tabpanel',
					id: `${ id }-panel-${ index }`,
					'aria-labelledby': `${ id }-tab-${ index }`,
					hidden: active !== item.id,
					tabIndex: 0,
				},
				item.content
			)
		)
	);
}
