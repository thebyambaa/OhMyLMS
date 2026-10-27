<?php
/**
 * Curriculum items and Course Bundles in place of course categories and tags: placing a course,
 * the course list filter, public filters and templates, shortcodes, memberships, duplicate / export /
 * import, other-LMS imports, the setup wizard, and the legacy terms that are deliberately left alone.
 * Disposable WordPress database only: configured through OHMYLMS_TEST_CREDENTIALS.
 * Names used below are made up to illustrate shapes.
 */
if (PHP_SAPI !== 'cli') { exit; }
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
$config = json_decode(file_get_contents(getenv('OHMYLMS_TEST_CREDENTIALS')), true);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8099';
require $config['site'] . '/wp-load.php';
if (!defined('OHMYLMS_TEST_SITE') || DB_NAME !== 'ohmylms_source_test') { throw new RuntimeException('Requires disposable test site'); }
require_once ABSPATH . 'wp-admin/includes/user.php';

use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Links;
use OhMyLMS\Curriculum\Placement;
use OhMyLMS\Curriculum\Schema;
use OhMyLMS\Exporters\CourseExporter;
use OhMyLMS\Importers\CourseImporter;
use OhMyLMS\Membership\CourseSelection;
use OhMyLMS\Bundles\Bundles;

$checks = 0; $posts = []; $users = []; $legacy_terms = []; $bundle_ids = []; $item_ids = []; $plans = [];
$admin = get_user_by('login', $config['username'])->ID;
$tag = wp_generate_password(5, false, false);
$previous_filters = get_option('ohmylms_archive_page_filters', false);
function ok($condition, $message) { global $checks; if (!$condition) { throw new RuntimeException($message); } $checks++; }
function api($method, $path, $body = [], $query = []) {
    $request = new WP_REST_Request($method, '/ohmylms/v1/' . $path);
    if ($query) { $request->set_query_params($query); }
    if ($body) { $request->set_header('Content-Type', 'application/json'); $request->set_body(wp_json_encode($body)); }
    return rest_do_request($request);
}
function code($response) { return $response->get_data()['code'] ?? ''; }
function course($name, $status = 'publish', $author = 0) {
    global $posts;
    $id = wp_insert_post(['post_type' => OHMYLMS_COURSE_CPT, 'post_title' => $name, 'post_status' => $status, 'post_author' => $author ?: get_current_user_id()]);
    ok($id && !is_wp_error($id), 'Course creation failed'); $posts[] = (int) $id; return (int) $id;
}
function item($name, $parent = 0) {
    global $item_ids;
    $created = Items::create(['name' => $name, 'item_type' => 'custom', 'parent_id' => $parent]);
    ok(!is_wp_error($created), 'Item creation failed'); $item_ids[] = (int) $created['id']; return (int) $created['id'];
}
function bundle($title) {
    global $bundle_ids;
    $created = Bundles::create(['title' => $title]);
    ok(!is_wp_error($created), 'Bundle creation failed'); $bundle_ids[] = (int) $created['id']; return (int) $created['id'];
}
function user($role) {
    global $users;
    $id = wp_create_user('place-' . wp_generate_password(9, false, false), wp_generate_password(24), 'place-' . wp_generate_password(9, false, false) . '@example.invalid');
    (new WP_User($id))->set_role($role); $users[] = $id; return $id;
}
/** Delete a post; for a course also its chapters, which wp_delete_post would leave behind. */
function purge($post_id) {
    global $wpdb;
    if (get_post_type($post_id) === OHMYLMS_COURSE_CPT) {
        foreach ($wpdb->get_col($wpdb->prepare("SELECT chapter_id FROM {$wpdb->prefix}ohmylms_chapter_relationship WHERE course_id=%d", $post_id)) as $chapter) {
            $wpdb->delete($wpdb->prefix . 'ohmylms_content_relationship', ['chapter_id' => (int) $chapter]);
            wp_delete_post((int) $chapter, true);
        }
        $wpdb->delete($wpdb->prefix . 'ohmylms_chapter_relationship', ['course_id' => $post_id]);
    }
    wp_delete_post($post_id, true);
}
function ids_of($response) { return array_map('intval', array_column($response->get_data(), 'id')); }
function same($a, $b) { sort($a); sort($b); return array_values($a) === array_values($b); }
function render($template, array $args = []) { ob_start(); ohmylms_get_template($template, $args); return ob_get_clean(); }

try {
    wp_set_current_user($admin);
    Schema::install(); ok(Schema::ready(), 'Curriculum tables unavailable');
    global $wpdb;
    $start = ['items' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Items::table()), 'bundles' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Bundles::table()), 'links' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Links::table()), 'members' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Bundles::members_table())];

    // ---- Fixtures: a small curriculum, courses, and bundles -------------------------------------
    $science = item('Science ' . $tag); $physics = item('Physics ' . $tag, $science); $chemistry = item('Chemistry ' . $tag, $science);
    $arts = item('Arts ' . $tag); $unused = item('Unused ' . $tag); $drafty = item('Only a draft ' . $tag);
    $c_physics = course('Physics 101 ' . $tag); $c_chemistry = course('Chemistry 101 ' . $tag); $c_arts = course('Painting ' . $tag);
    $c_draft = course('Draft course ' . $tag, 'draft'); $c_plain = course('Plain course ' . $tag);
    ok(Placement::set_items($c_physics, [$physics]) === true && Placement::set_items($c_chemistry, [$chemistry]) === true && Placement::set_items($c_arts, [$arts]) === true && Placement::set_items($c_draft, [$drafty, $physics]) === true, 'Placement failed');
    $b_pub = bundle('Data career ' . $tag); $b_draft = bundle('Hidden path ' . $tag); $b_items = bundle('By curriculum ' . $tag);
    ok(Bundles::add_course($b_pub, $c_physics) === true && Bundles::add_course($b_pub, $c_arts) === true && !is_wp_error(Bundles::set_status($b_pub, true)), 'Bundle setup failed');
    ok(Bundles::add_course($b_draft, $c_chemistry) === true, 'Draft bundle setup failed');
    ok(!is_wp_error(Bundles::set_members($b_items, [['type' => 'curriculum', 'id' => $arts]])), 'Bundle with a curriculum member failed');

    // ---- A course's organization: read, change, validate ----------------------------------------
    $read = api('GET', "courses/$c_physics/organization");
    $data = $read->get_data();
    ok($read->get_status() === 200 && count($data['curriculum']) === 1 && $data['curriculum'][0]['id'] === $physics && $data['curriculum'][0]['path'] === ['Science ' . $tag] && $data['curriculum'][0]['slug'] === "c$physics" && $data['can_manage_bundles'] === true, 'Organization read failed');
    ok(array_column($data['bundles'], 'id') === [$b_pub] && $data['bundles'][0]['slug'] === "b$b_pub", 'A course lists the bundles it belongs to');
    $before_links = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Links::table());
    $put = api('PUT', "courses/$c_plain/organization", ['curriculum_ids' => [$chemistry, $chemistry, (string) $arts]]);
    ok($put->get_status() === 200 && same(array_column($put->get_data()['curriculum'], 'id'), [$chemistry, $arts]), 'Curriculum placement not saved or not de-duplicated');
    $again = api('PUT', "courses/$c_plain/organization", ['curriculum_ids' => [$arts, $chemistry]]);
    ok($again->get_status() === 200 && (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Links::table()) === $before_links + 2, 'Repeating a placement created duplicate links');
    $put = api('PUT', "courses/$c_plain/organization", ['curriculum_ids' => [$arts]]);
    ok(array_column($put->get_data()['curriculum'], 'id') === [$arts] && !in_array($c_plain, Links::object_ids([$chemistry], 'course'), true), 'Removing a placement failed');
    foreach ([['curriculum_ids' => 'x'], ['bundle_ids' => 'x'], ['curriculum_ids' => [999999]], ['bundle_ids' => [999999]]] as $bad) {
        $response = api('PUT', "courses/$c_plain/organization", $bad);
        ok($response->get_status() === 400, 'Invalid organization accepted: ' . wp_json_encode($bad));
    }
    ok(array_column(api('GET', "courses/$c_plain/organization")->get_data()['curriculum'], 'id') === [$arts], 'A rejected request changed the course');
    ok(api('GET', 'courses/999999/organization')->get_status() >= 400, 'A missing course was readable');

    // ---- Course Bundles: administrators only, and a published bundle keeps a member -------------
    $put = api('PUT', "courses/$c_plain/organization", ['bundle_ids' => [$b_pub, $b_draft]]);
    ok($put->get_status() === 200 && same(array_column($put->get_data()['bundles'], 'id'), [$b_pub, $b_draft]), 'An administrator could not place a course in bundles');
    $put = api('PUT', "courses/$c_plain/organization", ['bundle_ids' => [$b_draft]]);
    ok($put->get_status() === 200 && array_column($put->get_data()['bundles'], 'id') === [$b_draft], 'Leaving a bundle failed');
    $lonely = course('Lonely member ' . $tag); $t_lonely = bundle('Single member ' . $tag);
    Bundles::add_course($t_lonely, $lonely); Bundles::set_status($t_lonely, true);
    $blocked = api('PUT', "courses/$lonely/organization", ['bundle_ids' => []]);
    ok($blocked->get_status() === 409 && code($blocked) === 'ohmylms_bundle_empty' && Placement::bundle_ids($lonely) === [$t_lonely], 'A published bundle lost its only member');
    $author = user('author'); $other_author = user('author'); $subscriber = user('subscriber');
    $own = course('Own course ' . $tag, 'draft', $author);
    wp_set_current_user($author);
    $put = api('PUT', "courses/$own/organization", ['curriculum_ids' => [$science]]);
    ok($put->get_status() === 200 && $put->get_data()['can_manage_bundles'] === false && array_column($put->get_data()['curriculum'], 'id') === [$science], 'An author could not place their own course in the curriculum');
    $put = api('PUT', "courses/$own/organization", ['bundle_ids' => [$b_pub]]);
    ok($put->get_status() === 403 && Placement::bundle_ids($own) === [], 'An author changed the course bundles of a course');
    $put = api('PUT', "courses/$own/organization", ['curriculum_ids' => [$science, $arts], 'bundle_ids' => []]);
    ok($put->get_status() === 200 && same(array_column($put->get_data()['curriculum'], 'id'), [$science, $arts]), 'Resending the unchanged bundles must not be refused');
    ok(api('GET', 'curriculum/outline')->get_status() === 200 && api('GET', 'bundles/outline')->get_status() === 200, 'An author could not read the outlines');
    wp_set_current_user($other_author);
    ok(api('PUT', "courses/$own/organization", ['curriculum_ids' => [$unused]])->get_status() === 403 && api('GET', "courses/$own/organization")->get_status() === 403, "Another author reached someone else's course");
    wp_set_current_user($subscriber);
    ok(api('GET', 'curriculum/outline')->get_status() === 403 && api('GET', 'bundles/outline')->get_status() === 403 && api('GET', "courses/$c_physics/organization")->get_status() === 403, 'A learner reached the organization API');
    wp_set_current_user(0);
    ok(in_array(api('GET', 'curriculum/outline')->get_status(), [401, 403], true) && in_array(api('PUT', "courses/$c_physics/organization", ['curriculum_ids' => []])->get_status(), [401, 403], true), 'An anonymous visitor reached the organization API');
    wp_set_current_user($admin);
    // The organization checks above used these courses; clear them so later checks see only the fixtures.
    Placement::set_items($c_plain, []); Placement::set_items($own, []);

    // ---- Outlines for pickers ------------------------------------------------------------------
    $outline = api('GET', 'curriculum/outline')->get_data()['items'];
    $by_id = []; foreach ($outline as $row) { $by_id[$row['id']] = $row; }
    ok(isset($by_id[$physics]) && $by_id[$physics]['parent_id'] === $science && $by_id[$physics]['name'] === 'Physics ' . $tag && !array_key_exists('courses', $by_id[$physics]), 'Curriculum outline shape');
    $with = []; foreach (api('GET', 'curriculum/outline', [], ['courses' => 1])->get_data()['items'] as $row) { $with[$row['id']] = $row; }
    ok(array_column($with[$physics]['courses'], 'id') === [$c_physics] && $with[$unused]['courses'] === [], 'The outline lists published courses only, per item');
    $bundles_outline = []; foreach (api('GET', 'bundles/outline')->get_data()['bundles'] as $row) { $bundles_outline[$row['id']] = $row; }
    ok($bundles_outline[$b_pub]['status'] === 'published' && $bundles_outline[$b_draft]['status'] === 'draft', 'Bundle outline shows drafts too, with their status');

    // ---- Course list filter in the admin ------------------------------------------------------
    $list = static function (array $query) { return ids_of(api('GET', 'courses', [], $query + ['per_page' => 100])); };
    $in_science = $list(['curriculum_id' => $science]);
    ok(in_array($c_physics, $in_science, true) && in_array($c_chemistry, $in_science, true) && !in_array($c_arts, $in_science, true) && !in_array($c_plain, $in_science, true), 'Filtering by a curriculum item must include everything beneath it');
    ok(same(array_intersect($list(['category_id' => $science]), [$c_physics, $c_chemistry, $c_arts, $c_plain]), array_intersect($in_science, [$c_physics, $c_chemistry, $c_arts, $c_plain])), 'The old category_id parameter means a curriculum item');
    $in_bundle = $list(['bundle_id' => $b_pub]);
    ok(in_array($c_physics, $in_bundle, true) && in_array($c_arts, $in_bundle, true) && !in_array($c_chemistry, $in_bundle, true) && same(array_intersect($list(['tag_id' => $b_pub]), [$c_physics, $c_arts, $c_chemistry]), [$c_physics, $c_arts]), 'Filtering by a course bundle (and the old tag_id parameter)');
    ok(in_array($c_arts, $list(['bundle_id' => $b_items]), true) && !in_array($c_physics, $list(['bundle_id' => $b_items]), true), 'A bundle with a curriculum member contains the courses under it');
    $both = $list(['curriculum_id' => $science, 'bundle_id' => $b_pub]);
    ok(in_array($c_physics, $both, true) && !in_array($c_arts, $both, true) && !in_array($c_chemistry, $both, true), 'Both filters must match');
    $none = $list(['curriculum_id' => 999999]);
    ok(!array_intersect($none, [$c_physics, $c_chemistry, $c_arts, $c_plain, $c_draft]), 'A filter that matches nothing must not show everything');
    $empty_item = $list(['curriculum_id' => $unused]);
    ok(!array_intersect($empty_item, [$c_physics, $c_chemistry, $c_arts, $c_plain]), 'An item with no courses lists none');

    // ---- Course payload keeps the keys the admin app reads ------------------------------------
    $course_data = api('GET', "courses/$c_physics")->get_data();
    ok($course_data['categories'] === [['id' => $physics, 'name' => 'Physics ' . $tag, 'slug' => "c$physics"]] && array_column($course_data['tags'], 'id') === [$b_pub] && $course_data['tags'][0]['slug'] === "b$b_pub", 'Course categories and tags now carry curriculum items and bundles');
    ok($course_data['curriculum'][0]['path'] === ['Science ' . $tag] && array_column($course_data['learning_bundles'], 'title') === ['Data career ' . $tag], 'Course payload has curriculum and course bundles');
    $legacy_term = wp_insert_term('Legacy category ' . $tag, 'course_category'); $legacy_terms[] = [(int) $legacy_term['term_id'], 'course_category'];
    $legacy_tag = wp_insert_term('Legacy tag ' . $tag, 'course_tag'); $legacy_terms[] = [(int) $legacy_tag['term_id'], 'course_tag'];
    $c_legacy = course('Legacy course ' . $tag);
    wp_set_object_terms($c_legacy, [(int) $legacy_term['term_id']], 'course_category'); wp_set_object_terms($c_legacy, [(int) $legacy_tag['term_id']], 'course_tag');
    $legacy_payload = api('GET', "courses/$c_legacy")->get_data();
    ok($legacy_payload['categories'] === [] && $legacy_payload['tags'] === [], 'Old terms must not appear as the new curriculum or bundles');

    // ---- Old endpoints: readable in the new shape, never writable --------------------------------
    $categories = api('GET', 'categories');
    $row = null; foreach ($categories->get_data() as $candidate) { if ($candidate['term_id'] === $physics) { $row = $candidate; } }
    ok($categories->get_status() === 200 && $row && $row['id'] === $physics && $row['name'] === 'Physics ' . $tag && $row['slug'] === "c$physics" && $row['parent'] === $science && array_column($row['courses'], 'id') === [$c_physics, $c_draft], '/categories lists curriculum items shaped like terms');
    $tags = api('GET', 'tags', [], ['search' => 'Data career']);
    ok($tags->get_status() === 200 && array_column($tags->get_data(), 'term_id') === [$b_pub] && $tags->get_data()[0]['slug'] === "b$b_pub", '/tags lists course bundles shaped like terms, and can search');
    foreach ([['POST', 'categories'], ['PUT', "categories/$science"], ['DELETE', "categories/$science"], ['DELETE', 'categories/bulk'], ['POST', 'tags'], ['PUT', "tags/$b_pub"], ['DELETE', "tags/$b_pub"], ['DELETE', 'tags/bulk']] as [$method, $path]) {
        $response = api($method, $path, ['name' => 'x', 'ids' => [1]]);
        ok($response->get_status() === 410 && in_array(code($response), ['ohmylms_categories_replaced', 'ohmylms_tags_replaced'], true), "$method $path should be gone (got " . $response->get_status() . ')');
    }
    ok(Items::get($science) && Bundles::get($b_pub), 'A refused delete removed something');
    $assign = api('POST', "courses/$c_plain/terms", ['taxonomy' => 'course_category', 'terms' => [(int) $legacy_term['term_id']]]);
    ok($assign->get_status() === 410 && code($assign) === 'ohmylms_taxonomy_replaced' && wp_get_object_terms($c_plain, 'course_category') === [], 'Assigning category terms to a course must be refused');
    $update = api('POST', "courses/$c_plain", ['name' => 'Plain course ' . $tag, 'categories' => [['id' => (int) $legacy_term['term_id']]], 'tags' => [['id' => (int) $legacy_tag['term_id']]]]);
    ok(wp_get_object_terms($c_plain, 'course_category') === [] && wp_get_object_terms($c_plain, 'course_tag') === [], 'Saving a course with categories or tags must not write terms');

    // ---- Placement helpers ---------------------------------------------------------------------
    ok(Placement::ids_from_slugs(["c$physics", "b$b_pub", 'c', 'c12x', 'science', "c$arts"], 'item') === [$physics, $arts] && Placement::ids_from_slugs(["c$physics", "b$b_pub"], 'bundle') === [$b_pub], 'Slugs are parsed by kind and nothing else is accepted');
    ok(Placement::ids_from_list("$physics, c$arts ,b$b_pub", 'item') === [$physics, $arts] && Placement::ids_from_list("$b_pub,b$b_items", 'bundle') === [$b_pub, $b_items] && Placement::ids_from_list('', 'item') === [] && Placement::ids_from_list('nonsense', 'item') === [0] && Placement::ids_from_list([$physics, 'c' . $arts], 'item') === [$physics, $arts], 'Shortcode lists: ids, slugs, empty and invalid values');
    $narrowed = Placement::narrow_query(['post_type' => OHMYLMS_COURSE_CPT], [$science]);
    ok(same($narrowed['post__in'], [$c_physics, $c_chemistry, $c_draft]), 'narrow_query by item');
    ok(same(Placement::narrow_query(['post_type' => 'x'], [$science], [$b_pub])['post__in'], [$c_physics]), 'narrow_query intersects the two groups');
    ok(same(Placement::narrow_query(['post__in' => [$c_physics, $c_arts]], [$science])['post__in'], [$c_physics]), 'narrow_query respects an existing post__in');
    ok(Placement::narrow_query(['post_type' => 'x'], [$unused])['post__in'] === [0] && Placement::narrow_query(['post_type' => 'x'], [0])['post__in'] === [0], 'narrow_query with no match must match nothing');
    ok(Placement::narrow_query(['post_type' => 'x']) === ['post_type' => 'x'], 'narrow_query without groups changes nothing');
    $query = new WP_Query(Placement::narrow_query(['post_type' => OHMYLMS_COURSE_CPT, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids'], [$science]));
    ok(same($query->posts, [$c_physics, $c_chemistry]), 'WP_Query only returns the published courses of the item');

    // ---- Public lists: only what has published courses -----------------------------------------
    $public = []; foreach (Placement::public_items() as $row) { $public[$row['id']] = $row; }
    ok(isset($public[$science], $public[$physics], $public[$chemistry], $public[$arts]) && !isset($public[$unused]) && !isset($public[$drafty]), 'Public items hide empty items and items with only draft courses');
    ok($public[$science]['count'] === 2 && $public[$physics]['count'] === 1 && $public[$physics]['depth'] === 1 && $public[$science]['depth'] === 0 && $public[$physics]['slug'] === "c$physics", 'Public item counts, depth and slugs');
    $order = array_column(Placement::public_items(), 'id');
    ok(array_search($science, $order, true) < array_search($physics, $order, true) && array_search($physics, $order, true) < array_search($arts, $order, true), 'Public items come in tree order');
    $public_bundles = []; foreach (Placement::public_bundles() as $row) { $public_bundles[$row['id']] = $row; }
    ok(isset($public_bundles[$b_pub]) && $public_bundles[$b_pub]['count'] === 2 && $public_bundles[$b_pub]['slug'] === "b$b_pub" && !isset($public_bundles[$b_draft]), 'Public bundles are published with published courses');
    ok(Placement::item_options()["c$physics"] === '— Physics ' . $tag && isset(Placement::bundle_options()["b$b_pub"]) && !isset(Placement::bundle_options()["b$b_draft"]), 'Builder pickers: tree-ordered items, published bundles');

    // ---- Course lists by slug (carousel, popup and AJAX filters) ---------------------------------
    foreach (['ohmylms_get_recent_course_ids', 'ohmylms_get_best_selling_course_ids', 'ohmylms_get_top_rated_course_ids', 'ohmylms_get_top_reviewed_course_ids', 'ohmylms_get_free_course_ids', 'ohmylms_get_paid_course_ids'] as $function) {
        foreach ([null, 'all', "c$science", "b$b_pub", 'legacy-slug'] as $group) {
            $wpdb->last_error = ''; $found = $function($group);
            ok(is_array($found) && $wpdb->last_error === '', "$function(" . wp_json_encode($group) . ') failed: ' . $wpdb->last_error);
        }
    }
    $recent = array_map('intval', ohmylms_get_recent_course_ids("c$science"));
    ok(in_array($c_physics, $recent, true) && in_array($c_chemistry, $recent, true) && !in_array($c_arts, $recent, true) && !in_array($c_draft, $recent, true), 'Recent courses of a curriculum item (published only)');
    ok(same(array_intersect(array_map('intval', ohmylms_get_recent_course_ids("b$b_pub")), [$c_physics, $c_arts, $c_chemistry]), [$c_physics, $c_arts]) && ohmylms_get_recent_course_ids('legacy-slug') === [] && ohmylms_get_recent_course_ids("c$unused") === [], 'Recent courses of a bundle; unknown slugs and empty items list none');
    ok(in_array($c_plain, array_map('intval', ohmylms_get_recent_course_ids('all'))) && in_array($c_physics, array_map('intval', ohmylms_get_recent_course_ids(null))), 'No group lists every published course');
    update_post_meta($c_physics, '_price_type', 'free'); update_post_meta($c_chemistry, '_price_type', 'paid'); update_post_meta($c_arts, '_price_type', 'free');
    $free = array_map('intval', ohmylms_get_free_course_ids("c$science")); $paid = array_map('intval', ohmylms_get_paid_course_ids("c$science"));
    ok(in_array($c_physics, $free, true) && !in_array($c_chemistry, $free, true) && !in_array($c_arts, $free, true) && in_array($c_chemistry, $paid, true) && !in_array($c_physics, $paid, true), 'Free and paid lists stay within the selected item');
    ok(ohmylms_course_ids_for_group(null) === null && ohmylms_course_ids_for_group('all') === null && ohmylms_course_ids_for_group('') === null && ohmylms_course_ids_for_group('nope') === [] && same(ohmylms_course_ids_for_group("c$arts"), [$c_arts]) && same(ohmylms_course_ids_for_group("b$b_pub"), [$c_physics, $c_arts]), 'Group slugs resolve to course IDs');

    // ---- Public filter templates --------------------------------------------------------------
    update_option('ohmylms_archive_page_filters', ['category', 'tag']);
    $html = render('filters/filters.php', ['atts' => ['show_filter' => 'yes']]);
    ok(strpos($html, 'data-slug="c' . $physics . '"') !== false && strpos($html, '— Physics ' . $tag) !== false && strpos($html, 'data-slug="b' . $b_pub . '"') !== false && strpos($html, 'Data career ' . $tag) !== false, 'Filters list curriculum items and bundles with their slugs');
    ok(strpos($html, 'Unused ' . $tag) === false && strpos($html, 'Only a draft ' . $tag) === false && strpos($html, 'Hidden path ' . $tag) === false && strpos($html, 'Legacy category ' . $tag) === false && strpos($html, 'Legacy tag ' . $tag) === false, 'Filters never list empty items, draft bundles or the old terms');
    ok(strpos($html, 'Curriculum') !== false && strpos($html, 'Course bundle') !== false && strpos($html, '>Category<') === false, 'Filter headings use the new names');
    $tabs = render('filters/category-type-button.php', ['is_enable_category' => 'yes']);
    ok(strpos($tabs, 'data-slug="c' . $arts . '"') !== false && strpos($tabs, 'data-slug="all"') !== false && strpos($tabs, 'Unused ' . $tag) === false && strpos($tabs, 'Legacy category') === false, 'Curriculum tabs');
    ok(render('filters/category-type-button.php', ['is_enable_category' => 'no']) === '', 'Tabs stay off when disabled');

    // ---- Shortcode ----------------------------------------------------------------------------
    $page = do_shortcode('[ohmylms_course_list curriculum="c' . $arts . '" posts_per_page="50"]');
    ok(strpos($page, 'Painting ' . $tag) !== false && strpos($page, 'Physics 101 ' . $tag) === false && strpos($page, 'Chemistry 101 ' . $tag) === false, 'Shortcode curriculum attribute');
    $page = do_shortcode('[ohmylms_course_list bundle="' . $b_pub . '" posts_per_page="50"]');
    ok(strpos($page, 'Painting ' . $tag) !== false && strpos($page, 'Physics 101 ' . $tag) !== false && strpos($page, 'Chemistry 101 ' . $tag) === false, 'Shortcode bundle attribute');
    $page = do_shortcode('[ohmylms_course_list curriculum="oops" posts_per_page="50"]');
    ok(strpos($page, 'Painting ' . $tag) === false && strpos($page, 'Physics 101 ' . $tag) === false, 'A mistyped list shows no courses instead of all of them');
    $page = do_shortcode('[ohmylms_course_list category="c' . $arts . '" posts_per_page="50"]');
    ok(strpos($page, 'Physics 101 ' . $tag) !== false && strpos($page, 'Painting ' . $tag) !== false, 'The old category attribute is ignored');

    // ---- Membership plans ---------------------------------------------------------------------
    $ids = static function ($rows) { return array_map('intval', array_column($rows, 'id')); };
    ok(same($ids(CourseSelection::resolve([], [], [], [], [$science], [])), [$c_physics, $c_chemistry]), 'A plan with a curriculum item includes everything beneath it (published only)');
    ok(same($ids(CourseSelection::resolve([], [], [], [], [], [$b_pub])), [$c_physics, $c_arts]) && same($ids(CourseSelection::resolve([], [], [], [], [], [$b_items])), [$c_arts]), 'A plan with a bundle includes its courses and the courses under its curriculum members');
    ok(same($ids(CourseSelection::resolve([], [], [], [$c_physics], [$science], [$b_pub])), [$c_chemistry, $c_arts]), 'Exclusions override curriculum and bundle rules');
    ok(same($ids(CourseSelection::resolve([['id' => $c_plain]], [(int) $legacy_term['term_id']], [(int) $legacy_tag['term_id']], [], [$arts], [])), [$c_plain, $c_legacy, $c_arts]), 'Old category and tag rules still resolve, together with the new ones');
    ok(has_action('ohmylms_curriculum_changed', [CourseSelection::class, 'queue']) !== false && has_action('ohmylms_curriculum_items_deleted', [CourseSelection::class, 'queue']) !== false, 'Plans resync when placement changes');
    $created = api('POST', 'membership', ['name' => 'Placement plan ' . $tag, 'status' => 'draft', 'regular_price' => '10', 'course_curriculum' => [$science], 'course_bundles' => [$b_pub]]);
    ok($created->get_status() === 201, 'Plan create failed: ' . wp_json_encode($created->get_data()));
    $plan_id = (int) $created->get_data()['id']; $plans[] = $plan_id;
    $plan = $created->get_data();
    ok($plan['course_curriculum'] === [$science] && $plan['course_bundles'] === [$b_pub] && $plan['legacy_rules'] === ['categories' => [], 'tags' => []], 'The plan stores curriculum and bundle rules');
    foreach ([['course_curriculum' => [999999]], ['course_bundles' => [999999]], ['course_curriculum' => 'x'], ['course_bundles' => [-1]]] as $bad) {
        $response = api('PUT', "membership/$plan_id", $bad);
        ok($response->get_status() === 400, 'Invalid plan rule accepted: ' . wp_json_encode($bad));
    }
    $stored = ohmylms_get_membership($plan_id);
    $stored->set_course_categories([(int) $legacy_term['term_id']]); $stored->set_course_tags([(int) $legacy_tag['term_id']]); $stored->save();
    $reopened = api('GET', "membership/$plan_id")->get_data();
    ok($reopened['legacy_rules']['categories'] === [['id' => (int) $legacy_term['term_id'], 'name' => 'Legacy category ' . $tag]] && $reopened['legacy_rules']['tags'] === [['id' => (int) $legacy_tag['term_id'], 'name' => 'Legacy tag ' . $tag]], 'Old rules are listed by name');
    $other_term = wp_insert_term('Another legacy ' . $tag, 'course_category'); $legacy_terms[] = [(int) $other_term['term_id'], 'course_category'];
    $extend = api('PUT', "membership/$plan_id", ['course_categories' => [(int) $legacy_term['term_id'], (int) $other_term['term_id']]]);
    ok($extend->get_status() === 400 && code($extend) === 'membership_legacy_rules', 'Old rules can only be removed, never added to');
    ok(ohmylms_get_membership($plan_id)->get_course_categories('edit') === [(int) $legacy_term['term_id']], 'A refused request changed the plan');
    $keep = api('PUT', "membership/$plan_id", ['course_categories' => [(int) $legacy_term['term_id']], 'course_curriculum' => [$science, $arts]]);
    ok($keep->get_status() === 200 && $keep->get_data()['course_curriculum'] === [$science, $arts] && $keep->get_data()['legacy_rules']['categories'][0]['id'] === (int) $legacy_term['term_id'], 'Keeping an old rule while editing the new ones');
    $remove = api('PUT', "membership/$plan_id", ['course_categories' => [], 'course_tags' => []]);
    ok($remove->get_status() === 200 && $remove->get_data()['legacy_rules'] === ['categories' => [], 'tags' => []], 'Old rules can be removed');
    $preview = api('POST', 'membership/course-preview', ['course_curriculum' => [$science], 'course_bundles' => [$b_pub], 'excluded_courses' => [$c_chemistry]]);
    $preview_rows = []; foreach ($preview->get_data()['courses'] as $row) { $preview_rows[$row['id']] = $row['reasons']; }
    ok($preview->get_status() === 200 && same(array_keys($preview_rows), [$c_physics, $c_arts]) && in_array('Physics ' . $tag, $preview_rows[$c_physics], true) && in_array('Data career ' . $tag, $preview_rows[$c_arts], true), 'The preview says which item or bundle included each course: ' . wp_json_encode($preview->get_data()));
    $with_legacy = api('POST', 'membership/course-preview', ['course_categories' => [(int) $legacy_term['term_id']]]);
    ok(in_array($c_legacy, array_column($with_legacy->get_data()['courses'], 'id'), true), 'The preview counts old rules, so it matches what members get');

    // ---- Duplicating, exporting and importing a course ------------------------------------------
    wp_set_current_user($admin);
    $copy_id = (new \OhMyLMS\Duplicate\Course($c_physics))->duplicate(); $posts[] = (int) $copy_id;
    ok(Placement::item_ids($copy_id) === [$physics] && Placement::bundle_ids($copy_id) === [], 'A duplicate keeps the curriculum placement but not the course bundles');
    $legacy_copy = (new \OhMyLMS\Duplicate\Course($c_legacy))->duplicate(); $posts[] = (int) $legacy_copy;
    ok(wp_get_object_terms($legacy_copy, 'course_category') === [] && wp_get_object_terms($legacy_copy, 'course_tag') === [], 'A duplicate does not carry old category or tag terms');
    $export = new ReflectionMethod(CourseExporter::class, 'get_course_data'); $export->setAccessible(true);
    $exported = $export->invoke(new CourseExporter([$c_physics]), $c_physics);
    ok($exported['curriculum'] === [['Science ' . $tag, 'Physics ' . $tag]] && $exported['learning_bundles'] === ['Data career ' . $tag] && !in_array('course_category', array_column($exported['terms'], 'taxonomy'), true), 'Export lists curriculum paths and course bundles instead of categories and tags');
    $import = new ReflectionMethod(CourseImporter::class, 'create_course'); $import->setAccessible(true);
    $importer = new CourseImporter(['tmp_name' => '', 'type' => 'application/json']);
    $imported = $import->invoke($importer, array_merge($exported, ['title' => 'Imported ' . $tag, 'status' => 'draft'])); $posts[] = (int) $imported;
    ok(Placement::item_ids($imported) === [$physics] && array_column(Placement::bundles($imported), 'id') === [$b_pub], 'Import restores curriculum and bundles, reusing what exists');
    $counts = [(int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Items::table()), (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Bundles::table())];
    $old_file = ['id' => 1, 'title' => 'Old export ' . $tag, 'status' => 'draft', 'contents' => [], 'meta' => [], 'terms' => [['taxonomy' => 'course_category', 'term' => 'Imported topic ' . $tag, 'slug' => 'imported-topic'], ['taxonomy' => 'course_tag', 'term' => 'Imported tag ' . $tag, 'slug' => 'imported-tag'], ['taxonomy' => 'course_tag', 'term' => 'Imported tag ' . $tag, 'slug' => 'imported-tag']]];
    $from_old = $import->invoke($importer, $old_file); $posts[] = (int) $from_old;
    $imported_item = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Items::table() . ' WHERE name=%s', 'Imported topic ' . $tag)); $item_ids[] = $imported_item;
    $imported_bundle = Bundles::find_by_title('Imported tag ' . $tag); $bundle_ids[] = (int) $imported_bundle['id'];
    ok($imported_item && Placement::item_ids($from_old) === [$imported_item] && $imported_bundle['status'] === 'draft' && Placement::bundle_ids($from_old) === [(int) $imported_bundle['id']] && !term_exists('Imported topic ' . $tag, 'course_category') && !term_exists('Imported tag ' . $tag, 'course_tag'), 'Old export files import categories as curriculum items and tags as draft course bundles, never as terms');
    $again = $import->invoke($importer, $old_file); $posts[] = (int) $again;
    ok((int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Items::table()) === $counts[0] + 1 && (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Bundles::table()) === $counts[1] + 1, 'Importing the same file again does not duplicate structure');

    // ---- Importing from other LMS plugins and the setup wizard ---------------------------------
    $migrated = course('Migrated ' . $tag);
    $parent_term = wp_insert_term('Source parent ' . $tag, 'course_category'); $legacy_terms[] = [(int) $parent_term['term_id'], 'course_category'];
    $child_term = wp_insert_term('Source child ' . $tag, 'course_category', ['parent' => (int) $parent_term['term_id']]); $legacy_terms[] = [(int) $child_term['term_id'], 'course_category'];
    ok(Placement::term_path(get_term((int) $child_term['term_id'], 'course_category')) === ['Source parent ' . $tag, 'Source child ' . $tag], 'A source category keeps its parent chain');
    Placement::import_categories($migrated, [Placement::term_path(get_term((int) $child_term['term_id'], 'course_category')), ['Source parent ' . $tag]]);
    Placement::import_categories($migrated, [['Source parent ' . $tag, 'Source child ' . $tag]]);
    Placement::import_tags($migrated, ['Source tag ' . $tag, 'Source tag ' . $tag, '  ', 'Another source tag ' . $tag]);
    $parent_item = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Items::table() . ' WHERE name=%s', 'Source parent ' . $tag)); $item_ids[] = $parent_item;
    $child_item = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Items::table() . ' WHERE name=%s', 'Source child ' . $tag)); $item_ids[] = $child_item;
    foreach (['Source tag ' . $tag, 'Another source tag ' . $tag] as $title) { $found = Bundles::find_by_title($title); if ($found) { $bundle_ids[] = (int) $found['id']; } }
    ok((int) Items::get($child_item)['parent_id'] === $parent_item && same(Placement::item_ids($migrated), [$parent_item, $child_item]) && (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Items::table() . ' WHERE name=%s', 'Source child ' . $tag)) === 1, 'Imported categories become one curriculum branch, reused on repeat');
    ok(count(Placement::bundles($migrated)) === 2 && (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Bundles::table() . ' WHERE title=%s', 'Source tag ' . $tag)) === 1, 'Imported tags become draft course bundles, once');
    $wizard = new ReflectionMethod(\OhMyLMS\Rest\V1\SetupWizardController::class, 'save_terms'); $wizard->setAccessible(true);
    $wizard->invoke(new \OhMyLMS\Rest\V1\SetupWizardController(), ['category' => ['Wizard topic ' . $tag, 'Wizard topic ' . $tag, 'Wizard other ' . $tag]]);
    $wizard_items = $wpdb->get_results($wpdb->prepare('SELECT id, parent_id FROM ' . Items::table() . ' WHERE name IN (%s, %s)', 'Wizard topic ' . $tag, 'Wizard other ' . $tag), ARRAY_A);
    foreach ($wizard_items as $wizard_item) { $item_ids[] = (int) $wizard_item['id']; }
    ok(count($wizard_items) === 2 && array_unique(array_column($wizard_items, 'parent_id')) === ['0'] && !term_exists('Wizard topic ' . $tag, 'course_category'), 'The setup wizard creates top-level curriculum items, not category terms');

    // ---- Old terms and URLs are left alone ---------------------------------------------------
    ok(taxonomy_exists('course_category') && taxonomy_exists('course_tag'), 'The old taxonomies must stay registered so existing URLs keep working');
    ok(wp_get_object_terms($c_legacy, 'course_category', ['fields' => 'ids']) === [(int) $legacy_term['term_id']] && wp_get_object_terms($c_legacy, 'course_tag', ['fields' => 'ids']) === [(int) $legacy_tag['term_id']], 'Existing category and tag assignments are untouched');
    $by_old_term = new WP_Query(['post_type' => OHMYLMS_COURSE_CPT, 'post_status' => 'publish', 'fields' => 'ids', 'tax_query' => [['taxonomy' => 'course_category', 'terms' => [(int) $legacy_term['term_id']]]]]);
    ok($by_old_term->posts === [$c_legacy] && is_string(get_term_link((int) $legacy_term['term_id'], 'course_category')), 'Old category archives still resolve');
    ok(!Items::get((int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Items::table() . ' WHERE name=%s', 'Legacy category ' . $tag)) ?: 0), 'Old terms were not converted into curriculum items');
} finally {
    wp_set_current_user($admin);
    foreach ($plans as $plan_id) { wp_delete_post($plan_id, true); }
    foreach ($bundle_ids as $id) { if (Bundles::get($id)) { Bundles::delete($id, true); } }
    foreach (array_reverse($item_ids) as $id) { if (Items::get($id)) { Items::delete($id, 'delete', true); } }
    foreach ($posts as $post_id) { purge($post_id); }
    foreach ($legacy_terms as [$term_id, $taxonomy]) { wp_delete_term($term_id, $taxonomy); }
    foreach ($users as $user_id) { wp_delete_user($user_id); }
    foreach ([['ohmylms_archive_page_filters', $previous_filters]] as [$option, $value]) { $value === false ? delete_option($option) : update_option($option, $value); }
    if (isset($start)) {
        $end = ['items' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Items::table()), 'bundles' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Bundles::table()), 'links' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Links::table()), 'members' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Bundles::members_table())];
        if ($end !== $start) { fwrite(STDERR, 'Cleanup left rows behind: ' . wp_json_encode([$start, $end]) . "\n"); }
    }
}
echo "$checks placement integration checks passed.\n";
