<?php
/**
 * Pure unit checks for the cmi5 helpers (no WordPress database).
 *
 *   php -d extension=zip tests/cmi5-unit.php
 */
define( 'ABSPATH', __DIR__ . '/' );
function __( $text ) {
	return $text;
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}
require dirname( __DIR__ ) . '/vendor/autoload.php';

use OhMyLMS\Cmi5\ContentPath;
use OhMyLMS\Cmi5\CourseStructure as CS;
use OhMyLMS\Cmi5\InvalidPackage;
use OhMyLMS\Cmi5\LaunchUrl;
use OhMyLMS\Cmi5\PackageExtractor as PE;
use OhMyLMS\Cmi5\Routes;
use OhMyLMS\Cmi5\Tokens;

if ( ! class_exists( 'ZipArchive' ) ) {
	exit( "These tests need the ZIP extension: php -d extension=zip tests/cmi5-unit.php\n" );
}

$checks = 0;
function check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$checks;
}
/** The errors a structure produces, or null when it parses. */
function errors_of( $xml ) {
	try {
		CS::parse( $xml );
		return null;
	} catch ( InvalidPackage $e ) {
		return $e->errors();
	}
}
function mentions( $errors, $needle ) {
	foreach ( (array) $errors as $error ) {
		if ( false !== stripos( $error, $needle ) ) {
			return true;
		}
	}
	return false;
}
function structure( $body, $course_attrs = 'id="https://example.com/course"' ) {
	return '<?xml version="1.0" encoding="utf-8"?><courseStructure xmlns="https://w3id.org/xapi/profiles/cmi5/v1/CourseStructure.xsd">'
		. '<course ' . $course_attrs . '><title><langstring lang="en-US">Course</langstring></title><description><langstring lang="en-US">About</langstring></description></course>'
		. $body . '</courseStructure>';
}
function au( $id, $extra = '', $url = 'index.html', $title = true ) {
	return '<au id="' . $id . '" ' . $extra . '>' . ( $title ? '<title><langstring lang="en-US">AU ' . $id . '</langstring></title>' : '' )
		. '<description><langstring lang="en-US">d</langstring></description><url>' . $url . '</url></au>';
}

// ---- Course structure: a valid package ----
$ok = CS::parse( structure( au( 'https://example.com/au/1' ) ) );
check( 'https://example.com/course' === $ok['course']['id'] && 'Course' === $ok['course']['title'] && 'About' === $ok['course']['description'], 'course fields' );
$a = $ok['nodes'][0];
check( 'au' === $a['type'] && 'NotApplicable' === $a['moveOn'] && 'AnyWindow' === $a['launchMethod'] && null === $a['masteryScore'] && 'index.html' === $a['url'], 'AU defaults: moveOn NotApplicable, AnyWindow, no mastery score' );

$full = CS::parse(
	structure(
		'<block id="https://example.com/b1"><title><langstring lang="en-US">Block one</langstring></title><description><langstring lang="en-US">x</langstring></description>'
		. au( 'https://example.com/au/a', 'moveOn="CompletedAndPassed" masteryScore="0.85" launchMethod="OwnWindow" activityType="http://adlnet.gov/expapi/activities/assessment"', 'a/start.html?x=1#top' )
		. '<block id="https://example.com/b2"><title><langstring lang="en-US">Nested</langstring></title><description><langstring lang="en-US">x</langstring></description>' . au( 'https://example.com/au/b', 'moveOn="Passed"', 'https://cdn.example.com/au/index.html' ) . '</block></block>'
		. au( 'https://example.com/au/c', 'moveOn="Completed"', './c.html' )
	)
);
$aus = CS::flatten_aus( $full['nodes'] );
check( 3 === count( $aus ), 'three AUs in document order' );
check( 'CompletedAndPassed' === $aus[0]['moveOn'] && 0.85 === $aus[0]['masteryScore'] && 'OwnWindow' === $aus[0]['launchMethod'] && 'a/start.html?x=1#top' === $aus[0]['url'], 'AU attributes read' );
check( array( 'https://example.com/b1' ) === $aus[0]['blocks'] && array( 'https://example.com/b1', 'https://example.com/b2' ) === $aus[1]['blocks'] && array() === $aus[2]['blocks'], 'block ancestry per AU' );
check( 'c.html' === $aus[2]['url'] && 'https://cdn.example.com/au/index.html' === $aus[1]['url'], 'a leading ./ is dropped and absolute URLs are kept' );
check( 0 === count( CS::missing_files( $full['nodes'], array( 'a/start.html', 'c.html' ) ) ), 'relative URLs that exist are fine, absolute ones are never checked' );
$missing = CS::missing_files( $full['nodes'], array( 'c.html' ) );
check( 1 === count( $missing ) && mentions( $missing, 'a/start.html' ), 'a relative URL to a missing file is reported' );
check( mentions( CS::missing_files( CS::parse( structure( au( 'https://e.com/a', '', 'My%20File.html' ) ) )['nodes'], array( 'My File.html' ) ), 'x' ) === false, 'percent-encoded URLs match the decoded file name' );

// Languages: the preferred one, then English, then anything.
$multi = '<au id="https://e.com/m"><title><langstring lang="fr-FR">Titre</langstring><langstring lang="en-US">Title</langstring><langstring lang="mn">Garchig</langstring></title><url>i.html</url></au>';
check( 'Title' === CS::parse( structure( $multi ) )['nodes'][0]['title'], 'English is the default language' );
check( 'Garchig' === CS::parse( structure( $multi ), 'mn_MN' )['nodes'][0]['title'], 'the site language wins when present (mn_MN matches mn)' );
check( 'Titre' === CS::parse( structure( $multi ), 'fr' )['nodes'][0]['title'], 'a bare language matches its regional variant' );

// ---- Course structure: every rule that rejects a package ----
check( mentions( errors_of( '<courseStructure><course id="urn:x"><title><langstring>t</langstring></title></course></courseStructure>' ), 'no assignable units' ), 'a course needs at least one AU' );
check( mentions( errors_of( '<notCmi5/>' ), 'courseStructure' ), 'wrong root element' );
check( mentions( errors_of( '<courseStructure>' . au( 'urn:a' ) . '</courseStructure>' ), '<course> element is missing' ), 'no course element' );
check( mentions( errors_of( structure( au( 'urn:a' ) . '<course id="urn:two"><title><langstring>t</langstring></title></course>' ) ), 'more than one' ), 'two course elements' );
check( mentions( errors_of( structure( au( 'urn:a' ) . au( 'urn:a' ) ) ), 'more than once' ), 'duplicate IDs' );
check( mentions( errors_of( structure( au( 'https://example.com/course' ) ) ), 'more than once' ), 'an AU may not reuse the course ID' );
check( mentions( errors_of( structure( au( 'not an iri' ) ) ), 'not an IRI' ), 'an ID must be an IRI' );
check( mentions( errors_of( structure( '<au><title><langstring>t</langstring></title><url>i.html</url></au>' ) ), 'no id' ), 'an AU without an ID' );
check( mentions( errors_of( structure( au( 'urn:a', '', 'i.html', false ) ) ), 'no title' ), 'an AU without a title' );
check( mentions( errors_of( structure( au( 'urn:a', 'moveOn="Sometimes"' ) ) ), 'moveOn' ), 'an unknown moveOn' );
foreach ( array( '1.5', '-0.1', '0.12345', 'abc', '.5', '2', '1.00001' ) as $bad ) {
	check( mentions( errors_of( structure( au( 'urn:a', 'masteryScore="' . $bad . '"' ) ) ), 'masteryScore' ), "masteryScore $bad is rejected" );
}
foreach ( array( '0', '1', '0.85', '1.0', '0.0001', '0.8' ) as $good ) {
	check( null === errors_of( structure( au( 'urn:a', 'masteryScore="' . $good . '"' ) ) ), "masteryScore $good is accepted" );
}
check( mentions( errors_of( structure( au( 'urn:a', 'launchMethod="NewTab"' ) ) ), 'launchMethod' ), 'an unknown launchMethod' );
check( mentions( errors_of( structure( au( 'urn:a', 'activityType="not an iri"' ) ) ), 'activityType' ), 'an activityType that is not an IRI' );

foreach ( array(
	'javascript:alert(1)'            => 'scheme',
	'JaVaScRiPt:alert(1)'            => 'scheme',
	'data:text/html,<b>x</b>'        => 'scheme',
	'file:///etc/passwd'             => 'scheme',
	'app://my-app/start'             => 'scheme',
	'ftp://example.com/x'            => 'scheme',
	'https://'                       => 'host',
	'/etc/passwd'                    => 'slash',
	'//evil.example.com/x'           => 'slash',
	'../outside.html'                => 'leaves',
	'a/../../outside.html'           => 'leaves',
	'?only=query'                    => 'empty',
	'has space.html'                 => 'spaces',
	'back\\slash.html'               => 'backslashes',
) as $url => $expected ) {
	check( mentions( errors_of( structure( au( 'urn:a', '', $url ) ) ), $expected ), "URL \"$url\" is rejected ($expected)" );
}
foreach ( array( 'index.html', 'a/b/c.html', 'story.html?x=1', 'https://cdn.example.com/x/index.html', 'http://localhost:8080/au' ) as $url ) {
	check( null === errors_of( structure( au( 'urn:a', '', $url ) ) ), "URL \"$url\" is accepted" );
}
check( mentions( errors_of( structure( '<au id="urn:a"><title><langstring>t</langstring></title></au>' ) ), 'no <url>' ), 'an AU with no URL' );

// XML attacks and malformed input.
$xxe = '<?xml version="1.0"?><!DOCTYPE c [<!ENTITY x SYSTEM "file:///etc/passwd">]><courseStructure><course id="urn:c"><title><langstring>&x;</langstring></title></course></courseStructure>';
check( mentions( errors_of( $xxe ), 'DOCTYPE' ), 'an external entity declaration is refused before parsing' );
check( mentions( errors_of( '<!ENTITY a "b"><courseStructure/>' ), 'DOCTYPE' ), 'a bare entity declaration is refused' );
check( mentions( errors_of( '<!DOCTYPE lolz [<!ENTITY lol "lol"><!ENTITY lol2 "&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;">]><courseStructure/>' ), 'DOCTYPE' ), 'an entity-expansion bomb is refused' );
check( mentions( errors_of( '<courseStructure><course' ), 'well-formed' ), 'malformed XML is reported' );
check( mentions( errors_of( '' ), 'empty' ) && mentions( errors_of( "  \n " ), 'empty' ), 'empty input' );
check( mentions( errors_of( str_repeat( 'x', CS::MAX_BYTES + 1 ) ), '5 MB' ), 'oversized input is refused before parsing' );

// Limits.
$deep = '';
$close = '';
for ( $i = 0; $i < 22; $i++ ) {
	$deep  .= '<block id="urn:b' . $i . '"><title><langstring>b</langstring></title>';
	$close .= '</block>';
}
check( mentions( errors_of( structure( $deep . au( 'urn:leaf' ) . $close ) ), 'levels deep' ), 'blocks nested too deeply' );
$many = '';
for ( $i = 0; $i <= CS::MAX_AUS; $i++ ) {
	$many .= au( 'urn:au:' . $i );
}
check( mentions( errors_of( structure( $many ) ), 'more than 5000' ), 'more AUs than the limit' );
$thousand = '';
for ( $i = 0; $i < 1200; $i++ ) {
	$thousand .= au( 'urn:au:' . $i );
}
check( null === errors_of( structure( $thousand ) ), 'a course with more than 1000 AUs imports (the spec expects it)' );
$errors = errors_of( structure( str_repeat( '<au><url>i.html</url></au>', 40 ) ) );
check( count( $errors ) <= CS::MAX_ERRORS + 1 && mentions( $errors, 'More problems' ), 'error output is capped' );
$warn = CS::parse( structure( au( 'urn:a' ) . '<mystery/>' . '<au id="urn:k"><title><langstring>t</langstring></title><url>i.html</url><entitlementKey><courseStructure>k</courseStructure></entitlementKey></au>' ) );
check( 2 === count( $warn['warnings'] ), 'unknown elements and entitlement keys warn instead of failing: ' . json_encode( $warn['warnings'] ) );

// ---- Entry names inside the ZIP ----
foreach ( array(
	'a/b.html'       => 'a/b.html',
	'a\\b.html'      => 'a/b.html',
	'./x.js'         => 'x.js',
	'a//b.css'       => 'a/b.css',
	'a/./b.css'      => 'a/b.css',
	'.DS_Store'      => '.DS_Store',
	'dir/._res.png'  => 'dir/._res.png',
) as $in => $out ) {
	check( PE::normalize_entry( $in ) === $out, "entry \"$in\" normalizes to \"$out\"" );
}
foreach ( array( '../x.html', 'a/../../x.html', '/etc/passwd', 'C:/x.html', 'C:\\x.html', 'a/b.html:stream', "a/b\0.html", "a/b\n.html", 'a/.hidden', 'evil.php.', 'evil.php ', '', '/', '..', str_repeat( 'a', 241 ), str_repeat( 'a/', 31 ) . 'x.html' ) as $bad ) {
	check( false === PE::normalize_entry( $bad ), 'entry is refused: ' . json_encode( $bad ) );
}
check( PE::is_ignorable( '__MACOSX/a/._b.png' ) && PE::is_ignorable( '.DS_Store' ) && PE::is_ignorable( 'x/Thumbs.db' ) && PE::is_ignorable( 'x/._y' ) && ! PE::is_ignorable( 'x/y.png' ), 'archiver junk is recognised' );
foreach ( array( 'index.html', 'a/b.JS', 'f.woff2', 'v/clip.mp4', 'img/logo.SVG', 'data.json', 'my.file.name.png' ) as $good ) {
	check( PE::is_allowed_type( $good ), "type is allowed: $good" );
}
foreach ( array( 'x.php', 'x.PHP', 'x.phtml', 'x.phar', 'x.php.png', 'x.png.php', 'x.php5.jpg', 'x.html.exe', 'noextension', 'a/.htaccess', 'x.sh', 'x.pl.txt', 'x.swf', 'x.svgz', 'x.exe', 'x.bat', 'web.config', 'x.jsp.png' ) as $bad ) {
	check( ! PE::is_allowed_type( $bad ), "type is refused: $bad" );
}

// ---- Extracting a real ZIP ----
function make_zip( array $entries, array $unix_modes = array() ) {
	$path = tempnam( sys_get_temp_dir(), 'cmi5zip' );
	$zip  = new ZipArchive();
	$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	foreach ( $entries as $name => $body ) {
		$zip->addFromString( $name, $body );
	}
	foreach ( $unix_modes as $name => $mode ) {
		$zip->setExternalAttributesName( $name, ZipArchive::OPSYS_UNIX, $mode << 16 );
	}
	$zip->close();
	return $path;
}
function temp_dest() {
	return sys_get_temp_dir() . '/cmi5dest-' . bin2hex( random_bytes( 6 ) );
}
function extract_errors( $zip, $limits = array() ) {
	$dest = temp_dest();
	try {
		PE::extract( $zip, $dest, $limits );
		PE::remove( $dest );
		return null;
	} catch ( InvalidPackage $e ) {
		$left = file_exists( $dest );
		PE::remove( $dest );
		return array( 'errors' => $e->errors(), 'dest_left_behind' => $left );
	}
}
$good_entries = array(
	'cmi5.xml'        => structure( au( 'urn:a', '', 'story/index.html' ) ),
	'story/index.html' => '<html><script src="app.js"></script></html>',
	'story/app.js'     => 'console.log(1);',
	'story/logo.png'   => 'PNGDATA',
	'__MACOSX/._x'     => 'junk',
	'.DS_Store'        => 'junk',
);
$zip  = make_zip( $good_entries );
$dest = temp_dest();
$done = PE::extract( $zip, $dest );
check( array( 'cmi5.xml', 'story/index.html', 'story/app.js', 'story/logo.png' ) === array_keys( $done['files'] ), 'only real files are extracted; archiver junk is skipped: ' . implode( ',', array_keys( $done['files'] ) ) );
check( 'console.log(1);' === file_get_contents( $dest . '/story/app.js' ) && ! file_exists( $dest . '/.DS_Store' ) && ! file_exists( $dest . '/__MACOSX' ), 'file contents are intact and junk is absent' );
check( $done['bytes'] === array_sum( $done['files'] ) && false !== strpos( $done['structure'], 'courseStructure' ), 'bytes and the structure are returned' );
check( 0 === count( CS::missing_files( CS::parse( $done['structure'] )['nodes'], array_keys( $done['files'] ) ) ), 'the extracted package passes the missing-file check' );
PE::remove( $dest );
check( ! file_exists( $dest ), 'remove() deletes the whole folder' );
unlink( $zip );

$cases = array(
	'zip slip (..)'             => array( array( 'cmi5.xml' => 'x', '../evil.html' => 'x' ), 'unsafe path' ),
	'zip slip (nested ..)'      => array( array( 'cmi5.xml' => 'x', 'a/../../evil.html' => 'x' ), 'unsafe path' ),
	'absolute path'             => array( array( 'cmi5.xml' => 'x', '/tmp/evil.html' => 'x' ), 'unsafe path' ),
	'a PHP file'                => array( array( 'cmi5.xml' => 'x', 'shell.php' => '<?php system($_GET[0]);' ), 'may not contain' ),
	'a PHP file hidden by .png' => array( array( 'cmi5.xml' => 'x', 'shell.php.png' => '<?php' ), 'may not contain' ),
	'an .htaccess'              => array( array( 'cmi5.xml' => 'x', '.htaccess' => 'AddType x' ), 'unsafe path' ),
	'a file with no extension'  => array( array( 'cmi5.xml' => 'x', 'LICENSE' => 'x' ), 'may not contain' ),
	'no cmi5.xml'               => array( array( 'index.html' => 'x' ), 'cmi5.xml was not found at the root' ),
	'cmi5.xml in a subfolder'   => array( array( 'wrapper/cmi5.xml' => 'x', 'wrapper/index.html' => 'x' ), 'cmi5.xml was not found at the root' ),
	'the same file twice'       => array( array( 'cmi5.xml' => 'x', 'a.html' => '1', './a.html' => '2' ), 'more than once' ),
);
foreach ( $cases as $label => list( $entries, $needle ) ) {
	$result = extract_errors( $zip = make_zip( $entries ) );
	unlink( $zip );
	check( null !== $result && mentions( $result['errors'], $needle ), "$label is refused ($needle): " . json_encode( $result ) );
	check( false === $result['dest_left_behind'], "$label leaves nothing on disk" );
}
// Every problem is reported together, not one at a time.
$result = extract_errors( $zip = make_zip( array( 'a.php' => 'x', '../b.html' => 'x', 'c.exe' => 'x' ) ) );
unlink( $zip );
check( count( $result['errors'] ) >= 4, 'all problems are reported in one pass: ' . count( $result['errors'] ) );

// A symlink entry (unix mode 0120777) is refused.
$result = extract_errors( $zip = make_zip( array( 'cmi5.xml' => 'x', 'link.html' => '/etc/passwd' ), array( 'link.html' => 0120777 ) ) );
unlink( $zip );
check( null !== $result && mentions( $result['errors'], 'link or special file' ), 'a symlink entry is refused' );
// A regular-file mode is fine.
$zip = make_zip( $good_entries, array( 'story/app.js' => 0100644 ) );
check( null === extract_errors( $zip ), 'a regular-file mode is accepted' );
unlink( $zip );

// Limits.
$zip = make_zip( $good_entries );
check( mentions( extract_errors( $zip, array( 'files' => 2 ) )['errors'], 'more than 2 entries' ), 'too many entries' );
check( mentions( extract_errors( $zip, array( 'file_bytes' => 10 ) )['errors'], 'larger than' ), 'a file over the per-file limit' );
check( mentions( extract_errors( $zip, array( 'total_bytes' => 50 ) )['errors'], 'larger than' ), 'a package over the total limit' );
unlink( $zip );
$zip = make_zip( array( 'cmi5.xml' => 'x', 'big.json' => str_repeat( '0', 3 * 1048576 ) ) );
check( mentions( extract_errors( $zip, array( 'ratio' => 50 ) )['errors'], 'compressed suspiciously well' ), 'a compression bomb is refused' );
check( null === extract_errors( $zip, array( 'ratio' => 100000 ) ), 'the same file is fine when the ratio limit allows it' );
unlink( $zip );

// A folder that already exists is never touched, even when the import then fails.
$existing = temp_dest();
mkdir( $existing );
file_put_contents( $existing . '/precious.txt', 'keep me' );
$zip = make_zip( $good_entries );
$threw = false;
try {
	PE::extract( $zip, $existing );
} catch ( InvalidPackage $e ) {
	$threw = mentions( $e->errors(), 'already exists' );
}
check( $threw && 'keep me' === file_get_contents( $existing . '/precious.txt' ), 'an existing folder is refused and left untouched (cleanup only removes what the import created)' );
unlink( $zip );
PE::remove( $existing );
check( mentions( ( function () { try { PE::extract( '/definitely/not/a.zip', temp_dest() ); } catch ( InvalidPackage $e ) { return $e->errors(); } } )(), 'could not be opened' ), 'a missing or non-ZIP file is reported' );
$not_zip = tempnam( sys_get_temp_dir(), 'cmi5nz' );
file_put_contents( $not_zip, 'this is not a zip' );
check( mentions( ( function () use ( $not_zip ) { try { PE::extract( $not_zip, temp_dest() ); } catch ( InvalidPackage $e ) { return $e->errors(); } } )(), 'could not be opened' ), 'garbage is not a ZIP' );
unlink( $not_zip );

// ---- Serving files: paths, types, ranges ----
foreach ( array(
	'a/b.html'         => 'a/b.html',
	'a%2Fb.html'       => 'a/b.html',
	'a/./b.html'       => 'a/b.html',
	'/a/b.html'        => 'a/b.html',
	'My%20File.html'   => 'My File.html',
	'%252e%252e/x.js'  => '%2e%2e/x.js',
) as $in => $out ) {
	check( ContentPath::clean( $in ) === $out, "request path \"$in\" becomes \"$out\"" );
}
foreach ( array( '../x', '%2e%2e/x', 'a/%2e%2e/b', 'a/%2E%2E/b', '..%2fx', 'a\\b', 'a%5Cb', "a%00b", 'a:b', '', '//', '.', '%2e' ) as $bad ) {
	check( false === ContentPath::clean( $bad ), 'request path is refused: ' . $bad );
}
check( 'text/html; charset=utf-8' === ContentPath::mime( 'a/B.HTML' ) && 'application/javascript; charset=utf-8' === ContentPath::mime( 'x.js' ) && 'video/mp4' === ContentPath::mime( 'v.mp4' ) && 'font/woff2' === ContentPath::mime( 'f.woff2' ) && 'application/octet-stream' === ContentPath::mime( 'x.unknown' ), 'content types' );
check( array( 0, 99 ) === ContentPath::parse_range( 'bytes=0-99', 1000 ), 'range: start-end' );
check( array( 500, 999 ) === ContentPath::parse_range( 'bytes=500-', 1000 ), 'range: open end' );
check( array( 900, 999 ) === ContentPath::parse_range( 'bytes=-100', 1000 ), 'range: last N bytes' );
check( array( 0, 999 ) === ContentPath::parse_range( 'bytes=0-99999', 1000 ), 'range: end past the file is clamped' );
check( array( 0, 999 ) === ContentPath::parse_range( 'bytes=-5000', 1000 ), 'range: suffix longer than the file is the whole file' );
check( false === ContentPath::parse_range( 'bytes=1000-', 1000 ) && false === ContentPath::parse_range( 'bytes=2000-3000', 1000 ) && false === ContentPath::parse_range( 'bytes=500-100', 1000 ) && false === ContentPath::parse_range( 'bytes=-0', 1000 ), 'range: unsatisfiable ranges' );
foreach ( array( '', 'bytes=0-1,5-9', 'items=0-5', 'bytes=-', 'bytes=a-b', 'garbage' ) as $ignored ) {
	check( null === ContentPath::parse_range( $ignored, 1000 ), 'range ignored (whole file sent): ' . $ignored );
}
check( null === ContentPath::parse_range( 'bytes=0-5', 0 ), 'range on an empty file is ignored' );

// ---- Tokens ----
$t1 = Tokens::content();
$t2 = Tokens::fetch();
check( Tokens::is_hex_token( $t1 ) && Tokens::is_hex_token( $t2 ) && $t1 !== $t2 && Tokens::content() !== $t1, 'content and fetch tokens are 64 random hex characters' );
check( 64 === strlen( Tokens::hash( $t1 ) ) && Tokens::hash( $t1 ) === Tokens::hash( $t1 ) && Tokens::hash( $t1 ) !== Tokens::hash( $t2 ) && Tokens::hash( $t1 ) !== $t1, 'a token hashes to something else, deterministically' );
$auth = Tokens::auth();
check( 32 === strlen( base64_decode( $auth, true ) ) && Tokens::auth() !== $auth, 'the auth token is a valid base64 Basic credential of 32 random bytes' );
foreach ( array( '', 'xyz', str_repeat( 'g', 64 ), str_repeat( 'A', 64 ), str_repeat( 'a', 63 ), str_repeat( 'a', 65 ), null, 5 ) as $bad ) {
	check( ! Tokens::is_hex_token( $bad ), 'not a token: ' . json_encode( $bad ) );
}

// ---- Launch URL ----
$cmi5 = array(
	'endpoint'     => 'https://lms.test/ohmylms-cmi5/lrs/',
	'fetch'        => 'https://lms.test/ohmylms-cmi5/fetch/abc',
	'actor'        => '{"objectType":"Agent","account":{"homePage":"https://lms.test","name":"u 1"}}',
	'registration' => '11111111-2222-4333-8444-555555555555',
	'activityId'   => 'https://lms.test/xapi/activities/au/7',
);
$url = LaunchUrl::build( 'https://lms.test/ohmylms-cmi5/c/T/index.html', $cmi5 );
parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $back );
check( $back === $cmi5, 'every cmi5 parameter round-trips through URL encoding (actor JSON, IRIs, spaces)' );
check( false === strpos( $url, ' ' ) && false !== strpos( $url, 'actor=%7B%22objectType' ), 'values are percent-encoded' );
$url = LaunchUrl::build( 'https://x.test/au.html?lang=mn&endpoint=evil&x=1#slide2', $cmi5 );
parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $back );
check( 'mn' === $back['lang'] && '1' === $back['x'] && $back['endpoint'] === $cmi5['endpoint'], "the AU's own parameters are kept, and a colliding cmi5 name is overridden by the LMS" );
check( '#slide2' === substr( $url, -7 ) && 1 === substr_count( $url, 'endpoint=' ), 'the fragment stays at the end and a colliding name appears once' );
check( 'https://x.test/a.html?fetch=f' === LaunchUrl::build( 'https://x.test/a.html', array( 'fetch' => 'f' ) ), 'missing parameters are simply omitted' );
check( 'https://lms.test/ohmylms-cmi5/c/' . $t1 . '/a/My%20File.html?x=1#top' === LaunchUrl::content_url( 'https://lms.test/', $t1, 'a/My File.html?x=1#top' ), 'content URL encodes each path segment and keeps the query and fragment' );
check( 'https://lms.test/ohmylms-cmi5/c/' . $t1 . '/a/My%20File.html' === LaunchUrl::content_url( 'https://lms.test/', $t1, 'a/My%20File.html' ), 'an already-encoded path is not double-encoded' );
check( 'https://lms.test/ohmylms-cmi5/fetch/' . $t2 === LaunchUrl::fetch_url( 'https://lms.test/', $t2 ) && 'https://lms.test/ohmylms-cmi5/lrs/' === LaunchUrl::endpoint( 'https://lms.test/' ), 'fetch and endpoint URLs' );

// ---- Routes ----
$r = Routes::parse( '/ohmylms-cmi5/c/' . $t1 . '/a/b.html?x=1', '/' );
check( array( 'route' => 'c', 'token' => $t1, 'path' => 'a/b.html' ) === $r, 'content route: ' . json_encode( $r ) );
$r = Routes::parse( '/shop/ohmylms-cmi5/c/' . $t1 . '/a%2Fb.html', '/shop/' );
check( 'c' === $r['route'] && 'a%2Fb.html' === $r['path'], 'a site in a subfolder, with the path left encoded for ContentPath to decode' );
check( array( 'route' => 'fetch', 'token' => $t2 ) === Routes::parse( '/ohmylms-cmi5/fetch/' . $t2, '/' ), 'fetch route' );
check( array( 'route' => 'lrs', 'path' => 'statements' ) === Routes::parse( '/ohmylms-cmi5/lrs/statements?statementId=1', '/' ), 'lrs route with a resource' );
check( array( 'route' => 'lrs', 'path' => 'activities/state' ) === Routes::parse( '/ohmylms-cmi5/lrs/activities/state', '/' ), 'lrs route with a nested resource' );
check( array( 'route' => 'lrs', 'path' => '' ) === Routes::parse( '/ohmylms-cmi5/lrs/', '/' ), 'the lrs root with a trailing slash' );
check( array( 'route' => 'lrs', 'path' => '' ) === Routes::parse( '/ohmylms-cmi5/lrs', '/' ), 'the lrs root without a trailing slash' );
foreach ( array( '/', '/blog/hello', '/ohmylms-cmi5', '/ohmylms-cmi5-evil/x', '/xohmylms-cmi5/c/x', '/other/ohmylms-cmi5/c/' . $t1 . '/a' ) as $other ) {
	check( null === Routes::parse( $other, '/' ), "not a cmi5 request: $other" );
}
foreach ( array( '/ohmylms-cmi5/', '/ohmylms-cmi5/c/', '/ohmylms-cmi5/c/short/a.html', '/ohmylms-cmi5/c/' . $t1, '/ohmylms-cmi5/c/' . $t1 . '/', '/ohmylms-cmi5/fetch/', '/ohmylms-cmi5/fetch/nope', '/ohmylms-cmi5/fetch/' . $t2 . '/extra', '/ohmylms-cmi5/unknown/x' ) as $invalid ) {
	check( array( 'route' => 'invalid' ) === Routes::parse( $invalid, '/' ), "malformed cmi5 request is 'invalid', not passed on to WordPress: $invalid" );
}

echo "$checks cmi5 unit checks passed.\n";
