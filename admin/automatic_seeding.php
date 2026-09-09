<?php
require_once '../db.php';

$selectedTournamentID = isset($_POST['tournamentID'])
    ? (int)$_POST['tournamentID']
    : (isset($_GET['tournamentID']) ? (int)$_GET['tournamentID'] : 0);

$selectedEvent = isset($_POST['event'])
    ? trim($_POST['event'])
    : (isset($_GET['event']) ? trim($_GET['event']) : '');

$success_message = '';
$error_message = '';

$players = [];
$tournaments = [];
$events = [];


// ============================================================
// RANKING CHECK
// ============================================================

function hasRanking($value)
{
    if ($value === null) {
        return false;
    }

    $value = trim((string)$value);

    if ($value === '') {
        return false;
    }

    if (!is_numeric($value)) {
        return false;
    }

    return ((int)$value > 0);
}


// ============================================================
// RANKING PRIORITY
// ============================================================

function getRankingInformation($player)
{
    // 1. WORLD RANKING
    if (hasRanking($player['world_ranking'])) {
        return [
            'type' => 'World Ranking',
            'value' => (int)$player['world_ranking'],
            'priority' => 1
        ];
    }

    // 2. AJSS RANKING
    if (hasRanking($player['ajss_ranking'])) {
        return [
            'type' => 'AJSS Ranking',
            'value' => (int)$player['ajss_ranking'],
            'priority' => 2
        ];
    }

    // 3. NATIONAL RANKING
    if (hasRanking($player['national_ranking'])) {
        return [
            'type' => 'National Ranking',
            'value' => (int)$player['national_ranking'],
            'priority' => 3
        ];
    }

    // 4. SPIN NUMBER
    if (hasRanking($player['spin_number'])) {
        return [
            'type' => 'SPIN Number',
            'value' => (int)$player['spin_number'],
            'priority' => 4
        ];
    }

    // 5. UNRANKED
    return [
        'type' => 'Unranked',
        'value' => PHP_INT_MAX,
        'priority' => 999
    ];
}


// ============================================================
// NUMBER OF SEEDS
// ============================================================

function calculateNumberOfSeeds($playerCount)
{
    return $playerCount;
}


// ============================================================
// LOAD TOURNAMENTS
// ============================================================

$tournamentSQL = "
    SELECT
        tournamentID,
        tournament_name
    FROM tournament
    ORDER BY tournamentID DESC
";

$tournamentResult = $conn->query($tournamentSQL);

if ($tournamentResult) {

    while ($row = $tournamentResult->fetch_assoc()) {
        $tournaments[] = $row;
    }

} else {

    $error_message = "Unable to load tournaments: " . $conn->error;
}


// ============================================================
// LOAD EVENTS FOR SELECTED TOURNAMENT
// ============================================================

if ($selectedTournamentID > 0) {

    /*
     * Events are taken from tournament_register.
     *
     * DISTINCT prevents duplicate events.
     */

    $eventSQL = "
        SELECT DISTINCT category_registered
        FROM tournament_register
        WHERE tournamentID = ?
        AND category_registered IS NOT NULL
        AND category_registered <> ''
        ORDER BY category_registered ASC
    ";

    $eventStmt = $conn->prepare($eventSQL);

    if ($eventStmt) {

        $eventStmt->bind_param(
            "i",
            $selectedTournamentID
        );

        $eventStmt->execute();

        $eventResult = $eventStmt->get_result();

        while ($row = $eventResult->fetch_assoc()) {

            $events[] = $row['category_registered'];
        }

        $eventStmt->close();

    } else {

        $error_message =
            "Unable to load events: " . $conn->error;
    }
}


// ============================================================
// LOAD PLAYERS
// ============================================================

if (
    $selectedTournamentID > 0 &&
    $selectedEvent !== ''
) {

    $playerSQL = "
        SELECT
            tr.registrationID,
            tr.playerID,
            tr.tournamentID,
            tr.category_registered,
            tr.seed_number,

            p.player_full_name,
            p.player_first_name,
            p.player_last_name,
            p.spin_number,
            p.national_ranking,
            p.ajss_ranking,
            p.world_ranking

        FROM tournament_register tr

        INNER JOIN players p
            ON CONVERT(p.playerID USING utf8mb4)
             = CONVERT(tr.playerID USING utf8mb4)

        WHERE tr.tournamentID = ?
        AND tr.category_registered = ?

        ORDER BY tr.registrationID ASC
    ";

    $playerStmt = $conn->prepare($playerSQL);

    if (!$playerStmt) {

        $error_message =
            "Unable to load players: "
            . $conn->error;

    } else {

        $playerStmt->bind_param(
            "is",
            $selectedTournamentID,
            $selectedEvent
        );

        $playerStmt->execute();

        $playerResult =
            $playerStmt->get_result();

        while ($row = $playerResult->fetch_assoc()) {

            $ranking =
                getRankingInformation($row);

            $row['ranking_type'] =
                $ranking['type'];

            $row['ranking_value'] =
                $ranking['value'];

            $row['ranking_priority'] =
                $ranking['priority'];

            $players[] = $row;
        }

        $playerStmt->close();
    }
}


// ============================================================
// GENERATE AUTOMATIC SEEDING
// ============================================================

if (
    isset($_POST['generate_seeding']) &&
    $selectedTournamentID > 0 &&
    $selectedEvent !== ''
) {

    $totalPlayers = count($players);

    $totalSeeds =
        calculateNumberOfSeeds($totalPlayers);

    if ($totalPlayers == 0) {

        $error_message =
            "There are no registered players for this event.";

    } else {

        /*
         * SORT BY:
         *
         * 1. Ranking priority
         * 2. Ranking number
         * 3. Registration ID
         */

        usort(
            $players,
            function ($a, $b) {

                if (
                    $a['ranking_priority']
                    !=
                    $b['ranking_priority']
                ) {

                    return
                        $a['ranking_priority']
                        <=>
                        $b['ranking_priority'];
                }

                if (
                    $a['ranking_value']
                    !=
                    $b['ranking_value']
                ) {

                    return
                        $a['ranking_value']
                        <=>
                        $b['ranking_value'];
                }

                return
                    $a['registrationID']
                    <=>
                    $b['registrationID'];
            }
        );


        // ====================================================
        // START TRANSACTION
        // ====================================================

        $conn->begin_transaction();

        try {

            // =================================================
            // RESET EXISTING SEEDS
            // =================================================

            $resetSQL = "
                UPDATE tournament_register
                SET seed_number = NULL
                WHERE tournamentID = ?
                AND category_registered = ?
            ";

            $resetStmt =
                $conn->prepare($resetSQL);

            if (!$resetStmt) {

                throw new Exception(
                    "Unable to reset existing seeds: "
                    . $conn->error
                );
            }

            $resetStmt->bind_param(
                "is",
                $selectedTournamentID,
                $selectedEvent
            );

            if (!$resetStmt->execute()) {

                throw new Exception(
                    "Unable to reset seeds: "
                    . $resetStmt->error
                );
            }

            $resetStmt->close();


            // =================================================
            // UPDATE SEEDS
            // =================================================

            $updateSQL = "
                UPDATE tournament_register
                SET seed_number = ?
                WHERE registrationID = ?
                AND tournamentID = ?
                AND category_registered = ?
            ";

            $updateStmt =
                $conn->prepare($updateSQL);

            if (!$updateStmt) {

                throw new Exception(
                    "Unable to prepare seed update: "
                    . $conn->error
                );
            }


            $seed = 1;

            $seededPlayers = 0;


            foreach ($players as &$player) {

                /*
                 * Only ranked players receive seeds.
                 */

                if (
                    $player['ranking_priority'] != 999
                    &&
                    $seed <= $totalSeeds
                ) {

                    $registrationID =
                        (int)$player['registrationID'];

                    $updateStmt->bind_param(
                        "iiis",
                        $seed,
                        $registrationID,
                        $selectedTournamentID,
                        $selectedEvent
                    );

                    if (!$updateStmt->execute()) {

                        throw new Exception(
                            "Unable to seed "
                            . $player['player_full_name']
                            . ": "
                            . $updateStmt->error
                        );
                    }


                    $player['seed_number'] =
                        $seed;

                    $player['seed_status'] =
                        'Seeded';

                    $player['seed_reason'] =
                        'Selected based on ranking priority.';

                    $seed++;

                    $seededPlayers++;

                } else {

                    $player['seed_number'] =
                        null;

                    $player['seed_status'] =
                        'Unseeded';


                    if (
                        $player['ranking_priority']
                        ==
                        999
                    ) {

                        $player['seed_reason'] =
                            'No valid ranking information available.';

                    } elseif (
                        $seed > $totalSeeds
                    ) {

                        $player['seed_reason'] =
                            'All seed positions have been filled by higher-priority players.';

                    } else {

                        $player['seed_reason'] =
                            'Not eligible for automatic seeding.';
                    }
                }
            }

            unset($player);


            $updateStmt->close();


            // =================================================
            // COMMIT
            // =================================================

            $conn->commit();


            $success_message =
                "Automatic seeding completed. "
                . $seededPlayers
                . " of "
                . $totalPlayers
                . " players received seeds.";

        } catch (Exception $e) {

            $conn->rollback();

            $error_message =
                "Seeding failed: "
                . $e->getMessage();
        }
    }
}


// ============================================================
// AFTER GENERATING, SORT PLAYERS BY SEED
// ============================================================

if (count($players) > 0) {

    usort(
        $players,
        function ($a, $b) {

            $seedA =
                (
                    isset($a['seed_number'])
                    &&
                    $a['seed_number'] !== null
                )
                ? (int)$a['seed_number']
                : 999999;

            $seedB =
                (
                    isset($b['seed_number'])
                    &&
                    $b['seed_number'] !== null
                )
                ? (int)$b['seed_number']
                : 999999;


            if ($seedA != $seedB) {

                return $seedA <=> $seedB;
            }


            return
                $a['registrationID']
                <=>
                $b['registrationID'];
        }
    );


    /*
     * Determine display reason.
     */

    $totalPlayers =
        count($players);

    $totalSeeds =
        calculateNumberOfSeeds($totalPlayers);


    foreach ($players as &$player) {

        if (
            isset($player['seed_number'])
            &&
            $player['seed_number'] !== null
        ) {

            $player['seed_status'] =
                'Seeded';

            $player['seed_reason'] =
                'Currently assigned seed #'
                . $player['seed_number'];

        } else {

            $player['seed_status'] =
                'Unseeded';


            if (
                $player['ranking_priority']
                ==
                999
            ) {

                $player['seed_reason'] =
                    'No valid ranking information available.';

            } else {

                $player['seed_reason'] =
                    'Outside the available '
                    . $totalSeeds
                    . ' seed positions.';
            }
        }
    }

    unset($player);

} else {

    $totalPlayers = 0;
    $totalSeeds = 0;
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Automatic Tournament Seeding</title>


<style>

* {
    box-sizing: border-box;
}


body {
    margin: 0;
    padding: 30px;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f5f5f5;

    color: #333;
}


.container {
    max-width: 1450px;

    margin: auto;
}


h1 {
    margin: 0 0 8px;

    color: #333;
}


.subtitle {
    color: #777;

    margin-bottom: 25px;
}


/* ============================================================
   ALERT
============================================================ */

.success {
    background: #e8f7ed;

    color: #26733f;

    border-left:
        5px solid #26733f;

    padding: 15px 18px;

    border-radius: 6px;

    margin-bottom: 20px;
}


.error {
    background: #fdecec;

    color: #a52828;

    border-left:
        5px solid #a52828;

    padding: 15px 18px;

    border-radius: 6px;

    margin-bottom: 20px;
}


/* ============================================================
   SELECT PANEL
============================================================ */

.selection-panel {
    background: white;

    border-radius: 12px;

    padding: 25px;

    margin-bottom: 25px;

    box-shadow:
        0 2px 8px rgba(0,0,0,0.08);
}


.selection-title {
    font-size: 18px;

    font-weight: bold;

    margin-bottom: 20px;
}


.selection-form {
    display: grid;

    grid-template-columns:
        1fr 1fr auto;

    gap: 18px;

    align-items: end;
}


.form-group {
    display: flex;

    flex-direction: column;
}


.form-group label {
    font-size: 13px;

    font-weight: bold;

    margin-bottom: 8px;

    color: #555;
}


.form-group select {
    width: 100%;

    padding: 12px;

    border: 1px solid #ddd;

    border-radius: 7px;

    background: white;

    font-size: 14px;

    outline: none;
}


.form-group select:focus {
    border-color: #993D86;

    box-shadow:
        0 0 0 2px rgba(153,61,134,0.1);
}


.load-btn {
    border: none;

    background: #993D86;

    color: white;

    padding: 12px 25px;

    border-radius: 7px;

    font-size: 14px;

    font-weight: bold;

    cursor: pointer;

    white-space: nowrap;
}


.load-btn:hover {
    background: #7c2f6d;
}


/* ============================================================
   INFORMATION CARDS
============================================================ */

.info-grid {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 25px;
}


.info-card {
    background: white;

    border-radius: 12px;

    padding: 20px;

    box-shadow:
        0 2px 8px rgba(0,0,0,0.08);
}


.info-label {
    color: #777;

    font-size: 13px;

    margin-bottom: 8px;
}


.info-value {
    color: #993D86;

    font-size: 25px;

    font-weight: bold;
}


/* ============================================================
   SEEDING PANEL
============================================================ */

.control-panel {
    background: white;

    padding: 25px;

    border-radius: 12px;

    margin-bottom: 25px;

    box-shadow:
        0 2px 8px rgba(0,0,0,0.08);
}


.control-row {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    flex-wrap: wrap;
}


.event-info {
    line-height: 1.7;
}


.event-info strong {
    color: #993D86;
}


.generate-btn {
    border: none;

    background: #993D86;

    color: white;

    padding: 13px 25px;

    border-radius: 7px;

    font-size: 15px;

    font-weight: bold;

    cursor: pointer;
}


.generate-btn:hover {
    background: #7c2f6d;
}


/* ============================================================
   RANKING PRIORITY
============================================================ */

.ranking-order {
    background: #fafafa;

    border: 1px solid #eee;

    border-radius: 8px;

    padding: 18px;

    margin-top: 20px;
}


.ranking-order h3 {
    margin-top: 0;

    font-size: 16px;
}


.ranking-list {
    display: flex;

    flex-wrap: wrap;

    gap: 10px;
}


.ranking-step {
    background: white;

    border: 1px solid #ddd;

    padding: 8px 12px;

    border-radius: 20px;

    font-size: 13px;
}


.ranking-step strong {
    color: #993D86;
}


/* ============================================================
   TABLE
============================================================ */

.table-container {
    background: white;

    border-radius: 12px;

    overflow-x: auto;

    box-shadow:
        0 2px 8px rgba(0,0,0,0.08);
}


table {
    width: 100%;

    border-collapse: collapse;

    min-width: 1150px;
}


thead {
    background: #993D86;

    color: white;
}


th {
    padding: 14px;

    text-align: left;

    font-size: 13px;
}


td {
    padding: 14px;

    border-bottom: 1px solid #eee;

    font-size: 14px;

    vertical-align: middle;
}


tbody tr:hover {
    background: #faf7fa;
}


/* ============================================================
   PLAYER
============================================================ */

.player-name {
    font-weight: bold;

    color: #333;
}


.player-id {
    font-size: 12px;

    color: #888;

    margin-top: 3px;
}


/* ============================================================
   SEED
============================================================ */

.seed-badge {
    display: inline-flex;

    align-items: center;

    justify-content: center;

    width: 38px;

    height: 38px;

    border-radius: 50%;

    background: #993D86;

    color: white;

    font-weight: bold;
}


.unseeded-badge {
    display: inline-block;

    padding: 6px 10px;

    border-radius: 20px;

    background: #eee;

    color: #777;

    font-size: 12px;

    font-weight: bold;
}


/* ============================================================
   RANKING BADGES
============================================================ */

.ranking-badge {
    display: inline-block;

    padding: 6px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;
}


.world {
    background: #eee5ff;

    color: #6633aa;
}


.national {
    background: #e4f0ff;

    color: #2464a5;
}


.ajss {
    background: #e8f7e8;

    color: #32753b;
}


.spin {
    background: #fff2d9;

    color: #9a6715;
}


.unranked {
    background: #eeeeee;

    color: #777;
}


/* ============================================================
   STATUS
============================================================ */

.status-seeded {
    color: #26733f;

    font-weight: bold;
}


.status-unseeded {
    color: #888;

    font-weight: bold;
}


.reason {
    color: #777;

    font-size: 12px;

    max-width: 320px;

    line-height: 1.4;
}


/* ============================================================
   EMPTY
============================================================ */

.empty {
    padding: 50px;

    text-align: center;

    color: #777;
}


/* ============================================================
   RESPONSIVE
============================================================ */

@media (max-width: 900px) {

    body {
        padding: 15px;
    }


    .selection-form {
        grid-template-columns: 1fr;
    }


    .load-btn {
        width: 100%;
    }


    .info-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

}


@media (max-width: 600px) {

    .info-grid {
        grid-template-columns: 1fr;
    }


    .control-row {
        flex-direction: column;

        align-items: stretch;
    }


    .generate-btn {
        width: 100%;
    }

}

</style>

</head>


<body>


<div class="container">


<h1>Automatic Tournament Seeding</h1>

<div class="subtitle">
    Select a tournament and event to manage automatic seeding.
</div>


<?php if ($success_message !== ''): ?>

<div class="success">
    <?= htmlspecialchars($success_message) ?>
</div>

<?php endif; ?>


<?php if ($error_message !== ''): ?>

<div class="error">
    <?= htmlspecialchars($error_message) ?>
</div>

<?php endif; ?>


<!-- =========================================================
     TOURNAMENT / EVENT SELECTION
========================================================== -->

<div class="selection-panel">


    <div class="selection-title">
        Select Tournament & Event
    </div>


    <form
        method="POST"
        class="selection-form"
    >


        <!-- TOURNAMENT -->

        <div class="form-group">

            <label for="tournamentID">
                Tournament
            </label>


            <select
                name="tournamentID"
                id="tournamentID"
                required
                onchange="loadTournamentEvents(this.value)"
            >

                <option value="">
                    -- Select Tournament --
                </option>


                <?php foreach ($tournaments as $tournament): ?>

                    <option
                        value="<?= (int)$tournament['tournamentID'] ?>"
                        <?= (
                            $selectedTournamentID
                            ==
                            $tournament['tournamentID']
                        )
                        ? 'selected'
                        : ''
                        ?>
                    >

                        <?= htmlspecialchars(
                            $tournament['tournament_name']
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- EVENT -->

        <div class="form-group">

            <label for="event">
                Event / Category
            </label>


            <select
                name="event"
                id="event"
                required
            >

                <option value="">
                    -- Select Event --
                </option>


                <?php foreach ($events as $eventName): ?>

                    <option
                        value="<?= htmlspecialchars($eventName) ?>"
                        <?= (
                            $selectedEvent
                            ===
                            $eventName
                        )
                        ? 'selected'
                        : ''
                        ?>
                    >

                        <?= htmlspecialchars($eventName) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- LOAD -->

        <button
            type="submit"
            name="load_players"
            class="load-btn"
        >
            Load Players
        </button>


    </form>


</div>


<?php if ($selectedTournamentID > 0 && $selectedEvent !== ''): ?>


<!-- =========================================================
     INFORMATION
========================================================== -->

<div class="info-grid">


    <div class="info-card">

        <div class="info-label">
            Tournament ID
        </div>

        <div class="info-value">
            <?= $selectedTournamentID ?>
        </div>

    </div>


    <div class="info-card">

        <div class="info-label">
            Event
        </div>

        <div
            class="info-value"
            style="font-size:18px;"
        >

            <?= htmlspecialchars(
                $selectedEvent
            ) ?>

        </div>

    </div>


    <div class="info-card">

        <div class="info-label">
            Registered Players
        </div>

        <div class="info-value">
            <?= $totalPlayers ?>
        </div>

    </div>


    <div class="info-card">

        <div class="info-label">
            Available Seeds
        </div>

        <div class="info-value">
            <?= $totalSeeds ?>
        </div>

    </div>


</div>


<!-- =========================================================
     SEEDING CONTROL
========================================================== -->

<div class="control-panel">


    <div class="control-row">


        <div class="event-info">

            <div>
                <strong>Tournament:</strong>

                <?= htmlspecialchars(
                    $selectedTournamentID
                ) ?>
            </div>


            <div>
                <strong>Event:</strong>

                <?= htmlspecialchars(
                    $selectedEvent
                ) ?>
            </div>


            <div>
                <strong>Available Seeds:</strong>

                <?= $totalSeeds ?>
            </div>

        </div>


        <form
            method="POST"
            onsubmit="
                return confirm(
                    'Generate automatic seeding for this event? Existing seeds will be replaced.'
                );
            "
        >

            <input
                type="hidden"
                name="tournamentID"
                value="<?= $selectedTournamentID ?>"
            >


            <input
                type="hidden"
                name="event"
                value="<?= htmlspecialchars($selectedEvent) ?>"
            >


            <button
                type="submit"
                name="generate_seeding"
                class="generate-btn"
            >
                Generate Automatic Seeding
            </button>

        </form>


    </div>


    <!-- RANKING PRIORITY -->

    <div class="ranking-order">

        <h3>
            Ranking Priority
        </h3>


        <div class="ranking-list">


            <div class="ranking-step">
                <strong>1.</strong>
                World Ranking
            </div>


            <div class="ranking-step">
                <strong>2.</strong>
                National Ranking
            </div>


            <div class="ranking-step">
                <strong>3.</strong>
                AJSS Ranking
            </div>


            <div class="ranking-step">
                <strong>4.</strong>
                SPIN Number
            </div>


            <div class="ranking-step">
                <strong>5.</strong>
                Unranked
            </div>


        </div>

    </div>


</div>


<!-- =========================================================
     PLAYER TABLE
========================================================== -->

<div class="table-container">


<?php if ($totalPlayers > 0): ?>


<table>


<thead>

<tr>

    <th>Seed</th>

    <th>Player</th>

    <th>SPIN</th>

    <th>National</th>

    <th>AJSS</th>

    <th>World</th>

    <th>Ranking Used</th>

    <th>Status</th>

    <th>Reason</th>

</tr>

</thead>


<tbody>


<?php foreach ($players as $player): ?>


<?php

$rankingClass = 'unranked';


switch ($player['ranking_type']) {

    case 'World Ranking':
        $rankingClass = 'world';
        break;

    case 'National Ranking':
        $rankingClass = 'national';
        break;

    case 'AJSS Ranking':
        $rankingClass = 'ajss';
        break;

    case 'SPIN Number':
        $rankingClass = 'spin';
        break;

    default:
        $rankingClass = 'unranked';
}


$spin =
    hasRanking($player['spin_number'])
    ? htmlspecialchars($player['spin_number'])
    : '-';


$national =
    hasRanking($player['national_ranking'])
    ? htmlspecialchars($player['national_ranking'])
    : '-';


$ajss =
    hasRanking($player['ajss_ranking'])
    ? htmlspecialchars($player['ajss_ranking'])
    : '-';


$world =
    hasRanking($player['world_ranking'])
    ? htmlspecialchars($player['world_ranking'])
    : '-';


$seedExists =
    isset($player['seed_number'])
    &&
    $player['seed_number'] !== null
    &&
    $player['seed_number'] !== '';

?>


<tr>


<!-- SEED -->

<td>

<?php if ($seedExists): ?>

<span class="seed-badge">

<?= (int)$player['seed_number'] ?>

</span>

<?php else: ?>

<span class="unseeded-badge">

Unseeded

</span>

<?php endif; ?>

</td>


<!-- PLAYER -->

<td>

<div class="player-name">

<?= htmlspecialchars(
    $player['player_full_name']
) ?>

</div>


<div class="player-id">

ID:
<?= htmlspecialchars(
    $player['playerID']
) ?>

</div>

</td>


<!-- SPIN -->

<td>
<?= $spin ?>
</td>


<!-- NATIONAL -->

<td>
<?= $national ?>
</td>


<!-- AJSS -->

<td>
<?= $ajss ?>
</td>


<!-- WORLD -->

<td>
<?= $world ?>
</td>


<!-- RANKING USED -->

<td>

<span
    class="ranking-badge <?= $rankingClass ?>"
>

<?= htmlspecialchars(
    $player['ranking_type']
) ?>


<?php if (
    $player['ranking_type']
    !==
    'Unranked'
): ?>

    #<?= (int)$player['ranking_value'] ?>

<?php endif; ?>

</span>

</td>


<!-- STATUS -->

<td>

<?php if ($seedExists): ?>

<span class="status-seeded">
    Seeded
</span>

<?php else: ?>

<span class="status-unseeded">
    Unseeded
</span>

<?php endif; ?>

</td>


<!-- REASON -->

<td>

<div class="reason">

<?= htmlspecialchars(
    $player['seed_reason']
) ?>

</div>

</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


<?php else: ?>


<div class="empty">

No registered players found for this event.

</div>


<?php endif; ?>


</div>


<?php endif; ?>


</div>


<script>

/*
|--------------------------------------------------------------------------
| When tournament changes
|--------------------------------------------------------------------------
| Automatically reload the page with the selected tournament.
| This allows PHP to load the events belonging to that tournament.
|--------------------------------------------------------------------------
*/

function loadTournamentEvents(tournamentID)
{
    if (!tournamentID) {

        return;
    }


    const currentUrl =
        new URL(window.location.href);


    currentUrl.searchParams.set(
        'tournamentID',
        tournamentID
    );


    currentUrl.searchParams.delete(
        'event'
    );


    window.location.href =
        currentUrl.toString();
}

</script>


</body>

</html>