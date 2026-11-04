<?php
/** Portable PHP syntax validation without a WordPress installation. */
$root = dirname(__DIR__);
$failed = false;
$count = 0;
$paths = array_slice($argv, 1) ?: array('.');
foreach ($paths as $path) {
    $absolute = $root . '/' . $path;
    $files = is_file($absolute) ? array(new SplFileInfo($absolute)) : new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS),
            static function ($file) { return !in_array($file->getFilename(), array('.git', 'vendor', 'node_modules', 'build', 'dist'), true); })
    );
    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') { continue; }
        $pipes = array();
        $process = proc_open(array(PHP_BINARY, '-l', $file->getPathname()), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
        if (!is_resource($process)) { fwrite(STDERR, "Could not start PHP syntax checker.\n"); exit(1); }
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) { fwrite(STDERR, $output); $failed = true; }
        ++$count;
    }
}
echo "Checked $count PHP files.\n";
exit($failed || $count === 0 ? 1 : 0);
