<?php

require_once '../db.php';
require_once 'admin_auth.php';
require_once '../platform_fee.php';
require_once '../registration_functions.php';


/*
|--------------------------------------------------------------------------
| Register a player (by the organizer)
|--------------------------------------------------------------------------
| The tournament's organizer (or a Platform Admin) registers a player:
| an existing player account, or a new one created here (password = their
| new player ID, which they can change in My Profile). Same rules as when
| players register themselves: one registration per player, and the
| category must suit the player's gender and age on the cut-off date.
| Late entries after the deadline are allowed; an unpaid subscription is not.
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
$cutoff = $tournament['tournament_age_cutoff'];
$hasCutoff = ageOnCutoff('2000-01-01', $cutoff) !== null;

$nationalities = [
    'MALAYSIAN', 'SINGAPOREAN', 'INDONESIAN', 'THAI', 'FILIPINO', 'VIETNAMESE', 'BRUNEIAN', 'CAMBODIAN',
    'MYANMAR', 'CHINESE', 'JAPANESE', 'KOREAN', 'INDIAN', 'PAKISTANI', 'BANGLADESHI', 'SRI LANKAN',
    'NEPALESE', 'AUSTRALIAN', 'NEW ZEALANDER', 'BRITISH', 'AMERICAN', 'CANADIAN', 'FRENCH', 'GERMAN',
];


// Active players not registered in this tournament yet
$stmt = $conn->prepare("
    SELECT playerID, player_full_name, player_gender, player_dob, player_nationality, player_email
    FROM players
    WHERE player_active = 'active'
      AND playerID NOT IN (SELECT playerID FROM tournament_register WHERE tournamentID = ? AND playerID IS NOT NULL)
    ORDER BY player_full_name
");
$stmt->bind_param("i", $tournamentID);
$stmt->execute();
$availablePlayers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


/*
| Why a category can't be chosen for this gender / age ('' = it can)
*/
function categoryProblem($code, $gender, $age, $hasCutoff)
{
    $categoryGender = categoryGender($code);
    $maxAge = categoryMaxAge($code);

    if ($gender !== null && $categoryGender !== null && $categoryGender !== $gender) {
        return $code . ' is for ' . ($categoryGender === 'male' ? 'boys / men' : 'girls / women') . ' only.';
    }

    if ($maxAge !== null && $hasCutoff && $age === null) {
        return 'The player needs a date of birth for ' . $code . '.';
    }

    if ($maxAge !== null && $hasCutoff && $age >= $maxAge) {
        return 'The player is ' . $age . ' on the age cut-off date, too old for ' . $code . ' (must be under ' . $maxAge . ').';
    }

    return '';
}


function normalGender($value)
{
    $value = strtolower(trim((string) $value));

    return in_array($value, ['male', 'female'], true) ? $value : null;
}


$form = [
    'player_mode' => 'existing',
    'playerID' => '',
    'first_name' => '',
    'last_name' => '',
    'gender' => '',
    'dob' => '',
    'nationality' => '',
    'email' => '',
    'contact' => '',
    'national_ranking' => '',
    'ajss_ranking' => '',
    'world_ranking' => '',
    'category' => '',
    'payment_status' => 'NOT PAID',
    'amount_paid' => '',
    'endorsement' => 'NOT ENDORSED',
    'admin_remark' => '',
];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($form as $key => $value) {
        $form[$key] = trim($_POST[$key] ?? $value);
    }

    $isNew = $form['player_mode'] === 'new';
    $player = null;

    if ($subscriptionUnpaid) {

        $error = 'Registration is closed until the T_Software subscription for this tournament is paid.';

    } elseif ($isNew) {

        // ---------- New player ----------
        if ($form['first_name'] === '' || $form['last_name'] === '') {
            $error = 'Enter the player\'s first and last name.';
        } elseif (normalGender($form['gender']) === null) {
            $error = 'Choose the player\'s gender.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['dob']) || strtotime($form['dob']) === false || $form['dob'] > date('Y-m-d')) {
            $error = 'Enter a valid date of birth.';
        } elseif (!in_array($form['nationality'], $nationalities, true)) {
            $error = 'Choose the player\'s nationality.';
        } elseif ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address (or leave it empty).';
        } else {
            foreach (['national_ranking' => 'National', 'ajss_ranking' => 'AJSS', 'world_ranking' => 'World'] as $field => $label) {
                if ($form[$field] !== '' && !preg_match('/^\d{1,6}$/', $form[$field])) {
                    $error = $label . ' ranking must be a number (or leave it empty).';
                    break;
                }
            }
        }

        if ($error === '' && $form['email'] !== '') {
            $stmt = $conn->prepare("SELECT playerID FROM players WHERE player_email = ? LIMIT 1");
            $stmt->bind_param("s", $form['email']);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existing) {
                $error = 'This email already belongs to player ' . $existing['playerID'] . ' - choose them under "Existing player".';
            }
        }

        if ($error === '') {
            $player = [
                'player_gender' => $form['gender'],
                'player_dob' => $form['dob'],
                'player_full_name' => $form['first_name'] . ' ' . $form['last_name'],
            ];
        }

    } else {

        // ---------- Existing player ----------
        foreach ($availablePlayers as $candidate) {
            if ($candidate['playerID'] === $form['playerID']) {
                $player = $candidate;
            }
        }

        if (!$player) {
            $error = 'Choose a player (players already registered in this tournament are not listed).';
        } elseif (normalGender($player['player_gender']) === null) {
            $error = $player['player_full_name'] . ' has no gender on their profile - add it before registering them.';
        }
    }

    // ---------- Category ----------
    if ($error === '' && !isset($categories[$form['category']])) {
        $error = 'Choose a category.';
    }

    if ($error === '') {
        $error = categoryProblem(
            $form['category'],
            normalGender($player['player_gender']),
            ageOnCutoff($player['player_dob'], $cutoff),
            $hasCutoff
        );
    }

    if ($error === '' && !in_array($form['payment_status'], ['NOT PAID', 'PAID'], true)) {
        $error = 'Choose the payment status.';
    }

    if ($error === '' && !in_array($form['endorsement'], ['NOT ENDORSED', 'ENDORSED'], true)) {
        $error = 'Choose the endorsement.';
    }

    // ---------- Save ----------
    if ($error === '') {

        $conn->begin_transaction();

        try {

            if ($isNew) {

                $row = $conn->query("
                    SELECT MAX(CAST(SUBSTRING(playerID, 2) AS UNSIGNED)) AS last_number
                    FROM players WHERE playerID REGEXP '^P[0-9]+$'
                ")->fetch_assoc();

                $newPlayerID = 'P' . str_pad((string) ((int) $row['last_number'] + 1), 4, '0', STR_PAD_LEFT);
                $fullName = $form['first_name'] . ' ' . $form['last_name'];
                $email = $form['email'] !== '' ? $form['email'] : null;
                $national = $form['national_ranking'] !== '' ? $form['national_ranking'] : null;
                $status = 'offline';

                // First password = their player ID; they can change it in My Profile
                $stmt = $conn->prepare("
                    INSERT INTO players (
                        playerID, player_password, player_full_name, player_first_name, player_last_name,
                        player_email, player_dob, player_gender, player_nationality, player_contact,
                        national_ranking, ajss_ranking, world_ranking, player_passport, player_status, player_active
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', ?, 'active')
                ");
                $stmt->bind_param(
                    "ssssssssssssss",
                    $newPlayerID, $newPlayerID, $fullName, $form['first_name'], $form['last_name'],
                    $email, $form['dob'], $form['gender'], $form['nationality'], $form['contact'],
                    $national, $form['ajss_ranking'], $form['world_ranking'], $status
                );

                if (!$stmt->execute()) {
                    throw new Exception('the player could not be created');
                }

                $stmt->close();

                $registerPlayerID = $newPlayerID;

            } else {

                $registerPlayerID = $player['playerID'];
            }

            $paid = $form['payment_status'] === 'PAID';
            $endorsed = $form['endorsement'] === 'ENDORSED';
            $amountPaid = $paid && $form['amount_paid'] !== '' ? $form['amount_paid'] : null;
            $paymentDate = $paid ? date('Y-m-d H:i:s') : null;
            $endorsementDate = $endorsed ? date('Y-m-d') : null;
            $remark = 'Registered by ' . $_SESSION['userid'] . ($form['admin_remark'] !== '' ? ': ' . $form['admin_remark'] : '');

            $stmt = $conn->prepare("
                INSERT INTO tournament_register (
                    category_registered, payment_status, amount_paid, payment_date,
                    endorsement, endorsement_date, admin_remark, tournamentID, playerID
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "sssssssis",
                $form['category'], $form['payment_status'], $amountPaid, $paymentDate,
                $form['endorsement'], $endorsementDate, $remark, $tournamentID, $registerPlayerID
            );

            if (!$stmt->execute()) {
                throw new Exception('the registration could not be saved');
            }

            $registrationID = $stmt->insert_id;
            $stmt->close();

            // Rankings at registration time (used for seeding)
            if (!saveRegistrationRankings($conn, $registrationID)) {
                throw new Exception('the rankings could not be saved');
            }

            $conn->commit();

            header("Location: admin_register_player.php?id=" . $tournamentID
                . "&registered=" . urlencode($registerPlayerID)
                . "&category=" . urlencode($form['category'])
                . ($isNew ? "&new=1" : ""));
            exit;

        } catch (Exception $e) {

            $conn->rollback();
            $error = 'Sorry, ' . $e->getMessage() . '. Please try again.';
        }
    }
}


// For the browser: which categories each gender / age may choose (same rules as above)
$categoryRules = [];

foreach ($categories as $code => $name) {
    $categoryRules[] = ['code' => $code, 'gender' => categoryGender($code), 'maxAge' => categoryMaxAge($code)];
}

$registeredName = '';

if (isset($_GET['registered'])) {
    $stmt = $conn->prepare("SELECT player_full_name FROM players WHERE playerID = ?");
    $stmt->bind_param("s", $_GET['registered']);
    $stmt->execute();
    $registeredName = $stmt->get_result()->fetch_assoc()['player_full_name'] ?? '';
    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Register a Player - <?= htmlspecialchars($tournament['tournament_name']) ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

    <link href="./assets/style.css" rel="stylesheet" type="text/css">
    <link href="./assets/admins.css?v=<?= filemtime(__DIR__ . '/assets/admins.css') ?>" rel="stylesheet" type="text/css">
    <link href="./assets/register_player.css?v=<?= filemtime(__DIR__ . '/assets/register_player.css') ?>" rel="stylesheet" type="text/css">

</head>

<body>

    <?php require_once 'admin_navbar.php'; ?>


    <main class="admins-page">

        <a href="admin_each_tournament.php?id=<?= $tournamentID ?>" class="back-link">&larr; Back to Tournament</a>

        <div class="page-title">
            <h1>Register a Player</h1>
            <p><?= htmlspecialchars($tournament['tournament_name']) ?></p>
        </div>


        <?php if (isset($_GET['registered'])): ?>
            <div class="success-message">
                <strong><?= htmlspecialchars($registeredName ?: $_GET['registered']) ?></strong>
                (<?= htmlspecialchars($_GET['registered']) ?>) is registered in
                <strong><?= htmlspecialchars($_GET['category'] ?? '') ?></strong>.
                <?php if (isset($_GET['new'])): ?>
                    Their new account logs in with user ID <strong><?= htmlspecialchars($_GET['registered']) ?></strong>
                    and password <strong><?= htmlspecialchars($_GET['registered']) ?></strong> (they can change it in My Profile).
                <?php endif; ?>
                <a href="admin_each_tournament.php?id=<?= $tournamentID ?>">Back to the tournament</a>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>


        <?php if ($subscriptionUnpaid): ?>

            <section class="card form-page">
                <p class="muted-text closed-note">
                    <i class="fa-solid fa-lock"></i>
                    Registration for this tournament is closed until the T_Software subscription is paid.
                    <?php if (!isPlatformAdmin()): ?>
                        <a href="admin_platform_payment.php?id=<?= $tournamentID ?>">Pay now</a>
                    <?php endif; ?>
                </p>
            </section>

        <?php elseif (!$categories): ?>

            <section class="card form-page">
                <p class="muted-text closed-note">This tournament has no categories yet, so players can't be registered.</p>
            </section>

        <?php else: ?>

            <form method="POST" action="admin_register_player.php?id=<?= $tournamentID ?>" class="add-form register-form" id="registerForm">

                <!-- ==========================================
                     1. PLAYER
                =========================================== -->

                <section class="card">

                    <h2><span class="step">1</span> Player</h2>

                    <div class="mode-tabs" role="radiogroup">
                        <label class="choice">
                            <input type="radio" name="player_mode" value="existing" <?= $form['player_mode'] !== 'new' ? 'checked' : '' ?>>
                            <span><strong>Existing player</strong><small>Already has an account</small></span>
                        </label>
                        <label class="choice">
                            <input type="radio" name="player_mode" value="new" <?= $form['player_mode'] === 'new' ? 'checked' : '' ?>>
                            <span><strong>New player</strong><small>Create their account now</small></span>
                        </label>
                    </div>


                    <!-- Existing player -->
                    <div id="existingPlayer">

                        <?php if ($availablePlayers): ?>

                            <label class="search-box">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="search" id="playerSearch" placeholder="Search by name, player ID or email" autocomplete="off">
                            </label>

                            <div class="player-pick" id="playerList">
                                <?php foreach ($availablePlayers as $candidate): ?>
                                    <?php
                                    $candidateAge = ageOnCutoff($candidate['player_dob'], $cutoff);
                                    $candidateGender = normalGender($candidate['player_gender']);
                                    ?>
                                    <label class="player-option"
                                           data-search="<?= htmlspecialchars(strtolower($candidate['playerID'] . ' ' . $candidate['player_full_name'] . ' ' . $candidate['player_email'])) ?>"
                                           data-gender="<?= htmlspecialchars((string) $candidateGender) ?>"
                                           data-age="<?= $candidateAge === null ? '' : (int) $candidateAge ?>">
                                        <input type="radio" name="playerID" value="<?= htmlspecialchars($candidate['playerID']) ?>"
                                            <?= $form['playerID'] === $candidate['playerID'] ? 'checked' : '' ?>>
                                        <span class="player-text">
                                            <strong><?= htmlspecialchars($candidate['player_full_name']) ?></strong>
                                            <small>
                                                <?= htmlspecialchars($candidate['playerID']) ?>
                                                &middot; <?= htmlspecialchars($candidateGender ? ucfirst($candidateGender) : 'No gender') ?>
                                                &middot; <?= $candidate['player_dob'] && strpos($candidate['player_dob'], '0000') !== 0 ? date('d M Y', strtotime($candidate['player_dob'])) : 'No date of birth' ?>
                                                &middot; <?= htmlspecialchars($candidate['player_nationality'] ?: '-') ?>
                                            </small>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                                <p class="muted-text no-match" id="noMatch" hidden>No player matches your search.</p>
                            </div>

                        <?php else: ?>

                            <p class="muted-text">Every player is already registered in this tournament. Add a new player instead.</p>

                        <?php endif; ?>

                    </div>


                    <!-- New player -->
                    <div id="newPlayer">

                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First name <span class="required">*</span></label>
                                <input type="text" id="first_name" name="first_name" maxlength="255" value="<?= htmlspecialchars($form['first_name']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last name <span class="required">*</span></label>
                                <input type="text" id="last_name" name="last_name" maxlength="255" value="<?= htmlspecialchars($form['last_name']) ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="gender">Gender <span class="required">*</span></label>
                                <select id="gender" name="gender">
                                    <option value="">Choose...</option>
                                    <option value="male" <?= $form['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                                    <option value="female" <?= $form['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="dob">Date of birth <span class="required">*</span></label>
                                <input type="date" id="dob" name="dob" max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($form['dob']) ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="nationality">Nationality <span class="required">*</span></label>
                                <select id="nationality" name="nationality">
                                    <option value="">Choose...</option>
                                    <?php foreach ($nationalities as $nationality): ?>
                                        <option value="<?= $nationality ?>" <?= $form['nationality'] === $nationality ? 'selected' : '' ?>><?= $nationality ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="contact">Contact number</label>
                                <input type="text" id="contact" name="contact" maxlength="50" value="<?= htmlspecialchars($form['contact']) ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" maxlength="255" value="<?= htmlspecialchars($form['email']) ?>">
                        </div>

                        <div class="form-row three">
                            <div class="form-group">
                                <label for="national_ranking">National ranking</label>
                                <input type="text" id="national_ranking" name="national_ranking" inputmode="numeric" value="<?= htmlspecialchars($form['national_ranking']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="ajss_ranking">AJSS ranking</label>
                                <input type="text" id="ajss_ranking" name="ajss_ranking" inputmode="numeric" value="<?= htmlspecialchars($form['ajss_ranking']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="world_ranking">World ranking</label>
                                <input type="text" id="world_ranking" name="world_ranking" inputmode="numeric" value="<?= htmlspecialchars($form['world_ranking']) ?>">
                            </div>
                        </div>

                        <p class="password-note">
                            The new player's first password is their player ID. They can change it in My Profile after logging in.
                        </p>

                    </div>

                </section>


                <!-- ==========================================
                     2. CATEGORY
                =========================================== -->

                <section class="card">

                    <h2><span class="step">2</span> Category</h2>

                    <div class="form-group">
                        <label for="category">Category <span class="required">*</span></label>
                        <select id="category" name="category" required>
                            <option value="">Choose a player first...</option>
                            <?php foreach ($categories as $code => $name): ?>
                                <option value="<?= htmlspecialchars($code) ?>" <?= $form['category'] === $code ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($code === $name ? $code : $code . ' - ' . $name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="field-hint" id="categoryHint">
                            <?= $hasCutoff
                                ? 'Age categories use the player\'s age on ' . date('d M Y', strtotime($cutoff)) . '.'
                                : 'This tournament has no age cut-off date.' ?>
                        </small>
                    </div>

                </section>


                <!-- ==========================================
                     3. PAYMENT & ENDORSEMENT
                =========================================== -->

                <section class="card">

                    <h2><span class="step">3</span> Payment &amp; endorsement</h2>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="payment_status">Payment</label>
                            <select id="payment_status" name="payment_status">
                                <option value="NOT PAID" <?= $form['payment_status'] === 'NOT PAID' ? 'selected' : '' ?>>Not paid</option>
                                <option value="PAID" <?= $form['payment_status'] === 'PAID' ? 'selected' : '' ?>>Paid</option>
                            </select>
                        </div>
                        <div class="form-group" id="amountGroup">
                            <label for="amount_paid">Amount paid</label>
                            <input type="text" id="amount_paid" name="amount_paid" maxlength="50" placeholder="e.g. RM 120.00"
                                   value="<?= htmlspecialchars($form['amount_paid']) ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="endorsement">Endorsement</label>
                            <select id="endorsement" name="endorsement">
                                <option value="NOT ENDORSED" <?= $form['endorsement'] === 'NOT ENDORSED' ? 'selected' : '' ?>>Not endorsed</option>
                                <option value="ENDORSED" <?= $form['endorsement'] === 'ENDORSED' ? 'selected' : '' ?>>Endorsed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="admin_remark">Remark</label>
                            <input type="text" id="admin_remark" name="admin_remark" maxlength="200" placeholder="Optional"
                                   value="<?= htmlspecialchars($form['admin_remark']) ?>">
                        </div>
                    </div>

                    <?php if (strtotime($tournament['tournament_deadline']) < time()): ?>
                        <p class="password-note">
                            The registration deadline (<?= date('d M Y', strtotime($tournament['tournament_deadline'])) ?>) has passed &ndash;
                            this will be a late entry.
                        </p>
                    <?php endif; ?>

                    <button type="submit" class="add-btn">Register player</button>

                </section>

            </form>

        <?php endif; ?>

    </main>


    <script>
        (function () {

            const form = document.getElementById('registerForm');

            if (!form) {
                return;
            }

            const rules = <?= json_encode($categoryRules) ?>;
            const cutoff = <?= json_encode($hasCutoff ? date('Y-m-d', strtotime($cutoff)) : null) ?>;

            const existingBox = document.getElementById('existingPlayer');
            const newBox = document.getElementById('newPlayer');
            const category = document.getElementById('category');
            const search = document.getElementById('playerSearch');
            const options = document.querySelectorAll('.player-option');
            const noMatch = document.getElementById('noMatch');

            const mode = () => form.querySelector('input[name="player_mode"]:checked').value;

            // Age on the cut-off date from a yyyy-mm-dd date of birth
            function ageOn(dob) {
                if (!dob || !cutoff) {
                    return null;
                }

                const [by, bm, bd] = dob.split('-').map(Number);
                const [cy, cm, cd] = cutoff.split('-').map(Number);

                return cy - by - ((cm < bm || (cm === bm && cd < bd)) ? 1 : 0);
            }

            // Gender + age of the chosen / typed-in player, or null when nobody is chosen
            function currentPlayer() {
                if (mode() === 'new') {
                    const gender = document.getElementById('gender').value;
                    const dob = document.getElementById('dob').value;
                    return gender ? { gender: gender, age: ageOn(dob), hasDob: dob !== '' } : null;
                }

                const picked = form.querySelector('input[name="playerID"]:checked');

                if (!picked) {
                    return null;
                }

                const option = picked.closest('.player-option');

                return { gender: option.dataset.gender || null, age: option.dataset.age === '' ? null : Number(option.dataset.age), hasDob: option.dataset.age !== '' };
            }

            // Same rules as the server: gender must match, under the age on the cut-off date
            function problem(rule, player) {
                if (player.gender && rule.gender && rule.gender !== player.gender) {
                    return rule.gender === 'male' ? 'boys only' : 'girls only';
                }

                if (rule.maxAge !== null && cutoff && !player.hasDob) {
                    return 'date of birth needed';
                }

                if (rule.maxAge !== null && cutoff && player.age >= rule.maxAge) {
                    return 'over age';
                }

                return '';
            }

            function updateCategories() {
                const player = currentPlayer();
                let best = null;

                category.options[0].textContent = player ? 'Choose a category...' : 'Choose a player first...';

                rules.forEach(function (rule, index) {
                    const option = category.options[index + 1];
                    const reason = player ? problem(rule, player) : '';

                    option.disabled = !player || reason !== '';
                    option.textContent = option.textContent.replace(/ \((boys only|girls only|over age|date of birth needed|recommended)\)$/, '')
                        + (player && reason ? ' (' + reason + ')' : '');

                    // Youngest category the player still fits
                    if (player && reason === '' && (best === null || (rule.maxAge ?? 999) < (best.rule.maxAge ?? 999))) {
                        best = { rule: rule, option: option };
                    }
                });

                if (best) {
                    best.option.textContent += ' (recommended)';
                }

                if (category.selectedOptions[0] && category.selectedOptions[0].disabled) {
                    category.value = '';
                }

                if (player && category.value === '' && best) {
                    category.value = best.rule.code;
                }
            }

            function updateMode() {
                const isNew = mode() === 'new';

                existingBox.hidden = isNew;
                newBox.hidden = !isNew;

                ['first_name', 'last_name', 'gender', 'dob', 'nationality'].forEach(function (id) {
                    document.getElementById(id).required = isNew;
                });

                updateCategories();
            }

            function updatePayment() {
                document.getElementById('amountGroup').hidden = document.getElementById('payment_status').value !== 'PAID';
            }

            form.querySelectorAll('input[name="player_mode"], input[name="playerID"]').forEach(function (input) {
                input.addEventListener('change', input.name === 'player_mode' ? updateMode : updateCategories);
            });

            document.getElementById('gender').addEventListener('change', updateCategories);
            document.getElementById('dob').addEventListener('change', updateCategories);
            document.getElementById('payment_status').addEventListener('change', updatePayment);

            if (search) {
                search.addEventListener('input', function () {
                    const term = search.value.toLowerCase().trim();
                    let shown = 0;

                    options.forEach(function (option) {
                        option.hidden = !option.dataset.search.includes(term);
                        shown += option.hidden ? 0 : 1;
                    });

                    noMatch.hidden = shown > 0;
                });
            }

            updateMode();
            updatePayment();

        })();
    </script>

</body>

</html>
