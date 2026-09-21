<?php
session_start();
require '../db.php';

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit();
}


/* =========================
   FEATURED TOURNAMENTS
========================= */

$featured_query = "SELECT tournamentid, tournament_name, tournament_type, tournament_startdate, tournament_enddate, tournament_deadline, tournament_picture
                   FROM tournament
                   WHERE DATE(tournament_deadline) >= CURDATE()
                   ORDER BY tournament_startdate ASC
                   LIMIT 3";


/* =========================
   ALL TOURNAMENTS
========================= */

$all_tournaments_query = "SELECT tournamentid, tournament_name, tournament_type, tournament_startdate, tournament_enddate, tournament_deadline, tournament_picture
                          FROM tournament
                          ORDER BY
                              CASE
                                  WHEN tournament_enddate >= CURDATE() THEN 0
                                  ELSE 1
                              END ASC,
                              tournament_startdate ASC";


/* =========================
   ONGOING TOURNAMENTS
========================= */

$ongoing_query = "SELECT tournamentid, tournament_name, tournament_type, tournament_startdate, tournament_enddate, tournament_deadline, tournament_picture
                  FROM tournament
                  WHERE tournament_startdate <= CURDATE()
                  AND tournament_enddate >= CURDATE()
                  ORDER BY tournament_startdate ASC
                  LIMIT 3";


/* =========================
   UPCOMING TOURNAMENTS
========================= */

$upcoming_query = "SELECT tournamentid, tournament_name, tournament_type, tournament_startdate, tournament_enddate, tournament_deadline, tournament_picture
                   FROM tournament
                   WHERE tournament_startdate > CURDATE()
                   ORDER BY tournament_startdate ASC
                   LIMIT 3";


/* =========================
   PAST TOURNAMENTS
========================= */

$past_query = "SELECT tournamentid, tournament_name, tournament_type, tournament_startdate, tournament_enddate, tournament_deadline, tournament_picture
               FROM tournament
               WHERE tournament_enddate < CURDATE()
               ORDER BY tournament_enddate DESC
               LIMIT 6";


/* =========================
   RUN QUERIES
========================= */

$featured_result = $conn->query($featured_query);
$all_tournaments_result = $conn->query($all_tournaments_query);
$ongoing_result = $conn->query($ongoing_query);
$upcoming_result = $conn->query($upcoming_query);
$past_result = $conn->query($past_query);


if (
    !$featured_result ||
    !$all_tournaments_result ||
    !$ongoing_result ||
    !$upcoming_result ||
    !$past_result
) {
    die("Could not load tournaments: " . $conn->error);
}


/* =========================
   FEATURED CARD
========================= */
function featured_card($tournament) {

    $picture = !empty($tournament['tournament_picture'])
        ? htmlspecialchars($tournament['tournament_picture'])
        : '../images/default-tournament.jpg';

    ?>

    <a class="featured-card"
       href="admin_view_tournaments.php?id=<?= (int)$tournament['tournamentid'] ?>">

        <div class="featured-type">
            Type: <?= htmlspecialchars($tournament['tournament_type']) ?>
        </div>

        <div class="featured-image">

            <img
                src="<?= $picture ?>"
                alt="<?= htmlspecialchars($tournament['tournament_name']) ?>"
            >

        </div>

        <div class="featured-content">

            <h3>
                <?= htmlspecialchars($tournament['tournament_name']) ?>
            </h3>

            <p class="deadline-title">
                Registration Deadline
            </p>

            <p class="deadline-date">

                <?= !empty($tournament['tournament_deadline'])
                    ? date('Y-m-d H:i:s', strtotime($tournament['tournament_deadline']))
                    : 'Not specified'
                ?>

            </p>

        </div>

    </a>

    <?php
}


/* =========================
   LARGE TOURNAMENT CARD
========================= */

function tournament_card($tournament, $status = null, $show_registration = false) {

    $picture = !empty($tournament['tournament_picture'])
        ? htmlspecialchars($tournament['tournament_picture'])
        : '../images/default-tournament.jpg';
    ?>

    <article class="large-tournament-card">

        <div class="large-image">

            <img src="<?= $picture ?>" alt="<?= htmlspecialchars($tournament['tournament_name']) ?>">

        </div>


        <div class="large-info">

            <?php if ($status): ?>

                <span class="tournament-status">
                    <?= htmlspecialchars($status) ?>
                </span>

            <?php endif; ?>


            <h2>
                <?= htmlspecialchars($tournament['tournament_name']) ?>
            </h2>


            <p class="large-type">
                <?= htmlspecialchars($tournament['tournament_type']) ?> Event
            </p>


            <p class="tournament-date">

                <?= date('d F Y', strtotime($tournament['tournament_startdate'])) ?>

                <?php if (!empty($tournament['tournament_enddate'])): ?>

                    - <?= date('d F Y', strtotime($tournament['tournament_enddate'])) ?>

                <?php endif; ?>

            </p>


            <div class="large-actions">

                <a class="details-btn"
                   href="admin_view_tournaments.php?id=<?= (int)$tournament['tournamentid'] ?>">
                    View Details
                </a>


                <?php if ($show_registration): ?>

                    <a class="details-btn registration-btn"
                       href="admin_each_tournament.php?id=<?= (int)$tournament['tournamentid'] ?>&action=register">
                        View Registration
                    </a>

                <?php endif; ?>

            </div>

        </div>

    </article>

    <?php
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tournaments</title>

    <link href="./assets/style.css" rel="stylesheet" type="text/css">
    <link href="./assets/index.css" rel="stylesheet" type="text/css">


</head>


<body>


    <?php require_once 'admin_navbar.php'; ?>


    <main class="tournament-page">

        <div class="page-header">

            <div>

                <h1>Tournaments</h1>

            </div>


            <a class="add-new-btn" href="admin_create_tournament.php">

                <span>＋</span>

                Add New

            </a>

        </div>



        <!-- =========================
            SEARCH
        ========================== -->

        <div class="search-wrapper">

            <input
                id="tournament-search"
                type="search"
                placeholder="Search..."
                autocomplete="off"
            >

            <span class="search-icon">⌕</span>

        </div>



        <!-- =========================
            FEATURED TOURNAMENTS
        ========================== -->

        <section class="featured-section">

            <div class="featured-list">

                <?php if ($featured_result->num_rows): ?>

                    <?php while ($tournament = $featured_result->fetch_assoc()): ?>

                        <?php featured_card($tournament); ?>

                    <?php endwhile; ?>

                <?php else: ?>

                    <p class="no-tournaments">
                        No tournaments available.
                    </p>

                <?php endif; ?>

            </div>

        </section>



        <!-- =========================
            ALL TOURNAMENTS
        ========================== -->

        <section class="large-section">

            <div class="section-title">

                <h2>All Tournaments</h2>

            </div>


            <div class="large-tournament-list">


                <?php if ($all_tournaments_result->num_rows): ?>


                    <?php while ($tournament = $all_tournaments_result->fetch_assoc()): ?>


                        <?php

                        $today = date('Y-m-d');

                        $start_date = date(
                            'Y-m-d',
                            strtotime($tournament['tournament_startdate'])
                        );

                        $end_date = date(
                            'Y-m-d',
                            strtotime($tournament['tournament_enddate'])
                        );


                        /* Determine tournament status */

                        if (
                            $start_date <= $today &&
                            $end_date >= $today
                        ) {

                            $status = 'ONGOING';

                        } elseif ($start_date > $today) {

                            $status = 'UPCOMING';

                        } else {

                            $status = 'PAST';

                        }

                        ?>


                        <?php tournament_card(
                            $tournament,
                            $status,
                            $status !== 'PAST'
                        ); ?>


                    <?php endwhile; ?>


                <?php else: ?>


                    <p class="no-tournaments">

                        No tournaments available.

                    </p>


                <?php endif; ?>


            </div>

        </section>



        <!-- =========================
            CURRENT / ONGOING
        ========================== -->

        <section class="large-section">

            <div class="section-title">

                <h2>Current Tournaments</h2>

            </div>


            <div class="large-tournament-list">


                <?php if ($ongoing_result->num_rows): ?>


                    <?php while ($tournament = $ongoing_result->fetch_assoc()): ?>


                        <?php tournament_card(
                            $tournament,
                            'ONGOING',
                            true
                        ); ?>


                    <?php endwhile; ?>


                <?php else: ?>


                    <?php if ($upcoming_result->num_rows): ?>


                        <?php

                        $upcoming_result->data_seek(0);

                        while ($tournament = $upcoming_result->fetch_assoc()):

                        ?>


                            <?php tournament_card(
                                $tournament,
                                'UPCOMING',
                                true
                            ); ?>


                        <?php endwhile; ?>


                    <?php else: ?>


                        <p class="no-tournaments">

                            No current tournaments available.

                        </p>


                    <?php endif; ?>


                <?php endif; ?>


            </div>

        </section>



        <!-- =========================
            PAST TOURNAMENTS
        ========================== -->

        <section class="large-section past-section">

            <div class="section-title">

                <h2>Past Tournaments</h2>

            </div>


            <div class="large-tournament-list">


                <?php if ($past_result->num_rows): ?>


                    <?php while ($tournament = $past_result->fetch_assoc()): ?>


                        <?php tournament_card(
                            $tournament,
                            'PAST',
                            false
                        ); ?>


                    <?php endwhile; ?>


                <?php else: ?>


                    <p class="no-tournaments">

                        No past tournaments available.

                    </p>


                <?php endif; ?>


            </div>

        </section>


    </main>



<script>

const searchInput = document.getElementById('tournament-search');


searchInput?.addEventListener('input', function () {

    const searchTerm = this.value.toLowerCase().trim();


    const cards = document.querySelectorAll(
        '.featured-card, .large-tournament-card'
    );


    cards.forEach(function (card) {

        const text = card.textContent.toLowerCase();


        card.style.display =
            text.includes(searchTerm)
                ? ''
                : 'none';

    });

});

</script>


</body>

</html>