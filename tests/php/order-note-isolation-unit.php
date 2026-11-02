<?php
/** Explicit note reads suspend known list exclusions without leaking a changed hook registry. */
$filters = array();
$fail = false;
$checks = 0;
function note_check( $condition, $message ) {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
    ++$GLOBALS['checks'];
}
function filter_key( $callback ) { return is_array( $callback ) ? implode( '::', $callback ) : $callback; }
function has_filter( $name, $callback ) { return $GLOBALS['filters'][filter_key( $callback )] ?? false; }
function remove_filter( $name, $callback, $priority ) { unset( $GLOBALS['filters'][filter_key( $callback )] ); }
function add_filter( $name, $callback, $priority, $accepted ) { $GLOBALS['filters'][filter_key( $callback )] = $priority; }
function get_comments( $args ) {
    note_check( ! isset( $GLOBALS['filters']['WC_Comments::exclude_order_comments'] ), 'WooCommerce list exclusion is suspended.' );
    note_check( ! isset( $GLOBALS['filters']['OhMyLMS\Commerce\Comments::exclude_order_comments'] ), 'LMS list exclusion is suspended.' );
    note_check( isset( $GLOBALS['filters']['unrelated'] ), 'Unrelated query filters stay active.' );
    note_check( $args['type'] === 'order_note' && $args['post_id'] === 42 && $args['number'] === 100, 'The query stays scoped to the requested order and type.' );
    if ( $GLOBALS['fail'] ) { throw new RuntimeException( 'query failed' ); }
    return array();
}
require __DIR__ . '/../../packages/e-commerce/includes/Utility/order-functions.php';
foreach ( array( false, true ) as $fail ) {
    $filters = array( 'OhMyLMS\Commerce\Comments::exclude_order_comments' => 20, 'WC_Comments::exclude_order_comments' => 0, 'unrelated' => 30 );
    $before = $filters;
    try { ohmylms_ecommerce_get_order_notes( array( 'order_id' => 42, 'limit' => 100 ) ); }
    catch ( RuntimeException $error ) { note_check( $fail && $error->getMessage() === 'query failed', 'Query errors propagate after cleanup.' ); }
    ksort( $filters ); ksort( $before );
    note_check( $filters === $before, 'Exact callback priorities are restored on success and failure.' );
}
$fail = false;
$filters = array( 'unrelated' => 30 );
ohmylms_ecommerce_get_order_notes( array( 'order_id' => 42, 'limit' => 100 ) );
note_check( $filters === array( 'unrelated' => 30 ), 'An absent exclusion is not installed by reading notes.' );
echo "Order note isolation: $checks checks passed.\n";
