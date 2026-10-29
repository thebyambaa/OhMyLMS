/**
 * Pure helpers behind the action, overlay and feedback controls. They hold the
 * logic that does not need React so it can be unit tested on its own.
 */

/** Padding given to `primary` and `secondary` buttons that are not `small`. */
export const BUTTON_DEFAULT_PADDING = '6px 24px';

/** Inline styles added to buttons with the `outline` variant. */
export const BUTTON_OUTLINE_STYLE = {
	border: '1px solid #C8D2E980',
	backgroundColor: 'transparent',
	color: '#000D25',
};

/**
 * Whether the dropdown menu treats `isPro` items as unlocked. Pro items stay
 * unlocked, so `aria-disabled` never turns true because of `isPro`.
 */
export const PRO_ITEMS_UNLOCKED = true;

/** Class name of the tabs container. */
export const TABS_CLASS = 'ohmylms-tabs-container';

/**
 * Copy an object without the given keys. Symbol keys are kept.
 *
 * @param {Object}   props Source props.
 * @param {string[]} keys  Keys to leave out.
 * @return {Object} Remaining props.
 */
export function omitProps( props, keys ) {
	const rest = {};
	Reflect.ownKeys( props ).forEach( ( key ) => {
		if (
			! keys.includes( key ) &&
			Object.prototype.propertyIsEnumerable.call( props, key )
		) {
			rest[ key ] = props[ key ];
		}
	} );
	return rest;
}

/**
 * Join class names like the `classnames` package: strings and numbers are kept,
 * arrays are flattened and objects contribute the keys with truthy values.
 *
 * @param {...unknown} args Class name values.
 * @return {string} Space separated class names.
 */
export function classNames( ...args ) {
	const classes = [];
	args.forEach( ( arg ) => {
		if ( ! arg ) {
			return;
		}
		if ( 'string' === typeof arg || 'number' === typeof arg ) {
			classes.push( arg );
		} else if ( Array.isArray( arg ) ) {
			const inner = classNames( ...arg );
			if ( inner ) {
				classes.push( inner );
			}
		} else if ( 'object' === typeof arg ) {
			Object.keys( arg ).forEach( ( key ) => {
				if ( arg[ key ] ) {
					classes.push( key );
				}
			} );
		}
	} );
	return classes.join( ' ' );
}

/**
 * Inline style for a button. An explicit `padding` wins over the default
 * padding, the `outline` variant adds its own look, and `style` always wins.
 *
 * @param {Object}        options           Button values.
 * @param {string|number} [options.padding] Explicit padding.
 * @param {string}        [options.variant] Button variant.
 * @param {string}        [options.size]    Button size.
 * @param {Object}        [options.style]   Style given by the caller.
 * @return {Object} Merged style.
 */
export function getButtonStyle( { padding, variant, size, style } ) {
	let paddingStyle = {};
	if ( padding ) {
		paddingStyle = { padding };
	} else if (
		[ 'secondary', 'primary' ].includes( variant ) &&
		'small' !== size
	) {
		paddingStyle = { padding: BUTTON_DEFAULT_PADDING };
	}

	return {
		...paddingStyle,
		...( 'outline' === variant ? BUTTON_OUTLINE_STYLE : {} ),
		...style,
	};
}

/**
 * Busy state of a button: the first truthy of `loading` and `isBusy`.
 *
 * @param {unknown} loading Loading flag.
 * @param {unknown} isBusy  Busy flag.
 * @return {unknown} Value for the `isBusy` button prop.
 */
export function getButtonBusy( loading, isBusy ) {
	return loading || isBusy;
}

/**
 * Class name of the split dropdown button wrapper.
 *
 * @param {boolean} loading Whether the main button is loading.
 * @return {string} Class name.
 */
export function getDropdownButtonClass( loading ) {
	return `ohmylms-dropdown-button ${ loading ? 'ohmylms-btn-disabled' : '' }`;
}

/**
 * Class name of the arrow menu of the split dropdown button.
 *
 * @param {string} className Extra class name.
 * @return {string} Class name.
 */
export function getDropdownButtonMenuClass( className ) {
	return `ohmylms-dropdown-button-menu ${ className }`;
}

/**
 * React key of a dropdown menu entry.
 *
 * @param {Object} item  Menu item.
 * @param {number} index Position in the list.
 * @return {string} Key.
 */
export function getMenuItemKey( item, index ) {
	return `${ index } - ${ item?.value }`;
}

/**
 * Props for the `MenuItem` that renders a dropdown entry. Disabled entries are
 * dimmed and ignore the pointer; `isPro` entries are never aria-disabled
 * because pro features are unlocked.
 *
 * @param {Object|null} item Menu item: `title`, `icon`, `value`, `disabled`, `isPro`.
 * @return {Object} MenuItem props, without `onClick` and `key`.
 */
export function getMenuItemProps( item ) {
	return {
		icon: item?.icon,
		'aria-disabled': item?.isPro && ! PRO_ITEMS_UNLOCKED,
		disabled: item?.disabled,
		style: {
			cursor: item?.disabled ? 'not-allowed' : 'pointer',
			opacity: item?.disabled ? 0.5 : 1,
			pointerEvents: item?.disabled ? 'none' : 'auto',
		},
	};
}

/**
 * Run a dropdown entry: disabled entries do nothing, any other entry runs its
 * `onClick` and then closes the menu.
 *
 * @param {Object|null} item    Menu item.
 * @param {() => void}  onClose Closes the menu.
 * @return {void}
 */
export function selectMenuItem( item, onClose ) {
	if ( item?.disabled ) {
		return;
	}
	if ( null !== item && undefined !== item ) {
		item.onClick();
	}
	onClose();
}

/**
 * Convert tab items into the `TabPanel` tab definitions.
 *
 * @param {Object[]} items     Items: `key`, `label`, `children`, `disabled`.
 * @param {string}   activekey Key of the active item.
 * @return {Object[]} Tabs.
 */
export function getTabs( items, activekey ) {
	return items.map( ( item ) => ( {
		name: item.key,
		title: item.label,
		className: classNames( 'ohmylms-tabs-tab', {
			'ohmylms-tabs-tab-disabled': item.disabled,
			'ohmylms-tabs-tab-active': activekey === item.key,
		} ),
		content: item.children,
		disabled: item?.disabled || false,
	} ) );
}

/**
 * Class name of the tabs container.
 *
 * @param {Object}  options           Options.
 * @param {string}  options.className Extra class name.
 * @param {boolean} options.centered  Whether the tabs are centered.
 * @param {string}  options.variant   `horizontal` or `vertical`.
 * @return {string} Class name.
 */
export function getTabsClassName( { className, centered, variant } ) {
	return classNames( TABS_CLASS, className, {
		'ohmylms-tabs-centered': centered,
		'ohmylms-tabs-vertical': 'vertical' === variant,
	} );
}

/**
 * Content of the active tab. Only the tab matching `activekey` is shown.
 *
 * @param {Object[]} tabs      Tabs from `getTabs`.
 * @param {string}   activekey Key of the active item.
 * @return {unknown} Content of the active tab, or undefined.
 */
export function getActiveTabContent( tabs, activekey ) {
	return tabs.find( ( tab ) => tab.name === activekey )?.content;
}
