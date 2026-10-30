import { useRef, type KeyboardEvent } from 'react';

export interface TabItem {
	id: string;
	label: string;
	/** Shown after the label, for example an unsaved-changes marker. */
	badge?: string;
}

/**
 * Accessible tab list (WAI-ARIA tabs pattern): one tab stop, Left/Right (and Up/Down) move and
 * select, Home/End jump to the ends. Panels are rendered by the caller with `tabPanelId` and
 * `tabId` so every tab and panel stay linked.
 *
 * @param props          Component props.
 * @param props.label    Accessible name of the tab list.
 * @param props.tabs     Tabs in order.
 * @param props.selected Id of the selected tab.
 * @param props.onSelect Called with the id of the tab to select.
 */
export function Tabs( {
	label,
	tabs,
	selected,
	onSelect,
}: {
	label: string;
	tabs: readonly TabItem[];
	selected: string;
	onSelect: ( id: string ) => void;
} ) {
	const refs = useRef< Record< string, HTMLButtonElement | null > >( {} );

	function move( event: KeyboardEvent< HTMLButtonElement >, index: number ) {
		const last = tabs.length - 1;
		let next: number | null = null;
		switch ( event.key ) {
			case 'ArrowRight':
			case 'ArrowDown':
				next = index === last ? 0 : index + 1;
				break;
			case 'ArrowLeft':
			case 'ArrowUp':
				next = 0 === index ? last : index - 1;
				break;
			case 'Home':
				next = 0;
				break;
			case 'End':
				next = last;
				break;
			default:
				return;
		}
		event.preventDefault();
		const target = tabs[ next ];
		if ( target ) {
			onSelect( target.id );
			refs.current[ target.id ]?.focus();
		}
	}

	return (
		<div role="tablist" aria-label={ label } className="nav-tab-wrapper">
			{ tabs.map( ( tab, index ) => {
				const active = tab.id === selected;
				return (
					<button
						key={ tab.id }
						ref={ ( node ) => {
							refs.current[ tab.id ] = node;
						} }
						type="button"
						role="tab"
						id={ tabId( tab.id ) }
						aria-selected={ active }
						aria-controls={ tabPanelId( tab.id ) }
						tabIndex={ active ? 0 : -1 }
						className={ `nav-tab${ active ? ' nav-tab-active' : '' }` }
						onClick={ () => onSelect( tab.id ) }
						onKeyDown={ ( event ) => move( event, index ) }
					>
						{ tab.label }
						{ tab.badge ? ` ${ tab.badge }` : '' }
					</button>
				);
			} ) }
		</div>
	);
}

export function tabId( id: string ): string {
	return `ohmylms-settings-tab-${ id }`;
}

export function tabPanelId( id: string ): string {
	return `ohmylms-settings-panel-${ id }`;
}
