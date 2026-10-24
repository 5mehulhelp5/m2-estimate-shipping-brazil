<?php
/**
 * Multi-store check: every scopeConfig->getValue() / isSetFlag() must receive the scope as its second argument,
 * otherwise the value of the default scope is returned for every website and store view.
 *
 * Usage: php dev/ci/check-scope-config.php
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

/**
 * Count the top-level arguments of the call whose opening parenthesis is at $start.
 *
 * @param array<int, array|string> $tokens
 * @param int $start
 * @return int
 */
function countArguments(array $tokens, int $start): int
{
    $depth = 0;
    $arguments = 0;
    $hasContent = false;
    for ($i = $start, $count = count($tokens); $i < $count; $i++) {
        $text = is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
        $type = is_array($tokens[$i]) ? $tokens[$i][0] : null;
        if (in_array($text, ['(', '[', '{'], true)) {
            $depth++;
            continue;
        }
        if (in_array($text, [')', ']', '}'], true)) {
            $depth--;
            if ($depth === 0) {
                return $hasContent ? $arguments + 1 : 0;
            }
            continue;
        }
        if ($depth === 1 && $text === ',') {
            $arguments++;
        } elseif ($type !== T_WHITESPACE && $type !== T_COMMENT && $type !== T_DOC_COMMENT) {
            $hasContent = true;
        }
    }

    return 0;
}

$root = dirname(__DIR__, 2);
$files = moduleFiles($root, ['php', 'phtml'], ['vendor', 'node_modules', 'Test', 'e2e', 'dev', 'build', 'test-results', '.git']);

$violations = 0;
$calls = 0;
foreach ($files as $file) {
    $tokens = token_get_all(file_get_contents($root . '/' . $file) ?: '');
    foreach ($tokens as $index => $token) {
        if (!is_array($token) || !in_array($token[1], ['getValue', 'isSetFlag'], true)) {
            continue;
        }
        $previous = $tokens[$index - 1] ?? null;
        $receiver = $tokens[$index - 2] ?? null;
        $isScopeConfigCall = is_array($previous) && $previous[0] === T_OBJECT_OPERATOR
            && is_array($receiver) && stripos($receiver[1], 'scopeConfig') !== false;
        if (!$isScopeConfigCall) {
            continue;
        }
        $calls++;
        if (countArguments($tokens, $index + 1) < 2) {
            $violations++;
            echo sprintf("%s:%d: %s() without a scope\n", $file, $token[2], $token[1]);
        }
    }
}

echo sprintf("Multi-store: %d scopeConfig call(s) checked, %d without a scope.\n", $calls, $violations);
exit($violations === 0 ? 0 : 1);
