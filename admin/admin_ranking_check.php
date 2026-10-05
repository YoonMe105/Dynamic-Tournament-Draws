<?php

require_once '../db.php';
require_once 'admin_auth.php';

// Platform Admins, or the admin who created this tournament
requireTournamentAccess($_GET['id'] ?? 0);


/*
|--------------------------------------------------------------------------
| Check Rankings (per tournament)
|--------------------------------------------------------------------------
| The admin uploads an official ranking file - the Asian Junior Ranking
| category PDF, the SRAM National Junior Ranking PDF, or the same list as
| an Excel (.xlsx / .xls) or CSV file. The rankings the players entered are
| compared with it and shown as a report.
|
| The comparison uses tournament_rankings - the rankings saved when each
| player registered, which is what seeding uses. The admin can fix wrong
| ones from the report; that only updates tournament_rankings for this
| tournament. The players table is never changed here.
| The file is read by ../rankings/check_rankings.py.
*/

// Python with the readers installed (pip install pdfplumber openpyxl xlrd)
$pythonPath = 'C:\\Users\\user\\.pyenv\\pyenv-win\\versions\\3.13.5\\python.exe';
$checkScript = realpath(__DIR__ . '/../rankings/check_rankings.py');


/*
|--------------------------------------------------------------------------
| Tournament
|--------------------------------------------------------------------------
*/

$tournamentID = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $conn->prepare("SELECT tournamentID, tournament_name FROM tournament WHERE tournamentID = ?");
$stmt->bind_param("i", $tournamentID);
$stmt->execute();

$tournament = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$tournament) {
    die("Tournament not found.");
}


/*
|--------------------------------------------------------------------------
| Registered players
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        tr.registrationID,
        tr.category_registered,
        tr.endorsement,
        p.playerID,
        p.player_full_name,
        p.player_dob,
        p.player_gender,
        p.player_nationality,
        p.asf_member_no,
        r.rankingID,
        r.national_ranking,
        r.ajss_ranking
    FROM tournament_register tr
    JOIN players p ON p.playerID = tr.playerID
    LEFT JOIN tournament_rankings r ON r.registrationID = tr.registrationID
    WHERE tr.tournamentID = ?
    ORDER BY tr.category_registered ASC, p.player_full_name ASC
");

$stmt->bind_param("i", $tournamentID);
$stmt->execute();

$registrations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();


/*
|--------------------------------------------------------------------------
| Run the check
|--------------------------------------------------------------------------
*/

$error = '';
$report = null;
$fileName = '';

/*
| Which saved ranking a column name means:
|   "AJSS Ranking", "Asian Ranking"        -> AJSS ranking
|   "World Ranking", "PSA Ranking"         -> World ranking
|   "National Ranking", "SRAM Ranking"     -> National ranking
|   no hint ("Rank", "Position") or empty  -> decided from the file's title
|                                             (National if the title doesn't say)
*/
function rankingTypeFromName($name)
{
    $name = strtoupper($name);

    if (strpos($name, 'AJSS') !== false || strpos($name, 'ASIA') !== false) {
        return 'asian';
    }

    if (strpos($name, 'WORLD') !== false || strpos($name, 'PSA') !== false) {
        return 'world';
    }

    if (strpos($name, 'SRAM') !== false) {
        return 'sram';
    }

    return strpos($name, 'NATIONAL') !== false ? 'national' : 'auto';
}


function runRankingCheck($pythonPath, $script, $filePath, $playersPath, $rankingType, $rankColumn)
{
    $process = proc_open(
        [$pythonPath, $script, $filePath, '--players-file', $playersPath, '--type', $rankingType, '--column', $rankColumn],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );

    if (!is_resource($process)) {
        return ['ok' => false, 'error' => 'Python could not be started.'];
    }

    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    $result = json_decode($output, true);

    if (!is_array($result)) {
        return ['ok' => false, 'error' => 'The ranking checker failed. ' . trim($errors ?: $output)];
    }

    return $result;
}


/*
|--------------------------------------------------------------------------
| Fix saved rankings (tournament_rankings only)
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'fix_rankings') {

    $field = $_POST['ranking_field'] ?? '';
    $ticked = array_keys(array_filter((array)($_POST['fix'] ?? [])));
    $values = (array)($_POST['value'] ?? []);

    // Only registrations of this tournament that have a saved ranking row
    $tournamentRegistrations = [];

    foreach ($registrations as $registration) {
        if ($registration['rankingID'] !== null) {
            $tournamentRegistrations[(int)$registration['registrationID']] = $registration['player_full_name'];
        }
    }

    $updates = [];

    if (!in_array($field, ['national_ranking', 'ajss_ranking', 'world_ranking'], true)) {

        $error = 'Unknown ranking type.';

    } elseif (!$ticked) {

        $error = 'Tick at least one player to fix.';

    } else {

        foreach ($ticked as $registrationID) {

            $registrationID = (int)$registrationID;
            $value = trim((string)($values[$registrationID] ?? ''));

            if (!isset($tournamentRegistrations[$registrationID])) {
                continue;
            }

            if ($value !== '' && !preg_match('/^[1-9][0-9]{0,5}$/', $value)) {
                $error = $tournamentRegistrations[$registrationID] . ': the ranking must be a whole number (or empty to clear it).';
                break;
            }

            $updates[$registrationID] = $value === '' ? null : (int)$value;
        }
    }

    if ($error === '' && $updates) {

        // $field is one of the two allowed column names above
        $stmt = $conn->prepare("
            UPDATE tournament_rankings
            SET $field = ?
            WHERE registrationID = ?
            AND tournamentID = ?
        ");

        foreach ($updates as $registrationID => $value) {
            $stmt->bind_param("iii", $value, $registrationID, $tournamentID);
            $stmt->execute();
        }

        $stmt->close();

        header("Location: admin_ranking_check.php?id=" . $tournamentID
            . "&fixed=" . count($updates) . "&field=" . urlencode($field));
        exit;
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    set_time_limit(180);

    $upload = $_FILES['ranking_file'] ?? null;
    $extension = strtolower(pathinfo($upload['name'] ?? '', PATHINFO_EXTENSION));
    $rankColumn = trim($_POST['rank_column'] ?? '');
    $rankingType = rankingTypeFromName($rankColumn);

    if (!$upload || $upload['error'] === UPLOAD_ERR_NO_FILE) {

        $error = 'Please choose a ranking file.';

    } elseif ($upload['error'] !== UPLOAD_ERR_OK) {

        $error = 'The file could not be uploaded (error ' . (int)$upload['error'] . ').';

    } elseif (!in_array($extension, ['pdf', 'xlsx', 'xls', 'csv'], true)) {

        $error = 'Please upload a PDF, Excel (.xlsx / .xls) or CSV file.';

    } elseif ($extension !== 'pdf' && $rankColumn === '') {

        $error = 'Type the name of the column to check (e.g. National Ranking) - it must match the column heading in the file.';

    } elseif (!is_file($pythonPath) || !$checkScript) {

        $error = 'The ranking checker is not set up (Python or rankings/check_rankings.py not found).';

    } elseif (!$registrations) {

        $error = 'No players are registered in this tournament yet.';

    } else {

        $fileName = $upload['name'];

        // Temporary copies only - both are deleted straight after the check
        $filePath = tempnam(sys_get_temp_dir(), 'rank') . '.' . $extension;
        $playersPath = tempnam(sys_get_temp_dir(), 'plyr') . '.json';

        move_uploaded_file($upload['tmp_name'], $filePath);

        $players = array_map(function ($row) {
            return [
                'playerID' => $row['playerID'],
                'name' => $row['player_full_name'],
                'dob' => $row['player_dob'],
                'gender' => $row['player_gender'],
                'asfMemberNo' => trim((string)$row['asf_member_no']),
                'category' => $row['category_registered'],
            ];
        }, $registrations);

        file_put_contents($playersPath, json_encode($players));

        // PDFs have a fixed layout - no column to choose
        $result = runRankingCheck($pythonPath, $checkScript, $filePath, $playersPath, $rankingType,
            $extension === 'pdf' ? '' : $rankColumn);

        @unlink($filePath);
        @unlink($playersPath);
        @unlink(substr($filePath, 0, -(strlen($extension) + 1)));
        @unlink(substr($playersPath, 0, -5));

        if (empty($result['ok'])) {
            $error = $result['error'] ?? 'The file could not be checked.';
        } else {
            $report = $result;
        }
    }
}


/*
|--------------------------------------------------------------------------
| Compare each player with the ranking file
|--------------------------------------------------------------------------
*/

$rows = [];
$skipped = 0;
$counts = ['correct' => 0, 'wrong' => 0, 'missing' => 0, 'not_found' => 0, 'not_ranked' => 0];

if ($report) {

    $field = $report['rankingField'];

    foreach ($registrations as $registration) {

        $playerID = $registration['playerID'];

        // Asian list for one category: only that category's players are compared
        if ($report['type'] === 'asian' && $report['category'] && $registration['category_registered'] !== $report['category']) {
            $skipped++;
            continue;
        }

        if (!array_key_exists($playerID, $report['matches'])) {
            $skipped++;   // other gender
            continue;
        }

        $match = $report['matches'][$playerID];
        $entered = trim((string)$registration[$field]);

        if ($match === null) {
            $status = $entered === '' ? 'not_ranked' : 'not_found';
        } elseif ($entered === '') {
            $status = 'missing';
        } elseif ((int)$entered === (int)$match['rank'] && ctype_digit($entered)) {
            $status = 'correct';
        } else {
            $status = 'wrong';
        }

        $counts[$status]++;

        $rows[] = $registration + [
            'entered' => $entered,
            'match' => $match,
            'status' => $status,
        ];
    }

    // Only wrong rankings are listed; correct / unranked players are just counted
    $rows = array_values(array_filter($rows, function ($row) {
        return in_array($row['status'], ['wrong', 'missing', 'not_found'], true);
    }));

    $order = ['wrong' => 0, 'not_found' => 1, 'missing' => 2];

    usort($rows, function ($a, $b) use ($order) {
        return [$order[$a['status']], $a['player_full_name']] <=> [$order[$b['status']], $b['player_full_name']];
    });
}

$statusText = [
    'correct' => 'Correct',
    'wrong' => 'Different',
    'missing' => 'Not saved',
    'not_found' => 'Not in this ranking',
    'not_ranked' => 'Not ranked',
];

$rankingLabels = [
    'national_ranking' => 'National ranking',
    'ajss_ranking' => 'AJSS ranking',
    'world_ranking' => 'World ranking',
];

$rankingLabel = $report ? $rankingLabels[$report['rankingField']] : '';
$problemCount = $report ? $counts['wrong'] + $counts['missing'] + $counts['not_found'] : 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Check Rankings - <?= htmlspecialchars($tournament['tournament_name']) ?></title>

    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/admin_seeding.css">
    <link rel="stylesheet" href="assets/ranking_check.css?v=<?= filemtime(__DIR__ . '/assets/ranking_check.css') ?>">

</head>

<body>

    <?php require 'admin_navbar.php'; ?>


    <main class="container">


        <!-- ==========================================================
            HEADER
        =========================================================== -->

        <div class="page-header">

            <div>
                <h1>Check Rankings</h1>
                <p><?= htmlspecialchars($tournament['tournament_name']) ?> &middot; <?= count($registrations) ?> registered players</p>
            </div>

            <a href="admin_seeding.php?id=<?= $tournamentID ?>" class="back-btn">Back to Seeding</a>

        </div>


        <!-- ==========================================================
            UPLOAD
        =========================================================== -->

        <section class="upload-box no-print">

            <form method="POST" enctype="multipart/form-data" id="checkForm">

                <div class="upload-text">
                    <strong>Upload the official ranking file</strong>
                    <span>
                        PDF, Excel (.xlsx / .xls) or CSV. An Asian Junior Ranking list (e.g. BU15) checks the
                        players' <b>AJSS ranking</b>; the SRAM National Junior Ranking checks their <b>national ranking</b>.
                        Excel/CSV files need a header row with at least <b>Rank</b> and <b>Name</b> columns
                        (an <b>AJSS Member ID</b> column and Date of Birth make the matching more reliable).
                        Players are matched by their <b>AJSS membership ID</b> first (the ASF Membership No on their profile),
                        then by name and date of birth.
                        For Excel/CSV, type the <b>column to check</b> exactly as its heading in the file
                        (e.g. <i>National Ranking</i>, <i>AJSS Ranking</i>, <i>Rank</i>). A column named AJSS/Asian is compared
                        with the saved AJSS ranking, World/PSA with the World ranking, otherwise the National ranking.
                        PDFs are read automatically.
                        The report compares the rankings saved for this tournament (the ones seeding uses).
                        Nothing changes until you fix a ranking in the report &ndash; and that only updates this
                        tournament's saved rankings, never the player's profile.
                    </span>
                </div>

                <div class="upload-controls">
                    <input type="file" name="ranking_file" accept=".pdf,.xlsx,.xls,.csv" required>

                    <input type="text" name="rank_column" id="rankColumn" class="type-input" maxlength="100"
                           value="<?= htmlspecialchars($_POST['rank_column'] ?? '') ?>"
                           placeholder="Column to check, e.g. National Ranking"
                           title="Heading of the ranking column in the Excel/CSV file (not needed for PDFs)">
                    <button type="submit" class="btn" id="checkBtn">Check Rankings</button>
                </div>

                <!-- Live hint: which saved ranking the typed column will be compared with -->
                <p class="compare-hint" id="compareHint"></p>

            </form>

        </section>


        <?php if ($error !== ''): ?>

            <div class="error-message"><?= htmlspecialchars($error) ?></div>

        <?php endif; ?>


        <?php if (isset($_GET['fixed'])): ?>

            <div class="success-message">
                <?= (int)$_GET['fixed'] ?> saved
                <?= ['national_ranking' => 'national', 'ajss_ranking' => 'AJSS', 'world_ranking' => 'world'][$_GET['field'] ?? ''] ?? '' ?>
                ranking<?= (int)$_GET['fixed'] === 1 ? '' : 's' ?> updated for this tournament.
                <a href="admin_seeding.php?id=<?= $tournamentID ?>">Go to Seeding</a> and run
                <strong>Automatic Seeding</strong> again to use them.
            </div>

        <?php endif; ?>


        <?php if ($report): ?>


            <!-- ==========================================================
                SUMMARY
            =========================================================== -->

            <section class="report-head">

                <div>
                    <h2>
                        <?= htmlspecialchars($report['title']) ?>
                        <?= $report['category'] ? htmlspecialchars($report['category']) : '' ?>
                        <?= $report['period'] ? '&ndash; ' . htmlspecialchars($report['period']) : '' ?>
                    </h2>
                    <p>
                        File: <?= htmlspecialchars($fileName) ?> &middot;
                        <?= (int)$report['rowsInFile'] ?> players in the file &middot;
                        <?php if (!empty($rankColumn) && $report['source'] === 'spreadsheet'): ?>
                            column checked: <strong>&ldquo;<?= htmlspecialchars($rankColumn) ?>&rdquo;</strong> &middot;
                        <?php endif; ?>
                        compared with the saved <strong><?= $rankingLabel ?></strong> (used for seeding) &middot;
                        checked <?= date('d M Y, h:i A') ?>
                    </p>
                </div>

                <div class="report-actions no-print">
                    <button type="button" class="btn" onclick="window.print()">Print</button>
                </div>

            </section>


            <div class="summary-grid">
                <div class="summary-card bad"><span><?= $counts['wrong'] ?></span>Different</div>
                <div class="summary-card warn"><span><?= $counts['missing'] ?></span>Ranked but not saved</div>
                <div class="summary-card warn"><span><?= $counts['not_found'] ?></span>Saved but not in the file</div>
            </div>

            <p class="report-note">
                Showing only the wrong rankings.
                <?= $counts['correct'] ?> player<?= $counts['correct'] === 1 ? ' is' : 's are' ?> correct
                and <?= $counts['not_ranked'] ?> <?= $counts['not_ranked'] === 1 ? 'is' : 'are' ?> not ranked in either &ndash; not listed.
            </p>

            <?php if ($skipped > 0): ?>

                <p class="report-note">
                    <?= $skipped ?> player<?= $skipped === 1 ? '' : 's' ?> not compared
                    <?php if ($report['type'] === 'asian'): ?>
                        &ndash; this list only covers <?= htmlspecialchars($report['category']) ?>. Upload their own category's list to check them.
                    <?php endif; ?>
                </p>

            <?php endif; ?>


            <!-- ==========================================================
                RESULTS
            =========================================================== -->

            <?php if ($rows): ?>

                <form method="POST" action="admin_ranking_check.php?id=<?= $tournamentID ?>" id="fixForm"
                      onsubmit="return confirmFix();">

                <input type="hidden" name="action" value="fix_rankings">
                <input type="hidden" name="ranking_field" value="<?= htmlspecialchars($report['rankingField']) ?>">

                <div class="table-wrapper">

                    <table class="check-table">

                        <thead>
                            <tr>
                                <th>Player</th>
                                <th>Category</th>
                                <th class="center">Saved Ranking</th>
                                <th class="center">Ranking in File</th>
                                <th>Found in file as</th>
                                <th>Result</th>
                                <th class="no-print">Fix saved ranking</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($rows as $row): ?>

                                <?php $isProblem = in_array($row['status'], ['wrong', 'missing', 'not_found'], true); ?>

                                <tr class="<?= $isProblem ? 'problem' : '' ?>">

                                    <td>
                                        <a class="player-link" href="admin_player_tournament_registration.php?id=<?= (int)$row['registrationID'] ?>">
                                            <?= htmlspecialchars($row['player_full_name']) ?>
                                        </a>
                                        <small>
                                            <?= htmlspecialchars($row['playerID']) ?>
                                            &middot; <?= htmlspecialchars($row['player_nationality']) ?>
                                            &middot; <?= trim((string)$row['asf_member_no']) !== ''
                                                ? 'AJSS ID ' . htmlspecialchars($row['asf_member_no'])
                                                : '<span class="muted-warn">no AJSS membership ID</span>' ?>
                                        </small>
                                    </td>

                                    <td><?= htmlspecialchars($row['category_registered']) ?></td>

                                    <td class="center rank"><?= $row['entered'] !== '' ? htmlspecialchars($row['entered']) : '&ndash;' ?></td>

                                    <td class="center rank official"><?= $row['match'] ? (int)$row['match']['rank'] : '&ndash;' ?></td>

                                    <td>
                                        <?php if ($row['match']): ?>
                                            <?= htmlspecialchars($row['match']['name']) ?>
                                            <small>
                                                <?= htmlspecialchars($row['match']['category'] ?? '') ?>
                                                <?= $row['match']['where'] ? '&middot; ' . htmlspecialchars($row['match']['where']) : '' ?>
                                                <?php if ($row['match']['category'] && $row['match']['category'] !== $row['category_registered']): ?>
                                                    &middot; <span class="muted-warn">ranked in <?= htmlspecialchars($row['match']['category']) ?></span>
                                                <?php endif; ?>
                                            </small>
                                            <?php if ($row['match']['match'] === 'id'): ?>
                                                <span class="match-note match-id">Matched by AJSS membership ID</span>
                                            <?php else: ?>
                                                <span class="match-note">No AJSS ID match &ndash; matched by name &amp; date of birth, please confirm</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="muted">Not found</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="result result-<?= $row['status'] ?>"><?= $statusText[$row['status']] ?></span>
                                    </td>

                                    <td class="no-print fix-cell">

                                        <?php if ($isProblem && $row['rankingID'] !== null): ?>

                                            <?php
                                            $regID = (int)$row['registrationID'];
                                            // Pre-tick sure fixes; name + DOB matches need the admin to confirm
                                            $preTick = $row['match'] && $row['match']['match'] === 'id';
                                            ?>

                                            <label class="fix-control">
                                                <input type="checkbox" name="fix[<?= $regID ?>]" value="1" <?= $preTick ? 'checked' : '' ?>>
                                                <input type="text" name="value[<?= $regID ?>]" class="fix-input"
                                                       inputmode="numeric" pattern="[1-9][0-9]{0,5}" maxlength="6"
                                                       value="<?= $row['match'] ? (int)$row['match']['rank'] : '' ?>"
                                                       placeholder="empty"
                                                       title="New saved ranking (empty = clear it)">
                                            </label>

                                        <?php else: ?>

                                            <span class="muted">&ndash;</span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <?php if ($problemCount > 0): ?>

                    <div class="fix-bar no-print">
                        <span>
                            Tick the players to fix and check the new value (filled in from the file; empty clears it).
                            Only <strong>this tournament's saved rankings</strong> change &ndash; player profiles stay as they are.
                        </span>
                        <button type="submit" class="btn">Save selected rankings</button>
                    </div>

                <?php endif; ?>

                </form>

            <?php else: ?>

                <div class="success-message">
                    <?php if ($counts['correct'] + $counts['not_ranked'] > 0): ?>
                        No wrong rankings &ndash; every saved <?= strtolower($rankingLabel) ?> matches this file.
                    <?php else: ?>
                        None of this tournament's players can be compared with this file.
                    <?php endif; ?>
                </div>

            <?php endif; ?>

            <p class="report-note">
                This report is not saved. Fixes change the rankings saved for this tournament only (the ones seeding uses) &ndash;
                run Automatic Seeding again afterwards.
            </p>

        <?php endif; ?>

    </main>


    <script>
        /*
        | Shows which saved ranking the column will be compared with.
        | Same rule as rankingTypeFromName() in PHP.
        */
        (function () {

            const fileInput = document.querySelector('input[name="ranking_file"]');
            const columnInput = document.getElementById('rankColumn');
            const hint = document.getElementById('compareHint');

            function update() {

                const file = fileInput.files[0] ? fileInput.files[0].name.toLowerCase() : '';
                const column = columnInput.value.trim().toUpperCase();

                if (file.endsWith('.pdf')) {
                    hint.innerHTML = 'PDF: the ranking is read automatically &ndash; an Asian Junior Ranking PDF is compared with the saved '
                        + '<b>AJSS ranking</b>, the SRAM PDF with the saved <b>National ranking</b>. No column needed.';
                    return;
                }

                if (column === '') {
                    hint.innerHTML = 'Type the ranking column\'s heading from the file. A column named <b>AJSS / Asian</b> is compared '
                        + 'with the saved AJSS ranking, <b>World / PSA</b> with the World ranking, <b>National / SRAM</b> with the National ranking.';
                    return;
                }

                let target = null;

                if (column.includes('AJSS') || column.includes('ASIA')) {
                    target = 'AJSS ranking';
                } else if (column.includes('WORLD') || column.includes('PSA')) {
                    target = 'World ranking';
                } else if (column.includes('NATIONAL') || column.includes('SRAM')) {
                    target = 'National ranking';
                }

                hint.innerHTML = target
                    ? '&#10003; Will be compared with the saved <b>' + target + '</b> of each player.'
                    : '&#9888; The name doesn\'t say which ranking it is &ndash; the file\'s title will decide '
                      + '(<b>AJSS</b> if it says Asian/AJSS, <b>World</b> if World/PSA, otherwise <b>National</b>). '
                      + 'To be sure, use the full heading, e.g. <i>National Ranking</i> or <i>AJSS Ranking</i>.';

                hint.classList.toggle('unclear', !target);
            }

            columnInput.addEventListener('input', update);
            fileInput.addEventListener('change', update);
            update();

        })();

        document.getElementById('checkForm').addEventListener('submit', function () {
            const button = document.getElementById('checkBtn');
            button.disabled = true;
            button.textContent = 'Checking... (this can take a few seconds)';
        });

        function confirmFix() {

            const ticked = document.querySelectorAll('#fixForm input[type="checkbox"]:checked').length;

            if (ticked === 0) {
                alert('Tick at least one player to fix.');
                return false;
            }

            return confirm('Update ' + ticked + ' saved ranking' + (ticked === 1 ? '' : 's') +
                ' for this tournament? Player profiles will not be changed.');
        }

    </script>

</body>

</html>
