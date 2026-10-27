/**
 * Tabs that other screens add to the standalone Gamification screen. The extension entry registers them
 * while it builds the route table (it holds the application's own screens); the Gamification chunk reads
 * them when it renders. This module sits in the extension entry, so both sides share one registry.
 */
const tabs = new Map();

/**
 * `{ key, label, Component }`: `key` is the `#/gamification/<key>` address and `label` the English tab title.
 * @param tab
 */
export function registerGamificationTab( tab ) {
	tabs.set( tab.key, tab );
}

export function gamificationExtraTabs() {
	return [ ...tabs.values() ];
}
