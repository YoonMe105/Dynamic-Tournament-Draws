<?php

/*
|--------------------------------------------------------------------------
| Calculate Next Power of 2
|--------------------------------------------------------------------------
*/

function nextPowerOfTwo($number)
{
    $power = 1;

    while ($power < $number) {
        $power *= 2;
    }

    return $power;
}


/*
|--------------------------------------------------------------------------
| Round Names
|--------------------------------------------------------------------------
*/

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
            return "Round of " . $playersInRound;
    }
}


/*
|--------------------------------------------------------------------------
| Generate Standard Seed Positions
|--------------------------------------------------------------------------
*/

function generateSeedPositions($size)
{
    if ($size == 1) {
        return [1];
    }

    if ($size == 2) {
        return [1, 2];
    }

    $positions = [1, 2];

    while (count($positions) < $size) {

        $currentSize = count($positions);
        $nextSize = $currentSize * 2;

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


/*
|--------------------------------------------------------------------------
| Get Players For Draw
|--------------------------------------------------------------------------
*/

function getDrawPlayers(
    $conn,
    $tournamentID,
    $event
) {

    $stmt = $conn->prepare("
        SELECT

            tr.registrationID,

            p.playerID AS playerID,

            tr.tournamentID,
            tr.category_registered,
            tr.seed_number,

            p.player_full_name,
            p.player_first_name,
            p.player_last_name,
            p.player_nationality

        FROM tournament_register tr

        INNER JOIN players p
            ON p.playerID = tr.playerID

        WHERE tr.tournamentID = ?
        AND tr.category_registered = ?

        ORDER BY

            CASE
                WHEN tr.seed_number IS NULL
                THEN 999999
                ELSE tr.seed_number
            END ASC,

            tr.registrationID ASC
    ");

    if (!$stmt) {

        throw new Exception(
            "Unable to prepare player query: "
            . $conn->error
        );
    }

    $stmt->bind_param(
        "is",
        $tournamentID,
        $event
    );

    if (!$stmt->execute()) {

        throw new Exception(
            "Unable to load draw players: "
            . $stmt->error
        );
    }

    $result = $stmt->get_result();

    $players = [];

    while ($row = $result->fetch_assoc()) {

        $players[] = $row;
    }

    $stmt->close();

    return $players;
}


/*
|--------------------------------------------------------------------------
| Advance Player To Next Match
|--------------------------------------------------------------------------
|
| This function receives:
|
| $playerID     = P0001
| $nextMatchID  = 15
| $position     = 1 or 2
| $seed         = player's seed number
|
|--------------------------------------------------------------------------
*/

function advancePlayer(
    $conn,
    $playerID,
    $nextMatchID,
    $position,
    $seed = null
) {

    if (
        empty($playerID) ||
        empty($nextMatchID)
    ) {
        return false;
    }

    $nextMatchID = (int)$nextMatchID;

    $position = (int)$position;

    if ($seed === null) {
        $seed = null;
    } else {
        $seed = (int)$seed;
    }


    /*
    |--------------------------------------------------------------------------
    | Put Player Into Player 1
    |--------------------------------------------------------------------------
    */

    if ($position == 1) {

        $stmt = $conn->prepare("
            UPDATE matches

            SET
                player1ID = ?,
                player1_seed = ?,
                player1_score = NULL

            WHERE matchID = ?
        ");

    }


    /*
    |--------------------------------------------------------------------------
    | Put Player Into Player 2
    |--------------------------------------------------------------------------
    */

    else {

        $stmt = $conn->prepare("
            UPDATE matches

            SET
                player2ID = ?,
                player2_seed = ?,
                player2_score = NULL

            WHERE matchID = ?
        ");
    }


    if (!$stmt) {

        throw new Exception(
            "Unable to prepare player advancement: "
            . $conn->error
        );
    }


    $stmt->bind_param(
        "sii",
        $playerID,
        $seed,
        $nextMatchID
    );


    if (!$stmt->execute()) {

        throw new Exception(
            "Unable to advance player: "
            . $stmt->error
        );
    }

    $stmt->close();

    return true;
}


function advanceWinner(
    $conn,
    $matchID,
    $winnerID
) {

    if (empty($winnerID)) {
        return false;
    }

    $matchID = (int)$matchID;


    /*
    |--------------------------------------------------------------------------
    | Get Next Match Information
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT

            next_matchID,
            next_match_position,

            CASE
                WHEN player1ID = ?
                THEN player1_seed

                WHEN player2ID = ?
                THEN player2_seed

                ELSE NULL
            END AS winner_seed

        FROM matches

        WHERE matchID = ?
    ");


    if (!$stmt) {

        throw new Exception(
            "Unable to prepare winner advancement query: "
            . $conn->error
        );
    }


    /*
    | winnerID is STRING
    | matchID is INTEGER
    */

    $stmt->bind_param(
        "ssi",
        $winnerID,
        $winnerID,
        $matchID
    );


    if (!$stmt->execute()) {

        throw new Exception(
            "Unable to get next match information: "
            . $stmt->error
        );
    }


    $result = $stmt->get_result();

    $match = $result->fetch_assoc();

    $stmt->close();


    if (!$match) {

        throw new Exception(
            "Match ID {$matchID} was not found."
        );
    }


    if (
        empty($match['next_matchID'])
    ) {

        return true;
    }


    $nextMatchID =
        (int)$match['next_matchID'];


    $nextPosition =
        (int)$match['next_match_position'];


    $winnerSeed =
        $match['winner_seed'] !== null
        ? (int)$match['winner_seed']
        : null;

    return advancePlayer(
        $conn,
        $winnerID,
        $nextMatchID,
        $nextPosition,
        $winnerSeed
    );
}


/*
|--------------------------------------------------------------------------
| Generate Tournament Draw
|--------------------------------------------------------------------------
*/

function generateTournamentDraw(
    $conn,
    $tournamentID,
    $event,
    $players = null
) {

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total

        FROM matches

        WHERE tournamentID = ?
        AND category_registered = ?
    ");

    if (!$stmt) {

        throw new Exception(
            "Unable to check existing draw: "
            . $conn->error
        );
    }

    $stmt->bind_param(
        "is",
        $tournamentID,
        $event
    );

    if (!$stmt->execute()) {

        throw new Exception(
            "Unable to check existing draw: "
            . $stmt->error
        );
    }

    $result = $stmt->get_result();

    $row = $result->fetch_assoc();

    $existing = (int)$row['total'];

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Do Not Regenerate Existing Draw
    |--------------------------------------------------------------------------
    */

    if ($existing > 0) {

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Get Players
    |--------------------------------------------------------------------------
    */

    if ($players === null) {

        $players =
            getDrawPlayers(
                $conn,
                $tournamentID,
                $event
            );
    }


    $playerCount =
        count($players);


    if ($playerCount < 2) {

        throw new Exception(
            "At least 2 players are required to create a draw."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Check Seeds
    |--------------------------------------------------------------------------
    */

    foreach ($players as $player) {

        if (
            empty($player['seed_number']) ||
            (int)$player['seed_number'] <= 0
        ) {

            throw new Exception(
                "Player "
                . $player['player_full_name']
                . " does not have a valid seed number."
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Calculate Bracket Size
    |--------------------------------------------------------------------------
    */

    $bracketSize =
        nextPowerOfTwo(
            $playerCount
        );


    $byeCount =
        $bracketSize - $playerCount;


    /*
    |--------------------------------------------------------------------------
    | Create Seed Map
    |--------------------------------------------------------------------------
    */

    $seedMap = [];

    foreach ($players as $player) {

        $seed =
            (int)$player['seed_number'];

        $seedMap[$seed] =
            $player;
    }


    /*
    |--------------------------------------------------------------------------
    | Generate Standard Seed Positions
    |--------------------------------------------------------------------------
    */

    $seedPositions =
        generateSeedPositions(
            $bracketSize
        );


    /*
    |--------------------------------------------------------------------------
    | Create Bracket
    |--------------------------------------------------------------------------
    */

    $bracket =
        array_fill(
            0,
            $bracketSize,
            null
        );


    /*
    |--------------------------------------------------------------------------
    | Put Players Into Bracket
    |--------------------------------------------------------------------------
    */

    foreach (
        $seedPositions as $index => $seed
    ) {

        if (isset($seedMap[$seed])) {

            $bracket[$index] =
                $seedMap[$seed];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Total Number Of Rounds
    |--------------------------------------------------------------------------
    */

    $totalRounds =
        (int)log(
            $bracketSize,
            2
        );


    $roundMatches = [];


    /*
    |--------------------------------------------------------------------------
    | Prepare Match Insert
    |--------------------------------------------------------------------------
    |
    | player1ID and player2ID are VARCHAR.
    |
    */

    $stmtInsert = $conn->prepare("
        INSERT INTO matches (

            tournamentID,
            category_registered,

            round_number,
            round_name,
            match_number,

            player1ID,
            player2ID,

            player1_seed,
            player2_seed,

            player1_score,
            player2_score,

            match_status

        )

        VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?
        )
    ");


    if (!$stmtInsert) {

        throw new Exception(
            "Unable to prepare match insertion: "
            . $conn->error
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ROUND 1
    |--------------------------------------------------------------------------
    */

    $round = 1;

    $matchesInRound =
        (int)($bracketSize / 2);


    $roundName =
        getRoundName(
            $bracketSize
        );


    $roundMatches[$round] = [];


    for (
        $matchNo = 1;
        $matchNo <= $matchesInRound;
        $matchNo++
    ) {

        $index1 =
            ($matchNo - 1) * 2;

        $index2 =
            $index1 + 1;


        $player1 =
            $bracket[$index1];

        $player2 =
            $bracket[$index2];


        /*
        |--------------------------------------------------------------------------
        | Player IDs
        |--------------------------------------------------------------------------
        |
        | DO NOT CAST THESE TO INTEGER.
        |
        | P0001 must remain P0001.
        |
        */

        $player1ID =
            $player1
            ? $player1['playerID']
            : null;


        $player2ID =
            $player2
            ? $player2['playerID']
            : null;


        /*
        |--------------------------------------------------------------------------
        | Seeds
        |--------------------------------------------------------------------------
        */

        $player1Seed =
            $player1
            ? (int)$player1['seed_number']
            : null;


        $player2Seed =
            $player2
            ? (int)$player2['seed_number']
            : null;


        $status =
            "Pending";


        /*
        |--------------------------------------------------------------------------
        | Insert
        |--------------------------------------------------------------------------
        |
        | i = tournamentID
        | s = event
        | i = round
        | s = roundName
        | i = matchNo
        | s = player1ID
        | s = player2ID
        | i = seed1
        | i = seed2
        | s = status
        |
        */

        $stmtInsert->bind_param(
            "isisissiis",
            $tournamentID,
            $event,
            $round,
            $roundName,
            $matchNo,
            $player1ID,
            $player2ID,
            $player1Seed,
            $player2Seed,
            $status
        );


        if (!$stmtInsert->execute()) {

            throw new Exception(
                "Unable to create match: "
                . $stmtInsert->error
            );
        }


        $matchID =
            $stmtInsert->insert_id;


        $roundMatches[$round][] =
            $matchID;
    }


    /*
    |--------------------------------------------------------------------------
    | FUTURE ROUNDS
    |--------------------------------------------------------------------------
    */

    for (
        $round = 2;
        $round <= $totalRounds;
        $round++
    ) {

        $previousRound =
            $round - 1;


        $matchesInRound =
            (int)(
                count(
                    $roundMatches[$previousRound]
                ) / 2
            );


        $playersInRound =
            (int)(
                $bracketSize /
                pow(
                    2,
                    $round - 1
                )
            );


        $roundName =
            getRoundName(
                $playersInRound
            );


        $roundMatches[$round] = [];


        for (
            $matchNo = 1;
            $matchNo <= $matchesInRound;
            $matchNo++
        ) {

            $player1ID = null;
            $player2ID = null;

            $player1Seed = null;
            $player2Seed = null;

            $status = "Pending";


            $stmtInsert->bind_param(
                "isisissiis",
                $tournamentID,
                $event,
                $round,
                $roundName,
                $matchNo,
                $player1ID,
                $player2ID,
                $player1Seed,
                $player2Seed,
                $status
            );


            if (!$stmtInsert->execute()) {

                throw new Exception(
                    "Unable to create future match: "
                    . $stmtInsert->error
                );
            }


            $matchID =
                $stmtInsert->insert_id;


            $roundMatches[$round][] =
                $matchID;
        }
    }


    $stmtInsert->close();


    /*
    |--------------------------------------------------------------------------
    | Connect Rounds
    |--------------------------------------------------------------------------
    */

    for (
        $round = 1;
        $round < $totalRounds;
        $round++
    ) {

        $currentMatches =
            $roundMatches[$round];


        $nextMatches =
            $roundMatches[$round + 1];


        foreach (
            $currentMatches as $index => $currentMatchID
        ) {

            $nextMatchIndex =
                (int)floor(
                    $index / 2
                );


            $nextMatchID =
                $nextMatches[
                    $nextMatchIndex
                ];


            /*
            | Even match -> Player 1
            | Odd match  -> Player 2
            */

            $nextPosition =
                ($index % 2 == 0)
                ? 1
                : 2;


            $stmt =
                $conn->prepare("
                    UPDATE matches

                    SET
                        next_matchID = ?,
                        next_match_position = ?

                    WHERE matchID = ?
                ");


            if (!$stmt) {

                throw new Exception(
                    "Unable to connect matches: "
                    . $conn->error
                );
            }


            $stmt->bind_param(
                "iii",
                $nextMatchID,
                $nextPosition,
                $currentMatchID
            );


            if (!$stmt->execute()) {

                throw new Exception(
                    "Unable to connect matches: "
                    . $stmt->error
                );
            }


            $stmt->close();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Process BYEs
    |--------------------------------------------------------------------------
    */

    if ($totalRounds >= 2) {

        for (
            $index = 0;
            $index < count($roundMatches[1]);
            $index++
        ) {

            $player1 =
                $bracket[
                    $index * 2
                ];


            $player2 =
                $bracket[
                    ($index * 2) + 1
                ];


            /*
            |--------------------------------------------------------------------------
            | Player 1 Gets BYE
            |--------------------------------------------------------------------------
            */

            if (
                $player1 &&
                !$player2
            ) {

                $matchID =
                    $roundMatches[1][$index];


                $winnerID =
                    $player1['playerID'];


                $winnerSeed =
                    (int)$player1['seed_number'];


                /*
                | Set Winner
                */

                $stmt =
                    $conn->prepare("
                        UPDATE matches

                        SET
                            winnerID = ?,
                            match_status = 'Completed'

                        WHERE matchID = ?
                    ");


                if (!$stmt) {

                    throw new Exception(
                        "Unable to prepare BYE update: "
                        . $conn->error
                    );
                }


                $stmt->bind_param(
                    "si",
                    $winnerID,
                    $matchID
                );


                if (!$stmt->execute()) {

                    throw new Exception(
                        "Unable to update BYE winner: "
                        . $stmt->error
                    );
                }


                $stmt->close();


                /*
                |--------------------------------------------------------------------------
                | Find Next Match
                |--------------------------------------------------------------------------
                */

                $nextMatchIndex =
                    (int)floor(
                        $index / 2
                    );


                if (
                    isset(
                        $roundMatches[2][$nextMatchIndex]
                    )
                ) {

                    $nextMatchID =
                        $roundMatches[2]
                        [$nextMatchIndex];


                    $nextPosition =
                        ($index % 2 == 0)
                        ? 1
                        : 2;


                    advancePlayer(
                        $conn,
                        $winnerID,
                        $nextMatchID,
                        $nextPosition,
                        $winnerSeed
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Player 2 Gets BYE
            |--------------------------------------------------------------------------
            */

            elseif (
                !$player1 &&
                $player2
            ) {

                $matchID =
                    $roundMatches[1][$index];


                $winnerID =
                    $player2['playerID'];


                $winnerSeed =
                    (int)$player2['seed_number'];


                /*
                | Set Winner
                */

                $stmt =
                    $conn->prepare("
                        UPDATE matches

                        SET
                            winnerID = ?,
                            match_status = 'Completed'

                        WHERE matchID = ?
                    ");


                if (!$stmt) {

                    throw new Exception(
                        "Unable to prepare BYE update: "
                        . $conn->error
                    );
                }


                $stmt->bind_param(
                    "si",
                    $winnerID,
                    $matchID
                );


                if (!$stmt->execute()) {

                    throw new Exception(
                        "Unable to update BYE winner: "
                        . $stmt->error
                    );
                }


                $stmt->close();


                /*
                |--------------------------------------------------------------------------
                | Find Next Match
                |--------------------------------------------------------------------------
                */

                $nextMatchIndex =
                    (int)floor(
                        $index / 2
                    );


                if (
                    isset(
                        $roundMatches[2][$nextMatchIndex]
                    )
                ) {

                    $nextMatchID =
                        $roundMatches[2]
                        [$nextMatchIndex];


                    $nextPosition =
                        ($index % 2 == 0)
                        ? 1
                        : 2;


                    advancePlayer(
                        $conn,
                        $winnerID,
                        $nextMatchID,
                        $nextPosition,
                        $winnerSeed
                    );
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Return Draw Information
    |--------------------------------------------------------------------------
    */

    return [

        'playerCount' =>
            $playerCount,

        'bracketSize' =>
            $bracketSize,

        'byeCount' =>
            $byeCount,

        'totalRounds' =>
            $totalRounds
    ];
}