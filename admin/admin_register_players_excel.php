<?php

require_once '../db.php';
require_once 'admin_auth.php';
require_once '../platform_fee.php';
require_once 'admin_registration_helpers.php';


/*
|--------------------------------------------------------------------------
| Register many players from an Excel file
|--------------------------------------------------------------------------
| 1. Upload  - the file is read by ../rankings/read_registration_sheet.py
|              (every sheet; a file with one sheet per category - BU19,
|              BU15, GU13 ... - uses the sheet name as the category)
| 2. Preview - each row is either a NEW player (an account is created and
|              they are registered; password = their new player ID) or
|              SKIPPED: the player already has an account (matched by Player
|              ID, email, or name + date of birth), or the row has no name /
|              no category. No age or eligibility checks. Gender comes from
|              the category (BU / Men = male, GU / Women = female).
|              Nothing is saved yet.
| 3. Confirm - the new players are created and registered.
*/

$tournamentID = isset($_GET['id']) ? intval($_GET['id']) : 0;

requireTournamentAccess($tournamentID);

$stmt = $conn->prepare("SELECT * FROM tournament WHERE tournamentID = ?");
$stmt->bind_param("i", $tournamentID);
$stmt->execute();
$tournament = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tournament) {
    die("Tournament not found.");
}

$categories = getCategories($conn, $tournamentID);
$subscriptionUnpaid = platformFeeUnpaid($conn, $tournamentID);

$pythonPath = 'C:\\Users\\user\\.pyenv\\pyenv-win\\versions\\3.13.5\\python.exe';
$readScript = realpath(__DIR__ . '/../rankings/read_registration_sheet.py');

$pageUrl = 'admin_register_players_excel.php?id=' . $tournamentID;


/*
|--------------------------------------------------------------------------
| Reading the file
|--------------------------------------------------------------------------
*/

function readRegistrationSheet($pythonPath, $script, $filePath)
{
    $process = proc_open(
        [$pythonPath, $script, $filePath],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );

    if (!is_resource($process)) {
        return ['error' => 'The file reader could not be started.'];
    }

    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    $data = json_decode($output, true);

    if (!is_array($data)) {
        return ['error' => 'The file could not be read.' . ($errors !== '' ? ' (' . trim(strtok($errors, "\n")) . ')' : '')];
    }

    return $data;
}


/*
|--------------------------------------------------------------------------
| Sorting the rows
|--------------------------------------------------------------------------
| Returns one entry per row:
|   action   'new'      - new player: created and registered
|            'existing' - already has an account: skipped
|            'error'    - can't be added (no name / no category): skipped
|   name, playerID (existing), data (new player), category,
|   payment, amount, endorsement, message
*/

function yesValue($value, array $yes)
{
    return in_array(strtolower(trim((string) $value)), $yes, true);
}

function analyseRows($conn, array $rows, $categories)
{
    // All players once: by ID, email, name + date of birth, and name
    $byID = $byEmail = $byNameDob = $byName = [];

    $result = $conn->query("
        SELECT playerID, player_full_name, player_first_name, player_last_name, player_email, player_dob
        FROM players
    ");

    while ($player = $result->fetch_assoc()) {

        $byID[strtoupper($player['playerID'])] = $player;

        if (trim((string) $player['player_email']) !== '') {
            $byEmail[strtolower(trim($player['player_email']))] = $player;
        }

        foreach ([$player['player_first_name'] . ' ' . $player['player_last_name'], $player['player_full_name']] as $name) {
            $name = strtolower(preg_replace('/\s+/', ' ', trim((string) $name)));
            $byNameDob[$name . '|' . $player['player_dob']] = $player;
            $byName[$name] = $player;
        }
    }

    $multipleSheets = count(array_unique(array_column($rows, 'sheet'))) > 1;
    $seen = [];      // players already in the file (email / name)
    $checked = [];

    foreach ($rows as $row) {

        $first = trim($row['first_name'] ?? '');
        $last = trim($row['last_name'] ?? '');

        // Only a "Name" column: first word(s) / last word
        if ($first === '' && $last === '' && trim($row['full_name'] ?? '') !== '') {
            $parts = preg_split('/\s+/', trim($row['full_name']));
            $last = count($parts) > 1 ? array_pop($parts) : '';
            $first = implode(' ', $parts);
        }

        $name = trim($first . ' ' . $last);
        $nameKey = strtolower(preg_replace('/\s+/', ' ', $name));
        $email = strtolower(trim($row['email'] ?? ''));
        $dob = $row['dob'] ?? '';
        $sheet = (string) ($row['sheet'] ?? '');

        $entry = [
            'row' => (int) $row['row'],
            'sheet' => $sheet,
            'where' => ($multipleSheets ? $sheet . ' row ' : 'row ') . (int) $row['row'],
            'name' => $name,
            'action' => 'error',
            'playerID' => '',
            'data' => null,
            'category' => '',
            'payment' => yesValue($row['payment'] ?? '', ['paid', 'yes', 'y', 'true', '1']) ? 'PAID' : 'NOT PAID',
            'amount' => trim($row['amount_paid'] ?? ''),
            'endorsement' => yesValue($row['endorsement'] ?? '', ['endorsed', 'yes', 'y', 'true', '1']) ? 'ENDORSED' : 'NOT ENDORSED',
            'message' => '',
        ];

        // ---------- Already has an account? -> skip ----------
        $existing = null;
        $playerIDText = strtoupper(trim($row['player_id'] ?? ''));

        if ($playerIDText !== '' && isset($byID[$playerIDText])) {
            $existing = $byID[$playerIDText];
        } elseif ($email !== '' && isset($byEmail[$email])) {
            $existing = $byEmail[$email];
        } elseif ($nameKey !== '' && $dob !== '' && isset($byNameDob[$nameKey . '|' . $dob])) {
            $existing = $byNameDob[$nameKey . '|' . $dob];
        } elseif ($nameKey !== '' && $dob === '' && isset($byName[$nameKey])) {
            $existing = $byName[$nameKey];
        }

        if ($existing) {
            $entry['action'] = 'existing';
            $entry['name'] = $existing['player_full_name'];
            $entry['playerID'] = $existing['playerID'];
            $entry['message'] = 'Already has an account (' . $existing['playerID'] . ').';
            $checked[] = $entry;
            continue;
        }

        // ---------- Category: the row's own column, else the sheet name ----------
        $categoryText = trim($row['category'] ?? '');
        $category = findCategory($categories, $categoryText !== '' ? $categoryText : $sheet);

        // ---------- New player ----------
        $seenKey = $email !== '' ? $email : $nameKey . '|' . $dob;

        if ($first === '') {
            $entry['message'] = 'No player name.';
        } elseif ($category === null) {
            $entry['message'] = $categoryText !== ''
                ? '"' . $categoryText . '" is not a category in this tournament.'
                : 'No category - name the sheet after the category (e.g. BU15) or fill in the Category column.';
        } elseif (isset($seen[$seenKey])) {
            $entry['message'] = 'Listed twice in the file (also ' . $seen[$seenKey] . ').';
        } else {
            $entry['action'] = 'new';
        }

        $seen[$seenKey] = $entry['where'];

        // Gender from the category (BU / Men = male, GU / Women = female), else the Gender column
        $gender = $category !== null ? categoryGender($category) : null;
        $gender = $gender ?? normalGender($row['gender'] ?? '');

        $entry['category'] = $category ?? '';
        $entry['data'] = [
            'first_name' => $first,
            'last_name' => $last,
            'gender' => (string) $gender,
            'dob' => $dob !== '' ? $dob : '0000-00-00',
            'nationality' => strtoupper(trim($row['nationality'] ?? '')),
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
            'contact' => trim($row['contact'] ?? ''),
            'national_ranking' => $row['national_ranking'] ?? '',
            'ajss_ranking' => $row['ajss_ranking'] ?? '',
            'world_ranking' => $row['world_ranking'] ?? '',
        ];

        $checked[] = $entry;
    }

    return $checked;
}


/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/

$error = '';
$preview = null;      // checked rows (after upload)
$previewToken = '';
$results = null;      // after confirming
$columnsFound = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $subscriptionUnpaid) {

    $error = 'Registration is closed until the T_Software subscription for this tournament is paid.';

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'preview') {

    $file = $_FILES['player_file'] ?? null;
    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));

    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please choose an Excel file to upload.';
    } elseif (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
        $error = 'Please upload an Excel file (.xlsx or .xls) or a CSV file.';
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $error = 'The file is too big (5 MB at most).';
    } elseif (!$categories) {
        $error = 'This tournament has no categories yet.';
    } elseif (!is_file($pythonPath) || !$readScript) {
        $error = 'The Excel reader is not set up (Python or rankings/read_registration_sheet.py not found).';
    } else {

        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'registration_' . bin2hex(random_bytes(8)) . '.' . $extension;
        move_uploaded_file($file['tmp_name'], $tempPath);

        $sheet = readRegistrationSheet($pythonPath, $readScript, $tempPath);

        @unlink($tempPath);

        if (!empty($sheet['error'])) {
            $error = $sheet['error'];
        } elseif (empty($sheet['rows'])) {
            $error = 'The file has no player rows under the heading row.';
        } else {

            $columnsFound = $sheet['columns'] ?? [];
            $preview = analyseRows($conn, $sheet['rows'], $categories);

            // Kept until the admin confirms; checked again then
            $previewToken = bin2hex(random_bytes(12));
            $_SESSION['excel_registration'] = [
                'token' => $previewToken,
                'tournamentID' => $tournamentID,
                'rows' => $sheet['rows'],
                'columns' => $columnsFound,
                'sheets' => $sheet['sheets'] ?? [],
                'file' => $file['name'],
            ];
        }
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm') {

    $saved = $_SESSION['excel_registration'] ?? null;

    if (!$saved || $saved['tournamentID'] !== $tournamentID || !hash_equals($saved['token'], (string) ($_POST['token'] ?? ''))) {

        $error = 'This upload has already been registered or has expired. Please upload the file again.';

    } else {

        unset($_SESSION['excel_registration']);

        // Sorted again: a player may have been added since the preview
        $rows = analyseRows($conn, $saved['rows'], $categories);
        $remark = 'Registered by ' . $_SESSION['userid'] . ' (Excel)';
        $results = ['added' => [], 'skipped' => []];

        foreach ($rows as $entry) {

            if ($entry['action'] !== 'new') {
                $results['skipped'][] = $entry;
                continue;
            }

            $conn->begin_transaction();

            try {

                $playerID = createPlayer($conn, $entry['data']);

                insertRegistration(
                    $conn, $tournamentID, $playerID, $entry['category'],
                    $entry['payment'], $entry['amount'], $entry['endorsement'], $remark
                );

                $conn->commit();

                $entry['playerID'] = $playerID;
                $results['added'][] = $entry;

            } catch (Exception $e) {

                $conn->rollback();
                $entry['action'] = 'error';
                $entry['message'] = 'Could not be saved: ' . $e->getMessage() . '.';
                $results['skipped'][] = $entry;
            }
        }
    }
}


function countAction($rows, $action)
{
    return count(array_filter($rows, function ($row) use ($action) {
        return $row['action'] === $action;
    }));
}

$fieldLabels = [
    'player_id' => 'Player ID', 'first_name' => 'First Name', 'last_name' => 'Last Name', 'full_name' => 'Full Name',
    'gender' => 'Gender', 'dob' => 'Date of Birth', 'nationality' => 'Nationality', 'email' => 'Email',
    'contact' => 'Contact Number', 'category' => 'Category', 'national_ranking' => 'National Ranking',
    'ajss_ranking' => 'AJSS Ranking', 'world_ranking' => 'World Ranking', 'payment' => 'Payment',
    'amount_paid' => 'Amount Paid', 'endorsement' => 'Endorsement',
];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Register from Excel - <?= htmlspecialchars($tournament['tournament_name']) ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

    <link href="./assets/style.css" rel="stylesheet" type="text/css">
    <link href="./assets/admins.css?v=<?= filemtime(__DIR__ . '/assets/admins.css') ?>" rel="stylesheet" type="text/css">
    <link href="./assets/register_player.css?v=<?= filemtime(__DIR__ . '/assets/register_player.css') ?>" rel="stylesheet" type="text/css">

</head>

<body>

    <?php require_once 'admin_navbar.php'; ?>


    <main class="admins-page">

        <a href="admin_register_player.php?id=<?= $tournamentID ?>" class="back-link">&larr; Back to Register a Player</a>

        <div class="page-title">
            <h1>Register Players from Excel</h1>
            <p><?= htmlspecialchars($tournament['tournament_name']) ?></p>
        </div>


        <?php if ($error !== ''): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>


        <?php if ($subscriptionUnpaid): ?>

            <!-- ==========================================
                 CLOSED
            =========================================== -->

            <section class="card form-page">
                <p class="muted-text closed-note">
                    <i class="fa-solid fa-lock"></i>
                    Registration for this tournament is closed until the T_Software subscription is paid.
                    <?php if (!isPlatformAdmin()): ?>
                        <a href="admin_platform_payment.php?id=<?= $tournamentID ?>">Pay now</a>
                    <?php endif; ?>
                </p>
            </section>

        <?php elseif ($results !== null): ?>

            <!-- ==========================================
                 DONE
            =========================================== -->

            <div class="success-message">
                <strong><?= count($results['added']) ?></strong>
                new player<?= count($results['added']) === 1 ? '' : 's' ?> added to this tournament
                <?php if ($results['added']): ?>
                    &ndash; each logs in with their Player ID as user ID and password
                <?php endif; ?>.
                <?php if ($results['skipped']): ?>
                    <?= count($results['skipped']) ?> row<?= count($results['skipped']) === 1 ? '' : 's' ?> skipped.
                <?php endif; ?>
                <a href="admin_each_tournament.php?id=<?= $tournamentID ?>">Back to the tournament</a>
            </div>

            <section class="card">

                <h2>Result</h2>

                <div class="table-wrap excel-table">
                    <table class="admins-table">
                        <thead>
                            <tr>
                                <th>Row</th>
                                <th>Player</th>
                                <th>Result</th>
                                <th>Category</th>
                                <th>Payment</th>
                                <th>Endorsement</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_merge($results['added'], $results['skipped']) as $entry): ?>
                                <tr>
                                    <td class="where-cell"><?= htmlspecialchars($entry['where']) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($entry['name'] ?: '-') ?></strong>
                                        <?php if ($entry['playerID'] !== ''): ?>
                                            <small><?= htmlspecialchars($entry['playerID']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($entry['action'] === 'new'): ?>
                                            <span class="row-pill new">Added</span>
                                        <?php else: ?>
                                            <span class="row-pill <?= $entry['action'] === 'existing' ? 'skipped' : 'error' ?>">Skipped</span>
                                            <small><?= htmlspecialchars($entry['message']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($entry['category'] ?: '-') ?></td>
                                    <td><?= $entry['action'] === 'new' ? ($entry['payment'] === 'PAID' ? 'Paid' : 'Not paid') : '-' ?></td>
                                    <td><?= $entry['action'] === 'new' ? ($entry['endorsement'] === 'ENDORSED' ? 'Endorsed' : 'Not endorsed') : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p class="excel-actions">
                    <a class="small-btn" href="<?= $pageUrl ?>">Upload another file</a>
                </p>

            </section>

        <?php elseif ($preview !== null): ?>

            <!-- ==========================================
                 PREVIEW
            =========================================== -->

            <?php
            $newCount = countAction($preview, 'new');
            $existingCount = countAction($preview, 'existing');
            $errorCount = countAction($preview, 'error');
            ?>

            <section class="card">

                <h2>Before adding <span class="count"><?= count($preview) ?> rows</span></h2>

                <p class="muted-text">
                    File: <strong><?= htmlspecialchars($_SESSION['excel_registration']['file'] ?? '') ?></strong>.
                    <?php if (count($_SESSION['excel_registration']['sheets'] ?? []) > 1): ?>
                        Sheets read: <?= htmlspecialchars(implode(', ', $_SESSION['excel_registration']['sheets'])) ?>.
                    <?php endif; ?>
                    New players are added to the tournament; players who already have an account are skipped.
                    Nothing is saved until you press <strong>Add</strong>.
                </p>

                <div class="excel-summary">
                    <span class="row-pill new"><?= $newCount ?> new player<?= $newCount === 1 ? '' : 's' ?> to add</span>
                    <span class="row-pill skipped"><?= $existingCount ?> existing player<?= $existingCount === 1 ? '' : 's' ?> skipped</span>
                    <?php if ($errorCount > 0): ?>
                        <span class="row-pill error"><?= $errorCount ?> row<?= $errorCount === 1 ? '' : 's' ?> can't be added</span>
                    <?php endif; ?>
                </div>

                <div class="table-wrap excel-table">
                    <table class="admins-table">
                        <thead>
                            <tr>
                                <th>Row</th>
                                <th>Player</th>
                                <th>Will be</th>
                                <th>Category</th>
                                <th>Payment</th>
                                <th>Endorsement</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview as $entry): ?>
                                <tr class="<?= $entry['action'] === 'new' ? '' : 'row-skipped' ?>">
                                    <td class="where-cell"><?= htmlspecialchars($entry['where']) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($entry['name'] ?: '-') ?></strong>
                                        <?php if ($entry['playerID'] !== ''): ?>
                                            <small><?= htmlspecialchars($entry['playerID']) ?></small>
                                        <?php elseif ($entry['data']): ?>
                                            <small>
                                                <?= htmlspecialchars(ucfirst($entry['data']['gender']) ?: '-') ?>
                                                &middot; <?= $entry['data']['dob'] !== '0000-00-00' ? date('d M Y', strtotime($entry['data']['dob'])) : 'no date of birth' ?>
                                                <?= $entry['data']['nationality'] !== '' ? '&middot; ' . htmlspecialchars($entry['data']['nationality']) : '' ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($entry['action'] === 'new'): ?>
                                            <span class="row-pill new">Added (new player)</span>
                                        <?php else: ?>
                                            <span class="row-pill <?= $entry['action'] === 'existing' ? 'skipped' : 'error' ?>">Skipped</span>
                                            <small><?= htmlspecialchars($entry['message']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($entry['category'] ?: '-') ?></td>
                                    <td><?= $entry['action'] === 'new' ? ($entry['payment'] === 'PAID' ? 'Paid' . ($entry['amount'] !== '' ? ' <small>' . htmlspecialchars($entry['amount']) . '</small>' : '') : 'Not paid') : '-' ?></td>
                                    <td><?= $entry['action'] === 'new' ? ($entry['endorsement'] === 'ENDORSED' ? 'Endorsed' : 'Not endorsed') : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="excel-actions">

                    <?php if ($newCount > 0): ?>
                        <form method="POST" action="<?= $pageUrl ?>"
                              onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Adding...';">
                            <input type="hidden" name="action" value="confirm">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($previewToken) ?>">
                            <button type="submit" class="add-btn">
                                Add <?= $newCount ?> new player<?= $newCount === 1 ? '' : 's' ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <p class="muted-text no-new">No new players in this file &ndash; everyone already has an account.</p>
                    <?php endif; ?>

                    <a class="small-btn" href="<?= $pageUrl ?>">Upload a different file</a>

                </div>

            </section>

        <?php else: ?>

            <!-- ==========================================
                 UPLOAD
            =========================================== -->

            <section class="card form-page excel-upload">

                <h2><span class="step">1</span> Download the template</h2>

                <p class="muted-text">
                    One player per row, one sheet per category (sheets named <strong>BU19, BU15, GU13 ...</strong>),
                    or a <strong>Category</strong> column. New players are added to this tournament &ndash; an account
                    is created for each, and their first password is their new Player ID. Players who
                    <strong>already have an account are skipped</strong>. The gender comes from the category
                    (BU = boys, GU = girls).
                </p>

                <a class="small-btn" href="assets/templates/player_registration_template.xlsx" download>
                    <i class="fa-solid fa-download"></i>
                    Download Excel template
                </a>

                <h2 class="second-step"><span class="step">2</span> Upload the filled-in file</h2>

                <form method="POST" action="<?= $pageUrl ?>" enctype="multipart/form-data" class="excel-form">
                    <input type="hidden" name="action" value="preview">
                    <input type="file" name="player_file" accept=".xlsx,.xls,.csv" required>
                    <button type="submit" class="add-btn">Check the file</button>
                </form>

                <p class="field-hint">Excel (.xlsx / .xls) or CSV, up to 5 MB. You'll see which players will be added before anything is saved.</p>

            </section>

        <?php endif; ?>

    </main>

</body>

</html>
