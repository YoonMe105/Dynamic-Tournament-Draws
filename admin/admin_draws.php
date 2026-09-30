<?php

session_start();

require_once "../db.php";

/*
|--------------------------------------------------------------------------
| GET CURRENT TOURNAMENT
|--------------------------------------------------------------------------
| A tournament is considered current if:
| tournament_startdate <= now
| AND
| tournament_enddate >= now
|--------------------------------------------------------------------------
*/

$currentDate = date("Y-m-d H:i:s");

$currentSql = "
    SELECT
        tournamentID,
        tournament_startdate,
        tournament_enddate,
        tournament_name,
        tournament_description,
        tournament_detail_link,
        tournament_location,
        tournament_fee,
        tournament_deadline,
        tournament_type,
        tournament_picture
    FROM tournament
    WHERE tournament_startdate <= ?
      AND tournament_enddate >= ?
    ORDER BY tournament_startdate ASC
    LIMIT 1
";

$currentStmt = mysqli_prepare($conn, $currentSql);

if (!$currentStmt) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $currentStmt,
    "ss",
    $currentDate,
    $currentDate
);

mysqli_stmt_execute($currentStmt);

$currentResult = mysqli_stmt_get_result($currentStmt);

$currentTournament = mysqli_fetch_assoc($currentResult);

mysqli_stmt_close($currentStmt);


/*
|--------------------------------------------------------------------------
| GET NEXT TOURNAMENT
|--------------------------------------------------------------------------
| The next tournament is the closest tournament that has not started yet.
|--------------------------------------------------------------------------
*/

$nextSql = "
    SELECT
        tournamentID,
        tournament_startdate,
        tournament_enddate,
        tournament_name,
        tournament_description,
        tournament_detail_link,
        tournament_location,
        tournament_fee,
        tournament_deadline,
        tournament_type,
        tournament_picture
    FROM tournament
    WHERE tournament_startdate > ?
    ORDER BY tournament_startdate ASC
    LIMIT 1
";

$nextStmt = mysqli_prepare($conn, $nextSql);

if (!$nextStmt) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $nextStmt,
    "s",
    $currentDate
);

mysqli_stmt_execute($nextStmt);

$nextResult = mysqli_stmt_get_result($nextStmt);

$nextTournament = mysqli_fetch_assoc($nextResult);

mysqli_stmt_close($nextStmt);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Draw | Tournaments</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">    

    <link href="./assets/style.css" rel="stylesheet" type="text/css">
    <link href="./assets/drawstyle.css" rel="stylesheet" type="text/css">

</head>

<body>

    <?php require_once 'admin_navbar.php'; ?>

    <main class="container">


        <!-- =========================================================
            CURRENT TOURNAMENT
        ========================================================== -->

        <section class="tournament-section">

            <div class="section-title">

                <h2>
                    Current Tournament
                </h2>

                <p>
                    Tournament currently in progress.
                </p>

            </div>


            <?php if ($currentTournament): ?>

                <div class="tournament-grid">

                    <div class="tournament-card">

                        <div class="tournament-image">

                            <?php if (!empty($currentTournament['tournament_picture'])): ?>

                                <img
                                    src="../uploads/tournaments/<?= htmlspecialchars($currentTournament['tournament_picture']) ?>"
                                    alt="Tournament Picture"
                                >

                            <?php else: ?>

                                <div class="no-image">
                                    🏆
                                </div>

                            <?php endif; ?>

                        </div>


                        <div class="tournament-content">

                            <span class="tournament-type">
                                Current Tournament
                            </span>


                            <h2>
                                <?= htmlspecialchars($currentTournament['tournament_name']) ?>
                            </h2>


                            <p class="description">
                                <?= htmlspecialchars($currentTournament['tournament_description']) ?>
                            </p>


                            <div class="tournament-info">


                                <div class="info-row">

                                    <span class="info-icon">
                                        📅
                                    </span>

                                    <span class="info-label">
                                        Date:
                                    </span>

                                    <span class="info-value">

                                        <?= date(
                                            "d M Y",
                                            strtotime($currentTournament['tournament_startdate'])
                                        ) ?>

                                        -

                                        <?= date(
                                            "d M Y",
                                            strtotime($currentTournament['tournament_enddate'])
                                        ) ?>

                                    </span>

                                </div>


                                <div class="info-row">

                                    <span class="info-icon">
                                        📍
                                    </span>

                                    <span class="info-label">
                                        Location:
                                    </span>

                                    <span class="info-value">

                                        <?= htmlspecialchars(
                                            $currentTournament['tournament_location']
                                        ) ?>

                                    </span>

                                </div>


                                <div class="info-row">

                                    <span class="info-icon">
                                        💰
                                    </span>

                                    <span class="info-label">
                                        Fee:
                                    </span>

                                    <span class="info-value">

                                        <?= htmlspecialchars(
                                            $currentTournament['tournament_fee']
                                        ) ?>

                                    </span>

                                </div>

                            </div>


                            <div class="tournament-actions">

                                <a
                                    href="admin_view_tournaments.php?id=<?= $currentTournament['tournamentID'] ?>"
                                    class="view-btn"
                                >
                                    View Tournament
                                </a>


                                <!-- MAKE DRAW BUTTON -->

                                <a
                                    href="admin_draw.php?id=<?= $currentTournament['tournamentID'] ?>"
                                    class="view-btn"
                                >
                                    <i class="fa-solid fa-table-cells"></i>
                                    Make Draw
                                </a>

                            </div>

                        </div>

                    </div>

                </div>

            <?php else: ?>

                <p class="empty-message">
                    There is currently no tournament in progress.
                </p>

            <?php endif; ?>

        </section>



        <!-- =========================================================
            NEXT TOURNAMENT
        ========================================================== -->

        <section class="tournament-section">

            <div class="section-title">

                <h2>
                    Next Tournament
                </h2>

                <p>
                    The next upcoming tournament available for draw preparation.
                </p>

            </div>


            <?php if ($nextTournament): ?>

                <div class="tournament-grid">

                    <div class="tournament-card">

                        <div class="tournament-image">

                            <?php if (!empty($nextTournament['tournament_picture'])): ?>

                                <img
                                    src="../uploads/tournaments/<?= htmlspecialchars($nextTournament['tournament_picture']) ?>"
                                    alt="Tournament Picture"
                                >

                            <?php else: ?>

                                <div class="no-image">
                                    🏆
                                </div>

                            <?php endif; ?>

                        </div>


                        <div class="tournament-content">

                            <span class="tournament-type">
                                Next Tournament
                            </span>


                            <h2>
                                <?= htmlspecialchars(
                                    $nextTournament['tournament_name']
                                ) ?>
                            </h2>


                            <p class="description">

                                <?= htmlspecialchars(
                                    $nextTournament['tournament_description']
                                ) ?>

                            </p>


                            <div class="tournament-info">


                                <div class="info-row">

                                    <span class="info-icon">
                                        📅
                                    </span>

                                    <span class="info-label">
                                        Date:
                                    </span>

                                    <span class="info-value">

                                        <?= date(
                                            "d M Y",
                                            strtotime($nextTournament['tournament_startdate'])
                                        ) ?>

                                        -

                                        <?= date(
                                            "d M Y",
                                            strtotime($nextTournament['tournament_enddate'])
                                        ) ?>

                                    </span>

                                </div>


                                <div class="info-row">

                                    <span class="info-icon">
                                        📍
                                    </span>

                                    <span class="info-label">
                                        Location:
                                    </span>

                                    <span class="info-value">

                                        <?= htmlspecialchars(
                                            $nextTournament['tournament_location']
                                        ) ?>

                                    </span>

                                </div>


                                <div class="info-row">

                                    <span class="info-icon">
                                        ⏰
                                    </span>

                                    <span class="info-label">
                                        Registration Deadline:
                                    </span>

                                    <span class="info-value">

                                        <?= date(
                                            "d M Y",
                                            strtotime($nextTournament['tournament_deadline'])
                                        ) ?>

                                    </span>

                                </div>

                            </div>


                            <div class="tournament-actions">

                                <a
                                    href="admin_view_tournaments.php?id=<?= $nextTournament['tournamentID'] ?>"
                                    class="view-btn"
                                >
                                    View Tournament
                                </a>


                                <!-- MAKE DRAW BUTTON -->

                                <a
                                    href="admin_draw.php?id=<?= $nextTournament['tournamentID'] ?>"
                                    class="view-btn"
                                >
                                    <i class="fa-solid fa-table-cells"></i>
                                    Make Draw
                                </a>

                            </div>

                        </div>

                    </div>

                </div>

            <?php else: ?>

                <p class="empty-message">
                    There is no upcoming tournament.
                </p>

            <?php endif; ?>

        </section>

    </main>


    <script>

    document.addEventListener("DOMContentLoaded", function () {

        const tournamentCards =
            document.querySelectorAll(".tournament-card");


        tournamentCards.forEach(function (card) {

            card.addEventListener("mouseenter", function () {

                card.classList.add("card-hover");

            });


            card.addEventListener("mouseleave", function () {

                card.classList.remove("card-hover");

            });

        });


        const viewButtons =
            document.querySelectorAll(".view-btn");


        viewButtons.forEach(function (button) {

            button.addEventListener("click", function () {

                button.classList.add("loading");

            });

        });

    });

    </script>

</body>
</html>