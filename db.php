<?php

// Use Malaysia time for all dates (deadlines, cut-offs, ongoing tournaments)
date_default_timezone_set('Asia/Kuala_Lumpur');

// On Render the connection comes from environment variables (Aiven MySQL);
// on XAMPP none are set, so the local database is used
$host = getenv('DB_HOST') ?: "localhost";
$port = (int) (getenv('DB_PORT') ?: 3306);
$user = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASSWORD') ?: "";
$database = getenv('DB_NAME') ?: "tournaments";

$conn = mysqli_init();

// Aiven only accepts SSL connections; DB_SSL_CA holds its CA certificate (the PEM text)
$sslCa = getenv('DB_SSL_CA');
$flags = 0;

if ($sslCa) {
    $caFile = sys_get_temp_dir() . '/db-ca.pem';

    if (!is_file($caFile) || file_get_contents($caFile) !== $sslCa) {
        file_put_contents($caFile, $sslCa);
    }

    $conn->ssl_set(null, null, $caFile, null, null);
    $flags = MYSQLI_CLIENT_SSL;
}

try {
    $conn->real_connect($host, $user, $password, $database, $port, null, $flags);
} catch (mysqli_sql_exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Same behaviour as the local MariaDB: Malaysia time for NOW(), and its lenient SQL mode
// (MySQL 8 would otherwise reject some GROUP BY queries and empty dates)
$conn->query("SET time_zone = '+08:00'");
$conn->query("SET SESSION sql_mode = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'");


?>