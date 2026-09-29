<?php
session_start();
require '../db.php';

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit();
}


/* =========================
   LOAD TOURNAMENTS
========================= */

$tournaments_query = "SELECT
                          t.tournamentid,
                          t.tournament_name,
                          t.tournament_type,
                          t.tournament_location,
                          t.tournament_country,
                          t.tournament_startdate,
                          t.tournament_enddate,
                          t.tournament_deadline,
                          t.tournament_picture,
                          COUNT(tr.registrationID) AS registered_count
                      FROM tournament t
                      LEFT JOIN tournament_register tr
                          ON tr.tournamentID = t.tournamentID
                      GROUP BY t.tournamentID
                      ORDER BY t.tournament_startdate ASC";

$tournaments_result = $conn->query($tournaments_query);

if (!$tournaments_result) {
    die("Could not load tournaments: " . $conn->error);
}


/* =========================
   SORT INTO SECTIONS
   - Registration open: deadline not passed yet
   - Current: started and not finished, or registration closed but not started yet
   - Previous: already finished
========================= */

$open_tournaments = [];
$current_tournaments = [];
$previous_tournaments = [];

$now = time();

while ($tournament = $tournaments_result->fetch_assoc()) {

    $start = tournament_time($tournament['tournament_startdate']);
    $end = tournament_time($tournament['tournament_enddate']);
    $deadline = tournament_time($tournament['tournament_deadline']);

    if ($deadline !== null && $deadline >= $now) {

        // Calendar days until the deadline (0 = today)
        $tournament['days_left'] = (int)round(
            (strtotime(date('Y-m-d', $deadline)) - strtotime(date('Y-m-d', $now))) / 86400
        );
        $open_tournaments[] = $tournament;

    } elseif ($end !== null && $end >= $now) {

        $tournament['is_ongoing'] = $start !== null && $start <= $now;
        $current_tournaments[] = $tournament;

    } else {

        $previous_tournaments[] = $tournament;

    }
}

// Closest deadline first
usort($open_tournaments, function ($a, $b) {
    return strcmp($a['tournament_deadline'], $b['tournament_deadline']);
});

// Happening now first, then the ones starting soonest
usort($current_tournaments, function ($a, $b) {
    if ($a['is_ongoing'] !== $b['is_ongoing']) {
        return $a['is_ongoing'] ? -1 : 1;
    }
    return strcmp($a['tournament_startdate'], $b['tournament_startdate']);
});

// Most recent first
$previous_tournaments = array_reverse($previous_tournaments);

// Previous tournaments shown before "Show all"
$previous_limit = 6;


/* =========================
   HELPERS
========================= */

// Timestamp of a date, or null when it's empty / 0000-00-00
function tournament_time($value) {

    if (empty($value) || strpos($value, '0000-00-00') === 0) {
        return null;
    }

    $time = strtotime($value);

    return $time === false ? null : $time;
}


// New uploads store a file name; older tournaments store a path
function tournament_picture($tournament) {

    $picture = trim($tournament['tournament_picture'] ?? '');

    if ($picture === '' || strtoupper($picture) === 'NULL') {
        return './assets/tournament.jpg';
    }

    $path = strpos($picture, '../') === 0 ? $picture : '../uploads/tournaments/' . $picture;

    return file_exists(__DIR__ . '/' . $path) ? $path : './assets/tournament.jpg';
}


// "05 Oct - 09 Oct 2026"
function tournament_dates($tournament) {

    $start = tournament_time($tournament['tournament_startdate']);
    $end = tournament_time($tournament['tournament_enddate']);

    if ($start === null) {
        return 'Dates not set';
    }

    if ($end === null || date('Y-m-d', $start) === date('Y-m-d', $end)) {
        return date('d M Y', $start);
    }

    if (date('Y', $start) === date('Y', $end)) {
        return date('d M', $start) . ' - ' . date('d M Y', $end);
    }

    return date('d M Y', $start) . ' - ' . date('d M Y', $end);
}


function tournament_place($tournament) {

    $parts = array_filter([
        trim($tournament['tournament_location'] ?? ''),
        trim($tournament['tournament_country'] ?? '')
    ]);

    return implode(', ', $parts);
}


/* =========================
   REGISTRATION OPEN CARD
========================= */

function open_card($tournament) {

    $id = (int)$tournament['tournamentid'];
    $days = $tournament['days_left'];

    if ($days <= 0) {
        $closing = 'Closes today';
    } elseif ($days === 1) {
        $closing = 'Closes tomorrow';
    } else {
        $closing = 'Closes in ' . $days . ' days';
    }

    ?>

    <article class="open-card">

        <div class="open-image">

            <img
                src="<?= htmlspecialchars(tournament_picture($tournament)) ?>"
                alt="<?= htmlspecialchars($tournament['tournament_name']) ?>"
            >

            <span class="type-badge">
                <?= htmlspecialchars(strtoupper($tournament['tournament_type'])) ?>
            </span>

            <span class="closing-badge <?= $days <= 3 ? 'urgent' : '' ?>">
                <?= $closing ?>
            </span>

        </div>

        <div class="open-content">

            <h3><?= htmlspecialchars($tournament['tournament_name']) ?></h3>

            <ul class="open-meta">

                <li>
                    <span>Tournament</span>
                    <strong><?= htmlspecialchars(tournament_dates($tournament)) ?></strong>
                </li>

                <li>
                    <span>Deadline</span>
                    <strong><?= date('d M Y, h:i A', strtotime($tournament['tournament_deadline'])) ?></strong>
                </li>

                <li>
                    <span>Registered</span>
                    <strong><?= (int)$tournament['registered_count'] ?> player<?= (int)$tournament['registered_count'] === 1 ? '' : 's' ?></strong>
                </li>

            </ul>

            <div class="open-actions">

                <a class="btn-primary" href="admin_each_tournament.php?id=<?= $id ?>">
                    Registrations
                </a>

                <a class="btn-outline" href="admin_view_tournaments.php?id=<?= $id ?>">
                    Details
                </a>

            </div>

        </div>

    </article>

    <?php
}


/* =========================
   CURRENT / PREVIOUS ROW
========================= */

function list_item($tournament, $badge, $badge_class, $hidden = false) {

    $id = (int)$tournament['tournamentid'];
    $place = tournament_place($tournament);

    ?>

    <a
        class="list-item <?= $hidden ? 'extra-item' : '' ?>"
        href="admin_each_tournament.php?id=<?= $id ?>"
        <?= $hidden ? 'hidden' : '' ?>
    >

        <img
            class="list-thumb"
            src="<?= htmlspecialchars(tournament_picture($tournament)) ?>"
            alt=""
        >

        <div class="list-info">

            <h4><?= htmlspecialchars($tournament['tournament_name']) ?></h4>

            <p>
                <?= htmlspecialchars(tournament_dates($tournament)) ?>
                <?php if ($place !== ''): ?>
                    &middot; <?= htmlspecialchars($place) ?>
                <?php endif; ?>
            </p>

            <p class="list-sub">
                <?= htmlspecialchars(strtoupper($tournament['tournament_type'])) ?>
                &middot; <?= (int)$tournament['registered_count'] ?> registered
            </p>

        </div>

        <span class="status-badge <?= $badge_class ?>"><?= $badge ?></span>

    </a>

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
    <link href="./assets/index.css?v=<?= filemtime(__DIR__ . '/assets/index.css') ?>" rel="stylesheet" type="text/css">

</head>


<body>


    <?php require_once 'admin_navbar.php'; ?>


    <main class="tournament-page">

        <div class="page-header">

            <h1>Tournaments</h1>

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
                placeholder="Search tournaments..."
                autocomplete="off"
            >

            <span class="search-icon">⌕</span>

        </div>



        <!-- =========================
            ROW 1: REGISTRATION OPEN
        ========================== -->

        <section class="panel">

            <div class="panel-header">

                <h2>
                    <span class="dot dot-open"></span>
                    Registration Open
                </h2>

                <span class="panel-count"><?= count($open_tournaments) ?></span>

            </div>

            <?php if ($open_tournaments): ?>

                <div class="open-grid">

                    <?php foreach ($open_tournaments as $tournament): ?>
                        <?php open_card($tournament); ?>
                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <p class="no-tournaments">No tournaments are open for registration.</p>

            <?php endif; ?>

            <p class="no-tournaments search-empty" hidden>No matching tournaments.</p>

        </section>



        <!-- =========================
            ROW 2: CURRENT | PREVIOUS
        ========================== -->

        <div class="two-columns">


            <!-- Current -->

            <section class="panel">

                <div class="panel-header">

                    <h2>
                        <span class="dot dot-current"></span>
                        Current Tournaments
                    </h2>

                    <span class="panel-count"><?= count($current_tournaments) ?></span>

                </div>

                <div class="list">

                    <?php if ($current_tournaments): ?>

                        <?php foreach ($current_tournaments as $tournament): ?>

                            <?php if ($tournament['is_ongoing']): ?>
                                <?php list_item($tournament, 'Ongoing', 'badge-ongoing'); ?>
                            <?php else: ?>
                                <?php list_item($tournament, 'Upcoming', 'badge-upcoming'); ?>
                            <?php endif; ?>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <p class="no-tournaments">No tournaments are being held right now.</p>

                    <?php endif; ?>

                    <p class="no-tournaments search-empty" hidden>No matching tournaments.</p>

                </div>

            </section>


            <!-- Previous -->

            <section class="panel">

                <div class="panel-header">

                    <h2>
                        <span class="dot dot-previous"></span>
                        Previous Tournaments
                    </h2>

                    <span class="panel-count"><?= count($previous_tournaments) ?></span>

                </div>

                <div class="list">

                    <?php if ($previous_tournaments): ?>

                        <?php foreach ($previous_tournaments as $index => $tournament): ?>
                            <?php list_item($tournament, 'Finished', 'badge-finished', $index >= $previous_limit); ?>
                        <?php endforeach; ?>

                    <?php else: ?>

                        <p class="no-tournaments">No previous tournaments.</p>

                    <?php endif; ?>

                    <p class="no-tournaments search-empty" hidden>No matching tournaments.</p>

                </div>

                <?php if (count($previous_tournaments) > $previous_limit): ?>

                    <button type="button" class="show-all-btn" id="showAllPrevious">
                        Show all (<?= count($previous_tournaments) ?>)
                    </button>

                <?php endif; ?>

            </section>

        </div>

    </main>



    <script>

    (function () {

        const searchInput = document.getElementById('tournament-search');
        const showAllBtn = document.getElementById('showAllPrevious');

        let showAll = false;


        function refresh() {

            const term = searchInput.value.toLowerCase().trim();

            document.querySelectorAll('.panel').forEach(function (panel) {

                const cards = panel.querySelectorAll('.open-card, .list-item');
                let visible = 0;

                cards.forEach(function (card) {

                    const matches = card.textContent.toLowerCase().includes(term);

                    // Older previous tournaments stay hidden until "Show all" (or a search)
                    const collapsed = card.classList.contains('extra-item') && !showAll && term === '';

                    card.hidden = !matches || collapsed;

                    if (!card.hidden) {
                        visible++;
                    }
                });

                const empty = panel.querySelector('.search-empty');

                if (empty) {
                    empty.hidden = !(cards.length && visible === 0);
                }
            });

            if (showAllBtn) {
                showAllBtn.hidden = term !== '';
            }
        }


        searchInput.addEventListener('input', refresh);


        if (showAllBtn) {

            showAllBtn.addEventListener('click', function () {

                showAll = !showAll;

                this.textContent = showAll
                    ? 'Show less'
                    : 'Show all (<?= count($previous_tournaments) ?>)';

                refresh();
            });
        }

    })();

    </script>


</body>

</html>
