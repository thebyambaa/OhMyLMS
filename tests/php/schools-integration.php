<?php
if (PHP_SAPI !== 'cli') { exit; }
$config = json_decode(file_get_contents(getenv('OHMYLMS_TEST_CREDENTIALS')), true);
require $config['site'] . '/wp-load.php';
if (!defined('OHMYLMS_TEST_SITE') || DB_NAME !== 'ohmylms_source_test') { throw new RuntimeException('Requires disposable test site'); }

use OhMyLMS\Schools\Schema;
use OhMyLMS\Schools\Service;

function school_check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function school_request($method, $path, $params = [], $expected = 200) {
    $request = new WP_REST_Request($method, '/ohmylms/v1/school/' . $path);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    if ($method === 'GET') { $request->set_query_params($params); } else { $request->set_body_params($params); }
    $response = rest_do_request($request);
    school_check($response->get_status() === $expected, "$method $path: expected $expected, got " . $response->get_status() . ' ' . wp_json_encode($response->get_data()));
    return $response->get_data();
}
function school_token($invitation) { parse_str(wp_parse_url($invitation['url'], PHP_URL_QUERY), $query); return $query['ohmylms_invite']; }

$admin = get_user_by('login', $config['username']); $users = []; $schools = []; $course = 0; $content = 0; $chapter = 0;
$suffix = strtolower(wp_generate_password(10, false));
foreach (['school_signup', 'school_invite'] as $purpose) { delete_transient('ohmylms_' . $purpose . '_' . hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local', wp_salt('nonce'))); }
try {
    Schema::install(); Schema::install();
    school_check(get_option('ohmylms_school_schema') === Schema::VERSION, 'Schema installation failed');
    foreach (['teacher', 'parent', 'outsider', 'school_admin'] as $role) {
        $id = wp_insert_user(['user_login' => "school-$role-$suffix", 'user_email' => "$role-$suffix@example.invalid", 'user_pass' => wp_generate_password(30), 'role' => 'subscriber']);
        school_check(!is_wp_error($id), 'Fixture user failed'); $users[$role] = $id;
    }
    $independent = 0;
    (new WP_User($users['teacher']))->add_role('ohmylms_teacher');
    (new WP_User($users['parent']))->add_role('ohmylms_parent');
    wp_set_current_user($users['teacher']);
    school_check(school_request('GET', 'me')['can_create_class'], 'Independent teacher cannot create classes');
    $independent = school_request('POST', 'classes', ['name' => 'Independent math'])['id'];
    $record = Service::row('classes', $independent);
    school_check((int) $record['school_id'] === 0 && (int) $record['academic_year_id'] === 0, 'Independent class requires a school');
    school_check(count(school_request('GET', 'classes')) === 1, 'Teacher cannot list own class');
    school_check(count(school_request('GET', "classes/$independent/members")) === 1, 'Creator missing from class');
    wp_set_current_user($users['outsider']);
    school_request('POST', 'classes', ['name' => 'Denied'], 403);
    school_request('GET', "classes/$independent/members", [], 403);
    (new WP_User($users['outsider']))->add_role('ohmylms_teacher');
    wp_set_current_user(0); wp_set_current_user($users['outsider']);
    school_check(count(school_request('GET', 'classes')) === 0, 'Independent classes leaked to another teacher');
    school_request('POST', "classes/$independent/archive", [], 403);
    (new WP_User($users['outsider']))->remove_role('ohmylms_teacher');
    wp_set_current_user($admin->ID);
    foreach (['teacher', 'parent'] as $filter) {
        $accounts = school_request('GET', 'users', ['role' => $filter, 'search' => $suffix]);
        school_check(count($accounts) === 1 && (int) $accounts[0]['id'] === $users[$filter], 'Account role filter failed');
    }
    school_check(count(school_request('GET', 'users', ['role' => 'parent', 'search' => 'no-match-' . $suffix])) === 0, 'Empty role search leaked users');
    wp_set_current_user($users['teacher']);
    school_request('POST', "classes/$independent/archive");
    (new WP_User($users['teacher']))->remove_role('ohmylms_teacher');
    wp_set_current_user(0);
    school_request('GET', 'schools', [], 401);
    school_request('GET', 'users', [], 401);
    school_request('POST', 'register', ['role' => 'school_admin'], 400);
    $signup = school_request('POST', 'register', ['role' => 'student', 'email' => "signup-$suffix@example.invalid", 'password' => 'School-signup-test-password-42']);
    $users['signup'] = get_user_by('email', "signup-$suffix@example.invalid")->ID;
    school_check(!$wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Schema::table('user_enrollment') . ' WHERE user_id=%d', $users['signup'])), 'Signup created an enrollment');
    wp_set_current_user($admin->ID);
    $directory = school_request('GET', 'users', ['search' => "teacher-$suffix@example.invalid"]);
    school_check(count($directory) === 1 && (int) $directory[0]['id'] === $users['teacher'], 'User directory search failed');
    school_check(!empty($directory[0]['edit_url']), 'Administrator user edit link missing');
    $schools[] = $a = school_request('POST', 'schools', ['name' => "School A $suffix", 'timezone' => 'Asia/Ulaanbaatar'])['id'];
    $schools[] = $b = school_request('POST', 'schools', ['name' => "School B $suffix"])['id'];
    $staff_invite = school_request('POST', "schools/$a/invitations", ['email' => get_userdata($users['school_admin'])->user_email, 'role' => 'school_admin']);
    wp_set_current_user($users['school_admin']); school_request('POST', 'accept', ['token' => school_token($staff_invite)]);
    school_request('GET', "schools/$a/roster"); school_request('GET', "schools/$b/roster", [], 403);
    school_check(!current_user_can('manage_options'), 'School administrator became a WordPress administrator');
    school_request('GET', 'users', [], 403);
    wp_set_current_user($admin->ID);
    $year = school_request('POST', "schools/$a/years", ['label' => '2026', 'starts_at' => '2026-09-01', 'ends_at' => '2027-06-01'])['id'];
    $class = school_request('POST', "schools/$a/classes", ['name' => 'Math A', 'academic_year_id' => $year])['id'];
    school_request('POST', "schools/$b/classes", ['name' => 'Wrong school', 'academic_year_id' => $year], 400);
    $users['student'] = school_request('POST', "schools/$a/students", ['name' => 'School test student', 'external_student_id' => 'S-1'])['id'];
    school_check(get_userdata($users['student'])->user_email === '', 'Child unexpectedly requires email');
    school_request('POST', "classes/$class/members", ['user_id' => $users['student'], 'role' => 'student']);
    school_request('POST', "classes/$class/members", ['user_id' => $users['outsider'], 'role' => 'student'], 403);
    $invite = school_request('POST', "schools/$a/invitations", ['email' => get_userdata($users['teacher'])->user_email, 'role' => 'teacher', 'class_id' => $class]);
    // A signed-in user whose email differs from the invitation is refused with a clear message (400); the invitation stays usable for the right person.
    wp_set_current_user($users['outsider']); school_request('POST', 'accept', ['token' => school_token($invite)], 400);
    wp_set_current_user($users['teacher']); school_request('POST', 'accept', ['token' => school_token($invite)]);
    school_request('POST', 'accept', ['token' => school_token($invite)], 400);
    school_request('GET', "classes/$class/members");
    school_request('GET', "schools/$b/roster", [], 403);
    school_request('POST', "schools/$a/invitations", ['role' => 'school_admin', 'email' => 'x@example.invalid'], 403);
	// Teachers can create one school under the current hub contract, with no platform capabilities.
	$schools[] = $teacher_school = school_request( 'POST', 'schools', array( 'name' => "Teacher school $suffix" ) )['id'];
	school_check( \OhMyLMS\Schools\Access::school( $teacher_school ), 'Teacher did not become administrator of their own school' );
	school_check( ! current_user_can( 'manage_options' ), 'School creation granted platform administration' );
	school_request( 'POST', 'schools', array( 'name' => 'Second teacher school' ), 400 );
    school_check(!current_user_can('edit_posts'), 'Teacher gained broad WordPress editing rights');
    school_request('GET', 'users', [], 403);
    wp_set_current_user($admin->ID);
    $course = wp_insert_post(['post_type' => OHMYLMS_COURSE_CPT, 'post_status' => 'publish', 'post_title' => "School course $suffix"]);
    $wpdb->insert(Schema::table('user_enrollment'), ['user_id' => $users['student'], 'course_id' => $course, 'status' => 'enrolled', 'progress' => 'running', 'start_date' => Service::now()]);
    wp_set_current_user($users['teacher']);
    $assignment = school_request('POST', "classes/$class/assignments", ['course_id' => $course, 'title' => 'School work'])['id'];
    school_check(count(school_request('GET', "classes/$class/assignments")) === 1, 'Teacher report missing recipient');
    wp_set_current_user($admin->ID);
    $content = wp_insert_post(['post_type' => 'ohmylms-assignment', 'post_status' => 'publish', 'post_title' => 'School written assignment']);
    $chapter = wp_insert_post(['post_type' => 'ohmylms-chapter', 'post_status' => 'publish', 'post_title' => 'School chapter']);
    update_post_meta($content, '_type', 'assignment'); update_post_meta($content, '_total_points', 100); update_post_meta($content, '_maximum_pass_points', 50);
    $wpdb->insert(Schema::table('chapter_relationship'), ['course_id' => $course, 'chapter_id' => $chapter]);
    $wpdb->insert(Schema::table('content_relationship'), ['chapter_id' => $chapter, 'content_id' => $content, 'content_type' => 'assignment']);
    wp_set_current_user($users['teacher']);
    $activity = school_request('POST', "classes/$class/assignments", ['course_id' => $course, 'content_id' => $content, 'title' => 'Written work'])['id'];
    $wpdb->insert(Schema::table('assignment_attempts'), ['user_id' => $users['student'], 'course_id' => $course, 'assignment_id' => $content, 'content' => 'My answer', 'files' => serialize([]), 'status' => 'submitted', 'start_date' => current_time('mysql')]);
    $attempt = $wpdb->insert_id;
    school_check(count(school_request('GET', "classes/$class/submissions")) === 1, 'Scoped submission missing');
    school_request('POST', "assignments/$activity/submissions/$attempt", ['score' => 101], 400);
    wp_set_current_user($users['outsider']); school_request('POST', "assignments/$activity/submissions/$attempt", ['score' => 20], 403);
    wp_set_current_user($users['teacher']); school_request('POST', "assignments/$activity/submissions/$attempt", ['score' => 20, 'note' => 'Please revise.']);
    school_check((int) Service::row('assignment_attempts', $attempt)['score'] === 20, 'Grade was not saved through the existing pipeline');
    school_request('POST', "assignments/$activity/submissions/$attempt", ['score' => 80, 'note' => 'Well done.']);
    school_check((int) Service::row('assignment_attempts', $attempt)['score'] === 80, 'Passing grade was not saved');
    school_check($wpdb->get_var($wpdb->prepare('SELECT completed_at FROM ' . Schema::table('assignment_recipients') . ' WHERE assignment_id=%d AND student_user_id=%d', $activity, $users['student'])), 'Passing grade did not complete distributed work');
    wp_set_current_user($users['student']); school_check(count(school_request('GET', 'work')) === 2, 'Student cannot view assigned work');
    school_request('GET', "classes/$class/assignments", [], 403);
    wp_set_current_user($users['parent']); school_request('GET', 'work', ['student' => $users['student'], 'school_id' => $a], 403);
    wp_set_current_user($admin->ID);
    $parent_invite = school_request('POST', "schools/$a/invitations", ['role' => 'guardian', 'student_user_id' => $users['student'], 'email' => get_userdata($users['parent'])->user_email]);
    wp_set_current_user($users['parent']); school_request('POST', 'accept', ['token' => school_token($parent_invite)]);
    school_check(count(school_request('GET', 'children')) === 1, 'Guardian link missing');
    school_check(count(school_request('GET', 'work', ['student' => $users['student'], 'school_id' => $a])) === 2, 'Guardian work missing');
    school_request('GET', 'work', ['student' => $users['student'], 'school_id' => $b], 403);
    wp_set_current_user($admin->ID);
    $guardian = school_request('GET', "schools/$a/guardians")[0];
    school_request('DELETE', "schools/$a/guardians/{$guardian['id']}");
    wp_set_current_user($users['parent']); school_request('GET', 'work', ['student' => $users['student'], 'school_id' => $a], 403);
    wp_set_current_user($admin->ID);
    $activation = school_request('POST', "schools/$a/invitations", ['role' => 'activation', 'student_user_id' => $users['student']]);
    wp_set_current_user(0);
    school_request('POST', 'accept', ['token' => school_token($activation), 'password' => 'New-school-child-password-42']);
    school_check(wp_check_password('New-school-child-password-42', get_userdata($users['student'])->user_pass, $users['student']), 'Activation did not set password');
    school_request('POST', 'accept', ['token' => school_token($activation), 'password' => 'Another-child-password-42'], 400);
    wp_set_current_user($admin->ID);
    $preview = school_request('POST', "schools/$a/import", ['csv' => "name,external_student_id\nExisting,S-1\n"]);
    school_check($preview[0]['status'] === 'existing', 'CSV did not recognize stable school ID');
    school_request('POST', "schools/$a/import", ['csv' => "name,external_student_id\nUnreviewed,S-new\n", 'commit' => true], 400);
    $imported = school_request('POST', "schools/$a/import", ['csv' => "name,external_student_id\nExisting,S-1\n", 'commit' => true]);
    school_check($imported[0]['status'] === 'existing', 'Repeat import duplicated a student');
    $members = school_request('GET', "schools/$a/roster");
    foreach ($members as $member) { if ((int) $member['user_id'] === $users['teacher']) { school_request('DELETE', "schools/$a/members/{$member['id']}"); } }
    wp_set_current_user($users['teacher']); school_request('GET', "classes/$class/members", [], 403);
    wp_set_current_user($admin->ID);
    $next = school_request('POST', "schools/$a/years", ['label' => '2027', 'starts_at' => '2027-09-01', 'ends_at' => '2028-06-01'])['id'];
    school_request('POST', "schools/$a/rollover", ['from_year' => $year, 'to_year' => $next]);
    school_request('POST', "schools/$a/rollover", ['from_year' => $year, 'to_year' => $next], 400);
    school_check(get_userdata($users['student']) && Service::row('learning_assignments', $assignment), 'Rollover lost student or learning history');
    wp_set_current_user(0);
    $profile = ['email' => get_userdata($users['outsider'])->user_email, 'sub' => "school-google-$suffix", 'email_verified' => true];
    school_check(is_wp_error(\OhMyLMS\Services\GoogleAuthService::find_or_create_user($profile)), 'Google linked existing email without account proof');
    wp_set_current_user($users['outsider']);
    school_check(!is_wp_error(\OhMyLMS\Services\GoogleAuthService::find_or_create_user($profile)), 'Signed-in Google linking failed');
    wp_set_current_user(0);
    school_check(\OhMyLMS\Services\GoogleAuthService::find_or_create_user($profile)->ID === $users['outsider'], 'Google subject login failed');
    $state = wp_generate_password(32, false);
    set_transient(\OhMyLMS\Services\GoogleAuthService::STATE_TRANSIENT . $state, ['redirect_to' => home_url('/'), 'link_user_id' => $users['outsider']], 600);
    $_COOKIE['ohmylms_google_state'] = 'wrong-browser';
    school_check(false === \OhMyLMS\Services\GoogleAuthService::consume_state($state), 'Google state accepted from another browser');
    $_COOKIE['ohmylms_google_state'] = $state;
    $_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie($users['outsider'], time() + 600, 'logged_in');
    school_check(home_url('/') === \OhMyLMS\Services\GoogleAuthService::consume_state($state), 'Authenticated Google linking state failed');
    school_check(false === \OhMyLMS\Services\GoogleAuthService::consume_state($state), 'Google state was replayable');
    unset($_COOKIE['ohmylms_google_state'], $_COOKIE[LOGGED_IN_COOKIE]);
    echo "School integration passed: schema, registration, tenant isolation, invitations, student activation, teacher/guardian scope, assignments, revocation, rollover, Google linking.\n";
} finally {
    if (!empty($independent)) {
        $wpdb->delete(Schema::table('class_memberships'), ['class_id' => $independent]);
        $wpdb->delete(Schema::table('classes'), ['id' => $independent]);
        $wpdb->delete(Schema::table('school_audit_log'), ['school_id' => 0, 'object_id' => $independent, 'action' => 'class_created']);
        $wpdb->delete(Schema::table('school_audit_log'), ['school_id' => 0, 'object_id' => $independent, 'action' => 'class_archived']);
    }
    wp_set_current_user($admin->ID);
    foreach ($schools as $school) {
        $classes = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . Schema::table('classes') . ' WHERE school_id=%d', $school));
        foreach ($classes as $class_id) { $wpdb->delete(Schema::table('class_memberships'), ['class_id' => $class_id]); }
        $assignments = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . Schema::table('learning_assignments') . ' WHERE school_id=%d', $school));
        foreach ($assignments as $assignment_id) { $wpdb->delete(Schema::table('assignment_recipients'), ['assignment_id' => $assignment_id]); }
        foreach (['school_memberships', 'school_student_profiles', 'academic_years', 'classes', 'guardian_links', 'school_invitations', 'learning_assignments', 'school_audit_log'] as $table) { $wpdb->delete(Schema::table($table), ['school_id' => $school]); }
        $wpdb->delete(Schema::table('schools'), ['id' => $school]);
    }
    if ($course) {
        $enrollments = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . Schema::table('user_enrollment') . ' WHERE course_id=%d', $course));
        foreach ($enrollments as $enrollment) { $wpdb->delete(Schema::table('user_progress'), ['enrollment_id' => $enrollment]); }
        $wpdb->delete(Schema::table('assignment_attempts'), ['course_id' => $course]);
        $wpdb->delete(Schema::table('chapter_relationship'), ['course_id' => $course]);
        $wpdb->delete(Schema::table('user_enrollment'), ['course_id' => $course]); wp_delete_post($course, true);
    }
    if ($chapter) { $wpdb->delete(Schema::table('content_relationship'), ['chapter_id' => $chapter]); wp_delete_post($chapter, true); }
    if ($content) { wp_delete_post($content, true); }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($users as $id) { wp_delete_user($id); $wpdb->delete(Schema::table('school_audit_log'), ['object_id' => $id, 'school_id' => 0]); }
}
