<?php

/*
|--------------------------------------------------------------------------
| Check Whether Ranking Exists
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Get Ranking Information
|--------------------------------------------------------------------------
|
| Priority:
| 1. World Ranking
| 2. AJSS Ranking
| 3. National Ranking
| 4. SPIN Number
|
| Lower ranking number = stronger player
|--------------------------------------------------------------------------
*/

function getRankingInformation($player)
{
    if (hasRanking($player['world_ranking'])) {

        return [
            'type' => 'World Ranking',
            'value' => (int)$player['world_ranking'],
            'priority' => 1
        ];
    }

    if (hasRanking($player['ajss_ranking'])) {

        return [
            'type' => 'AJSS Ranking',
            'value' => (int)$player['ajss_ranking'],
            'priority' => 2
        ];
    }

    if (hasRanking($player['national_ranking'])) {

        return [
            'type' => 'National Ranking',
            'value' => (int)$player['national_ranking'],
            'priority' => 3
        ];
    }

    if (hasRanking($player['spin_number'])) {

        return [
            'type' => 'SPIN Number',
            'value' => (int)$player['spin_number'],
            'priority' => 4
        ];
    }

    return [
        'type' => 'Unranked',
        'value' => PHP_INT_MAX,
        'priority' => 999
    ];
}


/*
|--------------------------------------------------------------------------
| Automatic Seed Players
|--------------------------------------------------------------------------
*/

function automaticSeedPlayers(
    $conn,
    $tournamentID,
    $event
) {

    /*
    |--------------------------------------------------------------------------
    | Get Registered Players
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            tr.registrationID,

            /*
             * IMPORTANT:
             * Use playerID from players table.
             *
             * Player IDs are values such as:
             * P0001
             * P0002
             * P0008
             *
             * DO NOT convert these to integers.
             */

            p.playerID AS playerID,

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
             =
               CONVERT(tr.playerID USING utf8mb4)

        WHERE tr.tournamentID = ?
        AND tr.category_registered = ?

        ORDER BY tr.registrationID ASC
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
            "Unable to load players: "
            . $stmt->error
        );
    }


    $result = $stmt->get_result();

    $players = [];


    /*
    |--------------------------------------------------------------------------
    | Add Ranking Information
    |--------------------------------------------------------------------------
    */

    while ($row = $result->fetch_assoc()) {

        $ranking = getRankingInformation($row);

        $row['ranking_type'] =
            $ranking['type'];

        $row['ranking_value'] =
            $ranking['value'];

        $row['ranking_priority'] =
            $ranking['priority'];

        $players[] = $row;
    }


    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Minimum Players
    |--------------------------------------------------------------------------
    */

    if (count($players) < 2) {

        throw new Exception(
            "At least 2 players are required."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Sort Players
    |--------------------------------------------------------------------------
    |
    | Ranking priority first.
    | Ranking number second.
    | Registration ID final tie breaker.
    |--------------------------------------------------------------------------
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


    /*
    |--------------------------------------------------------------------------
    | Reset Existing Seeds
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE tournament_register

        SET seed_number = NULL

        WHERE tournamentID = ?

        AND category_registered = ?
    ");

    if (!$stmt) {

        throw new Exception(
            "Unable to prepare seed reset: "
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
            "Unable to reset seeds: "
            . $stmt->error
        );
    }


    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Assign Seeds
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE tournament_register

        SET seed_number = ?

        WHERE registrationID = ?

        AND tournamentID = ?

        AND category_registered = ?
    ");

    if (!$stmt) {

        throw new Exception(
            "Unable to prepare seed update: "
            . $conn->error
        );
    }


    $seed = 1;


    foreach ($players as &$player) {

        $registrationID =
            (int)$player['registrationID'];


        $stmt->bind_param(
            "iiis",
            $seed,
            $registrationID,
            $tournamentID,
            $event
        );


        if (!$stmt->execute()) {

            throw new Exception(
                "Unable to assign seed: "
                . $stmt->error
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Update Seed In Current Player Array
        |--------------------------------------------------------------------------
        */

        $player['seed_number'] =
            $seed;


        $seed++;
    }


    unset($player);


    $stmt->close();


    return $players;
}