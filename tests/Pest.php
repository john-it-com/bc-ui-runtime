<?php

/**
 * Pest bootstrap configuration for the bc-ui-runtime package.
 *
 * Purpose: Bind package-local tests to the module Testbench harness and shared testing helpers.
 * Role: Establishes the canonical Pest entrypoint used by this module's isolated package suite.
 */

use JohnIt\Bc\Runtime\Tests\TestCase;
uses(TestCase::class)->in('.');
