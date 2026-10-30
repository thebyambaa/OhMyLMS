import { useRef, type KeyboardEvent } from 'react';
import { useAdminPlace } from '../../routing/place';

export interface TabItem {
	id: string;
	label: string;
}

/**
 * Accessible tab list (WAI-ARIA tabs pattern): one tab stop, arrow keys move and select, Home/End
 * jump to the ends. The caller renders panels with `tabPanelId` and `tabId`.
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
	// On an admin page the server prints these as tabs or sections already.
	const place = useAdminPlace();

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

	if ( place ) {
		return null;
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
					</button>
				);
			} ) }
		</div>
	);
}

export function tabId( id: string ): string {
	return `ohmylms-account-hub-tab-${ id }`;
}

export function tabPanelId( id: string ): string {
	return `ohmylms-account-hub-panel-${ id }`;
}
