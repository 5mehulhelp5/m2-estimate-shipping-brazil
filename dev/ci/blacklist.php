<?php
/**
 * Fails when debugging leftovers or forbidden constructs show up in the production php, js, phtml and html files.
 *
 * Usage: php dev/ci/blacklist.php
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

const BLACKLIST = [
    'var_dump(' => '/\bvar_dump\s*\(/',
    'print_r(' => '/\bprint_r\s*\(/',
    'console.log(' => '/\bconsole\.log\s*\(/',
    'die( / die;' => '/\bdie\s*[(;]/',
    'exit( / exit;' => '/\bexit\s*[(;]/',
    'eval(' => '/\beval\s*\(/',
    'phpinfo' => '/\bphpinfo\b/',
    'ObjectManager::getInstance' => '/ObjectManager::getInstance/',
    'DebuggerUtility' => '/DebuggerUtility/',
    'localhost' => '/localhost/',
    '_GET[ / _POST[ / _REQUEST[' => '/\b_(?:GET|POST|REQUEST)\[/',
    'merge conflict marker' => '/<<<<<<</',
];

$root = dirname(__DIR__, 2);
// The CI tooling is command line code (it exits, and this file lists the forbidden patterns): not module code
$files = array_filter(moduleFiles(
    $root,
    ['php', 'js', 'phtml', 'html'],
    ['vendor', 'node_modules', 'Test', 'e2e', 'test-results', 'build', '.git']
), static fn (string $file): bool => !str_starts_with($file, 'dev/ci/'));

$violations = 0;
foreach ($files as $file) {
    foreach (file($root . '/' . $file) ?: [] as $number => $line) {
        foreach (BLACKLIST as $label => $pattern) {
            if (preg_match($pattern, $line) === 1) {
                $violations++;
                echo sprintf("%s:%d: [%s] %s\n", $file, $number + 1, $label, trim($line));
            }
        }
    }
}

echo sprintf("Blacklist: %d file(s) scanned, %d violation(s).\n", count($files), $violations);
exit($violations === 0 ? 0 : 1);
