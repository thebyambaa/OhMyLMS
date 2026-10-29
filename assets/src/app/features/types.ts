import type { ComponentType, Element } from '@wordpress/element';

/**
 * What a feature contributes to the app. Each feature exports one of these from `features/<name>/index.ts`
 * and `features/registry.ts` lists it explicitly.
 */
export interface FeatureDefinition {
	/** Unique, stable, kebab-case id. Also used as the React key. */
	readonly id: string;
	/** Translated navigation label and page name. */
	readonly title: string;
	/** Absolute route path such as `/courses`. The home feature uses `/`. */
	readonly path: string;
	/** Optional icon element from `@wordpress/icons`. */
	readonly icon?: Element;
	/** Route component. Must be created with `lazy( () => import( './Page' ) )` so the feature is code-split. */
	readonly component: ComponentType;
	/** Route only: keep it out of the primary navigation (for example an editor opened from a list). */
	readonly hidden?: boolean;
	/** Capability the user needs (as exposed by `ohmylms_app_capabilities`). Omit for everyone. */
	readonly capability?: string;
}
