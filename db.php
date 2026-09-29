<?php

// Use Malaysia time for all dates (deadlines, cut-offs, ongoing tournaments)
date_default_timezone_set('Asia/Kuala_Lumpur');

$host = "localhost";
$user = "root";
$password = "";
$database = "tournaments";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}


?>