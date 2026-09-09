<?php

require '../db.php';


/* =========================================================
   GET TOURNAMENT AND EVENT
========================================================= */

$tournamentID = isset($_GET['tournamentID'])
    ? (int)$_GET['tournamentID']
    : 0;

$event = isset($_GET['event'])
    ? trim($_GET['event'])
    : "";


if ($tournamentID <= 0 || $event === "") {
    die("Invalid tournament or event.");
}


/* =========================================================
   GET TOURNAMENT INFORMATION
========================================================= */

$stmt = $conn->prepare("
    SELECT
        tournamentID,
        tournament_name
    FROM tournament
    WHERE tournamentID = ?
");

$stmt->bind_param(
    "i",
    $tournamentID
);

$stmt->execute();

$tournament = $stmt
    ->get_result()
    ->fetch_assoc();

$stmt->close();


if (!$tournament) {
    die("Tournament not found.");
}


/* =========================================================
   GET ALL MATCHES
========================================================= */

$stmt = $conn->prepare("
    SELECT
        m.matchID,
        m.tournamentID,
        m.category_registered,

        m.round_number,
        m.round_name,
        m.match_number,

        m.player1ID,
        m.player2ID,

        m.player1_seed,
        m.player2_seed,

        m.player1_score,
        m.player2_score,

        m.winnerID,

        m.next_matchID,
        m.next_match_position,

        m.match_status,

        p1.player_full_name AS player1_name,
        p2.player_full_name AS player2_name

    FROM matches m

    LEFT JOIN players p1
        ON CONVERT(p1.playerID USING utf8mb4)
         = CONVERT(m.player1ID USING utf8mb4)

    LEFT JOIN players p2
        ON CONVERT(p2.playerID USING utf8mb4)
         = CONVERT(m.player2ID USING utf8mb4)

    WHERE m.tournamentID = ?
    AND m.category_registered = ?

    ORDER BY
        m.round_number ASC,
        m.match_number ASC
");

$stmt->bind_param(
    "is",
    $tournamentID,
    $event
);

$stmt->execute();

$result = $stmt->get_result();

$matches = [];

while ($row = $result->fetch_assoc()) {
    $matches[] = $row;
}

$stmt->close();


/* =========================================================
   ORGANIZE MATCHES BY ROUND
========================================================= */

$rounds = [];

foreach ($matches as $match) {

    $roundNumber =
        (int)$match['round_number'];

    if (!isset($rounds[$roundNumber])) {
        $rounds[$roundNumber] = [];
    }

    $rounds[$roundNumber][] = $match;
}


/* =========================================================
   GET CHAMPION
========================================================= */

$champion = null;

foreach ($matches as $match) {

    /*
     The final is the match with the
     highest round number.
    */

    if (
        $match['round_number']
        == max(array_keys($rounds))
    ) {

        if (
            !empty($match['winnerID'])
        ) {

            if (
                !empty($match['player1ID']) &&
                $match['winnerID']
                === $match['player1ID']
            ) {

                $champion =
                    $match['player1_name'];

            } elseif (
                !empty($match['player2ID']) &&
                $match['winnerID']
                === $match['player2ID']
            ) {

                $champion =
                    $match['player2_name'];
            }
        }
    }
}


/* =========================================================
   FUNCTION: DISPLAY PLAYER
========================================================= */

function displayPlayerName($name)
{
    if (!$name) {
        return "TBD";
    }

    return htmlspecialchars($name);
}


function getSeedLabel($seed)
{
    if (
        $seed === null ||
        $seed === ""
    ) {
        return "";
    }

    return "Seed " . (int)$seed;
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    Tournament Draw
</title>


<style>

/* =========================================================
   GLOBAL
========================================================= */

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f5f5f5;

    color: #333;
}


/* =========================================================
   PAGE
========================================================= */

.page {

    padding: 35px;

    max-width: 1600px;

    margin: auto;
}


/* =========================================================
   HEADER
========================================================= */

.header {

    background: white;

    border-radius: 16px;

    padding: 25px 30px;

    margin-bottom: 30px;

    box-shadow:
        0 4px 15px rgba(0,0,0,0.08);
}

.header-top {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    flex-wrap: wrap;
}

.header h1 {

    margin: 0;

    color: #993D86;

    font-size: 28px;
}

.header p {

    margin: 8px 0 0;

    color: #777;

    font-size: 15px;
}

.event-badge {

    background: #993D86;

    color: white;

    padding: 10px 18px;

    border-radius: 30px;

    font-weight: bold;

    font-size: 14px;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    background: white;

    padding: 50px;

    text-align: center;

    border-radius: 16px;

    box-shadow:
        0 4px 15px rgba(0,0,0,0.08);
}

.empty h2 {

    color: #993D86;

    margin-bottom: 10px;
}


/* =========================================================
   BRACKET CONTAINER
========================================================= */

.bracket-wrapper {

    background: white;

    border-radius: 16px;

    padding: 35px;

    box-shadow:
        0 4px 15px rgba(0,0,0,0.08);

    overflow-x: auto;
}


/* =========================================================
   BRACKET
========================================================= */

.bracket {

    display: flex;

    align-items: stretch;

    gap: 70px;

    min-width: max-content;

    padding: 20px 10px 40px;
}


/* =========================================================
   ROUND
========================================================= */

.round {

    width: 260px;

    display: flex;

    flex-direction: column;
}

.round-title {

    text-align: center;

    color: #993D86;

    font-size: 17px;

    font-weight: bold;

    margin-bottom: 25px;

    min-height: 22px;
}


/* =========================================================
   MATCHES
========================================================= */

.round-matches {

    flex: 1;

    display: flex;

    flex-direction: column;

    justify-content: space-around;

    gap: 20px;
}


/* =========================================================
   MATCH
========================================================= */

.match {

    position: relative;

    background: white;

    border: 1px solid #ddd;

    border-radius: 12px;

    box-shadow:
        0 3px 10px rgba(0,0,0,0.06);

    overflow: visible;
}

.match-number {

    background: #993D86;

    color: white;

    padding: 6px 10px;

    font-size: 12px;

    font-weight: bold;

    border-radius: 12px 12px 0 0;
}


/* =========================================================
   PLAYER
========================================================= */

.player {

    display: flex;

    align-items: center;

    justify-content: space-between;

    min-height: 52px;

    padding: 10px 12px;

    border-bottom: 1px solid #eee;

    gap: 10px;
}

.player:last-child {

    border-bottom: none;
}

.player-info {

    min-width: 0;

    flex: 1;
}

.player-name {

    font-size: 14px;

    font-weight: bold;

    color: #333;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}

.player-seed {

    display: inline-block;

    margin-top: 4px;

    font-size: 11px;

    color: #777;
}

.score {

    min-width: 28px;

    text-align: center;

    font-size: 17px;

    font-weight: bold;

    color: #333;
}


/* =========================================================
   WINNER
========================================================= */

.player.winner {

    background: #f4eef3;
}

.player.winner .player-name {

    color: #993D86;
}

.player.winner .score {

    color: #993D86;
}


/* =========================================================
   TBD
========================================================= */

.player.tbd .player-name {

    color: #aaa;

    font-weight: normal;

    font-style: italic;
}


/* =========================================================
   STATUS
========================================================= */

.match-status {

    padding: 7px 10px;

    font-size: 11px;

    color: #777;

    text-align: center;

    background: #fafafa;

    border-radius: 0 0 12px 12px;
}

.match-status.completed {

    color: #2e7d32;

    font-weight: bold;
}


/* =========================================================
   CHAMPION
========================================================= */

.champion-box {

    margin-top: 30px;

    background: #993D86;

    color: white;

    border-radius: 16px;

    padding: 25px;

    text-align: center;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.12);
}

.champion-title {

    font-size: 14px;

    text-transform: uppercase;

    letter-spacing: 1px;

    opacity: .9;

    margin-bottom: 8px;
}

.champion-name {

    font-size: 25px;

    font-weight: bold;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 700px) {

    .page {
        padding: 15px;
    }

    .header {
        padding: 20px;
    }

    .bracket-wrapper {
        padding: 20px;
    }

}

</style>

</head>


<body>


<div class="page">


<!-- =====================================================
     HEADER
====================================================== -->

<div class="header">

    <div class="header-top">

        <div>

            <h1>
                <?= htmlspecialchars(
                    $tournament['tournament_name']
                ) ?>
            </h1>

            <p>
                Tournament Draw
            </p>

        </div>


        <div class="event-badge">

            <?= htmlspecialchars($event) ?>

        </div>

    </div>

</div>


<?php if (empty($matches)): ?>

    <!-- =================================================
         NO DRAW
    ================================================== -->

    <div class="empty">

        <h2>
            No Draw Generated
        </h2>

        <p>
            There are currently no matches for this
            tournament event.
        </p>

    </div>


<?php else: ?>


    <!-- =================================================
         BRACKET
    ================================================== -->

    <div class="bracket-wrapper">

        <div class="bracket">


        <?php foreach ($rounds as $roundNumber => $roundMatches): ?>


            <?php

            /*
             Get round name from first match.
            */

            $roundName =
                $roundMatches[0]['round_name'];

            ?>


            <div class="round">


                <div class="round-title">

                    <?= htmlspecialchars(
                        $roundName
                    ) ?>

                </div>


                <div class="round-matches">


                <?php foreach (
                    $roundMatches
                    as $match
                ): ?>


                    <?php

                    $player1IsWinner =
                        !empty(
                            $match['winnerID']
                        )
                        &&
                        $match['winnerID']
                        ===
                        $match['player1ID'];


                    $player2IsWinner =
                        !empty(
                            $match['winnerID']
                        )
                        &&
                        $match['winnerID']
                        ===
                        $match['player2ID'];


                    $player1Exists =
                        !empty(
                            $match['player1ID']
                        );


                    $player2Exists =
                        !empty(
                            $match['player2ID']
                        );

                    ?>


                    <div class="match">


                        <div class="match-number">

                            Match
                            <?= (int)$match[
                                'match_number'
                            ] ?>

                        </div>


                        <!-- PLAYER 1 -->

                        <div class="
                            player
                            <?= $player1IsWinner
                                ? 'winner'
                                : '' ?>
                            <?= !$player1Exists
                                ? 'tbd'
                                : '' ?>
                        ">


                            <div class="player-info">


                                <div class="player-name">

                                    <?php if (
                                        $player1Exists
                                    ): ?>

                                        <?= displayPlayerName(
                                            $match[
                                                'player1_name'
                                            ]
                                        ) ?>

                                    <?php else: ?>

                                        TBD

                                    <?php endif; ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $match[
                                            'player1_seed'
                                        ]
                                    )
                                ): ?>

                                    <div class="player-seed">

                                        Seed
                                        <?= (int)$match[
                                            'player1_seed'
                                        ] ?>

                                    </div>

                                <?php endif; ?>


                            </div>


                            <div class="score">

                                <?php

                                if (
                                    $match[
                                        'player1_score'
                                    ]
                                    !== null
                                ) {

                                    echo (int)
                                        $match[
                                            'player1_score'
                                        ];

                                }

                                ?>

                            </div>


                        </div>


                        <!-- PLAYER 2 -->

                        <div class="
                            player
                            <?= $player2IsWinner
                                ? 'winner'
                                : '' ?>
                            <?= !$player2Exists
                                ? 'tbd'
                                : '' ?>
                        ">


                            <div class="player-info">


                                <div class="player-name">

                                    <?php if (
                                        $player2Exists
                                    ): ?>

                                        <?= displayPlayerName(
                                            $match[
                                                'player2_name'
                                            ]
                                        ) ?>

                                    <?php else: ?>

                                        TBD

                                    <?php endif; ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $match[
                                            'player2_seed'
                                        ]
                                    )
                                ): ?>

                                    <div class="player-seed">

                                        Seed
                                        <?= (int)$match[
                                            'player2_seed'
                                        ] ?>

                                    </div>

                                <?php endif; ?>


                            </div>


                            <div class="score">

                                <?php

                                if (
                                    $match[
                                        'player2_score'
                                    ]
                                    !== null
                                ) {

                                    echo (int)
                                        $match[
                                            'player2_score'
                                        ];

                                }

                                ?>

                            </div>


                        </div>


                        <!-- STATUS -->

                        <div class="
                            match-status
                            <?= $match[
                                'match_status'
                            ] === 'Completed'
                                ? 'completed'
                                : '' ?>
                        ">

                            <?= htmlspecialchars(
                                $match[
                                    'match_status'
                                ]
                            ) ?>

                        </div>


                    </div>


                <?php endforeach; ?>


                </div>

            </div>


        <?php endforeach; ?>


        </div>


        <!-- =================================================
             CHAMPION
        ================================================== -->

        <?php if ($champion): ?>

            <div class="champion-box">

                <div class="champion-title">

                    🏆 Champion

                </div>

                <div class="champion-name">

                    <?= htmlspecialchars(
                        $champion
                    ) ?>

                </div>

            </div>

        <?php endif; ?>


    </div>


<?php endif; ?>


</div>


</body>

</html>