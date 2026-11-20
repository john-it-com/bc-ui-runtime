<?php

/**
 * Purpose: Guard the disposable package database before native worker setup or cleanup.
 * Role: Reject unsafe connection targets without creating or managing worker databases.
 */

namespace JohnIt\Bc\Runtime\Tests\Support;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;
use RuntimeException;

class TestDatabase
{
    /**
     * Reject unsafe names and routing before any destructive database operation.
     *
     * @param  array<string, mixed>  $connection
     */
    public static function assertSafeConnection(array $connection): void
    {
        $database = $connection['database'] ?? null;

        if (($connection['driver'] ?? null) !== 'mysql'
            || ! is_string($database)
            || preg_match('/\Abc_ui_runtime_testing(?:_test_[A-Za-z0-9]+)?\z/', $database) !== 1
            || ! empty($connection['url'])
            || ! empty($connection['unix_socket'])
            || array_key_exists('read', $connection)
            || array_key_exists('write', $connection)
        ) {
            throw new RuntimeException('bc-ui-runtime tests require bc_ui_runtime_testing or a native parallel-worker database over MySQL TCP.');
        }
    }

    /** Preserve the old engine-wide foreign-key integrity assertion using native MariaDB joins. */
    public static function assertForeignKeyIntegrity(): void
    {
        $connection = DB::connection('testbench');
        self::assertSafeConnection($connection->getConfig());
        $schema = $connection->getSchemaBuilder();

        foreach ($schema->getTableListing(schema: $connection->getDatabaseName(), schemaQualified: false) as $table) {
            foreach ($schema->getForeignKeys($table) as $foreignKey) {
                $query = $connection->table($table.' as foreign_child')
                    ->leftJoin($foreignKey['foreign_table'].' as foreign_parent', function (JoinClause $join) use ($foreignKey): void {
                        foreach ($foreignKey['columns'] as $index => $column) {
                            $join->on('foreign_child.'.$column, '=', 'foreign_parent.'.$foreignKey['foreign_columns'][$index]);
                        }
                    });

                foreach ($foreignKey['columns'] as $column) {
                    $query->whereNotNull('foreign_child.'.$column);
                }

                $query->whereNull('foreign_parent.'.$foreignKey['foreign_columns'][0]);
                Assert::assertSame(0, $query->count(), "Orphaned rows violate {$table}.{$foreignKey['name']}.");
            }
        }
    }

    /** Reject inherited environment overrides before Laravel can create native worker databases. */
    public static function assertSafeEnvironment(): void
    {
        foreach (['DB_URL', 'DATABASE_URL', 'DB_SOCKET'] as $key) {
            if (env($key)) {
                throw new RuntimeException("bc-ui-runtime tests prohibit {$key} overrides.");
            }
        }

        if (env('DB_CONNECTION', 'mysql') !== 'mysql') {
            throw new RuntimeException('bc-ui-runtime tests require DB_CONNECTION=mysql.');
        }

        $token = env('TEST_TOKEN');
        if ($token !== null && preg_match('/\A[A-Za-z0-9]+\z/', (string) $token) !== 1) {
            throw new RuntimeException('bc-ui-runtime tests require an alphanumeric native worker token.');
        }

        self::assertSafeConnection(require __DIR__.'/database.php');
    }
}
