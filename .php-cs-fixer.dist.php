<?php

/**
 * SB-Tech — curated PHP-CS-Fixer config.
 *
 * Deliberately NOT full PSR-12: this is a 7.4-era codebase with legacy
 * conventions (see docs/RULES.md §3 and the real note about camelCase).
 * The goal is normalizing NEW/EDITED code with zero churn in legacy files —
 * run via `composer fix` / `make fix` (fixers applied only on changed files
 * in the pre-commit hook).
 */

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/functions',
        __DIR__ . '/classes',
        __DIR__ . '/tests',
        __DIR__ . '/database/migration',
        __DIR__ . '/config',
    ])
    ->notPath('bootstrap.php')   // legacy config bootstrap; touch deliberately
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        // Arrays
        'array_syntax'            => ['syntax' => 'short'],
        'trailing_comma_in_multiline' => ['elements' => ['arrays']],

        // Names / casing
        'lowercase_keywords'      => true,
        'lowercase_static_reference' => true,

        // Strict-ish comparisons (risky: safe here — verified no == rewrites
        // occur in current code, rule is a guard for new code)
        'strict_comparison'       => true,

        // Formatting hygiene only — no behavior, no signature changes.
        // NOTE: deliberately NOT using no_multiple_statements_per_line or
        // binary_operator_spaces alignment — both churn legacy compact
        // switch/case and aligned-assignment style (see docs/RULES.md).
        'no_trailing_whitespace'  => true,
        'no_whitespace_in_blank_line' => true,
        'single_blank_line_at_eof' => true,
        // NOTE: no blank_line_after_opening_tag — it churns 9 legacy files
        // for zero benefit; new code can follow it voluntarily.
        'line_ending'             => true,
        'no_superfluous_elseif'   => false,   // elseif chains are idiomatic here
        'concat_space'            => ['spacing' => 'one'],  // matches "foo" . $bar
    ])
    ->setFinder($finder);
