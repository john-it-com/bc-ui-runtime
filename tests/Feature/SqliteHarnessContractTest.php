<?php

/**
 * Protect the package's MariaDB harness contract and production-owned schema boundaries.
 * Exercise native constraints through the real migration graph.
 */

use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Support\Facades\DB;
use JohnIt\Bc\Runtime\Tests\Support\TestDatabase;

/** Connection configuration must take effect before the migration and transaction wrappers run. */
test('uses native mariadb with enforced foreign keys', function (): void {
    DB::connection()->getSchemaBuilder();

    expect(DB::connection()->getDriverName())->toBe('mysql')
        ->and(DB::connection()->getDatabaseName())->toMatch('/\Abc_ui_runtime_testing(?:_test_[A-Za-z0-9]+)?\z/')
        ->and((int) DB::scalar('SELECT @@FOREIGN_KEY_CHECKS'))->toBe(1)
        ->and(DB::connection()->getSchemaGrammar()::class)->toBe(MySqlGrammar::class)
        ->and(DB::connection()->transactionLevel())->toBe(0);

    TestDatabase::assertSafeConnection(DB::connection()->getConfig());
    TestDatabase::assertForeignKeyIntegrity();
});

/** Test fixtures may own generic component or external-source tables, but never replace BC-owned tables. */
test('does not replace production tables or native schema operations', function (): void {
    $ownedTables = [];
    foreach (app('migrator')->paths() as $migrationPath) {
        foreach (glob($migrationPath.'/*.php') ?: [] as $migrationFile) {
            preg_match_all('/Schema::'.'create\(\s*[\'"]([^\'"]+)[\'"]/', file_get_contents($migrationFile), $matches);
            $ownedTables = array_merge($ownedTables, $matches[1]);
        }
    }

    $violations = [];
    $forbidden = ['hotfixForSqlite'.'SchemaBuilder', 'With'.'Sqlite', 'compile'.'DropForeign', 'compile'.'Fulltext', 'compile'.'DropFullText', 'blueprint'.'Resolver', 'SQLiteConnection::'.'resolverFor'];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__))) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        foreach ($forbidden as $fragment) {
            if (str_contains($contents, $fragment)) {
                $violations[] = $file->getPathname().' contains '.$fragment;
            }
        }
        preg_match_all('/Schema::(?:connection\([^;]+?\)->)?'.'create\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $matches);
        foreach (array_intersect($matches[1], $ownedTables) as $table) {
            $violations[] = $file->getPathname().' replaces '.$table;
        }
    }
    expect($violations)->toBe([]);
});
