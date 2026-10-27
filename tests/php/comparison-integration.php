<?php
/** Shared skills, multiple sources, reusable content, roadmaps and MCP. Isolated site only. */
if ( PHP_SAPI !== 'cli' ) { exit; }
$config = json_decode( file_get_contents( getenv( 'OHMYLMS_TEST_CREDENTIALS' ) ), true );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8100';
require $config['site'] . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! in_array( DB_NAME, array( 'ohmylms_source_test', 'ohmylms_streak_test' ), true ) ) { throw new RuntimeException( 'Requires an isolated database.' ); }

use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Roadmaps;
use OhMyLMS\Curriculum\SharedContent;
use OhMyLMS\Curriculum\SkillMappings;
use OhMyLMS\Curriculum\SubjectProgress;
use OhMyLMS\Curriculum\Syllabus;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\MCP\Abilities;
use OhMyLMS\MCP\Server;

$checks = 0; $items = array(); $terms = array(); $posts = array(); $student = 0;
$before = get_option( Comparison::OPTION, array() );
$integrations = get_option( 'ohmylms_integrations', array() );
$admin = get_user_by( 'login', $config['username'] )->ID;
$tag = wp_generate_password( 6, false, false );
function ok( $value, $message ) { global $checks; if ( ! $value ) { throw new RuntimeException( $message ); } ++$checks; }
function call_api( $method, $path, array $data = array() ) {
    $request = new WP_REST_Request( $method, '/ohmylms/v1/syllabus-comparison/' . $path );
    if ( 'GET' === $method ) { $request->set_query_params( $data ); }
    else { $request->set_header( 'Content-Type', 'application/json' ); $request->set_body( wp_json_encode( $data ) ); }
    return rest_do_request( $request );
}
try {
    wp_set_current_user( $admin );
    OhMyLMS\Curriculum\Schema::install(); OhMyLMS\Assessment\Schema::install(); OhMyLMS\Learning\Schema::install();
    $shared = call_api( 'POST', 'shared-skills/save', array( 'name' => 'Solve linear equations ' . $tag, 'subject' => 'Mathematics', 'topic' => 'Algebra', 'description' => 'Solve a single variable linear equation with rational coefficients.' ) );
    ok( 200 === $shared->get_status(), 'shared creation: ' . wp_json_encode( $shared->get_data() ) );
    $shared = $shared->get_data(); $terms[] = $shared['id'];
    $science = Comparison::save_shared( 0, array( 'name' => 'Interpret evidence ' . $tag, 'subject' => 'Science', 'topic' => 'Reasoning' ) ); $terms[] = $science['id'];
    ok( count( Comparison::shared_skills( array( 'subject' => 'Science', 'search' => $tag ) )['items'] ) === 1, 'subject filter is optional and generic' );
    ok( count( Comparison::shared_skills( array( 'search' => $tag ) )['items'] ) === 2, 'one reusable library can cross subject groupings' );
    $objectives = array();
    foreach ( array( 'Cambridge', 'Mongolia', 'Standardized exam' ) as $label ) {
        $item = Items::create( array( 'name' => $label . ' ' . $tag, 'is_syllabus' => true, 'version' => '2028–2030' ) ); $id = (int) $item['id']; $items[] = $id;
        $group = Syllabus::add_group( $id, array( 'name' => 'Algebra' ) );
        $term = Syllabus::create_skill( $id, $group, array( 'name' => $label . ' equation ' . $tag, 'code' => 'L1', 'description' => 'Distinct source scope.' ) );
        ok( ! is_wp_error( $term ), 'source objective creation' ); $terms[] = $term['term_id']; $objectives[] = $term['term_id'];
        for ( $i = 0; $i < 31; ++$i ) { $extra = Syllabus::create_skill( $id, $group, array( 'name' => $label . ' extra ' . $tag . ' ' . $i, 'code' => 'X' . $i ) ); $terms[] = $extra['term_id']; }
    }
    $page = call_api( 'GET', 'objectives/' . $items[0], array() )->get_data();
    ok( 32 === $page['total'] && 30 === count( $page['items'] ), 'bounded source page' );
    ok( 2 === count( call_api( 'GET', 'objectives/' . $items[0], array( 'page' => 2 ) )->get_data()['items'] ), 'second source page' );
    ok( $page['items'][0]['term_id'] === $objectives[0], 'source order and identity retained' );
    ok( 1 === call_api( 'GET', 'objectives/' . $items[0], array( 'search' => 'L1' ) )->get_data()['total'], 'search by code' );
    $mapping = array( 'specific_id' => $objectives[0], 'shared_id' => $shared['id'], 'relation' => 'equivalent', 'note' => 'Reviewed', 'expected_revision' => '' );
    ok( 200 === call_api( 'PUT', 'mapping', $mapping )->get_status(), 'explicit mapping save' );
    ok( 409 === call_api( 'PUT', 'mapping', $mapping )->get_status(), 'concurrent new-pair collision rejected' );
    $mapping['specific_id'] = $objectives[1]; ok( 200 === call_api( 'PUT', 'mapping', $mapping )->get_status(), 'second syllabus maps to same shared identity' );
    $mapping['specific_id'] = $objectives[2]; $mapping['relation'] = 'related'; ok( 200 === call_api( 'PUT', 'mapping', $mapping )->get_status(), 'third syllabus related scope' );
    ok( 3 === count( Comparison::shared_skill( $shared['id'] )['mappings']['mapped_from'] ), 'all affected objectives visible' );
    ok( 409 === call_api( 'PUT', 'mapping', array( 'specific_id' => $shared['id'], 'shared_id' => $science['id'], 'relation' => 'equivalent', 'expected_revision' => '' ) )->get_status(), 'mapping chains rejected' );
    ok( 400 === call_api( 'PUT', 'mapping', array( 'specific_id' => $shared['id'], 'shared_id' => $shared['id'], 'relation' => 'equivalent', 'expected_revision' => '' ) )->get_status(), 'self mapping rejected' );
    $extras = array_slice( array_column( Comparison::objectives( $items[0] ), 'term_id' ), 1, 2 );
    foreach ( array( 'broader', 'narrower' ) as $index => $relation ) {
        ok( 200 === call_api( 'PUT', 'mapping', array( 'specific_id' => $extras[$index], 'shared_id' => $science['id'], 'relation' => $relation, 'note' => 'Different scope', 'expected_revision' => '' ) )->get_status(), $relation . ' saves descriptively' );
        ok( ! SharedContent::owners( $extras[$index] ), $relation . ' does not inherit equivalent content' );
    }
    $combined_method = new ReflectionMethod( OhMyLMS\Bundles\Progress::class, 'combined' ); $combined_method->setAccessible(true);
    $combined_view = $combined_method->invoke(null, $admin, $extras, array());
    ok( 0 === $combined_view[0]['pooled_sources'], 'broader and narrower do not pool evidence' );
    $source_rows = Comparison::attach_mappings( Comparison::objectives( $items[0] ) );
    $pairs = array(); foreach ($source_rows as $row) { if (in_array($row['term_id'], $extras, true)) { $pairs[] = array('specific_id'=>$row['term_id'], 'expected_revision'=>$row['maps_to'][0]['revision']); } }
    $group_payload = array('shared_id'=>$science['id'], 'note'=>'Both objectives required', 'groups'=>array(array('source_id'=>$items[0], 'pairs'=>$pairs)));
    $stale_group = $group_payload; $stale_group['groups'][0]['pairs'][1]['expected_revision']='stale';
    ok( 409 === call_api('POST','mapping-groups',$stale_group)->get_status(), 'combined batch stale revision rejected' );
    ok( 'broader' === SkillMappings::for_skill($extras[0])['maps_to'][0]['relation'], 'combined batch failure rolls back earlier successful pair' );
    $single_group = $group_payload; $single_group['groups'][0]['pairs']=array($pairs[0]);
    ok( 400 === call_api('POST','mapping-groups',$single_group)->get_status(), 'combined singleton rejected' );
    $foreign_group = $group_payload; $foreign_group['groups'][0]['source_id']=$items[1];
    ok( 400 === call_api('POST','mapping-groups',$foreign_group)->get_status(), 'combined group must belong to its source' );
    $group_reply = call_api('POST','mapping-groups',$group_payload);
    ok( 200 === $group_reply->get_status(), 'combined coverage saves atomically' );
    $group_rows = $group_reply->get_data(); $uuid = $group_rows[0]['group_uuid'];
    ok( $uuid === $group_rows[1]['group_uuid'] && $uuid, 'combined links retain one stable group identity' );
    ok( $extras === call_api('GET','mapping-groups/'.$uuid)->get_data()['required_ids'], 'group stores complete required membership' );
    $combined_view = $combined_method->invoke(null, $admin, $extras, array());
    ok( 0 === $combined_view[0]['pooled_sources'] && ! SharedContent::owners($extras[0]), 'combined requirements never pool or inherit evidence' );
    $current_rows = Comparison::attach_mappings( Comparison::objectives($items[0])); $current_pairs=array(); foreach($current_rows as $row) { if(in_array($row['term_id'],$extras,true)) { $current_pairs[]=array('specific_id'=>$row['term_id'],'expected_revision'=>$row['maps_to'][0]['revision']); } }
    ok( 409 === call_api('PUT','mapping',array('specific_id'=>$extras[0],'shared_id'=>$science['id'],'relation'=>'equivalent','expected_revision'=>$current_pairs[0]['expected_revision']))->get_status(), 'individual editing cannot break a combined group' );
    ok( false === SkillMappings::remove($extras[0],$science['id']), 'legacy individual removal cannot break a combined group' );
    ok( 409 === call_api('POST','mapping-groups/remove',array('uuid'=>$uuid,'pairs'=>$pairs))->get_status(), 'stale group deletion rejected' );
    ok( 200 === call_api('POST','mapping-groups/remove',array('uuid'=>$uuid,'pairs'=>$current_pairs))->get_status(), 'combined group removal preserves objectives and evidence' );
    $group_payload['groups'][0]['pairs']=array_map(static function($id){return array('specific_id'=>$id,'expected_revision'=>'');},$extras);
    $recreated=call_api('POST','mapping-groups',$group_payload)->get_data();
    $group_payload['relation']='broader'; $group_payload['groups'][0]['pairs']=array_map(static function($row){return array('specific_id'=>$row['specific_id'],'expected_revision'=>$row['revision']);},$recreated);
    ok( 200 === call_api('POST','mapping-groups',$group_payload)->get_status(), 'complete group may be changed atomically to descriptive individual links' );
    foreach($extras as $id){ok('broader'===SkillMappings::for_skill($id)['maps_to'][0]['relation'],'whole group conversion updates every member'); SkillMappings::remove($id,$science['id']);}
    $current = Comparison::shared_skill( $shared['id'] );
    $renamed = Comparison::save_shared( $shared['id'], array( 'name' => 'Renamed equation ' . $tag, 'subject' => 'General', 'topic' => 'Equations', 'expected_revision' => $current['revision'] ) );
    ok( $renamed['id'] === $shared['id'] && $renamed['uuid'] === $shared['uuid'], 'rename and grouping preserve stable identity' );
    $mapping_post_reads = 0;
    $mapping_query_probe = static function ( $query ) use ( &$mapping_post_reads, $wpdb ) {
        if ( preg_match( '/\b(?:FROM|JOIN)\s+`?' . preg_quote( $wpdb->posts, '/' ) . '`?\b/i', $query ) ) { ++$mapping_post_reads; }
        return $query;
    };
    add_filter( 'query', $mapping_query_probe );
    $mapping_summary = SkillMappings::for_skill( $objectives[0] );
    remove_filter( 'query', $mapping_query_probe );
    ok( 0 === $mapping_post_reads, 'mapping summaries do not expand lesson or course resources' );
    ok( $mapping_summary['maps_to'][0]['shared']['name'] === $renamed['name'], 'mapping summary reflects a renamed target without stale cache' );
    ok( array( 'id', 'name', 'code' ) === array_keys( $mapping_summary['maps_to'][0]['shared'] ), 'mapping endpoint summary contract retained' );
    ok( is_wp_error( Comparison::save_shared( $shared['id'], array( 'name' => 'Stale', 'expected_revision' => $current['revision'] ) ) ), 'stale skill edits rejected' );
    foreach ( array( OHMYLMS_LESSON_CPT, OHMYLMS_QUIZ_CPT, OHMYLMS_ASSIGNMENT_CPT ) as $type ) {
        $post = wp_insert_post( array( 'post_type' => $type, 'post_title' => 'Shared resource ' . $tag, 'post_status' => 'publish' ) ); $posts[] = $post;
        ok( ! is_wp_error( SharedContent::link( $shared['id'], $post, true ) ), 'connect ' . $type );
        ok( has_term( $shared['id'], Taxonomy::NAME, $post ), 'resource references shared identity' );
    }
    ok( 1 === count( SharedContent::listing( $shared['id'], array( 'kind' => 'quiz' ) )['items'] ), 'quizzes reusable through shared skill' );
    ok( 1 === count( SharedContent::owners( $objectives[0] ) ) && ! SharedContent::owners( $objectives[2] ), 'equivalent content inheritance excludes reference-only links' );
    $preview = call_api( 'POST', 'roadmaps/preview', array( 'syllabus_ids' => $items ) );
    ok( 200 === $preview->get_status(), 'source roadmap preview succeeds' );
    $preview = $preview->get_data();
    ok( array( $shared['id'] ) === array_column( $preview['skills'], 'id' ), 'mapped shared skills deduplicate across equivalent and related sources' );
    ok( 93 === $preview['unmapped'], 'preview includes all source pages and counts unmapped objectives' );
    ok( 400 === call_api( 'POST', 'roadmaps/preview', array( 'syllabus_ids' => array( $items[0], $items[0] ) ) )->get_status(), 'duplicate source rejected' );
    ok( 400 === call_api( 'POST', 'roadmaps/preview', array( 'syllabus_ids' => array( array( $items[0] ) ) ) )->get_status(), 'malformed source rejected without PHP warnings' );
    ok( 400 === call_api( 'POST', 'roadmaps/preview', array( 'syllabus_ids' => array( 99999999 ) ) )->get_status(), 'missing source rejected' );
    ok( 409 === call_api( 'POST', 'roadmaps', array( 'name' => 'Invalid source roadmap', 'syllabus_ids' => $items, 'skill_ids' => array( $science['id'] ) ) )->get_status(), 'unmapped shared skill cannot enter a source roadmap' );
    $from_sources = call_api( 'POST', 'roadmaps', array( 'name' => 'Source roadmap ' . $tag, 'syllabus_ids' => $items, 'skill_ids' => array( $shared['id'] ) ) );
    ok( 200 === $from_sources->get_status(), 'source roadmap creation succeeds' );
    $from_sources = $from_sources->get_data(); $items[] = $from_sources['syllabus']['id'];
    ok( 3 === count( $from_sources['roadmap_sources'] ), 'roadmap saves source IDs names and versions' );
    $created_for_roadmap=call_api('POST','shared-skills/save',array('name'=>'Roadmap library skill '.$tag,'roadmap_id'=>(int)$from_sources['syllabus']['id']))->get_data(); $terms[]=$created_for_roadmap['id'];
    ok( 2 === Comparison::shared_skills(array('roadmap_id'=>$from_sources['syllabus']['id'],'search'=>$tag))['total'], 'new skill is associated with its Roadmap before any mapping or step save' );
    wp_delete_term($created_for_roadmap['id'],Taxonomy::NAME);
    ok( $from_sources['roadmap_sources'] === Syllabus::outline( $from_sources['syllabus']['id'] )['roadmap_sources'], 'source provenance persists when reopening' );
    $editor = call_api( 'GET', 'roadmaps/' . $from_sources['syllabus']['id'] )->get_data();
    ok( array_slice( $items, 0, 3 ) === $editor['syllabus_ids'], 'editor opens only saved sources' );
    ok( 1 === Comparison::shared_skills( array( 'roadmap_id' => $editor['id'], 'search' => $tag ) )['total'], 'roadmap library excludes unrelated shared skills' );
    $edit_data = array( 'name' => 'Renamed roadmap ' . $tag, 'syllabus_ids' => array( $items[1] ), 'skill_ids' => array( $science['id'], $shared['id'] ), 'expected_revision' => $editor['revision'] );
    $edited = call_api( 'PUT', 'roadmaps/' . $editor['id'], $edit_data );
    ok( 200 === $edited->get_status(), 'roadmap rename sources and ordered skill save succeeds' );
    $edited = $edited->get_data();
    ok( $edit_data['name'] === $edited['name'] && array( $items[1] ) === $edited['syllabus_ids'] && $edit_data['skill_ids'] === array_column( $edited['skills'], 'id' ), 'editor saves exact name source selection and step order' );
    $folder_data = $edit_data;
    $folder_data['expected_revision'] = $edited['revision'];
    $folder_data['folders'] = array( array( 'id' => $edited['folders'][0]['id'], 'name' => $edited['folders'][0]['name'], 'skill_ids' => array() ), array( 'id' => 0, 'name' => 'Algebra folder ' . $tag, 'skill_ids' => $edit_data['skill_ids'] ) );
    $folder_save = call_api( 'PUT', 'roadmaps/' . $editor['id'], $folder_data );
    ok( 200 === $folder_save->get_status(), 'new Roadmap folder and shared skill assignments save atomically' );
    $edited = $folder_save->get_data();
    ok( 2 === count( $edited['folders'] ) && $edited['folders'][1]['id'] === $edited['skills'][0]['folder_id'], 'folder persists with shared steps' );
    $folder_page = call_api( 'GET', 'objectives/' . $editor['id'], array( 'group' => $edited['folders'][1]['id'] ) )->get_data();
    ok( 2 === $folder_page['total'], 'roadmap as syllabus filters by shared skill folder' );
    $folder_data['expected_revision'] = $edited['revision'];
    $folder_data['folders'][1]['id'] = $group;
    ok( 400 === call_api( 'PUT', 'roadmaps/' . $editor['id'], $folder_data )->get_status(), 'source syllabus folder cannot be used as a Roadmap folder' );
    $folder_data['folders'] = $edited['folders']; $folder_data['folders'][0]['skill_ids'] = array( $shared['id'] );
    ok( 400 === call_api( 'PUT', 'roadmaps/' . $editor['id'], $folder_data )->get_status(), 'duplicate folder placement rejected' );
    ok( $edited['revision'] === Roadmaps::get( $editor['id'] )['revision'], 'invalid folder writes leave Roadmap unchanged' );
    ok( 409 === call_api( 'PUT', 'roadmaps/' . $editor['id'], $edit_data )->get_status(), 'stale roadmap save rejected' );
    $edit_data['expected_revision'] = $edited['revision']; $edit_data['skill_ids'] = array();
    ok( 200 === call_api( 'PUT', 'roadmaps/' . $editor['id'], $edit_data )->get_status(), 'source roadmap may have no shared skills yet' );
    $blank_source = Items::create( array( 'name' => 'Unmapped source ' . $tag, 'is_syllabus' => true ) ); $blank_source['id'] = (int) $blank_source['id']; $items[] = $blank_source['id'];
    $blank = call_api( 'POST', 'roadmaps', array( 'name' => 'Empty roadmap ' . $tag, 'syllabus_ids' => array( $blank_source['id'] ) ) );
    ok( 200 === $blank->get_status(), 'name and sources create roadmap without mappings' );
    $blank = $blank->get_data(); $items[] = $blank['syllabus']['id'];
    ok( 0 === $blank['totals']['skills'] && 'draft' === get_post_status( $blank['syllabus']['course_id'] ), 'empty roadmap starts as a draft' );
    $roadmap = call_api( 'POST', 'roadmaps', array( 'name' => 'General roadmap ' . $tag, 'subject' => 'Level 10', 'grade' => '10', 'skill_ids' => array( $science['id'], $shared['id'] ) ) );
    ok( 200 === $roadmap->get_status(), 'roadmap creation: ' . wp_json_encode( $roadmap->get_data() ) ); $roadmap = $roadmap->get_data(); $items[] = $roadmap['syllabus']['id'];
    ok( array_column( $roadmap['contents'][0]['groups'][0]['skills'], 'term_id' ) === array( $science['id'], $shared['id'] ), 'cross-subject roadmap preserves ordered references' );
    ok( 'draft' === get_post_status( $roadmap['syllabus']['course_id'] ), 'new roadmap stays draft' );
    $roadmap_item = Items::get( $roadmap['syllabus']['id'] );
    ok( 0 === (int) $roadmap_item['parent_id'] && 0 === (int) $roadmap_item['position'], 'shared roadmap is saved first at the curriculum root' );
    ok( $roadmap['syllabus']['id'] === Items::child_ids( 0 )[0], 'shared roadmap precedes existing top-level curriculums' );
    ok( 200 === call_api( 'PUT', 'configuration', array( 'subject' => 'General study plan', 'syllabus_ids' => $items ) )->get_status(), 'multiple syllabus plan configured' );
    ok( count( $items ) === count( get_option( Comparison::OPTION )['General study plan'] ), 'configuration is not limited to two sources' );
    $empty = SubjectProgress::progress( $admin, 'General study plan' ); ok( ! $empty['syllabuses'], 'draft syllabuses hidden from learner profiles' );
    wp_update_post( array( 'ID' => $roadmap['syllabus']['course_id'], 'post_status' => 'publish' ) );
    $progress = SubjectProgress::progress( $admin, 'General study plan' );
    ok( 1 === count( $progress['syllabuses'] ) && 2 === count( $progress['shared'] ), 'published roadmap shows shared skills across library groupings' );
    ok( 2 === count( $progress['syllabuses'][0]['audit']['unassessed'] ) && ! $progress['syllabuses'][0]['audit']['unmapped'], 'unassessed roadmap abilities are not mapping failures' );
    $definitions = Server::tools(); ok( ! $definitions['save_shared_skill']['readonly'] && $definitions['list_shared_skills']['readonly'], 'MCP annotations distinguish writes from reads' );
    $execute = new ReflectionMethod( Abilities::class, 'execute' ); $execute->setAccessible( true );
    $mcp = $execute->invoke( null, 'list_shared_skills', $definitions['list_shared_skills'], array( 'search' => $tag ) ); ok( 2 === count( $mcp['data']['items'] ), 'MCP GET uses subject-agnostic service' );
    $mcp = $execute->invoke( null, 'connect_shared_skill_content', $definitions['connect_shared_skill_content'], array( 'id' => $shared['id'], 'content_id' => $posts[0], 'linked' => false ) ); ok( ! is_wp_error( $mcp ) && ! has_term( $shared['id'], Taxonomy::NAME, $posts[0] ), 'MCP write body dispatches to normal content service' );
    $student = wp_insert_user( array( 'user_login' => 'shared-test-' . $tag, 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
    wp_set_current_user( $student );
    ok( 403 === call_api( 'GET', 'syllabuses' )->get_status(), 'non-admin comparison reads denied' );
    ok( 403 === call_api( 'POST', 'roadmaps/preview', array( 'syllabus_ids' => array( $items[0] ) ) )->get_status(), 'student roadmap source preview denied' );
    ok( 403 === call_api( 'GET', 'audit/' . $admin )->get_status(), 'student cannot audit another account' );
    ok( 403 === call_api( 'PUT', 'mapping', $mapping )->get_status(), 'student mapping writes denied' );
    ok( 200 === call_api( 'GET', 'audit', array( 'student_id' => $admin ) )->get_status(), 'own-account audit remains available without accepting another identity' );
    wp_set_current_user( 0 );
    ok( 401 === call_api( 'GET', 'syllabuses' )->get_status(), 'anonymous source reads denied' );
    ok( 401 === call_api( 'PUT', 'mapping', $mapping )->get_status(), 'anonymous writes denied' );
    ok( is_wp_error( $execute->invoke( null, 'list_shared_skills', $definitions['list_shared_skills'], array() ) ), 'MCP internal REST dispatch retains permissions' );
    wp_set_current_user( $admin );
    $row = call_api( 'GET', 'objectives/' . $items[0], array( 'search' => 'L1' ) )->get_data()['items'][0]; $old = $row['maps_to'][0];
    ok( 409 === call_api( 'POST', 'mapping/remove', array( 'specific_id' => $objectives[0], 'shared_id' => $shared['id'], 'expected_revision' => 'wrong' ) )->get_status(), 'stale remove rejected' );
    ok( 200 === call_api( 'POST', 'mapping/remove', array( 'specific_id' => $objectives[0], 'shared_id' => $shared['id'], 'expected_revision' => $old['revision'] ) )->get_status(), 'current remove succeeds' );
    echo "Shared skills integration: $checks checks passed.\n";
} finally {
    wp_set_current_user( $admin ); update_option( Comparison::OPTION, $before ); update_option( 'ohmylms_integrations', $integrations );
    foreach ( $items as $id ) { $item = Items::get( $id ); if ( $item ) { $course = $item['course_id']; $root = $item['skill_root_id']; Items::delete( $id, 'delete', true ); if ( $course ) { wp_delete_post( $course, true ); } if ( $root ) { wp_delete_term( $root, Taxonomy::NAME ); } } }
    foreach ( $terms as $id ) { wp_delete_term( $id, Taxonomy::NAME ); }
    foreach ( $posts as $id ) { wp_delete_post( $id, true ); }
    if ( $student && ! is_wp_error( $student ) ) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $student ); }
}
