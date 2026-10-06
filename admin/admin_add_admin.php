<?php

require_once '../db.php';
require_once 'admin_auth.php';
require_once 'admin_countries.php';
require_once 'admin_id_functions.php';

requirePlatformAdmin();


/*
|--------------------------------------------------------------------------
| Add Admin
|--------------------------------------------------------------------------
| A new admin is either a Platform Admin or a Tournament Organizer (creates
| tournaments and can do everything on the ones they created), saved in
| admins.admin_role (see admin_auth.php). The list is on admin_admins.php.
*/

$form = [
    'admin_name' => '',
    'admin_organization' => '',
    'admin_ic_passport' => '',
    'admin_gender' => '',
    'admin_contact' => '',
    'admin_email' => '',
    'admin_country' => '',
    'admin_type' => 'organizer',
];

$error = '';


/*
|--------------------------------------------------------------------------
| Add admin
|--------------------------------------------------------------------------
*/

$action = $_POST['action'] ?? '';

/*
| Add admin
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_admin') {

    foreach (['admin_name', 'admin_organization', 'admin_ic_passport', 'admin_gender', 'admin_contact', 'admin_email', 'admin_country', 'admin_type'] as $field) {
        $form[$field] = trim($_POST[$field] ?? '');
    }

    $password = $_POST['admin_password'] ?? '';
    $confirm = $_POST['admin_password_confirm'] ?? '';

    // Tournament Organizers start with their admin ID as password (set below)
    $isOrganizer = $form['admin_type'] === 'organizer';

    if ($form['admin_name'] === '') {
        $error = 'Please enter the admin\'s name.';
    } elseif (!$isOrganizer && strlen($password) < 6) {
        $error = 'The password must be at least 6 characters.';
    } elseif (!$isOrganizer && strlen($password) > 50) {
        $error = 'The password can be at most 50 characters.';
    } elseif (!$isOrganizer && $password !== $confirm) {
        $error = 'The two passwords don\'t match.';
    } elseif ($form['admin_ic_passport'] === '') {
        $error = 'Please enter the IC or passport number.';
    } elseif (!in_array($form['admin_gender'], ['male', 'female'], true)) {
        $error = 'Please choose a gender.';
    } elseif ($form['admin_contact'] === '') {
        $error = 'Please enter a contact number.';
    } elseif ($form['admin_email'] !== '' && !filter_var($form['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address (or leave it empty).';
    } elseif (!in_array($form['admin_type'], ['platform', 'organizer'], true)) {
        $error = 'Please choose the admin type.';
    } elseif ($form['admin_country'] !== '' && !in_array($form['admin_country'], $tournament_countries, true)) {
        $error = 'Please choose a country from the list.';
    } elseif ($form['admin_type'] === 'organizer' && $form['admin_organization'] === '') {
        $error = 'Please enter the Tournament Organizer\'s organization name.';
    } elseif ($form['admin_type'] === 'organizer' && $form['admin_country'] === '') {
        $error = 'Please choose the Tournament Organizer\'s country.';
    }

    if ($error === '') {

        $adminID = nextAdminID($conn);

        // Default password for Tournament Organizers = their admin ID (e.g. AD0003)
        if ($isOrganizer) {
            $password = $adminID;
        }
        $role = $form['admin_type'];   // 'platform' or 'organizer'
        $email = $form['admin_email'] !== '' ? $form['admin_email'] : null;
        $country = $form['admin_country'] !== '' ? $form['admin_country'] : null;
        // Only Tournament Organizers have an organization
        $organization = $role === 'organizer' && $form['admin_organization'] !== '' ? $form['admin_organization'] : null;
        $status = 'offline';
        $today = date('Y-m-d');

        $stmt = $conn->prepare("
            INSERT INTO admins (
                adminID, admin_password, admin_name, admin_organization, admin_ic_passport, admin_gender,
                admin_contact, admin_email, admin_country, admin_register, admin_status, admin_role
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssssssssssss",
            $adminID,
            $password,
            $form['admin_name'],
            $organization,
            $form['admin_ic_passport'],
            $form['admin_gender'],
            $form['admin_contact'],
            $email,
            $country,
            $today,
            $status,
            $role
        );

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: admin_admins.php?added=" . urlencode($adminID) . ($isOrganizer ? "&default_password=1" : ""));
            exit;
        }

        $error = 'The admin could not be saved: ' . $stmt->error;
        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Admin</title>

    <link href="./assets/style.css" rel="stylesheet" type="text/css">
    <link href="./assets/admins.css?v=<?= filemtime(__DIR__ . '/assets/admins.css') ?>" rel="stylesheet" type="text/css">

</head>

<body>

    <?php require_once 'admin_navbar.php'; ?>


    <main class="admins-page">

        <a href="admin_admins.php" class="back-link">&larr; Back to Admins</a>

        <div class="page-title">
            <h1>Add Admin</h1>
            <p>Tournament Organizers manage only the tournaments they create.</p>
        </div>


        <?php if ($error !== ''): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>


        <div class="form-page">

        <section class="card">

            <p class="new-id">New admin ID: <strong><?= htmlspecialchars(nextAdminID($conn)) ?></strong></p>

            <form method="POST" action="admin_add_admin.php" class="add-form">

                <input type="hidden" name="action" value="add_admin">

                <div class="form-group">
                    <label for="admin_name">Name <span class="required">*</span></label>
                    <input type="text" id="admin_name" name="admin_name" maxlength="255" required
                           value="<?= htmlspecialchars($form['admin_name']) ?>">
                </div>

                <!-- Tournament Organizers only (hidden for Platform Admins) -->
                <div class="form-group" id="organizationGroup">
                    <label for="admin_organization">Organization's name <span class="required">*</span></label>
                    <input type="text" id="admin_organization" name="admin_organization" maxlength="255"
                           placeholder="e.g. Squash Racquets Association of Kuala Lumpur"
                           value="<?= htmlspecialchars($form['admin_organization']) ?>">
                </div>

                <!-- Platform Admins only: organizers get their admin ID as password -->
                <div class="form-row" id="passwordGroup">

                    <div class="form-group">
                        <label for="admin_password">Password <span class="required">*</span></label>
                        <input type="password" id="admin_password" name="admin_password" minlength="6" maxlength="50" required>
                    </div>

                    <div class="form-group">
                        <label for="admin_password_confirm">Confirm password <span class="required">*</span></label>
                        <input type="password" id="admin_password_confirm" name="admin_password_confirm" minlength="6" maxlength="50" required>
                    </div>

                </div>

                <p class="password-note" id="passwordNote">
                    <strong>Password:</strong> a Tournament Organizer's first password is their admin ID
                    (<strong><?= htmlspecialchars(nextAdminID($conn)) ?></strong>). They can change it on My Profile after logging in.
                </p>

                <div class="form-row">

                    <div class="form-group">
                        <label for="admin_ic_passport">IC / Passport no. <span class="required">*</span></label>
                        <input type="text" id="admin_ic_passport" name="admin_ic_passport" maxlength="50" required
                               value="<?= htmlspecialchars($form['admin_ic_passport']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="admin_gender">Gender <span class="required">*</span></label>
                        <select id="admin_gender" name="admin_gender" required>
                            <option value="">Select gender</option>
                            <option value="male" <?= $form['admin_gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= $form['admin_gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group">
                        <label for="admin_contact">Contact no. <span class="required">*</span></label>
                        <input type="text" id="admin_contact" name="admin_contact" maxlength="50" required
                               value="<?= htmlspecialchars($form['admin_contact']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="admin_email">Email</label>
                        <input type="email" id="admin_email" name="admin_email" maxlength="255"
                               value="<?= htmlspecialchars($form['admin_email']) ?>">
                    </div>

                </div>

                <div class="form-group">
                    <label for="admin_country">Country <span class="required">*</span> <small>(required for Tournament Organizers)</small></label>
                    <select id="admin_country" name="admin_country">
                        <option value="">Select country</option>
                        <?php foreach ($tournament_countries as $country): ?>
                            <option value="<?= htmlspecialchars($country) ?>" <?= $form['admin_country'] === $country ? 'selected' : '' ?>>
                                <?= htmlspecialchars($country) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">

                    <label>Admin type <span class="required">*</span></label>

                    <label class="choice">
                        <input type="radio" name="admin_type" value="organizer" <?= $form['admin_type'] === 'organizer' ? 'checked' : '' ?>>
                        <span>
                            <strong>Tournament Organizer</strong>
                            <small>Creates tournaments and can do everything on them (registrations, seeding, draws,
                            rankings, editing) &ndash; but can't see or change anyone else's tournaments.</small>
                        </span>
                    </label>

                    <label class="choice">
                        <input type="radio" name="admin_type" value="platform" <?= $form['admin_type'] === 'platform' ? 'checked' : '' ?>>
                        <span>
                            <strong>Platform Admin</strong>
                            <small>Everything: registrations, seeding, draws, rankings and adding admins.</small>
                        </span>
                    </label>

                </div>

                <button type="submit" class="add-btn">Add Admin</button>

            </form>

        </section>

        </div>

    </main>


    <script>
        // Organizers: organization's name asked, password = admin ID.
        // Platform Admins: no organization, password typed in.
        (function () {

            const group = document.getElementById('organizationGroup');
            const input = document.getElementById('admin_organization');
            const passwordGroup = document.getElementById('passwordGroup');
            const passwordNote = document.getElementById('passwordNote');
            const passwordInputs = passwordGroup.querySelectorAll('input');

            function update() {
                const type = document.querySelector('input[name="admin_type"]:checked');
                const isOrganizer = !type || type.value === 'organizer';

                group.hidden = !isOrganizer;
                input.required = isOrganizer;

                passwordGroup.hidden = isOrganizer;
                passwordNote.hidden = !isOrganizer;

                passwordInputs.forEach(function (field) {
                    field.required = !isOrganizer;
                    field.disabled = isOrganizer;
                });
            }

            document.querySelectorAll('input[name="admin_type"]').forEach(function (radio) {
                radio.addEventListener('change', update);
            });

            update();

        })();
    </script>

</body>

</html>
