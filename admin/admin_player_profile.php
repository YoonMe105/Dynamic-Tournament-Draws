<?php
session_start();

require '../db.php';

$player_id = trim((string) ($_GET['id'] ?? ''));
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
    $player_nationality = trim((string) ($_POST['player_nationality'] ?? ''));
    $asf_member_no = trim((string) ($_POST['asf_member_no'] ?? ''));
    $spin_number = trim((string) ($_POST['spin_number'] ?? ''));
    $national_ranking = trim((string) ($_POST['national_ranking'] ?? ''));
    $ajss_ranking = trim((string) ($_POST['ajss_ranking'] ?? ''));
    $psa_ranking = trim((string) ($_POST['psa_ranking'] ?? ''));
    $player_contact = trim((string) ($_POST['player_contact'] ?? ''));
    $player_email = trim((string) ($_POST['player_email'] ?? ''));
    $player_active = trim((string) ($_POST['player_active'] ?? ''));

    $player_full_name = trim($player_first_name . ' ' . $player_last_name);

    $update_query = "UPDATE players SET player_password = ?, player_full_name = ?, player_first_name = ?, player_last_name = ?, player_nationalid = ?, player_passport = ?, player_dob = ?, player_gender = ?, player_nationality = ?, asf_member_no = ?, spin_number = ?, national_ranking = ?, ajss_ranking = ?, psa_ranking = ?, player_contact = ?, player_email = ?, player_active = ? WHERE playerID = ?";

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

        header("Location: admin_player_profile.php?id=" . urlencode($player_id) . "&updated=1");
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
            <form id="player-form" method="post" action="admin_player_profile.php?id=<?= urlencode($player['playerID']) ?>&edit=1">
                <input type="hidden" name="player_id" value="<?= htmlspecialchars($player['playerID']) ?>">
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

                        <strong><?= htmlspecialchars($player['player_passport']) ?></strong>

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
                            <option value="afghan" >Afghan</option><option value="albanian" >Albanian</option><option value="algerian" >Algerian</option><option value="american" >American</option><option value="andorran" >Andorran</option><option value="angolan" >Angolan</option><option value="antiguans" >Antiguans</option><option value="argentinean" >Argentinean</option><option value="armenian" >Armenian</option><option value="australian" >Australian</option><option value="austrian" >Austrian</option><option value="azerbaijani" >Azerbaijani</option><option value="bahamian" >Bahamian</option><option value="bahraini" >Bahraini</option><option value="bangladeshi" >Bangladeshi</option><option value="barbadian" >Barbadian</option><option value="barbudans" >Barbudans</option><option value="batswana" >Batswana</option><option value="belarusian" >Belarusian</option><option value="belgian" >Belgian</option><option value="belizean" >Belizean</option><option value="beninese" >Beninese</option><option value="bhutanese" >Bhutanese</option><option value="bolivian" >Bolivian</option><option value="bosnian" >Bosnian</option><option value="brazilian" >Brazilian</option><option value="british" >British</option><option value="bruneian" >Bruneian</option><option value="bulgarian" >Bulgarian</option><option value="burkinabe" >Burkinabe</option><option value="burmese" >Burmese</option><option value="burundian" >Burundian</option><option value="cambodian" >Cambodian</option><option value="cameroonian" >Cameroonian</option><option value="canadian" >Canadian</option><option value="cape verdean" >Cape verdean</option><option value="central african" >Central african</option><option value="chadian" >Chadian</option><option value="chilean" >Chilean</option><option value="chinese" >Chinese</option><option value="colombian" >Colombian</option><option value="comoran" >Comoran</option><option value="congolese" >Congolese</option><option value="costa rican" >Costa rican</option><option value="croatian" >Croatian</option><option value="cuban" >Cuban</option><option value="cypriot" >Cypriot</option><option value="czech" >Czech</option><option value="danish" >Danish</option><option value="djibouti" >Djibouti</option><option value="dominican" >Dominican</option><option value="dutch" >Dutch</option><option value="east timorese" >East timorese</option><option value="ecuadorean" >Ecuadorean</option><option value="egyptian" >Egyptian</option><option value="emirian" >Emirian</option><option value="equatorial guinean" >Equatorial guinean</option><option value="eritrean" >Eritrean</option><option value="estonian" >Estonian</option><option value="ethiopian" >Ethiopian</option><option value="fijian" >Fijian</option><option value="filipino" >Filipino</option><option value="finnish" >Finnish</option><option value="french" >French</option><option value="gabonese" >Gabonese</option><option value="gambian" >Gambian</option><option value="georgian" >Georgian</option><option value="german" >German</option><option value="ghanaian" >Ghanaian</option><option value="greek" >Greek</option><option value="grenadian" >Grenadian</option><option value="guatemalan" >Guatemalan</option><option value="guinea-bissauan" >Guinea-bissauan</option><option value="guinean" >Guinean</option><option value="guyanese" >Guyanese</option><option value="haitian" >Haitian</option><option value="honduran" >Honduran</option><option value="hungarian" >Hungarian</option><option value="icelander" >Icelander</option><option value="indian" >Indian</option><option value="indonesian" >Indonesian</option><option value="iranian" >Iranian</option><option value="iraqi" >Iraqi</option><option value="irish" >Irish</option><option value="israeli" >Israeli</option><option value="italian" >Italian</option><option value="jamaican" >Jamaican</option><option value="japanese" >Japanese</option><option value="jordanian" >Jordanian</option><option value="kazakhstani" >Kazakhstani</option><option value="kenyan" >Kenyan</option><option value="korean" >Korean</option><option value="kosovar" >Kosovar</option><option value="kuwaiti" >Kuwaiti</option><option value="kyrgyzstani" >Kyrgyzstani</option><option value="laotian" >Laotian</option><option value="latvian" >Latvian</option><option value="lebanese" >Lebanese</option><option value="lesotho" >Lesotho</option><option value="liberian" >Liberian</option><option value="libyan" >Libyan</option><option value="lithuanian" >Lithuanian</option><option value="luxembourger" >Luxembourger</option><option value="macedonian" >Macedonian</option><option value="malagasy" >Malagasy</option><option value="malawian" >Malawian</option><option value="malaysian" selected>Malaysian</option><option value="malian" >Malian</option><option value="maltese" >Maltese</option><option value="marshallese" >Marshallese</option><option value="mauritian" >Mauritian</option><option value="mexican" >Mexican</option><option value="micronesian" >Micronesian</option><option value="moldovan" >Moldovan</option><option value="monacan" >Monacan</option><option value="mongolian" >Mongolian</option><option value="moroccan" >Moroccan</option><option value="mozambican" >Mozambican</option><option value="namibian" >Namibian</option><option value="nauruan" >Nauruan</option><option value="nepalese" >Nepalese</option><option value="new zealander" >New zealander</option><option value="nicaraguan" >Nicaraguan</option><option value="nigerien" >Nigerien</option><option value="nigerian" >Nigerian</option><option value="norwegian" >Norwegian</option><option value="omani" >Omani</option><option value="pakistani" >Pakistani</option><option value="palauan" >Palauan</option><option value="palestinian" >Palestinian</option><option value="panamanian" >Panamanian</option><option value="papua new guinean" >Papua new guinean</option><option value="paraguayan" >Paraguayan</option><option value="peruvian" >Peruvian</option><option value="philosopher" >Philosopher</option><option value="polish" >Polish</option><option value="portuguese" >Portuguese</option><option value="qatarian" >Qatarian</option><option value="romanian" >Romanian</option><option value="russian" >Russian</option><option value="rwandan" >Rwandan</option><option value="saint lucian" >Saint lucian</option><option value="salvadoran" >Salvadoran</option><option value="samoan" >Samoan</option><option value="san marinese" >San marinese</option><option value="sao tomean" >Sao tomean</option><option value="saudi" >Saudi</option><option value="scottish" >Scottish</option><option value="senegalese" >Senegalese</option><option value="serbian" >Serbian</option><option value="singaporean" >Singaporean</option><option value="slovak" >Slovak</option><option value="slovenian" >Slovenian</option><option value="solomon islander" >Solomon islander</option><option value="somali" >Somali</option><option value="south african" >South african</option><option value="spanish" >Spanish</option><option value="sri lankan" >Sri lankan</option><option value="sudanese" >Sudanese</option><option value="surinamese" >Surinamese</option><option value="swedish" >Swedish</option><option value="swiss" >Swiss</option><option value="syrian" >Syrian</option><option value="taiwanese" >Taiwanese</option><option value="tajikistani" >Tajikistani</option><option value="tanzanian" >Tanzanian</option><option value="thai" >Thai</option><option value="togolese" >Togolese</option><option value="tongan" >Tongan</option><option value="trinidadian" >Trinidadian</option><option value="tunisian" >Tunisian</option><option value="turkish" >Turkish</option><option value="turkmen" >Turkmen</option><option value="ugandan" >Ugandan</option><option value="ukrainian" >Ukrainian</option><option value="emirian" >Emirian</option><option value="united kingdom" >United kingdom</option><option value="united states" >United states</option><option value="uruguayan" >Uruguayan</option><option value="uzbekistani" >Uzbekistani</option><option value="vanuatuan" >Vanuatuan</option><option value="vatican" >Vatican</option><option value="venezuelan" >Venezuelan</option><option value="vietnamese" >Vietnamese</option><option value="yemeni" >Yemeni</option><option value="zambian" >Zambian</option><option value="zimbabwean" >Zimbabwean</option>                            
                        
                        </select>

                    <?php else: ?>

                        <strong><?= htmlspecialchars($player['player_nationality']) ?></strong>

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

                        <strong><?= htmlspecialchars($player['ajss_ranking']) ?></strong>

                    <?php endif; ?>

                </div>

                <div class="profile-field">
                    <span>PSA World Ranking</span>

                    <?php if ($edit_mode): ?>

                        <input class="profile-input" type="text" name="psa_ranking" value="<?= htmlspecialchars($player['psa_ranking']) ?>">

                    <?php else: ?>

                        <strong><?= htmlspecialchars($player['psa_ranking']) ?></strong>

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

                <a class="details-btn" href="admin_player_profile.php?id=<?= urlencode($player['playerID']) ?>">Back</a>
                <button class="details-btn update-btn" type="submit">Update</button>

            <?php else: ?>

                <a class="details-btn" href="admin_player_lists.php">Back</a>
                <a class="details-btn update-btn" href="admin_player_profile.php?id=<?= urlencode($player['playerID']) ?>&edit=1">Update</a>

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

        <a class="details-btn" href="admin_player_lists.php">Back to Players</a>

    <?php endif; ?>

    </main>


    <script>
        const nationalitySelect = document.getElementById('player_nationality');

        Array.from(nationalitySelect.options).forEach(function(option) {
            if (option.value !== '') {
                option.value = option.value
                    .toLowerCase()
                    .replace(/\b\w/g, function(letter) {
                        return letter.toUpperCase();
                    });
            }
        });
    </script>
</body>
</html>