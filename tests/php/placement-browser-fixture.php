<?php
/**
 * Fixture for tests/browser/placement.spec.cjs. Disposable WordPress database only.
 * Usage: php placement-browser-fixture.php setup [tag] | state <tag> <state-json> | cleanup <tag> <state-json>
 * The state JSON is what setup printed; it is passed back in so no temporary file is needed.
 */
if (PHP_SAPI !== 'cli') { exit; }
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
$config = json_decode(file_get_contents(getenv('OHMYLMS_TEST_CREDENTIALS')), true);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8099';
require $config['site'] . '/wp-load.php';
if (!defined('OHMYLMS_TEST_SITE') || DB_NAME !== 'ohmylms_source_test') { throw new RuntimeException('Requires disposable test site'); }
require_once ABSPATH . 'wp-admin/includes/user.php';
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Placement;
use OhMyLMS\Curriculum\Schema;
use OhMyLMS\Bundles\Bundles;

global $wpdb;
$admin = get_user_by('login', $config['username']); wp_set_current_user($admin->ID);
$action = $argv[1] ?? ''; $tag = $argv[2] ?? strtolower(wp_generate_password(10, false, false));
if (!preg_match('/^[a-z0-9]{10}$/D', $tag)) { throw new RuntimeException('Invalid fixture tag'); }
$given = static function () use ($argv) { $state = json_decode($argv[3] ?? '', true); if (!is_array($state) || ($state['tag'] ?? '') !== ($argv[2] ?? null)) { throw new RuntimeException('Pass the state JSON that setup printed'); } return $state; };
$course = static function ($name, $status = 'publish', $author = 0) use (&$state) {
    $id = (int) wp_insert_post(['post_type' => OHMYLMS_COURSE_CPT, 'post_title' => $name, 'post_status' => $status, 'post_author' => $author ?: get_current_user_id()]);
    $state['posts'][] = $id; return $id;
};
/** Delete a post; for a course also its chapters, which wp_delete_post would leave behind. */
$purge = static function ($post_id) {
    global $wpdb;
    if (get_post_type($post_id) === OHMYLMS_COURSE_CPT) {
        foreach ($wpdb->get_col($wpdb->prepare("SELECT chapter_id FROM {$wpdb->prefix}ohmylms_chapter_relationship WHERE course_id=%d", $post_id)) as $chapter) {
            $wpdb->delete($wpdb->prefix . 'ohmylms_content_relationship', ['chapter_id' => (int) $chapter]);
            wp_delete_post((int) $chapter, true);
        }
        $wpdb->delete($wpdb->prefix . 'ohmylms_chapter_relationship', ['course_id' => $post_id]);
    }
    wp_delete_post($post_id, true);
};
$item = static function ($name, $parent = 0) use (&$state) { $created = Items::create(['name' => $name, 'item_type' => 'custom', 'parent_id' => $parent]); $state['items'][] = (int) $created['id']; return (int) $created['id']; };
if ($action === 'setup') {
    Schema::install();
    $state = ['tag' => $tag, 'posts' => [], 'items' => [], 'bundles' => [], 'terms' => [], 'users' => [], 'options' => ['ohmylms_archive_page_filters' => get_option('ohmylms_archive_page_filters', false), 'ohmylms_archive_page_filter_is_enabled' => get_option('ohmylms_archive_page_filter_is_enabled', false)]];
    $state['science'] = $item('Science ' . $tag); $state['physics'] = $item('Physics ' . $tag, $state['science']); $state['chemistry'] = $item('Chemistry ' . $tag, $state['science']);
    $state['arts'] = $item('Arts ' . $tag); $state['unused'] = $item('Unused ' . $tag);
    $state['physics_course'] = $course('Physics 101 ' . $tag); $state['chemistry_course'] = $course('Chemistry 101 ' . $tag); $state['arts_course'] = $course('Painting ' . $tag);
    $state['mine'] = $course('Author course ' . $tag, 'draft'); $state['plain'] = $course('Plain course ' . $tag);
    foreach ([[$state['physics_course'], $state['physics']], [$state['chemistry_course'], $state['chemistry']], [$state['arts_course'], $state['arts']]] as [$course_id, $item_id]) { Placement::set_items($course_id, [$item_id]); }
    $bundle = Bundles::create(['title' => 'Data career ' . $tag]); $state['bundle'] = (int) $bundle['id']; $state['bundles'][] = $state['bundle'];
    Bundles::add_course($state['bundle'], $state['physics_course']); Bundles::add_course($state['bundle'], $state['arts_course']); Bundles::set_status($state['bundle'], true);
    $draft = Bundles::create(['title' => 'Hidden path ' . $tag]); $state['draft_bundle'] = (int) $draft['id']; $state['bundles'][] = $state['draft_bundle'];
    Bundles::add_course($state['draft_bundle'], $state['chemistry_course']);
    // Old data: a category term, a course in it, and a plan whose rule still points at it.
    $term = wp_insert_term('Legacy category ' . $tag, 'course_category'); $state['legacy_term'] = (int) $term['term_id']; $state['terms'][] = [$state['legacy_term'], 'course_category'];
    $state['legacy_course'] = $course('Legacy course ' . $tag); wp_set_object_terms($state['legacy_course'], [$state['legacy_term']], 'course_category');
    $plan = wp_insert_post(['post_type' => 'ohmylms-membership', 'post_title' => 'Placement plan ' . $tag, 'post_status' => 'draft']); $state['posts'][] = (int) $plan; $state['plan'] = (int) $plan;
    $membership = ohmylms_get_membership($plan); $membership->set_regular_price('5'); $membership->set_course_categories([$state['legacy_term']]); $membership->set_course_curriculum([$state['science']]); $membership->save();
    // An author who may edit only their own course, and a public page with the course list and filters.
    $state['author_login'] = 'place-author-' . $tag; $state['author_password'] = wp_generate_password(24, false, false);
    $state['author'] = wp_create_user($state['author_login'], $state['author_password'], $state['author_login'] . '@example.invalid'); (new WP_User($state['author']))->set_role('author');
    wp_update_post(['ID' => $state['mine'], 'post_author' => $state['author']]);
    update_option('ohmylms_archive_page_filters', ['category', 'tag']); update_option('ohmylms_archive_page_filter_is_enabled', 'yes');
    $state['page'] = (int) wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Courses ' . $tag, 'post_name' => 'courses-' . $tag, 'post_content' => '[ohmylms_course_list show_filter="yes" layout_style="grid-style1" posts_per_page="50"]']);
    $state['page_url'] = get_permalink($state['page']);
    echo wp_json_encode($state), "\n";
} elseif ($action === 'state') {
    $state = $given();
    $out = ['items' => [], 'bundles' => [], 'plan' => ohmylms_get_membership($state['plan'])->get_course_curriculum('edit'), 'plan_bundles' => ohmylms_get_membership($state['plan'])->get_course_bundles('edit')];
    foreach (['physics_course', 'chemistry_course', 'arts_course', 'mine', 'plain'] as $key) { $out['items'][$key] = Placement::item_ids($state[$key]); $out['bundles'][$key] = Placement::bundle_ids($state[$key]); }
    $out['legacy_rules'] = ohmylms_get_membership($state['plan'])->get_course_categories('edit');
    $out['legacy_terms'] = wp_get_object_terms($state['legacy_course'], 'course_category', ['fields' => 'ids']);
    echo wp_json_encode($out), "\n";
} elseif ($action === 'cleanup') {
    $state = $given();
    foreach ($wpdb->get_col($wpdb->prepare('SELECT id FROM ' . Bundles::table() . ' WHERE title LIKE %s', '%' . $wpdb->esc_like($tag) . '%')) as $id) { Bundles::delete((int) $id, true); }
    foreach (array_reverse($wpdb->get_col($wpdb->prepare('SELECT id FROM ' . Items::table() . ' WHERE name LIKE %s ORDER BY id', '%' . $wpdb->esc_like($tag) . '%'))) as $id) { if (Items::get((int) $id)) { Items::delete((int) $id, 'delete', true); } }
    foreach ($state['posts'] as $post) { $purge($post); }
    wp_delete_post($state['page'], true);
    foreach ($state['terms'] as [$term, $taxonomy]) { wp_delete_term($term, $taxonomy); }
    wp_delete_user($state['author']);
    foreach ($state['options'] as $option => $value) { $value === false ? delete_option($option) : update_option($option, $value); }
    echo "Cleaned placement fixture\n";
}
