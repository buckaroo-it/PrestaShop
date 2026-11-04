<?php
/**
 * Print line coverage per file from a PHPUnit Clover report.
 *
 *   vendor/bin/phpunit --coverage-clover /tmp/clover.xml
 *   php tests/tools/coverage-report.php /tmp/clover.xml [path-prefix ...] [--uncovered] [--min=90]
 *
 * Path prefixes are relative to the module root (e.g. controllers/front src/Refund/Handler.php).
 * --uncovered also lists the uncovered line numbers of each file.
 * --min=N exits with status 1 when the total over the selected files is below N percent.
 */
if ($argc < 2) {
    fwrite(STDERR, "usage: php tests/tools/coverage-report.php <clover.xml> [path-prefix ...] [--uncovered] [--min=N]\n");
    exit(2);
}

$clover = $argv[1];
$prefixes = [];
$showUncovered = false;
$minimum = null;
foreach (array_slice($argv, 2) as $arg) {
    if ($arg === '--uncovered') {
        $showUncovered = true;
    } elseif (strpos($arg, '--min=') === 0) {
        $minimum = (float) substr($arg, 6);
    } else {
        $prefixes[] = rtrim($arg, '/');
    }
}

$xml = simplexml_load_file($clover);
if ($xml === false) {
    fwrite(STDERR, "cannot read $clover\n");
    exit(2);
}

$root = realpath(__DIR__ . '/../..') . '/';
$rows = [];
foreach ($xml->xpath('//file') as $file) {
    $path = (string) $file['name'];
    if (strpos($path, $root) === 0) {
        $path = substr($path, strlen($root));
    }
    if ($prefixes) {
        $match = false;
        foreach ($prefixes as $prefix) {
            if ($path === $prefix || strpos($path, $prefix . '/') === 0) {
                $match = true;
                break;
            }
        }
        if (!$match) {
            continue;
        }
    }

    $uncovered = [];
    foreach ($file->line as $line) {
        if ((string) $line['type'] === 'stmt' && (int) $line['count'] === 0) {
            $uncovered[] = (int) $line['num'];
        }
    }
    $statements = (int) $file->metrics['statements'];
    $rows[$path] = [$statements, $statements - count($uncovered), $uncovered];
}

ksort($rows);
$total = 0;
$covered = 0;
foreach ($rows as $path => [$statements, $hit, $uncovered]) {
    $total += $statements;
    $covered += $hit;
    printf("%6.1f%%  %5d/%-5d  %s\n", $statements ? 100 * $hit / $statements : 100, $hit, $statements, $path);
    if ($showUncovered && $uncovered) {
        echo '          uncovered: ' . compressRanges($uncovered) . "\n";
    }
}

$percent = $total ? 100 * $covered / $total : 100;
printf("%6.1f%%  %5d/%-5d  TOTAL (%d files)\n", $percent, $covered, $total, count($rows));

if ($minimum !== null && $percent < $minimum) {
    fwrite(STDERR, sprintf("Line coverage %.2f%% is below the required %.2f%%\n", $percent, $minimum));
    exit(1);
}

function compressRanges(array $lines): string
{
    sort($lines);
    $ranges = [];
    $start = $prev = array_shift($lines);
    foreach ($lines as $line) {
        if ($line === $prev + 1) {
            $prev = $line;
            continue;
        }
        $ranges[] = $start === $prev ? (string) $start : $start . '-' . $prev;
        $start = $prev = $line;
    }
    $ranges[] = $start === $prev ? (string) $start : $start . '-' . $prev;

    return implode(', ', $ranges);
}
