/** Ambient declarations for the admin app. No other global declarations belong in the app. */

declare module '*.css';

interface Window {
	/** Bootstrap data printed by includes/Assets/AdminAppAssets.php. Unvalidated; read it with `parseBootstrap`. */
	ohmylmsApp?: unknown;
}
