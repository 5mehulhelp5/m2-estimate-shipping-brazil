<?php
/**
 * Helpers shared by the checks in dev/ci.
 */

declare(strict_types=1);

/**
 * Production source files of the module (relative paths), without third party code, tests and tooling.
 *
 * @param string $root
 * @param string[] $extensions
 * @param string[] $excludedRoots First path segments that are skipped
 * @return string[]
 */
function moduleFiles(string $root, array $extensions, array $excludedRoots): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $relative = substr($file->getPathname(), strlen($root) + 1);
        $first = explode(DIRECTORY_SEPARATOR, $relative)[0];
        if ($file->isFile()
            && !in_array($first, $excludedRoots, true)
            && in_array($file->getExtension(), $extensions, true)
        ) {
            $files[] = $relative;
        }
    }
    sort($files);

    return $files;
}
