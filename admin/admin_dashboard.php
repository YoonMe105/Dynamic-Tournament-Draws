<?php
require_once '../db.php';
require_once 'admin_auth.php';

requirePlatformAdmin();



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
| Tournament Registrations: the newest tournaments created on the platform
|--------------------------------------------------------------------------
| Newest first (tournament IDs go up as tournaments are created), with who
| created it and, for Tournament Organizers, whether the T_Software fee is paid.
*/

$newTournaments = fetchAll($conn, "
    SELECT t.tournamentID, t.tournament_name, t.tournament_type, t.tournament_country,
           t.tournament_startdate, t.tournament_enddate, t.creatorID,
           a.admin_name AS creator_name, a.admin_role AS creator_role,
           pp.status AS fee_status, pp.amount AS fee_amount, pp.currency AS fee_currency,
           (SELECT COUNT(*) FROM tournament_register tr WHERE tr.tournamentID = t.tournamentID) AS players
    FROM tournament t
    LEFT JOIN admins a ON a.adminID = t.creatorID COLLATE utf8mb4_general_ci
    LEFT JOIN tournament_platform_payments pp ON pp.tournamentID = t.tournamentID
    ORDER BY t.tournamentID DESC
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
| Players by country (top 5)
|--------------------------------------------------------------------------
| Nationalities are saved as words like MALAYSIAN; the chart shows the
| country (Malaysia). Everyone outside the top 5 is counted in one line.
*/

function countryFromNationality($nationality)
{
    $countries = [
        'MALAYSIAN' => 'Malaysia', 'SINGAPOREAN' => 'Singapore', 'AUSTRALIAN' => 'Australia',
        'CHINESE' => 'China', 'INDIAN' => 'India', 'CANADIAN' => 'Canada', 'AMERICAN' => 'United States',
        'BRITISH' => 'United Kingdom', 'ENGLISH' => 'England', 'JAPANESE' => 'Japan', 'KOREAN' => 'Korea',
        'SOUTH KOREAN' => 'Korea', 'INDONESIAN' => 'Indonesia', 'FILIPINO' => 'Philippines',
        'PHILIPPINE' => 'Philippines', 'THAI' => 'Thailand', 'PAKISTANI' => 'Pakistan',
        'SRI LANKAN' => 'Sri Lanka', 'EGYPTIAN' => 'Egypt', 'NEW ZEALANDER' => 'New Zealand',
        'TAIWANESE' => 'Chinese Taipei', 'MACANESE' => 'Macau', 'HONG KONGER' => 'Hong Kong',
    ];

    $nationality = strtoupper(trim((string)$nationality));

    if ($nationality === '') {
        return 'Not specified';
    }

    return $countries[$nationality] ?? ucwords(strtolower($nationality));
}

$countryCounts = [];

foreach (fetchAll($conn, "SELECT player_nationality, COUNT(*) AS total FROM players GROUP BY player_nationality") as $row) {
    $country = countryFromNationality($row['player_nationality']);
    $countryCounts[$country] = ($countryCounts[$country] ?? 0) + (int)$row['total'];
}

arsort($countryCounts);

$topCountries = array_slice($countryCounts, 0, 5, true);
$otherCountries = array_slice($countryCounts, 5, null, true);
$playersTotal = array_sum($countryCounts);
$topCountryMax = $topCountries ? max($topCountries) : 0;

$adminName = $_SESSION['admin_name'] ?? 'Admin';

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
            RECENT | THIS WEEK + MISSING DETAILS
        ========================== -->

        <div class="bottom-grid">


            <!-- Tournament registrations: newest tournaments created -->

            <section class="panel">

                <div class="panel-header">
                    <span class="stat-icon tone-blue"><i class="fa-solid fa-trophy"></i></span>
                    <h3>Tournament registrations</h3>
                    <a class="panel-link" href="admin_index.php">All tournaments</a>
                </div>

                <?php if ($newTournaments): ?>

                    <div class="table-wrap">

                        <table class="recent-table">

                            <thead>
                                <tr>
                                    <th>Tournament</th>
                                    <th>Created by</th>
                                    <th>Dates</th>
                                    <th>T_Software fee</th>
                                    <th class="center">Players</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($newTournaments as $row): ?>

                                    <tr onclick="location.href='admin_each_tournament.php?id=<?= (int)$row['tournamentID'] ?>'">

                                        <td class="tournament-cell" title="<?= htmlspecialchars($row['tournament_name']) ?>">
                                            <span class="player-cell"><?= htmlspecialchars($row['tournament_name']) ?></span>
                                            <small class="cell-sub">
                                                <?= htmlspecialchars(strtoupper($row['tournament_type'])) ?>
                                                <?= trim((string)$row['tournament_country']) !== '' ? '&middot; ' . htmlspecialchars(trim($row['tournament_country'])) : '' ?>
                                            </small>
                                        </td>

                                        <td class="creator-cell">
                                            <?= htmlspecialchars($row['creator_name'] ?? $row['creatorID']) ?>
                                            <small class="cell-sub">
                                                <?= $row['creator_role'] === 'organizer' ? 'Tournament Organizer' : ($row['creator_role'] === 'platform' ? 'Platform Admin' : htmlspecialchars($row['creatorID'])) ?>
                                            </small>
                                        </td>

                                        <td><?= shortDate($row['tournament_startdate']) ?></td>

                                        <td>
                                            <?php if ($row['fee_status'] === 'paid'): ?>
                                                <span class="status-pill good">PAID</span>
                                            <?php elseif ($row['fee_status'] === 'unpaid'): ?>
                                                <span class="status-pill wait">UNPAID</span>
                                            <?php else: ?>
                                                <span class="status-pill">&ndash;</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="center"><?= (int)$row['players'] ?></td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <p class="all-clear">No tournaments yet.</p>

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


                <!-- Players by country (top 5) -->

                <section class="panel">

                    <div class="panel-header">
                        <span class="stat-icon tone-blue"><i class="fa-solid fa-earth-asia"></i></span>
                        <h3>Players by country</h3>
                        <span class="count-badge"><?= $playersTotal ?></span>
                    </div>

                    <?php if ($topCountries): ?>

                        <ol class="country-chart" aria-label="Top 5 countries by number of players">

                            <?php foreach ($topCountries as $country => $total): ?>

                                <?php
                                $share = $playersTotal > 0 ? round($total / $playersTotal * 100) : 0;
                                $width = $topCountryMax > 0 ? max(2, round($total / $topCountryMax * 100)) : 0;
                                $tip = $country . ' · ' . $total . ' player' . ($total === 1 ? '' : 's') . ' · ' . $share . '% of all players';
                                ?>

                                <li class="country-row" tabindex="0" data-tip="<?= htmlspecialchars($tip) ?>">
                                    <span class="country-name"><?= htmlspecialchars($country) ?></span>
                                    <span class="country-track">
                                        <span class="country-bar" style="width: <?= $width ?>%"></span>
                                    </span>
                                    <span class="country-value"><?= $total ?></span>
                                </li>

                            <?php endforeach; ?>

                        </ol>

                        <?php if ($otherCountries): ?>
                            <p class="country-other">
                                + <?= array_sum($otherCountries) ?> player<?= array_sum($otherCountries) === 1 ? '' : 's' ?>
                                from <?= count($otherCountries) ?> other countr<?= count($otherCountries) === 1 ? 'y' : 'ies' ?>
                                (<?= htmlspecialchars(implode(', ', array_keys($otherCountries))) ?>)
                            </p>
                        <?php endif; ?>

                        <div class="chart-tooltip" id="countryTooltip" role="tooltip" hidden></div>

                    <?php else: ?>

                        <p class="all-clear">No players yet.</p>

                    <?php endif; ?>

                </section>

            </div>

        </div>

    </main>


    <script>
        // Players by country: tooltip follows the hovered (or focused) row
        (function () {

            const tooltip = document.getElementById('countryTooltip');

            if (!tooltip) {
                return;
            }

            function show(row, x, y) {
                tooltip.textContent = row.dataset.tip;
                tooltip.hidden = false;

                const box = row.closest('.panel').getBoundingClientRect();
                tooltip.style.left = Math.min(x - box.left + 12, box.width - tooltip.offsetWidth - 8) + 'px';
                tooltip.style.top = (y - box.top - tooltip.offsetHeight - 10) + 'px';
            }

            document.querySelectorAll('.country-row').forEach(function (row) {

                row.addEventListener('mousemove', function (event) {
                    show(row, event.clientX, event.clientY);
                });

                row.addEventListener('focus', function () {
                    const r = row.getBoundingClientRect();
                    show(row, r.left + r.width / 2, r.top);
                });

                row.addEventListener('mouseleave', function () { tooltip.hidden = true; });
                row.addEventListener('blur', function () { tooltip.hidden = true; });
            });

        })();
    </script>

</body>

</html>
