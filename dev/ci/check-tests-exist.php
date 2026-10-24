<?php
/**
 * Fails when a production PHP file has no matching unit test under Test/Unit (same path, `Test` suffix).
 * registration.php, the Api/ folder and interfaces need none.
 *
 * Usage: php dev/ci/check-tests-exist.php
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

$root = dirname(__DIR__, 2);
$files = moduleFiles($root, ['php'], ['vendor', 'node_modules', 'Test', 'e2e', 'dev', 'build', 'test-results', '.git']);

$missing = 0;
$checked = 0;
foreach ($files as $file) {
    if ($file === 'registration.php'
        || str_starts_with($file, 'Api' . DIRECTORY_SEPARATOR)
        || str_ends_with($file, 'Interface.php')
    ) {
        continue;
    }
    $checked++;
    $expected = 'Test/Unit/' . substr($file, 0, -strlen('.php')) . 'Test.php';
    if (!is_file($root . '/' . $expected)) {
        $missing++;
        echo sprintf("%s has no unit test (expected %s)\n", $file, $expected);
    }
}

echo sprintf("Tests exist: %d production file(s) checked, %d without a unit test.\n", $checked, $missing);
exit($missing === 0 ? 0 : 1);
