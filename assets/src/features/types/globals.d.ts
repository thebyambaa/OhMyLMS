/**
 * Page data and scripts the native editors read from `window`. Printed or enqueued by PHP; the
 * shapes follow the PHP that prints them and are not validated at runtime.
 */

/** `ohmylmsNativeLesson`, includes/Learning/NativeLessonEditor.php. */
interface OhMyLMSNativeLessonData {
	returnUrl: string;
	previewUrl: string;
	hasReturn: boolean;
	initialSkillId: number;
	questionTypes: Record< string, any >;
	newQuestionUrl: string;
	bankUrl: string;
	chapterId: number;
	profile: Record< string, any > | null;
	assessmentTypes: { value: string; label: string }[];
	printUrls: { paper: string; key: string } | null;
}

/** `ohmylmsMath`, OhMyLMS\Assessment\MathLive::config(). */
interface OhMyLMSMathConfig {
	enabled: boolean;
	runtime: string;
	fonts: string;
	style: string;
}

interface Window {
	ohmylmsNativeLesson?: OhMyLMSNativeLessonData;
	ohmylmsMath?: OhMyLMSMathConfig;
	/** The math-runtime bundle (features/math/runtime.mjs) once loaded. */
	OhMyLMSMath?: Record< string, any >;
	/** Player template choices, OhMyLMS\Quiz\PlayerTemplates::choices(). */
	ohmylmsPlayerTemplates?: { value: string; label: string }[];
	/** Saved tab orders per scope, printed for MovableTabs. */
	ohmylmsTabPreferences?: Record< string, string[] >;
	/** Assessment profile panels by kind; add-ons may add their own (ProfilePanel.jsx). */
	ohmylmsProfilePanels?: Record< string, any >;
	/** Public extension API v1 host (assets/src/app/extensions). */
	ohmylms?: {
		extensions?: import( '../../../app/extensions/registry.mjs' ).ExtensionRegistry &
			Record< string, any >;
		[ key: string ]: unknown;
	};
	ohmylmsExtensionManifest?: Record< string, any >;
	/** Learner widgets (OhMyLMS\Assessment\Interactive::enqueue()). */
	OhMyLMSInteractive?: Record< string, any >;
	/** Figure scanner of the exam import screen. */
	OhMyLMSFigures?: { scan(): void };
	/** WordPress globals of classic scripts (media library, api-fetch). */
	wp?: Record< string, any >;
	/** Underscore, which the media library needs (version 1.x, not lodash). */
	_?: Record< string, any >;
}
