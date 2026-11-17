<?php
/**
 * The committed Composer class map must match the plugin's classes.
 *
 * Classes load from the optimized class map (composer.json `optimize-autoloader`), so a request
 * finds each class without checking the disk. A map entry whose file was moved or deleted breaks
 * loading, and a class missing from the map falls back to slower path lookups. Both fail here;
 * fix them with `composer dump-autoload`.
 *
 * @package OhMyLMS
 */

$root     = dirname( __DIR__, 2 );
$classmap = require $root . '/vendor/composer/autoload_classmap.php';
$failures = array();

// Every mapped file exists.
foreach ( $classmap as $class => $file ) {
	if ( ! is_file( $file ) ) {
		$failures[] = "mapped file is missing: $class => $file";
	}
}

/**
 * The class, interface, trait or enum a file declares, with its namespace.
 *
 * @param string $file PHP file.
 * @return string[] Fully qualified names.
 */
function autoload_classmap_declared( $file ) {
	$tokens    = token_get_all( (string) file_get_contents( $file ) );
	$namespace = '';
	$names     = array();
	$count     = count( $tokens );
	for ( $i = 0; $i < $count; $i++ ) {
		if ( ! is_array( $tokens[ $i ] ) ) {
			continue;
		}
		if ( T_NAMESPACE === $tokens[ $i ][0] ) {
			$namespace = '';
			for ( $j = $i + 1; $j < $count && ';' !== $tokens[ $j ] && '{' !== $tokens[ $j ]; $j++ ) {
				if ( is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_STRING, T_NAME_QUALIFIED ), true ) ) {
					$namespace .= $tokens[ $j ][1];
				}
			}
			continue;
		}
		$kinds = array( T_CLASS, T_INTERFACE, T_TRAIT );
		if ( defined( 'T_ENUM' ) ) {
			$kinds[] = T_ENUM;
		}
		if ( ! in_array( $tokens[ $i ][0], $kinds, true ) ) {
			continue;
		}
		// Skip `Foo::class` and anonymous classes.
		$before = $i - 1;
		while ( $before >= 0 && is_array( $tokens[ $before ] ) && T_WHITESPACE === $tokens[ $before ][0] ) {
			--$before;
		}
		if ( $before >= 0 && ( ( is_array( $tokens[ $before ] ) && in_array( $tokens[ $before ][0], array( T_DOUBLE_COLON, T_NEW ), true ) ) ) ) {
			continue;
		}
		for ( $j = $i + 1; $j < $count; $j++ ) {
			if ( is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) {
				$names[] = ltrim( $namespace . '\\' . $tokens[ $j ][1], '\\' );
				break;
			}
			if ( ! is_array( $tokens[ $j ] ) || T_WHITESPACE !== $tokens[ $j ][0] ) {
				break;
			}
		}
	}
	return $names;
}

// Every PSR-4 class under includes/ is in the map.
$includes = $root . '/includes';
$checked  = 0;
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $includes, FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $entry ) {
	if ( 'php' !== $entry->getExtension() ) {
		continue;
	}
	$relative = str_replace( '\\', '/', substr( $entry->getPathname(), strlen( $includes ) + 1 ) );
	$expected = 'OhMyLMS\\' . str_replace( '/', '\\', substr( $relative, 0, -4 ) );
	if ( ! in_array( $expected, autoload_classmap_declared( $entry->getPathname() ), true ) ) {
		continue; // Not a PSR-4 class file (functions, templates, or a non-conforming legacy class).
	}
	++$checked;
	if ( ! isset( $classmap[ $expected ] ) ) {
		$failures[] = "class is not in the class map: $expected";
	}
}

if ( $failures ) {
	fwrite( STDERR, "FAILED: the Composer class map is out of date; run `composer dump-autoload`.\n  " . implode( "\n  ", array_slice( $failures, 0, 20 ) ) . "\n" );
	exit( 1 );
}
echo 'OK: ' . count( $classmap ) . " mapped classes exist; $checked PSR-4 classes in includes/ are mapped\n";
