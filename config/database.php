<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DATABASE_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],
        'wanxiang_haidian' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL'),
            'host' => env('WANXIANG_HOST'),
            'port' => env('WANXIANG_PORT'),
            'database' => env('WANXIANG_DATA'),
            'username' => env('WANXIANG_NAME'),
            // 'password' => env('WANXIANG_PASS'),
            'password' => 'JLWX@854%g!j#j',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ],
        'haoxinren' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL'),
            'host' => env('HAOXINREN_HOST'),
            'port' => env('HAOXINREN_PORT'),
            'database' => env('HAOXINREN_DATA'),
            'username' => env('HAOXINREN_NAME'),
            'password' => env('HAOXINREN_PASS'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ],
        'jishitang' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL'),
            'host' => env('JISHITANG_HOST'),
            'port' => env('JISHITANG_PORT'),
            'database' => env('JISHITANG_DATA'),
            'username' => env('JISHITANG_NAME'),
            'password' => env('JISHITANG_PASS'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ],
        'xuesong' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL'),
            'host' => 'xsyf.f3322.net',
            'port' => '1433',
            'database' => 'med',
            'username' => 'meituan',
            'password' => 'meituan',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ],
        'beikang' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL'),
            'host' => '115.29.33.161',
            'port' => '1433',
            'database' => 'mt',
            'username' => 'mt',
            'password' => 'Mqd@20220222',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ],
        'yaojugu' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL'),
            'host' => '183.71.244.66',
            'port' => '8888',
            'database' => 'hydee_ls',
            'username' => 'yjgMT',
            'password' => 'Yjg@0824.Mt',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ],
        'ruizhijia' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL'),
            'host' => '36.133.112.59',
            'port' => '8433',
            'database' => 'FZYUN150218',
            'username' => 'sa',
            'password' => '68622928',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 1),
        ],

    ],

];
