<?php
session_start();
require '../db.php';
require 'admin_age_check.php';

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function fetchAll($conn, $sql)
{
    $result = $conn->query($sql);

    if (!$result) {
        die("Could not load the dashboard: " . $conn->error);
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function fetchCount($conn, $sql)
{
    return (int)(fetchAll($conn, $sql)[0]['total'] ?? 0);
}

function shortDate($value)
{
    if (empty($value) || strpos($value, '0000') === 0) {
        return '-';
    }

    return date('d M Y', strtotime($value));
}

// "today", "tomorrow", "in 5 days"
function daysFromNow($value)
{
    $days = (int)round((strtotime(date('Y-m-d', strtotime($value))) - strtotime(date('Y-m-d'))) / 86400);

    if ($days === 0) {
        return 'today';
    }

    if ($days === 1) {
        return 'tomorrow';
    }

    return 'in ' . $days . ' days';
}

// Tournaments that haven't finished yet (0000-00-00 dates are ignored)
$activeTournament = "t.tournament_enddate >= NOW()";


/*
|--------------------------------------------------------------------------
| Stat Tiles
|--------------------------------------------------------------------------
*/

$stats = [
    'open' => fetchCount($conn, "
        SELECT COUNT(*) AS total
        FROM tournament
        WHERE tournament_deadline >= NOW()
    "),
    'ongoing' => fetchCount($conn, "
        SELECT COUNT(*) AS total
        FROM tournament
        WHERE tournament_startdate <= NOW()
        AND tournament_enddate >= NOW()
    "),
    'registrations' => fetchCount($conn, "
        SELECT COUNT(*) AS total
        FROM tournament_register tr
        JOIN tournament t ON t.tournamentID = tr.tournamentID
        WHERE $activeTournament
    "),
    'players' => fetchCount($conn, "SELECT COUNT(*) AS total FROM players")
];


/*
|--------------------------------------------------------------------------
| Needs Attention: payments to check (receipt uploaded)
|--------------------------------------------------------------------------
*/

$pendingPayments = fetchAll($conn, "
    SELECT tr.registrationID, tr.category_registered, tr.payment_proof,
           p.player_full_name, t.tournamentID, t.tournament_name
    FROM tournament_register tr
    JOIN players p ON p.playerID = tr.playerID
    JOIN tournament t ON t.tournamentID = tr.tournamentID
    WHERE UPPER(TRIM(tr.payment_status)) = 'PENDING'
    ORDER BY tr.registrationID ASC
");


/*
|--------------------------------------------------------------------------
| Needs Attention: waiting for endorsement
|--------------------------------------------------------------------------
*/

$notEndorsed = fetchAll($conn, "
    SELECT tr.registrationID, tr.category_registered, tr.payment_status,
           p.player_full_name, t.tournamentID, t.tournament_name
    FROM tournament_register tr
    JOIN players p ON p.playerID = tr.playerID
    JOIN tournament t ON t.tournamentID = tr.tournamentID
    WHERE UPPER(TRIM(tr.endorsement)) <> 'ENDORSED'
    AND $activeTournament
    ORDER BY t.tournament_startdate ASC, tr.registrationID ASC
");


/*
|--------------------------------------------------------------------------
| Needs Attention: players too old for their category
|--------------------------------------------------------------------------
*/

$wrongCategories = [];

$ageRows = fetchAll($conn, "
    SELECT tr.registrationID, tr.category_registered,
           p.player_full_name, p.player_dob,
           t.tournamentID, t.tournament_name, t.tournament_age_cutoff
    FROM tournament_register tr
    JOIN players p ON p.playerID = tr.playerID
    JOIN tournament t ON t.tournamentID = tr.tournamentID
    WHERE $activeTournament
    AND t.tournament_age_cutoff IS NOT NULL
    ORDER BY t.tournament_startdate ASC
");

foreach ($ageRows as $row) {

    $wrong = wrongAgeCategory($row['category_registered'], $row['player_dob'], $row['tournament_age_cutoff']);

    if ($wrong !== null) {
        $wrongCategories[] = $row + $wrong;
    }
}


/*
|--------------------------------------------------------------------------
| Needs Attention: draws to make
|--------------------------------------------------------------------------
| Registration closed, tournament not finished, and a category with at
| least 2 endorsed players but no draw yet.
*/

$drawsToMake = fetchAll($conn, "
    SELECT t.tournamentID, t.tournament_name, t.tournament_startdate,
           tr.category_registered, COUNT(*) AS players
    FROM tournament_register tr
    JOIN tournament t ON t.tournamentID = tr.tournamentID
    WHERE t.tournament_deadline < NOW()
    AND $activeTournament
    AND UPPER(TRIM(tr.endorsement)) = 'ENDORSED'
    AND NOT EXISTS (
        SELECT 1 FROM matches m
        WHERE m.tournamentID = tr.tournamentID
        AND m.category_registered = tr.category_registered
    )
    GROUP BY t.tournamentID, tr.category_registered
    HAVING COUNT(*) >= 2
    ORDER BY t.tournament_startdate ASC, tr.category_registered ASC
");


/*
|--------------------------------------------------------------------------
| Recent Registrations
|--------------------------------------------------------------------------
*/

$recentRegistrations = fetchAll($conn, "
    SELECT tr.registrationID, tr.category_registered, tr.payment_status, tr.endorsement,
           p.player_full_name, t.tournament_name
    FROM tournament_register tr
    JOIN players p ON p.playerID = tr.playerID
    JOIN tournament t ON t.tournamentID = tr.tournamentID
    ORDER BY tr.registrationID DESC
    LIMIT 8
");


/*
|--------------------------------------------------------------------------
| This Week: deadlines and start dates in the next 7 days
|--------------------------------------------------------------------------
*/

$thisWeek = [];

foreach (fetchAll($conn, "
    SELECT tournamentID, tournament_name, tournament_deadline AS event_date
    FROM tournament
    WHERE tournament_deadline BETWEEN NOW() AND NOW() + INTERVAL 7 DAY
") as $row) {
    $thisWeek[] = $row + ['event' => 'Registration closes'];
}

foreach (fetchAll($conn, "
    SELECT tournamentID, tournament_name, tournament_startdate AS event_date
    FROM tournament
    WHERE tournament_startdate BETWEEN NOW() AND NOW() + INTERVAL 7 DAY
") as $row) {
    $thisWeek[] = $row + ['event' => 'Tournament starts'];
}

usort($thisWeek, function ($a, $b) {
    return strcmp($a['event_date'], $b['event_date']);
});


/*
|--------------------------------------------------------------------------
| Missing Details (tournaments that haven't finished)
|--------------------------------------------------------------------------
*/

$missingDetails = [];

foreach (fetchAll($conn, "
    SELECT t.tournamentID, t.tournament_name, t.tournament_age_cutoff,
           t.tournament_country, t.tournament_picture,
           (SELECT COUNT(*) FROM tournament_category c WHERE c.tournamentID = t.tournamentID) AS categories
    FROM tournament t
    WHERE $activeTournament
    ORDER BY t.tournament_startdate ASC
") as $row) {

    $missing = [];

    if ((int)$row['categories'] === 0) {
        $missing[] = 'categories';
    }

    if (empty($row['tournament_age_cutoff'])) {
        $missing[] = 'age cut-off';
    }

    if (empty($row['tournament_country'])) {
        $missing[] = 'country';
    }

    $picture = trim($row['tournament_picture'] ?? '');

    if ($picture === '' || strtoupper($picture) === 'NULL') {
        $missing[] = 'picture';
    }

    if ($missing) {
        $missingDetails[] = $row + ['missing' => $missing];
    }
}


/*
|--------------------------------------------------------------------------
| Attention cards
|--------------------------------------------------------------------------
*/

$attentionCards = [
    [
        'title' => 'Payments to check',
        'icon' => 'fa-receipt',
        'tone' => 'amber',
        'items' => $pendingPayments,
        'empty' => 'No receipts waiting.'
    ],
    [
        'title' => 'Waiting for endorsement',
        'icon' => 'fa-user-check',
        'tone' => 'blue',
        'items' => $notEndorsed,
        'empty' => 'Everyone is endorsed.'
    ],
    [
        'title' => 'Wrong category',
        'icon' => 'fa-triangle-exclamation',
        'tone' => 'red',
        'items' => $wrongCategories,
        'empty' => 'No age problems.'
    ],
    [
        'title' => 'Draws to make',
        'icon' => 'fa-sitemap',
        'tone' => 'purple',
        'items' => $drawsToMake,
        'empty' => 'No draws waiting.'
    ]
];

$attentionTotal = count($pendingPayments) + count($notEndorsed) + count($wrongCategories) + count($drawsToMake);

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$attentionLimit = 5;

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

    <link href="./assets/style.css" rel="stylesheet" type="text/css">
    <link href="./assets/dashboard.css?v=<?= filemtime(__DIR__ . '/assets/dashboard.css') ?>" rel="stylesheet" type="text/css">

</head>


<body>

    <?php require_once 'admin_navbar.php'; ?>


    <main class="dashboard-page">


        <!-- =========================
            HEADER
        ========================== -->

        <div class="dash-header">

            <div>
                <p class="dash-date"><?= date('l, d F Y') ?></p>
                <h1>Welcome back, <?= htmlspecialchars($adminName) ?></h1>
                <p class="dash-subtitle">
                    <?php if ($attentionTotal > 0): ?>
                        You have <strong><?= $attentionTotal ?></strong> item<?= $attentionTotal === 1 ? '' : 's' ?> that need your attention.
                    <?php else: ?>
                        Everything is up to date.
                    <?php endif; ?>
                </p>
            </div>

            <div class="dash-header-actions">

                <a class="btn-outline" href="admin_index.php">
                    <i class="fa-solid fa-trophy"></i>
                    All Tournaments
                </a>

                <a class="btn-primary" href="admin_create_tournament.php">
                    <i class="fa-solid fa-plus"></i>
                    Add Tournament
                </a>

            </div>

        </div>



        <!-- =========================
            STAT TILES
        ========================== -->

        <section class="stat-grid">

            <a class="stat-tile" href="admin_index.php">
                <span class="stat-icon tone-green"><i class="fa-solid fa-door-open"></i></span>
                <span class="stat-number"><?= $stats['open'] ?></span>
                <span class="stat-label">Open for registration</span>
            </a>

            <a class="stat-tile" href="admin_index.php">
                <span class="stat-icon tone-amber"><i class="fa-solid fa-table-tennis-paddle-ball"></i></span>
                <span class="stat-number"><?= $stats['ongoing'] ?></span>
                <span class="stat-label">Ongoing tournaments</span>
            </a>

            <div class="stat-tile">
                <span class="stat-icon tone-blue"><i class="fa-solid fa-clipboard-list"></i></span>
                <span class="stat-number"><?= $stats['registrations'] ?></span>
                <span class="stat-label">Registrations (upcoming &amp; ongoing)</span>
            </div>

            <div class="stat-tile">
                <span class="stat-icon tone-purple"><i class="fa-solid fa-users"></i></span>
                <span class="stat-number"><?= $stats['players'] ?></span>
                <span class="stat-label">Registered players</span>
            </div>

        </section>



        <!-- =========================
            NEEDS YOUR ATTENTION
        ========================== -->

        <h2 class="section-heading">Needs your attention</h2>

        <section class="attention-grid">

            <?php foreach ($attentionCards as $index => $card): ?>

                <article class="panel attention-card">

                    <div class="panel-header">

                        <span class="stat-icon tone-<?= $card['tone'] ?>"><i class="fa-solid <?= $card['icon'] ?>"></i></span>

                        <h3><?= $card['title'] ?></h3>

                        <span class="count-badge <?= $card['items'] ? 'tone-' . $card['tone'] : '' ?>">
                            <?= count($card['items']) ?>
                        </span>

                    </div>


                    <?php if (!$card['items']): ?>

                        <p class="all-clear">
                            <i class="fa-solid fa-circle-check"></i>
                            <?= $card['empty'] ?>
                        </p>

                    <?php else: ?>

                        <ul class="item-list">

                            <?php foreach (array_slice($card['items'], 0, $attentionLimit) as $item): ?>

                                <li>

                                    <?php if ($index === 3): // draws ?>

                                        <a href="admin_draw.php?id=<?= (int)$item['tournamentID'] ?>&category=<?= urlencode($item['category_registered']) ?>">
                                            <span class="item-main">
                                                <strong><?= htmlspecialchars($item['category_registered']) ?></strong>
                                                &middot; <?= (int)$item['players'] ?> players
                                            </span>
                                            <span class="item-sub">
                                                <?= htmlspecialchars($item['tournament_name']) ?>
                                                &middot; starts <?= shortDate($item['tournament_startdate']) ?>
                                            </span>
                                        </a>

                                    <?php else: ?>

                                        <a href="admin_player_tournament_registration.php?id=<?= (int)$item['registrationID'] ?>">
                                            <span class="item-main">
                                                <strong><?= htmlspecialchars($item['player_full_name']) ?></strong>
                                                &middot; <?= htmlspecialchars($item['category_registered']) ?>

                                                <?php if ($index === 2): // wrong category ?>
                                                    <span class="item-note">
                                                        is <?= (int)$item['age'] ?>, must be under <?= (int)$item['max'] ?>
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                            <span class="item-sub"><?= htmlspecialchars($item['tournament_name']) ?></span>
                                        </a>

                                    <?php endif; ?>

                                </li>

                            <?php endforeach; ?>

                        </ul>

                        <?php if (count($card['items']) > $attentionLimit): ?>
                            <p class="more-items">and <?= count($card['items']) - $attentionLimit ?> more</p>
                        <?php endif; ?>

                    <?php endif; ?>

                </article>

            <?php endforeach; ?>

        </section>



        <!-- =========================
            RECENT | THIS WEEK + MISSING DETAILS
        ========================== -->

        <div class="bottom-grid">


            <!-- Recent registrations -->

            <section class="panel">

                <div class="panel-header">
                    <span class="stat-icon tone-blue"><i class="fa-solid fa-clock-rotate-left"></i></span>
                    <h3>Recent registrations</h3>
                </div>

                <?php if ($recentRegistrations): ?>

                    <div class="table-wrap">

                        <table class="recent-table">

                            <thead>
                                <tr>
                                    <th>Player</th>
                                    <th>Tournament</th>
                                    <th>Category</th>
                                    <th>Payment</th>
                                    <th>Endorsement</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($recentRegistrations as $row): ?>

                                    <?php
                                    $payment = strtoupper(trim($row['payment_status'] ?? '')) ?: 'NOT PAID';
                                    $endorsement = strtoupper(trim($row['endorsement'] ?? '')) ?: 'NOT ENDORSED';
                                    ?>

                                    <tr onclick="location.href='admin_player_tournament_registration.php?id=<?= (int)$row['registrationID'] ?>'">
                                        <td class="player-cell"><?= htmlspecialchars($row['player_full_name']) ?></td>
                                        <td class="tournament-cell" title="<?= htmlspecialchars($row['tournament_name']) ?>"><?= htmlspecialchars($row['tournament_name']) ?></td>
                                        <td><?= htmlspecialchars($row['category_registered']) ?></td>
                                        <td>
                                            <span class="status-pill <?= $payment === 'PAID' ? 'good' : ($payment === 'PENDING' ? 'wait' : ($payment === 'REFUNDED' ? 'bad' : '')) ?>">
                                                <?= htmlspecialchars($payment) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-pill <?= $endorsement === 'ENDORSED' ? 'good' : '' ?>">
                                                <?= htmlspecialchars($endorsement) ?>
                                            </span>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <p class="all-clear">No registrations yet.</p>

                <?php endif; ?>

            </section>


            <div class="side-stack">


                <!-- This week -->

                <section class="panel">

                    <div class="panel-header">
                        <span class="stat-icon tone-green"><i class="fa-regular fa-calendar"></i></span>
                        <h3>Next 7 days</h3>
                    </div>

                    <?php if ($thisWeek): ?>

                        <ul class="timeline">

                            <?php foreach ($thisWeek as $event): ?>

                                <li>
                                    <span class="timeline-date">
                                        <strong><?= date('d', strtotime($event['event_date'])) ?></strong>
                                        <?= date('M', strtotime($event['event_date'])) ?>
                                    </span>

                                    <a href="admin_each_tournament.php?id=<?= (int)$event['tournamentID'] ?>">
                                        <span class="item-main"><?= htmlspecialchars($event['tournament_name']) ?></span>
                                        <span class="item-sub">
                                            <?= $event['event'] ?> <?= daysFromNow($event['event_date']) ?>,
                                            <?= date('h:i A', strtotime($event['event_date'])) ?>
                                        </span>
                                    </a>
                                </li>

                            <?php endforeach; ?>

                        </ul>

                    <?php else: ?>

                        <p class="all-clear">Nothing in the next 7 days.</p>

                    <?php endif; ?>

                </section>


                <!-- Missing details -->

                <section class="panel">

                    <div class="panel-header">
                        <span class="stat-icon tone-amber"><i class="fa-solid fa-pen-to-square"></i></span>
                        <h3>Missing details</h3>
                        <span class="count-badge <?= $missingDetails ? 'tone-amber' : '' ?>"><?= count($missingDetails) ?></span>
                    </div>

                    <?php if ($missingDetails): ?>

                        <ul class="item-list">

                            <?php foreach (array_slice($missingDetails, 0, $attentionLimit) as $row): ?>

                                <li>
                                    <a href="admin_view_tournaments.php?id=<?= (int)$row['tournamentID'] ?>&edit=1">
                                        <span class="item-main"><strong><?= htmlspecialchars($row['tournament_name']) ?></strong></span>
                                        <span class="item-sub">No <?= htmlspecialchars(implode(', ', $row['missing'])) ?></span>
                                    </a>
                                </li>

                            <?php endforeach; ?>

                        </ul>

                        <?php if (count($missingDetails) > $attentionLimit): ?>
                            <p class="more-items">and <?= count($missingDetails) - $attentionLimit ?> more</p>
                        <?php endif; ?>

                    <?php else: ?>

                        <p class="all-clear">
                            <i class="fa-solid fa-circle-check"></i>
                            All tournaments are complete.
                        </p>

                    <?php endif; ?>

                </section>

            </div>

        </div>

    </main>

</body>

</html>
