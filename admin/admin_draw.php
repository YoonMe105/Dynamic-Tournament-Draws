<?php

require_once '../db.php';
require_once 'admin_auth.php';

// Platform Admins, or the admin who created this tournament
requireTournamentAccess($_GET['id'] ?? 0);
require_once "admin_draw_functions.php";



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

                    <?php if ($selectedInfo['has_draw']): ?>

                        <form method="POST" action="<?= htmlspecialchars($pageUrl) ?>"
                              onsubmit="return confirm('Delete the draw for <?= htmlspecialchars($selectedCategory, ENT_QUOTES) ?>?');">

                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory) ?>">

                            <button type="submit" class="btn btn-danger">
                                Delete Draw
                            </button>

                        </form>

                    <?php endif; ?>

                    <a href="admin_seeding.php?id=<?= $tournamentID ?>&category=<?= urlencode($selectedCategory) ?>" class="back-btn">
                        Manage Seeding
                    </a>

                    <?php if ($draw): ?>

                        <a href="admin_draw_report.php?id=<?= $tournamentID ?>&category=<?= urlencode($selectedCategory) ?>"
                           class="back-btn" target="_blank">
                            Download Report
                        </a>

                        <button type="button" class="back-btn download-btn"
                                onclick="downloadBracket(this, <?= htmlspecialchars(json_encode(bracketFileName($tournament, $selectedCategory)), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($tournament['tournament_name'] . ' - ' . $selectedCategory), ENT_QUOTES) ?>)">
                            Download Bracket
                        </button>

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
                    <p class="locked-note"><i class="fa-solid fa-lock"></i> Matches already have results, so the draw can't be edited any more.</p>
                <?php endif; ?>

                <div class="bracket-wrapper">
                    <?php renderBracket($draw); ?>
                </div>

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
