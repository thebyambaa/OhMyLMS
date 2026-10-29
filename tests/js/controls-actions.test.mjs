import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire, register } from 'node:module';
import * as I18n from '@wordpress/i18n';
import {
	createLoader,
	installBrowserStubs,
} from '../../tools/recovered-modules.mjs';
import * as stubs from './controls-actions-stubs.mjs';
import {
	classNames,
	getActiveTabContent,
	getButtonBusy,
	getButtonStyle,
	getDropdownButtonClass,
	getDropdownButtonMenuClass,
	getMenuItemKey,
	getMenuItemProps,
	getTabs,
	getTabsClassName,
	omitProps,
	selectMenuItem,
} from '../../assets/src/shared/controls/actions/model.mjs';

// Comparison strategy
// - REAL RENDERS: the authored components and the recovered ones (inline code
//   of factory 38093 for DropdownMenuWP, DropdownButtonWP, EmptyWP and
//   ProgressBarWP, plus factories 94490, 24295, 22563, 39706, 69986 and 12278)
//   are both rendered with renderToStaticMarkup. `@wordpress/components` is a
//   set of probes that print their props, so every prop and every child is
//   compared. Factory 38093 is loaded for real; its unrelated imports get an
//   inert stand-in.
// - ELEMENT-LEVEL: callbacks (menu item clicks, tab selection, ref forwarding)
//   are compared on the elements the components return.
// - LOGIC ONLY: the pure helpers in model.mjs.
// Not compared against the real WordPress components, which are not installed
// for Node, and the FlexWP of the layout group is replaced by a stand-in with
// the shape of factory 4315.
const hostRequire = installBrowserStubs();
const { renderToStaticMarkup } = hostRequire( 'react-dom/server' );
const React = hostRequire( 'react' );
const { createElement } = React;
globalThis.wp = new Proxy( {}, { get: () => ( {} ) } );

register( './controls-actions-hooks.mjs', import.meta.url );
const authored =
	await import( '../../assets/src/shared/controls/actions/index.js' );

// Recovered side. Ids loaded from the real files; any other id is an inert stub.
const realIds = new Set( [
	'38093',
	'94490',
	'24295',
	'22563',
	'39706',
	'69986',
	'4315',
	'52485',
	'97856',
	'34164',
	'12278',
	'46942',
	'57677',
] );
const fixed = {
	2214: stubs,
	91386: React,
	41594: React,
	12470: I18n,
	74848: hostRequire( 'react/jsx-runtime' ),
};
const inert = () =>
	new Proxy( function () {}, {
		get: ( target, key ) => ( '__esModule' === key ? true : inert() ),
		apply: () => inert(),
	} );
const recovered = createLoader(
	new Proxy(
		{},
		{
			has: ( target, id ) => id in fixed || ! realIds.has( id ),
			get: ( target, id ) => fixed[ id ] ?? inert(),
		}
	)
)( 38093 );

const html = ( Component, props, ...children ) =>
	renderToStaticMarkup( createElement( Component, props, ...children ) );

const Star = createElement( 'svg', { id: 'star', viewBox: '0 0 8 8' } );
const noop = () => {};

function compare( name, cases, children = [] ) {
	for ( const [ label, props ] of Object.entries( cases ) ) {
		test( `${ name } markup matches the recovered code: ${ label }`, () => {
			assert.equal(
				html( authored[ name ], props, ...children ),
				html( recovered[ name ], props, ...children )
			);
		} );
	}
}

compare(
	'ButtonWP',
	{
		'no props': {},
		primary: { variant: 'primary', onClick: noop },
		secondary: { variant: 'secondary' },
		'small primary has no default padding': {
			variant: 'primary',
			size: 'small',
		},
		'tertiary with icon': {
			variant: 'tertiary',
			icon: Star,
			size: 'small',
		},
		outline: { variant: 'outline' },
		'outline with style': {
			variant: 'outline',
			style: { color: 'red', height: 30 },
		},
		'explicit padding': { variant: 'primary', padding: '2px 4px' },
		'padding on a plain button': { padding: 8 },
		'style beats default padding': {
			variant: 'secondary',
			style: { padding: 0 },
		},
		loading: { loading: true, variant: 'primary' },
		isBusy: { isBusy: true },
		'loading false and isBusy': { loading: false, isBusy: true },
		'both false': { loading: false, isBusy: false },
		disabled: { disabled: true, variant: 'secondary', className: 'x' },
		'link button': {
			href: '/x',
			target: '_blank',
			rel: 'noopener',
			isPrimary: true,
		},
		destructive: { isDestructive: true, variant: 'link' },
		'aria and data props': {
			'aria-label': 'Close',
			'data-id': 4,
			type: 'submit',
		},
	},
	[ 'Save' ]
);

test( 'ButtonWP renders no children when none are given', () => {
	assert.equal(
		html( authored.ButtonWP, { variant: 'primary' } ),
		html( recovered.ButtonWP, { variant: 'primary' } )
	);
} );

test( 'ButtonWP forwards its ref to the WordPress button', () => {
	const ref = { current: null };
	const props = { variant: 'primary', loading: true, children: 'Go' };
	const fromAuthored = authored.ButtonWP.render( props, ref );
	const fromRecovered = recovered.ButtonWP.render( props, ref );
	assert.equal( fromAuthored.ref, ref );
	assert.equal( fromAuthored.ref, fromRecovered.ref );
	assert.equal( fromAuthored.type, fromRecovered.type );
	assert.deepEqual( fromAuthored.props, fromRecovered.props );
} );

test( 'ButtonWP leaves the caller style object untouched', () => {
	const style = { color: 'red' };
	authored.ButtonWP.render( { variant: 'outline', style }, null );
	assert.deepEqual( style, { color: 'red' } );
} );

const menuControls = [
	{ title: 'Edit', value: 'edit', icon: Star, onClick: noop },
	{ title: 'Delete', value: 'delete', disabled: true, onClick: noop },
	{ title: 'Pro feature', value: 'pro', isPro: true, onClick: noop },
	{ title: 'Without value', onClick: noop },
	null,
];

compare( 'DropdownMenuWP', {
	'icon and controls': { icon: Star, controls: [] },
	'string icon': { icon: 'ellipsis', controls: [ menuControls[ 0 ] ] },
	'all kinds of entries': { icon: Star, controls: menuControls },
	'extra DropdownMenu props': {
		icon: Star,
		controls: [ menuControls[ 0 ] ],
		label: 'Actions',
		className: 'menu',
		popoverProps: { placement: 'bottom-end' },
	},
	'caller toggleProps replace the default': {
		icon: Star,
		controls: [],
		toggleProps: { className: 'mine' },
	},
} );

/**
 * Menu item elements rendered by a DropdownMenuWP element tree.
 *
 * @param {Object} Component DropdownMenuWP component.
 * @param {Object} props     Component props.
 * @param {Function} onClose Close callback.
 * @return {Object} Wrapper div and the rendered items.
 */
function renderMenu( Component, props, onClose ) {
	const wrapper = Component.type( props );
	const menu = wrapper.props.children;
	const fragment = menu.props.children( { onClose } );
	return { wrapper, menu, items: [].concat( fragment.props.children ) };
}

test( 'dropdown entries run onClick and close the menu, like the recovered code', () => {
	const run = ( Component ) => {
		const log = [];
		const controls = [
			{ title: 'A', value: 'a', onClick: () => log.push( 'a' ) },
			{
				title: 'B',
				value: 'b',
				disabled: true,
				onClick: () => log.push( 'b' ),
			},
			null,
		];
		const { items } = renderMenu( Component, { icon: Star, controls }, () =>
			log.push( 'close' )
		);
		items.forEach( ( item ) => item.props.onClick() );
		return { log, keys: items.map( ( item ) => item.key ) };
	};
	const expected = run( recovered.DropdownMenuWP );
	assert.deepEqual( run( authored.DropdownMenuWP ), expected );
	assert.deepEqual( expected.log, [ 'a', 'close', 'close' ] );
} );

test( 'dropdown clicks do not bubble from the wrapper or the toggle', () => {
	for ( const Component of [
		authored.DropdownMenuWP,
		recovered.DropdownMenuWP,
	] ) {
		const { wrapper, menu } = renderMenu(
			Component,
			{ icon: Star, controls: [] },
			noop
		);
		let stopped = 0;
		const event = { stopPropagation: () => stopped++ };
		wrapper.props.onClick( event );
		menu.props.toggleProps.onClick( event );
		assert.equal( stopped, 2 );
	}
} );

test( 'a dropdown entry without onClick throws like the recovered code', () => {
	for ( const Component of [
		authored.DropdownMenuWP,
		recovered.DropdownMenuWP,
	] ) {
		const { items } = renderMenu(
			Component,
			{ icon: Star, controls: [ { title: 'x' } ] },
			noop
		);
		assert.throws( () => items[ 0 ].props.onClick(), TypeError );
	}
} );

compare( 'DropdownButtonWP', {
	'no props': {},
	'with items and label': {
		buttonLabel: 'Publish',
		menuItems: [ menuControls[ 0 ], menuControls[ 1 ] ],
		onClick: noop,
	},
	loading: { loading: true, buttonLabel: 'Saving' },
	'extra class': { className: 'wide', menuItems: [] },
	'ignores unknown props': { id: 'x', style: { color: 'red' } },
} );

compare( 'EmptyWP', {
	'no props': {},
	'description only': { description: 'No Orders Found' },
	'custom title and level': { title: 'No data', titleLevel: '2' },
	'empty title falls back to the region label': { title: '' },
	'null title': { title: null },
	'icon is shown at 48px': {
		icon: createElement( 'svg', { viewBox: '0 0 8 8' } ),
		title: 'Empty',
	},
	'action and class': {
		className: 'custom',
		actionComponent: createElement( 'button', null, 'Add' ),
		description: 'Nothing',
	},
	'extra props reach the region': {
		id: 'e',
		'data-x': 1,
		style: { margin: 1 },
	},
} );

compare( 'ProgressBarWP', {
	'no props': {},
	value: { value: 40, className: 'bar' },
	'indeterminate with extra props': { id: 'p', 'aria-label': 'Loading' },
} );

compare(
	'ModalWP',
	{
		'title only': { title: 'Edit', onRequestClose: noop },
		'options from real usage': {
			title: 'Delete',
			onRequestClose: noop,
			shouldCloseOnEsc: false,
			shouldCloseOnClickOutside: false,
			isDismissible: false,
			className: 'ohmylms-modal',
			contentClassName: 'content',
		},
	},
	[ createElement( 'p', { key: 'a' }, 'Body' ) ]
);

test( 'ModalWP, PopoverWP and NoticeWP render without children', () => {
	for ( const name of [ 'ModalWP', 'PopoverWP', 'NoticeWP' ] ) {
		assert.equal(
			html( authored[ name ], { className: 'x' } ),
			html( recovered[ name ], { className: 'x' } )
		);
	}
} );

compare(
	'PopoverWP',
	{
		anchor: { placement: 'bottom-end', onFocusOutside: noop },
		'no props': {},
		'class and offset': { className: 'pop', offset: 8, noArrow: false },
	},
	[ 'Hint', createElement( 'b', { key: 'k' }, 'x' ) ]
);

compare(
	'NoticeWP',
	{
		status: { status: 'success', isDismissible: false },
		'dismiss handler': { status: 'error', onRemove: noop, className: 'n' },
		'no props': {},
	},
	[ 'Saved' ]
);

compare( 'SpinWP', {
	'no props': {},
	'class and style': { className: 'spin', style: { margin: 4 } },
} );

test( 'SpinWP passes a copy of its props', () => {
	const props = { className: 'spin' };
	const element = authored.SpinWP( props );
	assert.deepEqual( element.props, props );
	assert.notEqual( element.props, props );
} );

const tabItems = [
	{ key: 'one', label: 'One', children: createElement( 'i', null, '1' ) },
	{ key: 'two', label: 'Two', children: 'Second' },
	{ key: 'three', label: 'Three', children: 'Third', disabled: true },
];

compare( 'TabsWP', {
	'no props': {},
	'first tab active': { items: tabItems, activekey: 'one' },
	'second tab active': { items: tabItems, activekey: 'two', onChange: noop },
	'disabled tab active': { items: tabItems, activekey: 'three' },
	'unknown active key': { items: tabItems, activekey: 'zzz' },
	'no active key': { items: tabItems },
	centered: { items: tabItems, activekey: 'one', centered: true },
	vertical: { items: tabItems, activekey: 'one', variant: 'vertical' },
	'class and extra content': {
		items: tabItems,
		activekey: 'two',
		className: 'tabs',
		centered: true,
		variant: 'vertical',
		tabBarExtraContent: createElement( 'button', null, 'Add' ),
	},
	'extra props reach the container': {
		items: tabItems,
		activekey: 'one',
		id: 't',
		'data-x': 2,
		style: { margin: 1 },
	},
} );

test( 'TabsWP selection calls onChange with the tab name, like the recovered code', () => {
	const run = ( Component ) => {
		const calls = [];
		stubs.captured.tabPanel.length = 0;
		html( Component, {
			items: tabItems,
			activekey: 'one',
			onChange: ( name ) => calls.push( name ),
		} );
		const panel = stubs.captured.tabPanel.at( -1 );
		panel.onSelect( 'two' );
		return {
			calls,
			initial: panel.initialTabName,
			tabs: panel.tabs.map( ( tab ) => tab.name ),
		};
	};
	const expected = run( recovered.TabsWP );
	assert.deepEqual( run( authored.TabsWP ), expected );
	assert.deepEqual( expected.calls, [ 'two' ] );
} );

test( 'TabsWP selection works without onChange', () => {
	stubs.captured.tabPanel.length = 0;
	html( authored.TabsWP, { items: tabItems, activekey: 'one' } );
	assert.doesNotThrow( () =>
		stubs.captured.tabPanel.at( -1 ).onSelect( 'two' )
	);
} );

test( 'every export is a component', () => {
	assert.deepEqual( Object.keys( authored ).sort(), [
		'ButtonWP',
		'DropdownButtonWP',
		'DropdownMenuWP',
		'EmptyWP',
		'ModalWP',
		'NoticeWP',
		'PopoverWP',
		'ProgressBarWP',
		'SpinWP',
		'TabsWP',
	] );
} );

// Logic only.

test( 'getButtonStyle applies padding, outline and caller style in order', () => {
	assert.deepEqual( getButtonStyle( {} ), {} );
	assert.deepEqual( getButtonStyle( { variant: 'primary' } ), {
		padding: '6px 24px',
	} );
	assert.deepEqual(
		getButtonStyle( { variant: 'secondary', size: 'small' } ),
		{}
	);
	assert.deepEqual( getButtonStyle( { variant: 'tertiary' } ), {} );
	assert.deepEqual( getButtonStyle( { padding: 3, variant: 'primary' } ), {
		padding: 3,
	} );
	assert.deepEqual(
		getButtonStyle( {
			variant: 'outline',
			padding: 1,
			style: { color: 'red' },
		} ),
		{
			padding: 1,
			border: '1px solid #C8D2E980',
			backgroundColor: 'transparent',
			color: 'red',
		}
	);
} );

test( 'getButtonBusy returns the first truthy of loading and isBusy', () => {
	assert.equal( getButtonBusy( true, false ), true );
	assert.equal( getButtonBusy( false, true ), true );
	assert.equal( getButtonBusy( false, false ), false );
	assert.equal( getButtonBusy( undefined, undefined ), undefined );
} );

test( 'menu helpers build keys, props and click behaviour', () => {
	assert.equal( getMenuItemKey( { value: 'a' }, 2 ), '2 - a' );
	assert.equal( getMenuItemKey( null, 0 ), '0 - undefined' );

	const plain = getMenuItemProps( { title: 'x' } );
	assert.equal( plain.style.cursor, 'pointer' );
	assert.equal( plain.style.opacity, 1 );
	assert.equal( plain.style.pointerEvents, 'auto' );
	assert.equal( plain[ 'aria-disabled' ], undefined );

	const off = getMenuItemProps( { disabled: true, isPro: true } );
	assert.deepEqual( off.style, {
		cursor: 'not-allowed',
		opacity: 0.5,
		pointerEvents: 'none',
	} );
	assert.equal( off[ 'aria-disabled' ], false );

	const log = [];
	selectMenuItem( { onClick: () => log.push( 'click' ) }, () =>
		log.push( 'close' )
	);
	selectMenuItem( { disabled: true, onClick: () => log.push( 'no' ) }, () =>
		log.push( 'no' )
	);
	selectMenuItem( null, () => log.push( 'close-null' ) );
	assert.deepEqual( log, [ 'click', 'close', 'close-null' ] );
} );

test( 'dropdown button class names keep their trailing space', () => {
	assert.equal( getDropdownButtonClass( false ), 'ohmylms-dropdown-button ' );
	assert.equal(
		getDropdownButtonClass( true ),
		'ohmylms-dropdown-button ohmylms-btn-disabled'
	);
	assert.equal(
		getDropdownButtonMenuClass( 'x' ),
		'ohmylms-dropdown-button-menu x'
	);
} );

test( 'tab helpers', () => {
	const tabs = getTabs( tabItems, 'two' );
	assert.deepEqual(
		tabs.map( ( tab ) => [
			tab.name,
			tab.title,
			tab.className,
			tab.disabled,
		] ),
		[
			[ 'one', 'One', 'ohmylms-tabs-tab', false ],
			[ 'two', 'Two', 'ohmylms-tabs-tab ohmylms-tabs-tab-active', false ],
			[
				'three',
				'Three',
				'ohmylms-tabs-tab ohmylms-tabs-tab-disabled',
				true,
			],
		]
	);
	assert.equal( getActiveTabContent( tabs, 'two' ), 'Second' );
	assert.equal( getActiveTabContent( tabs, 'nope' ), undefined );
	assert.equal(
		getTabsClassName( {
			className: '',
			centered: false,
			variant: 'horizontal',
		} ),
		'ohmylms-tabs-container'
	);
	assert.equal(
		getTabsClassName( {
			className: 'a',
			centered: true,
			variant: 'vertical',
		} ),
		'ohmylms-tabs-container a ohmylms-tabs-centered ohmylms-tabs-vertical'
	);
} );

test( 'classNames and omitProps', () => {
	assert.equal(
		classNames( 'a', '', null, [ 'b', { c: 1, d: 0 } ], 5 ),
		'a b c 5'
	);
	const symbol = Symbol( 'kept' );
	const rest = omitProps( { a: 1, b: 2, [ symbol ]: 3 }, [ 'a' ] );
	assert.deepEqual( Object.keys( rest ), [ 'b' ] );
	assert.equal( rest[ symbol ], 3 );
} );
