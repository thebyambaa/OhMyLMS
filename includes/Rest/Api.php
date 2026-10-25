<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * API Manager class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest;

use OhMyLMS\Admin\Settings\AdminSettings;
use OhMyLMS\Admin\Settings\RegisterSettings;

/**
 * API Manager class.
 *
 * All API classes would be registered here.
 *
 * @since 1.0.0
 */
class Api {

	/**
	 * Class dir and class name mapping.
	 *
	 * @var array
	 *
	 * @since 1.0.0
	 */
	protected $class_map;

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! class_exists( 'WP_REST_Server' ) ) {
			return;
		}

		$controllers_v1  = array(
			\OhMyLMS\Rest\V1\PluginController::class,
			\OhMyLMS\Rest\V1\CourseController::class,
			\OhMyLMS\Rest\V1\CourseStructureController::class,
			\OhMyLMS\Rest\V1\LearningController::class,
			\OhMyLMS\Rest\V1\ContentHubController::class,
			\OhMyLMS\Rest\V1\AuthoringController::class,
			\OhMyLMS\Rest\V1\SetupWizardController::class,
			\OhMyLMS\Rest\V1\StudentController::class,
			\OhMyLMS\Rest\V1\UserController::class,
			\OhMyLMS\Rest\V1\ChapterController::class,
			\OhMyLMS\Rest\V1\LessonController::class,
			\OhMyLMS\Rest\V1\QuizController::class,
			\OhMyLMS\Rest\V1\QuestionController::class,
			\OhMyLMS\Rest\V1\AssessmentController::class,
			\OhMyLMS\Rest\V1\QuestionBankController::class,
			\OhMyLMS\Rest\V1\QuestionImportController::class,
			\OhMyLMS\Rest\V1\QuestionBankToolsController::class,
			\OhMyLMS\Rest\V1\CourseStructureToolsController::class,
			\OhMyLMS\Rest\V1\SkillAssessmentToolsController::class,
			\OhMyLMS\Rest\V1\SkillController::class,
			\OhMyLMS\Rest\V1\AttemptController::class,
			\OhMyLMS\Rest\V1\PracticeController::class,
			\OhMyLMS\Rest\V1\PlacementController::class,
			\OhMyLMS\Rest\V1\SkillReportController::class,
			\OhMyLMS\Rest\V1\CurriculumController::class,
			\OhMyLMS\Rest\V1\SubjectController::class,
			\OhMyLMS\Rest\V1\SiteSetupController::class,
			\OhMyLMS\Rest\V1\SyllabusController::class,
			\OhMyLMS\Rest\V1\CourseOrganizationController::class,
			\OhMyLMS\Rest\V1\SkillMappingController::class,
			\OhMyLMS\Rest\V1\ComparisonController::class,
			\OhMyLMS\Rest\V1\BundleController::class,
			\OhMyLMS\Rest\V1\MyBundlesController::class,
			\OhMyLMS\Rest\V1\PreviewController::class,
			\OhMyLMS\Rest\V1\TemplateController::class,
			\OhMyLMS\Rest\V1\SettingsController::class,
			\OhMyLMS\Rest\V1\DesignSystemController::class,
			\OhMyLMS\Rest\V1\HubIconsController::class,
			\OhMyLMS\Rest\V1\CategoryController::class,
			\OhMyLMS\Rest\V1\TagController::class,
			\OhMyLMS\Rest\V1\EmailController::class,
			\OhMyLMS\Rest\V1\DashboardController::class,
			\OhMyLMS\Rest\V1\AnalyticsController::class,
			\OhMyLMS\Rest\V1\CertificateController::class,
			\OhMyLMS\Rest\V1\MigrationController::class,
			\OhMyLMS\Rest\V1\NotificationController::class,
			\OhMyLMS\Rest\V1\IntegrationsController::class,
			\OhMyLMS\Rest\V1\AppController::class,
			\OhMyLMS\Rest\V1\WebhookController::class,
		);
		$controllers_v1  = apply_filters( 'ohmylms_rest_v1_controllers', $controllers_v1 );
		$controllers_v2  = array();
		$this->class_map = array_merge( $controllers_v1, $controllers_v2 );

		// Init REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ), 10 );
		add_action( 'rest_api_init', array( $this, 'register_admin_settings' ), 10 );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		foreach ( $this->class_map as $controller_class ) {
			$controller_instance = new $controller_class();
			$controller_instance->register_routes();
		}
	}

	/**
	 * Register admin settings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_admin_settings() {
		$settings = AdminSettings::get_settings_objects();

		foreach ( $settings as $setting ) {
			new RegisterSettings( $setting );
		}
	}
}
