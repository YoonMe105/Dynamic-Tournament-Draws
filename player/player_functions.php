<?php

/*
|--------------------------------------------------------------------------
| Shared helpers for the player tournament pages
|--------------------------------------------------------------------------
*/

session_start();

if (!isset($_SESSION["userid"]) || !isset($_SESSION["role"]) || $_SESSION["role"] !== "player") {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";

$playerID = $_SESSION["userid"];


// A player deactivated by a Platform Admin is logged out straight away
$activeStmt = $conn->prepare("SELECT player_active FROM players WHERE playerID = ?");
$activeStmt->bind_param("s", $playerID);
$activeStmt->execute();

$activeRow = $activeStmt->get_result()->fetch_assoc();

$activeStmt->close();

if (!$activeRow || $activeRow['player_active'] !== 'active') {
    session_unset();
    session_destroy();
    header("Location: ../login.php?deactivated=1");
    exit();
}


/*
|--------------------------------------------------------------------------
| Records
|--------------------------------------------------------------------------
*/

function getTournament($conn, $tournamentID)
{
    $stmt = $conn->prepare("SELECT * FROM tournament WHERE tournamentID = ?");
    $stmt->bind_param("i", $tournamentID);
    $stmt->execute();

    $tournament = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return $tournament;
}

function getPlayer($conn, $playerID)
{
    $stmt = $conn->prepare("SELECT * FROM players WHERE playerID = ?");
    $stmt->bind_param("s", $playerID);
    $stmt->execute();

    $player = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return $player;
}

function getRegistration($conn, $tournamentID, $playerID)
{
    $stmt = $conn->prepare("
        SELECT *
        FROM tournament_register
        WHERE tournamentID = ?
          AND playerID = ?
        LIMIT 1
    ");
    $stmt->bind_param("is", $tournamentID, $playerID);
    $stmt->execute();

    $registration = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return $registration;
}


/*
|--------------------------------------------------------------------------
| Registration Open?
|--------------------------------------------------------------------------
| A missing or zero deadline (0000-00-00) counts as closed. A tournament
| whose organizer hasn't paid the T_Software fee yet is visible but not
| open for registration.
*/

function isRegistrationOpen($tournament)
{
    global $conn;

    $deadlineTime = strtotime($tournament['tournament_deadline'] ?? '');

    $beforeDeadline = $deadlineTime !== false && $deadlineTime > 0 && $deadlineTime >= time();

    return $beforeDeadline && !platformFeeUnpaid($conn, $tournament['tournamentID']);
}


// Deadline not passed yet, but the organizer's T_Software fee is still unpaid
function registrationOpensSoon($tournament)
{
    global $conn;

    $deadlineTime = strtotime($tournament['tournament_deadline'] ?? '');

    return $deadlineTime !== false && $deadlineTime >= time() && platformFeeUnpaid($conn, $tournament['tournamentID']);
}


/*
|--------------------------------------------------------------------------
| Payment Allowed?
|--------------------------------------------------------------------------
| The player can upload (or re-upload) a receipt until an admin marks the
| registration as paid or refunded.
*/

function canPay($registration)
{
    $status = strtoupper(trim($registration['payment_status'] ?? ''));

    return !in_array($status, ['PAID', 'REFUNDED'], true);
}


/*
|--------------------------------------------------------------------------
| Category Code
|--------------------------------------------------------------------------
| Registrations store the short code (BU15, GU11) that the admin pages use.
| Category names are either already a code, or a long name such as
| "Boys Under 15 Open Championship". Some rows have no gender/age, so the
| name is checked first.
*/

function categoryCode($name, $gender, $age)
{
    $name = trim($name);

    if (preg_match('/^[BG]U\d+$/i', $name)) {
        return strtoupper($name);
    }

    if (preg_match('/\b(boys?|girls?)\b.*?\bunder\s*(\d+)/i', $name, $matches)) {

        $letter = strtolower($matches[1][0]) === 'b' ? 'B' : 'G';

        return $letter . 'U' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
    }

    $gender = strtolower(trim($gender));

    if ((int) $age > 0 && in_array($gender, ['male', 'boys', 'female', 'girls'], true)) {

        $letter = in_array($gender, ['male', 'boys'], true) ? 'B' : 'G';

        return $letter . 'U' . str_pad($age, 2, '0', STR_PAD_LEFT);
    }

    return $name;
}


/*
|--------------------------------------------------------------------------
| Categories
|--------------------------------------------------------------------------
| Returns [code => name], boys first, oldest age group first.
| Only the categories saved for the tournament in tournament_category are
| offered, so players can't register for a category the tournament
| doesn't have.
*/

function getCategories($conn, $tournamentID)
{
    $stmt = $conn->prepare("
        SELECT category_name, age, gender
        FROM tournament_category
        WHERE tournamentID = ?
    ");
    $stmt->bind_param("i", $tournamentID);
    $stmt->execute();

    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt->close();

    $categories = [];

    foreach ($rows as $category) {

        $code = categoryCode($category['category_name'], $category['gender'], $category['age']);

        $categories[$code] = $category['category_name'];
    }

    uksort($categories, function ($a, $b) {

        $isCodeA = preg_match('/^([BG])U(\d+)$/', $a, $matchA);
        $isCodeB = preg_match('/^([BG])U(\d+)$/', $b, $matchB);

        if ($isCodeA && $isCodeB) {
            return [$matchA[1], -(int) $matchA[2]] <=> [$matchB[1], -(int) $matchB[2]];
        }

        return [!$isCodeA, $a] <=> [!$isCodeB, $b];
    });

    return $categories;
}


/*
|--------------------------------------------------------------------------
| Fees
|--------------------------------------------------------------------------
| Fees are stored in one column (tournament_fee). International
| tournaments store "Local: RM120.00, Foreign: USD 60.00": Malaysian
| players pay the Local fee, foreign players the Foreign fee.
| See ../fees.php.
*/

require_once __DIR__ . '/../fees.php';
require_once __DIR__ . '/../platform_fee.php';

/*
| ['RM' => ..., 'USD' => ...] with only the currencies the fee has.
| A fee in another currency (SGD, INR, ...) or without an amount is
| returned as ['OTHER' => the text].
*/
function tournamentFees($tournament)
{
    $parts = splitFee($tournament['tournament_fee'] ?? '');

    if ($parts['currency'] === 'RM+USD') {
        return ['RM' => $parts['local'], 'USD' => $parts['foreign']];
    }

    return [$parts['currency'] ?? 'OTHER' => $parts['local']];
}

function playerFee($tournament, $player)
{
    $fees = tournamentFees($tournament);

    $isMalaysian = stripos($player['player_nationality'] ?? '', 'malaysia') === 0;

    // RM + USD: Malaysian players pay RM, foreign players pay USD
    if (count($fees) > 1) {
        return $isMalaysian
            ? ['currency' => 'RM', 'fee' => $fees['RM'], 'label' => 'Malaysian players (RM)']
            : ['currency' => 'USD', 'fee' => $fees['USD'], 'label' => 'Foreign players (USD)'];
    }

    // One fee for everyone, in whatever currency it is
    $currency = array_key_first($fees);

    return ['currency' => $currency, 'fee' => $fees[$currency], 'label' => $currency === 'OTHER' ? '' : $currency];
}


/*
|--------------------------------------------------------------------------
| Display Helpers
|--------------------------------------------------------------------------
*/

function formatDate($value)
{
    $time = strtotime($value ?? '');

    return ($time === false || $time <= 0) ? '-' : date("d M Y, h:i A", $time);
}

function formatCutoffDate($value)
{
    $time = strtotime($value ?? '');

    return ($time === false || $time <= 0) ? 'Not specified' : date("d M Y", $time);
}

function statusClass($value)
{
    $value = strtoupper(trim($value ?? ''));

    if ($value === 'PAID' || $value === 'ENDORSED') {
        return 'status-good';
    }

    if (in_array($value, ['BANNED', 'WITHDRAW', 'REFUNDED'], true)) {
        return 'status-bad';
    }

    return 'status-pending';
}


/*
|--------------------------------------------------------------------------
| File Upload
|--------------------------------------------------------------------------
| Returns [fileName, error]. fileName is null when no file was chosen.
*/

function uploadFile($input, $folder, $prefix)
{
    if (!isset($_FILES[$input]) || $_FILES[$input]['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, ''];
    }

    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    $fileExtension = strtolower(pathinfo($_FILES[$input]['name'], PATHINFO_EXTENSION));

    if ($_FILES[$input]['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Failed to upload file.'];
    }

    if (!in_array($fileExtension, $allowedExtensions, true)) {
        return [null, 'Invalid file format. Please upload PDF, JPG, JPEG, PNG or WEBP.'];
    }

    if ($_FILES[$input]['size'] > 5 * 1024 * 1024) {
        return [null, 'File must be 5MB or smaller.'];
    }

    $uploadDir = '../uploads/' . $folder . '/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $newFileName = uniqid($prefix . '_', true) . '.' . $fileExtension;

    if (!move_uploaded_file($_FILES[$input]['tmp_name'], $uploadDir . $newFileName)) {
        return [null, 'Failed to upload file.'];
    }

    return [$newFileName, ''];
}


/*
|--------------------------------------------------------------------------
| Ranking Record
|--------------------------------------------------------------------------
| Copies the player's rankings at the time of registration into
| tournament_rankings (one row per registration). The seed is filled in
| later, when the admin runs the seeding.
*/

function saveRegistrationRankings($conn, $registrationID)
{
    $stmt = $conn->prepare("
        INSERT INTO tournament_rankings (
            registrationID, tournamentID, playerID, category_registered,
            national_ranking, ajss_ranking, world_ranking
        )
        SELECT
            tr.registrationID, tr.tournamentID, tr.playerID, tr.category_registered,
            IF(p.national_ranking REGEXP '^[0-9]+$', CAST(p.national_ranking AS UNSIGNED), NULL),
            IF(p.ajss_ranking REGEXP '^[0-9]+$', CAST(p.ajss_ranking AS UNSIGNED), NULL),
            IF(p.world_ranking REGEXP '^[0-9]+$', CAST(p.world_ranking AS UNSIGNED), NULL)
        FROM tournament_register tr
        JOIN players p ON p.playerID = tr.playerID
        WHERE tr.registrationID = ?
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("i", $registrationID);

    $saved = $stmt->execute() && $stmt->affected_rows === 1;

    $stmt->close();

    return $saved;
}
