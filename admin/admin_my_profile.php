<?php

require_once '../db.php';
require_once 'admin_auth.php';
require_once 'admin_countries.php';

// Every admin (Platform Admin or Tournament Organizer) can edit their own details


/*
|--------------------------------------------------------------------------
| My Profile
|--------------------------------------------------------------------------
| The logged-in admin's own details, password and photo. The admin ID and
| role can't be changed here (only a Platform Admin manages roles).
*/

$adminID = $_SESSION['userid'];

function loadAdmin($conn, $adminID)
{
    $stmt = $conn->prepare("SELECT * FROM admins WHERE adminID = ?");
    $stmt->bind_param("s", $adminID);
    $stmt->execute();

    $admin = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return $admin;
}


// Photo path as saved -> path usable from this folder, or null when the file is missing
function adminPhotoSrc($path)
{
    $path = trim((string)$path);

    if ($path === '' || strtoupper($path) === 'NULL') {
        return null;
    }

    foreach ([$path, '../' . ltrim($path, './')] as $candidate) {
        if (is_file(__DIR__ . '/' . $candidate)) {
            return $candidate;
        }
    }

    return null;
}


$admin = loadAdmin($conn, $adminID);

$form = [
    'admin_name' => $admin['admin_name'],
    'admin_email' => (string)$admin['admin_email'],
    'admin_contact' => $admin['admin_contact'],
    'admin_ic_passport' => $admin['admin_ic_passport'],
    'admin_gender' => strtolower(trim($admin['admin_gender'])),
    'admin_country' => trim((string)$admin['admin_country']),
];

$profileError = '';
$passwordError = '';

$action = $_POST['action'] ?? '';


/*
|--------------------------------------------------------------------------
| Save details (and photo)
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'profile') {

    foreach (array_keys($form) as $field) {
        $form[$field] = trim($_POST[$field] ?? '');
    }

    if ($form['admin_name'] === '') {
        $profileError = 'Please enter your name.';
    } elseif ($form['admin_email'] !== '' && !filter_var($form['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $profileError = 'Please enter a valid email address (or leave it empty).';
    } elseif ($form['admin_contact'] === '') {
        $profileError = 'Please enter your contact number.';
    } elseif ($form['admin_ic_passport'] === '') {
        $profileError = 'Please enter your IC or passport number.';
    } elseif (!in_array($form['admin_gender'], ['male', 'female'], true)) {
        $profileError = 'Please choose your gender.';
    } elseif ($form['admin_country'] !== '' && !in_array($form['admin_country'], $tournament_countries, true)) {
        $profileError = 'Please choose a country from the list.';
    } elseif ($admin['admin_role'] === 'organizer' && $form['admin_country'] === '') {
        $profileError = 'Please choose your country.';
    }


    // New photo (optional)
    $newPhoto = null;

    if ($profileError === '' && isset($_FILES['admin_profile']) && $_FILES['admin_profile']['error'] !== UPLOAD_ERR_NO_FILE) {

        $extension = strtolower(pathinfo($_FILES['admin_profile']['name'], PATHINFO_EXTENSION));

        if ($_FILES['admin_profile']['error'] !== UPLOAD_ERR_OK) {
            $profileError = 'The photo could not be uploaded.';
        } elseif (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $profileError = 'The photo must be JPG, JPEG, PNG or WEBP.';
        } elseif ($_FILES['admin_profile']['size'] > 5 * 1024 * 1024) {
            $profileError = 'The photo must be smaller than 5 MB.';
        } else {

            $uploadDir = __DIR__ . '/../uploads/admins/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = 'admin_' . preg_replace('/[^A-Za-z0-9]/', '', $adminID) . '_' . uniqid() . '.' . $extension;

            if (move_uploaded_file($_FILES['admin_profile']['tmp_name'], $uploadDir . $fileName)) {
                $newPhoto = '../uploads/admins/' . $fileName;
            } else {
                $profileError = 'The photo could not be saved.';
            }
        }
    }

    if ($profileError === '') {

        $email = $form['admin_email'] !== '' ? $form['admin_email'] : null;
        $country = $form['admin_country'] !== '' ? $form['admin_country'] : null;
        $photo = $newPhoto ?? $admin['admin_profile'];

        $stmt = $conn->prepare("
            UPDATE admins
            SET admin_name = ?, admin_email = ?, admin_contact = ?, admin_ic_passport = ?,
                admin_gender = ?, admin_country = ?, admin_profile = ?
            WHERE adminID = ?
        ");

        $stmt->bind_param(
            "ssssssss",
            $form['admin_name'],
            $email,
            $form['admin_contact'],
            $form['admin_ic_passport'],
            $form['admin_gender'],
            $country,
            $photo,
            $adminID
        );

        $stmt->execute();
        $stmt->close();

        // Remove the old photo if it was one uploaded here
        if ($newPhoto !== null && strpos((string)$admin['admin_profile'], '../uploads/admins/') === 0) {
            @unlink(__DIR__ . '/' . $admin['admin_profile']);
        }

        $_SESSION['admin_name'] = $form['admin_name'];

        header("Location: admin_my_profile.php?saved=1");
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Change password
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'password') {

    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!hash_equals((string)$admin['admin_password'], $current)) {
        $passwordError = 'Your current password is not correct.';
    } elseif (strlen($new) < 6) {
        $passwordError = 'The new password must be at least 6 characters.';
    } elseif (strlen($new) > 50) {
        $passwordError = 'The new password can be at most 50 characters.';
    } elseif ($new !== $confirm) {
        $passwordError = 'The two new passwords don\'t match.';
    } elseif ($new === $current) {
        $passwordError = 'The new password must be different from the current one.';
    } else {

        $stmt = $conn->prepare("UPDATE admins SET admin_password = ? WHERE adminID = ?");
        $stmt->bind_param("ss", $new, $adminID);
        $stmt->execute();
        $stmt->close();

        header("Location: admin_my_profile.php?password=1");
        exit;
    }
}


$photoSrc = adminPhotoSrc($admin['admin_profile']);
$initials = strtoupper(implode('', array_map(function ($word) {
    return substr($word, 0, 1);
}, array_slice(preg_split('/\s+/', trim($admin['admin_name'])), 0, 2))));

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile</title>

    <link href="./assets/style.css" rel="stylesheet" type="text/css">
    <link href="./assets/my_profile.css?v=<?= filemtime(__DIR__ . '/assets/my_profile.css') ?>" rel="stylesheet" type="text/css">

</head>

<body>

    <?php require_once 'admin_navbar.php'; ?>


    <main class="profile-page">

        <div class="page-title">
            <h1>My Profile</h1>
            <p>Update your personal information and password.</p>
        </div>


        <?php if (isset($_GET['saved'])): ?>
            <div class="success-message">Your details have been saved.</div>
        <?php endif; ?>

        <?php if (isset($_GET['password'])): ?>
            <div class="success-message">Your password has been changed. Use the new one next time you log in.</div>
        <?php endif; ?>


        <div class="profile-layout">


            <!-- ==========================================
                 SUMMARY
            =========================================== -->

            <section class="card summary-card">

                <div class="avatar">
                    <?php if ($photoSrc): ?>
                        <img src="<?= htmlspecialchars($photoSrc) ?>" alt="Profile photo" id="avatarImage">
                    <?php else: ?>
                        <span id="avatarInitials"><?= htmlspecialchars($initials) ?></span>
                        <img src="" alt="Profile photo" id="avatarImage" hidden>
                    <?php endif; ?>
                </div>

                <h2><?= htmlspecialchars($admin['admin_name']) ?></h2>

                <span class="role <?= $admin['admin_role'] === 'platform' ? 'role-platform' : 'role-organizer' ?>">
                    <?= $admin['admin_role'] === 'platform' ? 'Platform Admin' : 'Tournament Organizer' ?>
                </span>

                <ul class="summary-list">
                    <li><span>Admin ID</span> <?= htmlspecialchars($admin['adminID']) ?></li>
                    <li><span>Member since</span> <?= date('d M Y', strtotime($admin['admin_register'])) ?></li>
                    <?php if (trim((string)$admin['admin_country']) !== ''): ?>
                        <li><span>Country</span> <?= htmlspecialchars(trim($admin['admin_country'])) ?></li>
                    <?php endif; ?>
                </ul>

                <p class="note">Your admin ID and role can only be changed by a Platform Admin.</p>

            </section>


            <div class="forms-column">


                <!-- ==========================================
                     PERSONAL INFORMATION
                =========================================== -->

                <section class="card">

                    <h2>Personal information</h2>

                    <?php if ($profileError !== ''): ?>
                        <div class="error-message"><?= htmlspecialchars($profileError) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="admin_my_profile.php" enctype="multipart/form-data" class="profile-form">

                        <input type="hidden" name="action" value="profile">

                        <div class="form-group">
                            <label for="admin_name">Full name <span class="required">*</span></label>
                            <input type="text" id="admin_name" name="admin_name" maxlength="255" required
                                   value="<?= htmlspecialchars($form['admin_name']) ?>">
                        </div>

                        <div class="form-row">

                            <div class="form-group">
                                <label for="admin_email">Email</label>
                                <input type="email" id="admin_email" name="admin_email" maxlength="255"
                                       value="<?= htmlspecialchars($form['admin_email']) ?>">
                            </div>

                            <div class="form-group">
                                <label for="admin_contact">Contact no. <span class="required">*</span></label>
                                <input type="text" id="admin_contact" name="admin_contact" maxlength="50" required
                                       value="<?= htmlspecialchars($form['admin_contact']) ?>">
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
                                <label for="admin_country">
                                    Country <?= $admin['admin_role'] === 'organizer' ? '<span class="required">*</span>' : '' ?>
                                </label>
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
                                <label for="admin_profile">Profile photo</label>
                                <input type="file" id="admin_profile" name="admin_profile" accept=".jpg,.jpeg,.png,.webp">
                                <small>JPG, PNG or WEBP, up to 5 MB</small>
                            </div>

                        </div>

                        <button type="submit" class="save-btn">Save details</button>

                    </form>

                </section>


                <!-- ==========================================
                     PASSWORD
                =========================================== -->

                <section class="card">

                    <h2>Change password</h2>

                    <?php if ($passwordError !== ''): ?>
                        <div class="error-message"><?= htmlspecialchars($passwordError) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="admin_my_profile.php" class="profile-form">

                        <input type="hidden" name="action" value="password">

                        <div class="form-group">
                            <label for="current_password">Current password <span class="required">*</span></label>
                            <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                        </div>

                        <div class="form-row">

                            <div class="form-group">
                                <label for="new_password">New password <span class="required">*</span></label>
                                <input type="password" id="new_password" name="new_password" minlength="6" maxlength="50" required autocomplete="new-password">
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm new password <span class="required">*</span></label>
                                <input type="password" id="confirm_password" name="confirm_password" minlength="6" maxlength="50" required autocomplete="new-password">
                            </div>

                        </div>

                        <button type="submit" class="save-btn secondary">Change password</button>

                    </form>

                </section>

            </div>

        </div>

    </main>


    <script>
        // Show the chosen photo straight away
        document.getElementById('admin_profile').addEventListener('change', function () {

            if (!this.files || !this.files[0]) {
                return;
            }

            const reader = new FileReader();

            reader.onload = function (event) {
                const image = document.getElementById('avatarImage');
                const initials = document.getElementById('avatarInitials');

                image.src = event.target.result;
                image.hidden = false;

                if (initials) {
                    initials.hidden = true;
                }
            };

            reader.readAsDataURL(this.files[0]);
        });
    </script>

</body>

</html>
