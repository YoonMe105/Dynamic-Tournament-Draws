<?php

require_once '../db.php';
require_once 'admin_auth.php';

// Platform Admins, or the admin who created this tournament
requireTournamentAccess($_GET['id'] ?? 0);
require_once 'admin_referee_functions.php';


/*
|--------------------------------------------------------------------------
| TOURNAMENT
|--------------------------------------------------------------------------
*/

$tournamentID = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $conn->prepare("SELECT * FROM tournament WHERE tournamentID = ?");
$stmt->bind_param("i", $tournamentID);
$stmt->execute();

$tournament = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$tournament) {
    die("Tournament not found.");
}

$pageUrl = "admin_referees.php?id=" . $tournamentID;
$nationalities = refereeNationalities();
$levels = refereeLevels();


/*
|--------------------------------------------------------------------------
| ACTIONS: add / edit / remove / make active again
|--------------------------------------------------------------------------
*/

// A referee of this tournament, or null
function findReferee($conn, $tournamentID, $refereeID)
{
    $stmt = $conn->prepare("SELECT * FROM referees WHERE refereeID = ? AND tournamentID = ?");
    $stmt->bind_param("ii", $refereeID, $tournamentID);
    $stmt->execute();

    $referee = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return $referee;
}

$formError = '';
$form = ['refereeID' => 0, 'referee_name' => '', 'referee_nationality' => 'MALAYSIAN', 'referee_level' => '', 'referee_phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $refereeID = (int) ($_POST['referee_id'] ?? 0);

    if ($action === 'save') {

        $form = [
            'refereeID' => $refereeID,
            'referee_name' => trim($_POST['referee_name'] ?? ''),
            'referee_nationality' => strtoupper(trim($_POST['referee_nationality'] ?? '')),
            'referee_level' => trim($_POST['referee_level'] ?? ''),
            'referee_phone' => trim($_POST['referee_phone'] ?? ''),
        ];

        if ($form['referee_name'] === '') {
            $formError = 'Enter the referee\'s name.';
        } elseif (!isset($nationalities[$form['referee_nationality']])) {
            $formError = 'Choose the referee\'s nationality.';
        } elseif (!in_array($form['referee_level'], $levels, true)) {
            $formError = 'Choose the referee\'s level.';
        } elseif ($form['referee_phone'] !== '' && !preg_match('/^[0-9+\-\s()]{6,30}$/', $form['referee_phone'])) {
            $formError = 'The phone number can only have numbers, spaces, +, - and brackets.';
        } elseif ($refereeID && !findReferee($conn, $tournamentID, $refereeID)) {
            $formError = 'That referee could not be found.';
        }

        if ($formError === '') {

            $phone = $form['referee_phone'] === '' ? null : $form['referee_phone'];

            if ($refereeID) {

                $stmt = $conn->prepare("
                    UPDATE referees
                    SET referee_name = ?, referee_nationality = ?, referee_level = ?, referee_phone = ?
                    WHERE refereeID = ? AND tournamentID = ?
                ");
                $stmt->bind_param("ssssii", $form['referee_name'], $form['referee_nationality'], $form['referee_level'], $phone, $refereeID, $tournamentID);

            } else {

                $stmt = $conn->prepare("
                    INSERT INTO referees (tournamentID, referee_name, referee_nationality, referee_level, referee_phone)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("issss", $tournamentID, $form['referee_name'], $form['referee_nationality'], $form['referee_level'], $phone);
            }

            $stmt->execute();
            $stmt->close();

            header("Location: " . $pageUrl . "&message=" . ($refereeID ? 'updated' : 'added'));
            exit;
        }
    }

    // Remove: deleted when they have no matches, otherwise made inactive
    if ($action === 'remove' && ($referee = findReferee($conn, $tournamentID, $refereeID))) {

        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM matches WHERE refereeID = ?");
        $stmt->bind_param("i", $refereeID);
        $stmt->execute();
        $hasMatches = $stmt->get_result()->fetch_assoc()['total'] > 0;
        $stmt->close();

        $stmt = $conn->prepare($hasMatches
            ? "UPDATE referees SET is_active = 0 WHERE refereeID = ?"
            : "DELETE FROM referees WHERE refereeID = ?");
        $stmt->bind_param("i", $refereeID);
        $stmt->execute();
        $stmt->close();

        header("Location: " . $pageUrl . "&message=" . ($hasMatches ? 'inactive' : 'removed'));
        exit;
    }

    if ($action === 'activate' && findReferee($conn, $tournamentID, $refereeID)) {

        $stmt = $conn->prepare("UPDATE referees SET is_active = 1 WHERE refereeID = ?");
        $stmt->bind_param("i", $refereeID);
        $stmt->execute();
        $stmt->close();

        header("Location: " . $pageUrl . "&message=activated");
        exit;
    }
}

// Edit: the form starts with that referee
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {

    $editing = findReferee($conn, $tournamentID, (int) $_GET['edit']);

    if ($editing) {
        $form = [
            'refereeID' => (int) $editing['refereeID'],
            'referee_name' => $editing['referee_name'],
            'referee_nationality' => $editing['referee_nationality'],
            'referee_level' => $editing['referee_level'],
            'referee_phone' => $editing['referee_phone'] ?? '',
        ];
    }
}

$referees = loadReferees($conn, $tournamentID);

$messages = [
    'added' => 'The referee has been added.',
    'updated' => 'The referee has been updated.',
    'removed' => 'The referee has been removed.',
    'inactive' => 'The referee already has matches, so they have been made inactive instead of removed. Their matches keep their name.',
    'activated' => 'The referee is active again.',
];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Referees - <?= htmlspecialchars($tournament['tournament_name']) ?></title>

    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <link rel="stylesheet" href="assets/admin_seeding.css?v=<?= filemtime(__DIR__ . '/assets/admin_seeding.css') ?>">
    <link rel="stylesheet" href="assets/referees.css?v=<?= filemtime(__DIR__ . '/assets/referees.css') ?>">

</head>

<body>

    <?php require 'admin_navbar.php'; ?>


    <main class="container">

        <a href="admin_each_tournament.php?id=<?= $tournamentID ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Tournament
        </a>

        <div class="page-header">

            <div>

                <h1>Referees</h1>

                <p><?= htmlspecialchars($tournament['tournament_name']) ?></p>

            </div>

            <a href="admin_draw.php?id=<?= $tournamentID ?>" class="back-btn">
                <i class="fa-solid fa-sitemap"></i> Draw
            </a>

        </div>


        <?php if (isset($messages[$_GET['message'] ?? ''])): ?>
            <div class="success-message"><?= $messages[$_GET['message']] ?></div>
        <?php endif; ?>


        <!-- ==========================================================
            ADD / EDIT REFEREE
        =========================================================== -->

        <form method="POST" action="<?= htmlspecialchars($pageUrl) ?>" class="referee-form" id="refereeForm">

            <input type="hidden" name="action" value="save">
            <input type="hidden" name="referee_id" value="<?= (int) $form['refereeID'] ?>">

            <h2>
                <?php if ($form['refereeID']): ?>
                    <i class="fa-solid fa-pen"></i> Edit Referee
                <?php else: ?>
                    <i class="fa-solid fa-user-plus"></i> Add Referee
                <?php endif; ?>
            </h2>

            <?php if ($formError !== ''): ?>
                <p class="referee-error"><?= htmlspecialchars($formError) ?></p>
            <?php endif; ?>

            <div class="referee-fields">

                <label>
                    Name
                    <input type="text" name="referee_name" maxlength="150" required
                           value="<?= htmlspecialchars($form['referee_name']) ?>">
                </label>

                <label>
                    Nationality
                    <select name="referee_nationality" required>
                        <option value="">Select Nationality</option>
                        <?php foreach ($nationalities as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>" <?= $form['referee_nationality'] === $value ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Level
                    <select name="referee_level" required>
                        <option value="">Select Level</option>
                        <?php foreach ($levels as $level): ?>
                            <option value="<?= $level ?>" <?= $form['referee_level'] === $level ? 'selected' : '' ?>><?= $level ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Phone <span class="optional">(optional)</span>
                    <input type="tel" name="referee_phone" maxlength="30"
                           value="<?= htmlspecialchars($form['referee_phone']) ?>">
                </label>

            </div>

            <div class="referee-form-actions">
                <?php if ($form['refereeID']): ?>
                    <a href="<?= htmlspecialchars($pageUrl) ?>" class="back-btn">Cancel</a>
                <?php endif; ?>
                <button type="submit" class="btn"><?= $form['refereeID'] ? 'Save Changes' : 'Add Referee' ?></button>
            </div>

        </form>


        <!-- ==========================================================
            REFEREE LIST
        =========================================================== -->

        <div class="table-wrapper">

            <table class="referee-table">

                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Name</th>
                        <th>Nationality</th>
                        <th>Level</th>
                        <th>Phone</th>
                        <th>Matches</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (!$referees): ?>

                        <tr>
                            <td colspan="8" class="referee-empty">No referees yet. Add the tournament's appointed officials above.</td>
                        </tr>

                    <?php endif; ?>

                    <?php foreach ($referees as $number => $referee): ?>

                        <tr class="<?= $referee['is_active'] ? '' : 'is-inactive' ?>">

                            <td><?= $number + 1 ?></td>
                            <td class="referee-name"><?= htmlspecialchars($referee['referee_name']) ?></td>
                            <td><?= htmlspecialchars($nationalities[$referee['referee_nationality']] ?? $referee['referee_nationality']) ?></td>
                            <td><?= htmlspecialchars($referee['referee_level']) ?></td>
                            <td><?= $referee['referee_phone'] !== null ? htmlspecialchars($referee['referee_phone']) : '-' ?></td>
                            <td><?= (int) $referee['match_count'] ?></td>
                            <td>
                                <span class="referee-status <?= $referee['is_active'] ? 'active' : '' ?>">
                                    <?= $referee['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>

                            <td class="referee-actions">

                                <a href="<?= htmlspecialchars($pageUrl) ?>&edit=<?= (int) $referee['refereeID'] ?>#refereeForm"
                                   class="icon-btn" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </a>

                                <?php if ($referee['is_active']): ?>

                                    <form method="POST" action="<?= htmlspecialchars($pageUrl) ?>"
                                          onsubmit="return confirm(<?= htmlspecialchars(json_encode($referee['match_count'] > 0
                                              ? 'Make ' . $referee['referee_name'] . ' inactive? They already have matches, so they will be kept for those matches but can\'t be chosen for new ones.'
                                              : 'Remove ' . $referee['referee_name'] . '?'), ENT_QUOTES) ?>);">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="referee_id" value="<?= (int) $referee['refereeID'] ?>">
                                        <button type="submit" class="icon-btn danger" title="Remove">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>

                                <?php else: ?>

                                    <form method="POST" action="<?= htmlspecialchars($pageUrl) ?>">
                                        <input type="hidden" name="action" value="activate">
                                        <input type="hidden" name="referee_id" value="<?= (int) $referee['refereeID'] ?>">
                                        <button type="submit" class="icon-btn" title="Make active again">
                                            <i class="fa-solid fa-rotate-left"></i>
                                        </button>
                                    </form>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </main>

</body>

</html>
