<?php

/*
|--------------------------------------------------------------------------
| Draw Helpers (knockout draw)
|--------------------------------------------------------------------------
| Standard seeded draw: every player with a seed number goes to that seed's
| fixed place in the bracket, so the best seeds meet the weakest opponents:
|   - seed 1 at the top, seed 2 at the bottom
|   - quarter-finals 1 v 8, 4 v 5, 3 v 6, 2 v 7 (each pair adds up to 9)
|   - round 1 of a 16 draw: 1 v 16, 8 v 9, 5 v 12, 4 v 13 ... (adds up to 17)
| Byes go opposite seed 1, seed 2, ... Players without a seed number are
| drawn at random into the places that are left.
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

    // rank => position: rank r meets rank (size + 1 - r) in round 1
    $rankPosition = seedRankPositions($size);
    $slots = array_fill(1, $size, null);


    // Seeds: rank 1 = first seed, rank 2 = second ... each in its fixed place
    foreach ($seeded as $index => $player) {

        $rank = $index + 1;

        $player['draw_seed'] = $player['seed_number'] ?? $rank;

        $slots[$rankPosition[$rank]] = $player;
    }


    // Unseeded players: drawn at random into the places of the next ranks.
    // The places of ranks after the last player stay empty - those are the
    // byes, and they sit opposite rank 1, rank 2, ...
    shuffle($unseeded);

    $rank = count($seeded) + 1;

    foreach ($unseeded as $player) {

        $player['draw_seed'] = null;

        $slots[$rankPosition[$rank]] = $player;
        $rank++;
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

    // Every endorsed player needs a seed number before the draw is made
    if (count($unseeded) > 0) {
        throw new Exception(count($unseeded) . ' of ' . $total . ' players in ' . $category
            . ' have no seed number yet. Run Automatic Seeding (or set the seeds in Manage Seeding) first.');
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
            p2.player_nationality AS player2_nationality,
            rf.referee_name
        FROM matches m
        LEFT JOIN players p1
            ON p1.playerID = m.player1ID
        LEFT JOIN players p2
            ON p2.playerID = m.player2ID
        LEFT JOIN referees rf
            ON rf.refereeID = m.refereeID
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


// "11-6, 16-14, 11-8" from the winner's side, with "(ret.)" etc. for a status
function matchScoreText($match)
{
    $games = $match['game_scores'] ? explode(',', $match['game_scores']) : [];

    if ($match['winnerID'] !== null && $match['winnerID'] === $match['player2ID']) {
        $games = array_map(function ($game) {
            return implode('-', array_reverse(explode('-', $game)));
        }, $games);
    }

    $text = implode(', ', $games);

    $short = ['Walkover' => 'w/o', 'Retired' => 'ret.', 'Disqualified' => 'dsq'];

    if (!empty($match['result_status'])) {
        $label = $short[$match['result_status']] ?? $match['result_status'];
        $text = $text === '' ? $label : $text . ' (' . $label . ')';
    }

    return $text;
}


// What the score dialog needs to know about a match
function matchDialogData($match)
{
    $games = [];

    foreach ($match['game_scores'] ? explode(',', $match['game_scores']) : [] as $game) {
        $games[] = array_map('intval', explode('-', $game));
    }

    $player = function ($number) use ($match) {
        return [
            'name' => $match['player' . $number . '_name'] ?? $match['player' . $number . 'ID'],
            'seed' => $match['player' . $number . '_seed'],
            'country' => countryCode($match['player' . $number . '_nationality'] ?? ''),
        ];
    };

    return [
        'id' => (int) $match['matchID'],
        'round' => $match['round_name'],
        'number' => (int) $match['match_number'],
        'p1' => $player(1),
        'p2' => $player(2),
        'best_of' => (int) $match['best_of'],
        'points' => (int) $match['points_per_game'],
        'games' => $games,
        'status' => $match['result_status'] ?? '',
        'winner' => $match['winnerID'] === null ? 0 : ($match['winnerID'] === $match['player1ID'] ? 1 : 2),
        'referee' => (int) $match['refereeID'],
    ];
}


// The whole bracket: one column per round, matches joined in pairs.
// $scorable: double-click a match (with both players) to enter its score
function renderBracket($draw, $scorable = false)
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

                                    <?php $canScore = $scorable && $match['player1ID'] !== null && $match['player2ID'] !== null; ?>
                                    <?php $score = matchScoreText($match); ?>

                                    <div class="match-slot">

                                        <div class="match <?= $canScore ? 'scorable' : '' ?>"
                                            <?php if ($canScore): ?>
                                                tabindex="0" title="Double-click to enter the score"
                                                data-match="<?= htmlspecialchars(json_encode(matchDialogData($match))) ?>"
                                            <?php endif; ?>>

                                            <?php drawPlayerLine($match, 1); ?>
                                            <?php drawPlayerLine($match, 2); ?>

                                            <?php if ($score !== '' && $match['player1ID'] !== null && $match['player2ID'] !== null): ?>
                                                <div class="match-score"><?= htmlspecialchars($score) ?></div>
                                            <?php endif; ?>

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


/*
|--------------------------------------------------------------------------
| Edit Draw (drag and drop on admin_draw.php)
|--------------------------------------------------------------------------
| The admin rearranges the first round; the same players stay in the draw,
| only their places (and so their opponents) change. Later rounds are
| cleared and byes go through again. Not allowed once a real match (two
| players) has a result.
*/

// True when a match between two players already has a winner or a score
function drawHasResults($draw)
{
    foreach ($draw as $matches) {
        foreach ($matches as $match) {
            if ($match['player1ID'] !== null && $match['player2ID'] !== null
                && ($match['winnerID'] !== null || $match['player1_score'] !== null || $match['player2_score'] !== null
                    || $match['game_scores'] !== null || $match['result_status'] !== null)) {
                return true;
            }
        }
    }

    return false;
}


/*
|--------------------------------------------------------------------------
| Match result (double-click a match on admin_draw.php)
|--------------------------------------------------------------------------
| game_scores keeps every game as "11-6,11-10,11-8" (player 1 first);
| player1_score / player2_score are the games won. The first player to
| points_per_game (11 at most) wins the game - no one scores more than that.
*/

// Scores can only be entered on the tournament's days (start date to end date).
// Returns null when today is one of them, otherwise the reason it isn't.
function scoringClosedReason($tournament)
{
    $start = (string) ($tournament['tournament_startdate'] ?? '');
    $end = (string) ($tournament['tournament_enddate'] ?? '');

    if ($start === '' || $end === '' || strpos($start, '0000') === 0 || strpos($end, '0000') === 0) {
        return 'This tournament has no dates yet, so scores can\'t be entered.';
    }

    $today = date('Y-m-d');

    if ($today < date('Y-m-d', strtotime($start))) {
        return 'Scores can be entered from ' . date('d M Y', strtotime($start)) . ', the first day of the tournament.';
    }

    if ($today > date('Y-m-d', strtotime($end))) {
        return 'The tournament ended on ' . date('d M Y', strtotime($end)) . ', so scores can\'t be changed any more.';
    }

    return null;
}


function matchStatuses()
{
    return ['Walkover', 'Retired', 'Disqualified'];
}


// 1 or 2 when the game is finished, 0 while it is still going
function gameWinner($score1, $score2, $points)
{
    if (max($score1, $score2) !== $points || min($score1, $score2) >= $points) {
        return 0;
    }

    return $score1 > $score2 ? 1 : 2;
}


/*
| $input: best_of, points, games[game][1|2], status, winner (0 unknown, 1, 2),
| referee_id (0 = none; an active referee of the tournament, or the one the
| match already has).
| The winner moves into the next match; changing the winner is only allowed
| while that next match has no result.
*/
function saveMatchResult($conn, $tournamentID, $category, $matchID, array $input)
{
    $stmt = $conn->prepare("SELECT * FROM matches WHERE matchID = ? AND tournamentID = ? AND category_registered = ?");
    $stmt->bind_param("iis", $matchID, $tournamentID, $category);
    $stmt->execute();
    $match = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$match) {
        throw new Exception('That match could not be found.');
    }

    if ($match['player1ID'] === null || $match['player2ID'] === null) {
        throw new Exception('Both players need to be known before a score can be entered.');
    }

    $bestOf = (int) ($input['best_of'] ?? 5);
    $points = (int) ($input['points'] ?? 11);
    $status = trim((string) ($input['status'] ?? ''));
    $chosen = (int) ($input['winner'] ?? 0);

    if (!in_array($bestOf, [3, 5], true)) {
        throw new Exception('Choose best of 3 or 5 games.');
    }

    if ($points < 1 || $points > 11) {
        throw new Exception('Points per game must be between 1 and 11.');
    }

    if ($status !== '' && !in_array($status, matchStatuses(), true)) {
        throw new Exception('Unknown match status.');
    }

    if (!in_array($chosen, [0, 1, 2], true)) {
        $chosen = 0;
    }

    $refereeID = (int) ($input['referee_id'] ?? 0);

    if ($refereeID && $refereeID !== (int) $match['refereeID']) {

        $stmt = $conn->prepare("SELECT refereeID FROM referees WHERE refereeID = ? AND tournamentID = ? AND is_active = 1");
        $stmt->bind_param("ii", $refereeID, $tournamentID);
        $stmt->execute();
        $found = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$found) {
            throw new Exception('That referee can\'t be chosen - they may have been removed. Reload the page and try again.');
        }
    }

    $refereeID = $refereeID ?: null;

    // Outside the tournament's days only the referee can be changed; the result stays as it is
    $stmt = $conn->prepare("SELECT tournament_startdate, tournament_enddate FROM tournament WHERE tournamentID = ?");
    $stmt->bind_param("i", $tournamentID);
    $stmt->execute();
    $tournament = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (scoringClosedReason($tournament) !== null) {

        $update = $conn->prepare("UPDATE matches SET refereeID = ? WHERE matchID = ?");
        $update->bind_param("ii", $refereeID, $matchID);
        $update->execute();
        $update->close();

        return;
    }

    // Games in order; the first empty game ends the list
    $needed = intdiv($bestOf, 2) + 1;
    $won = [1 => 0, 2 => 0];
    $games = [];
    $stopped = false;

    for ($game = 1; $game <= $bestOf; $game++) {

        $score1 = trim((string) ($input['games'][$game][1] ?? ''));
        $score2 = trim((string) ($input['games'][$game][2] ?? ''));

        if ($score1 === '' && $score2 === '') {
            $stopped = true;
            continue;
        }

        $label = 'Game ' . $game;

        if ($stopped) {
            throw new Exception($label . ' has a score, but the game before it is empty.');
        }

        if (!ctype_digit($score1) || !ctype_digit($score2)) {
            throw new Exception($label . ' needs a score for both players.');
        }

        if (max($won) >= $needed) {
            throw new Exception($label . ' wasn\'t needed - the match was already won ' . max($won) . '-' . min($won) . '.');
        }

        $score1 = (int) $score1;
        $score2 = (int) $score2;

        if ($score1 > $points || $score2 > $points) {
            throw new Exception($label . ' (' . $score1 . '-' . $score2 . '): the most a player can score is ' . $points . '.');
        }

        $games[] = $score1 . '-' . $score2;

        $gameWinner = gameWinner($score1, $score2, $points);

        if ($gameWinner === 0) {

            // A match that ended early (retired, disqualified) may stop part-way through a game
            if ($status === '' || $status === 'Walkover') {
                throw new Exception($label . ' (' . $score1 . '-' . $score2 . ') isn\'t finished - first to ' . $points . ' wins the game.');
            }

            $stopped = true;
            continue;
        }

        $won[$gameWinner]++;
    }

    $scoreWinner = $won[1] >= $needed ? 1 : ($won[2] >= $needed ? 2 : 0);

    if ($status === 'Walkover' && $games) {
        throw new Exception('A walkover has no games - clear the score or choose another status.');
    }

    if ($status === '') {

        if ($chosen !== 0 && $scoreWinner !== 0 && $chosen !== $scoreWinner) {
            throw new Exception('The score says the other player won.');
        }

        // The winner can be chosen once 3 games are in, even if the score isn't decided
        if ($chosen !== 0 && $scoreWinner === 0 && count($games) < 3) {
            throw new Exception('Enter at least 3 games before choosing the winner. If the match ended early, choose a status (e.g. Retired).');
        }

        $winnerSide = $scoreWinner ?: $chosen;

    } else {

        if ($chosen === 0) {
            throw new Exception('Choose the winner of the ' . strtolower($status) . '.');
        }

        $winnerSide = $chosen;
    }

    $winnerID = $winnerSide ? $match['player' . $winnerSide . 'ID'] : null;
    $winnerSeed = $winnerSide ? $match['player' . $winnerSide . '_seed'] : null;
    $gameScores = $games ? implode(',', $games) : null;
    $games1 = $games ? $won[1] : null;
    $games2 = $games ? $won[2] : null;
    $status = $status === '' ? null : $status;
    $matchStatus = $winnerID !== null ? 'Completed' : 'Pending';

    $conn->begin_transaction();

    try {

        // A new (or no) winner: put them into the next match
        if ($match['next_matchID'] !== null && $winnerID !== $match['winnerID']) {

            $nextID = (int) $match['next_matchID'];

            $stmt = $conn->prepare("SELECT * FROM matches WHERE matchID = ?");
            $stmt->bind_param("i", $nextID);
            $stmt->execute();
            $next = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($next && ($next['winnerID'] !== null || $next['game_scores'] !== null || $next['result_status'] !== null)) {
                throw new Exception('The winner can\'t be changed: the next match (' . $next['round_name'] . ' #'
                    . $next['match_number'] . ') already has a result. Clear that result first.');
            }

            $column = $match['next_match_position'] === 'player1' ? 'player1' : 'player2';

            $advance = $conn->prepare("UPDATE matches SET {$column}ID = ?, {$column}_seed = ? WHERE matchID = ?");
            $advance->bind_param("sii", $winnerID, $winnerSeed, $nextID);
            $advance->execute();
            $advance->close();
        }

        $update = $conn->prepare("
            UPDATE matches
            SET player1_score = ?, player2_score = ?, game_scores = ?, best_of = ?, points_per_game = ?,
                result_status = ?, refereeID = ?, winnerID = ?, match_status = ?
            WHERE matchID = ?
        ");
        $update->bind_param("iisiisissi", $games1, $games2, $gameScores, $bestOf, $points, $status, $refereeID, $winnerID, $matchStatus, $matchID);
        $update->execute();
        $update->close();

        $conn->commit();

    } catch (Throwable $e) {

        $conn->rollback();
        throw $e;
    }
}


// True when the final has a winner (every round is finished)
function drawFinished($draw)
{
    if (!$draw) {
        return false;
    }

    $final = end($draw);

    return count($final) === 1 && reset($final)['winnerID'] !== null;
}


// Categories whose matches have started (a real match has a result):
// their seeding and draw can't be changed any more
function startedCategories($conn, $tournamentID)
{
    $stmt = $conn->prepare("
        SELECT DISTINCT category_registered
        FROM matches
        WHERE tournamentID = ?
        AND player1ID IS NOT NULL
        AND player2ID IS NOT NULL
        AND (winnerID IS NOT NULL OR player1_score IS NOT NULL OR player2_score IS NOT NULL
             OR game_scores IS NOT NULL OR result_status IS NOT NULL)
    ");
    $stmt->bind_param("i", $tournamentID);
    $stmt->execute();

    $categories = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'category_registered');

    $stmt->close();

    return $categories;
}


/*
| $positions: [place => playerID or '' for a bye], places 1..draw size,
| top to bottom (place 1 and 2 = match 1, 3 and 4 = match 2 ...).
*/
function saveDrawPositions($conn, $tournamentID, $category, array $positions)
{
    $draw = loadDraw($conn, $tournamentID, $category);

    if (!$draw) {
        throw new Exception('There is no draw for ' . $category . ' to edit.');
    }

    if (drawHasResults($draw)) {
        throw new Exception('Matches in ' . $category . ' already have results, so the draw can\'t be changed any more.');
    }

    // Players and seeds now in the draw
    $seeds = [];

    foreach ($draw[1] as $match) {
        foreach ([1, 2] as $number) {
            if ($match['player' . $number . 'ID'] !== null) {
                $seeds[$match['player' . $number . 'ID']] = $match['player' . $number . '_seed'];
            }
        }
    }

    $size = count($draw[1]) * 2;

    // Same places, same players - nothing added, removed or doubled
    $placed = [];

    for ($place = 1; $place <= $size; $place++) {

        $playerID = trim((string) ($positions[$place] ?? ''));

        if ($playerID === '') {
            $placed[$place] = null;
            continue;
        }

        if (!array_key_exists($playerID, $seeds) || in_array($playerID, $placed, true)) {
            throw new Exception('The changed draw doesn\'t match the players in ' . $category . '. Please try again.');
        }

        $placed[$place] = $playerID;
    }

    if (count(array_filter($placed)) !== count($seeds)) {
        throw new Exception('The changed draw doesn\'t match the players in ' . $category . '. Please try again.');
    }

    foreach ($draw[1] as $number => $match) {
        if ($placed[$number * 2 - 1] === null && $placed[$number * 2] === null) {
            throw new Exception('Match ' . $number . ' would have two byes - every match needs at least one player.');
        }
    }

    $conn->begin_transaction();

    try {

        // Later rounds start empty again
        $clear = $conn->prepare("
            UPDATE matches
            SET player1ID = NULL, player2ID = NULL, player1_seed = NULL, player2_seed = NULL,
                player1_score = NULL, player2_score = NULL, winnerID = NULL, match_status = 'Pending'
            WHERE tournamentID = ? AND category_registered = ? AND round_number > 1
        ");
        $clear->bind_param("is", $tournamentID, $category);
        $clear->execute();
        $clear->close();

        $update = $conn->prepare("
            UPDATE matches
            SET player1ID = ?, player2ID = ?, player1_seed = ?, player2_seed = ?,
                player1_score = NULL, player2_score = NULL, winnerID = ?, match_status = ?
            WHERE matchID = ?
        ");

        foreach ($draw[1] as $number => $match) {

            $player1 = $placed[$number * 2 - 1];
            $player2 = $placed[$number * 2];
            $seed1 = $player1 !== null ? $seeds[$player1] : null;
            $seed2 = $player2 !== null ? $seeds[$player2] : null;

            // Bye: the player goes through
            $winner = ($player1 === null || $player2 === null) ? ($player1 ?? $player2) : null;
            $status = $winner !== null ? 'Completed' : 'Pending';
            $matchID = (int) $match['matchID'];

            $update->bind_param("ssiissi", $player1, $player2, $seed1, $seed2, $winner, $status, $matchID);
            $update->execute();

            if ($winner !== null && $match['next_matchID'] !== null) {

                $column = $match['next_match_position'] === 'player1' ? 'player1' : 'player2';
                $winnerSeed = $seeds[$winner];
                $nextID = (int) $match['next_matchID'];

                $advance = $conn->prepare("UPDATE matches SET {$column}ID = ?, {$column}_seed = ? WHERE matchID = ?");
                $advance->bind_param("sii", $winner, $winnerSeed, $nextID);
                $advance->execute();
                $advance->close();
            }
        }

        $update->close();
        $conn->commit();

    } catch (Throwable $e) {

        $conn->rollback();
        throw $e;
    }
}
