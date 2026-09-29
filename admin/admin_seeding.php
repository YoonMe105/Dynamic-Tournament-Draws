<?php
session_start();

require '../db.php';

if (!isset($_SESSION["userid"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| TOURNAMENT ID
|--------------------------------------------------------------------------
*/

$tournamentID = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($tournamentID <= 0) {
    header("Location: admin_index.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| GET TOURNAMENT
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM tournament
    WHERE tournamentID = ?
");

$stmt->bind_param("i", $tournamentID);
$stmt->execute();

$tournamentResult = $stmt->get_result();
$tournament = $tournamentResult->fetch_assoc();

$stmt->close();

if (!$tournament) {
    echo "Tournament not found.";
    exit;
}

/*
|--------------------------------------------------------------------------
| SEEDING RULE
|--------------------------------------------------------------------------
| Which rankings decide the seeds depends on where the tournament is held
| and its type. The first ranking is the primary one; the next ones are
| only used to order players with the same (or no) primary ranking.
|
|   Malaysia  + Local          -> National, then AJSS
|   Malaysia  + International  -> AJSS, then National
|   Singapore / other country  -> National
*/

$rankingColumns = [
    'world_ranking' => '(PSA) World ranking',
    'national_ranking' => 'National ranking',
    'ajss_ranking' => 'AJSS ranking'
];

$seedCountry = strtolower(trim($tournament['tournament_country'] ?? ''));
$seedType = strtolower(trim($tournament['tournament_type'] ?? ''));

// "international" also contains "national", so check it first
if (strpos($seedType, 'international') !== false) {
    $seedType = 'international';
} elseif (strpos($seedType, 'local') !== false) {
    $seedType = 'local';
}

if ($seedCountry === 'malaysia' && $seedType === 'local') {

    $seedingOrder = ['national_ranking', 'ajss_ranking'];

} elseif ($seedCountry === 'malaysia' && $seedType === 'international') {

    $seedingOrder = ['ajss_ranking', 'national_ranking'];

} else {

    $seedingOrder = ['national_ranking'];
}

$seedingRuleText = implode(', then ', array_map(function ($column) use ($rankingColumns) {
    return $rankingColumns[$column];
}, $seedingOrder));

/*
| ORDER BY for the rule: numeric rankings first (lowest = best),
| missing or non-numeric rankings last.
*/

$seedingOrderSQL = implode(",\n", array_map(function ($column) {
    return "CASE
                WHEN p.$column REGEXP '^[0-9]+$'
                THEN CAST(p.$column AS UNSIGNED)
                ELSE 999999
            END ASC";
}, $seedingOrder));

/*
|--------------------------------------------------------------------------
| AUTOMATIC SEEDING
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'automatic_seeding'
) {

    /*
    |--------------------------------------------------------------------------
    | CATEGORY
    |--------------------------------------------------------------------------
    | Only the chosen category is reseeded, so seeds in other categories
    | (including manual changes) are kept.
    */

    $seedCategory = trim($_POST['category'] ?? '');

    if ($seedCategory === '') {
        header("Location: admin_seeding.php?id=" . $tournamentID);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | GET ENDORSED PLAYERS
    |--------------------------------------------------------------------------
    */

    $seedStmt = $conn->prepare("
        SELECT
            tr.registrationID,
            tr.category_registered,
            p.world_ranking,
            p.national_ranking,
            p.ajss_ranking
        FROM tournament_register tr
        JOIN players p
            ON tr.playerID = p.playerID
        WHERE tr.tournamentID = ?
        AND tr.category_registered = ?
        AND UPPER(TRIM(tr.endorsement)) = 'ENDORSED'
        ORDER BY
            $seedingOrderSQL,
            tr.registrationID ASC
    ");

    $seedStmt->bind_param("is", $tournamentID, $seedCategory);
    $seedStmt->execute();

    $seedResult = $seedStmt->get_result();

    /*
    |--------------------------------------------------------------------------
    | RESET EXISTING SEEDS
    |--------------------------------------------------------------------------
    */

    $resetStmt = $conn->prepare("
        UPDATE tournament_register
        SET seed_number = NULL
        WHERE tournamentID = ?
        AND category_registered = ?
    ");

    $resetStmt->bind_param("is", $tournamentID, $seedCategory);
    $resetStmt->execute();

    $resetStmt->close();

    /*
    |--------------------------------------------------------------------------
    | ASSIGN SEEDS
    |--------------------------------------------------------------------------
    */

    $updateStmt = $conn->prepare("
        UPDATE tournament_register
        SET seed_number = ?
        WHERE registrationID = ?
        AND tournamentID = ?
    ");

    $seedNumber = 0;

    while ($player = $seedResult->fetch_assoc()) {

        $seedNumber++;

        $registrationID = intval($player['registrationID']);

        $updateStmt->bind_param(
            "iii",
            $seedNumber,
            $registrationID,
            $tournamentID
        );

        $updateStmt->execute();
    }

    $updateStmt->close();
    $seedStmt->close();

    header(
        "Location: admin_seeding.php?id="
        . $tournamentID
        . "&category=" . urlencode($seedCategory)
        . "&message=automatic"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| SAVE MANUAL SEEDING
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'save_seeding'
) {

    if (isset($_POST['seed']) && is_array($_POST['seed'])) {

        $updateStmt = $conn->prepare("
            UPDATE tournament_register
            SET seed_number = ?
            WHERE registrationID = ?
            AND tournamentID = ?
        ");

        foreach ($_POST['seed'] as $registrationID => $seed) {

            $registrationID = intval($registrationID);

            /*
            |--------------------------------------------------------------------------
            | EMPTY SEED
            |--------------------------------------------------------------------------
            */

            if ($seed === '') {

                $nullStmt = $conn->prepare("
                    UPDATE tournament_register
                    SET seed_number = NULL
                    WHERE registrationID = ?
                    AND tournamentID = ?
                ");

                $nullStmt->bind_param(
                    "ii",
                    $registrationID,
                    $tournamentID
                );

                $nullStmt->execute();
                $nullStmt->close();

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDATE SEED
            |--------------------------------------------------------------------------
            */

            $seedNumber = intval($seed);

            if ($seedNumber < 1) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE SEED
            |--------------------------------------------------------------------------
            */

            $updateStmt->bind_param(
                "iii",
                $seedNumber,
                $registrationID,
                $tournamentID
            );

            $updateStmt->execute();
        }

        $updateStmt->close();
    }

    header(
        "Location: admin_seeding.php?id="
        . $tournamentID
        . "&category=" . urlencode(trim($_POST['category'] ?? ''))
        . "&message=saved"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| GET CATEGORIES
|--------------------------------------------------------------------------
*/

$categoryStmt = $conn->prepare("
    SELECT DISTINCT category_registered
    FROM tournament_register
    WHERE tournamentID = ?
    AND category_registered IS NOT NULL
    AND TRIM(category_registered) != ''
    ORDER BY category_registered ASC
");

$categoryStmt->bind_param("i", $tournamentID);
$categoryStmt->execute();

$categoryResult = $categoryStmt->get_result();

/*
|--------------------------------------------------------------------------
| STORE CATEGORIES
|--------------------------------------------------------------------------
*/

$categories = [];

while ($category = $categoryResult->fetch_assoc()) {

    $categoryName = trim($category['category_registered']);

    if ($categoryName !== '') {
        $categories[] = $categoryName;
    }
}

$categoryStmt->close();

/*
|--------------------------------------------------------------------------
| FIRST CATEGORY
|--------------------------------------------------------------------------
*/

$firstCategory = '';

if (!empty($categories)) {
    $firstCategory = in_array($_GET['category'] ?? '', $categories, true)
        ? $_GET['category']
        : $categories[0];
}

/*
|--------------------------------------------------------------------------
| GET PLAYERS
|--------------------------------------------------------------------------
*/

$playerStmt = $conn->prepare("
    SELECT
        tr.registrationID,
        tr.category_registered,
        tr.seed_number,
        tr.endorsement,
        p.playerID,
        p.player_full_name,
        p.player_nationality,
        p.world_ranking,
        p.national_ranking,
        p.ajss_ranking
    FROM tournament_register tr
    JOIN players p
        ON tr.playerID = p.playerID
    WHERE tr.tournamentID = ?
    ORDER BY
        tr.category_registered ASC,
        CASE
            WHEN tr.seed_number IS NULL THEN 999999
            ELSE tr.seed_number
        END ASC,
        p.player_full_name ASC
");

$playerStmt->bind_param("i", $tournamentID);
$playerStmt->execute();

$playersResult = $playerStmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Seeding -
        <?= htmlspecialchars($tournament['tournament_name']) ?>
    </title>

    <link rel="stylesheet" href="assets/style.css">

    <link rel="stylesheet" href="assets/each_tournament.css">
    <link rel="stylesheet" href="assets/admin_seeding.css">

</head>

<body>

    <?php require 'admin_navbar.php'; ?>


    <main class="container">

        <!-- ==========================================================
            PAGE HEADER
        =========================================================== -->

        <div class="page-header">

            <div>

                <h1>
                    Tournament Seeding
                </h1>

                <p>
                    <?= htmlspecialchars($tournament['tournament_name']) ?>
                </p>

                <p class="seeding-rule">
                    Automatic seeding uses:
                    <strong><?= htmlspecialchars($seedingRuleText) ?></strong>
                    <?php if (empty($tournament['tournament_country'])): ?>
                        <span class="seeding-rule-note">
                            (no country set for this tournament &ndash; set it on the tournament page to apply the country rules)
                        </span>
                    <?php endif; ?>
                </p>

            </div>


            <a
                href="admin_each_tournament.php?id=<?= $tournamentID ?>"
                class="back-btn"
            >
                Back
            </a>

        </div>


        <!-- ==========================================================
            SUCCESS MESSAGE
        =========================================================== -->

        <?php if (isset($_GET['message'])): ?>

            <?php if ($_GET['message'] === 'automatic'): ?>

                <div class="success-message">
                    Automatic seeding has been generated successfully.
                </div>

            <?php elseif ($_GET['message'] === 'saved'): ?>

                <div class="success-message">
                    Seeding changes have been saved successfully.
                </div>

            <?php endif; ?>

        <?php endif; ?>


        <!-- ==========================================================
            ACTION BUTTONS
        =========================================================== -->

        <div class="seeding-actions">

            <form
                method="POST"
                action="admin_seeding.php?id=<?= $tournamentID ?>"
                onsubmit="return confirmSeeding();"
            >

                <input
                    type="hidden"
                    name="action"
                    value="automatic_seeding"
                >

                <input
                    type="hidden"
                    name="category"
                    class="selected-category-input"
                    value="<?= htmlspecialchars($firstCategory) ?>"
                >

                <button
                    type="submit"
                    class="btn"
                >
                    Automatic Seeding
                </button>

            </form>


            <a
                href="#"
                class="btn"
                id="downloadReportBtn"
                target="_blank"
            >
                Download Report
            </a>

        </div>


        <!-- ==========================================================
            CATEGORY SELECT
        =========================================================== -->

        <?php if (!empty($categories)): ?>

            <div class="category-filter">

                <label for="categorySelect">
                    Choose Category
                </label>


                <select
                    id="categorySelect"
                    onchange="filterCategory()"
                >

                    <?php foreach ($categories as $categoryName): ?>

                        <option
                            value="<?= htmlspecialchars($categoryName) ?>"
                            <?= $categoryName === $firstCategory ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($categoryName) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        <?php endif; ?>


        <!-- ==========================================================
            SEEDING FORM
        =========================================================== -->

        <form
            method="POST"
            action="admin_seeding.php?id=<?= $tournamentID ?>"
        >

            <input
                type="hidden"
                name="action"
                value="save_seeding"
            >

            <input
                type="hidden"
                name="category"
                class="selected-category-input"
                value="<?= htmlspecialchars($firstCategory) ?>"
            >


            <!-- ======================================================
                TABLE
            ======================================================= -->

            <div class="table-wrapper">

                <table class="players-table">

                    <thead>

                        <tr>

                            <th>No.</th>

                            <th>Player</th>

                            <th>Category</th>

                            <th>Nationality</th>

                            <th>World Ranking</th>

                            <th>National Ranking</th>

                            <th>Regional Ranking(AJSS)</th>

                            <th>Seed</th>

                            <th>Endorsement</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if ($playersResult->num_rows > 0): ?>

                            <?php $no = 1; ?>


                            <?php while ($player = $playersResult->fetch_assoc()): ?>

                                <tr
                                    data-category="<?= htmlspecialchars($player['category_registered']) ?>"
                                >

                                    <!-- NO. -->

                                    <td class="row-number">
                                        <?= $no++ ?>
                                    </td>


                                    <!-- PLAYER -->

                                    <td>
                                        <?= htmlspecialchars(
                                            $player['player_full_name']
                                        ) ?>
                                    </td>


                                    <!-- CATEGORY -->

                                    <td>
                                        <?= htmlspecialchars(
                                            $player['category_registered']
                                        ) ?>
                                    </td>


                                    <!-- NATIONALITY -->

                                    <td>
                                        <?= htmlspecialchars(
                                            $player['player_nationality']
                                        ) ?>
                                    </td>


                                    <!-- WORLD RANKING -->

                                    <td>

                                        <?php if (
                                            $player['world_ranking'] !== null
                                            && trim($player['world_ranking']) !== ''
                                        ): ?>

                                            <?= htmlspecialchars(
                                                $player['world_ranking']
                                            ) ?>

                                        <?php else: ?>

                                            -

                                        <?php endif; ?>

                                    </td>


                                    <!-- NATIONAL RANKING -->

                                    <td>

                                        <?php if (
                                            $player['national_ranking'] !== null
                                            && trim($player['national_ranking']) !== ''
                                        ): ?>

                                            <?= htmlspecialchars(
                                                $player['national_ranking']
                                            ) ?>

                                        <?php else: ?>

                                            -

                                        <?php endif; ?>

                                    </td>


                                    <!-- AJSS RANKING -->

                                    <td>

                                        <?php if (
                                            $player['ajss_ranking'] !== null
                                            && trim($player['ajss_ranking']) !== ''
                                        ): ?>

                                            <?= htmlspecialchars(
                                                $player['ajss_ranking']
                                            ) ?>

                                        <?php else: ?>

                                            -

                                        <?php endif; ?>

                                    </td>


                                    <!-- SEED -->

                                    <td>

                                        <input
                                            type="number"
                                            name="seed[<?= (int)$player['registrationID'] ?>]"
                                            value="<?= $player['seed_number'] !== null ? (int)$player['seed_number'] : '' ?>"
                                            min="1"
                                        >

                                    </td>


                                    <!-- ENDORSEMENT -->

                                    <td>

                                        <?php if (
                                            strtoupper(
                                                trim(
                                                    $player['endorsement'] ?? ''
                                                )
                                            ) === 'ENDORSED'
                                        ): ?>

                                            <span class="status-endorsed">
                                                ENDORSED
                                            </span>

                                        <?php else: ?>

                                            <span class="status-pending">
                                                PENDING
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endwhile; ?>


                        <?php else: ?>

                            <tr>

                                <td
                                    colspan="9"
                                    style="text-align:center;"
                                >
                                    No registered players found.
                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>


            <!-- ======================================================
                SAVE BUTTON
            ======================================================= -->

            <div class="save-section">

                <button
                    type="submit"
                    class="btn"
                >
                    Save Seeding
                </button>

            </div>

        </form>

    </main>


<script>

/*
|--------------------------------------------------------------------------
| CONFIRM AUTOMATIC SEEDING
|--------------------------------------------------------------------------
*/

function confirmSeeding() {

    const categorySelect = document.getElementById('categorySelect');

    const category = categorySelect ? categorySelect.value : '';

    return confirm(
        "Generate automatic seeding for " + category + "? Existing seed numbers in this category will be replaced."
    );

}


/*
|--------------------------------------------------------------------------
| FILTER CATEGORY
|--------------------------------------------------------------------------
*/

function filterCategory() {

    const categorySelect = document.getElementById(
        'categorySelect'
    );

    if (!categorySelect) {
        return;
    }

    const selectedCategory = categorySelect.value;

    document.querySelectorAll('.selected-category-input').forEach(function(input) {
        input.value = selectedCategory;
    });

    const rows = document.querySelectorAll(
        '.players-table tbody tr[data-category]'
    );

    let number = 1;

    rows.forEach(function(row) {

        const rowCategory = row.getAttribute(
            'data-category'
        );

        const numberCell = row.querySelector(
            '.row-number'
        );

        if (rowCategory === selectedCategory) {

            row.style.display = '';

            if (numberCell) {

                numberCell.textContent = number;

                number++;

            }

        } else {

            row.style.display = 'none';

        }

    });

    updateReportLink();

}


/*
|--------------------------------------------------------------------------
| UPDATE REPORT LINK
|--------------------------------------------------------------------------
*/

function updateReportLink() {

    const categorySelect = document.getElementById(
        'categorySelect'
    );

    const downloadButton = document.getElementById(
        'downloadReportBtn'
    );

    if (!categorySelect || !downloadButton) {
        return;
    }

    const selectedCategory = categorySelect.value;

    downloadButton.href =
        'admin_seeding_report.php'
        + '?tournamentID=<?= $tournamentID ?>'
        + '&category='
        + encodeURIComponent(selectedCategory);
}


/*
|--------------------------------------------------------------------------
| INITIALIZE PAGE
|--------------------------------------------------------------------------
*/

document.addEventListener('DOMContentLoaded', function() {

    filterCategory();

});

</script>


</body>

</html>