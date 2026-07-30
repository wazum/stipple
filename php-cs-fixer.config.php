<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__.'/src', __DIR__.'/tests'])
    ->append([__DIR__.'/bin/demo.php', __DIR__.'/bin/icon-row.php']);

return (new Config())
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setRules([
        '@PER-CS2.0' => true,
        'declare_strict_types' => true,
        'concat_space' => ['spacing' => 'none'],
        // Two PER-CS 2.0 defaults the existing codebase deliberately does not follow:
        // empty bodies stay on their own lines, and arrow functions keep the space after `fn`.
        'single_line_empty_body' => false,
        'function_declaration' => ['closure_fn_spacing' => 'one'],
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        // \GdImage and friends are referenced fully qualified rather than imported.
        'global_namespace_import' => [
            'import_classes' => false,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'case',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                // Keeps setUp()/tearDown() at the top instead of sorting them in as protected.
                'phpunit',
                'method_public',
                'method_protected',
                'method_private',
            ],
        ],
        'single_quote' => true,
        'trailing_comma_in_multiline' => [
            'elements' => ['arguments', 'array_destructuring', 'arrays', 'match', 'parameters'],
        ],
        'yoda_style' => false,
    ]);
