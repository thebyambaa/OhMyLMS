import type { AdminGroup } from '../features/types';
import { DEFAULT_EXTENSION_GROUP, isAdminGroup } from '../routing/address';
import type { ExtensionEntry, ExtensionRegistry } from './registry.mjs';

/**
 * Admin page an extension page belongs to: its optional `group` (`content`, `accounts`, `commerce`,
 * `engagement` or `settings`), Settings otherwise. The Dashboard has no tabs, so it is not a group for
 * extension pages. See docs/EXTENSIONS.md.
 *
 * @param entry Registered admin page.
 * @return Group.
 */
export function adminPageGroup(
	entry: ExtensionEntry | undefined
): AdminGroup {
	const group = entry?.group;
	return isAdminGroup( group ) && 'dashboard' !== group
		? group
		: DEFAULT_EXTENSION_GROUP;
}

/**
 * Group of the extension admin page with an id.
 *
 * @param registry Extension registry.
 * @param id       Admin page id.
 * @return Group.
 */
export function extensionGroupOf(
	registry: ExtensionRegistry | undefined,
	id: string
): AdminGroup {
	return adminPageGroup( registry?.get( 'admin-page', id ) );
}
