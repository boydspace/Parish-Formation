<?php
/**
 * Build and verify an installable Parish Formation release archive.
 *
 * Run with: composer build
 */

$plugin_root = dirname( __DIR__ );
$main_file   = $plugin_root . '/parish-formation.php';
$source      = file_get_contents( $main_file );

if ( ! preg_match( '/^\s*\* Version:\s*([^\s]+)$/mi', $source, $header_match ) ||
	! preg_match( "/define\(\s*'PARISH_FORMATION_VERSION',\s*'([^']+)'\s*\)/", $source, $constant_match ) ) {
	fwrite( STDERR, "The plugin version could not be read.\n" );
	exit( 1 );
}

$version = trim( $header_match[1] );
if ( $version !== trim( $constant_match[1] ) ) {
	fwrite( STDERR, "Plugin header and runtime versions do not match.\n" );
	exit( 1 );
}

$required_root_files = array( 'parish-formation.php', 'uninstall.php', 'readme.txt', 'README.md', 'LICENSE', 'composer.json', 'composer.lock' );
$included_dirs       = array( 'admin', 'assets', 'includes', 'public', 'vendor' );

foreach ( $required_root_files as $file ) {
	if ( ! is_file( $plugin_root . '/' . $file ) ) {
		fwrite( STDERR, "Required release file is missing: {$file}\n" );
		exit( 1 );
	}
}

$files = array();
foreach ( $required_root_files as $file ) {
	$files[ $file ] = $plugin_root . '/' . $file;
}
foreach ( $included_dirs as $directory ) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $plugin_root . '/' . $directory, FilesystemIterator::SKIP_DOTS )
	);
	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}
		$relative           = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $plugin_root ) + 1 ) );
		$files[ $relative ] = $file->getPathname();
	}
}
ksort( $files );

$dist_dir = $plugin_root . '/dist';
if ( ! is_dir( $dist_dir ) && ! mkdir( $dist_dir, 0775, true ) && ! is_dir( $dist_dir ) ) {
	fwrite( STDERR, "The dist directory could not be created.\n" );
	exit( 1 );
}

$archive_path = $dist_dir . '/parish-formation-' . $version . '.zip';
if ( file_exists( $archive_path ) && ! unlink( $archive_path ) ) {
	fwrite( STDERR, "The previous release archive could not be replaced.\n" );
	exit( 1 );
}

$prefix = 'parish-formation/';
if ( class_exists( 'ZipArchive' ) ) {
	$archive = new ZipArchive();
	if ( true !== $archive->open( $archive_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		fwrite( STDERR, "The ZIP archive could not be opened.\n" );
		exit( 1 );
	}
	foreach ( $files as $relative => $absolute ) {
		$archive->addFile( $absolute, $prefix . $relative );
	}
	$archive->close();
} else {
	try {
		$archive = new PharData( $archive_path );
		foreach ( $files as $relative => $absolute ) {
			$archive->addFile( $absolute, $prefix . $relative );
		}
		unset( $archive );
	} catch ( Throwable $error ) {
		fwrite( STDERR, 'The release archive could not be created: ' . $error->getMessage() . "\n" );
		exit( 1 );
	}
}

if ( ! is_file( $archive_path ) || filesize( $archive_path ) < 1024 ) {
	fwrite( STDERR, "The generated release archive is empty or invalid.\n" );
	exit( 1 );
}

$forbidden = array( '.git/', '.agents/', 'tests/', 'tools/', 'dist/' );
$archive_entries = array();
if ( class_exists( 'ZipArchive' ) ) {
	$verification_archive = new ZipArchive();
	if ( true !== $verification_archive->open( $archive_path ) ) {
		fwrite( STDERR, "The generated ZIP archive could not be reopened.\n" );
		exit( 1 );
	}
	for ( $index = 0; $index < $verification_archive->numFiles; ++$index ) {
		$archive_entries[] = $verification_archive->getNameIndex( $index );
	}
	$verification_archive->close();
} else {
	$verification_archive = new PharData( $archive_path );
	$iterator             = new RecursiveIteratorIterator( $verification_archive );
	foreach ( $iterator as $entry ) {
		$entry_path        = str_replace( '\\', '/', $entry->getPathname() );
		$archive_entries[] = preg_replace( '#^phar://.*?\.zip/#', '', $entry_path );
	}
	unset( $verification_archive );
}

foreach ( array( $prefix . 'parish-formation.php', $prefix . 'vendor/autoload.php', $prefix . 'uninstall.php' ) as $required_entry ) {
	if ( ! in_array( $required_entry, $archive_entries, true ) ) {
		fwrite( STDERR, "The generated archive is missing {$required_entry}.\n" );
		exit( 1 );
	}
}

foreach ( $archive_entries as $entry ) {
	$relative = str_starts_with( $entry, $prefix ) ? substr( $entry, strlen( $prefix ) ) : $entry;
	foreach ( $forbidden as $path ) {
		if ( str_starts_with( $relative, $path ) ) {
			fwrite( STDERR, "Development-only path entered the release: {$relative}\n" );
			exit( 1 );
		}
	}
}

fwrite(
	STDOUT,
	sprintf(
		"Built %s (%d files, %s).\n",
		str_replace( '\\', '/', $archive_path ),
		count( $files ),
		number_format( filesize( $archive_path ) ) . ' bytes'
	)
);
