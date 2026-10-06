<?php
require_once '../db.php';
require_once 'admin_auth.php';
require_once '../platform_fee.php';


/* =========================
   LOAD TOURNAMENTS
========================= */

// Tournament Organizers only see the tournaments they created
$creatorFilter = isPlatformAdmin()
    ? ""
    : "WHERE t.creatorID = '" . $conn->real_escape_string($_SESSION['userid']) . "'";

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
                      $creatorFilter
                      GROUP BY t.tournamentID
                      ORDER BY t.tournament_startdate ASC";

$tournaments_result = $conn->query($tournaments_query);

if (!$tournaments_result) {
    die("Could not load tournaments: " . $conn->error);
}


/* =========================
   SORT INTO THREE ROWS
   1. Upcoming: not started yet - registration open, or closed and starting soon
   2. Ongoing:  started and not finished
   3. Previous: already finished
========================= */

$open_tournaments = [];
$upcoming_tournaments = [];   // registration closed, not started yet
$ongoing_tournaments = [];
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

    } elseif ($start !== null && $start > $now) {

        $upcoming_tournaments[] = $tournament;

    } elseif ($end !== null && $end >= $now) {

        $ongoing_tournaments[] = $tournament;

    } else {

        $previous_tournaments[] = $tournament;

    }
}

// Closest deadline first
usort($open_tournaments, function ($a, $b) {
    return strcmp($a['tournament_deadline'], $b['tournament_deadline']);
});

// Starting soonest first
$by_start = function ($a, $b) {
    return strcmp($a['tournament_startdate'], $b['tournament_startdate']);
};

usort($upcoming_tournaments, $by_start);
usort($ongoing_tournaments, $by_start);

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

// Clicking a tournament opens its page (Tournament Organizers only see their own here)
function tournament_link($id) {
    return 'admin_each_tournament.php?id=' . (int)$id;
}


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

            <?php global $conn; if (platformFeeUnpaid($conn, $id)): ?>
                <span class="closing-badge urgent" style="bottom:auto;top:10px;right:10px;">Fee unpaid</span>
            <?php endif; ?>

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
    $link = tournament_link($id);
    $tag = $link ? 'a' : 'div';

    ?>

    <<?= $tag ?>
        class="list-item <?= $hidden ? 'extra-item' : '' ?>"
        <?= $link ? 'href="' . htmlspecialchars($link) . '"' : '' ?>
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

    </<?= $tag ?>>

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

            <h1><?= isPlatformAdmin() ? 'Tournaments' : 'My Tournaments' ?></h1>

            <a class="add-new-btn" href="admin_create_tournament.php">
                <span>＋</span>
                Add New
            </a>

        </div>



        <?php if (isset($_GET['created'])): ?>
            <p class="created-message">Tournament created successfully.</p>
        <?php endif; ?>


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
            ROW 1: UPCOMING (not started yet)
        ========================== -->

        <section class="panel">

            <div class="panel-header">

                <h2>
                    <span class="dot dot-open"></span>
                    Upcoming Tournaments
                </h2>

                <span class="panel-count"><?= count($open_tournaments) + count($upcoming_tournaments) ?></span>

            </div>

            <?php if ($open_tournaments): ?>

                <div class="open-grid">

                    <?php foreach ($open_tournaments as $tournament): ?>
                        <?php open_card($tournament); ?>
                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

            <?php if ($upcoming_tournaments): ?>

                <h3 class="sub-heading">Registration closed &ndash; starting soon</h3>

                <div class="list list-grid">
                    <?php foreach ($upcoming_tournaments as $tournament): ?>
                        <?php list_item($tournament, 'Upcoming', 'badge-upcoming'); ?>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

            <?php if (!$open_tournaments && !$upcoming_tournaments): ?>
                <p class="no-tournaments">No upcoming tournaments.</p>
            <?php endif; ?>

            <p class="no-tournaments search-empty" hidden>No matching tournaments.</p>

        </section>



        <!-- =========================
            ROW 2: ONGOING
        ========================== -->

        <section class="panel">

            <div class="panel-header">

                <h2>
                    <span class="dot dot-current"></span>
                    Ongoing Tournaments
                </h2>

                <span class="panel-count"><?= count($ongoing_tournaments) ?></span>

            </div>

            <div class="list list-grid">

                <?php if ($ongoing_tournaments): ?>

                    <?php foreach ($ongoing_tournaments as $tournament): ?>
                        <?php list_item($tournament, 'Ongoing', 'badge-ongoing'); ?>
                    <?php endforeach; ?>

                <?php else: ?>

                    <p class="no-tournaments">No tournaments are being held right now.</p>

                <?php endif; ?>

                <p class="no-tournaments search-empty" hidden>No matching tournaments.</p>

            </div>

        </section>



        <!-- =========================
            ROW 3: PREVIOUS
        ========================== -->

        <section class="panel">

            <div class="panel-header">

                <h2>
                    <span class="dot dot-previous"></span>
                    Previous Tournaments
                </h2>

                <span class="panel-count"><?= count($previous_tournaments) ?></span>

            </div>

            <div class="list list-grid">

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
