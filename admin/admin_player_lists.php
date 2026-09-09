<?php
session_start();

require '../db.php';

$player_query = "SELECT * FROM players ORDER BY playerID DESC";
$player_result = $conn->query($player_query);

if (!$player_result) {
    die("Could not load players: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Players | T_Software</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link href="./assets/style.css" rel="stylesheet" type="text/css">
</head>

<body>

    <?php require_once 'admin_navbar.php'; ?>

    <main class="">

        <div class="players-heading">
            <h1>Players</h1>

            <div class="players-actions">
                <label class="player-search">
                    <span class="visually-hidden">Search players</span>
                    <input id="player-search" type="search" placeholder="Search players..." autocomplete="off">
                </label>

                <a class="add-tournament-btn" href="admin_player_create.php">+ Add New</a>
            </div>
        </div>

        <div class="player-filters">

            <select id="event-filter" aria-label="Filter by training">
                <option value="">All Trainings</option>
                <option value="1">Training 1</option>
                <option value="2">Training 2</option>
                <option value="3">Training 3</option>
                <option value="4">Training 4</option>
                <option value="5">Training 5</option>
            </select>

            <select id="status-filter" aria-label="Filter by status">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>

        </div>

        <div class="players-table-head">
            <span>PLAYER ID</span>
            <span>PLAYER</span>
            <span>CONTACT</span>
            <span>STATUS</span>
            <span>ACTIONS</span>
        </div>

        <div class="players-list" id="players-list">

            <?php if ($player_result->num_rows > 0): ?>

                <?php while ($player = $player_result->fetch_assoc()): ?>

                    <article class="player-row" data-search="<?= strtolower($player['playerID'] . ' ' . $player['player_full_name'] . ' ' . $player['player_email']) ?>" data-status="<?= strtolower($player['player_active']) ?>">

                        <strong class="player-code">
                            <?= htmlspecialchars($player['playerID']) ?>
                        </strong>

                        <div class="player-profile">

                            <img class="player-avatar" src="<?= !empty($player['player_profile']) ? htmlspecialchars($player['player_profile']) : 'assets/user.png' ?>" alt="">

                            <div>
                                <strong>
                                    <?= htmlspecialchars($player['player_full_name']) ?>
                                </strong>
                            </div>

                        </div>

                        <div class="player-contact">

                            <span>
                                <i class="fa fa-phone"></i>
                                <?= htmlspecialchars($player['player_contact']) ?>
                            </span>

                            <span>
                                <i class="fa fa-envelope"></i>
                                <?= htmlspecialchars($player['player_email'] ?? '-') ?>
                            </span>

                        </div>

                        <div class="player-status">

                            <span class="status-pill <?= strtolower($player['player_active']) ?>">
                                <?= htmlspecialchars($player['player_active']) ?>
                            </span>

                        </div>

                        <div class="player-row-actions">

                            <a class="small-action view-action" href="admin_player_profile.php?id=<?= urlencode($player['playerID']) ?>">
                                View
                            </a>

                            <a class="small-action deactivate-action" href="admin_player_list.php?id=<?= urlencode($player['playerID']) ?>&action=deactivate">
                                Deactivate
                            </a>

                        </div>

                    </article>

                <?php endwhile; ?>

            <?php else: ?>

                <p class="no-tournaments">No players found.</p>

            <?php endif; ?>

        </div>

    </main>

    <script>

    const playerSearch = document.getElementById('player-search');
    const eventFilter = document.getElementById('event-filter');
    const statusFilter = document.getElementById('status-filter');
    const playerRows = document.querySelectorAll('.player-row');

    function filterPlayers() {

        const search = playerSearch.value.toLowerCase().trim();
        const event = eventFilter.value.toLowerCase();
        const status = statusFilter.value.toLowerCase();

        playerRows.forEach(function(row) {

            const matchesSearch = row.dataset.search.includes(search);

            const matchesEvent = !event || row.dataset.event === event;

            const matchesStatus = !status || row.dataset.status === status;

            row.hidden = !(matchesSearch && matchesEvent && matchesStatus);

        });
    }

    playerSearch.addEventListener('input', filterPlayers);
    eventFilter.addEventListener('change', filterPlayers);
    statusFilter.addEventListener('change', filterPlayers);

    </script>

</body>
</html>