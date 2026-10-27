<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Public skill directories use a publication snapshot; previews read the editor's outline.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Curriculum;

defined( 'ABSPATH' ) || exit;

/** Public skill directories use a publication snapshot; previews read the editor's outline. */
final class Directory {
	const META = '_ohmylms_syllabus_directory';

	/**
	 * Init for Directory.
	 */
	public static function init() {
		add_filter(
			'query_vars',
			static function ( $vars ) {
				$vars[] = 'ohmylms_syllabus';
				return $vars;
			}
		);
		add_action( 'template_redirect', array( __CLASS__, 'page' ) );
		add_shortcode(
			'ohmylms_syllabus',
			static function ( $attrs ) {
				return self::render( absint( $attrs['id'] ?? 0 ) );
			}
		);
	}

	/**
	 * Url for Directory.
	 *
	 * @param mixed $id Id.
	 * @param bool  $preview Preview.
	 */
	public static function url( $id, $preview = false ) {
		return add_query_arg( array( 'ohmylms_syllabus' => (int) $id ) + ( $preview ? array( 'syllabus_preview' => 1 ) : array() ), home_url( '/' ) );
	}

	/** Keep only display data, never editor resources or unpublished learning content.
	 *
	 * @param array $outline Outline.
	 * @param array $settings Configuration settings.
	 * @param mixed $allowed Allowed.
	 */
	public static function build( array $outline, array $settings, $allowed = null ) {
		$topics = array();
		$count  = 0;
		foreach ( $outline['contents'] as $content ) {
			$groups = array();
			foreach ( $content['groups'] as $group ) {
				if ( ! $group['skills'] ) {
					continue; }
				$skills = array();
				foreach ( $group['skills'] as $skill ) {
					if ( null !== $allowed && ! in_array( (int) $skill['term_id'], $allowed, true ) ) {
						continue; }
					$skills[] = array_intersect_key( $skill, array_flip( array( 'term_id', 'name', 'code', 'category' ) ) );
				}
				if ( ! $skills ) {
					continue; }
				$count   += count( $skills );
				$groups[] = array(
					'name'   => $group['name'],
					'icon'   => $group['icon'] ?? '',
					'skills' => $skills,
				);
			}
			if ( $groups ) {
				$topics[] = array(
					'name'   => $content['name'],
					'icon'   => $content['icon'] ?? '',
					'groups' => $groups,
				); }
		}
		return array(
			'name'        => $outline['syllabus']['name'],
			'description' => $outline['syllabus']['description'] ?? '',
			'settings'    => $settings,
			'topics'      => $topics,
			'count'       => $count,
		);
	}

	/**
	 * Capture for Directory.
	 *
	 * @param mixed $course Course.
	 * @param array $program Program.
	 */
	public static function capture( $course, array $program ) {
		$id = SyllabusCourse::owner( $course );
		if ( ! $id ) {
			return true; }
		$outline = Syllabus::outline( $id );
		if ( is_wp_error( $outline ) ) {
			return false; }
		$snapshot = self::build( $outline, SyllabusSettings::get( $id ), array_map( 'intval', array_column( $program['outcomes'], 'term_id' ) ) );
		return get_post_meta( $course, self::META, true ) === $snapshot || (bool) update_post_meta( $course, self::META, $snapshot );
	}

	/**
	 * Data for Directory.
	 *
	 * @param mixed $id Id.
	 * @param bool  $preview Preview.
	 */
	public static function data( $id, $preview = false ) {
		$course = SyllabusCourse::course_id( $id );
		if ( ! $course || post_password_required( $course ) ) {
			return null; }
		if ( $preview && Access::can_manage() ) {
			$outline = Syllabus::outline( $id );
			return is_wp_error( $outline ) ? null : self::build( $outline, SyllabusSettings::get( $id ) );
		}
		if ( get_post_status( $course ) !== 'publish' ) {
			return null; }
		$snapshot = get_post_meta( $course, self::META, true );
		return is_array( $snapshot ) && isset( $snapshot['topics'] ) ? $snapshot : null;
	}

	/**
	 * Page for Directory.
	 */
	public static function page() {
		$id = (int) get_query_var( 'ohmylms_syllabus' );
		if ( ! $id ) {
			return; }
		$preview = ! empty( $_GET['syllabus_preview'] ) && Access::can_manage();
		if ( ! self::data( $id, $preview ) ) {
			status_header( 404 );
			nocache_headers();
			get_header();
			echo '<main><p>' . esc_html__( 'This syllabus is not available yet.', 'ohmylms' ) . '</p></main>';
			get_footer();
			exit; }
		if ( $preview ) {
			nocache_headers(); }
		status_header( 200 );
		$html = self::render( $id, $preview );
		get_header();
		echo $html; // All values escaped in render().
		get_footer();
		exit;
	}

	/**
	 * Icon for Directory.
	 *
	 * @param mixed  $icon Icon.
	 * @param string $default Default.
	 */
	private static function icon( $icon, $default = 'category' ) {
		$clean = Icons::clean( $icon );
		return '<span aria-hidden="true" class="dashicons dashicons-' . esc_attr( is_wp_error( $clean ) || ! $clean ? $default : $clean ) . '"></span>';
	}

	/**
	 * Render for Directory.
	 *
	 * @param mixed $id Id.
	 * @param bool  $preview Preview.
	 */
	public static function render( $id, $preview = false ) {
		$data = self::data( $id, $preview );
		if ( ! $data ) {
			return ''; }
		wp_enqueue_style( 'ohmylms-syllabus-directory', plugins_url( 'assets/css/syllabus-directory.css', OHMYLMS_FILE ), array( 'dashicons' ), OHMYLMS_VERSION );
		wp_enqueue_script( 'ohmylms-syllabus-directory', plugins_url( 'assets/js/syllabus-directory.js', OHMYLMS_FILE ), array(), OHMYLMS_VERSION, true );
		$categories = array();
		$selected   = null;
		$skill_id   = isset( $_GET['syllabus_skill'] ) ? absint( $_GET['syllabus_skill'] ) : 0;
		foreach ( $data['topics'] as $topic ) {
			foreach ( $topic['groups'] as $group ) {
				foreach ( $group['skills'] as $skill ) {
					if ( $skill['term_id'] === $skill_id ) {
						$selected = $skill; }
					if ( ! empty( $skill['category'] ) ) {
						$categories[ $skill['category'] ] = ( $categories[ $skill['category'] ] ?? 0 ) + 1; }
				}
			}
		}
		$base = self::url( $id, $preview );
		ob_start();
		?>
		<main class="om-syllabus" data-directory>
			<?php
			if ( $preview ) {
				?>
				<div class="om-preview"><?php esc_html_e( 'Preview · Only administrators can see unpublished changes.', 'ohmylms' ); ?></div><?php } ?>
			<div class="om-directory-layout">
				<aside class="om-directory-nav" aria-label="<?php esc_attr_e( 'Syllabuses', 'ohmylms' ); ?>">
					<div class="om-nav-title"><?php esc_html_e( 'Explore skills', 'ohmylms' ); ?></div>
					<a class="om-grade is-current" href="<?php echo esc_url( $base ); ?>" aria-current="page"><?php echo esc_html( $data['settings']['grade'] ? $data['settings']['grade'] : $data['name'] ); ?><small><?php echo esc_html( $data['settings']['subject'] ); ?></small></a>
					<?php
					$courses = get_posts(
						array(
							'post_type'      => OHMYLMS_COURSE_CPT,
							'post_status'    => 'publish',
							'posts_per_page' => -1,
							'meta_key'       => self::META,
							'orderby'        => 'title',
							'order'          => 'ASC',
						)
					);
					foreach ( $courses as $course ) {
						$other = SyllabusCourse::owner( $course->ID );
						if ( $other === (int) $id || ! $other ) {
							continue; }
						$profile = self::data( $other );
						if ( ! $profile ) {
							continue; }
						echo '<a class="om-grade" href="' . esc_url( self::url( $other ) ) . '">' . esc_html( $profile['settings']['grade'] ? $profile['settings']['grade'] : $profile['name'] ) . '<small>' . esc_html( $profile['settings']['subject'] ) . '</small></a>';
					}
					?>
				</aside>
				<div class="om-directory-main">
					<header class="om-directory-hero">
						<div class="om-eyebrow"><?php echo esc_html( implode( ' · ', array_filter( array( $data['settings']['subject'], $data['settings']['language'] ) ) ) ); ?></div>
						<h1><?php echo esc_html( $data['name'] ); ?></h1>
						<p><?php echo esc_html( $data['description'] ? $data['description'] : __( 'Explore topics, choose a skill, and practise at your own pace.', 'ohmylms' ) ); ?></p>
						<div class="om-directory-stats"><span><strong><?php echo esc_html( $data['count'] ); ?></strong> <?php esc_html_e( 'skills', 'ohmylms' ); ?></span><span><strong><?php echo esc_html( count( $data['topics'] ) ); ?></strong> <?php esc_html_e( 'topics', 'ohmylms' ); ?></span></div>
					</header>
					<?php if ( $selected ) { ?>
						<a class="om-back" href="<?php echo esc_url( $base ); ?>">← <?php esc_html_e( 'Back to all skills', 'ohmylms' ); ?></a>
						<section class="om-skill-practice"><div class="om-eyebrow"><?php echo esc_html( $selected['code'] . ' · ' . ( $selected['category'] ?? '' ) ); ?></div>
						<?php
						// Catalog practice retains its existing bank/public-practice access policy.
						$practice = \OhMyLMS\Practice\Frontend::practice( array( 'skill' => $skill_id ) );
						if ( $practice ) {
							echo $practice; } else {
							echo '<h2>' . esc_html( $selected['name'] ) . '</h2><p>' . esc_html__( 'Practice is not available yet.', 'ohmylms' ) . '</p>'; }
							?>
						</section>
					<?php } else { ?>
						<div class="om-directory-controls">
							<label class="om-search"><span class="dashicons dashicons-search" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Search skills', 'ohmylms' ); ?></span><input type="search" data-skill-search placeholder="<?php esc_attr_e( 'Search skills or skill codes…', 'ohmylms' ); ?>"></label>
							<div class="om-category-filters" role="group" aria-label="<?php esc_attr_e( 'Skill category', 'ohmylms' ); ?>"><button type="button" data-category="" aria-pressed="true"><?php esc_html_e( 'All skills', 'ohmylms' ); ?> <span><?php echo esc_html( $data['count'] ); ?></span></button>
								<?php
								foreach ( $categories as $category => $count ) {
									?>
									<button type="button" data-category="<?php echo esc_attr( $category ); ?>" aria-pressed="false"><?php echo esc_html( $category ); ?> <span><?php echo esc_html( $count ); ?></span></button><?php } ?>
							</div>
						</div>
						<p class="om-results" data-skill-results aria-live="polite" data-label="<?php esc_attr_e( 'skills shown', 'ohmylms' ); ?>"><?php echo esc_html( $data['count'] ); ?> <?php esc_html_e( 'skills shown', 'ohmylms' ); ?></p>
						<div class="om-topic-grid">
						<?php foreach ( $data['topics'] as $topic ) { ?>
							<section class="om-topic" data-topic><h2><?php echo self::icon( $topic['icon'] ); ?><?php echo esc_html( $topic['name'] ); ?></h2>
								<?php
								foreach ( $topic['groups'] as $group ) {
									?>
									<div class="om-skill-group" data-skill-group><h3><?php echo self::icon( $group['icon'], 'book-alt' ); ?><?php echo esc_html( $group['name'] ); ?></h3><ol>
									<?php
									foreach ( $group['skills'] as $skill ) {
										?>
										<li data-skill data-category="<?php echo esc_attr( $skill['category'] ?? '' ); ?>"><a href="<?php echo esc_url( add_query_arg( 'syllabus_skill', $skill['term_id'], $base ) ); ?>"><span class="om-skill-code"><?php echo esc_html( $skill['code'] ); ?></span><span class="om-skill-name"><?php echo esc_html( $skill['name'] ); ?></span><span class="om-skill-arrow" aria-hidden="true">↗</span></a></li><?php } ?>
								</ol></div><?php } ?>
							</section>
						<?php } ?>
						</div>
						<p data-no-results hidden><?php esc_html_e( 'No skills match. Try another search or category.', 'ohmylms' ); ?></p>
					<?php } ?>
				</div>
			</div>
		</main>
		<?php
		return ob_get_clean();
	}
}
