<?php
/**
 * Prints the line coverage of every file of a PHPUnit clover report and fails when a file, or the total, is below
 * the threshold.
 *
 * Usage: php dev/ci/check-coverage.php build/clover.xml 70
 */

declare(strict_types=1);

$report = $argv[1] ?? 'build/clover.xml';
$threshold = (float) ($argv[2] ?? 70);

if (!is_file($report)) {
    fwrite(STDERR, "Coverage report not found: {$report}\n");
    exit(2);
}

$xml = simplexml_load_file($report);
if ($xml === false) {
    fwrite(STDERR, "Coverage report is not valid XML: {$report}\n");
    exit(2);
}

$root = dirname(__DIR__, 2) . '/';
$rows = [];
$covered = 0;
$total = 0;
foreach ($xml->xpath('//file') ?: [] as $file) {
    $metrics = $file->metrics;
    $statements = (int) $metrics['statements'];
    if ($statements === 0) {
        continue;
    }
    $coveredStatements = (int) $metrics['coveredstatements'];
    $covered += $coveredStatements;
    $total += $statements;
    $rows[str_replace($root, '', (string) $file['name'])] = [$coveredStatements, $statements];
}
ksort($rows);

$failed = 0;
foreach ($rows as $name => [$coveredStatements, $statements]) {
    $percentage = $coveredStatements / $statements * 100;
    $failed += $percentage < $threshold ? 1 : 0;
    echo sprintf(
        "%6.2f%%  %3d/%-3d  %s%s\n",
        $percentage,
        $coveredStatements,
        $statements,
        $name,
        $percentage < $threshold ? '   <-- below ' . $threshold . '%' : ''
    );
}

$overall = $total === 0 ? 0.0 : $covered / $total * 100;
echo sprintf("%6.2f%%  %3d/%-3d  TOTAL (gate: %s%% per file and in total)\n", $overall, $covered, $total, $threshold);

exit($failed === 0 && $overall >= $threshold ? 0 : 1);
