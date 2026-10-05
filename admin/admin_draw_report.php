<?php
require_once '../db.php';
require_once 'admin_auth.php';

// Platform Admins, or the admin who created this tournament
requireTournamentAccess($_GET['id'] ?? 0);
require 'admin_draw_functions.php';


/*
|--------------------------------------------------------------------------
| TOURNAMENT AND CATEGORY
|--------------------------------------------------------------------------
*/

$tournamentID = isset($_GET['id']) ? intval($_GET['id']) : 0;
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

if ($tournamentID <= 0) {
    die("Invalid tournament ID.");
}

if ($category === '') {
    die("No category selected.");
}

$stmt = $conn->prepare("SELECT * FROM tournament WHERE tournamentID = ?");
$stmt->bind_param("i", $tournamentID);
$stmt->execute();

$tournament = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$tournament) {
    die("Tournament not found.");
}


/*
|--------------------------------------------------------------------------
| DRAW
|--------------------------------------------------------------------------
*/

$draw = loadDraw($conn, $tournamentID, $category);

if (!$draw) {
    die("There is no draw for " . htmlspecialchars($category) . " yet.");
}

$drawSize = count($draw[1]) * 2;
$drawMade = reset($draw[1])['created_at'];


/*
|--------------------------------------------------------------------------
| PLAYER LIST (from round 1: who each player meets first)
|--------------------------------------------------------------------------
*/

$players = [];

foreach ($draw[1] as $match) {

    foreach ([1, 2] as $number) {

        $other = $number === 1 ? 2 : 1;

        if ($match['player' . $number . 'ID'] === null) {
            continue;
        }

        $players[] = [
            'seed' => $match['player' . $number . '_seed'],
            'name' => $match['player' . $number . '_name'] ?? $match['player' . $number . 'ID'],
            'nationality' => $match['player' . $number . '_nationality'],
            'position' => ((int)$match['match_number'] - 1) * 2 + $number,
            'match' => (int)$match['match_number'],
            'opponent' => $match['player' . $other . 'ID'] === null
                ? null
                : ($match['player' . $other . '_name'] ?? $match['player' . $other . 'ID']),
            'opponent_seed' => $match['player' . $other . '_seed']
        ];
    }
}

// Seeds first (by seed), then everyone else by name
usort($players, function ($a, $b) {

    if (($a['seed'] === null) !== ($b['seed'] === null)) {
        return $a['seed'] === null ? 1 : -1;
    }

    if ($a['seed'] !== null) {
        return $a['seed'] - $b['seed'];
    }

    return strcasecmp($a['name'], $b['name']);
});

$seededCount = count(array_filter($players, function ($player) {
    return $player['seed'] !== null;
}));

$byes = $drawSize - count($players);


function reportDate($value, $format = 'd M Y')
{
    $time = strtotime($value ?? '');

    return ($time && strpos($value, '0000') !== 0) ? date($format, $time) : '-';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Draw Report -
        <?= htmlspecialchars($category) ?>
    </title>

    <link rel="stylesheet" href="assets/draw.css?v=<?= filemtime(__DIR__ . '/assets/draw.css') ?>">

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            font-family: Arial, Helvetica, sans-serif;
            background: #ffffff;
            color: #333;
        }

        .report-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .report-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .print-button {
            display: inline-block;
            padding: 10px 18px;
            background: #993D86;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            font-family: inherit;
        }

        .print-button:hover {
            background: #7f316f;
        }

        .print-button.outline {
            background: #ffffff;
            color: #993D86;
            border: 1px solid #993D86;
        }

        .print-button.outline:hover {
            background: #f7eef5;
        }

        .print-button:disabled {
            opacity: 0.6;
            cursor: wait;
        }

        .report-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .report-header h1 {
            margin: 0 0 10px;
            font-size: 26px;
            color: #333;
        }

        .report-header h2 {
            margin: 0 0 8px;
            font-size: 20px;
            color: #993D86;
        }

        .report-header p {
            margin: 4px 0;
            font-size: 14px;
            color: #666;
        }

        .report-info {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px 20px;
            margin-bottom: 25px;
            padding: 15px;
            border: 1px solid #ddd;
            background: #f8f8f8;
            border-radius: 6px;
        }

        .report-info p {
            margin: 0;
            font-size: 14px;
        }

        .section-heading {
            margin: 0 0 12px;
            font-size: 17px;
            color: #993D86;
        }

        /* Bracket on paper: no box around it */
        .report-bracket {
            margin-bottom: 30px;
            overflow-x: auto;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th {
            padding: 10px;
            background: #993D86;
            color: #ffffff;
            border: 1px solid #ddd;
            font-size: 13px;
            text-align: left;
        }

        .report-table td {
            padding: 9px 10px;
            border: 1px solid #ddd;
            font-size: 13px;
        }

        .report-table tbody tr:nth-child(even) {
            background: #fafafa;
        }

        .center {
            text-align: center;
        }

        .muted {
            color: #999;
            font-style: italic;
        }

        .report-footer {
            margin-top: 20px;
            color: #999;
            font-size: 12px;
            text-align: right;
        }

        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        @media print {

            body {
                padding: 0;
            }

            .report-buttons {
                display: none;
            }

            .report-container {
                max-width: none;
            }

            .report-bracket {
                overflow: visible;
            }

            /* Shrink big draws to the page width (set by the script below) */
            .report-bracket .bracket {
                zoom: var(--print-zoom, 1);
            }

            .player-list {
                break-before: page;
            }

            .report-table tr {
                break-inside: avoid;
            }

            .draw-player.winner,
            .round-title,
            .report-table th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

    </style>

</head>

<body>

<div class="report-container">

    <!-- ==========================================================
         BUTTONS
    =========================================================== -->

    <div class="report-buttons">

        <button type="button" class="print-button" onclick="window.print();">
            Print / Save as PDF
        </button>

        <button type="button" class="print-button outline"
                onclick="downloadBracket(this, <?= htmlspecialchars(json_encode(bracketFileName($tournament, $category)), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($tournament['tournament_name'] . ' - ' . $category), ENT_QUOTES) ?>)">
            Download Bracket (PNG)
        </button>

    </div>


    <!-- ==========================================================
         REPORT HEADER
    =========================================================== -->

    <div class="report-header">

        <h1>Tournament Draw</h1>

        <h2><?= htmlspecialchars($tournament['tournament_name']) ?></h2>

        <p>
            Category:
            <strong><?= htmlspecialchars($category) ?></strong>
        </p>

    </div>


    <!-- ==========================================================
         TOURNAMENT INFORMATION
    =========================================================== -->

    <div class="report-info">

        <p>
            <strong>Dates:</strong>
            <?= reportDate($tournament['tournament_startdate']) ?>
            &ndash;
            <?= reportDate($tournament['tournament_enddate']) ?>
        </p>

        <p>
            <strong>Location:</strong>
            <?= htmlspecialchars(trim(($tournament['tournament_location'] ?? '') . (!empty($tournament['tournament_country']) ? ', ' . $tournament['tournament_country'] : '')) ?: '-') ?>
        </p>

        <p>
            <strong>Age cut-off:</strong>
            <?= reportDate($tournament['tournament_age_cutoff']) ?>
        </p>

        <p>
            <strong>Draw size:</strong>
            <?= $drawSize ?>
        </p>

        <p>
            <strong>Players:</strong>
            <?= count($players) ?>
            (<?= $seededCount ?> seeded, <?= $byes ?> bye<?= $byes === 1 ? '' : 's' ?>)
        </p>

        <p>
            <strong>Draw made:</strong>
            <?= reportDate($drawMade, 'd M Y, h:i A') ?>
        </p>

    </div>


    <!-- ==========================================================
         BRACKET
    =========================================================== -->

    <div class="report-bracket">
        <?php renderBracket($draw); ?>
    </div>


    <!-- ==========================================================
         PLAYER LIST
    =========================================================== -->

    <div class="player-list">

        <h3 class="section-heading">Players and First Round</h3>

        <table class="report-table">

            <thead>
                <tr>
                    <th class="center">Seed</th>
                    <th>Player</th>
                    <th>Nationality</th>
                    <th class="center">Draw Position</th>
                    <th>First Round</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($players as $player): ?>

                    <tr>

                        <td class="center">
                            <?= $player['seed'] !== null ? (int)$player['seed'] : '-' ?>
                        </td>

                        <td><?= htmlspecialchars($player['name']) ?></td>

                        <td><?= htmlspecialchars($player['nationality'] ?: '-') ?></td>

                        <td class="center"><?= $player['position'] ?></td>

                        <td>
                            <?php if ($player['opponent'] === null): ?>

                                <span class="muted">Bye</span>

                            <?php else: ?>

                                Match <?= $player['match'] ?> vs
                                <?php if ($player['opponent_seed'] !== null): ?>
                                    [<?= (int)$player['opponent_seed'] ?>]
                                <?php endif; ?>
                                <?= htmlspecialchars($player['opponent']) ?>

                            <?php endif; ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>


    <p class="report-footer">
        Printed <?= date('d M Y, h:i A') ?>
    </p>

</div>


<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="assets/bracket_download.js?v=<?= filemtime(__DIR__ . '/assets/bracket_download.js') ?>"></script>

<script>
    // Fit the bracket to an A4 landscape page when printing (about 1045px wide)
    (function () {

        const bracket = document.querySelector('.report-bracket .bracket');

        if (!bracket) {
            return;
        }

        const zoom = Math.min(1, 1045 / bracket.scrollWidth);

        bracket.style.setProperty('--print-zoom', zoom.toFixed(3));

    })();
</script>

</body>

</html>
