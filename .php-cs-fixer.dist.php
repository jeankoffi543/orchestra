<?php

$rules = [
    '@PSR12' => true,
    'array_syntax' => ['syntax' => 'short'],
    'binary_operator_spaces' => ['default' => 'align_single_space_minimal'],
    'blank_line_after_namespace' => true,
    'blank_line_before_statement' => ['statements' => ['return']],
    'braces' => true,
    'cast_spaces' => true,
    'class_attributes_separation' => ['elements' => ['method' => 'one']],
    'concat_space' => ['spacing' => 'one'],
    // 'declare_equal_normalize' => ['space' => 'none'],
    'elseif' => true,
    'encoding' => true,
    'full_opening_tag' => true,
    'function_declaration' => true,
    'indentation_type' => true,
    'line_ending' => true,
    'lowercase_keywords' => true,
    'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
    'native_function_invocation' => ['include' => ['@all']],
    'no_unused_imports' => true,
    'ordered_imports' => ['sort_algorithm' => 'alpha'],
    'single_blank_line_at_eof' => true,
    'single_quote' => true,
    'trailing_comma_in_multiline' => ['elements' => ['arrays']],
];

$config = new PhpCsFixer\Config();
$config
    ->setRules($rules)
    ->setRiskyAllowed(true)
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__)
            ->exclude(['vendor', 'node_modules', 'storage'])
    );

return $config;
