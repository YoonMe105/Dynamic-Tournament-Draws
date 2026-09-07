<?php
session_start();
require '../db.php';

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit();
}

$recent_query = "SELECT tournamentid, tournament_name, tournament_type, tournament_startdate, tournament_enddate FROM tournament WHERE tournament_startdate >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND tournament_startdate < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH) ORDER BY tournament_startdate ASC LIMIT 3";

$ongoing_query = "SELECT tournamentid, tournament_name, tournament_type, tournament_startdate, tournament_enddate FROM tournament WHERE tournament_startdate <= CURDATE() AND tournament_enddate >= CURDATE() ORDER BY tournament_startdate ASC LIMIT 3";

$outcome_query = "SELECT tournamentid, tournament_name, tournament_type, tournament_startdate, tournament_enddate FROM tournament WHERE tournament_startdate >= DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH) ORDER BY tournament_startdate ASC LIMIT 3";

$recent_result = $conn->query($recent_query);
$ongoing_result = $conn->query($ongoing_query);
$outcome_result = $conn->query($outcome_query);

if (!$recent_result || !$ongoing_result || !$outcome_result) {
    die("Could not load tournament dashboard: " . $conn->error);
}

function tournament_card($tournament, $status = null, $status_class = "", $show_range = false, $show_registration = false) {
    ?>
    <article class="tournament-card">
        <div class="tournament-info">
            <h3><?= htmlspecialchars($tournament['tournament_name']) ?></h3>
            <p><?= htmlspecialchars($tournament['tournament_type']) ?> &bull; Squash</p>
            <span><?= date('d F Y', strtotime($tournament['tournament_startdate'])) ?><?php if ($show_range): ?> - <?= date('d F Y', strtotime($tournament['tournament_enddate'])) ?><?php endif; ?></span>
        </div>
        <div class="tournament-actions">
            <?php if ($status): ?>
                <div class="status <?= $status_class ?>"><?= $status ?></div>
            <?php endif; ?>
            <a class="details-btn" href="admin_view_tournaments.php?id=<?= (int)$tournament['tournamentid'] ?>">View Details</a>
            <?php if ($show_registration): ?>
                <a class="details-btn registration-btn" href="tournaments.php?id=<?= (int)$tournament['tournamentid'] ?>&action=register">View Registration</a>
            <?php endif; ?>
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
    <title>Tournament Dashboard</title>
    <link href="./assets/style.css" rel="stylesheet" type="text/css">
</head>

<body>

    <?php require_once 'admin_navbar.php'; ?>

    <main>

        <div class="dashboard-header">
            <h1>Tournament Dashboard</h1>
            <p>Manage and monitor your tournaments.</p>
        </div>

        <div class="dashboard-actions">
            <label class="search-box">
                <span class="visually-hidden">Search all tournaments</span>
                <input id="recent-search" type="search" placeholder="Search tournaments..." autocomplete="off">
            </label>
            <a class="add-tournament-btn" href="tournaments.php?action=add">+ Add Tournament</a>
        </div>

        <section class="tournament-section ongoing-section">
            <div class="section-header">
                <div>
                    <p class="section-kicker">Live now</p>
                    <h2>Ongoing Tournaments</h2>
                </div>
                <span class="section-count"><?= $ongoing_result->num_rows ?> active</span>
            </div>

            <div class="tournament-list">
                <?php if ($ongoing_result->num_rows): ?>
                    <?php while ($tournament = $ongoing_result->fetch_assoc()): ?>
                        <?php tournament_card($tournament, 'Ongoing', 'ongoing', true); ?>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="no-tournaments">No tournaments are ongoing today.</p>
                <?php endif; ?>
            </div>
        </section>

        <div class="tournament-columns">

            <section class="tournament-section">
                <div class="section-header">
                    <h2>Recent Tournaments</h2>
                </div>

                <div class="tournament-list">
                    <?php if ($recent_result->num_rows): ?>
                        <?php while ($tournament = $recent_result->fetch_assoc()): ?>
                            <?php tournament_card($tournament, null, '', false, true); ?>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="no-tournaments">No recent tournaments found.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="tournament-section">
                <div class="section-header">
                    <h2>Upcoming Tournaments</h2>
                </div>

                <div class="tournament-list">
                    <?php if ($outcome_result->num_rows): ?>
                        <?php while ($tournament = $outcome_result->fetch_assoc()): ?>
                            <?php tournament_card($tournament, 'Upcoming', 'upcoming'); ?>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="no-tournaments">No upcoming tournaments found.</p>
                    <?php endif; ?>
                </div>
            </section>

        </div>

    </main>

    <script>
    const recentSearch = document.getElementById('recent-search');
    const tournamentCards = document.querySelectorAll('.tournament-card');

    recentSearch?.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();

        tournamentCards.forEach(function(card) {
            card.hidden = !card.textContent.toLowerCase().includes(searchTerm);
        });
    });
    </script>

</body>
</html>