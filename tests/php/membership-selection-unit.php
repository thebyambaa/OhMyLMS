<?php
define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');
require dirname(__DIR__, 2) . '/vendor/autoload.php';
use OhMyLMS\Membership\CourseSelection;
function absint($id) { return abs((int) $id); }
function apply_filters($hook, $value) { return $value; }
function current_time($format) { return '2026-10-03 12:00:00'; }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$GLOBALS['courses'] = [
    1 => ['status' => 'publish', 'course_category' => [], 'course_tag' => []],
    2 => ['status' => 'publish', 'course_category' => [11], 'course_tag' => []],
    3 => ['status' => 'publish', 'course_category' => [10], 'course_tag' => [20]],
    4 => ['status' => 'publish', 'course_category' => [], 'course_tag' => []],
    5 => ['status' => 'draft', 'course_category' => [10], 'course_tag' => []],
];
function get_post_type($id) { return isset($GLOBALS['courses'][$id]) ? 'ohmylms-course' : 'post'; }
function get_post_status($id) { return $GLOBALS['courses'][$id]['status'] ?? ''; }
function get_the_title($id) { return 'Course ' . $id; }
function rest_sanitize_boolean($value) { return ! in_array($value, [false, 0, '0', 'false', null, ''], true); }
function get_posts($args) {
    if (!isset($args['tax_query'])) return array_keys(array_filter($GLOBALS['courses'], fn($course) => $course['status'] === 'publish'));
    check($args['post_status'] === 'publish', 'Only published courses enter membership selection');
    check($args['tax_query']['relation'] === 'OR', 'Categories and tags match either rule');
    $matches = [];
    foreach ($GLOBALS['courses'] as $id => $course) {
        if ($course['status'] !== 'publish') continue;
        foreach ($args['tax_query'] as $rule) {
            if (!is_array($rule)) continue;
            $terms = $rule['terms'];
            if ($rule['taxonomy'] === 'course_category') {
                check($rule['include_children'] === true, 'Subcategories included');
                if (in_array(10, $terms)) $terms[] = 11;
            }
            if (array_intersect($terms, $course[$rule['taxonomy']])) { $matches[] = $id; break; }
        }
    }
    return $matches;
}
$plan = (new ReflectionClass(\OhMyLMS\Data\Membership::class))->newInstanceWithoutConstructor();
$plan->set_status('publish');
$plan->set_products([['id' => 1, 'name' => 'Course 1'], ['id' => 3], ['id' => 1]]);
$plan->set_course_categories([10]);
$plan->set_course_tags([20]);
$plan->set_excluded_courses([3]);
function ohmylms_get_membership($id) { return $GLOBALS['plan']; }
check(array_column($plan->get_products(), 'id') === [1, 2], 'Union deduplicates courses and exclusions win even over direct selection');
check(count($plan->get_products('edit')) === 3, 'Editor retains explicit choices rather than converting matches to direct choices');
check($plan->get_course_categories('edit') === [10] && $plan->get_course_tags('edit') === [20], 'Rule setters/getters retain term IDs');

class SelectionDatabase {
    public $prefix = 'wp_';
    public $members = [['user_id' => 7, 'order_id' => 70, 'status' => 'enrolled'], ['user_id' => 8, 'order_id' => 80, 'status' => 'pending']];
    public $rows = [
        ['id' => 1, 'user_id' => 7, 'order_id' => 70, 'membership_id' => 100, 'course_id' => 1, 'status' => 'enrolled', 'progress' => 'completed'],
        ['id' => 2, 'user_id' => 7, 'order_id' => 70, 'membership_id' => 100, 'course_id' => 4, 'status' => 'enrolled', 'progress' => 'running'],
        ['id' => 3, 'user_id' => 9, 'order_id' => 90, 'membership_id' => 100, 'course_id' => 2, 'status' => 'enrolled'],
        ['id' => 4, 'user_id' => 7, 'order_id' => 99, 'membership_id' => null, 'course_id' => 4, 'status' => 'enrolled'],
        ['id' => 5, 'user_id' => 7, 'order_id' => 71, 'membership_id' => 101, 'course_id' => 4, 'status' => 'enrolled'],
    ];
    public function prepare($sql, ...$args) { return [$sql, $args]; }
    public function esc_like($text) { return $text; }
    public function get_var($query) { return null; } // No ohmylms_enrolments table: the service writes legacy rows only.
    public function get_results($query, $format) {
        if (str_contains($query[0], 'ohmylms_user_membership') || in_array('wp_ohmylms_user_membership', $query[1], true)) return $this->members;
        return array_values(array_filter($this->rows, fn($row) => $row['membership_id'] === 100));
    }
    public function query($query) { check(str_contains($query[0], 'order_id=%d') && str_contains($query[0], 'membership_id IS NULL'), 'Legacy adoption is scoped to membership orders'); }
    public function update($table, $values, $where) { foreach ($this->rows as &$row) if ($row['id'] == $where['id']) $row = array_merge($row, $values); }
    public function insert($table, $values) { $values['id'] = count($this->rows) + 1; $this->rows[] = $values; }
}
$wpdb = new SelectionDatabase();
CourseSelection::sync(100);
check(
	2 === count(
		array_filter(
			$wpdb->rows,
			static function ( $row ) {
				return 100 === $row['membership_id'] && 7 === $row['user_id'] && 'enrolled' === $row['status'];
			}
		)
	),
	'An active membership automatically grants every included course without a separate enrollment action'
);
check($wpdb->rows[0]['progress'] === 'completed', 'Synchronization preserves course progress');
check($wpdb->rows[1]['status'] === 'cancelled', 'Losing a match revokes a started membership course');
check($wpdb->rows[2]['status'] === 'cancelled', 'Inactive members gain no access');
check($wpdb->rows[3]['status'] === 'enrolled' && $wpdb->rows[4]['status'] === 'enrolled', 'Independent purchases and other plans retain access');
$count = count($wpdb->rows);
CourseSelection::sync(100);
check(count($wpdb->rows) === $count, 'Repeated synchronization does not duplicate enrollments');
$wpdb->members[0]['order_id'] = 75;
CourseSelection::sync(100);
check(count($wpdb->rows) === $count && $wpdb->rows[0]['order_id'] === 75 && $wpdb->rows[0]['progress'] === 'completed', 'Renewal changes the membership order without resetting progress');
$GLOBALS['courses'][5]['status'] = 'publish';
CourseSelection::sync(100);
check(count(array_filter($wpdb->rows, fn($r) => $r['user_id'] === 7 && $r['course_id'] === 5 && $r['status'] === 'enrolled')) === 1, 'Future matching course reaches active members');
check(count(array_filter($wpdb->rows, fn($r) => $r['user_id'] === 8 && $r['course_id'] === 5 && $r['status'] === 'pending')) === 1, 'Unpaid membership does not gain active access');
$GLOBALS['courses'][2]['course_category'] = [];
CourseSelection::sync(100);
check(!array_filter($wpdb->rows, fn($r) => $r['membership_id'] === 100 && $r['course_id'] === 2 && $r['status'] === 'enrolled'), 'Retagging removes membership access immediately');
$plan->set_products([]); $plan->set_course_categories([]); $plan->set_course_tags([]);
CourseSelection::sync(100);
check(!array_filter($wpdb->rows, fn($r) => $r['membership_id'] === 100 && $r['status'] === 'enrolled'), 'Clearing all choices revokes all plan grants');
echo "Membership selection and enrollment checks passed.\n";

class WP_REST_Controller {}
class WP_Error {
    public function __construct(public $code, public $message, public $data = []) {}
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}
function __($text, $domain) { return $text; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function rest_ensure_response($value) { return $value; }
function term_exists($id, $taxonomy) { return in_array((int) $id, $taxonomy === 'course_category' ? [10, 11] : [20]); }
function get_term_children($id, $taxonomy) { return (int) $id === 10 ? [11] : []; }
function wp_get_post_terms($id, $taxonomy) { return array_map(fn($term) => (object) ['term_id' => $term, 'name' => 'Term ' . $term], $GLOBALS['courses'][$id][$taxonomy]); }
check( array() === \OhMyLMS\Membership\CourseSelection::resolve( array(), array(), array(), array(), array(), array( 999 ) ), 'Retired bundle rules grant no courses' );

$plan->set_all_learning_content(true);
$plan->set_excluded_courses([3]);
check(array_column($plan->get_products(), 'id') === [1, 2, 4, 5], 'All learning content includes every published course and honors exclusions');
$GLOBALS['courses'][6] = ['status' => 'publish', 'course_category' => [], 'course_tag' => []];
check(in_array(6, array_column($plan->get_products(), 'id'), true), 'Future published courses enter the plan automatically');
$plan->set_all_learning_content(false);
check(!in_array(6, array_column($plan->get_products(), 'id'), true), 'Turning all content off restores selective rules');
