<?php

/** Preserve registry state when Laravel 12 respects nullable constructor defaults during resolution. */

use JohnIt\Bc\Runtime\Runtime\ModuleRegistry;

test('container consumers share the runtime registry and its Tailwind entries', function (): void {
    $registry = app(ModuleRegistry::class);
    $registry->registerTailwindContent('admin-inertia', 'example', '/example', ['resources/**/*.vue']);

    $resolved = app(ModuleRegistry::class);
    $entries = $resolved->tailwindEntriesForContext('admin-inertia');

    expect($resolved)->toBe($registry)
        ->and($entries)->toHaveCount(1)
        ->and($entries[0]->moduleName)->toBe('example')
        ->and($entries[0]->globs)->toBe(['resources/**/*.vue'])
        ->and($resolved->tailwindEntriesForContext('shop'))->toBe([]);
});
