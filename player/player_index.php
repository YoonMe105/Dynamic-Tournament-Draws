<?php

session_start();

require_once "../db.php";


$sql = "SELECT tournamentID, tournament_startdate, tournament_enddate, tournament_name, tournament_description, tournament_detail_link, tournament_location, tournament_fee, tournament_deadline, tournament_type, tournament_picture
        FROM tournament
        ORDER BY tournament_startdate ASC";

$result = mysqli_query($conn, $sql);


if (!$result) {

    die("Database Error: " . mysqli_error($conn));

}


$currentTournaments = [];

$pastTournaments = [];


$currentDate = new DateTime();


while ($tournament = mysqli_fetch_assoc($result)) {

    $endDate = new DateTime($tournament['tournament_enddate']);

    if ($endDate >= $currentDate) {

        $currentTournaments[] = $tournament;

    } else {

        $pastTournaments[] = $tournament;

    }

}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player | Home</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/navbar.css" type="text/css" />

    <link href="./assets/css/index.css" rel="stylesheet" type="text/css" />

</head>
<body>

    <header class="player-header">
        <div class="header-logo">
            <a href="index.php">Players</a>
        </div>

        <nav class="header-nav">
            <a href="./player_index.php">Tournament</a>
            <a href="my_profile.php">My Profile</a>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </nav>
    </header>

    <main class="container">

        <section class="tournament-section">

            <div class="section-title">

                <h2>Current Tournaments</h2>

                <p>View tournaments that are currently available or upcoming.</p>

            </div>


            <div class="tournament-grid">

                <?php if (count($currentTournaments) > 0): ?>

                    <?php foreach ($currentTournaments as $tournament): ?>

                        <div class="tournament-card">

                            <div class="tournament-image">

                                <?php if (!empty($tournament['tournament_picture'])): ?>

                                    <img src="../uploads/tournaments/<?= htmlspecialchars($tournament['tournament_picture']) ?>" alt="Tournament Picture">

                                <?php else: ?>

                                    <div class="no-image">
                                        🏆
                                    </div>

                                <?php endif; ?>

                            </div>


                            <div class="tournament-content">

                                <span class="tournament-type">
                                    <?= htmlspecialchars($tournament['tournament_type']) ?>
                                </span>


                                <h2>
                                    <?= htmlspecialchars($tournament['tournament_name']) ?>
                                </h2>


                                <p class="description">
                                    <?= htmlspecialchars($tournament['tournament_description']) ?>
                                </p>


                                <div class="tournament-info">

                                    <div class="info-row">

                                        <span class="info-icon">📅</span>

                                        <span class="info-label">
                                            Date:
                                        </span>

                                        <span class="info-value">
                                            <?= date("d M Y", strtotime($tournament['tournament_startdate'])) ?>
                                            -
                                            <?= date("d M Y", strtotime($tournament['tournament_enddate'])) ?>
                                        </span>

                                    </div>


                                    <div class="info-row">

                                        <span class="info-icon">📍</span>

                                        <span class="info-label">
                                            Location:
                                        </span>

                                        <span class="info-value">
                                            <?= htmlspecialchars($tournament['tournament_location']) ?>
                                        </span>

                                    </div>


                                    <div class="info-row">

                                        <span class="info-icon">💰</span>

                                        <span class="info-label">
                                            Fee:
                                        </span>

                                        <span class="info-value">
                                            <?= htmlspecialchars($tournament['tournament_fee']) ?>
                                        </span>

                                    </div>


                                    <div class="info-row">

                                        <span class="info-icon">⏰</span>

                                        <span class="info-label">
                                            Deadline:
                                        </span>

                                        <span class="info-value">
                                            <?= date("d M Y", strtotime($tournament['tournament_deadline'])) ?>
                                        </span>

                                    </div>

                                </div>


                                <a href="tournament_details.php?tournamentID=<?= $tournament['tournamentID'] ?>" class="view-btn">
                                    View Tournament
                                </a>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <p class="empty-message">
                        There are currently no current tournaments.
                    </p>

                <?php endif; ?>

            </div>

        </section>


        <section class="tournament-section past-section">

            <div class="section-title">

                <h2>Past Tournaments</h2>

                <p>View tournaments that have already ended.</p>

            </div>


            <div class="tournament-grid">

                <?php if (count($pastTournaments) > 0): ?>

                    <?php foreach ($pastTournaments as $tournament): ?>

                        <div class="tournament-card past-card">

                            <div class="tournament-image">

                                <?php if (!empty($tournament['tournament_picture'])): ?>

                                    <img src="../uploads/tournaments/<?= htmlspecialchars($tournament['tournament_picture']) ?>" alt="Tournament Picture">

                                <?php else: ?>

                                    <div class="no-image">
                                        🏆
                                    </div>

                                <?php endif; ?>

                            </div>


                            <div class="tournament-content">

                                <span class="past-label">
                                    Past Tournament
                                </span>


                                <h2>
                                    <?= htmlspecialchars($tournament['tournament_name']) ?>
                                </h2>


                                <p class="description">
                                    <?= htmlspecialchars($tournament['tournament_description']) ?>
                                </p>


                                <div class="tournament-info">

                                    <div class="info-row">

                                        <span class="info-icon">📅</span>

                                        <span class="info-label">
                                            Date:
                                        </span>

                                        <span class="info-value">
                                            <?= date("d M Y", strtotime($tournament['tournament_startdate'])) ?>
                                            -
                                            <?= date("d M Y", strtotime($tournament['tournament_enddate'])) ?>
                                        </span>

                                    </div>


                                    <div class="info-row">

                                        <span class="info-icon">📍</span>

                                        <span class="info-label">
                                            Location:
                                        </span>

                                        <span class="info-value">
                                            <?= htmlspecialchars($tournament['tournament_location']) ?>
                                        </span>

                                    </div>

                                </div>


                                <a href="tournament_details.php?tournamentID=<?= $tournament['tournamentID'] ?>" class="view-btn">
                                    View Tournament
                                </a>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <p class="empty-message">
                        There are no past tournaments.
                    </p>

                <?php endif; ?>

            </div>

        </section>

    </main>


    <script>
        document.addEventListener("DOMContentLoaded", function () {

            const tournamentCards = document.querySelectorAll(".tournament-card");

            tournamentCards.forEach(function (card) {

                card.addEventListener("mouseenter", function () {
                    card.classList.add("card-hover");
                });

                card.addEventListener("mouseleave", function () {
                    card.classList.remove("card-hover");
                });
            });


            const viewButtons = document.querySelectorAll(".view-btn");

            viewButtons.forEach(function (button) {

                button.addEventListener("click", function () {
                    button.classList.add("loading");
                    button.textContent = "Loading...";
                });

            });

        });
    </script>
    
</body>
</html>