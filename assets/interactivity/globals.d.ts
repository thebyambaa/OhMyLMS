/**
 * Page globals the learner modules read: classic WordPress scripts, PHP-printed data and
 * third-party libraries loaded on demand. Not validated at runtime.
 */

interface Window {
	/** Classic WordPress scripts (`wp-i18n` is a dependency of every learner module page). */
	wp: { i18n: typeof import( '@wordpress/i18n' ); [ key: string ]: any };
	/** OhMyLMS\Assessment\MathLive::config(). */
	ohmylmsMath?: {
		enabled: boolean;
		runtime: string;
		fonts: string;
		style: string;
	};
	/** The math-runtime bundle (assets/src/features/math/runtime.mjs) once loaded. */
	OhMyLMSMath?: Record< string, any >;
	/** Interactive question widgets (OhMyLMS\Assessment\Interactive::enqueue()). */
	OhMyLMSInteractive?: Record< string, any >;
	/** Answer recording limits printed for extended questions. */
	ohmylmsResponseMedia?: Record< string, any >;
	/** Celebration animation and its queue (OhMyLMS\Gamification). */
	OhMyLMSCelebrate?: { startConfetti(): void; [ key: string ]: any };
	ohmylmsCelebrationQueue?: unknown[];
	ohmylmsCelebrationReady?: boolean;
	/** Payment gateway add-ons register here before or after ohmylms/checkout runs. */
	PaymentGatewayHandlers?: Record< string, unknown >;
	registerPaymentGatewayHandler?: ( id: string, handler: unknown ) => void;
	/** Third-party libraries, loaded on demand. */
	JXG?: any;
	YT?: any;
	onYouTubeIframeAPIReady?: () => void;
	Stripe?: any;
	/**
	 * Legacy jQuery, when a theme or add-on loads it. Only the event bridges listed in
	 * tools/architecture-rules/frontend.mjs may touch it.
	 */
	jQuery?: any;
}
