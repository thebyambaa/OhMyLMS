import type { ExtensionHost } from './host';
import type { ExtensionContext, ExtensionKind } from './registry.mjs';

/**
 * Mount the slots PHP marked up outside the admin app (`Slots::render()`): every `[data-ohmylms-slot]`
 * element, with its JSON `data-ohmylms-context` and an optional `data-ohmylms-kind` (`checkout-field`).
 * Elements whose context is not valid JSON are skipped.
 *
 * @param host Installed extension host.
 * @param root Where to look; the document by default.
 */
export function mountSlots(
	host: Pick< ExtensionHost, 'mount' >,
	root: ParentNode = document
): void {
	root.querySelectorAll< HTMLElement >( '[data-ohmylms-slot]' ).forEach(
		( element ) => {
			let context: ExtensionContext;
			try {
				context = JSON.parse( element.dataset.ohmylmsContext || '{}' );
			} catch {
				return;
			}
			host.mount(
				element,
				element.dataset.ohmylmsSlot || '',
				context,
				( element.dataset.ohmylmsKind || 'slot' ) as ExtensionKind
			);
		}
	);
}
