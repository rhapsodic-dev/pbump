<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()
    ->in([
        __DIR__ . DIRECTORY_SEPARATOR . 'src',
        __DIR__ . DIRECTORY_SEPARATOR . 'tests',
        __DIR__ . DIRECTORY_SEPARATOR . 'scripts',
        __DIR__ . DIRECTORY_SEPARATOR . 'bin',
    ])
    ->name('*.php')
    ->name('pbump')
;

return (new Config())
    ->setFinder($finder)
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRules([
        'braces_position' => [
            'functions_opening_brace' => 'next_line_unless_newline_at_signature_end',
        ],
        'single_import_per_statement' => [
            'group_to_single_imports' => false,
        ],
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],
    ])
    ->setUsingCache(false)
;
