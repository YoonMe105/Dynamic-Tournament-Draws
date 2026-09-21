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

</head>

<body>


<?php require_once 'admin_navbar.php'; ?>


<main class="container">


    <!-- ==========================================================
         SECTION 1: TOURNAMENT DETAILS
         YOUR ORIGINAL SECTION - NOT CHANGED
    =========================================================== -->

    <section class="tournament-section">

        <div class="section-header">

            <div>

                <h1>
                    <?= htmlspecialchars($tournament['tournament_name']) ?>
                </h1>

                <p class="section-subtitle">
                    Tournament Details
                </p>

            </div>


            <a href="admin_index.php" class="back-btn">
                ← Back to Tournaments
            </a>

        </div>


        <div class="tournament-details">


            <div class="detail-item">

                <span class="detail-label">
                    Tournament Name
                </span>

                <span class="detail-value">
                    <?= htmlspecialchars($tournament['tournament_name']) ?>
                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Location
                </span>

                <span class="detail-value">
                    <?= htmlspecialchars($tournament['tournament_location']) ?>
                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Start Date
                </span>

                <span class="detail-value">
                    <?= htmlspecialchars($tournament['tournament_startdate']) ?>
                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    End Date
                </span>

                <span class="detail-value">
                    <?= htmlspecialchars($tournament['tournament_enddate']) ?>
                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Registration Deadline
                </span>

                <span class="detail-value">
                    <?= htmlspecialchars($tournament['tournament_deadline']) ?>
                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Entry Fee
                </span>

                <span class="detail-value">
                    RM <?= number_format((float)$tournament['tournament_fee'], 2) ?>
                </span>

            </div>


            <?php if (!empty($tournament['tournament_description'])): ?>

                <div class="detail-item detail-description">

                    <span class="detail-label">
                        Description
                    </span>

                    <span class="detail-value">
                        <?= nl2br(htmlspecialchars($tournament['tournament_description'])) ?>
                    </span>

                </div>

            <?php endif; ?>


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
                                p.player_full_name
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

                                            <th>Amount Paid</th>

                                            <th>Payment Date</th>

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

                                                    <span class="category-badge">

                                                        <?= htmlspecialchars(
                                                            $player['category_registered'] ?? '-'
                                                        ) ?>

                                                    </span>

                                                </td>


                                                <td>
                                                    <?= $player['amount_paid'] ?? 0 ?>
                                                </td>


                                                <td>

                                                    <?= !empty($player['payment_date'])
                                                        ? htmlspecialchars($player['payment_date'])
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


                        <button
                            class="btn"
                            id="exportButton"
                            type="button"
                        >
                            Endorsement Report
                        </button>

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

                                                    <span class="category-badge">

                                                        <?= htmlspecialchars(
                                                            $player['category_registered'] ?? '-'
                                                        ) ?>

                                                    </span>

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
| ENDORSEMENT REPORT
|--------------------------------------------------------------------------
*/

document
    .getElementById('exportButton')
    .addEventListener(
        'click',
        function() {


            const tournamentID =
                <?= $tournamentID ?>;


            window.location.href =
                'admin_excel_endorsement.php?tournamentID=' +
                encodeURIComponent(tournamentID);

        }
    );


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

</script>


</body>
</html>