<?php
session_start();

require '../db.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $player_first_name = trim($_POST['player_first_name'] ?? '');
    $player_last_name = trim($_POST['player_last_name'] ?? '');
    $player_nationalid = trim($_POST['player_nationalid'] ?? '');
    $player_passport = trim($_POST['player_passport'] ?? '');
    $player_dob = trim($_POST['player_dob'] ?? '');
    $player_gender = trim($_POST['player_gender'] ?? '');
    $player_nationality = trim($_POST['player_nationality'] ?? '');
    $trainingID = trim($_POST['trainingID'] ?? '');
    $rankedin = trim($_POST['rankedin'] ?? '');
    $asf_member_no = trim($_POST['asf_member_no'] ?? '');
    $spin_number = trim($_POST['spin_number'] ?? '');
    $national_ranking = trim($_POST['national_ranking'] ?? '');
    $ajss_ranking = trim($_POST['ajss_ranking'] ?? '');
    $psa_ranking = trim($_POST['psa_ranking'] ?? '');
    $player_contact = trim($_POST['player_contact'] ?? '');
    $player_email = trim($_POST['player_email'] ?? '');
    $player_password = trim($_POST['player_password'] ?? '');

    $player_full_name = trim($player_first_name . ' ' . $player_last_name);

    if ($player_first_name === '' || $player_last_name === '') {
        $message = 'First name and last name are required.';
        $message_type = 'error';

    } elseif ($player_dob === '') {
        $message = 'Date of birth is required.';
        $message_type = 'error';

    } elseif ($player_gender === '') {
        $message = 'Gender is required.';
        $message_type = 'error';

    } elseif ($player_nationality === '') {
        $message = 'Nationality is required.';
        $message_type = 'error';

    } elseif ($player_contact === '') {
        $message = 'Contact number is required.';
        $message_type = 'error';

    } elseif ($player_password === '') {
        $message = 'Password is required.';
        $message_type = 'error';

    } else {

        /*
         * Generate Player ID
         * Example:
         * P0001
         * P0002
         * P0003
         */

        $id_query = "
            SELECT playerID
            FROM players
            WHERE playerID LIKE 'P%'
            ORDER BY CAST(SUBSTRING(playerID, 2) AS UNSIGNED) DESC
            LIMIT 1
        ";

        $id_result = $conn->query($id_query);

        if ($id_result && $id_result->num_rows > 0) {

            $last_player = $id_result->fetch_assoc();
            $last_id = $last_player['playerID'];

            $last_number = (int) substr($last_id, 1);
            $next_number = $last_number + 1;

        } else {

            $next_number = 1;
        }

        $player_id = 'P' . str_pad($next_number, 4, '0', STR_PAD_LEFT);


        /*
         * Check if email already exists
         */

        if ($player_email !== '') {

            $email_query = "SELECT playerID FROM players WHERE player_email = ? LIMIT 1";

            $email_stmt = $conn->prepare($email_query);

            if (!$email_stmt) {
                die("Prepare failed: " . $conn->error);
            }

            $email_stmt->bind_param("s", $player_email);
            $email_stmt->execute();

            $email_result = $email_stmt->get_result();

            if ($email_result->num_rows > 0) {

                $message = 'This email is already registered.';
                $message_type = 'error';

                $email_stmt->close();

            } else {

                $email_stmt->close();

                /*
                 * Training ID
                 *
                 * trainingID is an integer in the database.
                 * If it is empty, store NULL.
                 */

                if ($trainingID === '') {
                    $training_value = null;
                } else {
                    $training_value = (int) $trainingID;
                }


                /*
                 * Default status
                 */

                $player_status = 'Active';
                $player_active = 'Active';

                /*
                 * Registration date
                 */

                $player_register = date('Y-m-d');


                /*
                 * Insert player
                 */

                $insert_query = "
                    INSERT INTO players (
                        playerID,
                        player_password,
                        player_full_name,
                        player_first_name,
                        player_last_name,
                        player_nationalid,
                        player_passport,
                        player_dob,
                        player_gender,
                        player_nationality,
                        player_contact,
                        player_register,
                        rankedin,
                        asf_member_no,
                        spin_number,
                        national_ranking,
                        ajss_ranking,
                        psa_ranking,
                        player_email,
                        player_status,
                        player_active,
                        trainingID
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";

                $stmt = $conn->prepare($insert_query);

                if (!$stmt) {
                    die("Prepare failed: " . $conn->error);
                }

                $stmt->bind_param(
                    "sssssssssssssssssssssi",
                    $player_id,
                    $player_password,
                    $player_full_name,
                    $player_first_name,
                    $player_last_name,
                    $player_nationalid,
                    $player_passport,
                    $player_dob,
                    $player_gender,
                    $player_nationality,
                    $player_contact,
                    $player_register,
                    $rankedin,
                    $asf_member_no,
                    $spin_number,
                    $national_ranking,
                    $ajss_ranking,
                    $psa_ranking,
                    $player_email,
                    $player_status,
                    $player_active,
                    $training_value
                );

                /*
                 * Execute insert
                 */

                if ($stmt->execute()) {

                    $stmt->close();

                    header(
                        "Location: admin_player_profile.php?id=" .
                        urlencode($player_id) .
                        "&created=1"
                    );

                    exit;

                } else {

                    $message = 'Could not create player: ' . $stmt->error;
                    $message_type = 'error';

                    $stmt->close();
                }
            }

        } else {

            /*
             * Training ID
             */

            if ($trainingID === '') {
                $training_value = null;
            } else {
                $training_value = (int) $trainingID;
            }

            /*
             * Default status
             */

            $player_status = 'Active';
            $player_active = 'Active';

            /*
             * Registration date
             */

            $player_register = date('Y-m-d');


            /*
             * Insert player
             */

            $insert_query = "
                INSERT INTO players (
                    playerID,
                    player_password,
                    player_full_name,
                    player_first_name,
                    player_last_name,
                    player_nationalid,
                    player_passport,
                    player_dob,
                    player_gender,
                    player_nationality,
                    player_contact,
                    player_register,
                    rankedin,
                    asf_member_no,
                    spin_number,
                    national_ranking,
                    ajss_ranking,
                    psa_ranking,
                    player_email,
                    player_status,
                    player_active,
                    trainingID
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $conn->prepare($insert_query);

            if (!$stmt) {
                die("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param(
                "sssssssssssssssssssssi",
                $player_id,
                $player_password,
                $player_full_name,
                $player_first_name,
                $player_last_name,
                $player_nationalid,
                $player_passport,
                $player_dob,
                $player_gender,
                $player_nationality,
                $player_contact,
                $player_register,
                $rankedin,
                $asf_member_no,
                $spin_number,
                $national_ranking,
                $ajss_ranking,
                $ajss_ranking,
                $player_email,
                $player_status,
                $player_active,
                $training_value
            );

            if ($stmt->execute()) {

                $stmt->close();

                header(
                    "Location: admin_player_profile.php?id=" .
                    urlencode($player_id) .
                    "&created=1"
                );

                exit;

            } else {

                $message = 'Could not create player: ' . $stmt->error;
                $message_type = 'error';

                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Player | T_Software</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

    <link href="./assets/style.css" rel="stylesheet" type="text/css">
</head>

<body>

<?php require_once 'admin_navbar.php'; ?>

<main class="players-page">

    <div class="players-heading">

        <div>
            <h1>Add New Player</h1>
            <p>Create a new player account.</p>
        </div>

        <div class="players-actions">
            <a class="add-tournament-btn" href="admin_player_lists.php">Back to Players</a>
        </div>

    </div>


    <?php if ($message !== ''): ?>

        <div class="form-message <?= htmlspecialchars($message_type) ?>">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <form class="player-form" method="POST" action="admin_player_create.php">

        <section class="profile-section">

            <div class="profile-section-heading">
                <h2>Personal Information</h2>
                <p>Enter the player's basic information.</p>
            </div>


            <div class="profile-grid">

                <div class="profile-field">
                    <span>First Name</span>
                    <input class="profile-input" type="text" name="player_first_name" value="<?= htmlspecialchars($_POST['player_first_name'] ?? '') ?>" required >
                </div>


                <div class="profile-field">
                    <span>Last Name</span>
                    <input class="profile-input" type="text" name="player_last_name" value="<?= htmlspecialchars($_POST['player_last_name'] ?? '') ?>" required >
                </div>


                <div class="profile-field">
                    <span>National ID</span>
                    <input class="profile-input" type="text" name="player_nationalid" value="<?= htmlspecialchars($_POST['player_nationalid'] ?? '') ?>" >
                </div>


                <div class="profile-field">
                    <span>Passport</span>
                    <input class="profile-input" type="text" name="player_passport" value="<?= htmlspecialchars($_POST['player_passport'] ?? '') ?>" >
                </div>


                <div class="profile-field">
                    <span>Date of Birth</span>
                    <input class="profile-input" type="date" name="player_dob" value="<?= htmlspecialchars($_POST['player_dob'] ?? '') ?>" required >
                </div>


                <div class="profile-field">
                    <span>Gender</span>
                    <select class="profile-input" name="player_gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male" <?= (($_POST['player_gender'] ?? '') === 'Male') ? 'selected' : '' ?>>
                            Male
                        </option>
                        <option value="Female" <?= (($_POST['player_gender'] ?? '') === 'Female') ? 'selected' : '' ?>>
                            Female
                        </option>
                    </select>
                </div>


                <div class="profile-field">
                    <span>Nationality</span>
                    <select class="profile-input" id="player_nationality" name="player_nationality" required>
                       <option value="afghan" >Afghan</option><option value="albanian" >Albanian</option><option value="algerian" >Algerian</option><option value="american" >American</option><option value="andorran" >Andorran</option><option value="angolan" >Angolan</option><option value="antiguans" >Antiguans</option><option value="argentinean" >Argentinean</option><option value="armenian" >Armenian</option><option value="australian" >Australian</option><option value="austrian" >Austrian</option><option value="azerbaijani" >Azerbaijani</option><option value="bahamian" >Bahamian</option><option value="bahraini" >Bahraini</option><option value="bangladeshi" >Bangladeshi</option><option value="barbadian" >Barbadian</option><option value="barbudans" >Barbudans</option><option value="batswana" >Batswana</option><option value="belarusian" >Belarusian</option><option value="belgian" >Belgian</option><option value="belizean" >Belizean</option><option value="beninese" >Beninese</option><option value="bhutanese" >Bhutanese</option><option value="bolivian" >Bolivian</option><option value="bosnian" >Bosnian</option><option value="brazilian" >Brazilian</option><option value="british" >British</option><option value="bruneian" >Bruneian</option><option value="bulgarian" >Bulgarian</option><option value="burkinabe" >Burkinabe</option><option value="burmese" >Burmese</option><option value="burundian" >Burundian</option><option value="cambodian" >Cambodian</option><option value="cameroonian" >Cameroonian</option><option value="canadian" >Canadian</option><option value="cape verdean" >Cape verdean</option><option value="central african" >Central african</option><option value="chadian" >Chadian</option><option value="chilean" >Chilean</option><option value="chinese" >Chinese</option><option value="colombian" >Colombian</option><option value="comoran" >Comoran</option><option value="congolese" >Congolese</option><option value="costa rican" >Costa rican</option><option value="croatian" >Croatian</option><option value="cuban" >Cuban</option><option value="cypriot" >Cypriot</option><option value="czech" >Czech</option><option value="danish" >Danish</option><option value="djibouti" >Djibouti</option><option value="dominican" >Dominican</option><option value="dutch" >Dutch</option><option value="east timorese" >East timorese</option><option value="ecuadorean" >Ecuadorean</option><option value="egyptian" >Egyptian</option><option value="emirian" >Emirian</option><option value="equatorial guinean" >Equatorial guinean</option><option value="eritrean" >Eritrean</option><option value="estonian" >Estonian</option><option value="ethiopian" >Ethiopian</option><option value="fijian" >Fijian</option><option value="filipino" >Filipino</option><option value="finnish" >Finnish</option><option value="french" >French</option><option value="gabonese" >Gabonese</option><option value="gambian" >Gambian</option><option value="georgian" >Georgian</option><option value="german" >German</option><option value="ghanaian" >Ghanaian</option><option value="greek" >Greek</option><option value="grenadian" >Grenadian</option><option value="guatemalan" >Guatemalan</option><option value="guinea-bissauan" >Guinea-bissauan</option><option value="guinean" >Guinean</option><option value="guyanese" >Guyanese</option><option value="haitian" >Haitian</option><option value="honduran" >Honduran</option><option value="hungarian" >Hungarian</option><option value="icelander" >Icelander</option><option value="indian" >Indian</option><option value="indonesian" >Indonesian</option><option value="iranian" >Iranian</option><option value="iraqi" >Iraqi</option><option value="irish" >Irish</option><option value="israeli" >Israeli</option><option value="italian" >Italian</option><option value="jamaican" >Jamaican</option><option value="japanese" >Japanese</option><option value="jordanian" >Jordanian</option><option value="kazakhstani" >Kazakhstani</option><option value="kenyan" >Kenyan</option><option value="korean" >Korean</option><option value="kosovar" >Kosovar</option><option value="kuwaiti" >Kuwaiti</option><option value="kyrgyzstani" >Kyrgyzstani</option><option value="laotian" >Laotian</option><option value="latvian" >Latvian</option><option value="lebanese" >Lebanese</option><option value="lesotho" >Lesotho</option><option value="liberian" >Liberian</option><option value="libyan" >Libyan</option><option value="lithuanian" >Lithuanian</option><option value="luxembourger" >Luxembourger</option><option value="macedonian" >Macedonian</option><option value="malagasy" >Malagasy</option><option value="malawian" >Malawian</option><option value="malaysian" selected>Malaysian</option><option value="malian" >Malian</option><option value="maltese" >Maltese</option><option value="marshallese" >Marshallese</option><option value="mauritian" >Mauritian</option><option value="mexican" >Mexican</option><option value="micronesian" >Micronesian</option><option value="moldovan" >Moldovan</option><option value="monacan" >Monacan</option><option value="mongolian" >Mongolian</option><option value="moroccan" >Moroccan</option><option value="mozambican" >Mozambican</option><option value="namibian" >Namibian</option><option value="nauruan" >Nauruan</option><option value="nepalese" >Nepalese</option><option value="new zealander" >New zealander</option><option value="nicaraguan" >Nicaraguan</option><option value="nigerien" >Nigerien</option><option value="nigerian" >Nigerian</option><option value="norwegian" >Norwegian</option><option value="omani" >Omani</option><option value="pakistani" >Pakistani</option><option value="palauan" >Palauan</option><option value="palestinian" >Palestinian</option><option value="panamanian" >Panamanian</option><option value="papua new guinean" >Papua new guinean</option><option value="paraguayan" >Paraguayan</option><option value="peruvian" >Peruvian</option><option value="philosopher" >Philosopher</option><option value="polish" >Polish</option><option value="portuguese" >Portuguese</option><option value="qatarian" >Qatarian</option><option value="romanian" >Romanian</option><option value="russian" >Russian</option><option value="rwandan" >Rwandan</option><option value="saint lucian" >Saint lucian</option><option value="salvadoran" >Salvadoran</option><option value="samoan" >Samoan</option><option value="san marinese" >San marinese</option><option value="sao tomean" >Sao tomean</option><option value="saudi" >Saudi</option><option value="scottish" >Scottish</option><option value="senegalese" >Senegalese</option><option value="serbian" >Serbian</option><option value="singaporean" >Singaporean</option><option value="slovak" >Slovak</option><option value="slovenian" >Slovenian</option><option value="solomon islander" >Solomon islander</option><option value="somali" >Somali</option><option value="south african" >South african</option><option value="spanish" >Spanish</option><option value="sri lankan" >Sri lankan</option><option value="sudanese" >Sudanese</option><option value="surinamese" >Surinamese</option><option value="swedish" >Swedish</option><option value="swiss" >Swiss</option><option value="syrian" >Syrian</option><option value="taiwanese" >Taiwanese</option><option value="tajikistani" >Tajikistani</option><option value="tanzanian" >Tanzanian</option><option value="thai" >Thai</option><option value="togolese" >Togolese</option><option value="tongan" >Tongan</option><option value="trinidadian" >Trinidadian</option><option value="tunisian" >Tunisian</option><option value="turkish" >Turkish</option><option value="turkmen" >Turkmen</option><option value="ugandan" >Ugandan</option><option value="ukrainian" >Ukrainian</option><option value="emirian" >Emirian</option><option value="united kingdom" >United kingdom</option><option value="united states" >United states</option><option value="uruguayan" >Uruguayan</option><option value="uzbekistani" >Uzbekistani</option><option value="vanuatuan" >Vanuatuan</option><option value="vatican" >Vatican</option><option value="venezuelan" >Venezuelan</option><option value="vietnamese" >Vietnamese</option><option value="yemeni" >Yemeni</option><option value="zambian" >Zambian</option><option value="zimbabwean" >Zimbabwean</option>                            
                    </select>
                </div>


                <div class="profile-field">
                    <span>Contact</span>
                    <input class="profile-input" type="text" name="player_contact" value="<?= htmlspecialchars($_POST['player_contact'] ?? '') ?>"  required >
                </div>


                <div class="profile-field">
                    <span>Email</span>
                    <input class="profile-input" type="email" name="player_email" value="<?= htmlspecialchars($_POST['player_email'] ?? '') ?>" >
                </div>


                <div class="profile-field">
                    <span>Password</span>
                    <input class="profile-input" type="password" name="player_password" value="" required >
                </div>
            </div>

        </section>


        <section class="profile-section">

            <div class="profile-section-heading">
                <h2>Player Ranking Information</h2>
                <p>Enter the player's ranking information.</p>
            </div>


            <div class="profile-grid">

                <div class="profile-field">
                    <span>ASF Membership No.</span>
                    <input class="profile-input" type="text" name="asf_member_no" value="<?= htmlspecialchars($_POST['asf_member_no'] ?? '') ?>" >
                </div>


                <div class="profile-field">
                    <span>SPIN No.</span>
                    <input class="profile-input" type="text" name="spin_number" value="<?= htmlspecialchars($_POST['spin_number'] ?? '') ?>" >
                </div>


                <div class="profile-field">
                    <span>National Ranking</span>
                    <input class="profile-input" type="text" name="national_ranking" value="<?= htmlspecialchars($_POST['national_ranking'] ?? '') ?>" >
                </div>


                <div class="profile-field">
                    <span>AJSS Ranking</span>
                    <input class="profile-input" type="text" name="ajss_ranking" value="<?= htmlspecialchars($_POST['ajss_ranking'] ?? '') ?>" >
                </div>


                <div class="profile-field">
                    <span>PSA World Ranking</span>
                    <input class="profile-input" type="text" name="psa_ranking" value="<?= htmlspecialchars($_POST['psa_ranking'] ?? '') ?>" >
                </div>

                <div class="profile-field">
                    <span>Training ID</span>
                    <input class="profile-input" type="number" name="trainingID" value="<?= htmlspecialchars($_POST['trainingID'] ?? '') ?>" >
                </div>

            </div>

        </section>


        <section class="profile-section">

            <div class="profile-section-heading">
                <h2>Account Status</h2>
                <p>New players will be created as active players.</p>
            </div>

            <div class="profile-grid">
                <div class="profile-field">
                    <span>Status</span>
                    <input class="profile-input" type="text" value="Active" readonly >
                </div>
            </div>

        </section>


        <div class="profile-form-actions">

            <a class="cancel-btn" href="admin_player_lists.php">
                Cancel
            </a>

            <button class="save-btn" type="submit">
                <i class="fa fa-save"></i>
                Create Player
            </button>

        </div>

    </form>

</main>


</body>
</html>