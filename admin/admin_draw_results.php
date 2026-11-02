<?php
require_once '../db.php';
require_once 'admin_auth.php';
require_once 'admin_excel_export.php';

// Platform Admins, or the admin who created this tournament
requireTournamentAccess($_GET['id'] ?? 0);
require 'admin_draw_functions.php';

/*
|--------------------------------------------------------------------------
| MATCH RESULTS (Excel)
|--------------------------------------------------------------------------
| Every match of a finished draw: who played whom, the score and the
| winner. Byes are left out. Only once the final has a winner.
*/

$tournamentID = isset($_GET['id']) ? intval($_GET['id']) : 0;
$category = trim($_GET['category'] ?? '');

$stmt = $conn->prepare("SELECT * FROM tournament WHERE tournamentID = ?");
$stmt->bind_param("i", $tournamentID);
$stmt->execute();
$tournament = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tournament || $category === '') {
    die("Tournament or category not found.");
}

$draw = loadDraw($conn, $tournamentID, $category);

if (!drawFinished($draw)) {
    die("The draw for " . htmlspecialchars($category) . " isn't finished yet - results can be downloaded once the final has a winner.");
}

function reportDate($value)
{
    return !empty($value) && strpos($value, '0000') !== 0 ? date('d M Y', strtotime($value)) : '';
}

// "[1] Sofia Chen (SGP)"
function resultPlayer($match, $number)
{
    $seed = $match['player' . $number . '_seed'];
    $country = countryCode($match['player' . $number . '_nationality'] ?? '');

    return trim(($seed !== null ? '[' . (int) $seed . '] ' : '')
        . ($match['player' . $number . '_name'] ?? $match['player' . $number . 'ID'])
        . ($country !== '' ? ' (' . $country . ')' : ''));
}

// Game columns: as many as the longest match (best of 5 -> Game 1 to Game 5)
$maxGames = 3;

foreach ($draw as $matches) {
    foreach ($matches as $match) {
        $maxGames = max($maxGames, (int) $match['best_of']);
    }
}

$rows = [];
$no = 1;

foreach ($draw as $matches) {
    foreach ($matches as $match) {

        if ($match['player1ID'] === null || $match['player2ID'] === null) {
            continue;
        }

        $winner = $match['winnerID'] === $match['player1ID'] ? 1 : ($match['winnerID'] === $match['player2ID'] ? 2 : 0);

        // One column per game; games that weren't played are "-"
        $games = $match['game_scores'] ? explode(',', $match['game_scores']) : [];
        $gameColumns = [];

        for ($game = 0; $game < $maxGames; $game++) {
            $gameColumns[] = $games[$game] ?? '-';
        }

        $rows[] = array_merge([
            $no++,
            $match['round_name'],
            (int) $match['match_number'],
            resultPlayer($match, 1),
            resultPlayer($match, 2),
            $winner ? resultPlayer($match, $winner) : '',
            $match['player1_score'] !== null ? $match['player1_score'] . '-' . $match['player2_score'] : '-',
        ], $gameColumns, [
            $match['result_status'] ?? '',
            $match['referee_name'] ?? '',
        ]);
    }
}

$finalRound = end($draw);
$final = reset($finalRound);
$champion = $final['winnerID'] === $final['player1ID'] ? 1 : 2;

$location = trim(implode(', ', array_filter([
    trim((string) ($tournament['tournament_location'] ?? '')),
    trim((string) ($tournament['tournament_country'] ?? '')),
])));

sendExcel(
    $tournament['tournament_name'] . ' - ' . $category . ' Results.xlsx',
    [
        'sheet' => $category . ' Results',
        'title' => 'Match Results - ' . $category,
        'info' => [
            ['Tournament', $tournament['tournament_name']],
            ['Category', $category],
            ['Location', $location],
            ['Dates', trim(reportDate($tournament['tournament_startdate']) . ' - ' . reportDate($tournament['tournament_enddate']), ' -')],
            ['Winner', resultPlayer($final, $champion)],
            ['Runner-up', resultPlayer($final, 3 - $champion)],
            ['Downloaded', date('d M Y, h:i A')],
        ],
        'headers' => array_merge(
            ['No.', 'Round', 'Match', 'Player 1', 'Player 2', 'Winner', 'Games'],
            array_map(function ($game) { return 'Game ' . $game; }, range(1, $maxGames)),
            ['Status', 'Referee']
        ),
        'rows' => $rows,
        'widths' => array_merge([6, 15, 8, 32, 32, 32, 8], array_fill(0, $maxGames, 9), [13, 26]),
    ]
);
