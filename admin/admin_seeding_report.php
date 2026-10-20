<?php
require_once '../db.php';
require_once 'admin_auth.php';
require_once 'admin_excel_export.php';

// Platform Admins, or the admin who created this tournament
requireTournamentAccess($_GET['tournamentID'] ?? 0);

/*
|--------------------------------------------------------------------------
| TOURNAMENT ID
|--------------------------------------------------------------------------
*/

$tournamentID = isset($_GET['tournamentID'])
    ? intval($_GET['tournamentID'])
    : 0;

if ($tournamentID <= 0) {
    die("Invalid tournament ID.");
}

/*
|--------------------------------------------------------------------------
| CATEGORY
|--------------------------------------------------------------------------
*/

$category = isset($_GET['category'])
    ? trim($_GET['category'])
    : '';

if ($category === '') {
    die("No category selected.");
}

/*
|--------------------------------------------------------------------------
| GET TOURNAMENT
|--------------------------------------------------------------------------
*/

$tournamentStmt = $conn->prepare("
    SELECT *
    FROM tournament
    WHERE tournamentID = ?
");

$tournamentStmt->bind_param(
    "i",
    $tournamentID
);

$tournamentStmt->execute();

$tournamentResult = $tournamentStmt->get_result();

$tournament = $tournamentResult->fetch_assoc();

$tournamentStmt->close();

if (!$tournament) {
    die("Tournament not found.");
}

/*
|--------------------------------------------------------------------------
| GET SELECTED CATEGORY PLAYERS
|--------------------------------------------------------------------------
*/

$reportStmt = $conn->prepare("
    SELECT
        tr.registrationID,
        tr.category_registered,
        tr.seed_number,
        tr.endorsement,
        tr.endorsement_date,
        tr.admin_remark,
        p.playerID,
        p.player_full_name,
        p.player_nationality,
        r.world_ranking,
        r.national_ranking,
        r.ajss_ranking
    FROM tournament_register tr
    JOIN players p
        ON tr.playerID = p.playerID
    LEFT JOIN tournament_rankings r
        ON r.registrationID = tr.registrationID
    WHERE tr.tournamentID = ?
    AND tr.category_registered = ?
    ORDER BY
        CASE
            WHEN tr.seed_number IS NULL THEN 999999
            ELSE tr.seed_number
        END ASC,
        p.player_full_name ASC
");

$reportStmt->bind_param(
    "is",
    $tournamentID,
    $category
);

$reportStmt->execute();

$reportResult = $reportStmt->get_result();


/*
|--------------------------------------------------------------------------
| EXCEL FILE
|--------------------------------------------------------------------------
| Downloaded straight away: seeds and the rankings saved for this
| tournament (tournament_rankings), one row per player.
*/

// Ranking as a number for Excel, or empty
function reportRanking($value)
{
    $value = trim((string) $value);

    return ctype_digit($value) ? (int) $value : '';
}

function reportDate($value)
{
    return !empty($value) && strpos($value, '0000') !== 0 ? date('d M Y', strtotime($value)) : '';
}

$rows = [];
$no = 1;

while ($player = $reportResult->fetch_assoc()) {

    $rows[] = [
        $no++,
        $player['seed_number'] !== null ? (int) $player['seed_number'] : '',
        $player['playerID'],
        $player['player_full_name'],
        $player['player_nationality'],
        reportRanking($player['world_ranking']),
        reportRanking($player['national_ranking']),
        reportRanking($player['ajss_ranking']),
        strtoupper(trim($player['endorsement'] ?? '')) === 'ENDORSED' ? 'Endorsed' : 'Not endorsed',
    ];
}

$reportStmt->close();

$location = trim(implode(', ', array_filter([
    trim((string) ($tournament['tournament_location'] ?? '')),
    trim((string) ($tournament['tournament_country'] ?? '')),
])));

sendExcel(
    $tournament['tournament_name'] . ' - ' . $category . ' Seeding.xlsx',
    [
        'sheet' => $category . ' Seeding',
        'title' => 'Seeding Report - ' . $category,
        'info' => [
            ['Tournament', $tournament['tournament_name']],
            ['Category', $category],
            ['Location', $location],
            ['Dates', trim(reportDate($tournament['tournament_startdate']) . ' - ' . reportDate($tournament['tournament_enddate']), ' -')],
            ['Players', count($rows)],
            ['Downloaded', date('d M Y, h:i A')],
        ],
        'headers' => ['No.', 'Seed', 'Player ID', 'Player', 'Nationality', 'World Ranking', 'National Ranking', 'AJSS Ranking', 'Endorsement'],
        'rows' => $rows,
        'widths' => [6, 7, 11, 28, 18, 15, 17, 14, 15],
    ]
);
