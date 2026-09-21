<?php
session_start();

if ($_SESSION["userid"] == null || $_SESSION["role"] != "player") {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";

$currentDate = date("Y-m-d H:i:s");

$sql = "SELECT 
            tournamentID,
            tournament_name,
            tournament_deadline,
            tournament_picture
        FROM tournament
        WHERE tournament_deadline >= '$currentDate'
        ORDER BY tournament_deadline ASC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Database Error: " . mysqli_error($conn));
}

$tournaments = [];

while ($tournament = mysqli_fetch_assoc($result)) {
    $tournaments[] = $tournament;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Player | Tournaments</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />

    <!-- Navbar CSS -->
    <link rel="stylesheet" href="./assets/css/navbar.css" type="text/css" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="./assets/css/index.css" type="text/css" />
</head>


<body>


    <header class="player-header">

        <div class="header-logo">

            <a href="index.php">
                Players
            </a>
        </div>


        <nav class="header-nav">

            <div class="tournament-menu">
                <a href="./player_index.php">
                    Tournament
                </a>

                <div class="sub-navbar">
                    <a href="./my_tournaments.php">My Tournaments</a>
                </div>
            </div>

            <a href="my_profile.php">
                My Profile
            </a>

            <a href="../logout.php" class="logout-btn">
                Logout
            </a>

        </nav>

    </header>



    <main class="container">

        <section class="tournament-section">

            <div class="section-title">

                <h2>Tournaments</h2>

                <!-- Search -->
                <div class="tournament-search">

                    <i class="fa-solid fa-magnifying-glass"></i>

                    <input
                        type="text"
                        id="tournamentSearch"
                        placeholder="Search tournaments..."
                        autocomplete="off"
                    >

                </div>

            </div>


            <div class="tournament-grid" id="tournamentGrid">

                <?php if (count($tournaments) > 0): ?>

                    <?php foreach ($tournaments as $tournament): ?>

                        <a
                            href="tournament_details.php?tournamentID=<?= $tournament['tournamentID'] ?>"
                            class="tournament-card"
                        >

                            <!-- Image -->
                            <div class="tournament-image">

                                <?php if (!empty($tournament['tournament_picture'])): ?>

                                    <img
                                        src="../uploads/tournaments/<?= htmlspecialchars($tournament['tournament_picture']) ?>"
                                        alt="<?= htmlspecialchars($tournament['tournament_name']) ?>"
                                    >

                                <?php else: ?>

                                    <div class="no-image">
                                        <i class="fa-solid fa-trophy"></i>
                                    </div>

                                <?php endif; ?>


                                <!-- Tournament Type -->
                                <?php if (!empty($tournament['tournament_type'])): ?>

                                    <div class="tournament-type">
                                        Type:
                                        <?= htmlspecialchars($tournament['tournament_type']) ?>
                                    </div>

                                <?php endif; ?>

                            </div>


                            <!-- Card Content -->
                            <div class="tournament-content">

                                <!-- Tournament Name -->
                                <h3>
                                    <?= htmlspecialchars($tournament['tournament_name']) ?>
                                </h3>


                                <!-- Registration Deadline -->
                                <div class="registration-info">

                                    <div class="registration-label">
                                        Registration Deadline
                                    </div>

                                    <div class="registration-date">
                                        <?= date(
                                            "Y-m-d H:i:s",
                                            strtotime($tournament['tournament_deadline'])
                                        ) ?>
                                    </div>

                                </div>

                            </div>

                        </a>

                    <?php endforeach; ?>

                <?php else: ?>

                    <p class="empty-message">
                        There are currently no tournaments open for registration.
                    </p>

                <?php endif; ?>

            </div>

        </section>

    </main>


    <script>
        document.addEventListener("DOMContentLoaded", function () {

        const searchInput =
            document.getElementById("tournamentSearch");

        const tournamentCards =
            document.querySelectorAll(".tournament-card");


        searchInput.addEventListener("input", function () {

            const searchValue =
                this.value.toLowerCase().trim();


            tournamentCards.forEach(function (card) {

                const tournamentName =
                    card.querySelector("h3")
                        .textContent
                        .toLowerCase();


                if (tournamentName.includes(searchValue)) {

                    card.style.display = "block";

                } else {

                    card.style.display = "none";

                }

            });

        });

    });
    </script>


</body>

</html>
