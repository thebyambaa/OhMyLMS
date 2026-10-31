<?php
if (PHP_SAPI !== 'cli') { exit; }
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
$config = json_decode(file_get_contents(getenv('OHMYLMS_TEST_CREDENTIALS')), true);
require $config['site'] . '/wp-load.php';
if (!defined('OHMYLMS_TEST_SITE') || DB_NAME !== 'ohmylms_source_test') { throw new RuntimeException('Requires disposable test site'); }
$checks = 0;
function verify_extension($condition, $message) { global $checks; if (!$condition) { throw new RuntimeException($message); } $checks++; }
function extension_request($method,$path,$data=[]) {$request=new WP_REST_Request($method,'/ohmylms/v1/'.$path);$request->set_body_params($data);return rest_do_request($request);}
$admin = get_user_by('login', $config['username']);
wp_set_current_user($admin->ID);
$ids=[];$content_ids=[];$student_id=0;$enrollment_id=0;
try {
    $manifest=extension_request('GET','extensions');
    verify_extension($manifest->get_status()===200,'Manifest inaccessible');
    verify_extension($manifest->get_data()['apiVersion']===1,'Manifest API version missing');
    $response=extension_request('POST','memberships',['name'=>'Isolated extension plan','status'=>'draft','regular_price'=>'100','extension_settings'=>['example-benefit'=>['benefit'=>'Office hours']]]);
    verify_extension($response->get_status()===201,'Membership create: '.wp_json_encode($response->get_data()));
    $id=$response->get_data()['id'];$ids[]=$id;
    verify_extension($response->get_data()['extension_settings']['example-benefit']['benefit']==='Office hours','Create did not save extension settings');
    $response=extension_request('PUT','memberships/'.$id,['name'=>'Updated extension plan','extension_settings'=>['example-benefit'=>['benefit'=>'Updated benefit']]]);
    verify_extension($response->get_status()===200,'Membership update failed');
    $response=extension_request('GET','extension-settings/membership/'.$id);
    verify_extension($response->get_data()['settings']->{'example-benefit'}['benefit']==='Updated benefit','Reopen lost settings');
    $response=extension_request('PUT','memberships/'.$id,['name'=>'Must not save','extension_settings'=>['example-benefit'=>['benefit'=>['invalid']]]]);
    verify_extension($response->get_status()===400,'Invalid schema accepted');
    verify_extension(get_the_title($id)==='Updated extension plan','Invalid extension data partially saved core fields');
    $response=extension_request('PUT','extension-settings/membership/'.$id,['settings'=>['unregistered'=>['x'=>1]]]);
    verify_extension($response->get_status()===400,'Unknown settings accepted');
    $response=extension_request('PUT','extension-settings/membership/'.$id,['settings'=>['example-benefit'=>['benefit'=>'OK','price'=>0]]]);
    verify_extension($response->get_status()===400,'Unknown nested field accepted');
    verify_extension((float)ohmylms_get_membership($id)->get_regular_price()===100.0,'Extension changed price');
    wp_set_current_user(0);
    verify_extension(extension_request('GET','extension-settings/membership/'.$id)->get_status()>=400,'Anonymous read accepted');
    verify_extension(extension_request('PUT','memberships/'.$id,['extension_settings'=>['example-benefit'=>['benefit'=>'Attack']]])->get_status()>=400,'Anonymous write accepted');
    wp_set_current_user($admin->ID);
    verify_extension(extension_request('GET','extension-settings/course/'.$id)->get_status()===404,'Wrong post type accepted');
    $question=new class {public function get_settings(){return ['expected'=>42];}};
    $type=\OhMyLMS\Extensions\Registry::get('question','example-number');
    verify_extension(call_user_func($type['grade'],['42'],$question)['correct'],'Custom correct answer failed');
    verify_extension(!call_user_func($type['grade'],['41'],$question)['correct'],'Custom wrong answer passed');
    verify_extension(!call_user_func($type['validate'],['not a number']),'Invalid answer accepted');
    $errors=new WP_Error();
    \OhMyLMS\Extensions\CheckoutFields::validate(['ohmylms_extension_example-reference'=>str_repeat('x',101)],$errors);
    verify_extension($errors->has_errors(),'Checkout length validation failed');
    $errors=new WP_Error();\OhMyLMS\Extensions\CheckoutFields::validate(['ohmylms_extension_example-reference'=>'Reference 12'],$errors);
    verify_extension(!$errors->has_errors(),'Valid checkout field rejected');
    $order=new class {public $meta=[];public function update_meta_data($key,$value){$this->meta[$key]=$value;}};
    \OhMyLMS\Extensions\CheckoutFields::save($order,['ohmylms_extension_example-reference'=>'Reference 12','price'=>0]);
    verify_extension($order->meta===['_ohmylms_extension_fields'=>['example-reference'=>'Reference 12']],'Checkout saved unregistered data');
    foreach ([
        ['courses',['name'=>'Isolated course','status'=>'draft']],
        ['lessons',['name'=>'Isolated reading','type'=>'example-reading','description'=>'Reading body','status'=>'draft']],
        ['quiz',['name'=>'Isolated quiz','status'=>'draft']],
        ['question',['name'=>'Expected number','settings'=>['type'=>'example-number','expected'=>42,'score'=>['value'=>1]]]],
    ] as [$route,$body]) {
        $response=extension_request('POST',$route,$body);
        verify_extension($response->get_status()===201,"$route create failed: ".wp_json_encode($response->get_data()));
        $content_id=$response->get_data()['id'];$ids[]=$content_id;$content_ids[$route]=$content_id;
        $response=extension_request('GET',$route.'/'.$content_id);
        verify_extension($response->get_status()===200,"$route reopen failed");
        if ($route==='question') {
            verify_extension($response->get_data()['settings']['expected']===42,'Custom authoring settings lost');
            $question=ohmylms_get_question($content_id);
            verify_extension(call_user_func($type['grade'],['42'],$question)['correct'],'Saved custom question grading failed');
            ob_start();call_user_func($type['render'],['id'=>$content_id],['id'=>1]);$html=ob_get_clean();
            verify_extension(strpos($html,'type="number"')!==false,'Custom question renderer failed');
        }
        if ($route==='lessons') {
            verify_extension($response->get_data()['type']==='example-reading','Custom lesson type lost');
            $lesson=ohmylms_get_lesson($content_id);ob_start();\OhMyLMS\Extensions\Bootstrap::lesson($lesson);$html=ob_get_clean();
            verify_extension(strpos($html,'Reading body')!==false,'Custom lesson render failed');
        }
    }
    $response=extension_request('POST','question',['name'=>'Invalid custom question','settings'=>['type'=>'example-number','expected'=>['bad'],'score'=>['value'=>1]]]);
    verify_extension($response->get_status()===400,'Custom authoring schema not enforced');
    $chapter=wp_insert_post(['post_type'=>'ohmylms-chapter','post_title'=>'Isolated chapter','post_status'=>'publish']);$ids[]=$chapter;
    $wpdb->insert($wpdb->prefix.'ohmylms_chapter_relationship',['course_id'=>$content_ids['courses'],'chapter_id'=>$chapter,'order_number'=>0]);
    $wpdb->insert($wpdb->prefix.'ohmylms_content_relationship',['chapter_id'=>$chapter,'content_id'=>$content_ids['quiz'],'content_type'=>'quiz','order_number'=>0]);
    $wpdb->insert($wpdb->prefix.'ohmylms_quiz_questions_relationship',['quiz_id'=>$content_ids['quiz'],'question_id'=>$content_ids['question'],'order_number'=>0]);
    $response=extension_request('PUT','quiz/'.$content_ids['quiz'],['settings'=>['allow_attempts'=>5,'time_limit'=>['value'=>1,'type'=>'minutes'],'passing_grade'=>['enabled'=>true,'value'=>1]]]);
    verify_extension($response->get_status()===200,'Quiz settings update failed');
    extension_request('PUT','question/'.$content_ids['question'],['settings'=>['type'=>'example-number','expected'=>42,'required'=>true,'score'=>['enabled'=>true,'value'=>1]]]);
    $student_id=wp_create_user('ohmylms-fixture-'.wp_generate_password(10,false),wp_generate_password(32));
    verify_extension(!is_wp_error($student_id),'Student fixture failed');
    wp_set_current_user($student_id);
    verify_extension(is_wp_error(\OhMyLMS\Quiz\Submission::start($content_ids['quiz'],$student_id)),'Unenrolled quiz access accepted');
    $wpdb->insert($wpdb->prefix.'ohmylms_user_enrollment',['user_id'=>$student_id,'course_id'=>$content_ids['courses'],'status'=>'enrolled','progress'=>'running','start_date'=>current_time('mysql')]);$enrollment_id=(int)$wpdb->insert_id;
    // Simulate the next request after enrollment; direct fixture inserts bypass request-local caches.
    $cache=new ReflectionProperty(\OhMyLMS\DataStores\StudentStore::class,'enrollment_cache');$cache->setAccessible(true);$cache->setValue(null,[]);
    $attempt=\OhMyLMS\Quiz\Submission::start($content_ids['quiz'],$student_id);
    verify_extension(!is_wp_error($attempt),'Enrolled quiz start failed: '.(is_wp_error($attempt)?$attempt->get_error_message():''));
    verify_extension(\OhMyLMS\Quiz\Submission::start($content_ids['quiz'],$student_id)===$attempt,'Duplicate start created a second attempt');
    $result=\OhMyLMS\Quiz\Submission::submit($content_ids['quiz'],$attempt,$student_id,[],'timeout');
    verify_extension(is_wp_error($result)&&$result->get_error_code()==='quiz_timer','Early timeout accepted');
    $result=\OhMyLMS\Quiz\Submission::submit($content_ids['quiz'],$attempt,$student_id,[999999=>['42']]);
    verify_extension(is_wp_error($result)&&$result->get_error_code()==='quiz_question','Foreign quiz question accepted');
    $result=\OhMyLMS\Quiz\Submission::submit($content_ids['quiz'],$attempt,$student_id,[$content_ids['question']=>['42']]);
    verify_extension(!is_wp_error($result)&&(float)$result['total']===1.0&&$result['status']==='completed','Custom answer submission failed');
    verify_extension(is_wp_error(\OhMyLMS\Quiz\Submission::submit($content_ids['quiz'],$attempt,$student_id,[$content_ids['question']=>['42']])),'Duplicate submission accepted');
    verify_extension((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_user_progress WHERE enrollment_id=%d AND content_id=%d",$enrollment_id,$content_ids['quiz']))===1,'Quiz completion did not update progress');
    $timeout_attempt=\OhMyLMS\Quiz\Submission::start($content_ids['quiz'],$student_id);
    verify_extension(!is_wp_error($timeout_attempt),'Second attempt failed');
    $wpdb->update($wpdb->prefix.'ohmylms_quiz_attempts',['start_date'=>date('Y-m-d H:i:s',current_time('timestamp')-120)],['id'=>$timeout_attempt]);
    // Versioned attempts keep their UTC deadline in the attempt context.
    $wpdb->update($wpdb->prefix.'ohmylms_attempt_context',['started_at'=>gmdate('Y-m-d H:i:s',time()-120),'deadline_at'=>gmdate('Y-m-d H:i:s',time()-60)],['attempt_id'=>$timeout_attempt]);
    $result=\OhMyLMS\Quiz\Submission::submit($content_ids['quiz'],$timeout_attempt,$student_id,[],'timeout');
    verify_extension(!is_wp_error($result)&&(float)$result['total']===0.0,'Expired unanswered attempt failed');
    wp_set_current_user($admin->ID);
    $cart=new \OhMyLMS\Commerce\Cart();
    verify_extension((bool)$cart->add_to_cart($ids[0]),'Membership cart fixture failed');
    $cart->calculate_totals();
    verify_extension((float)$cart->get_total('edit')===100.0,'Server cart price differs from membership price');
    $coupon=new \OhMyLMS\Commerce\Data\Coupon();
    $coupon_code='fixture-'.strtolower(wp_generate_password(10,false));
    $coupon->set_title($coupon_code);$coupon->set_code($coupon_code);$coupon->set_status('publish');$coupon->set_amount(10);$coupon->set_discount_type('flat-rate');
    $coupon_id=$coupon->save();$ids[]=$coupon_id;
    verify_extension($cart->apply_coupon($coupon_code),'Coupon was rejected');$cart->calculate_totals();
    verify_extension((float)$cart->get_total('edit')===90.0,'Coupon total is incorrect');
    $cart->remove_coupon($coupon_code);$cart->calculate_totals();
    verify_extension((float)$cart->get_total('edit')===100.0,'Coupon removal did not restore total');
    $cart->empty_cart();
    $order=new \OhMyLMS\Commerce\Data\Order();
    $order->set_total(100);$order->set_currency('MNT');$order->set_status('pending');$order->set_payment_method('qpay');$order->set_student_id($admin->ID);
    do_action('ohmylms_checkout_create_order',$order,['ohmylms_extension_example-reference'=>'Saved after ID']);
    $order_id=$order->save();$ids[]=$order_id;
    verify_extension($order_id>0,'Order fixture failed');
    do_action('ohmylms_checkout_order_created',$order);
    verify_extension(get_post_meta($order_id,'_ohmylms_extension_fields',true)['example-reference']==='Saved after ID','Checkout metadata hook lost fields before ID assigned');
    $qpay_settings=['enabled'=>'yes','testmode'=>'yes','test_client_id'=>'fixture','test_client_secret'=>'fixture','invoice_code'=>'fixture'];
    add_filter('pre_option_ohmylms_qpay_settings',static function()use($qpay_settings){return $qpay_settings;});
    $gateway=new \OhMyLMS\Commerce\Gateways\QPay\GatewayQPay();
    \OhMyLMS\Commerce\Gateways\QPay\QPayAPI::settings_changed($qpay_settings,$qpay_settings);
    $payment_mode='unpaid';$payment_requests=0;
    $mock=static function($pre,$args,$url)use(&$payment_mode,&$payment_requests){
        if(strpos($url,'https://merchant-sandbox.qpay.mn/')!==0)return $pre;
        $payment_requests++;
        if(strpos($url,'/auth/token')!==false)$body=['access_token'=>'isolated-fixture-token'];
        elseif(strpos($url,'/payment/check')!==false){
            if($payment_mode==='failure')return new WP_Error('mock_payment_failure','Mock network failure');
            $amount=['unpaid'=>0,'partial'=>1,'paid'=>100][$payment_mode];
            $body=['count'=>$amount?1:0,'paid_amount'=>$amount,'rows'=>$amount?[['payment_id'=>'fixture-payment','payment_status'=>'PAID','payment_amount'=>(string)$amount,'payment_currency'=>'MNT']]:[]];
        }else $body=['invoice_id'=>'fixture-invoice','qr_image'=>'aGVsbG8=','urls'=>[]];
        return ['response'=>['code'=>200,'message'=>'OK'],'headers'=>[],'body'=>wp_json_encode($body),'cookies'=>[]];
    };
    add_filter('pre_http_request',$mock,PHP_INT_MAX,3);
    try {
        $payment=$gateway->process_payment($order_id);
        verify_extension($payment['result']==='success'&&$payment['qpay_invoice_id']==='fixture-invoice','Mock invoice failed');
        $request=new WP_REST_Request('POST');$request->set_param('order_id',$order_id);
        $request->set_param('qpay_token',get_post_meta($order_id,'_qpay_callback_token',true));
        update_post_meta($order_id,'_qpay_checkout_ready',1);
        foreach(['unpaid','partial','failure'] as $payment_mode){
            $callback=$gateway->handle_callback($request);
            verify_extension(!in_array(ohmylms_ecommerce_get_order($order_id)->get_status(),['completed','processing'],true),"$payment_mode payment incorrectly completed order");
            if($payment_mode==='failure')verify_extension($callback->get_status()===503,'Payment failure was hidden');
        }
        $payment_mode='paid';$callback=$gateway->handle_callback($request);
        verify_extension($callback->get_status()===200&&in_array(ohmylms_ecommerce_get_order($order_id)->get_status(),['completed','processing'],true),'Verified full payment failed');
        $requests_before=$payment_requests;$callback=$gateway->handle_callback($request);
        verify_extension($callback->get_status()===200&&$requests_before===$payment_requests,'Duplicate callback repeated payment processing');
    } finally {remove_filter('pre_http_request',$mock,PHP_INT_MAX);\OhMyLMS\Commerce\Gateways\QPay\QPayAPI::settings_changed($qpay_settings,$qpay_settings);}
    echo "$checks isolated extension integration checks passed.\n";
} finally {
    wp_set_current_user($admin->ID);
    if($student_id&&!is_wp_error($student_id)){
        foreach(['ohmylms_quiz_attempts_answers','ohmylms_quiz_attempts'] as $table)$wpdb->delete($wpdb->prefix.$table,['student_id'=>$student_id]);
        if($enrollment_id)$wpdb->delete($wpdb->prefix.'ohmylms_user_progress',['enrollment_id'=>$enrollment_id]);
        $wpdb->delete($wpdb->prefix.'ohmylms_user_enrollment',['user_id'=>$student_id]);
        require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($student_id);
    }
    foreach($ids as $id)wp_delete_post($id,true);
}
