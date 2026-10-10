<?php

// Database Exporter: SQLite to MySQL for Masjidi
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 1. Setup SQLite reader connection
$sqlitePdo = DB::connection('sqlite')->getPdo();

// 2. Setup MySQL Schema Pretend connection
$fakePdo = new PDO('sqlite::memory:');
$mySqlConnection = new MySqlConnection($fakePdo, 'forge', '', [
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);
$grammar = new MySqlGrammar($mySqlConnection);
$mySqlConnection->setSchemaGrammar($grammar);

// Bind MySQL connection for schema builder
$app->instance('db.connection', $mySqlConnection);
Schema::swap($mySqlConnection->getSchemaBuilder());

$sqlOutput = [];

$sqlOutput[] = "-- ========================================================";
$sqlOutput[] = "-- مسجدي - نظام إدارة مالية المسجد";
$sqlOutput[] = "-- تصدير قاعدة البيانات الكاملة بصيغة MySQL / MariaDB";
$sqlOutput[] = "-- تاريخ التصدير: " . date('Y-m-d H:i:s');
$sqlOutput[] = "-- متوافق مع: MySQL 8.0+, MariaDB 10.4+, phpMyAdmin, Hostinger";
$sqlOutput[] = "-- ========================================================\n";
$sqlOutput[] = "SET NAMES utf8mb4;";
$sqlOutput[] = "SET FOREIGN_KEY_CHECKS = 0;";
$sqlOutput[] = "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';";
$sqlOutput[] = "SET time_zone = '+00:00';\n";

// 3. Create migrations table
$sqlOutput[] = "-- --------------------------------------------------------";
$sqlOutput[] = "-- هيكل جدول: `migrations`";
$sqlOutput[] = "-- --------------------------------------------------------";
$sqlOutput[] = "DROP TABLE IF EXISTS `migrations`;";
$sqlOutput[] = "CREATE TABLE `migrations` (";
$sqlOutput[] = "  `id` int unsigned NOT NULL AUTO_INCREMENT,";
$sqlOutput[] = "  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,";
$sqlOutput[] = "  `batch` int NOT NULL,";
$sqlOutput[] = "  PRIMARY KEY (`id`)";
$sqlOutput[] = ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n";

// 4. Collect migration DDL statements
$migrationFiles = glob(__DIR__ . '/database/migrations/*.php');
sort($migrationFiles);

$tableQueries = [];
$alterQueries = [];

foreach ($migrationFiles as $file) {
    $migration = require $file;
    if (is_object($migration) && method_exists($migration, 'up')) {
        $queries = $mySqlConnection->pretend(function() use ($migration) {
            $migration->up();
        });
        
        foreach ($queries as $q) {
            $sql = trim($q['query']);
            if (stripos($sql, 'create table') === 0) {
                // Extract table name
                if (preg_match('/create table `([^`]+)`/i', $sql, $m)) {
                    $tableQueries[$m[1]][] = "DROP TABLE IF EXISTS `{$m[1]}`;";
                }
                $tableQueries[$m[1]][] = $sql . ';';
            } elseif (stripos($sql, 'alter table') === 0) {
                $alterQueries[] = $sql . ';';
            } else {
                $alterQueries[] = $sql . ';';
            }
        }
    }
}

// 5. Output DDL for each table
foreach ($tableQueries as $tableName => $statements) {
    $sqlOutput[] = "-- --------------------------------------------------------";
    $sqlOutput[] = "-- هيكل جدول: `{$tableName}`";
    $sqlOutput[] = "-- --------------------------------------------------------";
    foreach ($statements as $st) {
        $sqlOutput[] = $st;
    }
    $sqlOutput[] = "";
}

// Output any Alter queries (indexes/foreign keys)
if (!empty($alterQueries)) {
    $sqlOutput[] = "-- --------------------------------------------------------";
    $sqlOutput[] = "-- المفاتيح الأجنبية والفهارس الإضافية";
    $sqlOutput[] = "-- --------------------------------------------------------";
    foreach ($alterQueries as $alt) {
        $sqlOutput[] = $alt;
    }
    $sqlOutput[] = "";
}

// 6. Export Data from SQLite
$sqliteTables = array_column(
    $sqlitePdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_ASSOC),
    'name'
);

$sqlOutput[] = "-- ========================================================";
$sqlOutput[] = "-- بيانات الجداول (Data Dumps)";
$sqlOutput[] = "-- ========================================================\n";

foreach ($sqliteTables as $table) {
    // Exclude cache table locks and temp sessions if empty
    $stmt = $sqlitePdo->query("SELECT * FROM `{$table}`");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($rows)) {
        continue;
    }
    
    $sqlOutput[] = "-- تفريغ بيانات جدول `{$table}` (" . count($rows) . " سجلات)";
    
    // Column names
    $columns = array_keys($rows[0]);
    $colList = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
    
    // Chunk inserts
    $chunks = array_chunk($rows, 50);
    foreach ($chunks as $chunk) {
        $valueRows = [];
        foreach ($chunk as $row) {
            $vals = [];
            foreach ($row as $val) {
                if ($val === null) {
                    $vals[] = 'NULL';
                } elseif (is_numeric($val) && (string)(int)$val === (string)$val) {
                    $vals[] = $val;
                } else {
                    $vals[] = "'" . addcslashes($val, "\x00\n\r\\'\"\x1a") . "'";
                }
            }
            $valueRows[] = "(" . implode(', ', $vals) . ")";
        }
        $sqlOutput[] = "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $valueRows) . ";";
    }
    $sqlOutput[] = "";
}

$sqlOutput[] = "SET FOREIGN_KEY_CHECKS = 1;";
$sqlOutput[] = "-- اكتمل التصدير بنجاح";

$finalSql = implode("\n", $sqlOutput);
$targetPath = __DIR__ . '/database/msjed_mysql.sql';
file_put_contents($targetPath, $finalSql);

echo "Successfully exported MySQL dump to: {$targetPath} (" . strlen($finalSql) . " bytes)\n";
