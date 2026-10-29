/* Progressive enhancement for PHP-rendered local tabs and native dialogs. */
( function () {
	'use strict';
	const update = ( root, active ) => {
		for ( const tab of root.querySelectorAll( '[role="tab"]' ) ) {
			if ( tab.closest( '[data-oml-tabs]' ) !== root ) {
				continue;
			}
			const selected = tab === active;
			tab.setAttribute( 'aria-selected', String( selected ) );
			tab.tabIndex = selected ? 0 : -1;
			const panel = document.getElementById(
				tab.getAttribute( 'aria-controls' )
			);
			if ( panel ) {
				panel.hidden = ! selected;
			}
		}
	};
	const initialize = () => {
		for ( const root of document.querySelectorAll( '[data-oml-tabs]' ) ) {
			update(
				root,
				root.querySelector( '[role="tab"][aria-selected="true"]' )
			);
		}
	};
	document.addEventListener( 'click', ( event ) => {
		const tab = event.target.closest( '[data-oml-tabs] [role="tab"]' );
		if ( tab && ! tab.disabled ) {
			update( tab.closest( '[data-oml-tabs]' ), tab );
		}
		const opener = event.target.closest( '[data-oml-dialog-open]' );
		if ( opener ) {
			const dialog = document.getElementById(
				opener.dataset.omlDialogOpen
			);
			if (
				dialog?.matches( 'dialog[data-oml-dialog]' ) &&
				! dialog.open
			) {
				dialog.showModal();
			}
		}
		const close = event.target.closest( '[data-oml-dialog-close]' );
		if ( close ) {
			close.closest( 'dialog[data-oml-dialog]' )?.close();
		}
	} );
	document.addEventListener( 'keydown', ( event ) => {
		const dialog = event.target.closest( 'dialog[data-oml-dialog]' );
		if ( dialog && event.key === 'Tab' ) {
			const nodes = Array.from(
				dialog.querySelectorAll(
					'a[href], button, input, select, textarea, [tabindex]'
				)
			).filter(
				( node ) =>
					! node.disabled &&
					node.tabIndex >= 0 &&
					node.getClientRects().length
			);
			const first = nodes[ 0 ];
			const last = nodes[ nodes.length - 1 ];
			if ( event.shiftKey && document.activeElement === first ) {
				event.preventDefault();
				last?.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first?.focus();
			}
			return;
		}
		const tab = event.target.closest( '[data-oml-tabs] [role="tab"]' );
		if ( ! tab ) {
			return;
		}
		const list = tab.closest( '[role="tablist"]' );
		const tabs = Array.from(
			list.querySelectorAll( '[role="tab"]:not(:disabled)' )
		);
		const vertical = list.getAttribute( 'aria-orientation' ) === 'vertical';
		const rtl = getComputedStyle( list ).direction === 'rtl';
		const horizontalForward = rtl ? 'ArrowLeft' : 'ArrowRight';
		const horizontalBackward = rtl ? 'ArrowRight' : 'ArrowLeft';
		const forward = vertical ? 'ArrowDown' : horizontalForward;
		const backward = vertical ? 'ArrowUp' : horizontalBackward;
		let index = tabs.indexOf( tab );
		if ( event.key === 'Home' ) {
			index = 0;
		} else if ( event.key === 'End' ) {
			index = tabs.length - 1;
		} else if ( event.key === forward ) {
			index = ( index + 1 ) % tabs.length;
		} else if ( event.key === backward ) {
			index = ( index - 1 + tabs.length ) % tabs.length;
		} else {
			return;
		}
		event.preventDefault();
		update( tab.closest( '[data-oml-tabs]' ), tabs[ index ] );
		tabs[ index ].focus();
	} );
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initialize );
	} else {
		initialize();
	}
} )();
