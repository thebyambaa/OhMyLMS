<?php
/**
 * Curriculum lesson discovery, pagination and access in the local WordPress copy.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Links;
use OhMyLMS\Curriculum\Placement;
use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Utility\Transaction;

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_STRUCTURE_INTEGRATION' ) ) {
	exit( "Set OHMYLMS_STRUCTURE_INTEGRATION=1 for this local integration check.\n" );
}
require dirname( __DIR__, 5 ) . '/wp-load.php';
wp_set_current_user(
	get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
		)
	)[0]->ID
);

/**
 * Assert a fixture behavior.
 *
 * @param bool   $ok Expected condition.
 * @param string $message Failure explanation.
 * @throws RuntimeException On a failed check.
 */
function lesson_curriculum_check( $ok, $message ) {
	if ( ! $ok ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

$lesson_curriculum_posts = array();
try {
	Transaction::run(
		static function () use ( &$lesson_curriculum_posts ) {
			$root                      = Items::create(
				array(
					'name'      => 'Library fixture parent',
					'item_type' => 'custom',
				)
			);
			$child                     = Items::create(
				array(
					'name'      => 'Library fixture child',
					'item_type' => 'custom',
					'parent_id' => $root['id'],
				)
			);
			$empty                     = Items::create(
				array(
					'name'      => 'Library fixture empty',
					'item_type' => 'custom',
				)
			);
			$term                      = wp_insert_term( 'Library fixture skill ' . wp_generate_uuid4(), Taxonomy::NAME );
			$course                    = wp_insert_post(
				array(
					'post_type'   => OHMYLMS_COURSE_CPT,
					'post_status' => 'draft',
					'post_title'  => 'Library fixture course',
				)
			);
			$lesson_curriculum_posts[] = $course;
			Placement::set_items( $course, array( $child['id'] ) );
			$lesson                    = wp_insert_post(
				array(
					'post_type'   => OHMYLMS_LESSON_CPT,
					'post_status' => 'draft',
					'post_title'  => 'Library fixture placed',
				)
			);
			$lesson_curriculum_posts[] = $lesson;
			$program                   = CourseProgram::defaults( $course );
			$program['mode']           = 'traditional';
			$program['items']          = array(
				array(
					'id'         => wp_generate_uuid4(),
					'type'       => 'lesson',
					'content_id' => $lesson,
					'chapter_id' => 0,
					'required'   => true,
					'skill_ids'  => array(),
				),
			);
			CourseProgram::save( $course, $program );
			$ids = array_column( Catalog::lessons( array( 'curriculum' => $root['id'] ) )['items'], 'id' );
			lesson_curriculum_check( in_array( $lesson, $ids, true ), 'Parent scopes include lessons used in descendant courses.' );
			lesson_curriculum_check( 0 === Catalog::lessons( array( 'curriculum' => $empty['id'] ) )['total'], 'An empty branch never falls back to all lessons.' );
			lesson_curriculum_check(
				0 === Catalog::lessons(
					array(
						'curriculum' => $root['id'],
						'status'     => 'publish',
					)
				)['total'],
				'Status filters apply inside the selected branch.'
			);
			lesson_curriculum_check(
				0 === Catalog::lessons(
					array(
						'curriculum' => $root['id'],
						'search'     => 'impossible lesson search',
					)
				)['total'],
				'Search applies inside the selected branch.'
			);
			$first = Catalog::lessons(
				array(
					'curriculum' => $root['id'],
					'per_page'   => 1,
				)
			);
			lesson_curriculum_check( 1 === $first['total'] && 1 === count( $first['items'] ), 'Pagination totals are computed after curriculum filtering.' );
			lesson_curriculum_check( is_wp_error( Catalog::lessons( array( 'curriculum' => 999999999 ) ) ), 'Missing scopes report an error.' );
			$request = new WP_REST_Request( 'GET', '/ohmylms/v1/content-hub/lessons' );
			$request->set_param( 'curriculum', $root['id'] );
			lesson_curriculum_check( 1 === rest_do_request( $request )->get_data()['total'], 'The REST route forwards the category filter.' );
			$skilled                   = wp_insert_post(
				array(
					'post_type'   => OHMYLMS_LESSON_CPT,
					'post_status' => 'draft',
					'post_title'  => 'Library fixture skill lesson',
				)
			);
			$lesson_curriculum_posts[] = $skilled;
			wp_set_object_terms( $skilled, array( $term['term_id'] ), Taxonomy::NAME );
			Links::add( $child['id'], 'skill', $term['term_id'] );
			$matches = Catalog::lessons(
				array(
					'curriculum' => $root['id'],
					'per_page'   => 1,
				)
			);
			lesson_curriculum_check( 2 === $matches['total'] && 2 === $matches['pages'] && 1 === count( $matches['items'] ), 'Skill-linked lessons are included before pagination.' );
			wp_set_current_user( 0 );
			lesson_curriculum_check( rest_do_request( $request )->get_status() >= 400, 'Anonymous users cannot browse the author library.' );
			throw new RuntimeException( 'lesson_curriculum_rollback' );
		}
	);
} catch ( RuntimeException $error ) {
	if ( 'lesson_curriculum_rollback' !== $error->getMessage() ) {
		throw $error;
	}
	echo "PASS: descendant scopes, empty branches, status/search, pagination, REST filters and authorization.\n";
} finally {
	foreach ( $lesson_curriculum_posts as $lesson_curriculum_post ) {
		clean_post_cache( $lesson_curriculum_post );
	}
}
