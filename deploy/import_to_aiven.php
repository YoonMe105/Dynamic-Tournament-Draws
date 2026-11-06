<?php

/*
|--------------------------------------------------------------------------
| Copy the local XAMPP database into Aiven MySQL
|--------------------------------------------------------------------------
| Run from the tournament folder:
|
|   C:\xampp\php\php.exe deploy\import_to_aiven.php
|
| It asks for the Aiven "Service URI" (mysql://avnadmin:...@host:port/defaultdb?ssl-mode=REQUIRED)
| and the path of the downloaded CA certificate (ca.pem), dumps the local
| "tournaments" database with mysqldump, loads it into a "tournaments" database
| on Aiven and compares the row counts. Running it again replaces the tables.
*/

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

function ask($question)
{
    echo $question . ': ';
    return trim((string) fgets(STDIN));
}

$uri = ask('Aiven Service URI');
$parts = parse_url($uri);

if (!$parts || ($parts['scheme'] ?? '') !== 'mysql' || empty($parts['host']) || empty($parts['user'])) {
    exit("That doesn't look like a Service URI (mysql://user:password@host:port/defaultdb).\n");
}

$caFile = trim(ask('Path to ca.pem'), " \"'");

if (!is_file($caFile)) {
    exit("CA certificate not found: $caFile\n");
}

$database = 'tournaments';
$mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';

// Local dump (same options as checked against MySQL 8)
echo "Dumping the local database...\n";
$dumpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tournaments_dump.sql';
exec('"' . $mysqldump . '" -uroot --single-transaction --skip-triggers --no-tablespaces ' . $database
    . ' > "' . $dumpFile . '"', $output, $code);

if ($code !== 0 || !filesize($dumpFile)) {
    exit("mysqldump failed - is MySQL running in XAMPP?\n");
}

// Aiven (SSL only)
echo "Connecting to Aiven...\n";
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$aiven = mysqli_init();
$aiven->ssl_set(null, null, $caFile, null, null);
$aiven->real_connect($parts['host'], urldecode($parts['user']), urldecode($parts['pass'] ?? ''), null,
    (int) ($parts['port'] ?? 3306), null, MYSQLI_CLIENT_SSL);

$aiven->query("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$aiven->select_db($database);

echo "Loading " . round(filesize($dumpFile) / 1024) . " KB into Aiven...\n";
$aiven->multi_query(file_get_contents($dumpFile));

do {
    if ($result = $aiven->store_result()) {
        $result->free();
    }
} while ($aiven->more_results() && $aiven->next_result());

unlink($dumpFile);

// Compare row counts with the local database
$local = new mysqli('localhost', 'root', '', $database);
$tables = $local->query("SHOW TABLES")->fetch_all();
$allMatch = true;

echo "\n" . str_pad('Table', 32) . str_pad('Local', 8) . "Aiven\n";

foreach ($tables as [$table]) {
    $here = $local->query("SELECT COUNT(*) FROM `$table`")->fetch_row()[0];
    $there = $aiven->query("SELECT COUNT(*) FROM `$table`")->fetch_row()[0];
    $allMatch = $allMatch && $here === $there;

    echo str_pad($table, 32) . str_pad($here, 8) . $there . ($here === $there ? '' : '   <-- different') . "\n";
}

echo $allMatch ? "\nDone - every table copied.\n" : "\nSome tables are different - check the errors above.\n";
