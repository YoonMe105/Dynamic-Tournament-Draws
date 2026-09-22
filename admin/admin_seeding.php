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
        AND UPPER(TRIM(tr.endorsement)) = 'ENDORSED'
        ORDER BY
            tr.category_registered ASC,
            CASE
                WHEN p.world_ranking REGEXP '^[0-9]+$'
                THEN CAST(p.world_ranking AS UNSIGNED)
                ELSE 999999
            END ASC,
            CASE
                WHEN p.national_ranking REGEXP '^[0-9]+$'
                THEN CAST(p.national_ranking AS UNSIGNED)
                ELSE 999999
            END ASC,
            CASE
                WHEN p.ajss_ranking REGEXP '^[0-9]+$'
                THEN CAST(p.ajss_ranking AS UNSIGNED)
                ELSE 999999
            END ASC,
            tr.registrationID ASC
    ");

    $seedStmt->bind_param("i", $tournamentID);
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
    ");

    $resetStmt->bind_param("i", $tournamentID);
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

    $currentCategory = '';
    $seedNumber = 0;

    while ($player = $seedResult->fetch_assoc()) {

        if ($currentCategory !== $player['category_registered']) {

            $currentCategory = $player['category_registered'];
            $seedNumber = 1;

        } else {

            $seedNumber++;

        }

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
    $firstCategory = $categories[0];
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

                            <th>AJSS Ranking</th>

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

    return confirm(
        "Generate automatic seeding? Existing seed numbers will be replaced."
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