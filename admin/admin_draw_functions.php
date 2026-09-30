<?php

/*
|--------------------------------------------------------------------------
| Draw Helpers (knockout draw)
|--------------------------------------------------------------------------
| Every player with a seed number is placed as a seed, squash / WSF style:
|   - seed 1 at the top, seed 2 at the bottom
|   - seeds 3-4 drawn at random into the two remaining quarter slots
|   - seeds 5-8 drawn at random into the eighth slots, and so on
| Byes go opposite seed 1, seed 2, ... Players without a seed number are
| drawn at random into the slots that are left.
*/


// Smallest power of two that fits all players (at least 2)
function drawSize($players)
{
    $size = 2;

    while ($size < $players) {
        $size *= 2;
    }

    return $size;
}


function roundName($playersInRound)
{
    switch ($playersInRound) {
        case 2:
            return 'Final';
        case 4:
            return 'Semi Final';
        case 8:
            return 'Quarter Final';
        default:
            return 'Round of ' . $playersInRound;
    }
}


/*
| Standard bracket order: the draw position (1-based) for each seed rank.
| For a draw of 8 the positions from the top are ranks 1, 8, 5, 4, 3, 6, 7, 2,
| so rank r always meets rank (size + 1 - r) in round 1.
*/
function seedRankPositions($size)
{
    $order = [1];

    while (count($order) < $size) {

        $next = count($order) * 2;
        $expanded = [];

        // Every second pair is flipped so the higher rank stays on the outside
        foreach ($order as $index => $rank) {
            if ($index % 2 === 0) {
                $expanded[] = $rank;
                $expanded[] = $next + 1 - $rank;
            } else {
                $expanded[] = $next + 1 - $rank;
                $expanded[] = $rank;
            }
        }

        $order = $expanded;
    }

    // Flip it: rank => position
    $positions = [];

    foreach ($order as $index => $rank) {
        $positions[$rank] = $index + 1;
    }

    return $positions;
}


/*
| Places the players into draw positions.
|
| $seeded   players with a seed number, in seed order (seed 1 first)
| $unseeded players without a seed number (any order; they are shuffled)
|
| Returns [position => player or null for a bye] for positions 1..size.
| Each placed player gets a 'draw_seed' key (their seed number, or null).
*/
function buildDrawPositions(array $seeded, array $unseeded)
{
    $seeded = array_values($seeded);

    $total = count($seeded) + count($unseeded);
    $size = drawSize($total);
    $half = intdiv($size, 2);

    $rankPosition = seedRankPositions($size);
    $slots = array_fill(1, $size, null);
    $taken = [];


    // Top half of the ranks: 1 and 2 fixed, then each group (3-4, 5-8 ...)
    // shuffled within its own slots. Every rank gets a slot, seeded or not,
    // so the byes below know where each rank sits.
    $rankSlot = [];
    $groupStart = 1;

    while ($groupStart <= $half) {

        // Groups: 1, 2, 3-4, 5-8, 9-16 ...
        $groupEnd = $groupStart <= 2 ? $groupStart : ($groupStart - 1) * 2;

        $groupPositions = [];

        for ($rank = $groupStart; $rank <= $groupEnd; $rank++) {
            $groupPositions[] = $rankPosition[$rank];
        }

        shuffle($groupPositions);

        for ($rank = $groupStart; $rank <= $groupEnd; $rank++) {
            $rankSlot[$rank] = array_shift($groupPositions);
        }

        $groupStart = $groupEnd + 1;
    }

    foreach ($seeded as $index => $player) {

        $rank = $index + 1;

        if ($rank > $half) {
            break;
        }

        $player['draw_seed'] = $player['seed_number'] ?? $rank;

        $slots[$rankSlot[$rank]] = $player;
        $taken[$rankSlot[$rank]] = true;
    }


    // Byes: opposite rank 1, rank 2, ... (their opponents are all in the bottom-half ranks)
    $byes = $size - $total;

    for ($rank = 1; $rank <= $byes; $rank++) {

        $position = $rankSlot[$rank];
        $opponent = $position % 2 === 1 ? $position + 1 : $position - 1;

        $taken[$opponent] = true;
    }


    // Remaining seeds (bottom half of the ranks): drawn at random into the
    // free bottom-half slots
    $bottomSlots = [];

    for ($rank = $half + 1; $rank <= $size; $rank++) {
        if (!isset($taken[$rankPosition[$rank]])) {
            $bottomSlots[] = $rankPosition[$rank];
        }
    }

    shuffle($bottomSlots);

    foreach (array_slice($seeded, $half) as $player) {

        $position = array_shift($bottomSlots);

        $player['draw_seed'] = $player['seed_number'];

        $slots[$position] = $player;
        $taken[$position] = true;
    }


    // Players without a seed number: random order into the free slots
    shuffle($unseeded);

    for ($position = 1; $position <= $size; $position++) {

        if (isset($taken[$position])) {
            continue;
        }

        $player = array_shift($unseeded);

        if ($player === null) {
            continue;   // a bye
        }

        $player['draw_seed'] = null;

        $slots[$position] = $player;
    }

    return $slots;
}

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

// Endorsed players in a category, split into seeded (by seed number) and unseeded
function drawPlayers($conn, $tournamentID, $category)
{
    $stmt = $conn->prepare("
        SELECT
            tr.registrationID,
            tr.seed_number,
            p.playerID,
            p.player_full_name,
            p.player_nationality
        FROM tournament_register tr
        JOIN players p
            ON p.playerID = tr.playerID
        WHERE tr.tournamentID = ?
        AND tr.category_registered = ?
        AND UPPER(TRIM(tr.endorsement)) = 'ENDORSED'
        ORDER BY
            CASE WHEN tr.seed_number IS NULL THEN 1 ELSE 0 END,
            tr.seed_number ASC,
            tr.registrationID ASC
    ");

    $stmt->bind_param("is", $tournamentID, $category);
    $stmt->execute();

    $result = $stmt->get_result();

    $seeded = [];
    $unseeded = [];

    while ($row = $result->fetch_assoc()) {
        if ($row['seed_number'] !== null) {
            $seeded[] = $row;
        } else {
            $unseeded[] = $row;
        }
    }

    $stmt->close();

    return [$seeded, $unseeded];
}


function drawExists($conn, $tournamentID, $category)
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM matches
        WHERE tournamentID = ?
        AND category_registered = ?
    ");

    $stmt->bind_param("is", $tournamentID, $category);
    $stmt->execute();

    $total = (int)$stmt->get_result()->fetch_assoc()['total'];

    $stmt->close();

    return $total > 0;
}


function deleteDraw($conn, $tournamentID, $category)
{
    $stmt = $conn->prepare("
        DELETE FROM matches
        WHERE tournamentID = ?
        AND category_registered = ?
    ");

    $stmt->bind_param("is", $tournamentID, $category);
    $stmt->execute();
    $stmt->close();
}


/*
| Makes (or remakes) the knockout draw for one category.
| Every round is stored; later rounds start empty. Byes are completed
| straight away and the player moves into round 2.
|
| Returns the number of players in the draw.
*/
function generateDraw($conn, $tournamentID, $category)
{
    list($seeded, $unseeded) = drawPlayers($conn, $tournamentID, $category);

    $total = count($seeded) + count($unseeded);

    if ($total < 2) {
        throw new Exception('At least 2 endorsed players are needed to make a draw.');
    }

    $slots = buildDrawPositions($seeded, $unseeded);
    $size = count($slots);
    $rounds = (int)log($size, 2);

    $conn->begin_transaction();

    try {

        deleteDraw($conn, $tournamentID, $category);

        $insert = $conn->prepare("
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
                winnerID,
                next_matchID,
                next_match_position,
                match_status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // Final first, so each match knows the ID of the match it feeds into
        $matchIDs = [];

        for ($round = $rounds; $round >= 1; $round--) {

            $matchesInRound = $size / pow(2, $round);
            $name = roundName($matchesInRound * 2);

            for ($number = 1; $number <= $matchesInRound; $number++) {

                $player1 = null;
                $player2 = null;
                $seed1 = null;
                $seed2 = null;
                $winner = null;
                $status = 'Pending';

                if ($round === 1) {

                    $top = $slots[$number * 2 - 1];
                    $bottom = $slots[$number * 2];

                    $player1 = $top['playerID'] ?? null;
                    $player2 = $bottom['playerID'] ?? null;
                    $seed1 = $top['draw_seed'] ?? null;
                    $seed2 = $bottom['draw_seed'] ?? null;

                    // Bye: the player goes through
                    if ($player1 === null || $player2 === null) {
                        $winner = $player1 ?? $player2;
                        $status = 'Completed';
                    }
                }

                $nextID = $round < $rounds ? $matchIDs[$round + 1][(int)ceil($number / 2)] : null;
                $nextPosition = $round < $rounds ? ($number % 2 === 1 ? 'player1' : 'player2') : null;

                $insert->bind_param(
                    "isisissiisiss",
                    $tournamentID,
                    $category,
                    $round,
                    $name,
                    $number,
                    $player1,
                    $player2,
                    $seed1,
                    $seed2,
                    $winner,
                    $nextID,
                    $nextPosition,
                    $status
                );

                $insert->execute();

                $matchIDs[$round][$number] = $conn->insert_id;

                // Move the bye winner into round 2
                if ($winner !== null && $nextID !== null) {

                    $winnerSeed = $player1 !== null ? $seed1 : $seed2;
                    $column = $nextPosition === 'player1' ? 'player1' : 'player2';

                    $advance = $conn->prepare("
                        UPDATE matches
                        SET {$column}ID = ?, {$column}_seed = ?
                        WHERE matchID = ?
                    ");

                    $advance->bind_param("sii", $winner, $winnerSeed, $nextID);
                    $advance->execute();
                    $advance->close();
                }
            }
        }

        $insert->close();

        $conn->commit();

    } catch (Throwable $e) {

        $conn->rollback();
        throw $e;
    }

    return $total;
}


// All matches of a category grouped by round: [round => [match_number => match]]
function loadDraw($conn, $tournamentID, $category)
{
    $stmt = $conn->prepare("
        SELECT
            m.*,
            p1.player_full_name AS player1_name,
            p1.player_nationality AS player1_nationality,
            p2.player_full_name AS player2_name,
            p2.player_nationality AS player2_nationality
        FROM matches m
        LEFT JOIN players p1
            ON p1.playerID = m.player1ID
        LEFT JOIN players p2
            ON p2.playerID = m.player2ID
        WHERE m.tournamentID = ?
        AND m.category_registered = ?
        ORDER BY m.round_number ASC, m.match_number ASC
    ");

    $stmt->bind_param("is", $tournamentID, $category);
    $stmt->execute();

    $result = $stmt->get_result();

    $rounds = [];

    while ($match = $result->fetch_assoc()) {
        $rounds[(int)$match['round_number']][(int)$match['match_number']] = $match;
    }

    $stmt->close();

    return $rounds;
}


/*
|--------------------------------------------------------------------------
| Bracket display (draw page and report)
|--------------------------------------------------------------------------
*/

// Short country code shown next to the name (squash uses MAS for Malaysia)
function countryCode($nationality)
{
    $codes = [
        'MALAYSIAN' => 'MAS', 'SINGAPOREAN' => 'SGP', 'AUSTRALIAN' => 'AUS',
        'CHINESE' => 'CHN', 'HONG KONG' => 'HKG', 'JAPANESE' => 'JPN',
        'KOREAN' => 'KOR', 'SOUTH KOREAN' => 'KOR', 'INDIAN' => 'IND',
        'INDONESIAN' => 'INA', 'PHILIPPINE' => 'PHI', 'FILIPINO' => 'PHI',
        'THAI' => 'THA', 'CANADIAN' => 'CAN', 'AMERICAN' => 'USA',
        'BRITISH' => 'GBR', 'UNITED KINGDOM' => 'GBR', 'UNITED STATES' => 'USA', 'ENGLISH' => 'ENG', 'NEW ZEALANDER' => 'NZL',
        'PAKISTANI' => 'PAK', 'SRI LANKAN' => 'SRI', 'EGYPTIAN' => 'EGY',
        'TAIWANESE' => 'TPE', 'MACANESE' => 'MAC'
    ];

    $nationality = strtoupper(trim($nationality));

    return $codes[$nationality] ?? substr($nationality, 0, 3);
}


function drawPlayerLine($match, $number)
{
    $id = $match['player' . $number . 'ID'];
    $name = $match['player' . $number . '_name'];
    $seed = $match['player' . $number . '_seed'];
    $nationality = $match['player' . $number . '_nationality'];

    $isWinner = $id !== null && $match['winnerID'] === $id;

    // Round 1 slot with no player = bye; later rounds = still to be decided
    if ($id === null) {

        $label = (int)$match['round_number'] === 1 ? 'Bye' : '';

        ?>
        <div class="draw-player empty">
            <span class="player-name"><?= $label ?></span>
        </div>
        <?php

        return;
    }

    ?>
    <div class="draw-player <?= $isWinner ? 'winner' : '' ?>">

        <?php if ($seed !== null): ?>
            <span class="seed">[<?= (int)$seed ?>]</span>
        <?php endif; ?>

        <span class="player-name" title="<?= htmlspecialchars($name ?? $id) ?>">
            <?= htmlspecialchars($name ?? $id) ?>
        </span>

        <?php if (!empty($nationality)): ?>
            <span class="country"><?= htmlspecialchars(countryCode($nationality)) ?></span>
        <?php endif; ?>

    </div>
    <?php
}


// File name for the bracket image, e.g. Draw_Testing_again_2_BU15.png
function bracketFileName($tournament, $category)
{
    $name = preg_replace('/[^A-Za-z0-9]+/', '_', $tournament['tournament_name'] . ' ' . $category);

    return 'Draw_' . trim($name, '_') . '.png';
}


// The whole bracket: one column per round, matches joined in pairs
function renderBracket($draw)
{
    ?>
        <div class="bracket">

            <?php foreach ($draw as $roundNumber => $matches): ?>

                <?php $first = reset($matches); ?>

                <div class="round">

                    <h3 class="round-title"><?= htmlspecialchars($first['round_name']) ?></h3>

                    <div class="round-matches">

                        <?php foreach (array_chunk($matches, 2) as $pair): ?>

                            <div class="match-pair <?= count($pair) === 2 ? 'joined' : '' ?>">

                                <?php foreach ($pair as $match): ?>

                                    <div class="match-slot">

                                        <div class="match">

                                            <?php drawPlayerLine($match, 1); ?>
                                            <?php drawPlayerLine($match, 2); ?>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>
    <?php
}
