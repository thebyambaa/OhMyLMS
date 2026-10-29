import { TabPanel } from '@wordpress/components';
import { createElement, memo, useState } from '@wordpress/element';
import {
	getActiveTabContent,
	getTabs,
	getTabsClassName,
	omitProps,
} from './model.mjs';

const OWN_PROPS = [
	'items',
	'activekey',
	'onChange',
	'className',
	'centered',
	'tabBarExtraContent',
	'variant',
];

/**
 * Tabs built on the WordPress `TabPanel`. The active tab is controlled through
 * `activekey`, and only its content is shown.
 *
 * @param {Object}                    props                      Component props.
 * @param {Object[]}                  [props.items]              Tabs: `key`, `label`, `children`, `disabled`. Defaults to [].
 * @param {string}                    props.activekey            Key of the active tab.
 * @param {( key: string ) => void}   [props.onChange]           Called with the key of the selected tab.
 * @param {string}                    [props.className]          Extra class name. Defaults to "".
 * @param {boolean}                   [props.centered]           Center the tab bar. Defaults to false.
 * @param {import('react').ReactNode} [props.tabBarExtraContent] Content shown above the tabs. Defaults to null.
 * @param {string}                    [props.variant]            `horizontal` (default) or `vertical`.
 * @return {import('react').ReactElement} Tabs.
 */
function TabsWPBase( props ) {
	const {
		items = [],
		activekey,
		onChange = () => {},
		className = '',
		centered = false,
		tabBarExtraContent = null,
		variant = 'horizontal',
	} = props;
	const [ , setSelected ] = useState( activekey || items[ 0 ]?.key );
	const tabs = getTabs( items, activekey );

	return createElement(
		'div',
		{
			className: getTabsClassName( { className, centered, variant } ),
			...omitProps( props, OWN_PROPS ),
			key: 0,
		},
		tabBarExtraContent &&
			createElement(
				'div',
				{ className: 'ohmylms-tabs-header' },
				createElement(
					'div',
					{ className: 'ohmylms-tabs-extra' },
					tabBarExtraContent
				)
			),
		createElement(
			TabPanel,
			{
				key: 0,
				tabs,
				initialTabName: activekey,
				onSelect: ( name ) => {
					setSelected( name );
					onChange( name );
				},
			},
			() =>
				createElement(
					'div',
					{ className: `ohmylms-tabs-content ${ activekey }` },
					getActiveTabContent( tabs, activekey )
				)
		)
	);
}

export const TabsWP = memo( TabsWPBase );
