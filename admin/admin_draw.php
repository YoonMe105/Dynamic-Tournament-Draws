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
    <link rel="stylesheet" href="assets/admin_seeding.css">
    <link rel="stylesheet" href="assets/draw.css?v=<?= filemtime(__DIR__ . '/assets/draw.css') ?>">

</head>

<body>

    <?php require 'admin_navbar.php'; ?>


    <main class="container">

        <!-- ==========================================================
            PAGE HEADER
        =========================================================== -->

        <div class="page-header">

            <div>

                <h1>Tournament Draw</h1>

                <p><?= htmlspecialchars($tournament['tournament_name']) ?></p>

            </div>

            <a href="admin_each_tournament.php?id=<?= $tournamentID ?>" class="back-btn">
                Back
            </a>

        </div>


        <!-- ==========================================================
            MESSAGES
        =========================================================== -->

        <?php if (($_GET['message'] ?? '') === 'generated'): ?>

            <div class="success-message">
                The draw has been made for <?= htmlspecialchars($selectedCategory) ?>
                (<?= (int)($_GET['players'] ?? 0) ?> players).
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

                    <?php if ($selectedInfo['players'] >= 2): ?>

                        <form method="POST" action="<?= htmlspecialchars($pageUrl) ?>"
                              onsubmit="return confirmGenerate(<?= $selectedInfo['has_draw'] ? 'true' : 'false' ?>, <?= $selectedInfo['seeded'] ?>);">

                            <input type="hidden" name="action" value="generate">
                            <input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory) ?>">

                            <button type="submit" class="btn">
                                <?= $selectedInfo['has_draw'] ? 'Remake Draw' : 'Make Draw' ?>
                            </button>

                        </form>

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

            <?php elseif ($selectedInfo['seeded'] === 0 && !$selectedInfo['has_draw']): ?>

                <div class="warning-message">
                    No seeds are set for <?= htmlspecialchars($selectedCategory) ?> yet &ndash; every player will be drawn at random.
                    Run the seeding first if you want seeded players.
                </div>

            <?php endif; ?>

            <?php if ($drawOutOfDate): ?>

                <div class="warning-message">
                    This draw has <?= $drawPlayerCount ?> players, but <?= $selectedInfo['players'] ?> players are endorsed now.
                    Remake the draw to include the changes.
                </div>

            <?php endif; ?>


            <!-- ==========================================================
                BRACKET
            =========================================================== -->

            <?php if ($draw): ?>

                <div class="bracket-wrapper">
                    <?php renderBracket($draw); ?>
                </div>

            <?php elseif ($selectedInfo['players'] >= 2): ?>

                <div class="empty-draw">
                    <h2>No draw yet</h2>
                    <p>Press <strong>Make Draw</strong> to place the players for <?= htmlspecialchars($selectedCategory) ?>.</p>
                </div>

            <?php endif; ?>

        <?php endif; ?>

    </main>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="assets/bracket_download.js?v=<?= filemtime(__DIR__ . '/assets/bracket_download.js') ?>"></script>

    <script>
        function confirmGenerate(hasDraw, seeded) {

            let message = hasDraw
                ? 'Remake the draw? The current draw will be replaced with a new one.'
                : 'Make the draw for this category?';

            if (seeded === 0) {
                message += '\n\nNo seeds are set, so every player will be drawn at random.';
            }

            return confirm(message);
        }
    </script>

</body>

</html>
