<?php
/** Pure unit checks for the xAPI emitter (no WordPress database). */
define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
function __( $text ) {
	return $text;
}
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function wp_json_encode( $value, $flags = 0 ) {
	return json_encode( $value, $flags );
}
function wp_parse_url( $url ) {
	return parse_url( $url );
}
function untrailingslashit( $value ) {
	return rtrim( $value, '/\\' );
}
require dirname( __DIR__ ) . '/vendor/autoload.php';

use OhMyLMS\XApi\Identity;
use OhMyLMS\XApi\LrsClient;
use OhMyLMS\XApi\Outbox;
use OhMyLMS\XApi\ResponseFormat;
use OhMyLMS\XApi\Settings;
use OhMyLMS\XApi\StatementBuilder as S;
use OhMyLMS\XApi\Uuid;
use OhMyLMS\XApi\Vocabulary;

$checks = 0;
function check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$checks;
}

// UUIDs: the published RFC 4122 / Python example proves the v5 implementation.
check( Uuid::v5( '6ba7b810-9dad-11d1-80b4-00c04fd430c8', 'python.org' ) === '886313e1-3b8a-5372-9b90-0c9aee199e5d', 'v5 matches the known vector' );
$site = Uuid::v4();
check( Uuid::is_valid( $site ) && '4' === $site[14] && in_array( $site[19], array( '8', '9', 'a', 'b' ), true ), 'v4 has the right version and variant' );
check( Uuid::v5( $site, 'lesson-completed:1:2' ) === Uuid::v5( $site, 'lesson-completed:1:2' ), 'same event, same ID' );
check( Uuid::v5( $site, 'lesson-completed:1:2' ) !== Uuid::v5( $site, 'lesson-completed:1:3' ), 'different event, different ID' );
check( Uuid::v5( $site, 'x' ) !== Uuid::v5( Uuid::v4(), 'x' ), 'different sites never share an ID for the same event' );
check( '5' === Uuid::v5( $site, 'x' )[14], 'v5 sets the version nibble' );
check( ! Uuid::is_valid( 'nope' ) && ! Uuid::is_valid( null ) && ! Uuid::is_valid( '886313e1-3b8a-5372-9b90-0c9aee199e5' ), 'malformed UUIDs rejected' );
try {
	Uuid::v5( 'not-a-uuid', 'x' );
	check( false, 'invalid namespace accepted' );
} catch ( InvalidArgumentException $expected ) {
	check( true, '' );
}

// Vocabulary.
$verb = Vocabulary::verb( 'passed' );
check( 'http://adlnet.gov/expapi/verbs/passed' === $verb['id'] && 'passed' === $verb['display']['en-US'], 'ADL verb IRI and display' );
try {
	Vocabulary::verb( 'frobnicated' );
	check( false, 'unknown verb accepted' );
} catch ( InvalidArgumentException $expected ) {
	check( true, '' );
}
check( 'http://adlnet.gov/expapi/activities/cmi.interaction' === Vocabulary::activity_type( 'interaction' ), 'interaction activity type' );
check( 'http://adlnet.gov/expapi/activities/objective' === Vocabulary::activity_type( 'skill' ), 'skill activity type' );
check( 'choice' === Vocabulary::interaction_type( 'single-choice' ) && 'numeric' === Vocabulary::interaction_type( 'numerical' ) && 'sequencing' === Vocabulary::interaction_type( 'reorder' ), 'interaction types map' );
check( 'other' === Vocabulary::interaction_type( 'structured' ) && 'other' === Vocabulary::interaction_type( 'made-up' ), 'unknown question types are "other"' );

// Duration: ISO 8601, centisecond precision.
foreach ( array(
	array( 0, 'PT0S' ),
	array( 90.5, 'PT1M30.5S' ),
	array( 3600, 'PT1H' ),
	array( 3661.25, 'PT1H1M1.25S' ),
	array( 59.999, 'PT1M' ),
	array( 0.004, 'PT0S' ),
	array( -5, 'PT0S' ),
) as list( $seconds, $expected ) ) {
	check( S::duration( $seconds ) === $expected, "duration $seconds => " . S::duration( $seconds ) . " (expected $expected)" );
}
check( '2026-10-13T08:30:00Z' === S::timestamp( gmmktime( 8, 30, 0, 10, 13, 2026 ) ), 'timestamp is UTC ISO 8601' );

// Results: score rules.
$result = S::result(
	array(
		'raw' => 7.5,
		'max' => 10,
		'success' => true,
	)
);
check( 7.5 === $result['score']['raw'] && 0.75 === $result['score']['scaled'] && 10.0 === (float) $result['score']['max'] && 0.0 === (float) $result['score']['min'] && true === $result['success'], 'score, scaled and success' );
$over = S::result(
	array(
		'raw' => 12,
		'max' => 10,
	)
);
check( array( 'raw' => 12.0 ) === $over['score'], 'a raw score above its maximum is sent alone, never as an invalid range' );
$neg = S::result(
	array(
		'raw' => -1,
		'max' => 10,
	)
);
check( array( 'raw' => -1.0 ) === $neg['score'], 'a raw score below the minimum is sent alone' );
$zero_max = S::result(
	array(
		'raw' => 0,
		'max' => 0,
	)
);
check( array( 'raw' => 0.0 ) === $zero_max['score'], 'a zero maximum never divides' );
$failed = S::result(
	array(
		'raw'     => 0,
		'max'     => 4,
		'success' => false,
	)
);
check( false === $failed['success'] && 0.0 === $failed['score']['scaled'], 'false success and a zero score are kept' );
check( ! isset( S::result( array( 'completion' => null ) )['completion'] ), 'null flags are omitted' );
check( 'a[,]b' === S::result( array( 'response' => 'a[,]b' ) )['response'] && array() === S::result( array( 'response' => '' ) ), 'empty responses are omitted' );
check( 'PT1M30S' === S::result( array( 'seconds' => 90 ) )['duration'], 'duration in a result' );
check( 0.5 === S::result( array( 'scaled' => 0.5 ) )['score']['scaled'] && ! isset( S::result( array( 'scaled' => 3 ) )['score'] ), 'a standalone scaled score must be within -1 and 1' );

// Context.
$context = S::context(
	array(
		'registration' => 'r',
		'language'     => 'en-US',
		'parent'       => array( 'id' => 'p' ),
		'grouping'     => array( 'id' => 'g' ),
		'extensions'   => array( 'https://x' => 1 ),
	)
);
check( 'OhMyLMS' === $context['platform'] && 'r' === $context['registration'] && array( 'id' => 'p' ) === $context['contextActivities']['parent'][0] && array( 'id' => 'g' ) === $context['contextActivities']['grouping'][0], 'context nests activities in arrays' );
check( array( 'platform' => 'OhMyLMS' ) === S::context( array() ), 'an empty context is just the platform' );

// Statements.
$actor     = array(
	'objectType' => 'Agent',
	'account'    => array(
		'homePage' => 'https://school.test',
		'name'     => 'abc',
	),
);
$object    = array(
	'objectType' => 'Activity',
	'id'         => 'https://school.test/xapi/activities/course/9',
);
$statement = S::build(
	array(
		'id'        => $site,
		'actor'     => $actor,
		'verb'      => 'completed',
		'object'    => $object,
		'timestamp' => 0,
		'result'    => array( 'completion' => null ),
		'context'   => array(
			'registration' => null,
			'extensions'   => array(),
		),
	)
);
check( array( 'id', 'actor', 'verb', 'object', 'timestamp' ) === array_keys( $statement ), 'empty result and context are dropped: ' . implode( ',', array_keys( $statement ) ) );
check( '1970-01-01T00:00:00Z' === $statement['timestamp'] && 'http://adlnet.gov/expapi/verbs/completed' === $statement['verb']['id'], 'statement fields' );
$pruned = S::prune(
	array(
		'a' => false,
		'b' => 0,
		'c' => '0',
		'd' => null,
		'e' => array(),
		'f' => array( 'g' => null ),
	)
);
check( array( 'a' => false, 'b' => 0, 'c' => '0' ) === $pruned, 'prune keeps false, 0 and "0" and drops null and empty arrays' );

// Response format.
check( null === ResponseFormat::format( null ) && null === ResponseFormat::format( array() ) && null === ResponseFormat::format( '' ), 'nothing answered, nothing reported' );
check( '42' === ResponseFormat::format( 42 ) && '3/4' === ResponseFormat::format( '3/4' ), 'scalars' );
check( 'Right' === ResponseFormat::format( array( 11 ), array( '11' => 'Right', '12' => 'Wrong' ) ), 'option IDs become the text the learner saw' );
check( 'Right[,]Wrong' === ResponseFormat::format( array( 11, 12 ), array( '11' => 'Right', '12' => 'Wrong' ) ), 'lists use the xAPI [,] separator' );
check( 'A[.]1[,]B[.]2' === ResponseFormat::format( array( 'A' => '1', 'B' => '2' ) ), 'pairs use [.] and [,]' );
check( 'Left[.]Right' === ResponseFormat::format( array( 5 => 6 ), array( '5' => 'Left', '6' => 'Right' ) ), 'matching pairs resolve both sides' );
check( 'true' === ResponseFormat::format( true ) && 'false' === ResponseFormat::format( false ) && null === ResponseFormat::format( null ), 'booleans' );
$long = ResponseFormat::format( str_repeat( 'é', 3000 ) );
check( 1000 === mb_strlen( $long, 'UTF-8' ) && mb_check_encoding( $long, 'UTF-8' ), 'long answers are cut by characters, not bytes' );

// Identity.
$a = Identity::actor_for( 'pseudonym', 7, 'kid@example.com', 'https://school.test', 'key-one' );
$b = Identity::actor_for( 'pseudonym', 7, 'kid@example.com', 'https://school.test', 'key-one' );
$c = Identity::actor_for( 'pseudonym', 8, 'kid@example.com', 'https://school.test', 'key-one' );
$d = Identity::actor_for( 'pseudonym', 7, 'kid@example.com', 'https://school.test', 'key-two' );
check( $a === $b, 'a learner keeps the same pseudonym' );
check( $a['account']['name'] !== $c['account']['name'] && $a['account']['name'] !== $d['account']['name'], 'pseudonyms differ per learner and per key' );
check( 64 === strlen( $a['account']['name'] ) && false === strpos( wp_json_encode( $a ), 'kid@' ) && 'https://school.test' === $a['account']['homePage'], 'a pseudonym reveals neither email nor user ID' );
check( '7' === Identity::actor_for( 'user_id', 7, '', 'https://school.test', 'k' )['account']['name'], 'user ID mode' );
check( 'mailto:kid@example.com' === Identity::actor_for( 'mbox', 7, 'kid@example.com', 'https://school.test', 'k' )['mbox'], 'mbox mode' );
check( null === Identity::actor_for( 'mbox', 7, '', 'https://school.test', 'k' ) && null === Identity::actor_for( 'mbox', 7, 'not-an-email', 'https://school.test', 'k' ), 'mbox mode skips learners without an email' );
check( null === Identity::actor_for( 'pseudonym', 0, '', 'https://school.test', 'k' ), 'no learner, no actor' );

// LRS client.
check( 'https://lrs.test/xapi/statements' === LrsClient::url( 'https://lrs.test/xapi/', '/statements' ), 'resource URL' );
$headers = LrsClient::headers( 'key', 'secret' );
check( 'Basic ' . base64_encode( 'key:secret' ) === $headers['Authorization'] && '1.0.3' === $headers['X-Experience-API-Version'], 'auth and version headers' );
foreach ( array(
	array( 200, 'ok' ),
	array( 204, 'ok' ),
	array( 409, 'ok' ),
	array( 0, 'retry' ),
	array( 408, 'retry' ),
	array( 429, 'retry' ),
	array( 500, 'retry' ),
	array( 503, 'retry' ),
	array( 401, 'hold' ),
	array( 403, 'hold' ),
	array( 404, 'hold' ),
	array( 405, 'hold' ),
	array( 301, 'hold' ),
	array( 307, 'hold' ),
	array( 400, 'reject' ),
	array( 413, 'reject' ),
	array( 422, 'reject' ),
) as list( $code, $class ) ) {
	check( LrsClient::interpret( $code )['class'] === $class, "HTTP $code => $class" );
}
check( 'timeout' === LrsClient::interpret( 0, 'timeout' )['message'], 'transport errors keep their message' );
check( false !== strpos( LrsClient::interpret( 400, 'bad verb' )['message'], 'bad verb' ), 'rejections explain themselves' );
check( '' === LrsClient::interpret( 200 )['message'], 'success has no message' );

// Which statements an LRS says it stored (a 200 alone is not proof: a real LRS drops invalid ones from a batch).
$one = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
$two = '11111111-2222-4333-8444-555555555555';
check( array( $one, $two ) === LrsClient::stored_ids( wp_json_encode( array( $one, strtoupper( $two ) ) ) ), 'stored IDs are listed, lower-cased' );
check( array() === LrsClient::stored_ids( '[]' ), 'an empty list means "stored nothing", not "unknown"' );
foreach ( array( '', 'not json', '{"a":1}', '["not-a-uuid"]', '[1,2]', '"x"', 'null', '[["nested"]]' ) as $odd ) {
	check( null === LrsClient::stored_ids( $odd ), "an unreadable body is unknown, not a list: $odd" );
}

// Outbox retry timing: 1, 2, 4 ... minutes, capped at an hour.
check( array( 60, 120, 240, 480, 960, 1920, 3600, 3600 ) === array_map( array( Outbox::class, 'backoff_seconds' ), range( 1, 8 ) ), 'backoff doubles then caps' );
check( 3600 === Outbox::backoff_seconds( 500 ) && 60 === Outbox::backoff_seconds( 0 ), 'backoff is safe at the extremes' );

// Endpoint validation.
check( 'https://lrs.test/xapi' === Settings::normalize_endpoint( ' https://lrs.test/xapi/ ' ), 'trailing slash and spaces removed' );
check( 'http://localhost:8080/data/xAPI' === Settings::normalize_endpoint( 'http://localhost:8080/data/xAPI/?x=1#y' ), 'query and fragment removed, port kept' );
check( '' === Settings::normalize_endpoint( 'ftp://lrs.test' ) && '' === Settings::normalize_endpoint( 'javascript:alert(1)' ) && '' === Settings::normalize_endpoint( '' ) && '' === Settings::normalize_endpoint( 'lrs.test/xapi' ), 'only http(s) URLs with a host' );
check( '' === Settings::normalize_endpoint( 'https://user:pass@lrs.test/xapi' ), 'credentials do not belong in the URL' );
$kinds = array_keys( Settings::kind_groups() );
check( count( array_diff( array_unique( array_values( Settings::kind_groups() ) ), array_keys( Settings::groups() ) ) ) === 0, 'every statement kind belongs to a known switch' );
check( 7 === count( $kinds ), 'seven statement kinds' );

echo "$checks xAPI unit checks passed.\n";
