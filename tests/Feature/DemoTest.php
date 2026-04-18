<?php

/**
 * Pest smoke test for the bc-ui-runtime package.
 *
 * Purpose: Prove the standalone Pest/Testbench bootstrap executes for this previously untested module.
 * Role: Establishes a minimal verification point so future feature tests inherit a working package harness.
 */

test('the package testbench bootstrap initializes', function (): void {
    expect(app())->not->toBeNull();
});
