<?php

require_once '../db.php';
require_once 'admin_auth.php';
require_once 'admin_countries.php';

requirePlatformAdmin();


/*
|--------------------------------------------------------------------------
| Admins
|--------------------------------------------------------------------------
| Platform Admins can add admins. A new admin is either a Platform Admin or a
| Tournament Organizer (creates tournaments and can do everything on
| the ones they created), saved in admins.admin_role
| (see admin_auth.php).
*/

// Next free ID: AD0001 -> AD0002
function nextAdminID($conn)
{
    $row = $conn->query("
        SELECT MAX(CAST(SUBSTRING(adminID, 3) AS UNSIGNED)) AS last_number
        FROM admins
        WHERE adminID REGEXP '^AD[0-9]+$'
    ")->fetch_assoc();

    return 'AD' . str_pad((string)((int)$row['last_number'] + 1), 4, '0', STR_PAD_LEFT);
}


function responsibilityText($role)
{
    return $role === 'platform'
        ? 'Everything, all tournaments'
        : 'Creates tournaments; everything on their own tournaments';
}


$form = [
    'admin_name' => '',
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

    foreach (['admin_name', 'admin_ic_passport', 'admin_gender', 'admin_contact', 'admin_email', 'admin_country', 'admin_type'] as $field) {
        $form[$field] = trim($_POST[$field] ?? '');
    }

    $password = $_POST['admin_password'] ?? '';
    $confirm = $_POST['admin_password_confirm'] ?? '';

    if ($form['admin_name'] === '') {
        $error = 'Please enter the admin\'s name.';
    } elseif (strlen($password) < 6) {
        $error = 'The password must be at least 6 characters.';
    } elseif (strlen($password) > 50) {
        $error = 'The password can be at most 50 characters.';
    } elseif ($password !== $confirm) {
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
    } elseif ($form['admin_type'] === 'organizer' && $form['admin_country'] === '') {
        $error = 'Please choose the Tournament Organizer\'s country.';
    }

    if ($error === '') {

        $adminID = nextAdminID($conn);
        $role = $form['admin_type'];   // 'platform' or 'organizer'
        $email = $form['admin_email'] !== '' ? $form['admin_email'] : null;
        $country = $form['admin_country'] !== '' ? $form['admin_country'] : null;
        $status = 'offline';
        $today = date('Y-m-d');

        $stmt = $conn->prepare("
            INSERT INTO admins (
                adminID, admin_password, admin_name, admin_ic_passport, admin_gender,
                admin_contact, admin_email, admin_country, admin_register, admin_status, admin_role
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "sssssssssss",
            $adminID,
            $password,
            $form['admin_name'],
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
            header("Location: admin_admins.php?added=" . urlencode($adminID));
            exit;
        }

        $error = 'The admin could not be saved: ' . $stmt->error;
        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| Admin list
|--------------------------------------------------------------------------
*/

$admins = $conn->query("
    SELECT adminID, admin_name, admin_email, admin_contact, admin_country, admin_register, admin_role,
           (SELECT COUNT(*) FROM tournament t WHERE t.creatorID = a.adminID COLLATE utf8mb4_unicode_ci) AS tournaments_created
    FROM admins a
    ORDER BY adminID ASC
")->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admins</title>

    <link href="./assets/style.css" rel="stylesheet" type="text/css">
    <link href="./assets/admins.css?v=<?= filemtime(__DIR__ . '/assets/admins.css') ?>" rel="stylesheet" type="text/css">

</head>

<body>

    <?php require_once 'admin_navbar.php'; ?>


    <main class="admins-page">

        <div class="page-title">
            <h1>Admins</h1>
            <p>Add admins. Tournament Organizers manage only the tournaments they create.</p>
        </div>


        <?php if (isset($_GET['added'])): ?>
            <div class="success-message">
                Admin <strong><?= htmlspecialchars($_GET['added']) ?></strong> added. They can now log in with this ID and the password you set.
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>


        <div class="admins-layout">


            <!-- ==========================================
                 ADMIN LIST
            =========================================== -->

            <section class="card">

                <h2>All admins <span class="count"><?= count($admins) ?></span></h2>

                <div class="table-wrap">

                    <table class="admins-table">

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Country</th>
                                <th>Can do</th>
                                <th class="center">Tournaments created</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($admins as $admin): ?>

                                <?php $isPlatform = $admin['admin_role'] === 'platform'; ?>

                                <tr>
                                    <td class="admin-id">
                                        <?= htmlspecialchars($admin['adminID']) ?>
                                        <?php if ($admin['adminID'] === $_SESSION['userid']): ?>
                                            <span class="you">you</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($admin['admin_name']) ?>
                                        <small><?= htmlspecialchars($admin['admin_email'] ?: $admin['admin_contact']) ?></small>
                                    </td>
                                    <td>
                                        <span class="role <?= $isPlatform ? 'role-platform' : 'role-organizer' ?>">
                                            <?= $isPlatform ? 'Platform Admin' : 'Tournament Organizer' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($admin['admin_country'] ?: '-') ?></td>
                                    <td class="responsibilities">
                                        <?= htmlspecialchars(responsibilityText($admin['admin_role'])) ?>
                                    </td>
                                    <td class="center"><?= (int)$admin['tournaments_created'] ?></td>
                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </section>


            <!-- ==========================================
                 ADD ADMIN
            =========================================== -->

            <section class="card">

                <h2>Add admin</h2>

                <p class="new-id">New admin ID: <strong><?= htmlspecialchars(nextAdminID($conn)) ?></strong></p>

                <form method="POST" action="admin_admins.php" class="add-form">

                    <input type="hidden" name="action" value="add_admin">

                    <div class="form-group">
                        <label for="admin_name">Name <span class="required">*</span></label>
                        <input type="text" id="admin_name" name="admin_name" maxlength="255" required
                               value="<?= htmlspecialchars($form['admin_name']) ?>">
                    </div>

                    <div class="form-row">

                        <div class="form-group">
                            <label for="admin_password">Password <span class="required">*</span></label>
                            <input type="password" id="admin_password" name="admin_password" minlength="6" maxlength="50" required>
                        </div>

                        <div class="form-group">
                            <label for="admin_password_confirm">Confirm password <span class="required">*</span></label>
                            <input type="password" id="admin_password_confirm" name="admin_password_confirm" minlength="6" maxlength="50" required>
                        </div>

                    </div>

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


</body>

</html>
