<?php
require_once "player_functions.php";


/*
|--------------------------------------------------------------------------
| Get Player Registrations
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        tr.registrationID,
        tr.category_registered,
        tr.player_tshirt,
        tr.payment_status,
        tr.payment_proof,
        tr.endorsement,
        tr.seed_number,
        tr.admin_remark,

        t.tournamentID,
        t.tournament_name,
        t.tournament_type,
        t.tournament_location,
        t.tournament_startdate,
        t.tournament_enddate,
        t.tournament_picture

    FROM tournament_register tr

    INNER JOIN tournament t
        ON tr.tournamentID = t.tournamentID

    WHERE tr.playerID = ?

    ORDER BY t.tournament_startdate DESC
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("s", $playerID);
$stmt->execute();

$result = $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Upcoming / Past
|--------------------------------------------------------------------------
| A tournament is past once its end date has passed.
*/

$registrations = [];

$counts = [
    'all' => 0,
    'upcoming' => 0,
    'past' => 0
];

while ($registration = $result->fetch_assoc()) {

    $endTime = strtotime($registration['tournament_enddate']);

    $registration['period'] = ($endTime !== false && $endTime > 0 && $endTime < time())
        ? 'past'
        : 'upcoming';

    $counts['all']++;
    $counts[$registration['period']]++;

    $registrations[] = $registration;
}

$stmt->close();


function formatDay($value)
{
    $time = strtotime($value ?? '');

    return ($time === false || $time <= 0) ? '-' : date("d M Y", $time);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Player | My Tournaments</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />

    <!-- Navbar CSS -->
    <link rel="stylesheet" href="./assets/css/navbar.css?v=<?= filemtime(__DIR__ . '/assets/css/navbar.css') ?>" type="text/css" />

    <!-- Shared Page CSS -->
    <link rel="stylesheet" href="./assets/css/tournament_details.css?v=<?= filemtime(__DIR__ . '/assets/css/tournament_details.css') ?>" type="text/css" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="./assets/css/my_tournaments.css?v=<?= filemtime(__DIR__ . '/assets/css/my_tournaments.css') ?>" type="text/css" />
</head>


<body>


    <?php include "player_navbar.php"; ?>


    <main class="container">


        <!-- =====================================================
             HEADER
        ====================================================== -->

        <div class="my-header">

            <h1>My Tournaments</h1>


            <div class="my-toolbar">

                <!-- Tabs -->
                <div class="tabs">

                    <button type="button" class="tab active" data-period="all">
                        All <span><?= $counts['all'] ?></span>
                    </button>

                    <button type="button" class="tab" data-period="upcoming">
                        Upcoming <span><?= $counts['upcoming'] ?></span>
                    </button>

                    <button type="button" class="tab" data-period="past">
                        Past <span><?= $counts['past'] ?></span>
                    </button>

                </div>


                <!-- Search -->
                <div class="my-search">

                    <i class="fa-solid fa-magnifying-glass"></i>

                    <input
                        type="text"
                        id="registrationSearch"
                        placeholder="Search tournaments..."
                        autocomplete="off"
                    >

                </div>

            </div>

        </div>


        <!-- =====================================================
             REGISTRATION LIST
        ====================================================== -->

        <?php if (empty($registrations)): ?>

            <div class="card empty-state">

                <i class="fa-solid fa-trophy"></i>

                <h2>No registrations yet</h2>

                <p class="muted">
                    You haven't registered for any tournament.
                </p>

                <a href="./player_index.php" class="primary-btn">
                    Browse Tournaments
                </a>

            </div>

        <?php else: ?>

            <div class="registration-list" id="registrationList">

                <?php foreach ($registrations as $registration): ?>

                    <article
                        class="registration-item"
                        data-period="<?= $registration['period'] ?>"
                        data-name="<?= htmlspecialchars(strtolower($registration['tournament_name'])) ?>"
                    >


                        <!-- Image -->

                        <a
                            href="tournament_details.php?tournamentID=<?= (int) $registration['tournamentID'] ?>"
                            class="item-image"
                        >

                            <?php if (!empty($registration['tournament_picture'])): ?>

                                <img
                                    src="../uploads/tournaments/<?= htmlspecialchars($registration['tournament_picture']) ?>"
                                    alt="<?= htmlspecialchars($registration['tournament_name']) ?>"
                                >

                            <?php else: ?>

                                <div class="no-image">
                                    <i class="fa-solid fa-trophy"></i>
                                </div>

                            <?php endif; ?>

                        </a>


                        <!-- Content -->

                        <div class="item-content">

                            <div class="item-top">

                                <?php if (!empty($registration['tournament_type'])): ?>

                                    <span class="tournament-type">
                                        <?= htmlspecialchars($registration['tournament_type']) ?>
                                    </span>

                                <?php endif; ?>

                                <?php if ($registration['period'] === 'past'): ?>

                                    <span class="badge badge-closed">Completed</span>

                                <?php else: ?>

                                    <span class="badge badge-open">Upcoming</span>

                                <?php endif; ?>

                            </div>


                            <h2>
                                <a href="tournament_details.php?tournamentID=<?= (int) $registration['tournamentID'] ?>">
                                    <?= htmlspecialchars($registration['tournament_name']) ?>
                                </a>
                            </h2>


                            <div class="item-meta">

                                <span>
                                    <i class="fa-solid fa-calendar-days"></i>
                                    <?= formatDay($registration['tournament_startdate']) ?>
                                    &ndash;
                                    <?= formatDay($registration['tournament_enddate']) ?>
                                </span>

                                <?php if (!empty($registration['tournament_location'])): ?>

                                    <span>
                                        <i class="fa-solid fa-location-dot"></i>
                                        <?= htmlspecialchars($registration['tournament_location']) ?>
                                    </span>

                                <?php endif; ?>

                            </div>


                            <div class="item-stats">

                                <div class="stat">
                                    <span>Category</span>
                                    <strong><?= htmlspecialchars($registration['category_registered']) ?></strong>
                                </div>

                                <div class="stat">
                                    <span>Payment</span>
                                    <strong class="status <?= statusClass($registration['payment_status']) ?>">
                                        <?= htmlspecialchars($registration['payment_status'] ?: 'Not Paid') ?>
                                    </strong>
                                </div>

                                <div class="stat">
                                    <span>Endorsement</span>
                                    <strong class="status <?= statusClass($registration['endorsement']) ?>">
                                        <?= htmlspecialchars($registration['endorsement'] ?: 'NOT ENDORSED') ?>
                                    </strong>
                                </div>

                                <?php if (!empty($registration['seed_number'])): ?>

                                    <div class="stat">
                                        <span>Seed</span>
                                        <strong><?= (int) $registration['seed_number'] ?></strong>
                                    </div>

                                <?php endif; ?>

                            </div>


                            <?php if (!empty($registration['admin_remark'])): ?>

                                <div class="summary-remark">
                                    <span>Remarks from organiser</span>
                                    <p><?= nl2br(htmlspecialchars($registration['admin_remark'])) ?></p>
                                </div>

                            <?php endif; ?>


                            <!-- Actions -->

                            <div class="item-actions">

                                <a
                                    href="tournament_details.php?tournamentID=<?= (int) $registration['tournamentID'] ?>"
                                    class="small-btn outline"
                                >
                                    View Details
                                </a>

                                <?php if (canPay($registration)): ?>

                                    <a
                                        href="tournament_payment.php?registrationID=<?= (int) $registration['registrationID'] ?>"
                                        class="small-btn"
                                    >
                                        <?= empty($registration['payment_proof']) ? 'Pay Entry Fee' : 'Re-upload Receipt' ?>
                                    </a>

                                <?php endif; ?>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>


                <p class="empty-message" id="noResults" hidden>
                    No tournaments match your search.
                </p>

            </div>

        <?php endif; ?>

    </main>


    <script>
        document.addEventListener("DOMContentLoaded", function () {

            const tabs = document.querySelectorAll(".tab");
            const searchInput = document.getElementById("registrationSearch");
            const items = document.querySelectorAll(".registration-item");
            const noResults = document.getElementById("noResults");

            let currentPeriod = "all";


            function filterRegistrations() {

                const searchValue = searchInput.value.toLowerCase().trim();

                let visible = 0;

                items.forEach(function (item) {

                    const matchesPeriod =
                        currentPeriod === "all" || item.dataset.period === currentPeriod;

                    const matchesSearch =
                        item.dataset.name.includes(searchValue);

                    const show = matchesPeriod && matchesSearch;

                    item.style.display = show ? "" : "none";

                    if (show) {
                        visible++;
                    }

                });

                if (noResults) {
                    noResults.hidden = visible > 0;
                }

            }


            tabs.forEach(function (tab) {

                tab.addEventListener("click", function () {

                    tabs.forEach(function (t) {
                        t.classList.remove("active");
                    });

                    this.classList.add("active");

                    currentPeriod = this.dataset.period;

                    filterRegistrations();

                });

            });


            searchInput.addEventListener("input", filterRegistrations);

        });
    </script>


</body>

</html>
