<?php

require_once '../db.php';
require_once 'admin_auth.php';

// Platform Admins, or the admin who created this tournament
requireTournamentAccess($_GET['id'] ?? 0);
require_once "admin_draw_functions.php";
require_once "admin_referee_functions.php";



/*
|--------------------------------------------------------------------------
| TOURNAMENT
|--------------------------------------------------------------------------
*/

$tournamentID = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $conn->prepare("SELECT * FROM tournament WHERE tournamentID = ?");
$stmt->bind_param("i", $tournamentID);
$stmt->execute();

$tournament = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$tournament) {
    die("Tournament not found.");
}


/*
|--------------------------------------------------------------------------
| CATEGORIES (with endorsed players, or an existing draw)
|--------------------------------------------------------------------------
*/

$categoryStmt = $conn->prepare("
    SELECT category_registered AS category
    FROM tournament_register
    WHERE tournamentID = ?
    AND UPPER(TRIM(endorsement)) = 'ENDORSED'
    AND TRIM(category_registered) != ''

    UNION

    SELECT category_registered
    FROM matches
    WHERE tournamentID = ?

    ORDER BY category ASC
");

$categoryStmt->bind_param("ii", $tournamentID, $tournamentID);
$categoryStmt->execute();

$categoryResult = $categoryStmt->get_result();

$categories = [];

while ($row = $categoryResult->fetch_assoc()) {
    $categories[] = $row['category'];
}

$categoryStmt->close();

$selectedCategory = in_array($_GET['category'] ?? '', $categories, true)
    ? $_GET['category']
    : ($categories[0] ?? '');


/*
|--------------------------------------------------------------------------
| ACTIONS: make / remake / delete the draw
|--------------------------------------------------------------------------
*/

$pageUrl = "admin_draw.php?id=" . $tournamentID . "&category=" . urlencode($selectedCategory);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $category = trim($_POST['category'] ?? '');

    $pageUrl = "admin_draw.php?id=" . $tournamentID . "&category=" . urlencode($category);

    if (!in_array($category, $categories, true)) {
        header("Location: " . $pageUrl);
        exit;
    }

    try {

        // Once matches have results, the draw can't be made again or deleted
        if (in_array($action, ['generate', 'delete'], true) && drawHasResults(loadDraw($conn, $tournamentID, $category))) {
            throw new Exception('Matches in ' . $category . ' already have results, so the draw can\'t be '
                . ($action === 'delete' ? 'deleted' : 'made again') . '.');
        }

        if ($action === 'generate') {

            $count = generateDraw($conn, $tournamentID, $category);

            header("Location: " . $pageUrl . "&message=generated&players=" . $count);
            exit;
        }

        // Edit Draw: the first round rearranged by drag and drop
        if ($action === 'save_edit') {

            $positions = [];

            foreach ((array) ($_POST['positions'] ?? []) as $place => $playerID) {
                $positions[(int) $place] = (string) $playerID;
            }

            saveDrawPositions($conn, $tournamentID, $category, $positions);

            header("Location: " . $pageUrl . "&message=edited");
            exit;
        }

        // Score dialog (double-click a match)
        if ($action === 'save_score') {

            saveMatchResult($conn, $tournamentID, $category, (int) ($_POST['match_id'] ?? 0), $_POST);

            header("Location: " . $pageUrl . "&message=" . (scoringClosedReason($tournament) === null ? 'scored' : 'referee'));
            exit;
        }

        if ($action === 'delete') {

            deleteDraw($conn, $tournamentID, $category);

            header("Location: " . $pageUrl . "&message=deleted");
            exit;
        }

    } catch (Throwable $e) {

        header("Location: " . $pageUrl . "&error=" . urlencode($e->getMessage()));
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| DRAW STATUS PER CATEGORY
|--------------------------------------------------------------------------
*/

$categoryInfo = [];

foreach ($categories as $category) {

    list($seeded, $unseeded) = drawPlayers($conn, $tournamentID, $category);

    $categoryInfo[$category] = [
        'players' => count($seeded) + count($unseeded),
        'seeded' => count($seeded),
        'unseeded' => count($unseeded),
        'has_draw' => drawExists($conn, $tournamentID, $category)
    ];
}

$selectedInfo = $categoryInfo[$selectedCategory] ?? null;

$draw = $selectedCategory !== '' ? loadDraw($conn, $tournamentID, $selectedCategory) : [];

// Referees for the score dialog
$referees = loadReferees($conn, $tournamentID);

// Winners List (all categories) once any category's final has a winner
$anyDrawFinished = false;

foreach ($categoryInfo as $category => $info) {
    if ($info['has_draw'] && drawFinished($category === $selectedCategory ? $draw : loadDraw($conn, $tournamentID, $category))) {
        $anyDrawFinished = true;
        break;
    }
}


// Players in the saved draw vs. endorsed now (someone was endorsed or removed after the draw)
$drawPlayerCount = 0;

if (!empty($draw[1])) {
    foreach ($draw[1] as $match) {
        $drawPlayerCount += ($match['player1ID'] !== null) + ($match['player2ID'] !== null);
    }
}

$drawOutOfDate = $selectedInfo && $selectedInfo['has_draw'] && $drawPlayerCount !== $selectedInfo['players'];

// Edit Draw: only while no real match has a result
$drawLocked = $draw && drawHasResults($draw);
$editMode = isset($_GET['edit']) && $draw && !$drawLocked;

// Scores only on the tournament's days; outside them the dialog only changes the referee
$scoringClosed = scoringClosedReason($tournament);

// "[1] Sofia Chen (SGP)" parts for the editor
function editSlot($match, $number)
{
    $id = $match['player' . $number . 'ID'];

    return [
        'id' => $id ?? '',
        'seed' => $match['player' . $number . '_seed'],
        'name' => $id === null ? 'Bye' : ($match['player' . $number . '_name'] ?? $id),
        'country' => $id === null ? '' : countryCode($match['player' . $number . '_nationality'] ?? ''),
    ];
}


?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Draw -
        <?= htmlspecialchars($tournament['tournament_name']) ?>
    </title>

    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <link rel="stylesheet" href="assets/admin_seeding.css?v=<?= filemtime(__DIR__ . '/assets/admin_seeding.css') ?>">
    <link rel="stylesheet" href="assets/draw.css?v=<?= filemtime(__DIR__ . '/assets/draw.css') ?>">

</head>

<body>

    <?php require 'admin_navbar.php'; ?>


    <main class="container">

        <a href="admin_each_tournament.php?id=<?= $tournamentID ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Tournament
        </a>

        <!-- ==========================================================
            PAGE HEADER
        =========================================================== -->

        <div class="page-header">

            <div>

                <h1>Tournament Draw</h1>

                <p><?= htmlspecialchars($tournament['tournament_name']) ?></p>

            </div>

        </div>


        <!-- ==========================================================
            MESSAGES
        =========================================================== -->

        <?php if (($_GET['message'] ?? '') === 'generated'): ?>

            <div class="success-message">
                The draw has been made for <?= htmlspecialchars($selectedCategory) ?>
                (<?= (int)($_GET['players'] ?? 0) ?> players).
            </div>

        <?php elseif (($_GET['message'] ?? '') === 'edited'): ?>

            <div class="success-message">
                The draw for <?= htmlspecialchars($selectedCategory) ?> has been changed.
            </div>

        <?php elseif (($_GET['message'] ?? '') === 'scored'): ?>

            <div class="success-message">
                The match result has been saved.
            </div>

        <?php elseif (($_GET['message'] ?? '') === 'referee'): ?>

            <div class="success-message">
                The referee has been saved.
            </div>

        <?php elseif (($_GET['message'] ?? '') === 'deleted'): ?>

            <div class="success-message">
                The draw for <?= htmlspecialchars($selectedCategory) ?> has been deleted.
            </div>

        <?php endif; ?>

        <?php if (!empty($_GET['error'])): ?>

            <div class="error-message">
                <?= htmlspecialchars($_GET['error']) ?>
            </div>

        <?php endif; ?>


        <?php if (empty($categories)): ?>

            <div class="empty-draw">
                <h2>No players to draw yet</h2>
                <p>Endorse players in this tournament first, then come back to make the draw.</p>
            </div>

        <?php else: ?>


            <!-- ==========================================================
                CATEGORY TABS
            =========================================================== -->

            <div class="category-tabs">

                <?php foreach ($categoryInfo as $category => $info): ?>

                    <a
                        href="admin_draw.php?id=<?= $tournamentID ?>&category=<?= urlencode($category) ?>"
                        class="category-tab <?= $category === $selectedCategory ? 'active' : '' ?>"
                    >
                        <?= htmlspecialchars($category) ?>

                        <span class="tab-status <?= $info['has_draw'] ? 'done' : '' ?>">
                            <?= $info['has_draw'] ? 'Drawn' : $info['players'] . ($info['players'] === 1 ? ' player' : ' players') ?>
                        </span>
                    </a>

                <?php endforeach; ?>

            </div>


            <!-- ==========================================================
                TOOLBAR
            =========================================================== -->

            <div class="draw-toolbar">

                <div class="draw-summary">

                    <strong><?= htmlspecialchars($selectedCategory) ?></strong>

                    <span>
                        <?= $selectedInfo['players'] ?> endorsed player<?= $selectedInfo['players'] === 1 ? '' : 's' ?>
                        <?php if ($selectedInfo['players'] >= 2): ?>
                            &middot; draw of <?= drawSize($selectedInfo['players']) ?>
                            &middot; <?= $selectedInfo['seeded'] ?> seeded
                            &middot; <?= drawSize($selectedInfo['players']) - $selectedInfo['players'] ?> byes
                        <?php endif; ?>
                    </span>

                </div>

                <div class="draw-actions">

                    <?php if (!$selectedInfo['has_draw'] && $selectedInfo['players'] >= 2 && $selectedInfo['unseeded'] === 0): ?>

                        <form method="POST" action="<?= htmlspecialchars($pageUrl) ?>"
                              onsubmit="return confirm('Make the draw for this category?');">
                            <input type="hidden" name="action" value="generate">
                            <input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory) ?>">
                            <button type="submit" class="btn">Make Draw</button>
                        </form>

                    <?php elseif ($draw && !$editMode && !$drawLocked): ?>

                        <a href="<?= htmlspecialchars($pageUrl) ?>&edit=1" class="btn">
                            <i class="fa-solid fa-pen"></i> Edit Draw
                        </a>

                    <?php endif; ?>

                    <?php if ($selectedInfo['has_draw'] && !$drawLocked): ?>

                        <form method="POST" action="<?= htmlspecialchars($pageUrl) ?>"
                              onsubmit="return confirm('Delete the draw for <?= htmlspecialchars($selectedCategory, ENT_QUOTES) ?>?');">

                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory) ?>">

                            <button type="submit" class="btn btn-danger">
                                Delete Draw
                            </button>

                        </form>

                    <?php endif; ?>

                    <?php if ($drawLocked): ?>
                        <span class="back-btn is-locked" title="Matches have started, so the seeding can't be changed">
                            <i class="fa-solid fa-lock"></i> Seeding Locked
                        </span>
                    <?php else: ?>
                        <a href="admin_seeding.php?id=<?= $tournamentID ?>&category=<?= urlencode($selectedCategory) ?>" class="back-btn">
                            Manage Seeding
                        </a>
                    <?php endif; ?>

                    <a href="admin_referees.php?id=<?= $tournamentID ?>" class="back-btn">
                        Referees
                    </a>

                    <?php if ($draw): ?>

                        <!-- One Download button: Report or Bracket -->
                        <div class="download-menu" id="downloadMenu">

                            <button type="button" class="back-btn download-btn" id="downloadToggle"
                                    aria-haspopup="true" aria-expanded="false">Download</button>

                            <div class="download-options" hidden>
                                <a href="admin_draw_report.php?id=<?= $tournamentID ?>&category=<?= urlencode($selectedCategory) ?>" target="_blank">
                                    <i class="fa-solid fa-file-lines"></i> Report
                                </a>
                                <button type="button"
                                        onclick="downloadBracket(document.getElementById('downloadToggle'), <?= htmlspecialchars(json_encode(bracketFileName($tournament, $selectedCategory)), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($tournament['tournament_name'] . ' - ' . $selectedCategory), ENT_QUOTES) ?>)">
                                    <i class="fa-solid fa-image"></i> Bracket (image)
                                </button>
                                <?php if (drawFinished($draw)): ?>
                                    <a href="admin_draw_results.php?id=<?= $tournamentID ?>&category=<?= urlencode($selectedCategory) ?>">
                                        <i class="fa-solid fa-table-list"></i> Match Results (Excel)
                                    </a>
                                <?php endif; ?>
                                <?php if ($anyDrawFinished): ?>
                                    <a href="admin_winners_report.php?id=<?= $tournamentID ?>">
                                        <i class="fa-solid fa-trophy"></i> Winners List (Excel)
                                    </a>
                                <?php endif; ?>
                            </div>

                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <?php if ($selectedInfo['players'] < 2 && !$selectedInfo['has_draw']): ?>

                <div class="warning-message">
                    At least 2 endorsed players are needed to make a draw for <?= htmlspecialchars($selectedCategory) ?>.
                </div>

            <?php elseif ($selectedInfo['unseeded'] > 0): ?>

                <div class="warning-message">
                    <strong><?= $selectedInfo['unseeded'] ?> of <?= $selectedInfo['players'] ?> players</strong>
                    in <?= htmlspecialchars($selectedCategory) ?> have no seed number yet, so the draw can't be
                    <?= $selectedInfo['has_draw'] ? 'remade' : 'made' ?>.
                    Run <strong>Automatic Seeding</strong> (or set the seeds) on
                    <a href="admin_seeding.php?id=<?= $tournamentID ?>&category=<?= urlencode($selectedCategory) ?>">Manage Seeding</a> first.
                </div>

            <?php endif; ?>

            <?php if ($drawOutOfDate): ?>

                <div class="warning-message out-of-date">
                    <span>
                        This draw has <?= $drawPlayerCount ?> players, but <?= $selectedInfo['players'] ?> players are endorsed now.
                        Make the draw again to include the changes (any edits to the draw will be lost).
                    </span>
                    <?php if ($selectedInfo['unseeded'] === 0 && !$drawLocked): ?>
                        <form method="POST" action="<?= htmlspecialchars($pageUrl) ?>"
                              onsubmit="return confirm('Make the draw again? The current draw, including any edits, will be replaced.');">
                            <input type="hidden" name="action" value="generate">
                            <input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory) ?>">
                            <button type="submit" class="btn">Make Draw Again</button>
                        </form>
                    <?php endif; ?>
                </div>

            <?php endif; ?>


            <!-- ==========================================================
                BRACKET
            =========================================================== -->

            <?php if ($editMode): ?>

                <!-- ==========================================================
                    EDIT DRAW: drag a player onto another player (or a bye)
                    to swap their places; or click one, then the other
                =========================================================== -->

                <form method="POST" action="<?= htmlspecialchars($pageUrl) ?>" class="draw-editor" id="drawEditor">

                    <input type="hidden" name="action" value="save_edit">
                    <input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory) ?>">

                    <div class="editor-head">
                        <p>
                            <i class="fa-solid fa-hand-pointer"></i>
                            <strong>Drag a player onto another player</strong> (or onto a Bye) to swap their places.
                            You can also click one player, then the other. The later rounds are worked out again when you save.
                        </p>
                        <div class="editor-actions">
                            <button type="button" class="back-btn" id="undoSwap" disabled>
                                <i class="fa-solid fa-rotate-left"></i> Undo
                            </button>
                            <a href="<?= htmlspecialchars($pageUrl) ?>" class="back-btn">Cancel</a>
                            <button type="submit" class="btn" id="saveDraw">Save Draw</button>
                        </div>
                    </div>

                    <div class="editor-grid">

                        <?php foreach ($draw[1] as $number => $match): ?>

                            <div class="editor-match">

                                <span class="editor-match-no">Match <?= (int) $number ?></span>

                                <?php foreach ([1, 2] as $side): ?>
                                    <?php $slot = editSlot($match, $side); $place = ($number - 1) * 2 + $side; ?>

                                    <div class="editor-slot <?= $slot['id'] === '' ? 'is-bye' : '' ?>"
                                         draggable="true" data-place="<?= $place ?>" tabindex="0">
                                        <input type="hidden" name="positions[<?= $place ?>]" value="<?= htmlspecialchars($slot['id']) ?>">
                                        <i class="fa-solid fa-grip-vertical grip" aria-hidden="true"></i>
                                        <span class="seed"><?= $slot['seed'] !== null ? '[' . (int) $slot['seed'] . ']' : '' ?></span>
                                        <span class="player-name"><?= htmlspecialchars($slot['name']) ?></span>
                                        <span class="country"><?= htmlspecialchars($slot['country']) ?></span>
                                    </div>

                                    <?php if ($side === 1): ?>
                                        <span class="editor-vs">vs</span>
                                    <?php endif; ?>

                                <?php endforeach; ?>

                            </div>

                        <?php endforeach; ?>

                    </div>

                    <p class="editor-error" id="editorError" hidden></p>

                </form>

            <?php elseif ($draw): ?>

                <?php if ($drawLocked): ?>
                    <p class="locked-note"><i class="fa-solid fa-lock"></i> Matches already have results, so the draw and the seeding can't be edited or deleted any more.</p>
                <?php endif; ?>

                <?php if ($scoringClosed): ?>
                    <p class="score-hint is-closed"><i class="fa-solid fa-calendar-xmark"></i> <?= htmlspecialchars($scoringClosed) ?> Double-click a match to see it or set its referee.</p>
                <?php else: ?>
                    <p class="score-hint"><i class="fa-solid fa-computer-mouse"></i> Double-click a match to enter its score.</p>
                <?php endif; ?>

                <div class="bracket-wrapper">
                    <?php renderBracket($draw, true); ?>
                </div>


                <!-- ==========================================================
                    MATCH INFORMATION: score, status and winner
                =========================================================== -->

                <dialog class="score-dialog" id="scoreDialog">

                    <form method="POST" action="<?= htmlspecialchars($pageUrl) ?>" id="scoreForm">

                        <input type="hidden" name="action" value="save_score">
                        <input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory) ?>">
                        <input type="hidden" name="match_id" id="scoreMatchId">

                        <div class="score-head">
                            <h2>Match Information</h2>
                            <button type="button" class="score-close" data-close aria-label="Close">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <div class="score-body">

                            <dl class="score-info">
                                <dt>Draw</dt><dd><?= htmlspecialchars($selectedCategory) ?></dd>
                                <dt>Round</dt><dd id="scoreRound"></dd>
                                <dt>Match</dt><dd id="scoreNumber"></dd>
                            </dl>

                            <?php if ($scoringClosed): ?>
                                <p class="score-closed"><i class="fa-solid fa-calendar-xmark"></i> <?= htmlspecialchars($scoringClosed) ?></p>
                            <?php endif; ?>

                            <fieldset class="score-result" id="scoreResult"<?= $scoringClosed ? ' disabled' : '' ?>>

                            <div class="score-row">
                                <label for="scoreBestOf">Scoring Format</label>
                                <div class="score-format">
                                    Best of
                                    <select name="best_of" id="scoreBestOf">
                                        <option value="5">5</option>
                                        <option value="3">3</option>
                                    </select>
                                    games, PAR
                                    <input type="number" name="points" id="scorePoints" min="1" max="11" value="11">
                                </div>
                            </div>

                            <div class="score-row">
                                <span class="score-label">Score</span>
                                <div class="score-grid" id="scoreGrid"></div>
                            </div>

                            <p class="score-check" id="scoreCheck"></p>

                            <div class="score-row">
                                <label for="scoreStatus">Status</label>
                                <select name="status" id="scoreStatus">
                                    <option value=""></option>
                                    <?php foreach (matchStatuses() as $status): ?>
                                        <option value="<?= $status ?>"><?= $status ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="score-row">
                                <span class="score-label">Winner</span>
                                <div class="score-winner">
                                    <label><kbd>F4</kbd> <input type="radio" name="winner" value="0"> Unknown</label>
                                    <label><kbd>F5</kbd> <input type="radio" name="winner" value="1"> <span id="scoreWinner1"></span></label>
                                    <label><kbd>F6</kbd> <input type="radio" name="winner" value="2"> <span id="scoreWinner2"></span></label>
                                </div>
                            </div>

                            </fieldset>

                            <!-- Referee: the tournament's active referees (an inactive one only on a match that already has them) -->
                            <div class="score-row score-officials">
                                <label for="scoreReferee">Referee</label>
                                <select name="referee_id" id="scoreReferee">
                                    <option value="0">&lt;None&gt;</option>
                                    <?php foreach ($referees as $referee): ?>
                                        <option value="<?= (int) $referee['refereeID'] ?>"
                                                data-country="<?= htmlspecialchars(countryCode($referee['referee_nationality'])) ?>"
                                                data-active="<?= (int) $referee['is_active'] ?>">
                                            <?= htmlspecialchars($referee['referee_name'] . ' (' . countryCode($referee['referee_nationality']) . ') - ' . $referee['referee_level']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <p class="score-referee-note" id="refereeNote" hidden></p>

                            <?php if (!$referees): ?>
                                <p class="score-referee-note">
                                    No referees yet - add them on the <a href="admin_referees.php?id=<?= $tournamentID ?>">Referees</a> page.
                                </p>
                            <?php endif; ?>

                        </div>

                        <div class="score-foot">
                            <?php if (!$scoringClosed): ?>
                                <button type="button" class="back-btn" id="scoreClear">Clear Result</button>
                            <?php endif; ?>
                            <span class="spacer"></span>
                            <button type="button" class="back-btn" data-close>Cancel</button>
                            <button type="submit" class="btn">Save</button>
                        </div>

                    </form>

                </dialog>

            <?php elseif ($selectedInfo['players'] >= 2): ?>

                <div class="empty-draw">
                    <h2>No draw yet</h2>
                    <?php if ($selectedInfo['unseeded'] > 0): ?>
                        <p>Seed every player in <?= htmlspecialchars($selectedCategory) ?> first, then come back to make the draw.</p>
                    <?php else: ?>
                        <p>Press <strong>Make Draw</strong> to place the players for <?= htmlspecialchars($selectedCategory) ?>.</p>
                    <?php endif; ?>
                </div>

            <?php endif; ?>

        <?php endif; ?>

    </main>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="assets/bracket_download.js?v=<?= filemtime(__DIR__ . '/assets/bracket_download.js') ?>"></script>

    <?php if ($draw): ?>
    <script>
        // Download menu: open on click, close on a choice, outside click or Escape
        (function () {

            const menu = document.getElementById('downloadMenu');
            const toggle = document.getElementById('downloadToggle');
            const options = menu.querySelector('.download-options');

            function show(open) {
                options.hidden = !open;
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            toggle.addEventListener('click', function () {
                show(options.hidden);
            });

            options.addEventListener('click', function () {
                show(false);
            });

            document.addEventListener('click', function (event) {
                if (!menu.contains(event.target)) {
                    show(false);
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    show(false);
                }
            });

        })();
    </script>
    <?php endif; ?>

    <?php if ($draw && !$editMode): ?>
    <script>
        // Match Information: double-click a match to enter its score
        (function () {

            const dialog = document.getElementById('scoreDialog');
            const form = document.getElementById('scoreForm');
            const grid = document.getElementById('scoreGrid');
            const check = document.getElementById('scoreCheck');
            const bestOf = form.elements.best_of;
            const points = form.elements.points;
            const status = form.elements.status;
            const referee = form.elements.referee_id;
            const refereeNote = document.getElementById('refereeNote');
            const wrapper = document.querySelector('.bracket-wrapper');
            const scoringOpen = <?= $scoringClosed ? 'false' : 'true' ?>;
            let match = null;
            let autoWinner = true;      // the winner follows the score until the admin picks one

            function playerLabel(player) {
                return (player.seed !== null ? '[' + player.seed + '] ' : '') + player.name;
            }

            function element(tag, className, text) {
                const node = document.createElement(tag);
                node.className = className;
                node.textContent = text || '';
                return node;
            }

            function scoreInput(game, side) {
                return form.querySelector('input[name="games[' + game + '][' + side + ']"]');
            }

            // Name column, then one column per game; Tab goes down each game (player 1, player 2)
            function buildGrid(games) {
                grid.innerHTML = '';
                grid.appendChild(element('span', 'score-game', ''));

                [match.p1, match.p2].forEach(function (player) {
                    const name = element('span', 'score-player', playerLabel(player));
                    if (player.country) {
                        name.appendChild(element('span', 'country', player.country));
                    }
                    grid.appendChild(name);
                });

                for (let game = 1; game <= parseInt(bestOf.value, 10); game++) {
                    grid.appendChild(element('span', 'score-game', 'G' + game));

                    [1, 2].forEach(function (side) {
                        const input = document.createElement('input');
                        input.type = 'text';
                        input.inputMode = 'numeric';
                        input.maxLength = 2;
                        input.name = 'games[' + game + '][' + side + ']';
                        input.setAttribute('aria-label', 'Game ' + game + ', ' + (side === 1 ? match.p1 : match.p2).name);
                        input.value = games[game - 1] ? games[game - 1][side - 1] : '';
                        grid.appendChild(input);
                    });
                }
            }

            function currentGames() {
                const games = [];
                for (let game = 1; scoreInput(game, 1); game++) {
                    games.push([scoreInput(game, 1).value, scoreInput(game, 2).value]);
                }
                return games;
            }

            // Same rules as saveMatchResult(): 1 or 2 when the game is finished, 0 while it is still going
            function gameWinner(a, b, par) {
                const finished = Math.max(a, b) === par && Math.min(a, b) < par;
                return finished ? (a > b ? 1 : 2) : 0;
            }

            function evaluate() {
                const par = parseInt(points.value, 10);
                const needed = Math.floor(parseInt(bestOf.value, 10) / 2) + 1;
                const won = [0, 0, 0];
                let stopped = false;
                let played = 0;

                if (!(par >= 1 && par <= 11)) {
                    return { error: 'PAR must be between 1 and 11 points.' };
                }

                const games = currentGames();

                for (let i = 0; i < games.length; i++) {
                    const a = games[i][0].trim();
                    const b = games[i][1].trim();
                    const label = 'Game ' + (i + 1);

                    if (a === '' && b === '') {
                        stopped = true;
                        continue;
                    }
                    if (stopped) {
                        return { error: label + ' has a score, but the game before it is empty.' };
                    }
                    if (!/^\d+$/.test(a) || !/^\d+$/.test(b)) {
                        return { error: label + ' needs a score for both players.' };
                    }
                    if (Math.max(won[1], won[2]) >= needed) {
                        return { error: label + ' wasn\'t needed - the match was already won.' };
                    }
                    if (parseInt(a, 10) > par || parseInt(b, 10) > par) {
                        return { error: label + ' (' + a + '-' + b + '): the most a player can score is ' + par + '.' };
                    }

                    played++;
                    const winner = gameWinner(parseInt(a, 10), parseInt(b, 10), par);

                    if (winner === 0) {
                        if (status.value === '' || status.value === 'Walkover') {
                            return { error: label + ' (' + a + '-' + b + ') isn\'t finished - first to ' + par + ' wins the game.' };
                        }
                        stopped = true;
                        continue;
                    }

                    won[winner]++;
                }

                if (status.value === 'Walkover' && played > 0) {
                    return { error: 'A walkover has no games - clear the score or choose another status.' };
                }

                return { won: won, played: played, winner: won[1] >= needed ? 1 : (won[2] >= needed ? 2 : 0) };
            }

            function chosenWinner() {
                return parseInt(form.querySelector('input[name="winner"]:checked').value, 10);
            }

            function setWinner(side) {
                form.querySelector('input[name="winner"][value="' + side + '"]').checked = true;
            }

            function refresh() {
                const result = evaluate();

                check.classList.remove('is-error');

                if (result.error) {
                    check.textContent = result.error;
                    return result;
                }

                if (status.value === '' && autoWinner) {
                    setWinner(result.winner);
                }

                // Without a status, the winner can be picked once 3 games are in (or the score decides it)
                const canPick = status.value !== '' || result.winner !== 0 || result.played >= 3;

                form.querySelectorAll('input[name="winner"]:not([value="0"])').forEach(function (radio) {
                    radio.disabled = !canPick;
                    radio.closest('label').classList.toggle('is-disabled', !canPick);
                });

                if (!canPick) {
                    setWinner(0);
                }

                // Match decided: the games left over weren't played, shown as "-"
                const decided = result.winner !== 0 || chosenWinner() !== 0;
                let lastPlayed = 0;

                currentGames().forEach(function (game, index) {
                    if (game[0].trim() !== '' || game[1].trim() !== '') {
                        lastPlayed = index + 1;
                    }
                });

                for (let game = 1; scoreInput(game, 1); game++) {
                    [1, 2].forEach(function (side) {
                        const input = scoreInput(game, side);
                        const unplayed = decided && game > lastPlayed;
                        input.disabled = unplayed;
                        input.placeholder = unplayed ? '-' : '';
                    });
                }

                const names = { 1: match.p1.name, 2: match.p2.name };

                if (result.winner) {
                    check.textContent = names[result.winner] + ' wins ' + result.won[result.winner] + '-' + result.won[3 - result.winner] + '.';
                } else if (result.played) {
                    check.textContent = 'Games so far: ' + result.won[1] + '-' + result.won[2] + '.';
                } else {
                    check.textContent = '';
                }

                return result;
            }

            function open(box) {
                match = JSON.parse(box.dataset.match);
                autoWinner = match.winner === 0;

                form.elements.match_id.value = match.id;
                document.getElementById('scoreRound').textContent = match.round;
                document.getElementById('scoreNumber').textContent = '#' + match.number;
                document.getElementById('scoreWinner1').textContent = playerLabel(match.p1);
                document.getElementById('scoreWinner2').textContent = playerLabel(match.p2);

                bestOf.value = match.best_of;
                points.value = match.points;
                status.value = match.status;

                // Inactive referees only show on the match they already have
                Array.from(referee.options).forEach(function (option) {
                    const usable = option.dataset.active !== '0' || parseInt(option.value, 10) === match.referee;
                    option.hidden = !usable;
                    option.disabled = !usable;
                });
                referee.value = match.referee;
                checkReferee();
                setWinner(match.winner);
                buildGrid(match.games);
                refresh();

                dialog.showModal();

                if (scoringOpen) {
                    scoreInput(1, 1).focus();
                    scoreInput(1, 1).select();
                } else {
                    referee.focus();
                }
            }

            document.querySelectorAll('.match.scorable').forEach(function (box) {
                box.addEventListener('dblclick', function () {
                    window.getSelection().removeAllRanges();   // double-click selects the name text
                    open(box);
                });
                box.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        open(box);
                    }
                });
            });

            // One losing score typed, the other empty: the other player won the game (6 -> 11)
            grid.addEventListener('change', function (event) {
                const input = event.target;
                const place = input.name.match(/^games\[(\d+)\]\[(\d)\]$/);
                const value = input.value.trim();
                const par = parseInt(points.value, 10);

                if (!place || !/^\d+$/.test(value) || !(par >= 1)) {
                    return;
                }

                const other = scoreInput(place[1], 3 - parseInt(place[2], 10));
                const score = parseInt(value, 10);

                if (other.value.trim() === '' && score < par) {
                    other.value = par;
                    refresh();
                }
            });

            grid.addEventListener('input', refresh);
            grid.addEventListener('focusin', function (event) {
                if (event.target.tagName === 'INPUT') {
                    event.target.select();
                }
            });

            points.addEventListener('input', refresh);
            status.addEventListener('change', refresh);

            // Only a note: a referee from the same country as a player is allowed
            function checkReferee() {
                const country = referee.selectedOptions[0] ? referee.selectedOptions[0].dataset.country : '';
                const same = [match.p1, match.p2].filter(function (player) {
                    return country && player.country === country;
                });

                refereeNote.hidden = same.length === 0;
                refereeNote.innerHTML = '';

                if (same.length) {
                    const icon = element('i', 'fa-solid fa-circle-info', '');
                    refereeNote.appendChild(icon);
                    refereeNote.appendChild(document.createTextNode(' Referee is from the same country as '
                        + same.map(function (player) { return player.name; }).join(' and ') + '.'));
                }
            }

            referee.addEventListener('change', checkReferee);

            bestOf.addEventListener('change', function () {
                buildGrid(currentGames());
                refresh();
            });

            form.querySelectorAll('input[name="winner"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    autoWinner = false;
                    refresh();
                });
            });

            // F4 / F5 / F6 pick the winner, like the radio labels show
            dialog.addEventListener('keydown', function (event) {
                const keys = { F4: 0, F5: 1, F6: 2 };
                if (scoringOpen && event.key in keys) {
                    event.preventDefault();
                    setWinner(keys[event.key]);
                    autoWinner = false;
                    refresh();
                }
            });

            const clearButton = document.getElementById('scoreClear');

            clearButton && clearButton.addEventListener('click', function () {
                grid.querySelectorAll('input').forEach(function (input) { input.value = ''; });
                status.value = '';
                setWinner(0);
                autoWinner = true;
                refresh();
                scoreInput(1, 1).focus();
            });

            dialog.querySelectorAll('[data-close]').forEach(function (button) {
                button.addEventListener('click', function () { dialog.close(); });
            });

            // Click on the dark backdrop closes it
            dialog.addEventListener('click', function (event) {
                if (event.target === dialog) {
                    dialog.close();
                }
            });

            form.addEventListener('submit', function (event) {
                const result = scoringOpen ? refresh() : {};
                let problem = result.error;

                if (!scoringOpen) {
                    // Only the referee is saved
                } else if (!problem && status.value !== '' && chosenWinner() === 0) {
                    problem = 'Choose the winner of the ' + status.value.toLowerCase() + '.';
                } else if (!problem && status.value === '' && result.played < 3 && result.winner === 0 && chosenWinner() !== 0) {
                    problem = 'Enter at least 3 games before choosing the winner. If the match ended early, choose a status (e.g. Retired).';
                } else if (!problem && status.value === '' && result.winner && chosenWinner() !== 0 && chosenWinner() !== result.winner) {
                    problem = 'The score says the other player won.';
                }

                if (problem) {
                    event.preventDefault();
                    check.textContent = problem;
                    check.classList.add('is-error');
                    return;
                }

                // Come back to the same place in the bracket after saving
                try {
                    sessionStorage.setItem('drawScroll', JSON.stringify({ x: wrapper.scrollLeft, y: window.scrollY }));
                } catch (e) {}
            });

            try {
                const saved = JSON.parse(sessionStorage.getItem('drawScroll') || 'null');
                sessionStorage.removeItem('drawScroll');
                if (saved) {
                    wrapper.scrollLeft = saved.x;
                    window.scrollTo(0, saved.y);
                }
            } catch (e) {}

        })();
    </script>
    <?php endif; ?>

    <?php if ($editMode): ?>
    <script>
        // Edit Draw: swap two places by drag and drop (or click one, then the other)
        (function () {

            const editor = document.getElementById('drawEditor');
            const slots = Array.from(editor.querySelectorAll('.editor-slot'));
            const undo = document.getElementById('undoSwap');
            const error = document.getElementById('editorError');
            const history = [];
            let dragged = null;
            let picked = null;
            let changed = false;

            // Everything shown in a slot moves with the player
            function swap(a, b, remember) {
                if (a === b) {
                    return;
                }

                // The hidden input keeps its place number (name), only the value moves
                const inputA = a.querySelector('input');
                const inputB = b.querySelector('input');
                const valueA = inputA.value;
                inputA.value = inputB.value;
                inputB.value = valueA;

                ['.seed', '.player-name', '.country'].forEach(function (selector) {
                    const x = a.querySelector(selector);
                    const y = b.querySelector(selector);
                    const text = x.textContent;
                    x.textContent = y.textContent;
                    y.textContent = text;
                });

                a.classList.toggle('is-bye', inputA.value === '');
                b.classList.toggle('is-bye', inputB.value === '');

                [a, b].forEach(function (slot) {
                    slot.classList.remove('just-moved');
                    void slot.offsetWidth;          // restart the flash
                    slot.classList.add('just-moved');
                });

                if (remember) {
                    history.push([a, b]);
                }

                changed = history.length > 0;
                undo.disabled = history.length === 0;
                check();
            }

            // A match can't have two byes
            function check() {
                const problem = Array.from(editor.querySelectorAll('.editor-match')).find(function (match) {
                    return Array.from(match.querySelectorAll('input')).every(function (input) { return input.value === ''; });
                });

                error.hidden = !problem;
                error.textContent = problem ? problem.querySelector('.editor-match-no').textContent + ' has two byes - every match needs at least one player.' : '';
                document.getElementById('saveDraw').disabled = !!problem;
            }

            slots.forEach(function (slot) {

                slot.addEventListener('dragstart', function (event) {
                    dragged = slot;
                    slot.classList.add('dragging');
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', slot.dataset.place);
                });

                slot.addEventListener('dragend', function () {
                    slot.classList.remove('dragging');
                    slots.forEach(function (s) { s.classList.remove('drop-target'); });
                    dragged = null;
                });

                slot.addEventListener('dragover', function (event) {
                    if (dragged && dragged !== slot) {
                        event.preventDefault();
                        slot.classList.add('drop-target');
                    }
                });

                slot.addEventListener('dragleave', function () {
                    slot.classList.remove('drop-target');
                });

                slot.addEventListener('drop', function (event) {
                    event.preventDefault();
                    slot.classList.remove('drop-target');
                    if (dragged) {
                        swap(dragged, slot, true);
                    }
                });

                // Click (or Enter) one slot, then another
                function pick() {
                    if (picked === null) {
                        picked = slot;
                        slot.classList.add('picked');
                    } else {
                        picked.classList.remove('picked');
                        swap(picked, slot, true);
                        picked = null;
                    }
                }

                slot.addEventListener('click', pick);
                slot.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        pick();
                    }
                });
            });

            undo.addEventListener('click', function () {
                const last = history.pop();
                if (last) {
                    swap(last[0], last[1], false);
                    changed = history.length > 0;
                    undo.disabled = history.length === 0;
                }
            });

            editor.addEventListener('submit', function () {
                changed = false;
            });

            // Leaving with unsaved changes
            window.addEventListener('beforeunload', function (event) {
                if (changed) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });

        })();
    </script>
    <?php endif; ?>

</body>

</html>
