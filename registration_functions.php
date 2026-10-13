<?php

/*
|--------------------------------------------------------------------------
| Tournament registration helpers
|--------------------------------------------------------------------------
| Shared by the player pages (player/player_functions.php) and the admin
| page where an organizer registers a player (admin/admin_register_player.php).
*/

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
| Category Eligibility
|--------------------------------------------------------------------------
| BU13 / GU13 = the player must be under 13 on the age cut-off date.
*/

function categoryMaxAge($code)
{
    return preg_match('/^[BG]U(\d+)$/', $code, $matches) ? (int) $matches[1] : null;
}


/*
| Gender of a category: BU / Men = male, GU / Women = female, else null
*/
function categoryGender($code)
{
    if (preg_match('/^BU\d+$/', $code) || strcasecmp($code, 'Men') === 0) {
        return 'male';
    }

    if (preg_match('/^GU\d+$/', $code) || strcasecmp($code, 'Women') === 0) {
        return 'female';
    }

    return null;
}


// Age on the tournament's age cut-off date, or null when either date is missing
function ageOnCutoff($dob, $cutoff)
{
    $dobTime = strtotime((string) $dob);
    $cutoffTime = strtotime((string) $cutoff);

    if ($dobTime === false || $cutoffTime === false
        || strpos((string) $dob, '0000') === 0 || strpos((string) $cutoff, '0000') === 0) {
        return null;
    }

    return (new DateTime(date('Y-m-d', $dobTime)))->diff(new DateTime(date('Y-m-d', $cutoffTime)))->y;
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
