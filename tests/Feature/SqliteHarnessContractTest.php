<?php

/**
 * Protect the package's SQLite harness contract and production-owned schema boundaries.
 * Full-text search and MySQL-specific queries remain outside SQLite coverage.
 */

use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Support\Facades\DB;

/** Connection configuration must take effect before the migration and transaction wrappers run. */
test('uses native in-memory sqlite with enforced foreign keys', function (): void {
    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:')
        ->and(DB::connection()->getConfig('foreign_key_constraints'))->toBeTrue()
        ->and((int) DB::scalar('PRAGMA foreign_keys'))->toBe(1)
        ->and(DB::connection()->getSchemaGrammar()::class)->toBe(SQLiteGrammar::class)
        ->and(DB::select('PRAGMA foreign_key_check'))->toBe([]);
});

/** Test fixtures may own generic component or external-source tables, but never replace BC-owned tables. */
test('does not replace production tables or native sqlite schema operations', function (): void {
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
