<?php

use JohnIt\Bc\Runtime\Tests\Support\TestDatabase;

/**
 * Purpose: Load the package-local dependency tree before booting the isolated suite.
 * Role: Reject unsafe database targets before Testbench can migrate or create parallel worker databases.
 */

require __DIR__.'/../vendor/autoload.php';

TestDatabase::assertSafeEnvironment();
