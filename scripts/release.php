#!/usr/bin/env php
<?php

declare(strict_types=1);

use Rhapsodic\Pbump\ReleaseCli;

$projectRoot = resolveProjectRoot();
$autoloadPath = resolveAutoloadPath($projectRoot);

if ($autoloadPath === null) {
    fwrite(STDERR, "[ERROR] vendor/autoload.php not found. Run composer install.\n");
    exit(1);
}

require $autoloadPath;

$rawArgv = $GLOBALS['argv'] ?? [];
if (!is_array($rawArgv)) {
    $rawArgv = [];
}

$argv = [];
foreach ($rawArgv as $arg) {
    if (!is_string($arg)) {
        continue;
    }

    $argv[] = $arg === 'help' ? '--help' : $arg;
}

$cli = new ReleaseCli();
exit($cli->run($argv, $projectRoot));

function resolveProjectRoot(): string
{
    $projectRoot = getenv('RELEASE_PROJECT_ROOT');
    if (is_string($projectRoot) && $projectRoot !== '') {
        return $projectRoot;
    }

    $composerBinDir = $GLOBALS['_composer_bin_dir'] ?? null;
    if (is_string($composerBinDir) && $composerBinDir !== '') {
        $consumerRoot = dirname($composerBinDir, 2);
        if (is_file($consumerRoot . DIRECTORY_SEPARATOR . 'composer.json')) {
            return $consumerRoot;
        }
    }

    $workingTreeRoot = findProjectRoot(getcwd());
    if ($workingTreeRoot !== null) {
        return $workingTreeRoot;
    }

    $packageRoot = dirname(__DIR__);

    return findProjectRoot($packageRoot) ?? $packageRoot;
}

function resolveAutoloadPath(string $projectRoot): ?string
{
    $autoloadCandidates = [];

    $envAutoloadPath = getenv('RELEASE_AUTOLOAD_PATH');
    if (is_string($envAutoloadPath) && $envAutoloadPath !== '') {
        $autoloadCandidates[] = $envAutoloadPath;
    }

    $composerAutoloadPath = $GLOBALS['_composer_autoload_path'] ?? null;
    if (is_string($composerAutoloadPath) && $composerAutoloadPath !== '') {
        $autoloadCandidates[] = $composerAutoloadPath;
    }

    $autoloadCandidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    $autoloadCandidates[] = __DIR__ . '/../vendor/autoload.php';
    $autoloadCandidates[] = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'autoload.php';

    foreach ($autoloadCandidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function findProjectRoot(string|false $startPath): ?string
{
    if (!is_string($startPath) || $startPath === '') {
        return null;
    }

    $current = realpath($startPath) ?: $startPath;

    while (true) {
        if (is_file($current . DIRECTORY_SEPARATOR . 'composer.json')) {
            return $current;
        }

        $parent = dirname($current);
        if ($parent === $current) {
            return null;
        }

        $current = $parent;
    }
}
