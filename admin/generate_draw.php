<?php
require '../db.php';

$message = "";
$error = "";

$selectedTournamentID = isset($_POST['tournamentID'])
    ? (int)$_POST['tournamentID']
    : (isset($_GET['tournamentID']) ? (int)$_GET['tournamentID'] : 0);

$selectedEvent = $_POST['category_registered'] ?? $_GET['event'] ?? "";


/* =========================================================
   GET TOURNAMENTS
========================================================= */

$tournaments = [];

$sql = "
    SELECT
        tournamentID,
        tournament_name
    FROM tournament
    ORDER BY tournament_startdate DESC
";

$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $tournaments[] = $row;
}


/* =========================================================
   GET EVENTS FOR SELECTED TOURNAMENT
========================================================= */

$events = [];

if ($selectedTournamentID > 0) {

    $stmt = $conn->prepare("
        SELECT DISTINCT category_registered
        FROM tournament_register
        WHERE tournamentID = ?
        AND category_registered IS NOT NULL
        AND category_registered <> ''
        ORDER BY category_registered ASC
    ");

    $stmt->bind_param("i", $selectedTournamentID);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $events[] = $row['category_registered'];
    }

    $stmt->close();
}


/* =========================================================
   HELPER FUNCTIONS
========================================================= */

function nextPowerOfTwo($number)
{
    $power = 1;

    while ($power < $number) {
        $power *= 2;
    }

    return $power;
}


function getRoundName($playersInRound)
{
    switch ($playersInRound) {

        case 2:
            return "Final";

        case 4:
            return "Semi Final";

        case 8:
            return "Quarter Final";

        case 16:
            return "Round of 16";

        case 32:
            return "Round of 32";

        case 64:
            return "Round of 64";

        case 128:
            return "Round of 128";

        default:
            return "Round";
    }
}


/*
 Standard bracket positions.

 Example for 4:
 1 vs 4
 2 vs 3

 Example for 8:
 1 vs 8
 4 vs 5
 3 vs 6
 2 vs 7
*/
function generateSeedPositions($size)
{
    if ($size == 2) {
        return [1, 2];
    }

    $positions = [1, 2];

    while (count($positions) < $size) {

        $nextSize = count($positions) * 2;

        $newPositions = [];

        foreach ($positions as $position) {

            $newPositions[] = $position;

            $newPositions[] =
                $nextSize + 1 - $position;
        }

        $positions = $newPositions;
    }

    return $positions;
}


/* =========================================================
   GENERATE DRAW
========================================================= */

if (
    isset($_POST['generate_draw']) &&
    $selectedTournamentID > 0 &&
    $selectedEvent !== ""
) {

    try {

        $conn->begin_transaction();


        /* -------------------------------------------------
           CHECK EXISTING DRAW
        ------------------------------------------------- */

        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM matches
            WHERE tournamentID = ?
            AND category_registered = ?
        ");

        $stmt->bind_param(
            "is",
            $selectedTournamentID,
            $selectedEvent
        );

        $stmt->execute();

        $existing =
            $stmt->get_result()->fetch_assoc()['total'];

        $stmt->close();


        if ($existing > 0) {
            throw new Exception(
                "A draw has already been generated for this event."
            );
        }


        /* -------------------------------------------------
           GET PLAYERS
        ------------------------------------------------- */

        $stmt = $conn->prepare("
            SELECT
                tr.registrationID,
                tr.playerID,
                tr.seed_number,
                p.player_full_name

            FROM tournament_register tr

            INNER JOIN players p
                ON CONVERT(p.playerID USING utf8mb4)
                 = CONVERT(tr.playerID USING utf8mb4)

            WHERE tr.tournamentID = ?
            AND tr.category_registered = ?

            ORDER BY
                CASE
                    WHEN tr.seed_number IS NULL THEN 1
                    ELSE 0
                END,
                tr.seed_number ASC,
                tr.registrationID ASC
        ");

        $stmt->bind_param(
            "is",
            $selectedTournamentID,
            $selectedEvent
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $players = [];

        while ($row = $result->fetch_assoc()) {
            $players[] = $row;
        }

        $stmt->close();


        $playerCount = count($players);

        if ($playerCount < 2) {
            throw new Exception(
                "At least 2 players are required to generate a draw."
            );
        }


        /* -------------------------------------------------
           CREATE BRACKET SIZE
        ------------------------------------------------- */

        $bracketSize = nextPowerOfTwo($playerCount);

        /*
         3 players  -> bracket 4
         5 players  -> bracket 8
         9 players  -> bracket 16
        */


        /* -------------------------------------------------
           MAP PLAYERS BY SEED
        ------------------------------------------------- */

        $seedPlayers = [];
        $unseededPlayers = [];

        foreach ($players as $player) {

            if (
                $player['seed_number'] !== null &&
                $player['seed_number'] !== ''
            ) {

                $seed =
                    (int)$player['seed_number'];

                $seedPlayers[$seed] = $player;

            } else {

                $unseededPlayers[] = $player;
            }
        }


        /* -------------------------------------------------
           STANDARD DRAW POSITIONS
        ------------------------------------------------- */

        $seedPositions =
            generateSeedPositions($bracketSize);

        $bracket = array_fill(
            0,
            $bracketSize,
            null
        );


        /*
         Put seeded players first.
        */

        foreach ($seedPositions as $index => $seed) {

            if (isset($seedPlayers[$seed])) {
                $bracket[$index] =
                    $seedPlayers[$seed];
            }
        }


        /*
         Fill remaining spaces using
         unseeded players.
        */

        foreach ($bracket as $index => $value) {

            if (
                $value === null &&
                count($unseededPlayers) > 0
            ) {

                $bracket[$index] =
                    array_shift($unseededPlayers);
            }
        }


        /* -------------------------------------------------
           ROUND COUNT
        ------------------------------------------------- */

        $totalRounds =
            (int)log($bracketSize, 2);

        $roundMatches = [];


        /* =================================================
           CREATE ALL MATCHES
        ================================================= */

        for (
            $round = 1;
            $round <= $totalRounds;
            $round++
        ) {

            $playersInRound =
                $bracketSize /
                pow(2, $round - 1);

            $matchCount =
                $playersInRound / 2;

            $roundName =
                getRoundName($playersInRound);

            $roundMatches[$round] = [];


            for (
                $matchNo = 1;
                $matchNo <= $matchCount;
                $matchNo++
            ) {

                $player1ID = null;
                $player2ID = null;

                $player1Seed = null;
                $player2Seed = null;


                /*
                 Only Round 1 receives actual players.
                */

                if ($round == 1) {

                    $index1 =
                        ($matchNo - 1) * 2;

                    $index2 =
                        $index1 + 1;


                    $player1 =
                        $bracket[$index1];

                    $player2 =
                        $bracket[$index2];


                    if ($player1) {

                        $player1ID =
                            $player1['playerID'];

                        $player1Seed =
                            $player1['seed_number'];
                    }


                    if ($player2) {

                        $player2ID =
                            $player2['playerID'];

                        $player2Seed =
                            $player2['seed_number'];
                    }
                }


                $stmtInsert =
                    $conn->prepare("
                        INSERT INTO matches (
                            tournamentID,
                            category_registered,
                            round_number,
                            round_name,
                            match_number,
                            player1ID,
                            player2ID,
                            player1_seed,
                            player2_seed
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");


                $stmtInsert->bind_param(
                    "isisissii",
                    $selectedTournamentID,
                    $selectedEvent,
                    $round,
                    $roundName,
                    $matchNo,
                    $player1ID,
                    $player2ID,
                    $player1Seed,
                    $player2Seed
                );


                $stmtInsert->execute();

                $matchID =
                    $conn->insert_id;

                $roundMatches[$round][$matchNo] =
                    $matchID;

                $stmtInsert->close();
            }
        }


        /* =================================================
           CONNECT MATCHES
        ================================================= */

        for (
            $round = 1;
            $round < $totalRounds;
            $round++
        ) {

            foreach (
                $roundMatches[$round]
                as $matchNo => $matchID
            ) {

                /*
                 Matches 1 & 2 → next Match 1

                 Matches 3 & 4 → next Match 2
                */

                $nextMatchNumber =
                    (int)ceil($matchNo / 2);

                $nextMatchID =
                    $roundMatches[
                        $round + 1
                    ][
                        $nextMatchNumber
                    ];


                /*
                 Odd match -> player1 slot
                 Even match -> player2 slot
                */

                if ($matchNo % 2 == 1) {
                    $nextPosition =
                        "player1";
                } else {
                    $nextPosition =
                        "player2";
                }


                $stmtUpdate =
                    $conn->prepare("
                        UPDATE matches
                        SET
                            next_matchID = ?,
                            next_match_position = ?
                        WHERE matchID = ?
                    ");


                $stmtUpdate->bind_param(
                    "isi",
                    $nextMatchID,
                    $nextPosition,
                    $matchID
                );

                $stmtUpdate->execute();
                $stmtUpdate->close();
            }
        }


        /* =================================================
           HANDLE BYES
        ================================================= */

        foreach (
            $roundMatches[1]
            as $matchNo => $matchID
        ) {

            $stmt =
                $conn->prepare("
                    SELECT *
                    FROM matches
                    WHERE matchID = ?
                ");

            $stmt->bind_param(
                "i",
                $matchID
            );

            $stmt->execute();

            $match =
                $stmt
                ->get_result()
                ->fetch_assoc();

            $stmt->close();


            /*
             Player 1 exists but Player 2 BYE.
            */

            if (
                $match['player1ID'] &&
                !$match['player2ID']
            ) {

                $winner =
                    $match['player1ID'];

                $stmt =
                    $conn->prepare("
                        UPDATE matches
                        SET
                            winnerID = ?,
                            match_status = 'Completed'
                        WHERE matchID = ?
                    ");

                $stmt->bind_param(
                    "si",
                    $winner,
                    $matchID
                );

                $stmt->execute();
                $stmt->close();


                advancePlayer(
                    $conn,
                    $matchID,
                    $winner
                );
            }


            /*
             Player 2 exists but Player 1 BYE.
            */

            elseif (
                !$match['player1ID'] &&
                $match['player2ID']
            ) {

                $winner =
                    $match['player2ID'];

                $stmt =
                    $conn->prepare("
                        UPDATE matches
                        SET
                            winnerID = ?,
                            match_status = 'Completed'
                        WHERE matchID = ?
                    ");

                $stmt->bind_param(
                    "si",
                    $winner,
                    $matchID
                );

                $stmt->execute();
                $stmt->close();


                advancePlayer(
                    $conn,
                    $matchID,
                    $winner
                );
            }
        }


        $conn->commit();

        header(
            "Location: draw.php?tournamentID="
            . $selectedTournamentID
            . "&event="
            . urlencode($selectedEvent)
        );

        exit;


    } catch (Exception $e) {

        $conn->rollback();

        $error = $e->getMessage();
    }
}


/* =========================================================
   ADVANCE PLAYER
========================================================= */

function advancePlayer(
    $conn,
    $matchID,
    $winnerID
) {

    $stmt =
        $conn->prepare("
            SELECT
                next_matchID,
                next_match_position
            FROM matches
            WHERE matchID = ?
        ");

    $stmt->bind_param(
        "i",
        $matchID
    );

    $stmt->execute();

    $match =
        $stmt
        ->get_result()
        ->fetch_assoc();

    $stmt->close();


    if (!$match['next_matchID']) {
        return;
    }


    if (
        $match['next_match_position']
        === 'player1'
    ) {

        $stmt =
            $conn->prepare("
                UPDATE matches
                SET player1ID = ?
                WHERE matchID = ?
            ");

    } else {

        $stmt =
            $conn->prepare("
                UPDATE matches
                SET player2ID = ?
                WHERE matchID = ?
            ");
    }


    $stmt->bind_param(
        "si",
        $winnerID,
        $match['next_matchID']
    );

    $stmt->execute();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Generate Tournament Draw</title>

<style>

body {
    font-family: Arial, sans-serif;
    background: #f5f5f5;
    margin: 0;
    padding: 40px;
}

.container {
    max-width: 1000px;
    margin: auto;
    background: white;
    padding: 30px;
    border-radius: 16px;
    box-shadow: 0 4px 15px rgba(0,0,0,.08);
}

h1 {
    color: #993D86;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 8px;
}

select {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
}

button {
    background: #993D86;
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
}

button:hover {
    opacity: .9;
}

.error {
    background: #ffe6e6;
    color: #b30000;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
}

</style>

</head>

<body>

<div class="container">

<h1>Generate Tournament Draw</h1>

<?php if ($error): ?>

<div class="error">
    <?= htmlspecialchars($error) ?>
</div>

<?php endif; ?>


<form method="GET">

<div class="form-group">

<label>Tournament</label>

<select
    name="tournamentID"
    onchange="this.form.submit()"
>

<option value="">
    Select Tournament
</option>

<?php foreach ($tournaments as $tournament): ?>

<option
    value="<?= $tournament['tournamentID'] ?>"
    <?= $selectedTournamentID ==
        $tournament['tournamentID']
        ? 'selected'
        : '' ?>
>

<?= htmlspecialchars(
    $tournament['tournament_name']
) ?>

</option>

<?php endforeach; ?>

</select>

</div>

</form>


<?php if ($selectedTournamentID): ?>

<form method="POST">

<input
    type="hidden"
    name="tournamentID"
    value="<?= $selectedTournamentID ?>"
>

<div class="form-group">

<label>Event</label>

<select
    name="category_registered"
    required
>

<option value="">
    Select Event
</option>

<?php foreach ($events as $event): ?>

<option
    value="<?= htmlspecialchars($event) ?>"
>

<?= htmlspecialchars($event) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<button
    type="submit"
    name="generate_draw"
>

Generate Draw

</button>

</form>

<?php endif; ?>

</div>

</body>
</html>