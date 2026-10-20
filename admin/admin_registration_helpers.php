<?php

require_once __DIR__ . '/../registration_functions.php';

/*
|--------------------------------------------------------------------------
| Registering players (organizer / Platform Admin)
|--------------------------------------------------------------------------
| Shared by admin_register_player.php (one player) and
| admin_register_players_excel.php (many players from an Excel file), so
| both follow the same rules as when players register themselves.
*/

function registrationNationalities()
{
    return [
        'MALAYSIAN', 'SINGAPOREAN', 'INDONESIAN', 'THAI', 'FILIPINO', 'VIETNAMESE', 'BRUNEIAN', 'CAMBODIAN',
        'MYANMAR', 'CHINESE', 'JAPANESE', 'KOREAN', 'INDIAN', 'PAKISTANI', 'BANGLADESHI', 'SRI LANKAN',
        'NEPALESE', 'AUSTRALIAN', 'NEW ZEALANDER', 'BRITISH', 'AMERICAN', 'CANADIAN', 'FRENCH', 'GERMAN',
    ];
}


// "Male", "M", "boy" -> 'male'; "Female", "F", "girl" -> 'female'; else null
function normalGender($value)
{
    $value = strtolower(trim((string) $value));

    if (in_array($value, ['male', 'm', 'boy', 'boys', 'man', 'men'], true)) {
        return 'male';
    }

    if (in_array($value, ['female', 'f', 'girl', 'girls', 'woman', 'women'], true)) {
        return 'female';
    }

    return null;
}


/*
| Why a category can't be chosen for this gender / age ('' = it can)
*/
function categoryProblem($code, $gender, $age, $hasCutoff)
{
    $categoryGender = categoryGender($code);
    $maxAge = categoryMaxAge($code);

    if ($gender !== null && $categoryGender !== null && $categoryGender !== $gender) {
        return $code . ' is for ' . ($categoryGender === 'male' ? 'boys / men' : 'girls / women') . ' only.';
    }

    if ($maxAge !== null && $hasCutoff && $age === null) {
        return 'The player needs a date of birth for ' . $code . '.';
    }

    if ($maxAge !== null && $hasCutoff && $age >= $maxAge) {
        return 'The player is ' . $age . ' on the age cut-off date, too old for ' . $code . ' (must be under ' . $maxAge . ').';
    }

    return '';
}


// The youngest category the player can still enter, or null
function recommendedCategory($categories, $gender, $age, $hasCutoff)
{
    $best = null;

    foreach ($categories as $code => $name) {

        if (categoryProblem($code, $gender, $age, $hasCutoff) !== '') {
            continue;
        }

        $maxAge = categoryMaxAge($code) ?? PHP_INT_MAX;

        if ($best === null || $maxAge < (categoryMaxAge($best) ?? PHP_INT_MAX)) {
            $best = $code;
        }
    }

    return $best;
}


// "BU15", "bu15", "Boys Under 15 Open Championship" -> the tournament's code, or null
function findCategory($categories, $value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    foreach ($categories as $code => $name) {
        if (strcasecmp($code, $value) === 0 || strcasecmp($name, $value) === 0) {
            return $code;
        }
    }

    $code = categoryCode($value, '', 0);

    return isset($categories[$code]) ? $code : null;
}


// Next free player ID: P0035 -> P0036
function nextPlayerID($conn)
{
    $row = $conn->query("
        SELECT MAX(CAST(SUBSTRING(playerID, 2) AS UNSIGNED)) AS last_number
        FROM players WHERE playerID REGEXP '^P[0-9]+$'
    ")->fetch_assoc();

    return 'P' . str_pad((string) ((int) $row['last_number'] + 1), 4, '0', STR_PAD_LEFT);
}


/*
| New player account. Their first password is their player ID (they can
| change it in My Profile). Returns the new player ID; throws on failure.
| $data: first_name, last_name, gender ('male'/'female'), dob (Y-m-d),
|        nationality, email, contact, national_ranking, ajss_ranking, world_ranking
*/
function createPlayer($conn, array $data)
{
    $playerID = nextPlayerID($conn);
    $fullName = $data['first_name'] . ' ' . $data['last_name'];
    $email = ($data['email'] ?? '') !== '' ? $data['email'] : null;
    $contact = $data['contact'] ?? '';
    $national = ($data['national_ranking'] ?? '') !== '' ? $data['national_ranking'] : null;
    $ajss = $data['ajss_ranking'] ?? '';
    $world = $data['world_ranking'] ?? '';
    $gender = ucfirst($data['gender']);
    $status = 'offline';

    $stmt = $conn->prepare("
        INSERT INTO players (
            playerID, player_password, player_full_name, player_first_name, player_last_name,
            player_email, player_dob, player_gender, player_nationality, player_contact,
            national_ranking, ajss_ranking, world_ranking, player_passport, player_status, player_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', ?, 'active')
    ");
    $stmt->bind_param(
        "ssssssssssssss",
        $playerID, $playerID, $fullName, $data['first_name'], $data['last_name'],
        $email, $data['dob'], $gender, $data['nationality'], $contact,
        $national, $ajss, $world, $status
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('the player could not be created');
    }

    $stmt->close();

    return $playerID;
}


/*
| Registers a player in a tournament and saves their rankings for seeding.
| Returns the registration ID; throws on failure.
*/
function insertRegistration($conn, $tournamentID, $playerID, $category, $paymentStatus, $amountPaid, $endorsement, $remark)
{
    $paid = $paymentStatus === 'PAID';
    $endorsed = $endorsement === 'ENDORSED';
    $amountPaid = $paid && $amountPaid !== '' ? $amountPaid : null;
    $paymentDate = $paid ? date('Y-m-d H:i:s') : null;
    $endorsementDate = $endorsed ? date('Y-m-d') : null;

    $stmt = $conn->prepare("
        INSERT INTO tournament_register (
            category_registered, payment_status, amount_paid, payment_date,
            endorsement, endorsement_date, admin_remark, tournamentID, playerID
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "sssssssis",
        $category, $paymentStatus, $amountPaid, $paymentDate,
        $endorsement, $endorsementDate, $remark, $tournamentID, $playerID
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('the registration could not be saved');
    }

    $registrationID = $stmt->insert_id;
    $stmt->close();

    // Rankings at registration time (used for seeding)
    if (!saveRegistrationRankings($conn, $registrationID)) {
        throw new Exception('the rankings could not be saved');
    }

    return $registrationID;
}


// Players already registered in this tournament: [playerID => true]
function registeredPlayerIDs($conn, $tournamentID)
{
    $stmt = $conn->prepare("SELECT playerID FROM tournament_register WHERE tournamentID = ? AND playerID IS NOT NULL");
    $stmt->bind_param("i", $tournamentID);
    $stmt->execute();

    $ids = [];

    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $ids[$row['playerID']] = true;
    }

    $stmt->close();

    return $ids;
}
