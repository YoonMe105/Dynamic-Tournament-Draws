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
| 2. Preview - each row is:
|                NEW player   - an account is created (password = their new
|                               player ID) and they are registered
|                EXISTING     - already has an account (matched by Player ID,
|                               email, or name + date of birth): registered
|                SKIPPED      - already registered in this tournament, listed
|                               twice, or no name / no category
|              No age or eligibility checks. A new player's gender comes from
|              the category (BU / Men = male, GU / Women = female).
|              Nothing is saved yet.
|              Rankings in the file that differ from the ones saved for this
|              tournament replace them (tournament_rankings - the ones
|              seeding uses; the player's profile is not changed), also for
|              players who are already registered.
|              For existing players, details in the file that differ from
|              their profile (name, gender, date of birth, nationality,
|              email, contact, rankings) are listed; the admin ticks which
|              profiles to overwrite - unticked ones are kept as they are.
| 3. Confirm - new players are created, everyone is registered, and the
|              ticked profiles are overwritten.
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
|   action   'new'        - new player: account created and registered
|            'existing'   - already has an account: registered
|            'update'     - already registered, rankings in the file differ:
|                           this tournament's saved rankings are updated
|            'registered' - already registered, nothing to change: skipped
|   changes  ['National ranking' => [old, new], ...] - rankings the file changes
|            (this tournament's saved rankings)
|   profileChanges ['Date of birth' => [old, new], ...] - existing player's
|            profile details that differ from the file
|   profileUpdate  [column => new value] - what overwriting would save
|   key      sheet|row - identifies the row for the overwrite tick box
|            'error'      - listed twice / no name / no category: skipped
|   name, playerID (existing), data (new player), category,
|   payment, amount, endorsement, message
*/

function yesValue($value, array $yes)
{
    return in_array(strtolower(trim((string) $value)), $yes, true);
}

const EXCEL_RANKINGS = [
    'national_ranking' => 'National ranking',
    'ajss_ranking' => 'AJSS ranking',
    'world_ranking' => 'World ranking',
];

// Rankings filled in on this row of the file: ['national_ranking' => 3, ...]
function fileRankings($row)
{
    $rankings = [];

    foreach (EXCEL_RANKINGS as $column => $label) {
        if (ctype_digit((string) ($row[$column] ?? ''))) {
            $rankings[$column] = (int) $row[$column];
        }
    }

    return $rankings;
}

// What the file would change: ['National ranking' => [old, new], ...]
function rankingChanges(array $fileRankings, array $current)
{
    $changes = [];

    foreach ($fileRankings as $column => $value) {

        $old = ctype_digit((string) ($current[$column] ?? '')) ? (int) $current[$column] : null;

        if ($old !== $value) {
            $changes[EXCEL_RANKINGS[$column]] = [$old, $value];
        }
    }

    return $changes;
}

function changesText(array $changes)
{
    return implode(', ', array_map(function ($label, $values) {
        return $label . ' ' . ($values[0] ?? '-') . ' → ' . $values[1];
    }, array_keys($changes), $changes));
}

// This tournament's saved rankings (used for seeding) - the player's profile is not touched
function saveTournamentRankings($conn, $registrationID, array $fileRankings)
{
    foreach ($fileRankings as $column => $value) {

        // $column is one of EXCEL_RANKINGS' keys, never user input
        $stmt = $conn->prepare("UPDATE tournament_rankings SET $column = ? WHERE registrationID = ?");
        $stmt->bind_param("ii", $value, $registrationID);
        $stmt->execute();
        $stmt->close();
    }
}

// A date of birth that can't be right: in the future, or younger than 3
function impossibleDob($dob)
{
    $dob = (string) $dob;

    return $dob !== '' && strpos($dob, '0000') !== 0 && $dob > date('Y-m-d', strtotime('-3 years'));
}

// Same text, ignoring case and extra spaces
function sameText($a, $b)
{
    $clean = function ($value) {
        return strtolower(preg_replace('/\s+/', ' ', trim((string) $value)));
    };

    return $clean($a) === $clean($b);
}

/*
| An existing player's profile details that differ from the file. Only
| details filled in on the file count - an empty cell never clears anything.
| Returns ['changes' => [label => [old, new]], 'update' => [column => value]].
*/
function profileDifferences($row, $player)
{
    $changes = [];
    $update = [];

    $add = function ($label, $old, $new, array $columns) use (&$changes, &$update) {
        $changes[$label] = [trim((string) $old) !== '' ? (string) $old : '-', (string) $new];
        $update = array_merge($update, $columns);
    };

    $first = trim($row['first_name'] ?? '');
    $last = trim($row['last_name'] ?? '');
    $full = trim($row['full_name'] ?? '');

    // Name: First / Last Name columns, or a single Name column
    if ($first !== '' || $last !== '') {

        $newFirst = $first !== '' ? $first : $player['player_first_name'];
        $newLast = $last !== '' ? $last : $player['player_last_name'];

        if (!sameText($newFirst, $player['player_first_name']) || !sameText($newLast, $player['player_last_name'])) {
            $add('Name', $player['player_full_name'], trim($newFirst . ' ' . $newLast), [
                'player_first_name' => $newFirst,
                'player_last_name' => $newLast,
                'player_full_name' => trim($newFirst . ' ' . $newLast),
            ]);
        }

    } elseif ($full !== '' && !sameText($full, $player['player_full_name'])) {

        $parts = preg_split('/\s+/', $full);
        $newLast = count($parts) > 1 ? array_pop($parts) : '';

        $add('Name', $player['player_full_name'], $full, [
            'player_full_name' => $full,
            'player_first_name' => implode(' ', $parts),
            'player_last_name' => $newLast,
        ]);
    }

    $gender = normalGender($row['gender'] ?? '');

    if ($gender !== null && $gender !== normalGender($player['player_gender'])) {
        $add('Gender', $player['player_gender'], ucfirst($gender), ['player_gender' => ucfirst($gender)]);
    }

    $dob = $row['dob'] ?? '';
    $oldDob = strpos((string) $player['player_dob'], '0000') === 0 ? '' : (string) $player['player_dob'];

    if ($dob !== '' && $dob !== $oldDob) {
        $add(impossibleDob($oldDob) ? 'Date of birth (looks wrong in the system)' : 'Date of birth',
            $oldDob !== '' ? date('d M Y', strtotime($oldDob)) : '', date('d M Y', strtotime($dob)), ['player_dob' => $dob]);
    }

    $nationality = strtoupper(trim($row['nationality'] ?? ''));

    if ($nationality !== '' && !sameText($nationality, $player['player_nationality'])) {
        $add('Nationality', $player['player_nationality'], $nationality, ['player_nationality' => $nationality]);
    }

    $email = strtolower(trim($row['email'] ?? ''));

    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && !sameText($email, $player['player_email'])) {
        $add('Email', $player['player_email'], $email, ['player_email' => $email]);
    }

    $contact = trim($row['contact'] ?? '');

    if ($contact !== '' && $contact !== trim((string) $player['player_contact'])) {
        $add('Contact number', $player['player_contact'], $contact, ['player_contact' => $contact]);
    }

    foreach (EXCEL_RANKINGS as $column => $label) {

        $value = trim((string) ($row[$column] ?? ''));

        if (ctype_digit($value) && $value !== trim((string) $player[$column])) {
            $add($label . ' (profile)', $player[$column], $value, [$column => $value]);
        }
    }

    return ['changes' => $changes, 'update' => $update];
}

/*
| Overwrites a player's profile with the file's details. An email that
| already belongs to another player is left out. Returns the labels saved.
*/
function overwriteProfile($conn, $playerID, array $update)
{
    $allowed = ['player_first_name', 'player_last_name', 'player_full_name', 'player_gender', 'player_dob',
                'player_nationality', 'player_email', 'player_contact', 'national_ranking', 'ajss_ranking', 'world_ranking'];

    if (isset($update['player_email'])) {
        $stmt = $conn->prepare("SELECT 1 FROM players WHERE player_email = ? AND playerID <> ? LIMIT 1");
        $stmt->bind_param("ss", $update['player_email'], $playerID);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            unset($update['player_email']);
        }

        $stmt->close();
    }

    $sets = [];
    $values = [];

    foreach ($update as $column => $value) {
        if (in_array($column, $allowed, true)) {
            $sets[] = "$column = ?";
            $values[] = (string) $value;
        }
    }

    if (!$sets) {
        return false;
    }

    $values[] = $playerID;

    $stmt = $conn->prepare("UPDATE players SET " . implode(', ', $sets) . " WHERE playerID = ?");
    $stmt->bind_param(str_repeat('s', count($values)), ...$values);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function analyseRows($conn, $tournamentID, array $rows, $categories)
{
    // All players once: by ID, email, name + date of birth, and name
    $byID = $byEmail = $byNameDob = $byName = $nameOwners = [];

    $result = $conn->query("
        SELECT playerID, player_full_name, player_first_name, player_last_name, player_email, player_dob,
               player_gender, player_nationality, player_contact,
               national_ranking, ajss_ranking, world_ranking
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
            $nameOwners[$name][$player['playerID']] = true;   // how many players have this name
        }
    }

    // Already registered: [playerID => registrationID + saved rankings]
    $registered = [];

    $stmt = $conn->prepare("
        SELECT tr.playerID, tr.registrationID, r.national_ranking, r.ajss_ranking, r.world_ranking
        FROM tournament_register tr
        LEFT JOIN tournament_rankings r ON r.registrationID = tr.registrationID
        WHERE tr.tournamentID = ? AND tr.playerID IS NOT NULL
    ");
    $stmt->bind_param("i", $tournamentID);
    $stmt->execute();

    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $saved) {
        $registered[$saved['playerID']] = $saved;
    }

    $stmt->close();
    $multipleSheets = count(array_unique(array_column($rows, 'sheet'))) > 1;
    $seen = [];      // new players already in the file (email / name)
    $seenPlayers = [];   // existing players already in the file
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

        // A date of birth in the file that can't be right is ignored
        $dobNote = '';

        if (impossibleDob($row['dob'] ?? '')) {
            $dobNote = 'Date of birth in the file (' . date('d M Y', strtotime($row['dob'])) . ') looks wrong - ignored.';
            $row['dob'] = '';
        }

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
            'rankings' => fileRankings($row),
            'changes' => [],
            'registrationID' => 0,
            'key' => $sheet . '|' . (int) $row['row'],
            'profileChanges' => [],
            'profileUpdate' => [],
            'profileUpdated' => false,
            'note' => $dobNote,
        ];

        // ---------- Category: the row's own column, else the sheet name ----------
        $categoryText = trim($row['category'] ?? '');
        $category = findCategory($categories, $categoryText !== '' ? $categoryText : $sheet);
        $noCategory = $categoryText !== ''
            ? '"' . $categoryText . '" is not a category in this tournament.'
            : 'No category - name the sheet after the category (e.g. BU15) or fill in the Category column.';

        // ---------- Already has an account? ----------
        $existing = null;
        $playerIDText = strtoupper(trim($row['player_id'] ?? ''));

        if ($playerIDText !== '' && isset($byID[$playerIDText])) {
            $existing = $byID[$playerIDText];
        } elseif ($email !== '' && isset($byEmail[$email])) {
            $existing = $byEmail[$email];
        } elseif ($nameKey !== '' && $dob !== '' && isset($byNameDob[$nameKey . '|' . $dob])) {
            $existing = $byNameDob[$nameKey . '|' . $dob];
        } elseif ($nameKey !== '' && count($nameOwners[$nameKey] ?? []) === 1) {
            // Only one player has this name: it's them, even if the date of birth differs
            // (the difference is shown so the admin can fix it)
            $existing = $byName[$nameKey];
        } elseif ($nameKey !== '' && count($nameOwners[$nameKey] ?? []) > 1 && !isset($byNameDob[$nameKey . '|' . $dob])) {
            $entry['message'] = count($nameOwners[$nameKey]) . ' players are called ' . $name
                . ' (' . implode(', ', array_keys($nameOwners[$nameKey])) . ') - add the Player ID to this row.';
            $checked[] = $entry;
            continue;
        }

        if ($existing) {

            // ---------- Existing player: register them (no new account) ----------
            $entry['name'] = $existing['player_full_name'];
            $entry['playerID'] = $existing['playerID'];
            $entry['category'] = $category ?? '';

            if (isset($seenPlayers[$existing['playerID']])) {
                $entry['message'] = 'Listed twice in the file (also ' . $seenPlayers[$existing['playerID']] . ').';
            } elseif (isset($registered[$existing['playerID']])) {

                // Already registered: only the rankings can change
                $saved = $registered[$existing['playerID']];
                $entry['registrationID'] = (int) $saved['registrationID'];
                $entry['changes'] = rankingChanges($entry['rankings'], $saved);

                if ($entry['changes']) {
                    $entry['action'] = 'update';
                    $entry['message'] = 'Already registered - rankings: ' . changesText($entry['changes']) . '.';
                } else {
                    $entry['action'] = 'registered';
                    $entry['message'] = 'Already registered in this tournament.';
                }

            } elseif ($category === null) {
                $entry['message'] = $noCategory;
            } else {
                $entry['action'] = 'existing';

                // Registered with their profile rankings, then the file's where they differ
                $entry['changes'] = rankingChanges($entry['rankings'], $existing);

                if ($entry['changes']) {
                    $entry['message'] = 'Rankings from the file: ' . changesText($entry['changes']) . '.';
                }
            }

            // Details in the file that differ from their profile: the admin chooses
            if (!isset($seenPlayers[$existing['playerID']])) {
                $difference = profileDifferences($row, $existing);
                $entry['profileChanges'] = $difference['changes'];
                $entry['profileUpdate'] = $difference['update'];
            }

            $seenPlayers[$existing['playerID']] = $entry['where'];
            $checked[] = $entry;
            continue;
        }

        // ---------- New player ----------
        $seenKey = $email !== '' ? $email : $nameKey . '|' . $dob;

        if ($first === '') {
            $entry['message'] = 'No player name.';
        } elseif ($category === null) {
            $entry['message'] = $noCategory;
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
            $preview = analyseRows($conn, $tournamentID, $sheet['rows'], $categories);

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
        $rows = analyseRows($conn, $tournamentID, $saved['rows'], $categories);
        $remark = 'Registered by ' . $_SESSION['userid'] . ' (Excel)';
        $results = ['added' => [], 'updated' => [], 'skipped' => [], 'profiles' => 0];

        // Rows whose profile the admin ticked to overwrite
        $overwrite = array_flip(array_map('strval', (array) ($_POST['overwrite'] ?? [])));

        foreach ($rows as $entry) {

            // Profile first, so a registration made below uses the new details
            if ($entry['profileUpdate'] && isset($overwrite[$entry['key']])
                && overwriteProfile($conn, $entry['playerID'], $entry['profileUpdate'])) {
                $entry['profileUpdated'] = true;
                $results['profiles']++;
            }

            // Already registered, rankings differ: update this tournament's saved rankings
            if ($entry['action'] === 'update') {
                saveTournamentRankings($conn, $entry['registrationID'], $entry['rankings']);
                $results['updated'][] = $entry;
                continue;
            }

            if (!in_array($entry['action'], ['new', 'existing'], true)) {
                $results[$entry['profileUpdated'] ? 'updated' : 'skipped'][] = $entry;
                continue;
            }

            $conn->begin_transaction();

            try {

                // New player: account first; existing player: just register
                $playerID = $entry['action'] === 'new' ? createPlayer($conn, $entry['data']) : $entry['playerID'];

                $registrationID = insertRegistration(
                    $conn, $tournamentID, $playerID, $entry['category'],
                    $entry['payment'], $entry['amount'], $entry['endorsement'], $remark
                );

                // Existing player: the file's rankings where they differ from the profile
                if ($entry['action'] === 'existing' && $entry['changes']) {
                    saveTournamentRankings($conn, $registrationID, $entry['rankings']);
                }

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


// Pill shown for a row: [css class, text]
function actionPill($action, $done = false)
{
    switch ($action) {
        case 'new':
            return ['new', $done ? 'New account + registered' : 'New account + register'];
        case 'existing':
            return ['existing', $done ? 'Registered (existing player)' : 'Register (existing player)'];
        case 'update':
            return ['update', $done ? 'Rankings updated' : 'Update rankings'];
        case 'registered':
            return ['skipped', 'Skipped'];
        default:
            return ['error', 'Skipped'];
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

            <?php $newAccounts = countAction($results['added'], 'new'); ?>

            <div class="success-message">
                <strong><?= count($results['added']) ?></strong>
                player<?= count($results['added']) === 1 ? '' : 's' ?> registered in this tournament
                <?php if ($newAccounts > 0): ?>
                    (<?= $newAccounts ?> new account<?= $newAccounts === 1 ? '' : 's' ?> &ndash; each new player logs in with their Player ID as user ID and password)
                <?php endif; ?>.
                <?php if (countAction($results['updated'], 'update') > 0): ?>
                    Rankings updated for <?= countAction($results['updated'], 'update') ?> player<?= countAction($results['updated'], 'update') === 1 ? '' : 's' ?> already registered.
                <?php endif; ?>
                <?php if ($results['profiles'] > 0): ?>
                    <?= $results['profiles'] ?> player profile<?= $results['profiles'] === 1 ? '' : 's' ?> overwritten with the file's details.
                <?php endif; ?>
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
                            <?php foreach (array_merge($results['added'], $results['updated'], $results['skipped']) as $entry): ?>
                                <tr>
                                    <td class="where-cell"><?= htmlspecialchars($entry['where']) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($entry['name'] ?: '-') ?></strong>
                                        <?php if ($entry['playerID'] !== ''): ?>
                                            <small><?= htmlspecialchars($entry['playerID']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php [$pillClass, $pillText] = actionPill($entry['action'], true); ?>
                                        <?php if ($entry['action'] === 'registered' && $entry['profileUpdated']): ?>
                                            <span class="row-pill update">Profile overwritten</span>
                                        <?php else: ?>
                                            <span class="row-pill <?= $pillClass ?>"><?= $pillText ?></span>
                                        <?php endif; ?>
                                        <?php if ($entry['message'] !== '' && !($entry['action'] === 'registered' && $entry['profileUpdated'])): ?>
                                            <small><?= htmlspecialchars($entry['message']) ?></small>
                                        <?php endif; ?>
                                        <?php if ($entry['profileUpdated']): ?>
                                            <small class="profile-note">Profile overwritten: <?= htmlspecialchars(implode(', ', array_keys($entry['profileChanges']))) ?></small>
                                        <?php elseif ($entry['profileChanges']): ?>
                                            <small class="profile-note muted">Profile kept (different details in the file were skipped)</small>
                                        <?php endif; ?>
                                    </td>
                                    <?php $willRegister = in_array($entry['action'], ['new', 'existing'], true); ?>
                                    <td><?= htmlspecialchars($entry['category'] ?: '-') ?></td>
                                    <td><?= $willRegister ? ($entry['payment'] === 'PAID' ? 'Paid' : 'Not paid') : '-' ?></td>
                                    <td><?= $willRegister ? ($entry['endorsement'] === 'ENDORSED' ? 'Endorsed' : 'Not endorsed') : '-' ?></td>
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
            $registeredCount = countAction($preview, 'registered');
            $updateCount = countAction($preview, 'update');
            $registerCount = $newCount + $existingCount;
            $profileDiffCount = count(array_filter($preview, function ($entry) {
                return $entry['profileChanges'] !== [];
            }));
            $errorCount = countAction($preview, 'error');
            ?>

            <section class="card">

                <h2>Before registering <span class="count"><?= count($preview) ?> rows</span></h2>

                <p class="muted-text">
                    File: <strong><?= htmlspecialchars($_SESSION['excel_registration']['file'] ?? '') ?></strong>.
                    <?php if (count($_SESSION['excel_registration']['sheets'] ?? []) > 1): ?>
                        Sheets read: <?= htmlspecialchars(implode(', ', $_SESSION['excel_registration']['sheets'])) ?>.
                    <?php endif; ?>
                    New players get an account and are registered; players who already have an account are just registered.
                    Rankings in the file that differ from the ones saved for this tournament replace them (the player's profile is not changed).
                    Nothing is saved until you press <strong>Register</strong>.
                </p>

                <div class="excel-summary">
                    <span class="row-pill new"><?= $newCount ?> new player<?= $newCount === 1 ? '' : 's' ?></span>
                    <span class="row-pill existing"><?= $existingCount ?> existing player<?= $existingCount === 1 ? '' : 's' ?></span>
                    <?php if ($updateCount > 0): ?>
                        <span class="row-pill update"><?= $updateCount ?> already registered, rankings to update</span>
                    <?php endif; ?>
                    <?php if ($registeredCount > 0): ?>
                        <span class="row-pill skipped"><?= $registeredCount ?> already registered</span>
                    <?php endif; ?>
                    <?php if ($errorCount > 0): ?>
                        <span class="row-pill error"><?= $errorCount ?> row<?= $errorCount === 1 ? '' : 's' ?> can't be added</span>
                    <?php endif; ?>
                </div>

                <?php
                // Players whose details in the file differ from the system
                $differences = array_values(array_filter($preview, function ($entry) {
                    return $entry['profileChanges'] !== [] || ($entry['changes'] !== [] && in_array($entry['action'], ['existing', 'update'], true));
                }));
                ?>

                <?php if ($differences): ?>

                    <!-- ==========================================
                         DIFFERENCES: which player, what is different
                    =========================================== -->

                    <div class="differences">

                        <div class="differences-head">
                            <p>
                                <i class="fa-solid fa-user-pen"></i>
                                <strong><?= count($differences) ?> player<?= count($differences) === 1 ? ' has' : 's have' ?> different details in the file</strong>
                                than in the system.
                                <?php if ($profileDiffCount > 0): ?>
                                    Tick <strong>Overwrite profile</strong> to use the file's details &ndash; unticked profiles are kept as they are.
                                <?php endif; ?>
                            </p>
                            <?php if ($profileDiffCount > 1): ?>
                                <label class="check overwrite-all">
                                    <input type="checkbox" id="overwriteAll"> Overwrite all <?= $profileDiffCount ?> profiles
                                </label>
                            <?php endif; ?>
                        </div>

                        <div class="table-wrap">
                            <table class="admins-table differences-table">
                                <thead>
                                    <tr>
                                        <th>Player</th>
                                        <th>Detail</th>
                                        <th>In the system</th>
                                        <th>In the file</th>
                                        <th>What happens</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($differences as $entry): ?>
                                        <?php
                                        // Profile details first, then this tournament's rankings
                                        $lines = [];

                                        foreach ($entry['profileChanges'] as $label => [$old, $new]) {
                                            $lines[] = ['profile', $label, $old, $new];
                                        }

                                        if (in_array($entry['action'], ['existing', 'update'], true)) {
                                            foreach ($entry['changes'] as $label => [$old, $new]) {
                                                $lines[] = ['tournament', $label . ' (this tournament)', $old ?? '-', $new];
                                            }
                                        }

                                        $profileLines = count($entry['profileChanges']);
                                        ?>
                                        <?php foreach ($lines as $index => [$kind, $label, $old, $new]): ?>
                                            <tr class="<?= $index === 0 ? 'first-line' : '' ?>">
                                                <?php if ($index === 0): ?>
                                                    <td rowspan="<?= count($lines) ?>" class="diff-player">
                                                        <strong><?= htmlspecialchars($entry['name']) ?></strong>
                                                        <small><?= htmlspecialchars($entry['playerID']) ?> &middot; <?= htmlspecialchars($entry['where']) ?></small>
                                                    </td>
                                                <?php endif; ?>
                                                <td><?= htmlspecialchars($label) ?></td>
                                                <td class="diff-old"><?= htmlspecialchars((string) $old) ?></td>
                                                <td class="diff-new"><?= htmlspecialchars((string) $new) ?></td>
                                                <?php if ($kind === 'profile' && $index === 0): ?>
                                                    <td rowspan="<?= $profileLines ?>">
                                                        <label class="check">
                                                            <input type="checkbox" class="overwrite-box" name="overwrite[]" form="excelConfirm" value="<?= htmlspecialchars($entry['key']) ?>">
                                                            Overwrite profile
                                                        </label>
                                                        <small class="diff-hint">Unticked: profile kept</small>
                                                    </td>
                                                <?php elseif ($kind === 'tournament'): ?>
                                                    <td><span class="diff-auto">Updated for this tournament</span></td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>

                <?php endif; ?>

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
                                <tr class="<?= in_array($entry['action'], ['new', 'existing', 'update'], true) ? '' : 'row-skipped' ?>">
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
                                        <?php [$pillClass, $pillText] = actionPill($entry['action']); ?>
                                        <span class="row-pill <?= $pillClass ?>"><?= $pillText ?></span>
                                        <?php if ($entry['message'] !== ''): ?>
                                            <small><?= htmlspecialchars($entry['message']) ?></small>
                                        <?php endif; ?>
                                        <?php if ($entry['note'] !== ''): ?>
                                            <small class="dob-note"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($entry['note']) ?></small>
                                        <?php endif; ?>
                                        <?php if ($entry['profileChanges']): ?>
                                            <div class="profile-diff">
                                                <ul>
                                                    <?php foreach ($entry['profileChanges'] as $label => [$old, $new]): ?>
                                                        <li><span><?= htmlspecialchars($label) ?>:</span> <?= htmlspecialchars($old) ?> <i class="fa-solid fa-arrow-right"></i> <strong><?= htmlspecialchars($new) ?></strong></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                                <small class="diff-hint">Overwrite or keep: see <strong>Differences</strong> above.</small>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <?php $willRegister = in_array($entry['action'], ['new', 'existing'], true); ?>
                                    <td><?= htmlspecialchars($entry['category'] ?: '-') ?></td>
                                    <td><?= $willRegister ? ($entry['payment'] === 'PAID' ? 'Paid' . ($entry['amount'] !== '' ? ' <small>' . htmlspecialchars($entry['amount']) . '</small>' : '') : 'Not paid') : '-' ?></td>
                                    <td><?= $willRegister ? ($entry['endorsement'] === 'ENDORSED' ? 'Endorsed' : 'Not endorsed') : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="excel-actions">

                    <?php if ($registerCount + $updateCount + $profileDiffCount > 0): ?>
                        <form method="POST" action="<?= $pageUrl ?>" id="excelConfirm"
                              onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Registering...';">
                            <input type="hidden" name="action" value="confirm">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($previewToken) ?>">
                            <button type="submit" class="add-btn">
                                <?php if ($registerCount > 0): ?>
                                    Register <?= $registerCount ?> player<?= $registerCount === 1 ? '' : 's' ?><?= $updateCount > 0 ? ' and update ' . $updateCount . ' ranking' . ($updateCount === 1 ? '' : 's') : '' ?>
                                <?php elseif ($updateCount > 0): ?>
                                    Update rankings for <?= $updateCount ?> player<?= $updateCount === 1 ? '' : 's' ?>
                                <?php else: ?>
                                    Save ticked profile changes
                                <?php endif; ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <p class="muted-text no-new">Nothing to do &ndash; everyone in this file is already registered in this tournament, with the same rankings.</p>
                    <?php endif; ?>

                    <a class="small-btn" href="<?= $pageUrl ?>">Upload a different file</a>

                </div>

            </section>

            <script>
                // "Overwrite all" ticks / unticks every profile box
                (function () {
                    const all = document.getElementById('overwriteAll');
                    const boxes = document.querySelectorAll('.overwrite-box');

                    if (!all) {
                        return;
                    }

                    all.addEventListener('change', function () {
                        boxes.forEach(function (box) {
                            box.checked = all.checked;
                        });
                    });

                    boxes.forEach(function (box) {
                        box.addEventListener('change', function () {
                            all.checked = Array.from(boxes).every(function (b) { return b.checked; });
                        });
                    });
                })();
            </script>

        <?php else: ?>

            <!-- ==========================================
                 UPLOAD
            =========================================== -->

            <section class="card form-page excel-upload">

                <h2><span class="step">1</span> Download the template</h2>

                <p class="muted-text">
                    One player per row, one sheet per category (sheets named <strong>BU19, BU15, GU13 ...</strong>),
                    or a <strong>Category</strong> column. Everyone in the file is registered in this tournament.
                    Players who <strong>already have an account</strong> (found by Player ID, email, or name + date of birth)
                    are just registered; <strong>new players</strong> get an account too, and their first password is
                    their new Player ID. A new player's gender comes from the category (BU = boys, GU = girls).
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

                <p class="field-hint">Excel (.xlsx / .xls) or CSV, up to 5 MB. You'll see who will be registered before anything is saved.</p>

            </section>

        <?php endif; ?>

    </main>

</body>

</html>
