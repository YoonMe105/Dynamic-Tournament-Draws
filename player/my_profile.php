<?php
require_once "player_functions.php";

$player = getPlayer($conn, $playerID);

if (!$player) {
    die("Player not found.");
}


/*
|--------------------------------------------------------------------------
| Options
|--------------------------------------------------------------------------
*/

$nationalities = [
    'Afghan', 'Albanian', 'Algerian', 'American', 'Australian', 'Bangladeshi',
    'British', 'Bruneian', 'Cambodian', 'Canadian', 'Chinese', 'Egyptian',
    'Filipino', 'French', 'German', 'Hong Konger', 'Indian', 'Indonesian',
    'Japanese', 'Korean', 'Macanese', 'Malaysian', 'Myanmar', 'Nepalese',
    'New Zealander', 'Pakistani', 'Singaporean', 'Sri Lankan', 'Taiwanese',
    'Thai', 'Vietnamese'
];

// Nationality is shown and saved in capitals
$nationalities = array_map('strtoupper', $nationalities);

// Keep a value that isn't in the list (e.g. older "australia") selectable
$currentNationality = strtoupper(trim($player['player_nationality'] ?? ''));

$nationalityMatch = null;

foreach ($nationalities as $nationality) {
    if (strcasecmp($nationality, $currentNationality) === 0) {
        $nationalityMatch = $nationality;
    }
}

if ($currentNationality !== '' && $nationalityMatch === null) {
    $nationalities[] = $currentNationality;
    $nationalityMatch = $currentNationality;
}


/*
|--------------------------------------------------------------------------
| Form Submission
|--------------------------------------------------------------------------
| action=profile  -> update profile details and photo
| action=password -> change password
*/

$error_message = '';
$password_error = '';

$form = $player;
$form['player_nationality'] = $nationalityMatch ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'profile') {

    $fields = [
        'player_first_name', 'player_last_name', 'player_nationalid', 'player_passport',
        'player_dob', 'player_gender', 'player_nationality', 'player_contact', 'player_email',
        'asf_member_no', 'spin_number', 'national_ranking', 'ajss_ranking', 'world_ranking'
    ];

    foreach ($fields as $field) {
        $form[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    $dobTime = strtotime($form['player_dob']);

    if ($form['player_first_name'] === '' || $form['player_last_name'] === '') {

        $error_message = 'First name and last name are required.';

    } elseif ($form['player_email'] === '' || !filter_var($form['player_email'], FILTER_VALIDATE_EMAIL)) {

        $error_message = 'Please enter a valid email address.';

    } elseif ($dobTime === false || $dobTime > time()) {

        $error_message = 'Please enter a valid date of birth.';

    } elseif (!in_array($form['player_gender'], ['Male', 'Female'], true)) {

        $error_message = 'Please select your gender.';

    } elseif (!in_array($form['player_nationality'], $nationalities, true)) {

        $error_message = 'Please select your nationality.';
    }


    /*
    | Rankings: a whole number (1, 2, 3 ...) or left empty
    */

    $rankingLabels = [
        'national_ranking' => 'National ranking',
        'ajss_ranking' => 'AJSS ranking',
        'world_ranking' => '(PSA) World ranking'
    ];

    foreach ($rankingLabels as $field => $label) {

        if ($error_message === '' && $form[$field] !== '' && !preg_match('/^[1-9][0-9]{0,5}$/', $form[$field])) {
            $error_message = $label . ' must be a whole number, e.g. 12 (or leave it empty).';
        }
    }


    /*
    | Email must not belong to another player
    */

    if ($error_message === '') {

        $stmt = $conn->prepare("SELECT playerID FROM players WHERE player_email = ? AND playerID <> ? LIMIT 1");
        $stmt->bind_param("ss", $form['player_email'], $playerID);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            $error_message = 'This email is already used by another player.';
        }

        $stmt->close();
    }


    /*
    | Profile Photo
    */

    $newPhoto = null;

    if ($error_message === '' && isset($_FILES['player_profile']) && $_FILES['player_profile']['error'] !== UPLOAD_ERR_NO_FILE) {

        $extension = strtolower(pathinfo($_FILES['player_profile']['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {

            $error_message = 'Profile photo must be JPG, JPEG, PNG or WEBP.';

        } else {

            [$fileName, $error_message] = uploadFile('player_profile', 'players', 'player');

            if ($fileName !== null) {
                $newPhoto = '../uploads/players/' . $fileName;
            }
        }
    }


    /*
    | Update Player
    */

    if ($error_message === '') {

        $fullName = $form['player_first_name'] . ' ' . $form['player_last_name'];
        $photo = $newPhoto ?? $player['player_profile'];
        $nationalID = $form['player_nationalid'] !== '' ? $form['player_nationalid'] : null;
        $dob = date('Y-m-d', $dobTime);

        $stmt = $conn->prepare("
            UPDATE players
            SET player_full_name = ?,
                player_first_name = ?,
                player_last_name = ?,
                player_nationalid = ?,
                player_passport = ?,
                player_dob = ?,
                player_gender = ?,
                player_nationality = ?,
                player_contact = ?,
                player_email = ?,
                asf_member_no = ?,
                spin_number = ?,
                national_ranking = ?,
                ajss_ranking = ?,
                world_ranking = ?,
                player_profile = ?
            WHERE playerID = ?
        ");

        if (!$stmt) {
            die("Database error: " . $conn->error);
        }

        $stmt->bind_param(
            "sssssssssssssssss",
            $fullName,
            $form['player_first_name'],
            $form['player_last_name'],
            $nationalID,
            $form['player_passport'],
            $dob,
            $form['player_gender'],
            $form['player_nationality'],
            $form['player_contact'],
            $form['player_email'],
            $form['asf_member_no'],
            $form['spin_number'],
            $form['national_ranking'],
            $form['ajss_ranking'],
            $form['world_ranking'],
            $photo,
            $playerID
        );

        if ($stmt->execute()) {

            $stmt->close();

            // Remove the old photo if it was one uploaded here
            if ($newPhoto !== null && strpos((string) $player['player_profile'], '../uploads/players/') === 0) {
                @unlink('../uploads/players/' . basename($player['player_profile']));
            }

            $_SESSION["firstname"] = $form['player_first_name'];
            $_SESSION["lastname"] = $form['player_last_name'];

            header("Location: my_profile.php?updated=1");
            exit();
        }

        $error_message = 'Something went wrong. Please try again.';

        $stmt->close();

        if ($newPhoto !== null) {
            @unlink('../uploads/players/' . basename($newPhoto));
        }
    }
}


/*
|--------------------------------------------------------------------------
| Change Password
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'password') {

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($currentPassword !== $player['player_password']) {

        $password_error = 'Current password is incorrect.';

    } elseif (strlen($newPassword) < 6 || strlen($newPassword) > 50) {

        $password_error = 'New password must be 6 to 50 characters.';

    } elseif ($newPassword !== $confirmPassword) {

        $password_error = 'New passwords do not match.';

    } else {

        $stmt = $conn->prepare("UPDATE players SET player_password = ? WHERE playerID = ?");
        $stmt->bind_param("ss", $newPassword, $playerID);

        if ($stmt->execute()) {

            $stmt->close();

            header("Location: my_profile.php?password=1#password");
            exit();
        }

        $password_error = 'Something went wrong. Please try again.';

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| Display
|--------------------------------------------------------------------------
*/

$photoSrc = !empty($player['player_profile']) ? $player['player_profile'] : '../admin/assets/user.png';

$dobValue = '';

if (!empty($form['player_dob']) && strtotime($form['player_dob']) > 0) {
    $dobValue = date('Y-m-d', strtotime($form['player_dob']));
}

function value($form, $key)
{
    return htmlspecialchars((string) ($form[$key] ?? ''));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Player | My Profile</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />

    <!-- Navbar CSS -->
    <link rel="stylesheet" href="./assets/css/navbar.css?v=<?= filemtime(__DIR__ . '/assets/css/navbar.css') ?>" type="text/css" />

    <!-- Shared Page CSS -->
    <link rel="stylesheet" href="./assets/css/tournament_details.css?v=<?= filemtime(__DIR__ . '/assets/css/tournament_details.css') ?>" type="text/css" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="./assets/css/my_profile.css?v=<?= filemtime(__DIR__ . '/assets/css/my_profile.css') ?>" type="text/css" />
</head>


<body>


    <?php include "player_navbar.php"; ?>


    <main class="container profile-container">

        <h1 class="profile-heading">My Profile</h1>


        <?php if (isset($_GET['updated']) && $_GET['updated'] == '1'): ?>

            <div class="message success">
                <i class="fa-solid fa-circle-check"></i>
                Profile updated successfully.
            </div>

        <?php endif; ?>


        <?php if ($error_message !== ''): ?>

            <div class="message error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>

        <?php endif; ?>


        <form method="POST" action="my_profile.php" enctype="multipart/form-data" id="profileForm">

            <input type="hidden" name="action" value="profile">


            <!-- =================================================
                 PROFILE HEADER
            ================================================== -->

            <section class="card profile-header">

                <div class="avatar-wrapper">

                    <img
                        src="<?= htmlspecialchars($photoSrc) ?>"
                        alt="Profile photo"
                        class="avatar"
                        id="avatarPreview"
                    >

                    <label for="player_profile" class="avatar-btn" title="Change photo">
                        <i class="fa-solid fa-camera"></i>
                    </label>

                    <input
                        type="file"
                        id="player_profile"
                        name="player_profile"
                        accept=".jpg,.jpeg,.png,.webp"
                        hidden
                    >

                </div>


                <div class="profile-identity">

                    <h2><?= htmlspecialchars(trim($player['player_full_name']) ?: $player['player_first_name'] . ' ' . $player['player_last_name']) ?></h2>

                    <div class="identity-meta">

                        <span><i class="fa-solid fa-id-card"></i> <?= htmlspecialchars($player['playerID']) ?></span>

                        <?php if (!empty($player['player_register'])): ?>

                            <span>
                                <i class="fa-solid fa-calendar-check"></i>
                                Member since <?= date("M Y", strtotime($player['player_register'])) ?>
                            </span>

                        <?php endif; ?>

                        <span class="status <?= strtolower($player['player_active'] ?? '') === 'active' ? 'status-good' : 'status-bad' ?>">
                            <?= htmlspecialchars(ucfirst($player['player_active'] ?: 'inactive')) ?>
                        </span>

                    </div>

                    <small class="hint">Click the camera to change your photo (JPG, PNG or WEBP, max 5MB).</small>

                </div>

            </section>


            <!-- =================================================
                 PERSONAL INFORMATION
            ================================================== -->

            <section class="card profile-section">

                <h2>Personal Information</h2>

                <div class="form-grid">

                    <div class="form-group">
                        <label for="player_first_name">First Name <span class="required">*</span></label>
                        <input type="text" id="player_first_name" name="player_first_name" maxlength="255" value="<?= value($form, 'player_first_name') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="player_last_name">Last Name <span class="required">*</span></label>
                        <input type="text" id="player_last_name" name="player_last_name" maxlength="255" value="<?= value($form, 'player_last_name') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="player_dob">Date of Birth <span class="required">*</span></label>
                        <input type="date" id="player_dob" name="player_dob" value="<?= htmlspecialchars($dobValue) ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="player_gender">Gender <span class="required">*</span></label>
                        <select id="player_gender" name="player_gender" required>
                            <option value="">Select gender</option>
                            <option value="Male" <?= ($form['player_gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= ($form['player_gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="player_nationality">Nationality <span class="required">*</span></label>
                        <select id="player_nationality" name="player_nationality" required>
                            <option value="">Select nationality</option>
                            <?php foreach ($nationalities as $nationality): ?>
                                <option
                                    value="<?= htmlspecialchars($nationality) ?>"
                                    <?= ($form['player_nationality'] ?? '') === $nationality ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($nationality) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="player_nationalid">National ID (IC)</label>
                        <input type="text" id="player_nationalid" name="player_nationalid" maxlength="255" value="<?= value($form, 'player_nationalid') ?>" placeholder="e.g. 130101-14-1234">
                    </div>

                    <div class="form-group">
                        <label for="player_passport">Passport Number</label>
                        <input type="text" id="player_passport" name="player_passport" maxlength="255" value="<?= value($form, 'player_passport') ?>">
                    </div>

                </div>

            </section>


            <!-- =================================================
                 CONTACT
            ================================================== -->

            <section class="card profile-section">

                <h2>Contact</h2>

                <div class="form-grid">

                    <div class="form-group">
                        <label for="player_email">Email <span class="required">*</span></label>
                        <input type="email" id="player_email" name="player_email" maxlength="255" value="<?= value($form, 'player_email') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="player_contact">Contact Number</label>
                        <input type="tel" id="player_contact" name="player_contact" maxlength="50" value="<?= value($form, 'player_contact') ?>" placeholder="e.g. 0123456789">
                    </div>

                </div>

            </section>


            <!-- =================================================
                 SQUASH INFORMATION
            ================================================== -->

            <section class="card profile-section">

                <h2>Squash Information</h2>

                <div class="form-grid">

                    <div class="form-group">
                        <label for="asf_member_no">ASF Membership No</label>
                        <input type="text" id="asf_member_no" name="asf_member_no" maxlength="255" value="<?= value($form, 'asf_member_no') ?>">
                    </div>

                    <div class="form-group">
                        <label for="spin_number">WSF SPIN No</label>
                        <input type="text" id="spin_number" name="spin_number" maxlength="255" value="<?= value($form, 'spin_number') ?>">
                    </div>

                    <div class="form-group">
                        <label for="national_ranking">National Ranking</label>
                        <input type="text" id="national_ranking" name="national_ranking" inputmode="numeric" pattern="[1-9][0-9]{0,5}" maxlength="6" placeholder="e.g. 12" title="A whole number, e.g. 12" value="<?= value($form, 'national_ranking') ?>">
                    </div>

                    <div class="form-group">
                        <label for="ajss_ranking">AJSS Ranking</label>
                        <input type="text" id="ajss_ranking" name="ajss_ranking" inputmode="numeric" pattern="[1-9][0-9]{0,5}" maxlength="6" placeholder="e.g. 12" title="A whole number, e.g. 12" value="<?= value($form, 'ajss_ranking') ?>">
                    </div>

                    <div class="form-group">
                        <label for="world_ranking">(PSA) World Ranking</label>
                        <input type="text" id="world_ranking" name="world_ranking" inputmode="numeric" pattern="[1-9][0-9]{0,5}" maxlength="6" placeholder="e.g. 12" title="A whole number, e.g. 12" value="<?= value($form, 'world_ranking') ?>">
                    </div>

                </div>

                <p class="hint">
                    <i class="fa-solid fa-circle-info"></i>
                    Rankings are used for seeding. Enter your current ranking as a number, or leave it empty if you don't have one.
                </p>

            </section>


            <div class="profile-actions">
                <button type="submit" class="primary-btn" id="saveBtn">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save Changes
                </button>
            </div>

        </form>


        <!-- =====================================================
             CHANGE PASSWORD
        ====================================================== -->

        <section class="card profile-section" id="password">

            <h2>Change Password</h2>


            <?php if (isset($_GET['password']) && $_GET['password'] == '1'): ?>

                <div class="message success">
                    <i class="fa-solid fa-circle-check"></i>
                    Password changed successfully.
                </div>

            <?php endif; ?>


            <?php if ($password_error !== ''): ?>

                <div class="message error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= htmlspecialchars($password_error) ?>
                </div>

            <?php endif; ?>


            <form method="POST" action="my_profile.php#password" id="passwordForm">

                <input type="hidden" name="action" value="password">

                <div class="form-grid three">

                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" minlength="6" maxlength="50" required autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" minlength="6" maxlength="50" required autocomplete="new-password">
                    </div>

                </div>

                <div class="profile-actions">
                    <button type="submit" class="secondary-btn">
                        Change Password
                    </button>
                </div>

            </form>

        </section>

    </main>


    <script>
        document.addEventListener("DOMContentLoaded", function () {

            /* Photo preview */

            const photoInput = document.getElementById("player_profile");
            const avatar = document.getElementById("avatarPreview");

            photoInput.addEventListener("change", function () {

                if (this.files && this.files[0]) {
                    avatar.src = URL.createObjectURL(this.files[0]);
                }

            });


            /* Disable save button while submitting */

            document.getElementById("profileForm").addEventListener("submit", function () {

                const button = document.getElementById("saveBtn");

                button.disabled = true;
                button.textContent = "Saving...";

            });


            /* Confirm password match */

            document.getElementById("passwordForm").addEventListener("submit", function (event) {

                const newPassword = document.getElementById("new_password").value;
                const confirmPassword = document.getElementById("confirm_password").value;

                if (newPassword !== confirmPassword) {
                    event.preventDefault();
                    alert("New passwords do not match.");
                }

            });

        });
    </script>


</body>

</html>
