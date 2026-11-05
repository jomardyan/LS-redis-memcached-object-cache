<?php
/** Validate the release contract without a WordPress installation. */
$root = dirname( __DIR__ );
$plugin = $root . '/smart-hybrid-cache';
$main = file_get_contents( $plugin . '/smart-hybrid-cache.php' );
$readme = file_get_contents( $plugin . '/readme.txt' );
$dropin = file_get_contents( $plugin . '/dropins/object-cache.php' );
$errors = array();
// The packager reads Git-clean source bytes. Compare that same representation:
// Windows working-tree CRLF and directory separators are not archive changes.
$git_bytes = static function ( array $arguments, string $input = '' ) use ( $root ) {
 $process = proc_open( array_merge( array( 'git', '-C', $root ), $arguments ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
 if ( ! is_resource( $process ) ) { return false; }
 fwrite( $pipes[0], $input );
 fclose( $pipes[0] );
 $output = stream_get_contents( $pipes[1] );
 $error = stream_get_contents( $pipes[2] );
 fclose( $pipes[1] );
 fclose( $pipes[2] );
 $status = proc_close( $process );
 if ( '' !== $error ) { fwrite( STDERR, $error ); }
 return 0 === $status ? $output : false;
};
$canonical_source = static function ( string $name ) use ( $root, $git_bytes ) {
 if ( ! is_file( $root . '/' . $name ) ) { return false; }
 $hash = $git_bytes( array( 'hash-object', '-w', '--path=' . $name, '--stdin' ), file_get_contents( $root . '/' . $name ) );
 if ( false === $hash || ! preg_match( '/^[a-f0-9]{40,64}$/', trim( $hash ) ) ) { return false; }
 return $git_bytes( array( 'cat-file', 'blob', trim( $hash ) ) );
};
preg_match( '/^ \* Version:\s*(\S+)/m', $main, $version );
preg_match( '/^Stable tag:\s*(\S+)/m', $readme, $stable );
preg_match( "/SMART_HYBRID_CACHE_DROPIN_VERSION', '([^']+)'/", $dropin, $cache_version );
if ( empty( $version[1] ) || ( $stable[1] ?? '' ) !== $version[1] || ( $cache_version[1] ?? '' ) !== $version[1] ) { $errors[] = 'Plugin, readme and drop-in versions must match.'; }
foreach ( array( 'Requires at least', 'Requires PHP', 'License', 'Text Domain' ) as $header ) {
 if ( ! preg_match( '/^ \* ' . preg_quote( $header, '/' ) . ':\s*\S+/m', $main ) ) { $errors[] = 'Missing plugin header ' . $header; }
}
foreach ( array( 'Description', 'Installation', 'Frequently Asked Questions', 'Changelog', 'Upgrade Notice' ) as $section ) {
 if ( false === strpos( $readme, '== ' . $section . ' ==' ) ) { $errors[] = 'Missing readme section ' . $section; }
}
preg_match( '/^Tags:\s*(.*)$/m', $readme, $tags );
if ( empty( $tags[1] ) || count( explode( ',', $tags[1] ) ) > 5 ) { $errors[] = 'Readme needs one to five tags.'; }
if ( preg_match( '/pending|TODO|TBD/i', $readme ) ) { $errors[] = 'Readme contains release placeholders.'; }
if ( 1 !== substr_count( $dropin, '/* SHC_CONFIGURATION */ array()' ) ) { $errors[] = 'Release source must contain an unconfigured drop-in template.'; }
if ( ! is_file( $plugin . '/LICENSE' ) || hash_file( 'sha256', $root . '/LICENSE' ) !== hash_file( 'sha256', $plugin . '/LICENSE' ) ) { $errors[] = 'Runtime license must match the repository license.'; }
foreach ( array( 1, 2 ) as $n ) { if ( ! is_file( $root . '/.wordpress-org/screenshot-' . $n . '.png' ) ) { $errors[] = 'Missing documented screenshot ' . $n; } }
if ( ! empty( $argv[1] ) && ! class_exists( 'ZipArchive' ) ) {
 $errors[] = 'The PHP ZIP extension is required to validate an archive.';
} elseif ( ! empty( $argv[1] ) ) {
 $zip = new ZipArchive();
 if ( true !== $zip->open( $argv[1] ) ) { $errors[] = 'Cannot open release ZIP.'; } else {
  for ( $i = 0; $i < $zip->numFiles; ++$i ) {
   $name = $zip->getNameIndex( $i );
   if ( ! str_starts_with( $name, 'smart-hybrid-cache/' ) || preg_match( '#(?:^|/)(?:\.git|tests|vendor|node_modules|\.wordpress-org)(?:/|$)|\.zip$#', $name ) ) { $errors[] = 'Unexpected ZIP entry ' . $name; }
   if ( ! str_ends_with( $name, '/' ) ) {
    $source = $root . '/' . $name;
    if ( ! is_file( $source ) || $canonical_source( $name ) !== $zip->getFromIndex( $i ) ) { $errors[] = 'Stale or modified ZIP entry ' . $name; }
   }
  }
  foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
   $name = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
   if ( false === $zip->locateName( $name ) ) { $errors[] = 'Missing ZIP entry ' . $name; }
  }
  $zip->close();
 }
}
if ( $errors ) { fwrite( STDERR, implode( PHP_EOL, $errors ) . PHP_EOL ); exit( 1 ); }
echo 'Release metadata and package checks passed for ' . $version[1] . PHP_EOL;
