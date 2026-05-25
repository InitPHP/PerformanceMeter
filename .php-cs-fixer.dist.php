<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12'                       => true,
        '@PHP81Migration'              => true,
        'declare_strict_types'         => true,
        'array_syntax'                 => ['syntax' => 'short'],
        'no_unused_imports'            => true,
        'ordered_imports'              => ['sort_algorithm' => 'alpha'],
        'single_quote'                 => true,
        'trailing_comma_in_multiline'  => true,
        'native_function_invocation'   => [
            'include' => ['@compiler_optimized'],
            'scope'   => 'namespaced',
            'strict'  => true,
        ],
        'binary_operator_spaces'       => ['default' => 'single_space'],
        'concat_space'                 => ['spacing' => 'one'],
        'no_superfluous_phpdoc_tags'   => ['allow_mixed' => true],
        'phpdoc_align'                 => ['align' => 'left'],
        'phpdoc_separation'            => true,
        'phpdoc_trim'                  => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');
