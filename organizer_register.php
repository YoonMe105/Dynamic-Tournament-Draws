<?php

require_once './db.php';
require_once './admin/admin_countries.php';
require_once './admin/admin_id_functions.php';


/*
|--------------------------------------------------------------------------
| Apply as Tournament Organizer
|--------------------------------------------------------------------------
| Anyone can register here as a Tournament Organizer (saved in admins). They
| can log in straight away with their new admin ID and the password they chose.
*/

$form = [
    'admin_name' => '',
    'admin_organization' => '',
    'admin_ic_passport' => '',
    'admin_gender' => '',
    'admin_contact' => '',
    'admin_email' => '',
    'admin_country' => '',
];

$error = '';
$newAdminID = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach (array_keys($form) as $field) {
        $form[$field] = trim($_POST[$field] ?? '');
    }

    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($form['admin_name'] === '') {
        $error = 'Please enter your name.';
    } elseif ($form['admin_organization'] === '') {
        $error = 'Please enter your organization\'s name.';
    } elseif ($form['admin_email'] === '' || !filter_var($form['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($form['admin_contact'] === '') {
        $error = 'Please enter a contact number.';
    } elseif ($form['admin_ic_passport'] === '') {
        $error = 'Please enter your IC or passport number.';
    } elseif (!in_array($form['admin_gender'], ['male', 'female'], true)) {
        $error = 'Please choose a gender.';
    } elseif (!in_array($form['admin_country'], $tournament_countries, true)) {
        $error = 'Please choose your country from the list.';
    } elseif (strlen($password) < 6) {
        $error = 'The password must be at least 6 characters.';
    } elseif (strlen($password) > 50) {
        $error = 'The password can be at most 50 characters.';
    } elseif ($password !== $confirm) {
        $error = 'The two passwords don\'t match.';
    }

    // One application / account per email
    if ($error === '') {

        $stmt = $conn->prepare("SELECT adminID FROM admins WHERE admin_email = ? LIMIT 1");
        $stmt->bind_param("s", $form['admin_email']);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            $error = 'This email is already registered.';
        }

        $stmt->close();
    }

    if ($error === '') {

        $adminID = nextAdminID($conn);
        $today = date('Y-m-d');
        $status = 'offline';
        $role = 'organizer';
        $active = 'active';

        $stmt = $conn->prepare("
            INSERT INTO admins (
                adminID, admin_password, admin_name, admin_organization, admin_ic_passport, admin_gender,
                admin_contact, admin_email, admin_country, admin_register, admin_status, admin_role, admin_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "sssssssssssss",
            $adminID,
            $password,
            $form['admin_name'],
            $form['admin_organization'],
            $form['admin_ic_passport'],
            $form['admin_gender'],
            $form['admin_contact'],
            $form['admin_email'],
            $form['admin_country'],
            $today,
            $status,
            $role,
            $active
        );

        if ($stmt->execute()) {
            $newAdminID = $adminID;
        } else {
            $error = 'Your account could not be created. Please try again.';
        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register as Tournament Organizer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <link href="./player/assets/css/signin.css?v=<?= filemtime(__DIR__ . '/player/assets/css/signin.css') ?>" rel="stylesheet" type="text/css">
</head>

<body>

    <div class="signin-container">
        <div class="signin-card">

            <?php if ($newAdminID !== ''): ?>

                <!-- Registered: can log in now -->
                <div class="application-sent">
                    <span class="sent-icon"><i class="fa-solid fa-circle-check"></i></span>
                    <h2>Account created</h2>
                    <p>Welcome, <?= htmlspecialchars($form['admin_name']) ?>. You can now log in and create tournaments.</p>
                    <p class="your-id">Your user ID: <strong><?= htmlspecialchars($newAdminID) ?></strong></p>
                    <p class="hint">Keep this ID. You log in with it and the password you chose.</p>
                    <a class="signin-btn as-link" href="login.php">Go to Log In</a>
                </div>

            <?php else: ?>

                <h2>Register as Organizer</h2>
                <p class="subtitle">Create an account to host tournaments on T_Software.</p>

                <?php if ($error !== ''): ?>
                    <div class="message error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form action="organizer_register.php" method="post" id="signinForm">

                    <div class="form-group">
                        <label for="admin_name">Full Name</label>
                        <input type="text" id="admin_name" name="admin_name" maxlength="255" required
                               value="<?= htmlspecialchars($form['admin_name']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="admin_organization">Organization's Name</label>
                        <input type="text" id="admin_organization" name="admin_organization" maxlength="255" required
                               placeholder="e.g. club, school or association"
                               value="<?= htmlspecialchars($form['admin_organization']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="admin_country">Country</label>
                        <select id="admin_country" name="admin_country" required>
                            <option value="">Select country</option>
                            <?php foreach ($tournament_countries as $country): ?>
                                <option value="<?= htmlspecialchars($country) ?>" <?= $form['admin_country'] === $country ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($country) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="admin_email">Email</label>
                        <input type="email" id="admin_email" name="admin_email" maxlength="255" required
                               value="<?= htmlspecialchars($form['admin_email']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="admin_contact">Contact Number</label>
                        <input type="text" id="admin_contact" name="admin_contact" maxlength="50" required
                               value="<?= htmlspecialchars($form['admin_contact']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="admin_ic_passport">IC / Passport No.</label>
                        <input type="text" id="admin_ic_passport" name="admin_ic_passport" maxlength="50" required
                               value="<?= htmlspecialchars($form['admin_ic_passport']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="admin_gender">Gender</label>
                        <select id="admin_gender" name="admin_gender" required>
                            <option value="">Select gender</option>
                            <option value="male" <?= $form['admin_gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= $form['admin_gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" minlength="6" maxlength="50" placeholder="At least 6 characters" required>
                            <button type="button" class="eye-btn" id="togglePassword" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" minlength="6" maxlength="50" required>
                            <button type="button" class="eye-btn" id="toggleConfirmPassword" aria-label="Show confirm password"><i class="fa-solid fa-eye"></i></button>
                        </div>
                        <small id="passwordMessage"></small>
                    </div>

                    <button type="submit" class="signin-btn">Create Account</button>

                </form>

                <p class="signin-links">Already have an account? <a href="login.php">Log in</a></p>

                <script src="./player/assets/js/signin.js?v=<?= filemtime(__DIR__ . '/player/assets/js/signin.js') ?>"></script>

            <?php endif; ?>

        </div>
    </div>

</body>

</html>
