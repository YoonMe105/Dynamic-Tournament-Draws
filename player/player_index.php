<?php
require_once "player_functions.php";

$player = getPlayer($conn, $playerID) ?? [];


/*
|--------------------------------------------------------------------------
| Open Tournaments
|--------------------------------------------------------------------------
| Tournaments still open for registration, with whether this player has
| already registered and whether it is a PSA (Men/Women) tournament.
*/

$stmt = $conn->prepare("
    SELECT
        t.tournamentID,
        t.tournament_name,
        t.tournament_type,
        t.tournament_location,
        t.tournament_country,
        t.tournament_startdate,
        t.tournament_enddate,
        t.tournament_deadline,
        t.tournament_fee,
        t.tournament_picture,

        EXISTS (
            SELECT 1
            FROM tournament_register tr
            WHERE tr.tournamentID = t.tournamentID
              AND tr.playerID = ?
        ) AS is_registered,

        EXISTS (
            SELECT 1
            FROM tournament_category tc
            WHERE tc.tournamentID = t.tournamentID
              AND tc.category_name IN ('Men', 'Women')
        ) AS is_psa

    FROM tournament t
    WHERE t.tournament_deadline >= NOW()
    ORDER BY t.tournament_deadline ASC
");

$stmt->bind_param("s", $playerID);
$stmt->execute();

$tournaments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();


/*
|--------------------------------------------------------------------------
| Card Data
|--------------------------------------------------------------------------
*/

$closingSoonCount = 0;


/*
| All of this player's registrations, including tournaments whose
| registration has closed (same number as "All" on My Tournaments)
*/

$countStmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM tournament_register tr
    JOIN tournament t ON t.tournamentID = tr.tournamentID
    WHERE tr.playerID = ?
");

$countStmt->bind_param("s", $playerID);
$countStmt->execute();

$registeredCount = (int) $countStmt->get_result()->fetch_assoc()['total'];

$countStmt->close();

foreach ($tournaments as &$tournament) {

    $deadline = strtotime($tournament['tournament_deadline']);

    $tournament['days_left'] = max(0, (int) ceil(($deadline - time()) / 86400));
    $tournament['closing_soon'] = $tournament['days_left'] <= 7;

    $type = strtolower($tournament['tournament_type'] ?? '');
    $tournament['scope'] = strpos($type, 'international') !== false ? 'international' : 'local';

    // New uploads store a file name, older tournaments store a path
    $picture = $tournament['tournament_picture'] ?? '';
    $tournament['picture_src'] = $picture === ''
        ? ''
        : (strpos($picture, '../') === 0 ? $picture : '../uploads/tournaments/' . $picture);

    $tournament['my_fee'] = playerFee($tournament, $player);

    $closingSoonCount += $tournament['closing_soon'] ? 1 : 0;
}

unset($tournament);


function cardDate($value)
{
    $time = strtotime($value ?? '');

    return ($time === false || $time <= 0) ? '-' : date("d M Y", $time);
}

$firstName = trim($player['player_first_name'] ?? ($_SESSION['firstname'] ?? ''));
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
    <link rel="stylesheet" href="./assets/css/navbar.css?v=<?= filemtime(__DIR__ . '/assets/css/navbar.css') ?>" type="text/css" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="./assets/css/index.css?v=<?= filemtime(__DIR__ . '/assets/css/index.css') ?>" type="text/css" />
</head>


<body>


    <?php include "player_navbar.php"; ?>


    <!-- =====================================================
         HERO
    ====================================================== -->

    <header class="hero">

        <div class="hero-inner">

            <p class="hero-eyebrow">
                <i class="fa-solid fa-table-tennis-paddle-ball"></i>
                Squash Tournaments
            </p>

            <h1>
                <?= $firstName !== '' ? 'Welcome back, ' . htmlspecialchars($firstName) . '!' : 'Find your next tournament' ?>
            </h1>

            <p class="hero-subtitle">
                Browse tournaments open for registration and sign up in a few steps.
            </p>


            <!-- Search -->
            <div class="hero-search">

                <i class="fa-solid fa-magnifying-glass"></i>

                <input
                    type="text"
                    id="tournamentSearch"
                    placeholder="Search by tournament name, location or country..."
                    autocomplete="off"
                >

            </div>


            <!-- Stats -->
            <div class="hero-stats">

                <div class="hero-stat">
                    <span class="stat-number"><?= count($tournaments) ?></span>
                    <span class="stat-label">Open for registration</span>
                </div>

                <a href="./my_tournaments.php" class="hero-stat">
                    <span class="stat-number"><?= $registeredCount ?></span>
                    <span class="stat-label"><?= $registeredCount === 1 ? 'My registration' : 'My registrations' ?></span>
                </a>

                <div class="hero-stat">
                    <span class="stat-number"><?= $closingSoonCount ?></span>
                    <span class="stat-label">Closing within 7 days</span>
                </div>

            </div>

        </div>

    </header>


    <main class="container">


        <!-- =====================================================
             TOOLBAR
        ====================================================== -->

        <div class="toolbar">

            <div class="filter-chips" role="tablist">

                <button type="button" class="chip active" data-filter="all">All</button>
                <button type="button" class="chip" data-filter="local">Local</button>
                <button type="button" class="chip" data-filter="international">International</button>
                <button type="button" class="chip" data-filter="closing">
                    <i class="fa-solid fa-fire"></i> Closing soon
                </button>
                <button type="button" class="chip" data-filter="registered">
                    <i class="fa-solid fa-circle-check"></i> Registered
                </button>

            </div>

            <div class="toolbar-right">

                <span class="result-count" id="resultCount">
                    <?= count($tournaments) ?> <?= count($tournaments) === 1 ? 'tournament' : 'tournaments' ?>
                </span>

                <select id="sortSelect" class="sort-select" aria-label="Sort tournaments">
                    <option value="deadline">Deadline: soonest</option>
                    <option value="start">Start date</option>
                    <option value="name">Name (A&ndash;Z)</option>
                </select>

            </div>

        </div>


        <!-- =====================================================
             GRID
        ====================================================== -->

        <?php if (empty($tournaments)): ?>

            <div class="empty-state">

                <i class="fa-solid fa-calendar-xmark"></i>

                <h2>No tournaments open right now</h2>

                <p>Check back soon, or see the tournaments you've already joined.</p>

                <a href="./my_tournaments.php" class="btn-primary">My Tournaments</a>

            </div>

        <?php else: ?>

            <div class="tournament-grid" id="tournamentGrid">

                <?php foreach ($tournaments as $tournament): ?>

                    <a
                        href="tournament_details.php?tournamentID=<?= (int) $tournament['tournamentID'] ?>"
                        class="tournament-card"
                        data-scope="<?= $tournament['scope'] ?>"
                        data-closing="<?= $tournament['closing_soon'] ? '1' : '0' ?>"
                        data-registered="<?= $tournament['is_registered'] ? '1' : '0' ?>"
                        data-deadline="<?= strtotime($tournament['tournament_deadline']) ?>"
                        data-start="<?= (int) strtotime($tournament['tournament_startdate']) ?>"
                        data-name="<?= htmlspecialchars(strtolower($tournament['tournament_name'])) ?>"
                        data-search="<?= htmlspecialchars(strtolower(
                            $tournament['tournament_name'] . ' ' .
                            $tournament['tournament_location'] . ' ' .
                            ($tournament['tournament_country'] ?? '') . ' ' .
                            $tournament['tournament_type']
                        )) ?>"
                    >


                        <!-- Image + badges -->

                        <div class="card-image">

                            <?php if ($tournament['picture_src'] !== ''): ?>

                                <img
                                    src="<?= htmlspecialchars($tournament['picture_src']) ?>"
                                    alt="<?= htmlspecialchars($tournament['tournament_name']) ?>"
                                    loading="lazy"
                                    onerror="this.remove()"
                                >

                            <?php endif; ?>

                            <i class="fa-solid fa-trophy card-placeholder"></i>

                            <div class="card-badges">

                                <?php if (!empty($tournament['tournament_type'])): ?>
                                    <span class="badge badge-type"><?= htmlspecialchars(strtoupper($tournament['tournament_type'])) ?></span>
                                <?php endif; ?>

                                <?php if ($tournament['is_psa']): ?>
                                    <span class="badge badge-psa">PSA</span>
                                <?php endif; ?>

                            </div>

                            <?php if ($tournament['is_registered']): ?>

                                <span class="badge badge-registered">
                                    <i class="fa-solid fa-circle-check"></i> Registered
                                </span>

                            <?php elseif ($tournament['closing_soon']): ?>

                                <span class="badge badge-closing">
                                    <i class="fa-solid fa-hourglass-half"></i>
                                    <?= $tournament['days_left'] === 0
                                        ? 'Closes today'
                                        : 'Closes in ' . $tournament['days_left'] . ($tournament['days_left'] === 1 ? ' day' : ' days') ?>
                                </span>

                            <?php endif; ?>

                        </div>


                        <!-- Body -->

                        <div class="card-body">

                            <p class="card-location">
                                <i class="fa-solid fa-location-dot"></i>
                                <?= htmlspecialchars($tournament['tournament_location'] ?: '-') ?><?= !empty($tournament['tournament_country']) ? ', ' . htmlspecialchars($tournament['tournament_country']) : '' ?>
                            </p>

                            <h3><?= htmlspecialchars($tournament['tournament_name']) ?></h3>

                            <ul class="card-meta">

                                <li>
                                    <i class="fa-regular fa-calendar"></i>
                                    <?= cardDate($tournament['tournament_startdate']) ?> &ndash; <?= cardDate($tournament['tournament_enddate']) ?>
                                </li>

                                <li>
                                    <i class="fa-regular fa-clock"></i>
                                    Register by <?= date("d M Y, h:i A", strtotime($tournament['tournament_deadline'])) ?>
                                </li>

                            </ul>

                        </div>


                        <!-- Footer -->

                        <div class="card-footer">

                            <div class="card-fee">
                                <span class="fee-label">Entry fee<?= $tournament['my_fee']['currency'] !== 'OTHER' ? ' (' . $tournament['my_fee']['currency'] . ')' : '' ?></span>
                                <span class="fee-value" title="<?= htmlspecialchars($tournament['tournament_fee']) ?>">
                                    <?= htmlspecialchars($tournament['my_fee']['fee'] ?: '-') ?>
                                </span>
                            </div>

                            <span class="card-cta">
                                <?= $tournament['is_registered'] ? 'View' : 'Details' ?>
                                <i class="fa-solid fa-arrow-right"></i>
                            </span>

                        </div>

                    </a>

                <?php endforeach; ?>

            </div>


            <div class="empty-state" id="noResults" hidden>

                <i class="fa-solid fa-magnifying-glass"></i>

                <h2>No tournaments found</h2>

                <p>Try another search or filter.</p>

                <button type="button" class="btn-primary" id="clearFilters">Clear filters</button>

            </div>

        <?php endif; ?>

    </main>


    <script>
        document.addEventListener("DOMContentLoaded", function () {

            const grid = document.getElementById("tournamentGrid");

            if (!grid) {
                return;
            }

            const searchInput = document.getElementById("tournamentSearch");
            const sortSelect = document.getElementById("sortSelect");
            const chips = document.querySelectorAll(".chip");
            const cards = Array.from(grid.querySelectorAll(".tournament-card"));
            const resultCount = document.getElementById("resultCount");
            const noResults = document.getElementById("noResults");

            let filter = "all";


            function matchesFilter(card) {
                switch (filter) {
                    case "local":         return card.dataset.scope === "local";
                    case "international": return card.dataset.scope === "international";
                    case "closing":       return card.dataset.closing === "1";
                    case "registered":    return card.dataset.registered === "1";
                    default:              return true;
                }
            }


            function update() {

                const search = searchInput.value.toLowerCase().trim();

                let visible = 0;

                cards.forEach(function (card) {

                    const show = matchesFilter(card) && card.dataset.search.includes(search);

                    card.hidden = !show;

                    if (show) {
                        visible++;
                    }

                });

                resultCount.textContent = visible + (visible === 1 ? " tournament" : " tournaments");
                noResults.hidden = visible > 0;
            }


            function sortCards() {

                const key = sortSelect.value;

                cards.sort(function (a, b) {
                    if (key === "name") {
                        return a.dataset.name.localeCompare(b.dataset.name);
                    }
                    return Number(a.dataset[key]) - Number(b.dataset[key]);
                });

                cards.forEach(function (card) {
                    grid.appendChild(card);
                });
            }


            chips.forEach(function (chip) {

                chip.addEventListener("click", function () {

                    chips.forEach(function (c) {
                        c.classList.remove("active");
                    });

                    this.classList.add("active");

                    filter = this.dataset.filter;

                    update();
                });

            });


            document.getElementById("clearFilters").addEventListener("click", function () {
                searchInput.value = "";
                chips[0].click();
            });


            searchInput.addEventListener("input", update);

            sortSelect.addEventListener("change", sortCards);

        });
    </script>


</body>

</html>
