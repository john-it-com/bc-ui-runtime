<?php

/**
 * Purpose: Configure the disposable MariaDB connection for package tests.
 * Role: Mirror application session semantics without inheriting database URLs, sockets, or read/write routing.
 */

return [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'bc_ui_runtime_testing'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', 'testing'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'prefix_indexes' => true,
    'strict' => true,
    'engine' => 'InnoDB',
    'timezone' => '+00:00',
    'isolation_level' => 'REPEATABLE READ',
];
