<?php
require_once '../db.php';
require_once 'admin_auth.php';

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

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Seeding Report -
        <?= htmlspecialchars($category) ?>
    </title>

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

        .report-header {
            text-align: center;
            margin-bottom: 30px;
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
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            background: #f8f8f8;
            border-radius: 6px;
        }

        .report-info p {
            margin: 5px 0;
            font-size: 14px;
        }

        .report-table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th {
            padding: 12px 10px;
            background: #993D86;
            color: #ffffff;
            border: 1px solid #ddd;
            font-size: 13px;
            text-align: left;
        }

        .report-table td {
            padding: 11px 10px;
            border: 1px solid #ddd;
            font-size: 13px;
        }

        .report-table tbody tr:nth-child(even) {
            background: #fafafa;
        }

        .seed {
            font-weight: 600;
            text-align: center;
        }

        .ranking {
            text-align: center;
        }

        .status-endorsed {
            color: #247a43;
            font-weight: 600;
        }

        .status-pending {
            color: #a66a00;
            font-weight: 600;
        }

        .empty {
            text-align: center;
            padding: 30px !important;
            color: #888;
        }

        .print-button {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 18px;
            background: #993D86;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .print-button:hover {
            background: #7f316f;
        }

        @media print {

            body {
                padding: 0;
            }

            .print-button {
                display: none;
            }

            .report-container {
                max-width: none;
            }

        }

    </style>

</head>

<body>

<div class="report-container">

    <!-- ==========================================================
         PRINT BUTTON
    =========================================================== -->

    <button
        type="button"
        class="print-button"
        onclick="window.print();"
    >
        Print / Save as PDF
    </button>


    <!-- ==========================================================
         REPORT HEADER
    =========================================================== -->

    <div class="report-header">

        <h1>
            Tournament Seeding Report
        </h1>

        <h2>
            <?= htmlspecialchars($tournament['tournament_name']) ?>
        </h2>

        <p>
            Category:
            <strong>
                <?= htmlspecialchars($category) ?>
            </strong>
        </p>

    </div>


    <!-- ==========================================================
         TOURNAMENT INFORMATION
    =========================================================== -->

    <div class="report-info">

        <p>
            <strong>Tournament:</strong>
            <?= htmlspecialchars($tournament['tournament_name']) ?>
        </p>

        <p>
            <strong>Category:</strong>
            <?= htmlspecialchars($category) ?>
        </p>

        <?php if (!empty($tournament['location'])): ?>

            <p>
                <strong>Location:</strong>
                <?= htmlspecialchars($tournament['location']) ?>
            </p>

        <?php endif; ?>

        <?php if (!empty($tournament['start_date'])): ?>

            <p>
                <strong>Start Date:</strong>
                <?= htmlspecialchars($tournament['start_date']) ?>
            </p>

        <?php endif; ?>

        <?php if (!empty($tournament['end_date'])): ?>

            <p>
                <strong>End Date:</strong>
                <?= htmlspecialchars($tournament['end_date']) ?>
            </p>

        <?php endif; ?>

    </div>


    <!-- ==========================================================
         PLAYER SEEDING TABLE
    =========================================================== -->

    <div class="report-table-wrapper">

        <table class="report-table">

            <thead>

                <tr>

                    <th>No.</th>

                    <th>Seed</th>

                    <th>Player</th>

                    <th>Nationality</th>

                    <th>World Ranking</th>

                    <th>National Ranking</th>

                    <th>AJSS Ranking</th>

                    <th>Endorsement</th>

                </tr>

            </thead>


            <tbody>

                <?php if ($reportResult->num_rows > 0): ?>

                    <?php $no = 1; ?>


                    <?php while ($player = $reportResult->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?= $no++ ?>
                            </td>


                            <td class="seed">

                                <?php if ($player['seed_number'] !== null): ?>

                                    <?= (int)$player['seed_number'] ?>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $player['player_full_name']
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $player['player_nationality']
                                ) ?>
                            </td>


                            <td class="ranking">

                                <?php if (
                                    $player['world_ranking'] !== null
                                    && trim($player['world_ranking']) !== ''
                                ): ?>

                                    <?= htmlspecialchars(
                                        $player['world_ranking']
                                    ) ?>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>


                            <td class="ranking">

                                <?php if (
                                    $player['national_ranking'] !== null
                                    && trim($player['national_ranking']) !== ''
                                ): ?>

                                    <?= htmlspecialchars(
                                        $player['national_ranking']
                                    ) ?>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>


                            <td class="ranking">

                                <?php if (
                                    $player['ajss_ranking'] !== null
                                    && trim($player['ajss_ranking']) !== ''
                                ): ?>

                                    <?= htmlspecialchars(
                                        $player['ajss_ranking']
                                    ) ?>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php if (
                                    strtoupper(
                                        trim(
                                            $player['endorsement'] ?? ''
                                        )
                                    ) === 'ENDORSED'
                                ): ?>

                                    <span class="status-endorsed">
                                        ENDORSED
                                    </span>

                                <?php else: ?>

                                    <span class="status-pending">
                                        PENDING
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>


                <?php else: ?>

                    <tr>

                        <td
                            colspan="8"
                            class="empty"
                        >
                            No players found for this category.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>

</html>