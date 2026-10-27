<?php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
define('OHMYLMS_COURSE_CPT', 'ohmylms-course');
require dirname(__DIR__, 2) . '/vendor/autoload.php';
use OhMyLMS\Curriculum\Directory;
function check_directory($value, $message) { if (!$value) { throw new RuntimeException($message); } }
$outline = ['syllabus' => ['name' => 'Математик', 'description' => 'Grade skills'], 'contents' => [
    ['name' => 'Тоо', 'icon' => 'calculator', 'groups' => [
        ['name' => 'Core', 'skills' => [['term_id' => 10, 'name' => 'Бутархай', 'code' => 'C1', 'category' => 'Core', 'description' => 'Editor notes']]],
        ['name' => 'Extended', 'skills' => [['term_id' => 11, 'name' => 'Зэрэг', 'code' => 'E1', 'category' => 'Extended']]],
    ]],
    ['name' => 'Empty', 'groups' => [['name' => 'No skills', 'skills' => []]]],
]];
$settings = ['grade' => 'Grade 8', 'subject' => 'Mathematics', 'language' => 'Монгол'];
$snapshot = Directory::build($outline, $settings, [10]);
check_directory($snapshot['count'] === 1 && count($snapshot['topics']) === 1, 'Only published outcomes appear; empty topics are excluded');
check_directory(count($snapshot['topics'][0]['groups']) === 1, 'A chapter containing only unpublished skills is excluded');
check_directory(!isset($snapshot['topics'][0]['groups'][0]['skills'][0]['description']), 'Editor-only skill notes are not exported');
$outline['contents'][0]['groups'][0]['skills'][0]['name'] = 'Draft edit';
check_directory($snapshot['topics'][0]['groups'][0]['skills'][0]['name'] === 'Бутархай', 'Draft edits do not change a captured publication');
check_directory(Directory::build($outline, $settings)['count'] === 2, 'Preview includes the full draft');
check_directory(Directory::build($outline, $settings, [])['count'] === 0, 'An empty published outcome set cannot leak draft skills');
// Exercise public visibility using minimal WordPress storage doubles.
$wpdb = new class {
    public $prefix = 'wp_';
    public function prepare($sql, $id) { return $sql; }
    public function get_row($sql, $format) { return ['course_id' => 8]; }
};
$status = 'draft'; $password = false; $meta = $snapshot;
function get_post_type($id) { return OHMYLMS_COURSE_CPT; }
function get_post_status($id) { global $status; return $status; }
function post_password_required($id) { global $password; return $password; }
function get_post_meta($id, $key, $single) { global $meta; return $meta; }
check_directory(Directory::data(5) === null, 'A saved snapshot does not make a draft course public');
$status = 'publish';
check_directory(Directory::data(5) === $snapshot, 'Published courses return the snapshot');
$password = true;
check_directory(Directory::data(5) === null, 'Password protection also applies to directory query links');
$password = false; $meta = '';
check_directory(Directory::data(5) === null, 'Older courses without snapshots do not expose their draft outline');
echo "syllabus directory checks passed: 10\n";
