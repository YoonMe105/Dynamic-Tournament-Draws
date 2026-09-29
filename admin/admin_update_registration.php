<?php

require_once "../db.php";


/*
|--------------------------------------------------------------------------
| Only allow POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header(
        "Location: admin_player_tournament_registration.php"
    );

    exit;

}


/*
|--------------------------------------------------------------------------
| Get Registration ID
|--------------------------------------------------------------------------
*/

$registrationID = isset($_POST['registrationID'])
    ? intval($_POST['registrationID'])
    : 0;


/*
|--------------------------------------------------------------------------
| Get values
|--------------------------------------------------------------------------
*/

$status = isset($_POST['status'])
    ? trim($_POST['status'])
    : '';

$payment_status = isset($_POST['payment_status'])
    ? strtoupper(trim($_POST['payment_status']))
    : '';

$remarks = isset($_POST['remarks'])
    ? trim($_POST['remarks'])
    : '';

$category = isset($_POST['category_registered']) ? trim($_POST['category_registered']) : "";
/*
|--------------------------------------------------------------------------
| Validate Registration ID
|--------------------------------------------------------------------------
*/

if ($registrationID <= 0) {

    die("Invalid registration ID.");

}



$allowedStatuses = [
    "NOT ENDORSED",
    "ENDORSED",
    "Banned",
    "Withdraw",
    "Walkover"
];


if (!in_array($status, $allowedStatuses, true)) {

    die("Invalid status.");

}


$allowedPaymentStatuses = [
    "NOT PAID",
    "PENDING",
    "PAID",
    "REFUNDED"
];


if (
    !in_array(
        $payment_status,
        $allowedPaymentStatuses,
        true
    )
) {

    die("Invalid payment status.");

}


/*
|--------------------------------------------------------------------------
| Update Database
|--------------------------------------------------------------------------
*/

$sql = "
    UPDATE tournament_register

    SET
        endorsement = ?,
        payment_status = ?,
        category_registered = ?,
        admin_remark = ?

    WHERE registrationID = ?
";


$stmt = $conn->prepare($sql);


if (!$stmt) {

    die(
        "Prepare failed: "
        . $conn->error
    );

}


/*
|--------------------------------------------------------------------------
| Bind parameters
|--------------------------------------------------------------------------
|
| s = string
| s = string
| s = string
| i = integer
|
*/

$stmt->bind_param( "ssssi", $status, $payment_status, $category, $remarks, $registrationID );


/*
|--------------------------------------------------------------------------
| Execute
|--------------------------------------------------------------------------
*/

if ($stmt->execute()) {

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Redirect back
    |--------------------------------------------------------------------------
    */

    header(
        "Location: admin_player_tournament_registration.php"
        . "?id="
        . $registrationID
    );

    exit;

}


/*
|--------------------------------------------------------------------------
| Update Failed
|--------------------------------------------------------------------------
*/

$error = $stmt->error;

$stmt->close();

die(
    "Update failed: "
    . $error
);

?>