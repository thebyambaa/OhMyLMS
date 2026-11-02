<?php
if (PHP_SAPI !== 'cli') { exit; }
$config = json_decode(file_get_contents(getenv('OHMYLMS_TEST_CREDENTIALS')), true);
require $config['site'] . '/wp-load.php';
if (!defined('OHMYLMS_TEST_SITE') || DB_NAME !== 'ohmylms_source_test') { throw new RuntimeException('Requires disposable test site'); }
function student_check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function student_request($method, $path, $params = []) {
    $request = new WP_REST_Request($method, '/ohmylms/v1/' . $path);
    $request->set_body_params($params);
    return rest_do_request($request);
}
$admin = get_user_by('login', $config['username']);
$student = wp_create_user('source-student-' . wp_generate_password(10, false), wp_generate_password(32));
student_check(!is_wp_error($student), 'Could not create fixture');
$course = 0;
try {
    $course = wp_insert_post(['post_type'=>'ohmylms-courses', 'post_title'=>'Student source fixture', 'post_status'=>'draft']);
    $wpdb->insert($wpdb->prefix . 'ohmylms_user_enrollment', ['user_id'=>$student, 'course_id'=>$course, 'status'=>'enrolled', 'progress'=>'running', 'start_date'=>current_time('mysql')]);
    foreach ([0, $student] as $viewer) {
        wp_set_current_user($viewer);
        foreach ([['GET','students',[]], ['GET','students/'.$student,[]], ['POST','students/'.$student,['blocked'=>true]], ['POST','students/'.$student,['blocked'=>false]]] as [$method,$path,$params]) {
            student_check(in_array(student_request($method,$path,$params)->get_status(), [401, 403], true), 'Unauthorized student access: '.$method.' '.$path);
        }
    }
    wp_set_current_user($admin->ID);
    student_check(in_array(student_request('POST','students/'.$admin->ID,['blocked'=>true])->get_status(), [404, 422], true) && get_user_meta($admin->ID,'_ohmylms_banned_student',true) !== 'yes', 'An administrator could be blocked');
    $blocked = student_request('POST','students/'.$student,['blocked'=>true]);
    student_check($blocked->get_status() === 200 && true === $blocked->get_data()['blocked'], 'Block failed');
    student_check(get_user_meta($student,'_ohmylms_banned_student',true) === 'yes', 'Block metadata missing');
    student_check($wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id=%d",$student)) === 'banned', 'Enrollment not blocked');
    $unblocked = student_request('POST','students/'.$student,['blocked'=>false]);
    student_check($unblocked->get_status() === 200 && false === $unblocked->get_data()['blocked'], 'Unblock failed');
    student_check(get_user_meta($student,'_ohmylms_banned_student',true) !== 'yes', 'Block metadata retained');
    student_check($wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id=%d",$student)) === 'enrolled', 'Enrollment not restored');
    echo "Student permissions and block/unblock integration passed.\n";
} finally {
    $wpdb->delete($wpdb->prefix.'ohmylms_user_enrollment',['user_id'=>$student]);
    if ($course) { wp_delete_post($course,true); }
    require_once ABSPATH.'wp-admin/includes/user.php';
    wp_delete_user($student);
}
