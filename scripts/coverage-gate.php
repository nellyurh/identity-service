<?php

declare(strict_types=1);

/**
 * Coverage gate (invoked by php-service-ci.yml). Reads a Clover report and enforces line
 * coverage floors per architectural layer:
 *   - app/Domain      >= <domain-min>   (default 90)
 *   - app/Application >= <services-min>  (default 80)
 *
 * Usage: php scripts/coverage-gate.php coverage-unit.xml <domain-min> <services-min>
 * Exits non-zero if any bucket is below its floor.
 */
$reportPath = $argv[1] ?? 'coverage-unit.xml';
$domainMin = (float) ($argv[2] ?? 90);
$appMin = (float) ($argv[3] ?? 80);

if (! is_file($reportPath)) {
    fwrite(STDERR, "coverage-gate: report not found: {$reportPath}\n");
    exit(1);
}

$xml = simplexml_load_file($reportPath);
if ($xml === false) {
    fwrite(STDERR, "coverage-gate: could not parse Clover report: {$reportPath}\n");
    exit(1);
}

$buckets = [
    'app/Domain' => ['stmts' => 0, 'covered' => 0, 'min' => $domainMin],
    'app/Application' => ['stmts' => 0, 'covered' => 0, 'min' => $appMin],
];

foreach ($xml->xpath('//file') as $file) {
    $name = str_replace('\\', '/', (string) $file['name']);
    $metrics = $file->metrics;
    if ($metrics === null) {
        continue;
    }
    $stmts = (int) $metrics['statements'];
    $covered = (int) $metrics['coveredstatements'];

    foreach ($buckets as $path => &$bucket) {
        if (str_contains($name, '/'.$path.'/')) {
            $bucket['stmts'] += $stmts;
            $bucket['covered'] += $covered;
        }
    }
    unset($bucket);
}

$failed = false;
foreach ($buckets as $path => $bucket) {
    if ($bucket['stmts'] === 0) {
        printf("coverage-gate: %-16s no statements measured (skipped)\n", $path);

        continue;
    }
    $pct = 100 * $bucket['covered'] / $bucket['stmts'];
    $ok = $pct + 1e-9 >= $bucket['min'];
    printf(
        "coverage-gate: %-16s %6.2f%% (floor %.0f%%) %s\n",
        $path, $pct, $bucket['min'], $ok ? 'OK' : 'FAIL',
    );
    $failed = $failed || ! $ok;
}

exit($failed ? 1 : 0);
