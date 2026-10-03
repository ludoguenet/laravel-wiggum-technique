<?php

$cloverPath = $argv[1] ?? __DIR__ . '/../../../../coverage.xml'; // scripts/ -> commit-coverage/ -> skills/ -> .claude/ -> project root

if (!file_exists($cloverPath)) {
    fwrite(STDERR, "Coverage file not found: {$cloverPath}\n");
    fwrite(STDERR, "Run: php artisan test --coverage --coverage-clover={$cloverPath}\n");
    exit(1);
}

$xml = simplexml_load_file($cloverPath);

if ($xml === false) {
    fwrite(STDERR, "Could not parse clover XML at {$cloverPath}\n");
    exit(1);
}

$threshold = 80.0;
$failed = false;
$files = [];

foreach ($xml->xpath('//file') as $file) {
    $lines = $file->xpath('line[@type="stmt"]');
    $total = count($lines);

    if ($total === 0) {
        continue;
    }

    $covered = 0;
    $uncoveredLines = [];

    foreach ($lines as $line) {
        if ((int) $line['count'] > 0) {
            $covered++;
        } else {
            $uncoveredLines[] = (int) $line['num'];
        }
    }

    $files[] = [
        'path' => (string) $file['name'],
        'percent' => round(($covered / $total) * 100, 1),
        'uncovered' => $uncoveredLines,
    ];
}

usort($files, fn ($a, $b) => $a['percent'] <=> $b['percent']);

foreach ($files as $file) {
    $status = $file['percent'] >= $threshold ? 'OK' : 'BELOW THRESHOLD';

    if ($status === 'BELOW THRESHOLD') {
        $failed = true;
    }

    printf("[%s] %-50s %5.1f%%\n", $status, $file['path'], $file['percent']);

    if (!empty($file['uncovered'])) {
        printf("           uncovered lines: %s\n", implode(', ', $file['uncovered']));
    }
}

exit($failed ? 1 : 0);
