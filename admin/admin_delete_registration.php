<?php

require_once '../db.php';
require_once 'admin_auth.php';

// Platform Admins, or the admin who created this tournament
requireRegistrationAccess($_GET['id'] ?? 0);

$registrationID = isset($_GET['id'])
    ? intval($_GET['id'])
    : 0;

if ($registrationID <= 0) {

    die("Invalid registration ID.");

}

$sql = "
    DELETE FROM tournament_register

    WHERE registrationID = ?
";


$stmt = $conn->prepare($sql);


if (!$stmt) {

    die(
        "Prepare failed: "
        . $conn->error
    );

}


$stmt->bind_param(
    "i",
    $registrationID
);


if ($stmt->execute()) {


    if ($stmt->affected_rows > 0) {

        $stmt->close();


        header(
            "Location: admin_index.php");

        exit;

    }

    $stmt->close();

    die("Registration not found.");

}

$error = $stmt->error;

$stmt->close();

die(
    "Delete failed: "
    . $error
);

?>