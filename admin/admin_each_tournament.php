<?php
session_start();

require '../db.php';

if (!isset($_SESSION["userid"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}


$tournamentID = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($tournamentID <= 0) {
    header("Location: admin_index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| HANDLE ENDORSEMENT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrationID']) && isset($_POST['endorsement'])) {

    $registrationID = intval($_POST['registrationID']);
    $endorsement = trim($_POST['endorsement']);

    $endorsementDate = !empty($_POST['endorsement_date'])
        ? $_POST['endorsement_date']
        : date('Y-m-d');


    if ($registrationID > 0 && $endorsement === 'ENDORSED') {

        $endorseStmt = $conn->prepare("
            UPDATE tournament_register
            SET endorsement = ?, endorsement_date = ?
            WHERE registrationID = ?
            AND tournamentID = ?
        ");

        $endorseStmt->bind_param(
            "ssii",
            $endorsement,
            $endorsementDate,
            $registrationID,
            $tournamentID
        );


        if ($endorseStmt->execute()) {

            echo json_encode([
                'success' => true,
                'message' => 'Player endorsed successfully.'
            ]);

        } else {

            echo json_encode([
                'success' => false,
                'message' => 'Failed to endorse player.'
            ]);

        }

        $endorseStmt->close();

    } else {

        echo json_encode([
            'success' => false,
            'message' => 'Invalid endorsement request.'
        ]);

    }

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
| GET SEEDING CATEGORIES
|--------------------------------------------------------------------------
*/

$categoryStmt = $conn->prepare("
    SELECT DISTINCT category_registered
    FROM tournament_register
    WHERE tournamentID = ?
      AND category_registered IS NOT NULL
      AND category_registered <> ''
    ORDER BY category_registered ASC
");

$categoryStmt->bind_param("i", $tournamentID);
$categoryStmt->execute();

$categoryResult = $categoryStmt->get_result();

$categories = [];

while ($categoryRow = $categoryResult->fetch_assoc()) {
    $categories[] = $categoryRow['category_registered'];
}

$categoryStmt->close();

/*
|--------------------------------------------------------------------------
| SELECTED SEEDING CATEGORY
|--------------------------------------------------------------------------
| If no category is selected, automatically show the first category.
*/

$selectedCategory = $_GET['category'] ?? '';

if ($selectedCategory === '' && !empty($categories)) {
    $selectedCategory = $categories[0];
}

if (!in_array($selectedCategory, $categories, true)) {
    $selectedCategory = !empty($categories) ? $categories[0] : '';
}


/*
|--------------------------------------------------------------------------
| GET REGISTERED PLAYERS
|--------------------------------------------------------------------------
*/

$playerStmt = $conn->prepare("
    SELECT
        tr.registrationID,
        tr.category_registered,
        tr.category_remarks,
        tr.player_tshirt,
        tr.accommodation,
        tr.amount_paid,
        tr.payment_date,
        tr.payment_status,
        tr.endorsement,
        tr.endorsement_date,
        tr.admin_remark,
        tr.seed_number,
        p.playerID,
        p.player_full_name,
        p.player_email,
        p.player_contact,
        p.player_gender,
        p.player_nationality
    FROM tournament_register tr
    JOIN players p ON tr.playerID = p.playerID
    WHERE tr.tournamentID = ?
    ORDER BY tr.registrationID DESC
");

$playerStmt->bind_param("i", $tournamentID);
$playerStmt->execute();

$playersResult = $playerStmt->get_result();


/*
|--------------------------------------------------------------------------
| OVERVIEW DATA
|--------------------------------------------------------------------------
*/

// Tournament categories as short codes (BU13, GU11, Men, Women)
$overviewStmt = $conn->prepare("
    SELECT category_name
    FROM tournament_category
    WHERE tournamentID = ?
");

$overviewStmt->bind_param("i", $tournamentID);
$overviewStmt->execute();

$overviewCategories = [];

foreach ($overviewStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {

    $name = trim($row['category_name']);

    if (preg_match('/\b(boys?|girls?)\b.*?\bunder\s*(\d+)/i', $name, $matches)) {
        $name = (strtolower($matches[1][0]) === 'b' ? 'B' : 'G') . 'U' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
    }

    $overviewCategories[$name] = true;
}

$overviewStmt->close();

$overviewCategories = array_keys($overviewCategories);

$overviewCategoryType = array_intersect(['Men', 'Women'], $overviewCategories)
    ? 'PSA'
    : (empty($overviewCategories) ? '' : 'Junior');

// Registration status
$deadlineTime = strtotime($tournament['tournament_deadline'] ?? '');
$registrationOpen = $deadlineTime !== false && $deadlineTime > 0 && $deadlineTime >= time();
$daysLeft = $registrationOpen ? (int) ceil(($deadlineTime - time()) / 86400) : 0;

// Picture: new uploads store a file name, older tournaments store a path
$overviewPicture = '';

if (!empty($tournament['tournament_picture'])) {
    $overviewPicture = strpos($tournament['tournament_picture'], '../') === 0
        ? $tournament['tournament_picture']
        : '../uploads/tournaments/' . $tournament['tournament_picture'];
}

function overviewDate($value, $withTime = true)
{
    $time = strtotime($value ?? '');

    if ($time === false || $time <= 0) {
        return '-';
    }

    return date($withTime ? 'd M Y, h:i A' : 'd M Y', $time);
}


/*
|--------------------------------------------------------------------------
| WRONG AGE CATEGORY
|--------------------------------------------------------------------------
| Registrations whose player is too old for their category on the age
| cut-off date. Skipped when the tournament has no cut-off date.
*/

require_once 'admin_age_check.php';

require_once '../fees.php';

$wrongCategories = [];

if (!empty($tournament['tournament_age_cutoff'])) {

    $ageStmt = $conn->prepare("
        SELECT
            tr.registrationID,
            tr.category_registered,
            p.player_full_name,
            p.player_dob
        FROM tournament_register tr
        JOIN players p ON tr.playerID = p.playerID
        WHERE tr.tournamentID = ?
    ");

    $ageStmt->bind_param("i", $tournamentID);
    $ageStmt->execute();

    foreach ($ageStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {

        $wrong = wrongAgeCategory(
            $row['category_registered'],
            $row['player_dob'],
            $tournament['tournament_age_cutoff']
        );

        if ($wrong !== null) {
            $wrongCategories[$row['registrationID']] = $wrong + [
                'name' => $row['player_full_name'],
                'category' => $row['category_registered'],
                'correct' => correctAgeCategory($row['category_registered'], $wrong['age'], $overviewCategories)
            ];
        }
    }

    $ageStmt->close();
}

// Category badge, marked when the player is too old for it
function categoryBadge($registrationID, $category, $wrongCategories)
{
    $category = htmlspecialchars($category ?? '-');

    if (!isset($wrongCategories[$registrationID])) {
        return '<span class="category-badge">' . $category . '</span>';
    }

    $wrong = $wrongCategories[$registrationID];

    $title = $wrong['correct'] !== null
        ? 'Wrong category. The correct category is ' . $wrong['correct'] . '.'
        : 'Wrong category. There is no category in this tournament for age ' . $wrong['age'] . '.';

    return '<span class="category-badge category-wrong" title="' . htmlspecialchars($title) . '">'
        . '<i class="fa-solid fa-triangle-exclamation"></i> ' . $category
        . '</span>';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= htmlspecialchars($tournament['tournament_name']) ?>
    </title>

    <link href="./assets/style.css" rel="stylesheet" type="text/css">

    <link href="./assets/each_tournaments.css" rel="stylesheet" type="text/css">

    <!-- Font Awesome (detail icons) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

</head>

<body>


<?php require_once 'admin_navbar.php'; ?>


<main class="container">


    <!-- ==========================================================
         SECTION 1: TOURNAMENT DETAILS
    =========================================================== -->

    <a href="admin_index.php" class="back-link">
        <i class="fa-solid fa-arrow-left"></i>
        Back to Tournaments
    </a>


    <section class="tournament-section tournament-overview">


        <!-- HEADER -->

        <div class="section-header overview-header">

            <p class="section-subtitle overview-eyebrow">
                Tournament Details
            </p>

            <a href="admin_view_tournaments.php?id=<?= (int) $tournamentID ?>" class="back-btn view-details-btn">
                <i class="fa-solid fa-eye"></i>
                View Details
            </a>

        </div>


        <div class="overview-hero">


            <!-- LEFT: PICTURE -->

            <div class="overview-image">

                <?php if ($overviewPicture !== ''): ?>

                    <img
                        src="<?= htmlspecialchars($overviewPicture) ?>"
                        alt="<?= htmlspecialchars($tournament['tournament_name']) ?>"
                        onerror="this.remove()"
                    >

                <?php endif; ?>

                <i class="fa-solid fa-trophy"></i>

            </div>


            <!-- RIGHT: DETAILS -->

            <div class="overview-main">

                <div class="overview-badges">

                    <?php if (!empty($tournament['tournament_type'])): ?>
                        <span class="overview-badge badge-type"><?= htmlspecialchars(strtoupper($tournament['tournament_type'])) ?></span>
                    <?php endif; ?>

                    <?php if ($overviewCategoryType !== ''): ?>
                        <span class="overview-badge badge-category-type"><?= htmlspecialchars($overviewCategoryType) ?></span>
                    <?php endif; ?>

                    <span class="overview-badge <?= $registrationOpen ? 'badge-open' : 'badge-closed' ?>">
                        <?= $registrationOpen
                            ? 'Open &middot; ' . $daysLeft . ($daysLeft === 1 ? ' day left' : ' days left')
                            : 'Registration Closed' ?>
                    </span>

                    <span class="overview-badge badge-players">
                        <i class="fa-solid fa-users"></i>
                        <?= $playersResult->num_rows ?> <?= $playersResult->num_rows === 1 ? 'player' : 'players' ?>
                    </span>

                </div>


                <h1><?= htmlspecialchars($tournament['tournament_name']) ?></h1>


                <p class="overview-location">
                    <i class="fa-solid fa-location-dot"></i>
                    <?= htmlspecialchars($tournament['tournament_location'] ?: '-') ?><?php if (!empty($tournament['tournament_country'])): ?>,
                        <strong><?= htmlspecialchars($tournament['tournament_country']) ?></strong>
                    <?php endif; ?>
                </p>


                <dl class="tournament-details">

                    <div class="detail-item">
                        <dt><i class="fa-solid fa-calendar-day"></i> Start</dt>
                        <dd><?= overviewDate($tournament['tournament_startdate']) ?></dd>
                    </div>

                    <div class="detail-item">
                        <dt><i class="fa-solid fa-flag-checkered"></i> End</dt>
                        <dd><?= overviewDate($tournament['tournament_enddate']) ?></dd>
                    </div>

                    <div class="detail-item">
                        <dt><i class="fa-solid fa-hourglass-half"></i> Deadline</dt>
                        <dd><?= overviewDate($tournament['tournament_deadline']) ?></dd>
                    </div>

                    <div class="detail-item">
                        <dt><i class="fa-solid fa-cake-candles"></i> Age Cut-off</dt>
                        <dd><?= overviewDate($tournament['tournament_age_cutoff'], false) ?></dd>
                    </div>

                    <div class="detail-item">
                        <dt><i class="fa-solid fa-money-bill-wave"></i> Entry Fee</dt>
                        <dd>
                            <?php $feeParts = splitFee($tournament['tournament_fee'] ?? ''); ?>
                            <?php if ($feeParts['foreign'] !== null): ?>
                                <span class="fee-line">Local: <?= htmlspecialchars($feeParts['local']) ?></span>
                                <span class="fee-line">Foreign: <?= htmlspecialchars($feeParts['foreign']) ?></span>
                            <?php else: ?>
                                <?= htmlspecialchars($tournament['tournament_fee'] ?: '-') ?>
                            <?php endif; ?>
                        </dd>
                    </div>

                    <div class="detail-item">
                        <dt><i class="fa-solid fa-shirt"></i> T-Shirt</dt>
                        <dd><?= htmlspecialchars($tournament['tournament_tshirt_size'] ?: '-') ?></dd>
                    </div>

                </dl>


                <?php if (!empty($overviewCategories)): ?>

                    <div class="overview-chips">

                        <?php foreach ($overviewCategories as $categoryCode): ?>
                            <span class="overview-chip"><?= htmlspecialchars($categoryCode) ?></span>
                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>


                <?php if (!empty($tournament['tournament_description']) || !empty($tournament['tournament_detail_link'])): ?>

                    <div class="overview-more">

                        <?php if (!empty($tournament['tournament_description'])): ?>

                            <details class="overview-description">

                                <summary>Show description</summary>

                                <p><?= nl2br(htmlspecialchars($tournament['tournament_description'])) ?></p>

                            </details>

                        <?php endif; ?>

                        <?php if (!empty($tournament['tournament_detail_link'])): ?>

                            <a
                                class="detail-link"
                                href="<?= htmlspecialchars($tournament['tournament_detail_link']) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                Tournament link
                            </a>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </section>


    <!-- ==========================================================
         SECTION 2: REGISTERED PLAYERS
         NEW PAYMENT / ENDORSEMENT SECTION
    =========================================================== -->

    <section class="players-section">


        <!-- SECTION HEADER -->

        <div class="section-header">

            <div>

                <h2>
                    Registered Players
                </h2>

                <p class="section-subtitle">
                    Players registered for this tournament
                </p>

            </div>


            <div class="player-count">
                <?= $playersResult->num_rows ?> Players
            </div>

        </div>


        <!-- WRONG AGE CATEGORY -->

        <?php if (!empty($wrongCategories)): ?>

            <div class="wrong-category-alert" role="alert">

                <div class="wrong-category-title">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <?= count($wrongCategories) ?>
                    <?= count($wrongCategories) === 1 ? 'player is' : 'players are' ?>
                    in the wrong category for their age on the cut-off date
                    (<?= overviewDate($tournament['tournament_age_cutoff'], false) ?>)
                </div>

                <ul>
                    <?php foreach ($wrongCategories as $wrong): ?>
                        <li>
                            <strong><?= htmlspecialchars($wrong['name']) ?></strong>
                            is currently registered in <strong><?= htmlspecialchars($wrong['category']) ?></strong>,
                            <?php if ($wrong['correct'] !== null): ?>
                                but the correct category is <strong><?= htmlspecialchars($wrong['correct']) ?></strong>.
                            <?php else: ?>
                                but there is no category in this tournament for age <?= $wrong['age'] ?>.
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

            </div>


            <script>
                window.addEventListener('load', function () {
                    alert(<?= json_encode(
                        "Wrong category!\n\n"
                        . implode("\n", array_map(function ($wrong) {
                            return '- ' . wrongCategoryMessage($wrong['name'], $wrong['category'], $wrong['correct'], $wrong['age']);
                        }, $wrongCategories))
                        . "\n\nAge cut-off date: " . overviewDate($tournament['tournament_age_cutoff'], false)
                    ) ?>);
                });
            </script>

        <?php endif; ?>


        <!-- ======================================================
             PAYMENT / ENDORSEMENT
        ======================================================= -->

        <div class="tournament_payment_player">

            <div class="tournament_detail">


                <!-- ==================================================
                     TAB NAVIGATION
                =================================================== -->

                <div class="payment-nav">

                    <div class="nav active_now" id="nav1">
                        Payment
                    </div>


                    <div class="nav inactive_now" id="nav2">
                        Endorsement
                    </div>

                </div>


                <div class="horizontal_divider"></div>


                <!-- ==================================================
                     PAYMENT SECTION
                =================================================== -->

                <div class="tournament_payment_detail" id="paymentbg">


                    <!-- FILTER -->

                    <div class="filter">

                        <div class="spacer"></div>


                        <select id="payment_filter" onchange="filterPlayers()">

                            <option value="all">
                                Payment - All
                            </option>

                            <option value="PAID">
                                Paid
                            </option>

                            <option value="PENDING">
                                Pending
                            </option>

                            <option value="NOT PAID">
                                Not Paid
                            </option>

                            <option value="REFUNDED">
                                Refunded
                            </option>

                        </select>

                    </div>


                    <!-- PAYMENT TABLE -->

                    <div id="paymentList">


                        <?php

                        /*
                        |--------------------------------------------------------------------------
                        | RESET QUERY RESULT
                        |--------------------------------------------------------------------------
                        */

                        $paymentStmt = $conn->prepare("
                            SELECT
                                tr.registrationID,
                                tr.category_registered,
                                tr.amount_paid,
                                tr.payment_date,
                                tr.payment_status,
                                tr.player_tshirt,
                                p.player_full_name,
                                p.player_nationality
                            FROM tournament_register tr
                            JOIN players p ON tr.playerID = p.playerID
                            WHERE tr.tournamentID = ?
                            ORDER BY tr.registrationID DESC
                        ");

                        $paymentStmt->bind_param("i", $tournamentID);
                        $paymentStmt->execute();

                        $paymentResult = $paymentStmt->get_result();

                        ?>


                        <?php if ($paymentResult->num_rows > 0): ?>


                            <div class="table-wrapper">

                                <table class="players-table">


                                    <thead>

                                        <tr>

                                            <th>No.</th>

                                            <th>Player Name</th>

                                            <th>Category</th>

                                            <th>Nationality</th>

                                            <th>T-Shirt Size</th>

                                            <th>Payment Status</th>

                                            <th>Action</th>

                                        </tr>

                                    </thead>


                                    <tbody>


                                        <?php

                                        $paymentNo = 1;

                                        while ($player = $paymentResult->fetch_assoc()):

                                            $paymentStatus = strtoupper(
                                                trim($player['payment_status'] ?? '')
                                            );

                                        ?>


                                            <tr
                                                class="payment-row"
                                                data-payment="<?= htmlspecialchars($paymentStatus) ?>"
                                            >


                                                <td>
                                                    <?= $paymentNo++ ?>
                                                </td>


                                                <td>

                                                    <div class="player-name">

                                                        <?= htmlspecialchars(
                                                            $player['player_full_name'] ?? '-'
                                                        ) ?>

                                                    </div>

                                                </td>


                                                <td>

                                                    <?= categoryBadge(
                                                        $player['registrationID'],
                                                        $player['category_registered'],
                                                        $wrongCategories
                                                    ) ?>

                                                </td>


                                                <td>
                                                    <?= !empty($player['player_nationality'])
                                                        ? htmlspecialchars($player['player_nationality'])
                                                        : '-' ?>
                                                </td>


                                                <td>

                                                    <?= !empty($player['player_tshirt'])
                                                        ? htmlspecialchars($player['player_tshirt'])
                                                        : '-' ?>

                                                </td>


                                                <td>

                                                    <span class="status-badge">

                                                        <?= htmlspecialchars(
                                                            $player['payment_status'] ?? '-'
                                                        ) ?>

                                                    </span>

                                                </td>


                                                <td class="action-cell">

                                                    <a
                                                        href="admin_player_tournament_registration.php?id=<?= urlencode($player['registrationID']) ?>"
                                                        class="action-btn view-btn"
                                                    >
                                                        View
                                                    </a>

                                                </td>


                                            </tr>


                                        <?php endwhile; ?>


                                    </tbody>

                                </table>

                            </div>


                        <?php else: ?>


                            <div class="no-players">

                                <div class="no-players-icon">
                                    👤
                                </div>

                                <h3>
                                    No Players Registered
                                </h3>

                                <p>
                                    There are currently no players registered for this tournament.
                                </p>

                            </div>


                        <?php endif; ?>


                        <?php $paymentStmt->close(); ?>


                    </div>

                </div>


                <!-- ==================================================
                     ENDORSEMENT SECTION
                =================================================== -->

                <div class="tournament_payment_detail" id="eligiblebg">


                    <!-- FILTER + REPORT -->

                    <div class="filter">

                        <div class="spacer"></div>


                        <select id="endorsement_filter" onchange="filterPlayers()">

                            <option value="all">
                                Endorsement - All
                            </option>

                            <option value="ENDORSED">
                                Endorsed
                            </option>

                            <option value="PENDING">
                                Pending
                            </option>

                        </select>


                    </div>


                    <!-- ENDORSEMENT TABLE -->

                    <div id="endorsementList">


                        <?php

                        $endorsementStmt = $conn->prepare("
                            SELECT
                                tr.registrationID,
                                tr.category_registered,
                                tr.endorsement,
                                tr.endorsement_date,
                                tr.admin_remark,
                                p.player_full_name
                            FROM tournament_register tr
                            JOIN players p ON tr.playerID = p.playerID
                            WHERE tr.tournamentID = ?
                            ORDER BY tr.registrationID DESC
                        ");

                        $endorsementStmt->bind_param("i", $tournamentID);
                        $endorsementStmt->execute();

                        $endorsementResult =
                            $endorsementStmt->get_result();

                        ?>


                        <?php if ($endorsementResult->num_rows > 0): ?>


                            <div class="table-wrapper">

                                <table class="players-table">


                                    <thead>

                                        <tr>

                                            <th>No.</th>

                                            <th>Player Name</th>

                                            <th>Category</th>

                                            <th>Endorsement</th>

                                            <th>Endorsement Date</th>

                                            <th>Admin Remark</th>

                                            <th>Action</th>

                                        </tr>

                                    </thead>


                                    <tbody>


                                        <?php

                                        $endorsementNo = 1;

                                        while ($player = $endorsementResult->fetch_assoc()):

                                            $endorsementStatus = strtoupper(
                                                trim($player['endorsement'] ?? '')
                                            );

                                        ?>


                                            <tr
                                                class="endorsement-row"
                                                data-endorsement="<?= htmlspecialchars($endorsementStatus) ?>"
                                            >


                                                <td>
                                                    <?= $endorsementNo++ ?>
                                                </td>


                                                <td>

                                                    <div class="player-name">

                                                        <?= htmlspecialchars(
                                                            $player['player_full_name'] ?? '-'
                                                        ) ?>

                                                    </div>

                                                </td>


                                                <td>

                                                    <?= categoryBadge(
                                                        $player['registrationID'],
                                                        $player['category_registered'],
                                                        $wrongCategories
                                                    ) ?>

                                                </td>


                                                <td class="endorsement-status">

                                                    <span class="status-badge">

                                                        <?= htmlspecialchars(
                                                            $player['endorsement'] ?? '-'
                                                        ) ?>

                                                    </span>

                                                </td>


                                                <td class="endorsement-date">

                                                    <?= !empty($player['endorsement_date'])
                                                        ? htmlspecialchars($player['endorsement_date'])
                                                        : '-' ?>

                                                </td>


                                                <td>

                                                    <?= !empty($player['admin_remark'])
                                                        ? htmlspecialchars($player['admin_remark'])
                                                        : '-' ?>

                                                </td>


                                                <td class="action-cell">


                                                    <!-- VIEW BUTTON -->

                                                    <a
                                                        href="admin_player_tournament_registration.php?id=<?= urlencode($player['registrationID']) ?>"
                                                        class="action-btn view-btn"
                                                    >
                                                        View
                                                    </a>


                                                    <!-- ENDORSE BUTTON -->

                                                    <?php if ($endorsementStatus !== 'ENDORSED'): ?>


                                                        <button
                                                            type="button"
                                                            class="action-btn endorse-btn"
                                                            onclick="endorse(this, <?= (int)$player['registrationID'] ?>)"
                                                        >
                                                            Endorse
                                                        </button>


                                                    <?php else: ?>


                                                        <span class="endorsed-text">
                                                            Endorsed
                                                        </span>


                                                    <?php endif; ?>


                                                </td>


                                            </tr>


                                        <?php endwhile; ?>


                                    </tbody>

                                </table>

                            </div>


                        <?php else: ?>


                            <div class="no-players">

                                <div class="no-players-icon">
                                    👤
                                </div>

                                <h3>
                                    No Players Registered
                                </h3>

                                <p>
                                    There are currently no players registered for this tournament.
                                </p>

                            </div>


                        <?php endif; ?>


                        <?php $endorsementStmt->close(); ?>


                    </div>

                </div>


            </div>

        </div>


    </section>

    <!-- ==========================================================
        SECTION 3: SEEDING AND RANKING
    =========================================================== -->

    <section class="players-section">

        <div class="section-header">

            <div>

                <h2>
                    Tournament Seeding
                </h2>

                <p class="section-subtitle">
                    Automatic ranking, seeding and admin adjustment
                </p>

            </div>

        </div>


        <div class="seeding-toolbar">
            <div class="seeding-category">

                <label for="category-select">
                    Select Category
                </label>

                <form method="GET" action="admin_each_tournament.php">

                    <input type="hidden" name="id" value="<?= $tournamentID ?>">

                    <select id="seeding_category" name="category" onchange="this.form.submit()">

                        <?php if (!empty($categories)): ?>

                            <?php foreach ($categories as $category): ?>

                                <option
                                    value="<?= htmlspecialchars($category) ?>"
                                    <?= $selectedCategory === $category ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($category) ?>
                                </option>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <option value="">
                                No Category Available
                            </option>

                        <?php endif; ?>

                    </select>

                </form>

            </div>


            <!-- ======================================================
                SEEDING ACTIONS
            ======================================================= -->

            <div class="seeding-actions">

                <form method="POST" action="admin_seeding.php?id=<?= $tournamentID ?>&category=<?= urlencode($selectedCategory) ?>" onsubmit="automaticSeedingLoading()">
                    <input type="hidden" name="action" value="automatic_seeding">
                    <input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory) ?>">

                    <button type="submit" id="automaticSeedingBtn" class="btn" <?= empty($selectedCategory) ? 'disabled' : '' ?>>
                        Automatic Seeding
                    </button>
                </form>


                <a
                    href="admin_seeding.php?id=<?= $tournamentID ?>&category=<?= urlencode($selectedCategory) ?>"
                    class="btn manage-seeding"
                >
                    Manage Seeding
                </a>

            </div>
        </div>


        <!-- ======================================================
            SEEDING TABLE
        ======================================================= -->

        <?php

        $seedStmt = $conn->prepare("
            SELECT
                tr.registrationID,
                tr.category_registered,
                tr.seed_number,
                tr.endorsement,
                tr.payment_status,

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
              AND tr.category_registered = ?
              AND tr.endorsement = 'ENDORSED'

            ORDER BY
                CASE
                    WHEN tr.seed_number IS NULL THEN 999999
                    ELSE tr.seed_number
                END ASC,
                p.player_full_name ASC
        ");

        $seedStmt->bind_param("is", $tournamentID, $selectedCategory);

        $seedStmt->execute();

        $seedResult = $seedStmt->get_result();

        ?>


        <?php if ($seedResult->num_rows > 0): ?>

            <div class="selected-seeding-category">
                Showing Seeding for:
                <strong><?= htmlspecialchars($selectedCategory) ?></strong>
            </div>

            <div class="table-wrapper">

                <table class="players-table">

                    <thead>

                        <tr>

                            <th>No.</th>

                            <th>Player</th>

                            <th>Category</th>

                            <th>World Ranking</th>

                            <th>National Ranking</th>

                            <th>Regional Ranking(AJSS)</th>

                            <th>Seed</th>

                            <th>Status</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php

                        $seedNo = 1;

                        while ($seedPlayer = $seedResult->fetch_assoc()):

                        ?>

                            <tr>

                                <td>
                                    <?= $seedNo++ ?>
                                </td>


                                <td>

                                    <div class="player-name">

                                        <?= htmlspecialchars(
                                            $seedPlayer['player_full_name']
                                        ) ?>

                                    </div>

                                </td>


                                <td>

                                    <?= categoryBadge(
                                        $seedPlayer['registrationID'],
                                        $seedPlayer['category_registered'],
                                        $wrongCategories
                                    ) ?>

                                </td>


                                <td>

                                    <?= !empty($seedPlayer['world_ranking'])
                                        ? htmlspecialchars($seedPlayer['world_ranking'])
                                        : '-' ?>

                                </td>


                                <td>

                                    <?= !empty($seedPlayer['national_ranking'])
                                        ? htmlspecialchars($seedPlayer['national_ranking'])
                                        : '-' ?>

                                </td>


                                <td>

                                    <?= !empty($seedPlayer['ajss_ranking'])
                                        ? htmlspecialchars($seedPlayer['ajss_ranking'])
                                        : '-' ?>

                                </td>


                                <td>

                                    <?php if ($seedPlayer['seed_number'] !== null): ?>

                                        <strong>
                                            <?= (int)$seedPlayer['seed_number'] ?>
                                        </strong>

                                    <?php else: ?>

                                        -

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php if (
                                        strtoupper(trim($seedPlayer['endorsement'] ?? ''))
                                        === 'ENDORSED'
                                    ): ?>

                                        <span class="status-badge">
                                            ENDORSED
                                        </span>

                                    <?php else: ?>

                                        <span class="status-badge">
                                            PENDING
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="no-players">

                <div class="no-players-icon">
                    👤
                </div>

                <h3>
                    No Players Available
                </h3>

                <p>
                    There are currently no registered players for
                    <?= !empty($selectedCategory)
                        ? '<strong>' . htmlspecialchars($selectedCategory) . '</strong>'
                        : 'this category' ?>.
                </p>

            </div>

        <?php endif; ?>


        <?php $seedStmt->close(); ?>

    </section>


</main>


<script>


/*
|--------------------------------------------------------------------------
| SHOW TAB
|--------------------------------------------------------------------------
*/

function showTab(tab) {

    const paymentBg =
        document.getElementById('paymentbg');

    const endorsementBg =
        document.getElementById('eligiblebg');

    const nav1 =
        document.getElementById('nav1');

    const nav2 =
        document.getElementById('nav2');


    if (tab === 'payment') {


        paymentBg.style.display = 'block';

        endorsementBg.style.display = 'none';


        nav1.classList.add('active_now');

        nav1.classList.remove('inactive_now');


        nav2.classList.add('inactive_now');

        nav2.classList.remove('active_now');


    } else {


        paymentBg.style.display = 'none';

        endorsementBg.style.display = 'block';


        nav2.classList.add('active_now');

        nav2.classList.remove('inactive_now');


        nav1.classList.add('inactive_now');

        nav1.classList.remove('active_now');

    }


    sessionStorage.setItem(
        'activeTournamentTab',
        tab
    );


    filterPlayers();

}


/*
|--------------------------------------------------------------------------
| FILTER PLAYERS
|--------------------------------------------------------------------------
*/

function filterPlayers() {


    /*
    |--------------------------------------------------------------------------
    | PAYMENT FILTER
    |--------------------------------------------------------------------------
    */

    const paymentFilter =
        document.getElementById('payment_filter');


    const paymentValue =
        paymentFilter
            ? paymentFilter.value
            : 'all';


    const paymentRows =
        document.querySelectorAll('.payment-row');


    paymentRows.forEach(function(row) {


        const status =
            row.getAttribute('data-payment');


        if (paymentValue === 'all') {

            row.style.display = '';

        } else if (paymentValue === status) {

            row.style.display = '';

        } else {

            row.style.display = 'none';

        }

    });


    /*
    |--------------------------------------------------------------------------
    | ENDORSEMENT FILTER
    |--------------------------------------------------------------------------
    */

    const endorsementFilter =
        document.getElementById('endorsement_filter');


    const endorsementValue =
        endorsementFilter
            ? endorsementFilter.value
            : 'all';


    const endorsementRows =
        document.querySelectorAll('.endorsement-row');


    endorsementRows.forEach(function(row) {


        const status =
            row.getAttribute('data-endorsement');


        if (endorsementValue === 'all') {

            row.style.display = '';

        }


        else if (endorsementValue === 'PENDING') {


            if (status !== 'ENDORSED') {

                row.style.display = '';

            } else {

                row.style.display = 'none';

            }


        }


        else if (endorsementValue === status) {

            row.style.display = '';

        }


        else {

            row.style.display = 'none';

        }

    });

}


/*
|--------------------------------------------------------------------------
| ENDORSE PLAYER
|--------------------------------------------------------------------------
*/

function endorse(button, registrationID) {


    const confirmEndorse =
        confirm(
            'Are you sure you want to endorse this player?'
        );


    if (!confirmEndorse) {
        return;
    }


    button.disabled = true;

    button.textContent = 'Processing...';


    const formData =
        new FormData();


    formData.append(
        'registrationID',
        registrationID
    );


    formData.append(
        'endorsement',
        'ENDORSED'
    );


    formData.append(
        'endorsement_date',
        getTodayDate()
    );


    fetch(
        'admin_each_tournament.php?id=<?= $tournamentID ?>',
        {
            method: 'POST',
            body: formData
        }
    )


    .then(function(response) {

        return response.json();

    })


    .then(function(data) {


        if (data.success) {


            const row =
                button.closest('tr');


            /*
            |--------------------------------------------------------------------------
            | CHANGE STATUS
            |--------------------------------------------------------------------------
            */

            const statusCell =
                row.querySelector(
                    '.endorsement-status'
                );


            if (statusCell) {

                statusCell.innerHTML = `
                    <span class="status-badge">
                        ENDORSED
                    </span>
                `;

            }


            /*
            |--------------------------------------------------------------------------
            | CHANGE DATE
            |--------------------------------------------------------------------------
            */

            const dateCell =
                row.querySelector(
                    '.endorsement-date'
                );


            if (dateCell) {

                dateCell.textContent =
                    getTodayDate();

            }


            /*
            |--------------------------------------------------------------------------
            | CHANGE DATA ATTRIBUTE
            |--------------------------------------------------------------------------
            */

            row.setAttribute(
                'data-endorsement',
                'ENDORSED'
            );


            /*
            |--------------------------------------------------------------------------
            | CHANGE BUTTON
            |--------------------------------------------------------------------------
            */

            button.outerHTML = `
                <span class="endorsed-text">
                    Endorsed
                </span>
            `;


            alert(data.message);


        } else {


            alert(
                data.message ||
                'Failed to endorse player.'
            );


            button.disabled = false;

            button.textContent = 'Endorse';

        }

    })


    .catch(function(error) {


        console.error(error);


        alert(
            'An error occurred while endorsing the player.'
        );


        button.disabled = false;

        button.textContent = 'Endorse';

    });

}


/*
|--------------------------------------------------------------------------
| GET TODAY DATE
|--------------------------------------------------------------------------
*/

function getTodayDate() {


    const today =
        new Date();


    const year =
        today.getFullYear();


    const month =
        String(
            today.getMonth() + 1
        ).padStart(2, '0');


    const day =
        String(
            today.getDate()
        ).padStart(2, '0');


    return `${year}-${month}-${day}`;

}


/*
|--------------------------------------------------------------------------
| PAGE LOAD
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function() {


        const activeTab =
            sessionStorage.getItem(
                'activeTournamentTab'
            ) || 'payment';


        showTab(activeTab);


        /*
        |--------------------------------------------------------------------------
        | PAYMENT TAB
        |--------------------------------------------------------------------------
        */

        document
            .getElementById('nav1')
            .addEventListener(
                'click',
                function() {

                    showTab('payment');

                }
            );


        /*
        |--------------------------------------------------------------------------
        | ENDORSEMENT TAB
        |--------------------------------------------------------------------------
        */

        document
            .getElementById('nav2')
            .addEventListener(
                'click',
                function() {

                    showTab('endorsement');

                }
            );


        filterPlayers();

    }
);

function automaticSeedingLoading() {

    const button = document.getElementById('automaticSeedingBtn');

    if (button) {
        button.disabled = true;
        button.textContent = 'Generating Seeding...';
    }
}

</script>


</body>
</html>