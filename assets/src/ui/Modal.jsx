import { createElement, useEffect, useId, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from './Button';

/**
 * Native modal dialog with browser focus containment, Escape and focus restoration.
 *
 * @param {Object}     props                Component properties.
 * @param {boolean}    props.open           Whether the dialog is open (default true).
 * @param {string}     props.title          Accessible visible title.
 * @param {() => void} props.onRequestClose Request to close; caller updates open state.
 * @param {string}     props.closeLabel     Accessible close-button text.
 * @param {string}     props.className      Extra dialog classes.
 * @param {Object}     props.children       Dialog content.
 */
export function Modal( {
	open = true,
	title,
	onRequestClose,
	closeLabel = __( 'Close', 'ohmylms' ),
	className = '',
	children,
} ) {
	const ref = useRef();
	const id = useId();
	useEffect( () => {
		const dialog = ref.current;
		const previousFocus = document.activeElement;
		if ( open && ! dialog.open ) {
			dialog.showModal();
		}
		if ( ! open && dialog.open ) {
			dialog.close();
		}
		return () => {
			if ( dialog.open ) {
				dialog.close();
			}
			if ( open && previousFocus?.isConnected ) {
				previousFocus.focus();
			}
		};
	}, [ open ] );
	return createElement(
		'dialog',
		{
			ref,
			className: `oml-modal ${ className }`,
			'aria-labelledby': id,
			onKeyDown: ( event ) => {
				if ( event.key !== 'Tab' ) {
					return;
				}
				const focusable = Array.from(
					event.currentTarget.querySelectorAll(
						'a[href], button, input, select, textarea, [tabindex]'
					)
				).filter(
					( node ) =>
						! node.disabled &&
						node.tabIndex >= 0 &&
						node.getClientRects().length
				);
				const first = focusable[ 0 ];
				const last = focusable[ focusable.length - 1 ];
				if ( event.shiftKey && document.activeElement === first ) {
					event.preventDefault();
					last?.focus();
				} else if (
					! event.shiftKey &&
					document.activeElement === last
				) {
					event.preventDefault();
					first?.focus();
				}
			},
			onCancel: ( event ) => {
				event.preventDefault();
				onRequestClose?.();
			},
			onClose: () => {
				if ( open ) {
					onRequestClose?.();
				}
			},
		},
		createElement(
			'header',
			{ className: 'oml-modal-header' },
			createElement( 'h2', { className: 'oml-modal-title', id }, title ),
			createElement(
				Button,
				{
					variant: 'tertiary',
					onClick: onRequestClose,
					'aria-label': closeLabel,
				},
				closeLabel
			)
		),
		createElement( 'div', { className: 'oml-modal-content' }, children )
	);
}
