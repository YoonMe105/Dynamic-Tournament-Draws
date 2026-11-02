<?php
require_once '../db.php';
require_once 'admin_auth.php';
require_once 'admin_excel_export.php';

// Platform Admins, or the admin who created this tournament
requireTournamentAccess($_GET['id'] ?? 0);
require 'admin_draw_functions.php';

/*
|--------------------------------------------------------------------------
| WINNERS LIST (Excel)
|--------------------------------------------------------------------------
| Every player of each finished category with their final place: Winner,
| Runner-up, 3/4, 5/8 ... by the round they lost in. Categories whose
| final has no winner yet are left out.
*/

$tournamentID = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $conn->prepare("SELECT * FROM tournament WHERE tournamentID = ?");
$stmt->bind_param("i", $tournamentID);
$stmt->execute();
$tournament = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tournament) {
    die("Tournament not found.");
}

$stmt = $conn->prepare("SELECT DISTINCT category_registered FROM matches WHERE tournamentID = ? ORDER BY category_registered ASC");
$stmt->bind_param("i", $tournamentID);
$stmt->execute();
$categories = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'category_registered');
$stmt->close();

function reportDate($value)
{
    return !empty($value) && strpos($value, '0000') !== 0 ? date('d M Y', strtotime($value)) : '';
}

// [seed, name, country] of player 1 or 2 in a match
function winnerListPlayer($match, $number)
{
    $seed = $match['player' . $number . '_seed'];

    return [
        $seed !== null ? (int) $seed : '',
        $match['player' . $number . '_name'] ?? $match['player' . $number . 'ID'],
        countryCode($match['player' . $number . '_nationality'] ?? ''),
    ];
}

// 1 or 2: the side that lost the match
function loserSide($match)
{
    return $match['winnerID'] === $match['player1ID'] ? 2 : 1;
}

$rows = [];
$finished = [];
$notFinished = [];

foreach ($categories as $category) {

    $draw = loadDraw($conn, $tournamentID, $category);

    if (!drawFinished($draw)) {
        $notFinished[] = $category;
        continue;
    }

    $finished[] = $category;

    $roundCount = count($draw);
    $finalRound = end($draw);
    $final = reset($finalRound);
    $loser = loserSide($final);

    $rows[] = array_merge([$category, 1, 'Winner'], winnerListPlayer($final, 3 - $loser), ['', '']);
    $place = 2;

    /*
    | Everyone else is placed 2, 3, 4 ... by the round they lost in (a bye
    | has no loser): best round first, then by seed within a round.
    */
    for ($round = $roundCount; $round >= 1; $round--) {

        $lost = [];

        foreach ($draw[$round] as $match) {
            if ($match['player1ID'] !== null && $match['player2ID'] !== null && $match['winnerID'] !== null) {

                $side = loserSide($match);
                $score = $match['player1_score'] !== null
                    ? $match['player' . (3 - $side) . '_score'] . '-' . $match['player' . $side . '_score']
                    : '';

                $lost[] = [
                    'seed' => $match['player' . $side . '_seed'] ?? 999999,
                    'row' => array_merge(
                        [$category, 0],
                        [$round === $roundCount ? 'Runner-up' : 'Lost in ' . $match['round_name']],
                        winnerListPlayer($match, $side),
                        [$match['player' . (3 - $side) . '_name'] ?? $match['player' . (3 - $side) . 'ID'],
                         trim($score . ($match['result_status'] ? ' (' . $match['result_status'] . ')' : ''))]
                    ),
                ];
            }
        }

        usort($lost, function ($a, $b) {
            return $a['seed'] <=> $b['seed'];
        });

        foreach ($lost as $player) {
            $player['row'][1] = $place++;
            $rows[] = $player['row'];
        }
    }
}

if (!$rows) {
    die("No category in this tournament is finished yet - the winners list can be downloaded once a final has a winner.");
}

$location = trim(implode(', ', array_filter([
    trim((string) ($tournament['tournament_location'] ?? '')),
    trim((string) ($tournament['tournament_country'] ?? '')),
])));

$info = [
    ['Tournament', $tournament['tournament_name']],
    ['Location', $location],
    ['Dates', trim(reportDate($tournament['tournament_startdate']) . ' - ' . reportDate($tournament['tournament_enddate']), ' -')],
    ['Categories', implode(', ', $finished)],
];

if ($notFinished) {
    $info[] = ['Not finished yet', implode(', ', $notFinished)];
}

$info[] = ['Downloaded', date('d M Y, h:i A')];

sendExcel(
    $tournament['tournament_name'] . ' - Winners List.xlsx',
    [
        'sheet' => 'Winners List',
        'title' => 'Winners List - ' . $tournament['tournament_name'],
        'info' => $info,
        'headers' => ['Category', 'Place', 'Result', 'Seed', 'Player', 'Country', 'Lost To', 'Score'],
        'rows' => $rows,
        'widths' => [14, 9, 24, 7, 30, 10, 30, 16],
    ]
);
