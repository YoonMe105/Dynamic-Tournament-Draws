<?php
require_once "../db.php";
require_once "automatic_seeding.php";
require_once "generate_draw.php";

/*
|--------------------------------------------------------------------------
| Get Tournament and Event
|--------------------------------------------------------------------------
*/

$tournamentID = isset($_GET['tournamentID']) ? intval($_GET['tournamentID']) : 0;
$event = isset($_GET['event']) ? trim($_GET['event']) : '';

/*
|--------------------------------------------------------------------------
| Get All Tournaments
|--------------------------------------------------------------------------
*/

$tournaments = [];

$sql = "SELECT tournamentID, tournament_name
        FROM tournament
        ORDER BY tournament_startdate DESC";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tournaments[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| Get Events for Selected Tournament
|--------------------------------------------------------------------------
*/

$events = [];

if ($tournamentID > 0) {

    $stmt = $conn->prepare("
        SELECT DISTINCT category_registered
        FROM tournament_register
        WHERE tournamentID = ?
        AND category_registered IS NOT NULL
        AND category_registered != ''
        ORDER BY category_registered
    ");

    $stmt->bind_param("i", $tournamentID);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $events[] = $row['category_registered'];
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Get Tournament Name
|--------------------------------------------------------------------------
*/

$tournamentName = '';

if ($tournamentID > 0) {

    $stmt = $conn->prepare("
        SELECT tournament_name
        FROM tournament
        WHERE tournamentID = ?
    ");

    $stmt->bind_param("i", $tournamentID);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $tournamentName = $row['tournament_name'];
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Generate Draw Automatically If It Does Not Exist
|--------------------------------------------------------------------------
*/

$drawGenerated = false;

if ($tournamentID > 0 && $event != '') {

    /*
    |--------------------------------------------------------------------------
    | Check whether draw already exists
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM matches
        WHERE tournamentID = ?
        AND category_registered = ?
    ");

    $stmt->bind_param("is", $tournamentID, $event);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $matchCount = intval($row['total']);

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Generate only if no draw exists
    |--------------------------------------------------------------------------
    */

    if ($matchCount == 0) {

        try {

            // Get registered players
            $players = getDrawPlayers($conn, $tournamentID, $event);

            if (count($players) > 0) {

                /*
                |--------------------------------------------------------------------------
                | Automatic Seeding
                |--------------------------------------------------------------------------
                */

                $seededPlayers = automaticSeedPlayers(
                    $conn,
                    $tournamentID,
                    $event
                );
                /*
                |--------------------------------------------------------------------------
                | Generate Draw
                |--------------------------------------------------------------------------
                */

                generateTournamentDraw(
                    $conn,
                    $tournamentID,
                    $event,
                    $seededPlayers
                );

                $drawGenerated = true;

            }

        } catch (Exception $e) {

            $errorMessage = $e->getMessage();

        }
    }
}

/*
|--------------------------------------------------------------------------
| Get Matches
|--------------------------------------------------------------------------
*/

$matchesByRound = [];
$roundNames = [];

if ($tournamentID > 0 && $event != '') {

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
            p1.player_nationality AS player1_nationality,

            p2.player_full_name AS player2_name,
            p2.player_nationality AS player2_nationality

        FROM matches m

        LEFT JOIN players p1
            ON m.player1ID = p1.playerID

        LEFT JOIN players p2
            ON m.player2ID = p2.playerID

        WHERE m.tournamentID = ?
        AND m.category_registered = ?

        ORDER BY
            m.round_number ASC,
            m.match_number ASC
    ");

    $stmt->bind_param("is", $tournamentID, $event);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $round = intval($row['round_number']);

        if (!isset($matchesByRound[$round])) {
            $matchesByRound[$round] = [];
        }

        $matchesByRound[$round][] = $row;

        $roundNames[$round] = $row['round_name'];
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Country Flag
|--------------------------------------------------------------------------
*/

function getCountryFlag($country)
{
    if (!$country) {
        return '';
    }

    $country = strtolower(trim($country));

    $flags = [

        'malaysia' => '🇲🇾',
        'malaysian' => '🇲🇾',

        'singapore' => '🇸🇬',
        'singaporean' => '🇸🇬',

        'myanmar' => '🇲🇲',
        'myanmese' => '🇲🇲',
        'burma' => '🇲🇲',

        'thailand' => '🇹🇭',
        'thai' => '🇹🇭',

        'indonesia' => '🇮🇩',
        'indonesian' => '🇮🇩',

        'japan' => '🇯🇵',
        'japanese' => '🇯🇵',

        'china' => '🇨🇳',
        'chinese' => '🇨🇳',

        'hong kong' => '🇭🇰',
        'hongkong' => '🇭🇰',

        'taiwan' => '🇹🇼',
        'taiwanese' => '🇹🇼',

        'south korea' => '🇰🇷',
        'korea' => '🇰🇷',
        'korean' => '🇰🇷',

        'india' => '🇮🇳',
        'indian' => '🇮🇳',

        'pakistan' => '🇵🇰',
        'pakistani' => '🇵🇰',

        'bangladesh' => '🇧🇩',
        'bangladeshi' => '🇧🇩',

        'philippines' => '🇵🇭',
        'filipino' => '🇵🇭',

        'vietnam' => '🇻🇳',
        'vietnamese' => '🇻🇳',

        'cambodia' => '🇰🇭',
        'cambodian' => '🇰🇭',

        'laos' => '🇱🇦',
        'laotian' => '🇱🇦',

        'brunei' => '🇧🇳',
        'bruneian' => '🇧🇳',

        'united states' => '🇺🇸',
        'usa' => '🇺🇸',
        'american' => '🇺🇸',

        'canada' => '🇨🇦',
        'canadian' => '🇨🇦',

        'england' => '🇬🇧',
        'british' => '🇬🇧',
        'united kingdom' => '🇬🇧',
        'uk' => '🇬🇧',

        'australia' => '🇦🇺',
        'australian' => '🇦🇺',

        'new zealand' => '🇳🇿',
        'new zealander' => '🇳🇿',

        'france' => '🇫🇷',
        'french' => '🇫🇷',

        'germany' => '🇩🇪',
        'german' => '🇩🇪',

        'italy' => '🇮🇹',
        'italian' => '🇮🇹',

        'spain' => '🇪🇸',
        'spanish' => '🇪🇸',

        'netherlands' => '🇳🇱',
        'dutch' => '🇳🇱',

        'switzerland' => '🇨🇭',
        'swiss' => '🇨🇭',

        'sweden' => '🇸🇪',
        'swedish' => '🇸🇪',

        'norway' => '🇳🇴',
        'norwegian' => '🇳🇴',

        'denmark' => '🇩🇰',
        'danish' => '🇩🇰',

        'russia' => '🇷🇺',
        'russian' => '🇷🇺',

        'ukraine' => '🇺🇦',
        'ukrainian' => '🇺🇦',

        'nepal' => '🇳🇵',
        'nepalese' => '🇳🇵',

        'sri lanka' => '🇱🇰',
        'sri lankan' => '🇱🇰',

        'south africa' => '🇿🇦',
        'south african' => '🇿🇦'

    ];

    return $flags[$country] ?? '🌐';
}

/*
|--------------------------------------------------------------------------
| Get Champion
|--------------------------------------------------------------------------
*/

$champion = null;

if (!empty($matchesByRound)) {

    $lastRoundNumber = max(array_keys($matchesByRound));

    foreach ($matchesByRound[$lastRoundNumber] as $finalMatch) {

        if (!empty($finalMatch['winnerID'])) {

            if ($finalMatch['winnerID'] == $finalMatch['player1ID']) {

                $champion = [
                    'name' => $finalMatch['player1_name'],
                    'nationality' => $finalMatch['player1_nationality'],
                    'seed' => $finalMatch['player1_seed']
                ];

            } elseif ($finalMatch['winnerID'] == $finalMatch['player2ID']) {

                $champion = [
                    'name' => $finalMatch['player2_name'],
                    'nationality' => $finalMatch['player2_nationality'],
                    'seed' => $finalMatch['player2_seed']
                ];
            }

            break;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Helper: Display Player
|--------------------------------------------------------------------------
*/

function displayPlayer($match, $playerNumber)
{
    $nameKey = "player{$playerNumber}_name";
    $nationalityKey = "player{$playerNumber}_nationality";
    $seedKey = "player{$playerNumber}_seed";
    $scoreKey = "player{$playerNumber}_score";
    $idKey = "player{$playerNumber}ID";

    $name = $match[$nameKey];
    $nationality = $match[$nationalityKey];
    $seed = $match[$seedKey];
    $score = $match[$scoreKey];
    $playerID = $match[$idKey];

    $winner = (
        !empty($match['winnerID']) &&
        $match['winnerID'] == $playerID
    );

    ?>

    <div class="player <?= $winner ? 'winner' : '' ?>">

        <div class="player-information">

            <?php if ($name): ?>

                <div class="player-name">

                    <?= htmlspecialchars($name) ?>

                </div>

                <?php if ($nationality): ?>

                    <div class="player-nationality">

                        <span class="flag">
                            <?= getCountryFlag($nationality) ?>
                        </span>

                        <?= htmlspecialchars($nationality) ?>

                    </div>

                <?php endif; ?>

            <?php else: ?>

                <div class="bye-player">
                    BYE
                </div>

            <?php endif; ?>

        </div>

        <div class="player-result">

            <?php if ($seed): ?>

                <span class="seed">
                    #<?= htmlspecialchars($seed) ?>
                </span>

            <?php endif; ?>

            <?php if ($score !== null && $score !== ''): ?>

                <span class="score">
                    <?= htmlspecialchars($score) ?>
                </span>

            <?php endif; ?>

        </div>

    </div>

    <?php
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Tournament Draw
        <?php if ($tournamentName): ?>
            - <?= htmlspecialchars($tournamentName) ?>
        <?php endif; ?>
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {

            margin: 0;

            padding: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f5f3f5;

            color: #333;

        }

        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        .page-header {

            background: linear-gradient(
                135deg,
                #993D86,
                #6f2b62
            );

            color: white;

            padding: 25px 35px;

        }

        .page-header h1 {

            margin: 0;

            font-size: 28px;

        }

        .page-header p {

            margin: 8px 0 0;

            opacity: 0.9;

        }

        /*
        |--------------------------------------------------------------------------
        | Main Container
        |--------------------------------------------------------------------------
        */

        .container {

            max-width: 1600px;

            margin: auto;

            padding: 25px;

        }

        /*
        |--------------------------------------------------------------------------
        | Selection Area
        |--------------------------------------------------------------------------
        */

        .selection-card {

            background: white;

            padding: 20px;

            border-radius: 12px;

            margin-bottom: 25px;

            box-shadow:
                0 3px 12px rgba(
                    0,
                    0,
                    0,
                    0.08
                );

        }

        .selection-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 20px;

        }

        .form-group {

            display: flex;

            flex-direction: column;

            gap: 8px;

        }

        .form-group label {

            font-weight: bold;

            color: #555;

        }

        .form-group select {

            padding: 12px;

            border: 1px solid #ddd;

            border-radius: 8px;

            font-size: 15px;

            background: white;

        }

        .form-group select:focus {

            outline: none;

            border-color: #993D86;

        }

        /*
        |--------------------------------------------------------------------------
        | Tournament Information
        |--------------------------------------------------------------------------
        */

        .draw-title {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 20px;

        }

        .draw-title h2 {

            margin: 0;

            color: #993D86;

        }

        .event-label {

            background: #993D86;

            color: white;

            padding: 8px 15px;

            border-radius: 20px;

            font-size: 14px;

        }

        /*
        |--------------------------------------------------------------------------
        | Messages
        |--------------------------------------------------------------------------
        */

        .message {

            padding: 15px;

            border-radius: 8px;

            margin-bottom: 20px;

        }

        .success {

            background: #e9f7ef;

            color: #1e8449;

        }

        .error {

            background: #fdecea;

            color: #c0392b;

        }

        .info {

            background: #f0e9f0;

            color: #6f2b62;

        }

        /*
        |--------------------------------------------------------------------------
        | Bracket
        |--------------------------------------------------------------------------
        */

        .bracket-wrapper {

            width: 100%;

            overflow-x: auto;

            padding-bottom: 30px;

        }

        .bracket {

            display: flex;

            align-items: stretch;

            gap: 50px;

            min-width: max-content;

            padding: 20px;

        }

        .round {

            min-width: 250px;

            display: flex;

            flex-direction: column;

        }

        .round-title {

            text-align: center;

            background: #993D86;

            color: white;

            padding: 12px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-weight: bold;

        }

        .round-matches {

            flex: 1;

            display: flex;

            flex-direction: column;

            justify-content: space-around;

            gap: 20px;

        }

        /*
        |--------------------------------------------------------------------------
        | Match
        |--------------------------------------------------------------------------
        */

        .match {

            background: white;

            border-radius: 10px;

            overflow: hidden;

            box-shadow:
                0 3px 10px rgba(
                    0,
                    0,
                    0,
                    0.08
                );

            border: 1px solid #e5e5e5;

            position: relative;

        }

        .match-header {

            background: #f6f2f6;

            padding: 7px 10px;

            font-size: 11px;

            color: #777;

            display: flex;

            justify-content: space-between;

        }

        /*
        |--------------------------------------------------------------------------
        | Player
        |--------------------------------------------------------------------------
        */

        .player {

            min-height: 65px;

            padding: 9px 10px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            border-bottom: 1px solid #eee;

            transition: 0.2s;

        }

        .player:last-child {

            border-bottom: none;

        }

        .player.winner {

            background: #f4eaf3;

            font-weight: bold;

            border-left: 4px solid #993D86;

        }

        .player-information {

            min-width: 0;

            flex: 1;

        }

        .player-name {

            font-size: 14px;

            font-weight: 600;

            color: #333;

            overflow: hidden;

            text-overflow: ellipsis;

            white-space: nowrap;

        }

        .player-nationality {

            margin-top: 4px;

            font-size: 11px;

            color: #777;

            display: flex;

            align-items: center;

            gap: 4px;

        }

        .flag {

            font-size: 14px;

            line-height: 1;

        }

        .bye-player {

            color: #aaa;

            font-style: italic;

            font-size: 13px;

        }

        /*
        |--------------------------------------------------------------------------
        | Seed and Score
        |--------------------------------------------------------------------------
        */

        .player-result {

            display: flex;

            align-items: center;

            gap: 8px;

            margin-left: 8px;

        }

        .seed {

            font-size: 10px;

            color: #888;

            background: #f1f1f1;

            padding: 3px 6px;

            border-radius: 4px;

            white-space: nowrap;

        }

        .score {

            font-size: 16px;

            font-weight: bold;

            color: #333;

            min-width: 18px;

            text-align: center;

        }

        /*
        |--------------------------------------------------------------------------
        | Match Status
        |--------------------------------------------------------------------------
        */

        .match-status {

            padding: 6px 10px;

            font-size: 10px;

            text-align: right;

            color: #999;

        }

        .status-completed {

            color: #2e7d32;

        }

        .status-pending {

            color: #f39c12;

        }

        /*
        |--------------------------------------------------------------------------
        | Champion
        |--------------------------------------------------------------------------
        */

        .champion-card {

            background: white;

            border-radius: 12px;

            padding: 25px;

            margin-bottom: 25px;

            text-align: center;

            box-shadow:
                0 3px 12px rgba(
                    0,
                    0,
                    0,
                    0.08
                );

            border: 2px solid #993D86;

        }

        .champion-icon {

            font-size: 45px;

            margin-bottom: 10px;

        }

        .champion-card h3 {

            margin: 5px 0;

            color: #993D86;

        }

        .champion-name {

            font-size: 22px;

            font-weight: bold;

        }

        .champion-nationality {

            margin-top: 6px;

            color: #777;

        }

        /*
        |--------------------------------------------------------------------------
        | No Draw
        |--------------------------------------------------------------------------
        */

        .no-draw {

            background: white;

            border-radius: 12px;

            padding: 50px;

            text-align: center;

            box-shadow:
                0 3px 12px rgba(
                    0,
                    0,
                    0,
                    0.08
                );

        }

        .no-draw-icon {

            font-size: 50px;

            margin-bottom: 15px;

        }

        .no-draw h3 {

            color: #993D86;

        }

        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 700px) {

            .container {

                padding: 15px;

            }

            .selection-grid {

                grid-template-columns: 1fr;

            }

            .page-header {

                padding: 20px;

            }

            .page-header h1 {

                font-size: 22px;

            }

            .draw-title {

                flex-direction: column;

                align-items: flex-start;

                gap: 10px;

            }

        }

    </style>

</head>

<body>

<div class="page-header">

    <h1>
        Tournament Draw
    </h1>

    <?php if ($tournamentName): ?>

        <p>
            <?= htmlspecialchars($tournamentName) ?>
        </p>

    <?php else: ?>

        <p>
            Select a tournament and event
        </p>

    <?php endif; ?>

</div>


<div class="container">

    <!--
    |--------------------------------------------------------------------------
    | Tournament / Event Selection
    |--------------------------------------------------------------------------
    -->

    <div class="selection-card">

        <div class="selection-grid">

            <div class="form-group">

                <label for="tournament">

                    Tournament

                </label>

                <select
                    id="tournament"
                    onchange="changeTournament(this.value)"
                >

                    <option value="">

                        -- Select Tournament --

                    </option>

                    <?php foreach ($tournaments as $tournament): ?>

                        <option
                            value="<?= $tournament['tournamentID'] ?>"
                            <?= (
                                $tournamentID ==
                                $tournament['tournamentID']
                            ) ? 'selected' : '' ?>
                        >

                            <?= htmlspecialchars(
                                $tournament['tournament_name']
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="form-group">

                <label for="event">

                    Event / Category

                </label>

                <select
                    id="event"
                    onchange="changeEvent(this.value)"
                    <?= $tournamentID <= 0 ? 'disabled' : '' ?>
                >

                    <option value="">

                        -- Select Event --

                    </option>

                    <?php foreach ($events as $eventName): ?>

                        <option
                            value="<?= htmlspecialchars($eventName) ?>"
                            <?= $event === $eventName
                                ? 'selected'
                                : '' ?>
                        >

                            <?= htmlspecialchars($eventName) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        </div>

    </div>


    <?php if (!empty($errorMessage)): ?>

        <div class="message error">

            <?= htmlspecialchars($errorMessage) ?>

        </div>

    <?php endif; ?>


    <?php if ($drawGenerated): ?>

        <div class="message success">

            Draw generated successfully.

        </div>

    <?php endif; ?>


    <?php if ($tournamentID > 0 && $event != ''): ?>

        <div class="draw-title">

            <h2>

                <?= htmlspecialchars($tournamentName) ?>

            </h2>

            <div class="event-label">

                <?= htmlspecialchars($event) ?>

            </div>

        </div>


        <!--
        |--------------------------------------------------------------------------
        | Champion
        |--------------------------------------------------------------------------
        -->

        <?php if ($champion): ?>

            <div class="champion-card">

                <div class="champion-icon">
                    🏆
                </div>

                <h3>
                    Champion
                </h3>

                <div class="champion-name">

                    <?= htmlspecialchars(
                        $champion['name']
                    ) ?>

                </div>

                <?php if ($champion['nationality']): ?>

                    <div class="champion-nationality">

                        <?= getCountryFlag(
                            $champion['nationality']
                        ) ?>

                        <?= htmlspecialchars(
                            $champion['nationality']
                        ) ?>

                        <?php if ($champion['seed']): ?>

                            · Seed #<?= htmlspecialchars(
                                $champion['seed']
                            ) ?>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

            </div>

        <?php endif; ?>


        <?php if (!empty($matchesByRound)): ?>

            <!--
            |--------------------------------------------------------------------------
            | Bracket
            |--------------------------------------------------------------------------
            -->

            <div class="bracket-wrapper">

                <div class="bracket">

                    <?php foreach (
                        $matchesByRound
                        as $roundNumber => $matches
                    ): ?>

                        <div class="round">

                            <div class="round-title">

                                <?= htmlspecialchars(
                                    $roundNames[$roundNumber]
                                    ?? "Round $roundNumber"
                                ) ?>

                            </div>


                            <div class="round-matches">

                                <?php foreach (
                                    $matches
                                    as $match
                                ): ?>

                                    <div class="match">

                                        <div class="match-header">

                                            <span>

                                                Match
                                                <?= htmlspecialchars(
                                                    $match['match_number']
                                                ) ?>

                                            </span>

                                            <span>

                                                #<?= htmlspecialchars(
                                                    $match['matchID']
                                                ) ?>

                                            </span>

                                        </div>


                                        <?php

                                        displayPlayer(
                                            $match,
                                            1
                                        );

                                        displayPlayer(
                                            $match,
                                            2
                                        );

                                        ?>


                                        <?php

                                        $status =
                                            strtolower(
                                                trim(
                                                    $match['match_status']
                                                    ?? ''
                                                )
                                            );

                                        ?>

                                        <?php if (
                                            $match['match_status']
                                        ): ?>

                                            <div
                                                class="
                                                    match-status
                                                    <?=
                                                        $status === 'completed'
                                                        ? 'status-completed'
                                                        : 'status-pending'
                                                    ?>
                                                "
                                            >

                                                <?= htmlspecialchars(
                                                    $match['match_status']
                                                ) ?>

                                            </div>

                                        <?php endif; ?>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        <?php else: ?>

            <div class="no-draw">

                <div class="no-draw-icon">
                    📋
                    <i class="fa-solid fa-clipboard" style="color: rgb(255, 212, 59);"></i>
                </div>

                <h3>
                    No Draw Generated
                </h3>

                <p>
                    There are no matches for this event.
                </p>

            </div>

        <?php endif; ?>

    <?php else: ?>

        <div class="no-draw">

            <div class="no-draw-icon">
                🎾
            </div>

            <h3>
                Select Tournament and Event
            </h3>

            <p>
                Select a tournament and event above
                to view the draw.
            </p>

        </div>

    <?php endif; ?>

</div>


<script>

/*
|--------------------------------------------------------------------------
| Change Tournament
|--------------------------------------------------------------------------
*/

function changeTournament(tournamentID)
{
    if (!tournamentID) {

        window.location.href = "draw.php";

        return;
    }

    window.location.href =
        "draw.php?tournamentID=" +
        encodeURIComponent(tournamentID);
}


/*
|--------------------------------------------------------------------------
| Change Event
|--------------------------------------------------------------------------
*/

function changeEvent(event)
{
    const tournamentID =
        document.getElementById(
            "tournament"
        ).value;

    if (!tournamentID || !event) {

        return;
    }

    window.location.href =
        "draw.php?tournamentID=" +
        encodeURIComponent(tournamentID) +
        "&event=" +
        encodeURIComponent(event);
}

</script>

</body>

</html>