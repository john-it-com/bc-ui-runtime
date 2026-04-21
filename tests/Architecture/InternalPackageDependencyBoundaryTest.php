<?php

/**
 * Pest architecture specification for internal package dependency boundaries.
 *
 * Purpose: Prevent production code from referencing JohnIt namespaces that are not reachable through the installed internal dependency graph.
 * Role: Keeps the module's published dependency graph aligned with the namespaces its autoloaded code actually uses.
 */

use JohnIt\Bc\Runtime\Tests\Support\InternalPackageDependencyBoundary;
use PHPUnit\Framework\Assert;

$boundary = InternalPackageDependencyBoundary::fromComposerJson(dirname(__DIR__, 2).'/composer.json');

/**
 * Ensure the module's production namespaces only reference reachable JohnIt package namespaces.
 *
 * Why: Local development installs sibling packages together, so undeclared internal dependencies can appear to work
 * until the package is consumed in isolation.
 */
test('production code does not use undeclared JohnIt package namespaces', function () use ($boundary): void {
    Assert::assertSame([], $boundary->unauthorizedJohnItUsages(), $boundary->violationSummary());
});
