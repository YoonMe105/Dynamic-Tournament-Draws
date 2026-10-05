<?php
require_once '../db.php';
require_once 'admin_auth.php';

// Platform Admins, or the admin who created this tournament
requirePlayerAccess(trim((string)($_GET['id'] ?? '')), $_GET['tournamentID'] ?? 0);
$player_id = trim((string) ($_GET['id'] ?? ''));
$tournamentID = trim((string) ($_GET['tournamentID'] ?? ''));
$edit_mode = isset($_GET['edit']) && $_GET['edit'] === '1';
$player = null;

if ($player_id !== '') {
    $stmt = $conn->prepare("SELECT * FROM players WHERE playerID = ?");
    $stmt->bind_param("s", $player_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $player = $result->fetch_assoc();

    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $player_id = trim((string) ($_POST['player_id'] ?? ''));

    $player_password = trim((string) ($_POST['player_password'] ?? ''));
    $player_first_name = trim((string) ($_POST['player_first_name'] ?? ''));
    $player_last_name = trim((string) ($_POST['player_last_name'] ?? ''));
    $player_nationalid = trim((string) ($_POST['player_nationalid'] ?? ''));
    $player_passport = trim((string) ($_POST['player_passport'] ?? ''));
    $player_dob = trim((string) ($_POST['player_dob'] ?? ''));
    $player_gender = trim((string) ($_POST['player_gender'] ?? ''));
    $player_nationality = strtoupper(trim((string) ($_POST['player_nationality'] ?? '')));
    $asf_member_no = trim((string) ($_POST['asf_member_no'] ?? ''));
    $spin_number = trim((string) ($_POST['spin_number'] ?? ''));
    $national_ranking = trim((string) ($_POST['national_ranking'] ?? ''));
    $ajss_ranking = trim((string) ($_POST['ajss_ranking'] ?? ''));
    $psa_ranking = trim((string) ($_POST['psa_ranking'] ?? ''));
    $player_contact = trim((string) ($_POST['player_contact'] ?? ''));
    $player_email = trim((string) ($_POST['player_email'] ?? ''));
    $player_active = trim((string) ($_POST['player_active'] ?? ''));

    $player_full_name = trim($player_first_name . ' ' . $player_last_name);

    $update_query = "UPDATE players SET player_password = ?, player_full_name = ?, player_first_name = ?, player_last_name = ?, player_nationalid = ?, player_passport = ?, player_dob = ?, player_gender = ?, player_nationality = ?, asf_member_no = ?, spin_number = ?, national_ranking = ?, ajss_ranking = ?, world_ranking = ?, player_contact = ?, player_email = ?, player_active = ? WHERE playerID = ?";

    $stmt = $conn->prepare($update_query);

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "ssssssssssssssssss",
        $player_password,
        $player_full_name,
        $player_first_name,
        $player_last_name,
        $player_nationalid,
        $player_passport,
        $player_dob,
        $player_gender,
        $player_nationality,
        $asf_member_no,
        $spin_number,
        $national_ranking,
        $ajss_ranking,
        $psa_ranking,
        $player_contact,
        $player_email,
        $player_active,
        $player_id
    );

    if ($stmt->execute()) {

        $stmt->close();

        echo "
        <script>
            alert('Update Successfully');
            window.location.href = 'admin_player_profile.php?id="
            . urlencode($player_id)
            . "&tournamentID="
            . urlencode($tournamentID)
            . "';
        </script>
        ";

        exit;

    } else {

        die("Could not update player: " . $stmt->error);

    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Profile | T_Software</title>
    <link href="./assets/style.css" rel="stylesheet" type="text/css">
    <link href="./assets/player_profile.css" rel="stylesheet" type="text/css" />
</head>

<body>

    <?php require_once 'admin_navbar.php'; ?>

    <main>

    <?php if ($player): ?>

        <div class="profile-titlebar">
            <div>
                <p class="section-kicker">Player Profile</p>
                <div class="">
                    <span>Registration Date: </span>
                    <strong><?= htmlspecialchars($player['player_register']) ?></strong>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['updated'])): ?>
            <div class="update-message">Player updated successfully.</div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="update-message"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <?php if ($edit_mode): ?>
            <form id="player-form" method="post" action="admin_player_profile.php?id=<?= urlencode($player['playerID']) ?>&tournamentID=<?= urlencode($tournamentID) ?>&edit=1">

                <input type="hidden" name="player_id" value="<?= htmlspecialchars($player['playerID']) ?>">

                <input type="hidden" name="tournamentID" value="<?= htmlspecialchars($tournamentID) ?>">
        <?php endif; ?>


        <!-- PROFILE IMAGE -->

        <section class="profile-image-panel">

            <img class="profile-large-avatar" src="<?= !empty($player['player_profile']) ? htmlspecialchars($player['player_profile']) : 'assets/user.png' ?>" alt="Player Profile">

            <div class="profile-image-help">
                <strong>Image Requirements</strong>
                <span>1. Image in .png or .jpg only</span>
                <span>2. Maximum size 16MB only</span>
                <span>3. Face Picture only</span>
            </div>

            <?php if ($edit_mode): ?>
                <label class="image-upload">
                    Choose Photo
                    <input type="file" name="player_profile" accept="image/png,image/jpeg">
                </label>
            <?php endif; ?>

        </section>


        <!-- PERSONAL DETAILS -->

        <section class="details-panel profile-panel">

            <h2>Personal Details</h2>

            <div class="profile-grid">

                <div class="profile-field">
                    <span>ID</span>
                    <strong><?= htmlspecialchars($player['playerID']) ?></strong>
                </div>

                <div class="profile-field">
                    <span>Full Name</span>
                    <strong><?= htmlspecialchars($player['player_full_name']) ?></strong>
                </div>

                <div class="profile-field">
                    <span>First Name</span>

                    <?php if ($edit_mode): ?>

                        <input class="profile-input" type="text" name="player_first_name" value="<?= htmlspecialchars($player['player_first_name']) ?>">

                    <?php else: ?>

                        <strong><?= htmlspecialchars($player['player_first_name']) ?></strong>

                    <?php endif; ?>

                </div>

                <div class="profile-field">
                    <span>Last Name</span>

                    <?php if ($edit_mode): ?>

                        <input class="profile-input" type="text" name="player_last_name" value="<?= htmlspecialchars($player['player_last_name']) ?>">

                    <?php else: ?>

                        <strong><?= htmlspecialchars($player['player_last_name']) ?></strong>

                    <?php endif; ?>

                </div>

                <div class="profile-field">
                    <span>Gender</span>

                    <?php if ($edit_mode): ?>

                        <select class="profile-input" name="player_gender">
                            <option value="Male" <?= $player['player_gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= $player['player_gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                        </select>

                    <?php else: ?>

                        <strong><?= htmlspecialchars($player['player_gender']) ?></strong>

                    <?php endif; ?>

                </div>

                <div class="profile-field">
                    <span>National ID</span>

                    <?php if ($edit_mode): ?>

                        <input class="profile-input" type="text" name="player_nationalid" value="<?= htmlspecialchars($player['player_nationalid'] ?? '') ?>">

                    <?php else: ?>

                        <strong><?= !empty($player['player_nationalid']) ? htmlspecialchars($player['player_nationalid']) : '-' ?></strong>

                    <?php endif; ?>

                </div>

                <div class="profile-field">
                    <span>Passport</span>

                    <?php if ($edit_mode): ?>

                        <input class="profile-input" type="text" name="player_passport" value="<?= htmlspecialchars($player['player_passport']) ?>">

                    <?php else: ?>

                        <strong><?= !empty($player['player_passport']) ? htmlspecialchars($player['player_passport']) : '-' ?></strong>

                    <?php endif; ?>

                </div>

                <div class="profile-field">
                    <span>Date of Birth</span>

                    <?php if ($edit_mode): ?>

                        <input class="profile-input" type="date" name="player_dob" value="<?= htmlspecialchars($player['player_dob']) ?>">

                    <?php else: ?>

                        <strong><?= htmlspecialchars($player['player_dob']) ?></strong>

                    <?php endif; ?>

                </div>


                <!-- NATIONALITY -->

                <div class="profile-field">
                    <span>Nationality</span>

                    <?php if ($edit_mode): ?>

                        <select class="profile-input" id="player_nationality" name="player_nationality">
                            <?php
                            $nationalities = ['AFGHAN', 'ALBANIAN', 'ALGERIAN', 'AMERICAN', 'ANDORRAN', 'ANGOLAN', 'ANTIGUANS', 'ARGENTINEAN', 'ARMENIAN', 'AUSTRALIAN', 'AUSTRIAN', 'AZERBAIJANI', 'BAHAMIAN', 'BAHRAINI', 'BANGLADESHI', 'BARBADIAN', 'BARBUDANS', 'BATSWANA', 'BELARUSIAN', 'BELGIAN', 'BELIZEAN', 'BENINESE', 'BHUTANESE', 'BOLIVIAN', 'BOSNIAN', 'BRAZILIAN', 'BRITISH', 'BRUNEIAN', 'BULGARIAN', 'BURKINABE', 'BURMESE', 'BURUNDIAN', 'CAMBODIAN', 'CAMEROONIAN', 'CANADIAN', 'CAPE VERDEAN', 'CENTRAL AFRICAN', 'CHADIAN', 'CHILEAN', 'CHINESE', 'COLOMBIAN', 'COMORAN', 'CONGOLESE', 'COSTA RICAN', 'CROATIAN', 'CUBAN', 'CYPRIOT', 'CZECH', 'DANISH', 'DJIBOUTI', 'DOMINICAN', 'DUTCH', 'EAST TIMORESE', 'ECUADOREAN', 'EGYPTIAN', 'EMIRIAN', 'EQUATORIAL GUINEAN', 'ERITREAN', 'ESTONIAN', 'ETHIOPIAN', 'FIJIAN', 'FILIPINO', 'FINNISH', 'FRENCH', 'GABONESE', 'GAMBIAN', 'GEORGIAN', 'GERMAN', 'GHANAIAN', 'GREEK', 'GRENADIAN', 'GUATEMALAN', 'GUINEA-BISSAUAN', 'GUINEAN', 'GUYANESE', 'HAITIAN', 'HONDURAN', 'HUNGARIAN', 'ICELANDER', 'INDIAN', 'INDONESIAN', 'IRANIAN', 'IRAQI', 'IRISH', 'ISRAELI', 'ITALIAN', 'JAMAICAN', 'JAPANESE', 'JORDANIAN', 'KAZAKHSTANI', 'KENYAN', 'KOREAN', 'KOSOVAR', 'KUWAITI', 'KYRGYZSTANI', 'LAOTIAN', 'LATVIAN', 'LEBANESE', 'LESOTHO', 'LIBERIAN', 'LIBYAN', 'LITHUANIAN', 'LUXEMBOURGER', 'MACEDONIAN', 'MALAGASY', 'MALAWIAN', 'MALAYSIAN', 'MALIAN', 'MALTESE', 'MARSHALLESE', 'MAURITIAN', 'MEXICAN', 'MICRONESIAN', 'MOLDOVAN', 'MONACAN', 'MONGOLIAN', 'MOROCCAN', 'MOZAMBICAN', 'NAMIBIAN', 'NAURUAN', 'NEPALESE', 'NEW ZEALANDER', 'NICARAGUAN', 'NIGERIEN', 'NIGERIAN', 'NORWEGIAN', 'OMANI', 'PAKISTANI', 'PALAUAN', 'PALESTINIAN', 'PANAMANIAN', 'PAPUA NEW GUINEAN', 'PARAGUAYAN', 'PERUVIAN', 'PHILOSOPHER', 'POLISH', 'PORTUGUESE', 'QATARIAN', 'ROMANIAN', 'RUSSIAN', 'RWANDAN', 'SAINT LUCIAN', 'SALVADORAN', 'SAMOAN', 'SAN MARINESE', 'SAO TOMEAN', 'SAUDI', 'SCOTTISH', 'SENEGALESE', 'SERBIAN', 'SINGAPOREAN', 'SLOVAK', 'SLOVENIAN', 'SOLOMON ISLANDER', 'SOMALI', 'SOUTH AFRICAN', 'SPANISH', 'SRI LANKAN', 'SUDANESE', 'SURINAMESE', 'SWEDISH', 'SWISS', 'SYRIAN', 'TAIWANESE', 'TAJIKISTANI', 'TANZANIAN', 'THAI', 'TOGOLESE', 'TONGAN', 'TRINIDADIAN', 'TUNISIAN', 'TURKISH', 'TURKMEN', 'UGANDAN', 'UKRAINIAN', 'UNITED KINGDOM', 'UNITED STATES', 'URUGUAYAN', 'UZBEKISTANI', 'VANUATUAN', 'VATICAN', 'VENEZUELAN', 'VIETNAMESE', 'YEMENI', 'ZAMBIAN', 'ZIMBABWEAN'];

                            // Pre-select the saved nationality, and keep it selectable if it isn't in the list
                            $savedNationality = strtoupper(trim($player['player_nationality'] ?? '')) ?: 'MALAYSIAN';

                            if (!in_array($savedNationality, $nationalities, true)) {
                                $nationalities[] = $savedNationality;
                            }
                            ?>
                            <?php foreach ($nationalities as $nationality): ?>
                                <option value="<?= htmlspecialchars($nationality) ?>" <?= $nationality === $savedNationality ? 'selected' : '' ?>><?= htmlspecialchars($nationality) ?></option>
                            <?php endforeach; ?>
                        
                        </select>

                    <?php else: ?>
                        <strong><?= !empty($player['player_nationality']) ? htmlspecialchars($player['player_nationality']) : '-' ?></strong>
                    <?php endif; ?>

                </div>

            </div>

        </section>


        <!-- PLAYER DETAILS -->

        <section class="details-panel profile-panel">

            <h2>Player Details</h2>
            <div class="profile-grid">

                <div class="profile-field">
                    <span>ASF Membership No.</span>

                    <?php if ($edit_mode): ?>
                        <input class="profile-input" type="text" name="asf_member_no" value="<?= htmlspecialchars($player['asf_member_no'] ?? '') ?>">
                    <?php else: ?>
                        <strong><?= !empty($player['asf_member_no']) ? htmlspecialchars($player['asf_member_no']) : '-' ?></strong>
                    <?php endif; ?>

                </div>

                <div class="profile-field">
                    <span>SPIN No.</span>

                    <?php if ($edit_mode): ?>

                        <input class="profile-input" type="text" name="spin_number" value="<?= htmlspecialchars($player['spin_number'] ?? '') ?>">

                    <?php else: ?>

                        <strong><?= !empty($player['spin_number']) ? htmlspecialchars($player['spin_number']) : '-' ?></strong>

                    <?php endif; ?>

                </div>

                <div class="profile-field">
                    <span>National Ranking</span>

                    <?php if ($edit_mode): ?>

                        <input class="profile-input" type="text" name="national_ranking" value="<?= htmlspecialchars($player['national_ranking'] ?? '') ?>">

                    <?php else: ?>

                        <strong><?= !empty($player['national_ranking']) ? htmlspecialchars($player['national_ranking']) : '-' ?></strong>

                    <?php endif; ?>

                </div>

                <div class="profile-field">
                    <span>AJSS Ranking</span>

                    <?php if ($edit_mode): ?>

                        <input class="profile-input" type="text" name="ajss_ranking" value="<?= htmlspecialchars($player['ajss_ranking']) ?>">

                    <?php else: ?>
                        <strong><?= !empty($player['ajss_ranking']) ? htmlspecialchars($player['ajss_ranking']) : '-' ?></strong>

                    <?php endif; ?>

                </div>

                <div class="profile-field">
                    <span>World Ranking</span>

                    <?php if ($edit_mode): ?>

                        <input class="profile-input" type="text" name="world_ranking" value="<?= htmlspecialchars($player['world_ranking']) ?>">

                    <?php else: ?>

                        <strong><?= !empty($player['world_ranking']) ? htmlspecialchars($player['world_ranking']) : '-' ?></strong>

                    <?php endif; ?>

                </div>

                <div class="profile-field">
                    <span>Player Status</span>

                    <?php if ($edit_mode): ?>

                        <select class="profile-input" name="player_active">
                            <option value="Active" <?= $player['player_active'] === 'Active' ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= $player['player_active'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>

                    <?php else: ?>

                        <strong><?= htmlspecialchars($player['player_active']) ?></strong>

                    <?php endif; ?>

                </div>

            </div>

        </section>


        <!-- CONTACT DETAILS -->

        <section class="details-panel profile-panel">

            <h2>Contact Details</h2>

            <div class="profile-grid profile-contact-grid">

                <div class="profile-field">
                    <span>Contact</span>

                    <?php if ($edit_mode): ?>

                        <input class="profile-input" type="text" name="player_contact" value="<?= htmlspecialchars($player['player_contact']) ?>">

                    <?php else: ?>

                        <strong><?= htmlspecialchars($player['player_contact']) ?></strong>

                    <?php endif; ?>

                </div>

                <div class="profile-field">
                    <span>Email</span>

                    <?php if ($edit_mode): ?>

                        <input class="profile-input" type="email" name="player_email" value="<?= htmlspecialchars($player['player_email'] ?? '') ?>">

                    <?php else: ?>

                        <strong><?= !empty($player['player_email']) ? htmlspecialchars($player['player_email']) : '-' ?></strong>

                    <?php endif; ?>

                </div>

                <div class="profile-field">
                    <span>Password</span>
                    <?php if ($edit_mode): ?>
                        <input class="profile-input" type="password" name="player_password" value="<?= htmlspecialchars($player['player_password']) ?>">
                    <?php else: ?>
                        <strong>****</strong>
                    <?php endif; ?>
                </div>

            </div>

        </section>


        <!-- BUTTONS -->

        <div class="details-bottom-actions">

            <?php if ($edit_mode): ?>

                <a class="details-btn" href="admin_each_tournament.php?id=<?= urlencode($tournamentID) ?>">Back</a>
                <button class="details-btn update-btn" type="submit">Save</button>

            <?php else: ?>

                <a class="details-btn" href="admin_each_tournament.php?id=<?= urlencode($tournamentID) ?>">Back</a>
                <a class="details-btn update-btn" href="admin_player_profile.php?id=<?= urlencode($player['playerID']) ?>&tournamentID=<?= urlencode($tournamentID) ?>&edit=1">Update</a>

            <?php endif; ?>

        </div>


        <?php if ($edit_mode): ?>
            </form>
        <?php endif; ?>


    <?php else: ?>

        <div class="dashboard-header">
            <h1>Player Not Found</h1>
            <p>The player you are looking for does not exist.</p>
        </div>

        <a class="details-btn" href="admin_each_tournament.php?id=<?= urlencode($tournamentID) ?>">Back to Tournaments</a>

    <?php endif; ?>

    </main>

</body>
</html>