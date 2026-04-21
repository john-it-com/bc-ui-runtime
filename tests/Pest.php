<?php

/**
 * Pest bootstrap configuration for the bc-ui-runtime package.
 *
 * Purpose: Bind package-local tests to the module Testbench harness and shared testing helpers.
 * Role: Establishes the canonical Pest entrypoint used by this module's isolated package suite.
 */

use JohnIt\Bc\Runtime\Tests\TestCase;

/**
 * Determine which package test directories need the Laravel Testbench harness.
 *
 * Why: Architecture tests are static and do not need the application bootstrap, which keeps them isolated from
 * package-specific runtime fixtures.
 *
 * @var list<string> $packageTestDirectories
 */
$packageTestDirectories = array_values(array_map(
    static fn (string $directoryPath): string => basename($directoryPath),
    array_filter(
        glob(__DIR__.'/*', GLOB_ONLYDIR) ?: [],
        static fn (string $directoryPath): bool => basename($directoryPath) !== 'Architecture',
    ),
));

if ($packageTestDirectories !== []) {
    uses(TestCase::class)->in(...$packageTestDirectories);
}
