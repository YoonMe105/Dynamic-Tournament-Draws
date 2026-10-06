<?php

require_once '../db.php';
require_once 'admin_auth.php';

requirePlatformAdmin();


/*
|--------------------------------------------------------------------------
| Players
|--------------------------------------------------------------------------
| Every player account. Platform Admins can view a player, add one, and
| deactivate / activate an account (a deactivated player can't log in).
*/


/*
| Deactivate / activate
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {

    $playerID = trim($_POST['playerID'] ?? '');

    $stmt = $conn->prepare("
        UPDATE players
        SET player_active = IF(player_active = 'active', 'inactive', 'active')
        WHERE playerID = ?
    ");
    $stmt->bind_param("s", $playerID);
    $stmt->execute();
    $stmt->close();

    $status = $conn->query("SELECT player_active FROM players WHERE playerID = '" . $conn->real_escape_string($playerID) . "'")
        ->fetch_assoc()['player_active'] ?? '';

    header("Location: admin_player_lists.php?changed=" . urlencode($playerID) . "&status=" . urlencode($status));
    exit;
}


/*
| Player list (newest first) with how many tournaments each entered
*/

$players = $conn->query("
    SELECT p.*,
           (SELECT COUNT(*) FROM tournament_register tr WHERE tr.playerID = p.playerID) AS tournaments_entered
    FROM players p
    ORDER BY p.playerID DESC
")->fetch_all(MYSQLI_ASSOC);

$nationalities = array_values(array_unique(array_filter(array_map(function ($player) {
    return strtoupper(trim((string)$player['player_nationality']));
}, $players))));

sort($nationalities);

$activeCount = count(array_filter($players, function ($player) {
    return $player['player_active'] === 'active';
}));


// Photo path as saved -> usable path, or null when the file is missing
function playerPhoto($path)
{
    $path = trim((string)$path);

    if ($path === '' || strtoupper($path) === 'NULL') {
        return null;
    }

    return is_file(__DIR__ . '/' . $path) ? $path : null;
}


function playerInitials($name)
{
    $words = array_slice(preg_split('/\s+/', trim((string)$name)), 0, 2);

    return strtoupper(implode('', array_map(function ($word) {
        return substr($word, 0, 1);
    }, $words)));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Players | T_Software</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

    <link href="./assets/style.css" rel="stylesheet" type="text/css">
    <link href="./assets/player_list.css?v=<?= filemtime(__DIR__ . '/assets/player_list.css') ?>" rel="stylesheet" type="text/css">

</head>

<body>

    <?php require_once 'admin_navbar.php'; ?>


    <main class="players-page">


        <!-- ==========================================
             HEADER
        =========================================== -->

        <div class="page-title">

            <div>
                <h1>Players</h1>
                <p><?= count($players) ?> players &middot; <?= $activeCount ?> active</p>
            </div>

            <a class="add-btn" href="admin_player_create.php">
                <i class="fa-solid fa-plus"></i>
                Add New
            </a>

        </div>


        <?php if (isset($_GET['changed'])): ?>
            <div class="success-message">
                <?= htmlspecialchars($_GET['changed']) ?>
                <?= ($_GET['status'] ?? '') === 'inactive'
                    ? 'has been deactivated and can no longer log in.'
                    : 'has been activated and can log in again.' ?>
            </div>
        <?php endif; ?>


        <section class="card">


            <!-- ==========================================
                 SEARCH & FILTERS
            =========================================== -->

            <div class="filters">

                <label class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <span class="visually-hidden">Search players</span>
                    <input id="player-search" type="search" placeholder="Search by name, ID or email" autocomplete="off">
                </label>

                <select id="nationality-filter" aria-label="Filter by nationality">
                    <option value="">All nationalities</option>
                    <?php foreach ($nationalities as $nationality): ?>
                        <option value="<?= htmlspecialchars(strtolower($nationality)) ?>"><?= htmlspecialchars($nationality) ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="status-filter" aria-label="Filter by status">
                    <option value="">All status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>

                <span class="result-count" id="resultCount"><?= count($players) ?> shown</span>

            </div>


            <!-- ==========================================
                 PLAYER LIST
            =========================================== -->

            <?php if ($players): ?>

                <div class="table-wrap">

                    <table class="players-table">

                        <thead>
                            <tr>
                                <th>Player</th>
                                <th>Nationality</th>
                                <th>Contact</th>
                                <th class="center">Tournaments</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($players as $player): ?>

                                <?php
                                $photo = playerPhoto($player['player_profile']);
                                $isActive = $player['player_active'] === 'active';
                                $nationality = strtoupper(trim((string)$player['player_nationality']));
                                ?>

                                <tr class="player-row"
                                    data-search="<?= htmlspecialchars(strtolower($player['playerID'] . ' ' . $player['player_full_name'] . ' ' . $player['player_email'])) ?>"
                                    data-nationality="<?= htmlspecialchars(strtolower($nationality)) ?>"
                                    data-status="<?= htmlspecialchars($player['player_active']) ?>">

                                    <td>
                                        <div class="player-cell">
                                            <span class="avatar">
                                                <?php if ($photo): ?>
                                                    <img src="<?= htmlspecialchars($photo) ?>" alt="">
                                                <?php else: ?>
                                                    <?= htmlspecialchars(playerInitials($player['player_full_name'])) ?>
                                                <?php endif; ?>
                                            </span>
                                            <span>
                                                <strong><?= htmlspecialchars($player['player_full_name']) ?></strong>
                                                <small><?= htmlspecialchars($player['playerID']) ?> &middot; <?= htmlspecialchars(ucfirst(strtolower((string)$player['player_gender'])) ?: '-') ?></small>
                                            </span>
                                        </div>
                                    </td>

                                    <td><?= htmlspecialchars($nationality ?: '-') ?></td>

                                    <td class="contact-cell">
                                        <span><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($player['player_contact'] ?: '-') ?></span>
                                        <span><i class="fa-regular fa-envelope"></i> <?= htmlspecialchars($player['player_email'] ?: '-') ?></span>
                                    </td>

                                    <td class="center"><?= (int)$player['tournaments_entered'] ?></td>

                                    <td>
                                        <span class="status-pill <?= $isActive ? 'active' : 'inactive' ?>">
                                            <?= $isActive ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>

                                    <td class="actions">

                                        <a class="action-btn" href="admin_player_profile.php?id=<?= urlencode($player['playerID']) ?>">
                                            View
                                        </a>

                                        <form method="POST" action="admin_player_lists.php"
                                              onsubmit="return confirm('<?= $isActive
                                                  ? 'Deactivate ' . htmlspecialchars(addslashes($player['player_full_name']), ENT_QUOTES) . '? They will not be able to log in.'
                                                  : 'Activate ' . htmlspecialchars(addslashes($player['player_full_name']), ENT_QUOTES) . '? They will be able to log in again.' ?>');">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="playerID" value="<?= htmlspecialchars($player['playerID']) ?>">
                                            <button type="submit" class="action-btn <?= $isActive ? 'danger' : 'positive' ?>">
                                                <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <p class="no-results" id="noResults" hidden>No players match your search.</p>

            <?php else: ?>

                <p class="no-results">No players yet.</p>

            <?php endif; ?>

        </section>

    </main>


    <script>
        // Search + filters (all on this page, no reload)
        (function () {

            const search = document.getElementById('player-search');
            const nationality = document.getElementById('nationality-filter');
            const status = document.getElementById('status-filter');
            const rows = document.querySelectorAll('.player-row');
            const count = document.getElementById('resultCount');
            const noResults = document.getElementById('noResults');

            function filterPlayers() {

                const term = search.value.toLowerCase().trim();
                let shown = 0;

                rows.forEach(function (row) {

                    const visible = row.dataset.search.includes(term)
                        && (!nationality.value || row.dataset.nationality === nationality.value)
                        && (!status.value || row.dataset.status === status.value);

                    row.hidden = !visible;

                    if (visible) {
                        shown++;
                    }
                });

                count.textContent = shown + ' shown';

                if (noResults) {
                    noResults.hidden = shown > 0;
                }
            }

            search.addEventListener('input', filterPlayers);
            nationality.addEventListener('change', filterPlayers);
            status.addEventListener('change', filterPlayers);

        })();
    </script>

</body>

</html>
