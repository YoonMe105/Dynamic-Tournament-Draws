<?php

session_start();

require_once "../db.php";
require_once "automatic_seeding.php";
require_once "generate_draw.php";

/*
|--------------------------------------------------------------------------
| Basic Settings
|--------------------------------------------------------------------------
*/

$tournamentID = isset($_GET['tournamentID'])
    ? intval($_GET['tournamentID'])
    : 0;

$event = isset($_GET['event'])
    ? trim($_GET['event'])
    : '';

$errorMessage = '';
$successMessage = '';

/*
|--------------------------------------------------------------------------
| Validate Tournament
|--------------------------------------------------------------------------
*/

if ($tournamentID <= 0) {
    die("Invalid tournament ID.");
}

/*
|--------------------------------------------------------------------------
| Get Tournament Information
|--------------------------------------------------------------------------
*/

$tournament = null;

$stmt = $conn->prepare("
    SELECT
        tournamentID,
        tournament_name,
        tournament_description,
        tournament_startdate,
        tournament_enddate,
        tournament_location,
        tournament_type
    FROM tournament
    WHERE tournamentID = ?
    LIMIT 1
");

$stmt->bind_param("i", $tournamentID);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $tournament = $row;
}

$stmt->close();

if (!$tournament) {
    die("Tournament not found.");
}

$tournamentName = $tournament['tournament_name'];

/*
|--------------------------------------------------------------------------
| Get Tournament Categories
|--------------------------------------------------------------------------
|
| We use tournament_category instead of tournament_register so that
| categories configured for the tournament are shown even if there
| are currently no registrations.
|
*/

$events = [];

$stmt = $conn->prepare("
    SELECT category_name
    FROM tournament_category
    WHERE tournamentID = ?
    AND category_name IS NOT NULL
    AND category_name != ''
    ORDER BY category_name
");

$stmt->bind_param("i", $tournamentID);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $events[] = $row['category_name'];
}

$stmt->close();

/*
|--------------------------------------------------------------------------
| If Event Is Not Supplied
|--------------------------------------------------------------------------
|
| Automatically select the first category.
|
*/

if ($event === '' && !empty($events)) {
    $event = $events[0];
}

/*
|--------------------------------------------------------------------------
| Make Sure Event Belongs To Tournament
|--------------------------------------------------------------------------
*/

if ($event !== '' && !in_array($event, $events, true)) {
    $event = '';
    $errorMessage = "The selected event does not belong to this tournament.";
}

/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Preserve Event
    |--------------------------------------------------------------------------
    */

    $postEvent = isset($_POST['event'])
        ? trim($_POST['event'])
        : '';

    if ($postEvent !== '') {
        $event = $postEvent;
    }

    /*
    |--------------------------------------------------------------------------
    | Automatic Seed
    |--------------------------------------------------------------------------
    */

    if ($action === 'automatic_seed') {

        if ($event === '') {

            $errorMessage = "Please select an event first.";

        } elseif (!in_array($event, $events, true)) {

            $errorMessage = "Invalid event.";

        } else {

            try {

                $seededPlayers = automaticSeedPlayers(
                    $conn,
                    $tournamentID,
                    $event
                );

                if (empty($seededPlayers)) {

                    $errorMessage =
                        "There are not enough registered players to seed this event.";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Check Existing Draw
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $conn->prepare("
                        SELECT COUNT(*) AS total
                        FROM matches
                        WHERE tournamentID = ?
                        AND category_registered = ?
                    ");

                    $stmt->bind_param(
                        "is",
                        $tournamentID,
                        $event
                    );

                    $stmt->execute();

                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();

                    $matchCount = intval($row['total']);

                    $stmt->close();

                    if ($matchCount > 0) {

                        $successMessage =
                            "Players were reseeded successfully. The existing draw was not regenerated.";

                    } else {

                        $successMessage =
                            "Players were seeded successfully.";
                    }
                }

            } catch (Throwable $e) {

                $errorMessage = $e->getMessage();
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Generate Draw
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'generate_draw') {

        if ($event === '') {

            $errorMessage = "Please select an event first.";

        } elseif (!in_array($event, $events, true)) {

            $errorMessage = "Invalid event.";

        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | Check Existing Draw
                |--------------------------------------------------------------------------
                */

                $stmt = $conn->prepare("
                    SELECT COUNT(*) AS total
                    FROM matches
                    WHERE tournamentID = ?
                    AND category_registered = ?
                ");

                $stmt->bind_param(
                    "is",
                    $tournamentID,
                    $event
                );

                $stmt->execute();

                $result = $stmt->get_result();
                $row = $result->fetch_assoc();

                $matchCount = intval($row['total']);

                $stmt->close();

                if ($matchCount > 0) {

                    $errorMessage =
                        "A draw already exists for this event. The existing draw was not replaced.";

                } else {

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

                    if (count($seededPlayers) < 2) {

                        $errorMessage =
                            "At least 2 registered players are required to generate a draw.";

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Generate Tournament Draw
                        |--------------------------------------------------------------------------
                        */

                        $drawResult = generateTournamentDraw(
                            $conn,
                            $tournamentID,
                            $event,
                            $seededPlayers
                        );

                        if ($drawResult === false) {

                            $errorMessage =
                                "The draw could not be generated.";

                        } else {

                            $successMessage =
                                "Draw generated successfully.";

                        }
                    }
                }

            } catch (Throwable $e) {

                $errorMessage = $e->getMessage();
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Save Match Player
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'save_match') {

        $matchID = isset($_POST['matchID'])
            ? intval($_POST['matchID'])
            : 0;

        $player1ID = isset($_POST['player1ID'])
            ? trim($_POST['player1ID'])
            : '';

        $player2ID = isset($_POST['player2ID'])
            ? trim($_POST['player2ID'])
            : '';

        if ($matchID <= 0) {

            $errorMessage = "Invalid match.";

        } elseif ($event === '') {

            $errorMessage = "Invalid event.";

        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | Verify Match Belongs To Tournament/Event
                |--------------------------------------------------------------------------
                */

                $stmt = $conn->prepare("
                    SELECT
                        matchID,
                        round_number,
                        match_status,
                        winnerID,
                        player1ID,
                        player2ID
                    FROM matches
                    WHERE matchID = ?
                    AND tournamentID = ?
                    AND category_registered = ?
                    LIMIT 1
                ");

                $stmt->bind_param(
                    "iis",
                    $matchID,
                    $tournamentID,
                    $event
                );

                $stmt->execute();

                $result = $stmt->get_result();

                $match = $result->fetch_assoc();

                $stmt->close();

                if (!$match) {

                    throw new Exception(
                        "Match not found for this tournament and event."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Do Not Modify Completed Matches
                |--------------------------------------------------------------------------
                */

                $matchStatus = strtolower(
                    trim($match['match_status'] ?? '')
                );

                if (
                    $matchStatus === 'completed' ||
                    !empty($match['winnerID'])
                ) {

                    throw new Exception(
                        "A completed match cannot be edited."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Player 1 / Player 2 Cannot Be The Same
                |--------------------------------------------------------------------------
                */

                if (
                    $player1ID !== '' &&
                    $player2ID !== '' &&
                    $player1ID === $player2ID
                ) {

                    throw new Exception(
                        "Player 1 and Player 2 cannot be the same player."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Verify Players Are Registered In This Event
                |--------------------------------------------------------------------------
                */

                $registeredPlayers = [];

                $stmt = $conn->prepare("
                    SELECT DISTINCT playerID
                    FROM tournament_register
                    WHERE tournamentID = ?
                    AND category_registered = ?
                    AND playerID IS NOT NULL
                    AND playerID != ''
                ");

                $stmt->bind_param(
                    "is",
                    $tournamentID,
                    $event
                );

                $stmt->execute();

                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {

                    $registeredPlayers[
                        (string)$row['playerID']
                    ] = true;
                }

                $stmt->close();

                /*
                |--------------------------------------------------------------------------
                | Validate Player 1
                |--------------------------------------------------------------------------
                */

                if (
                    $player1ID !== '' &&
                    !isset($registeredPlayers[$player1ID])
                ) {

                    throw new Exception(
                        "Player 1 is not registered for this event."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Validate Player 2
                |--------------------------------------------------------------------------
                */

                if (
                    $player2ID !== '' &&
                    !isset($registeredPlayers[$player2ID])
                ) {

                    throw new Exception(
                        "Player 2 is not registered for this event."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Get Seeds
                |--------------------------------------------------------------------------
                */

                $player1Seed = null;
                $player2Seed = null;

                if ($player1ID !== '') {

                    $stmt = $conn->prepare("
                        SELECT seed_number
                        FROM tournament_register
                        WHERE tournamentID = ?
                        AND category_registered = ?
                        AND playerID = ?
                        LIMIT 1
                    ");

                    $stmt->bind_param(
                        "iss",
                        $tournamentID,
                        $event,
                        $player1ID
                    );

                    $stmt->execute();

                    $result = $stmt->get_result();

                    if ($row = $result->fetch_assoc()) {

                        if (
                            $row['seed_number'] !== null &&
                            $row['seed_number'] !== ''
                        ) {

                            $player1Seed = intval(
                                $row['seed_number']
                            );
                        }
                    }

                    $stmt->close();
                }

                if ($player2ID !== '') {

                    $stmt = $conn->prepare("
                        SELECT seed_number
                        FROM tournament_register
                        WHERE tournamentID = ?
                        AND category_registered = ?
                        AND playerID = ?
                        LIMIT 1
                    ");

                    $stmt->bind_param(
                        "iss",
                        $tournamentID,
                        $event,
                        $player2ID
                    );

                    $stmt->execute();

                    $result = $stmt->get_result();

                    if ($row = $result->fetch_assoc()) {

                        if (
                            $row['seed_number'] !== null &&
                            $row['seed_number'] !== ''
                        ) {

                            $player2Seed = intval(
                                $row['seed_number']
                            );
                        }
                    }

                    $stmt->close();
                }

                /*
                |--------------------------------------------------------------------------
                | Update Match
                |--------------------------------------------------------------------------
                */

                $stmt = $conn->prepare("
                    UPDATE matches
                    SET
                        player1ID = NULLIF(?, ''),
                        player2ID = NULLIF(?, ''),
                        player1_seed = ?,
                        player2_seed = ?,
                        player1_score = NULL,
                        player2_score = NULL
                    WHERE matchID = ?
                    AND tournamentID = ?
                    AND category_registered = ?
                ");

                $stmt->bind_param(
                    "ssiiiss",
                    $player1ID,
                    $player2ID,
                    $player1Seed,
                    $player2Seed,
                    $matchID,
                    $tournamentID,
                    $event
                );

                if (!$stmt->execute()) {

                    throw new Exception(
                        "Failed to save match: " .
                        $stmt->error
                    );
                }

                $stmt->close();

                $successMessage =
                    "Match players updated successfully.";

            } catch (Throwable $e) {

                $errorMessage = $e->getMessage();
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Get Registered Players For Event
|--------------------------------------------------------------------------
*/

$registeredPlayers = [];

if ($event !== '') {

    /*
    |--------------------------------------------------------------------------
    | Use tournament_register first
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT DISTINCT
            tr.playerID,
            tr.seed_number,
            p.player_full_name,
            p.player_nationality
        FROM tournament_register tr
        LEFT JOIN players p
            ON CONVERT(p.playerID USING utf8mb4)
             = CONVERT(tr.playerID USING utf8mb4)
        WHERE tr.tournamentID = ?
        AND tr.category_registered = ?
        AND tr.playerID IS NOT NULL
        AND tr.playerID != ''
        ORDER BY
            CASE
                WHEN tr.seed_number IS NULL THEN 1
                ELSE 0
            END,
            tr.seed_number ASC,
            p.player_full_name ASC,
            tr.playerID ASC
    ");

    $stmt->bind_param(
        "is",
        $tournamentID,
        $event
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $registeredPlayers[] = $row;
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Get Matches
|--------------------------------------------------------------------------
*/

$matchesByRound = [];
$roundNames = [];

if ($tournamentID > 0 && $event !== '') {

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
            ON CONVERT(m.player1ID USING utf8mb4)
             = CONVERT(p1.playerID USING utf8mb4)

        LEFT JOIN players p2
            ON CONVERT(m.player2ID USING utf8mb4)
             = CONVERT(p2.playerID USING utf8mb4)

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

    $lastRoundNumber = max(
        array_keys($matchesByRound)
    );

    foreach (
        $matchesByRound[$lastRoundNumber]
        as $finalMatch
    ) {

        if (!empty($finalMatch['winnerID'])) {

            if (
                (string)$finalMatch['winnerID'] ===
                (string)$finalMatch['player1ID']
            ) {

                $champion = [
                    'name' =>
                        $finalMatch['player1_name'],
                    'nationality' =>
                        $finalMatch['player1_nationality'],
                    'seed' =>
                        $finalMatch['player1_seed']
                ];

            } elseif (
                (string)$finalMatch['winnerID'] ===
                (string)$finalMatch['player2ID']
            ) {

                $champion = [
                    'name' =>
                        $finalMatch['player2_name'],
                    'nationality' =>
                        $finalMatch['player2_nationality'],
                    'seed' =>
                        $finalMatch['player2_seed']
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
    $nameKey =
        "player{$playerNumber}_name";

    $nationalityKey =
        "player{$playerNumber}_nationality";

    $seedKey =
        "player{$playerNumber}_seed";

    $scoreKey =
        "player{$playerNumber}_score";

    $idKey =
        "player{$playerNumber}ID";

    $name =
        $match[$nameKey] ?? '';

    $nationality =
        $match[$nationalityKey] ?? '';

    $seed =
        $match[$seedKey] ?? null;

    $score =
        $match[$scoreKey] ?? null;

    $playerID =
        $match[$idKey] ?? '';

    $winner = (
        !empty($match['winnerID']) &&
        (string)$match['winnerID'] ===
        (string)$playerID
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

            <?php if (
                $seed !== null &&
                $seed !== ''
            ): ?>

                <span class="seed">
                    <?= htmlspecialchars($seed) ?>
                </span>

            <?php endif; ?>

            <?php if (
                $score !== null &&
                $score !== ''
            ): ?>

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

    <link href="./assets/style.css" rel="stylesheet" type="text/css">
    <link href="./assets/make_draw_style.css" rel="stylesheet" type="text/css">
</head>

<body>
    
    <?php require_once 'admin_navbar.php'; ?>


    <main>
        <div class="page-header">

            <h1>
                Tournament Draw
            </h1>

            <p>
                <?= htmlspecialchars($tournamentName) ?>
            </p>

        </div>


        <div class="container">

            <div class="selection-card">

            <div class="selection-grid">

                <div class="form-group">

                    <label for="event">
                        Event / Category
                    </label>

                    <select
                        id="event"
                        onchange="changeEvent(this.value)"
                        <?= empty($events) ? 'disabled' : '' ?>
                    >

                        <?php if (empty($events)): ?>

                            <option value="">
                                No categories found
                            </option>

                        <?php else: ?>

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

                        <?php endif; ?>

                    </select>

                </div>

            </div>


            <?php if ($event !== ''): ?>

                <div class="draw-actions">

                    <form method="POST">

                        <input type="hidden" name="action" value="automatic_seed" >

                        <input type="hidden" name="event" value="<?= htmlspecialchars($event) ?>" >

                        <button type="submit" class="action-btn seed-btn" >
                            🌱 Automatic Seeding
                        </button>

                    </form>


                    <form method="POST" onsubmit="
                            return confirm(
                                'Generate the draw for this event?'
                            );">

                        <input type="hidden" name="action" value="generate_draw" >

                        <input type="hidden" name="event" value="<?= htmlspecialchars($event) ?>" >

                        <button type="submit"class="action-btn generate-btn" >
                            🎯 Generate Draw
                        </button>

                    </form>

                </div>

            <?php endif; ?>

        </div>


        <?php if ($errorMessage !== ''): ?>

            <div class="message error">

                <?= htmlspecialchars($errorMessage) ?>

            </div>

        <?php endif; ?>


        <?php if ($successMessage !== ''): ?>

            <div class="message success">

                <?= htmlspecialchars($successMessage) ?>

            </div>

        <?php endif; ?>


        <?php if ($event !== ''): ?>

            <div class="draw-title">

                <h2>
                    <?= htmlspecialchars($tournamentName) ?>
                </h2>

                <div class="event-label">
                    <?= htmlspecialchars($event) ?>
                </div>

            </div>

            <?php if (!empty($registeredPlayers)): ?>

                <div class="player-summary">

                    <div class="player-summary-header">

                        <h3>
                            Registered Players
                        </h3>

                        <span class="player-count">
                            <?= count($registeredPlayers) ?> Players
                        </span>

                    </div>


                    <div class="seed-list">

                        <?php foreach ($registeredPlayers as $registeredPlayer ): ?>

                            <div class="seed-player">

                                <?php if ( $registeredPlayer['seed_number'] !== null && $registeredPlayer['seed_number'] !== '' ): ?>

                                    <span class="seed-number">
                                        <?= htmlspecialchars( $registeredPlayer['seed_number'] ) ?>
                                    </span>

                                <?php endif; ?>

                                <span>
                                    <?= htmlspecialchars($registeredPlayer['player_full_name'] ?: $registeredPlayer['playerID'] ) ?>
                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endif; ?>

            <?php if ($champion): ?>

                <div class="champion-card">

                    <div class="champion-icon">
                        🏆
                    </div>

                    <h3>Champion </h3>

                    <div class="champion-name">
                        <?= htmlspecialchars( $champion['name'] ) ?>
                    </div>


                    <?php if ($champion['nationality']): ?>

                        <div class="champion-nationality">

                            <?= getCountryFlag($champion['nationality']) ?>

                            <?= htmlspecialchars($champion['nationality']) ?>


                            <?php if ($champion['seed'] !== null && $champion['seed'] !== '' ): ?>
                                <?= htmlspecialchars($champion['seed']) ?>
                            <?php endif; ?>

                        </div>

                    <?php endif; ?>

                </div>

            <?php endif; ?>


            <?php if (!empty($matchesByRound)): ?>

                <div class="bracket-wrapper">

                    <div class="bracket">

                        <?php foreach ($matchesByRound as $roundNumber => $matches): ?>

                            <div class="round">

                                <div class="round-title">
                                    <?= htmlspecialchars($roundNames[$roundNumber]?? "Round $roundNumber") ?>
                                </div>


                                <div class="round-matches">

                                    <?php foreach ($matches as $match): ?>

                                        <?php

                                            $status = strtolower(trim($match['match_status'] ?? ''));

                                            $completed = $status === 'completed' || !empty($match['winnerID']);
                                        ?>

                                        <div class="match">

                                            <!-- Match Header -->

                                            <div class="match-header">

                                                <div class="match-header-left" >
                                                    <span> Match <?= htmlspecialchars($match['match_number'] ) ?></span>
                                                    <span><?= htmlspecialchars($match['matchID'] ) ?></span>
                                                </div>


                                                <?php if (!$completed): ?>

                                                    <button type="button" class="edit-match-btn" onclick="
                                                            toggleEdit(
                                                                <?= intval(
                                                                    $match['matchID']
                                                                ) ?>
                                                            );
                                                        ">✏ Edit</button>

                                                <?php else: ?>

                                                    <span style="font-size:10px;color:#aaa;">Locked</span>

                                                <?php endif; ?>
                                            </div>


                                            <!-- Player 1 -->

                                            <?php displayPlayer($match, 1); ?>


                                            <!-- Player 2 -->

                                            <?php displayPlayer( $match, 2 ); ?>


                                            <!-- Match Status -->

                                            <?php if (!empty($match['match_status'] ) ): ?>

                                                <div
                                                    class="match-status
                                                        <?= $status === 'completed'
                                                            ? 'status-completed'
                                                            : 'status-pending'
                                                        ?>
                                                    ">

                                                    <?= htmlspecialchars($match['match_status']) ?>

                                                </div>

                                            <?php endif; ?>

                                            <?php if(!$completed): ?>

                                                <div class="edit-panel" id="edit-panel-<?= intval($match['matchID'] ) ?>" >

                                                    <form method="POST" >

                                                        <input type="hidden" name="action" value="save_match" >

                                                        <input type="hidden" name="matchID" value="<?= intval($match['matchID']) ?>" >

                                                        <input type="hidden" name="event" value="<?= htmlspecialchars() ?>" >


                                                        <!-- Player 1 -->

                                                        <div class="edit-group" >

                                                            <label> Player 1 </label>

                                                            <select name="player1ID">
                                                                <option value="" >
                                                                    -- BYE / Empty --
                                                                </option>

                                                                <?php foreach ( $registeredPlayers as $rp ): ?>

                                                                    <option value="<?= htmlspecialchars( $rp['playerID'] ) ?>"
                                                                        <?= ( (string)$match['player1ID'] === (string)$rp['playerID'] ) ? 'selected' : '' ?> >

                                                                        <?php if ( $rp['seed_number'] !== null && $rp['seed_number'] !== '' ): ?>
                                                                        
                                                                            <?= htmlspecialchars( $rp['seed_number'] ) ?>
                                                                        <?php endif; ?>

                                                                        <?= htmlspecialchars($rp['player_full_name'] ? : $rp['playerID'] ) ?>

                                                                    </option>

                                                                <?php endforeach; ?>

                                                            </select>

                                                        </div>


                                                        <!-- Player 2 -->

                                                        <div class="edit-group" >

                                                            <label> Player 2 </label>

                                                            <select name="player2ID">

                                                                <option value="" >
                                                                    -- BYE / Empty --
                                                                </option>

                                                                <?php foreach ($registeredPlayers as $rp): ?>

                                                                    <option
                                                                        value="<?= htmlspecialchars(
                                                                            $rp['playerID']
                                                                        ) ?>"
                                                                        <?= (
                                                                            (string)$match['player2ID'] ===
                                                                            (string)$rp['playerID']
                                                                        )
                                                                            ? 'selected'
                                                                            : ''
                                                                        ?>
                                                                    >

                                                                        <?php if (
                                                                            $rp['seed_number']
                                                                            !== null &&
                                                                            $rp['seed_number']
                                                                            !== ''
                                                                        ): ?>

                                                                            #
                                                                            <?= htmlspecialchars(
                                                                                $rp['seed_number']
                                                                            ) ?>

                                                                            -

                                                                        <?php endif; ?>

                                                                        <?= htmlspecialchars(
                                                                            $rp['player_full_name']
                                                                            ?: $rp['playerID']
                                                                        ) ?>

                                                                    </option>

                                                                <?php endforeach; ?>

                                                            </select>

                                                        </div>


                                                        <div class="edit-actions" >

                                                            <button type="submit" class="save-edit-btn" >
                                                                Save Changes
                                                            </button>


                                                            <button type="button" class="cancel-edit-btn" onclick=" toggleEdit( <?= intval( $match['matchID'] ) ?> ); " >
                                                                Cancel
                                                            </button>

                                                        </div>

                                                    </form>

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
                    </div>

                    <h3>
                        No Draw Generated
                    </h3>

                    <?php if (empty($registeredPlayers)): ?>

                        <p>
                            There are no registered players for this event.
                        </p>

                    <?php else: ?>

                        <p>
                            <?= count($registeredPlayers) ?>
                            registered player(s) found.
                        </p>

                        <p>
                            Use <strong>Automatic Seeding</strong>
                            and then <strong>Generate Draw</strong>
                            above.
                        </p>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        <?php else: ?>

            <div class="no-draw">

                <div class="no-draw-icon">
                    🎾
                </div>

                <h3>
                    Select Event
                </h3>

                <p>
                    Select an event above to view the tournament draw.
                </p>

            </div>

        <?php endif; ?>

        </div>
    </main>


    <script>

    function changeEvent(event)
    {
        if (!event) {
            return;
        }

        window.location.href =
            "make_draw.php?tournamentID=" +
            encodeURIComponent(
                <?= json_encode($tournamentID) ?>
            ) +
            "&event=" +
            encodeURIComponent(event);
    }

    function toggleEdit(matchID)
    {
        const panel =
            document.getElementById(
                "edit-panel-" + matchID
            );

        if (!panel) {
            return;
        }

        panel.classList.toggle("open");
    }

    document.addEventListener(
        "change",
        function(event)
        {
            if (
                !event.target.matches(
                    '.edit-panel select'
                )
            ) {
                return;
            }

            const panel =
                event.target.closest(
                    '.edit-panel'
                );

            if (!panel) {
                return;
            }

            const selects =
                panel.querySelectorAll(
                    'select[name="player1ID"], select[name="player2ID"]'
                );

            if (selects.length !== 2) {
                return;
            }

            const player1 =
                selects[0].value;

            const player2 =
                selects[1].value;

            if (
                player1 !== '' &&
                player2 !== '' &&
                player1 === player2
            ) {

                alert(
                    "Player 1 and Player 2 cannot be the same player."
                );

                event.target.value = '';
            }
        }
    );

    </script>

</body>

</html>