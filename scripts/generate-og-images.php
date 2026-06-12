#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$python = $root . '/scripts/.venv/bin/python';
$script = $root . '/scripts/generate-og-images.py';

if (!is_file($python)) {
    fwrite(STDERR, "Python venv missing. Run:\n");
    fwrite(STDERR, "  cd scripts && python3 -m venv .venv && .venv/bin/pip install -r requirements.txt\n");
    fwrite(STDERR, "  .venv/bin/playwright install chromium\n");
    exit(1);
}

passthru(escapeshellarg($python) . ' ' . escapeshellarg($script), $exitCode);
exit($exitCode);
