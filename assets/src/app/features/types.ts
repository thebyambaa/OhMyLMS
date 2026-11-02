import type { ComponentType, Element } from '@wordpress/element';

/**
 * The six WordPress admin pages of OhMyLMS (includes/Admin/AdminNavigation.php): Dashboard, Content Hub,
 * Account Hub, Commerce, Engagement and Settings.
 */
export type AdminGroup =
	| 'dashboard'
	| 'content'
	| 'accounts'
	| 'commerce'
	| 'engagement'
	| 'settings';

/** Address parameters that place a screen on its admin page (`&tab=`, `&section=`, `&view=`). */
export type AddressSlot = 'tab' | 'section' | 'view';

/**
 * What a feature contributes to the app. Each feature exports one of these from `features/<name>/index.ts`
 * and `features/registry.ts` lists it explicitly.
 *
 * `group`, `tab`, `section`, `view` and `params` give the screen its admin address
 * (`admin.php?page=<group page>&tab=<tab>…`); `routing/address.ts` turns them into addresses and back,
 * and includes/Admin/AdminNavigation.php holds the same table (tests/fixtures/admin-navigation.json).
 */
export interface FeatureDefinition {
	/** Unique, stable, kebab-case id. Also used as the React key. */
	readonly id: string;
	/** Translated page name. */
	readonly title: string;
	/** Absolute route path such as `/courses`. */
	readonly path: string;
	/** Optional icon element from `@wordpress/icons`. */
	readonly icon?: Element;
	/** Route component. Must be created with `lazy( () => import( './Page' ) )` so the feature is code-split. */
	readonly component: ComponentType;
	/** Route only: not a tab or section of its own (for example an editor opened from a list). */
	readonly hidden?: boolean;
	/** Capability the user needs (as exposed by `ohmylms_app_capabilities`). Omit for everyone. */
	readonly capability?: string;
	/**
	 * Route pattern extensions see as `context.route` and use as this screen's editor-panel slot. The public
	 * extension contract (docs/EXTENSIONS.md) names screens by their original admin routes, such as
	 * `/quiz-edit/:id`. Defaults to `path`.
	 */
	readonly extensionRoute?: string;
	/** Admin page the screen belongs to. */
	readonly group: AdminGroup;
	/**
	 * Tab on that page (`&tab=`); none on the Dashboard. A `:name` placeholder takes the tab from a path
	 * parameter (`ext-:id`). When `params` maps a route parameter to `tab`, this is the default tab.
	 */
	readonly tab?: string;
	/** Further tabs the screen serves when `params` maps a route parameter to `tab`. */
	readonly tabs?: readonly string[];
	/** Fixed section under the tab (`&section=`, a `.subsubsub` link). */
	readonly section?: string;
	/** Fixed view of a detail screen (`&view=`). */
	readonly view?: string;
	/**
	 * Route parameters (path segments or the route's own query) carried under another address name:
	 * `{ tab: 'view' }` puts `/courses/:id/:tab?`'s `:tab` in `&view=`, `{ tab: 'section' }` carries
	 * `/settings?tab=tax` as `&section=tax`. Other path parameters keep their names (`:id` is `&id=`).
	 */
	readonly params?: Readonly< Record< string, string > >;
}
