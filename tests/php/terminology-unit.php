<?php
/**
 * Unit checks for the site-wide Chapter to Unit wording. No WordPress needed.
 *
 * Run: php tests/php/terminology-unit.php
 */
define( 'ABSPATH', __DIR__ );
require dirname( __DIR__, 2 ) . '/includes/Utility/Terminology.php';
function check( $value, $message ) {
	if ( ! $value ) {
		throw new RuntimeException( $message );
	}
}
$GLOBALS['__option'] = array();
function get_option( $name, $default = false ) {
	return $GLOBALS['__option'][ $name ] ?? $default;
}
function sanitize_text_field( $text ) {
	return trim( strip_tags( (string) $text ) );
}
function __( $text, $domain = '' ) {
	return $text;
}
use OhMyLMS\Utility\Terminology as T;

// Defaults: on, Unit / Units.
check( 'Unit' === T::settings()['singular'] && 'Units' === T::settings()['plural'] && T::settings()['enabled'], 'Defaults' );
check( 'Add Unit' === T::rename( 'Add Chapter' ), 'Capitalized singular' );
check( 'All Units' === T::rename( 'All Chapters' ), 'Capitalized plural' );
check( 'Expand all units' === T::rename( 'Expand all chapters' ), 'Lowercase plural' );
check( 'Add your first unit to get started.' === T::rename( 'Add your first chapter to get started.' ), 'Lowercase singular in a sentence' );
check( 'UNITS' === T::rename( 'CHAPTERS' ), 'All caps' );
check( 'Unit 1: Marketing' === T::rename( 'Chapter 1: Marketing' ), 'Numbered title' );
check( 'Failed to link lesson to unit: %1$s - %2$s' === T::rename( 'Failed to link lesson to chapter: %1$s - %2$s' ), 'Placeholders survive' );

// Words that merely contain the letters are left alone.
check( 'Characters' === T::rename( 'Characters' ), 'Unrelated word untouched' );
check( 'Chapterhouse' === T::rename( 'Chapterhouse' ), 'No partial-word replacement' );
check( 'No match here' === T::rename( 'No match here' ), 'Strings without the word are returned as they are' );
check( 42 === T::rename( 42 ), 'Non-strings pass through' );

// Custom wording and its safety limits.
$GLOBALS['__option'][ T::OPTION ] = array(
	'enabled'  => true,
	'singular' => 'Module',
	'plural'   => 'Modules',
);
check( 'Add Module' === T::rename( 'Add Chapter' ) && 'Modules list' === T::rename( 'Chapters list' ), 'Custom words' );
check( 'module' === T::rename( 'chapter' ), 'Custom lowercase' );
$GLOBALS['__option'][ T::OPTION ] = array(
	'singular' => 'Topic',
	'plural'   => '',
);
check( 'Topics' === T::settings()['plural'], 'Missing plural is derived' );
$GLOBALS['__option'][ T::OPTION ] = array(
	'singular' => '<b>X</b>',
	'plural'   => str_repeat( 'a', 80 ),
);
check( 'X' === T::settings()['singular'] && 30 === strlen( T::settings()['plural'] ), 'Markup stripped and length limited' );
$GLOBALS['__option'][ T::OPTION ] = array(
	'singular' => '   ',
	'plural'   => '',
);
check( 'Unit' === T::settings()['singular'], 'Blank falls back to Unit' );

// Switched off restores the original wording.
$GLOBALS['__option'][ T::OPTION ] = array( 'enabled' => false );
check( 'Add Chapter' === T::rename( 'Add Chapter' ), 'Disabled leaves text alone' );
$GLOBALS['__option'][ T::OPTION ] = 'not-an-array';
check( T::settings()['enabled'], 'Corrupt option falls back to defaults' );

// Strings that already have their own translation are never rewritten.
$GLOBALS['__option'] = array();
check( 'Capítulo' === T::gettext( 'Capítulo', 'Chapter', 'ohmylms' ), 'Existing translation kept' );
check( 'Unit' === T::gettext( 'Chapter', 'Chapter', 'ohmylms' ), 'Untranslated English renamed' );
check( 'Chapter' === T::gettext( 'Chapter', 'Chapter', 'other-plugin' ), 'Other text domains untouched' );
check( 'Unit' === T::gettext_with_context( 'Chapter', 'Chapter', 'Post Type Singular Name', 'ohmylms' ), 'Context strings renamed' );
check( 'Units' === T::ngettext( 'Chapters', 'Chapter', 'Chapters', 3, 'ohmylms' ), 'Plural form renamed' );
check( 'Unit' === T::ngettext( 'Chapter', 'Chapter', 'Chapters', 1, 'ohmylms' ), 'Singular form renamed' );
check( 'Chapitres' === T::ngettext( 'Chapitres', 'Chapter', 'Chapters', 3, 'ohmylms' ), 'Translated plural kept' );
check( 'Units' === T::ngettext_with_context( 'Chapters', 'Chapter', 'Chapters', 2, 'ctx', 'ohmylms-pro' ), 'Pro domain and context' );

echo "terminology-unit: all checks passed\n";
