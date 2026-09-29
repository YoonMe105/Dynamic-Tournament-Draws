<?php
require_once "player_functions.php";

$tournamentID = isset($_GET['tournamentID']) ? intval($_GET['tournamentID']) : 0;

$tournament = getTournament($conn, $tournamentID);

if (!$tournament) {
    die("Tournament not found.");
}

$player = getPlayer($conn, $playerID);

if (!$player) {
    die("Player not found.");
}


/*
|--------------------------------------------------------------------------
| Already Registered / Closed
|--------------------------------------------------------------------------
*/

$registration = getRegistration($conn, $tournamentID, $playerID);

if ($registration) {

    if (canPay($registration) && empty($registration['payment_proof'])) {
        header("Location: tournament_payment.php?registrationID=" . $registration['registrationID']);
    } else {
        header("Location: tournament_details.php?tournamentID=" . $tournamentID);
    }

    exit();
}

$categories = getCategories($conn, $tournamentID);

if (!isRegistrationOpen($tournament) || empty($categories)) {
    header("Location: tournament_details.php?tournamentID=" . $tournamentID);
    exit();
}


/*
|--------------------------------------------------------------------------
| Age on Cut-off Date
|--------------------------------------------------------------------------
*/

$ageOnCutoff = null;

$dobTime = strtotime($player['player_dob'] ?? '');
$cutoffTime = strtotime($tournament['tournament_age_cutoff'] ?? '');

// '0000-00-00' means no date was entered
$hasCutoff = $cutoffTime !== false && strpos((string) $tournament['tournament_age_cutoff'], '0000') !== 0;
$hasDob = $dobTime !== false && strpos((string) $player['player_dob'], '0000') !== 0;

if ($hasDob && $hasCutoff) {
    $ageOnCutoff = (new DateTime(date('Y-m-d', $dobTime)))->diff(new DateTime(date('Y-m-d', $cutoffTime)))->y;
}


/*
|--------------------------------------------------------------------------
| Age Eligibility
|--------------------------------------------------------------------------
| BU13 / GU13 = the player must be under 13 on the age cut-off date.
| Playing up (e.g. an 11-year-old in BU13) is allowed.
| Categories without an age (Men / Women) and tournaments without a
| cut-off date are open to every age. If the tournament has a cut-off
| date but the player has no date of birth, age categories are blocked.
*/

function categoryMaxAge($code)
{
    return preg_match('/^[BG]U(\d+)$/', $code, $matches) ? (int) $matches[1] : null;
}


/*
| Gender of a category: BU / Men = male, GU / Women = female, else null
*/
function categoryGender($code)
{
    if (preg_match('/^BU\d+$/', $code) || strcasecmp($code, 'Men') === 0) {
        return 'male';
    }

    if (preg_match('/^GU\d+$/', $code) || strcasecmp($code, 'Women') === 0) {
        return 'female';
    }

    return null;
}

$playerGender = strtolower(trim($player['player_gender'] ?? ''));
$playerGender = in_array($playerGender, ['male', 'female'], true) ? $playerGender : null;


/*
|--------------------------------------------------------------------------
| Category Eligibility (gender + age)
|--------------------------------------------------------------------------
| $categoryEligible[code] = true/false
| $categoryReason[code]   = why it can't be chosen ("girls only", "over age", ...)
| $recommendedCategory    = the category that fits the player best: their
|                           own gender and the youngest age group they are
|                           still under on the cut-off date
*/

$categoryEligible = [];
$categoryReason = [];

foreach ($categories as $code => $name) {

    $gender = categoryGender($code);
    $maxAge = categoryMaxAge($code);

    $reason = '';

    if ($playerGender !== null && $gender !== null && $gender !== $playerGender) {

        $isAdult = strcasecmp($code, 'Men') === 0 || strcasecmp($code, 'Women') === 0;

        $reason = $gender === 'male'
            ? ($isAdult ? 'men only' : 'boys only')
            : ($isAdult ? 'women only' : 'girls only');

    } elseif ($maxAge !== null && $hasCutoff && !$hasDob) {

        $reason = 'date of birth needed';

    } elseif ($maxAge !== null && $hasCutoff && $ageOnCutoff >= $maxAge) {

        $reason = 'over age';
    }

    $categoryEligible[$code] = $reason === '';
    $categoryReason[$code] = $reason;
}

$recommendedCategory = null;

foreach ($categories as $code => $name) {

    if (!$categoryEligible[$code]) {
        continue;
    }

    $maxAge = categoryMaxAge($code) ?? PHP_INT_MAX;

    if ($recommendedCategory === null || $maxAge < (categoryMaxAge($recommendedCategory) ?? PHP_INT_MAX)) {
        $recommendedCategory = $code;
    }
}


/*
|--------------------------------------------------------------------------
| Registration Fields
|--------------------------------------------------------------------------
| registration_field is a ";" separated list. Older tournaments store
| column names (player_tshirt, player_ic), newer ones store the keys from
| admin_create_tournament.php (player_tshirt_size, player_national_id),
| and some store labels (T-Shirt Size). All of them are mapped here.
|
| "profile" fields are shown read-only from the player's profile.
| "input" fields are filled in by the player and saved in tournament_register.
*/

$profile_fields = [
    'Full Name' => ['player_full_name', ['player_full_name', 'Full Name']],
    'First Name' => ['player_first_name', ['player_first_name', 'First Name']],
    'Last Name' => ['player_last_name', ['player_last_name', 'Last Name']],
    'National ID' => ['player_nationalid', ['player_ic', 'player_national_id', 'National ID']],
    'Passport' => ['player_passport', ['player_passport', 'Passport']],
    'Date of Birth' => ['player_dob', ['player_dob', 'player_date_of_birth', 'Date of Birth']],
    'Gender' => ['player_gender', ['player_gender', 'Gender']],
    'Nationality' => ['player_nationality', ['player_nationality', 'Nationality']],
    'Contact Number' => ['player_contact', ['player_contact_number', 'Contact Number']],
    'Email' => ['player_email', ['player_email', 'Email']],
    'National Ranking' => ['national_ranking', ['national_ranking', 'player_national_ranking', 'National Ranking']],
    'AJSS Ranking' => ['ajss_ranking', ['ajss_ranking', 'player_ajss_ranking', 'AJSS Ranking']],
    '(PSA) World Ranking' => ['world_ranking', ['player_psa_world_ranking', '(PSA) World Ranking']],
    'ASF Membership No' => ['asf_member_no', ['asf_member_no', 'player_asf_membership_no', 'ASF Membership No']],
    'WSF Spin No' => ['spin_number', ['spin_number', 'player_wsf_spin_no', 'WSF Spin No']]
];

$input_fields = [
    'chinese_name' => ['player_chinese_name', 'Chinese Name'],
    'tshirt' => ['player_tshirt', 'player_tshirt_size', 'T-Shirt Size'],
    'accommodation' => ['accommodation', 'player_accommodation_transportation', 'Team Accommodation / Transportation'],
    'latest_results' => ['player_latest_results', 'Latest Results'],
    'attachments' => ['player_attachments', 'player_other_attachments', 'Other Attachments']
];

$requested_fields = array_filter(array_map('trim', explode(';', $tournament['registration_field'] ?? '')));

$show_profile_fields = [];

foreach ($profile_fields as $label => [$column, $aliases]) {

    if (array_intersect($aliases, $requested_fields)) {
        $show_profile_fields[$label] = trim((string) ($player[$column] ?? ''));
    }
}

$show_input = [];

foreach ($input_fields as $name => $aliases) {
    $show_input[$name] = (bool) array_intersect($aliases, $requested_fields);
}


/*
|--------------------------------------------------------------------------
| T-Shirt Sizes
|--------------------------------------------------------------------------
| Stored as "S;M;L" or "S,M,L".
*/

$tshirt_sizes = array_values(array_unique(array_filter(array_map(
    'trim',
    preg_split('/[;,]/', $tournament['tournament_tshirt_size'] ?? '')
))));

if (empty($tshirt_sizes)) {
    $show_input['tshirt'] = false;
}


/*
|--------------------------------------------------------------------------
| Form Submission
|--------------------------------------------------------------------------
*/

$error_message = '';

$form = [
    'category' => '',
    'category_remarks' => '',
    'chinese_name' => '',
    'tshirt' => '',
    'accommodation' => '',
    'latest_results' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($form as $key => $value) {
        $form[$key] = trim($_POST[$key] ?? '');
    }

    if ($playerGender === null) {

        $error_message = 'Please update your gender in My Profile before registering.';

    } elseif (!isset($categories[$form['category']])) {

        $error_message = 'Please select a valid category.';

    } elseif (in_array($categoryReason[$form['category']], ['boys only', 'girls only', 'men only', 'women only'], true)) {

        $error_message = $form['category'] . ' is for ' . str_replace(' only', '', $categoryReason[$form['category']])
            . ' only. Please choose a category for your gender.';

    } elseif (!$categoryEligible[$form['category']] && !$hasDob) {

        $error_message = 'Please add your date of birth in My Profile before registering for an age category.';

    } elseif (!$categoryEligible[$form['category']]) {

        $error_message = 'You are ' . $ageOnCutoff . ' on the age cut-off date ('
            . formatCutoffDate($tournament['tournament_age_cutoff']) . '), so you are too old for '
            . $form['category'] . '. Players must be under ' . categoryMaxAge($form['category']) . '.';

    } elseif ($show_input['tshirt'] && !in_array($form['tshirt'], $tshirt_sizes, true)) {

        $error_message = 'Please select a T-shirt size.';

    } elseif ($show_input['accommodation'] && !in_array($form['accommodation'], ['Yes', 'No'], true)) {

        $error_message = 'Please choose whether you need accommodation / transportation.';

    } elseif ($show_input['latest_results'] && $form['latest_results'] === '') {

        $error_message = 'Please enter your latest results.';
    }


    /*
    | Attachment
    */

    $attachment = null;

    if ($error_message === '' && $show_input['attachments']) {
        [$attachment, $error_message] = uploadFile('attachment', 'registrations', 'registration');
    }


    /*
    | Insert Registration
    */

    if ($error_message === '') {

        $categoryRemarks = $form['category_remarks'] !== '' ? $form['category_remarks'] : null;
        $chineseName = $show_input['chinese_name'] && $form['chinese_name'] !== '' ? $form['chinese_name'] : null;
        $tshirt = $show_input['tshirt'] ? $form['tshirt'] : null;
        $accommodation = $show_input['accommodation'] ? $form['accommodation'] : null;
        $latestResults = $show_input['latest_results'] ? $form['latest_results'] : null;

        $paymentStatus = 'NOT PAID';
        $endorsement = 'NOT ENDORSED';

        $stmt = $conn->prepare("
            INSERT INTO tournament_register (
                category_registered,
                category_remarks,
                player_chinese_name,
                player_tshirt,
                accommodation,
                player_latest_results,
                player_attachments,
                payment_status,
                endorsement,
                tournamentID,
                playerID
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            die("Database error: " . $conn->error);
        }

        $stmt->bind_param(
            "sssssssssis",
            $form['category'],
            $categoryRemarks,
            $chineseName,
            $tshirt,
            $accommodation,
            $latestResults,
            $attachment,
            $paymentStatus,
            $endorsement,
            $tournamentID,
            $playerID
        );

        if ($stmt->execute()) {

            $registrationID = $conn->insert_id;

            $stmt->close();

            header("Location: tournament_payment.php?registrationID=" . $registrationID . "&registered=1");
            exit();
        }

        $error_message = 'Something went wrong. Please try again.';

        $stmt->close();

        if ($attachment !== null) {
            @unlink('../uploads/registrations/' . $attachment);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Register | <?= htmlspecialchars($tournament['tournament_name']) ?></title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />

    <!-- Navbar CSS -->
    <link rel="stylesheet" href="./assets/css/navbar.css?v=<?= filemtime(__DIR__ . '/assets/css/navbar.css') ?>" type="text/css" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="./assets/css/tournament_details.css?v=<?= filemtime(__DIR__ . '/assets/css/tournament_details.css') ?>" type="text/css" />
</head>


<body>


    <?php include "player_navbar.php"; ?>


    <main class="container narrow">

        <a href="tournament_details.php?tournamentID=<?= $tournamentID ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Tournament
        </a>


        <!-- STEPS -->

        <div class="steps">
            <div class="step active"><span>1</span> Registration Details</div>
            <div class="step-line"></div>
            <div class="step"><span>2</span> Pay Entry Fee</div>
        </div>


        <section class="card">

            <h1 class="page-title"><?= htmlspecialchars($tournament['tournament_name']) ?></h1>

            <div class="registration-summary tournament-summary">

                <div class="summary-row">
                    <span>Registration Deadline</span>
                    <strong><?= formatDate($tournament['tournament_deadline']) ?></strong>
                </div>

                <div class="summary-row">
                    <span>Age Cut-off Date</span>
                    <strong><?= formatCutoffDate($tournament['tournament_age_cutoff']) ?></strong>
                </div>

                <?php if ($ageOnCutoff !== null): ?>

                    <div class="summary-row">
                        <span>Your Age on Cut-off Date</span>
                        <strong><?= $ageOnCutoff ?></strong>
                    </div>

                <?php endif; ?>

                <?php $myFee = playerFee($tournament, $player); ?>

                <div class="summary-row">
                    <span>Your Entry Fee<?= $myFee['currency'] !== 'OTHER' ? ' (' . $myFee['currency'] . ')' : '' ?></span>
                    <strong><?= htmlspecialchars($myFee['fee'] ?: '-') ?></strong>
                </div>

            </div>


            <?php if ($error_message !== ''): ?>

                <div class="message error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>

            <?php endif; ?>


            <form
                method="POST"
                action="tournament_register.php?tournamentID=<?= $tournamentID ?>"
                enctype="multipart/form-data"
                id="registrationForm"
            >


                <!-- PROFILE INFORMATION -->

                <?php if (!empty($show_profile_fields)): ?>

                    <div class="profile-block">

                        <div class="profile-block-title">
                            From your profile
                        </div>

                        <?php foreach ($show_profile_fields as $label => $value): ?>

                            <div class="summary-row">

                                <span><?= htmlspecialchars($label) ?></span>

                                <?php if ($value !== ''): ?>

                                    <strong><?= htmlspecialchars($value) ?></strong>

                                <?php else: ?>

                                    <strong class="missing">Missing</strong>

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>

                        <?php if (in_array('', $show_profile_fields, true)): ?>

                            <p class="hint">
                                Some information is missing. Please update
                                <a href="my_profile.php">your profile</a>
                                or contact the organiser.
                            </p>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>


                <!-- CATEGORY -->

                <div class="form-group">

                    <label for="category">Category <span class="required">*</span></label>

                    <?php
                    // Pre-select the recommended category the first time the form is shown
                    $selectedCategory = $form['category'] !== '' ? $form['category'] : ($recommendedCategory ?? '');
                    ?>

                    <select id="category" name="category" required>

                        <option value="">Select category</option>


                        <!-- Categories the player can enter, first -->

                        <?php if (in_array(true, $categoryEligible, true)): ?>

                            <optgroup label="Categories you can enter">

                                <?php
                                // Youngest age group first, so the recommended category is on top
                                $eligibleCodes = array_keys(array_filter($categoryEligible));

                                usort($eligibleCodes, function ($a, $b) {
                                    return (categoryMaxAge($a) ?? PHP_INT_MAX) <=> (categoryMaxAge($b) ?? PHP_INT_MAX);
                                });
                                ?>

                                <?php foreach ($eligibleCodes as $code): ?>

                                    <?php $name = $categories[$code]; ?>

                                    <option
                                        value="<?= htmlspecialchars($code) ?>"
                                        <?= $selectedCategory === $code ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($code === $name ? $code : $code . ' - ' . $name) ?>
                                        <?= $code === $recommendedCategory ? ' (recommended)' : '' ?>
                                    </option>

                                <?php endforeach; ?>

                            </optgroup>

                        <?php endif; ?>


                        <!-- Everything else, greyed out with the reason -->

                        <?php if (in_array(false, $categoryEligible, true)): ?>

                            <optgroup label="Other categories">

                                <?php foreach ($categories as $code => $name): ?>

                                    <?php if ($categoryEligible[$code]) { continue; } ?>

                                    <option value="<?= htmlspecialchars($code) ?>" disabled>
                                        <?= htmlspecialchars($code === $name ? $code : $code . ' - ' . $name) ?>
                                        (<?= htmlspecialchars($categoryReason[$code]) ?>)
                                    </option>

                                <?php endforeach; ?>

                            </optgroup>

                        <?php endif; ?>

                    </select>

                    <?php if ($ageOnCutoff !== null): ?>

                        <small class="hint">
                            You are <strong><?= $ageOnCutoff ?></strong> on the age cut-off date
                            (<?= formatCutoffDate($tournament['tournament_age_cutoff']) ?>)<?= $recommendedCategory !== null
                                ? ', so your category is <strong>' . htmlspecialchars($recommendedCategory) . '</strong>. You can also play up in an older age group.'
                                : '.' ?>
                        </small>

                    <?php elseif ($recommendedCategory !== null && $playerGender !== null): ?>

                        <small class="hint">
                            Showing the categories for your gender first.
                        </small>

                    <?php elseif ($hasCutoff && !$hasDob): ?>

                        <small class="hint">
                            Add your date of birth in <a href="my_profile.php">My Profile</a>
                            to enter an age category.
                        </small>

                    <?php endif; ?>

                    <?php if ($playerGender === null): ?>

                        <small class="hint missing">
                            Your gender is missing. Please update it in
                            <a href="my_profile.php">My Profile</a> before registering.
                        </small>

                    <?php endif; ?>

                    <?php if (!in_array(true, $categoryEligible, true)): ?>

                        <small class="hint missing">
                            There is no category in this tournament for your gender and age.
                        </small>

                    <?php endif; ?>

                </div>


                <!-- CATEGORY REMARKS -->

                <div class="form-group">

                    <label for="category_remarks">Category Remarks</label>

                    <input
                        type="text"
                        id="category_remarks"
                        name="category_remarks"
                        maxlength="255"
                        value="<?= htmlspecialchars($form['category_remarks']) ?>"
                        placeholder="e.g. Playing up an age group (optional)"
                    >

                </div>


                <!-- CHINESE NAME -->

                <?php if ($show_input['chinese_name']): ?>

                    <div class="form-group">

                        <label for="chinese_name">Chinese Name</label>

                        <input
                            type="text"
                            id="chinese_name"
                            name="chinese_name"
                            maxlength="255"
                            value="<?= htmlspecialchars($form['chinese_name']) ?>"
                            placeholder="Leave blank if not applicable"
                        >

                    </div>

                <?php endif; ?>


                <!-- T-SHIRT -->

                <?php if ($show_input['tshirt']): ?>

                    <div class="form-group">

                        <label for="tshirt">T-Shirt Size <span class="required">*</span></label>

                        <select id="tshirt" name="tshirt" required>

                            <option value="">Select size</option>

                            <?php foreach ($tshirt_sizes as $size): ?>

                                <option
                                    value="<?= htmlspecialchars($size) ?>"
                                    <?= $form['tshirt'] === $size ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($size) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                <?php endif; ?>


                <!-- ACCOMMODATION -->

                <?php if ($show_input['accommodation']): ?>

                    <div class="form-group">

                        <label for="accommodation">
                            Need Team Accommodation / Transportation? <span class="required">*</span>
                        </label>

                        <select id="accommodation" name="accommodation" required>

                            <option value="">Select</option>

                            <option value="Yes" <?= $form['accommodation'] === 'Yes' ? 'selected' : '' ?>>Yes</option>

                            <option value="No" <?= $form['accommodation'] === 'No' ? 'selected' : '' ?>>No</option>

                        </select>

                    </div>

                <?php endif; ?>


                <!-- LATEST RESULTS -->

                <?php if ($show_input['latest_results']): ?>

                    <div class="form-group">

                        <label for="latest_results">Latest Results <span class="required">*</span></label>

                        <textarea
                            id="latest_results"
                            name="latest_results"
                            maxlength="1000"
                            rows="4"
                            placeholder="e.g. Quarter-finalist, Malaysian Junior Open 2026 (BU15)"
                            required
                        ><?= htmlspecialchars($form['latest_results']) ?></textarea>

                    </div>

                <?php endif; ?>


                <!-- ATTACHMENT -->

                <?php if ($show_input['attachments']): ?>

                    <div class="form-group">

                        <label for="attachment">Attachment</label>

                        <input
                            type="file"
                            id="attachment"
                            name="attachment"
                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                        >

                        <small class="hint">PDF, JPG, PNG or WEBP. Max 5MB.</small>

                    </div>

                <?php endif; ?>


                <button type="submit" class="primary-btn" id="submitBtn">
                    Continue to Payment
                    <i class="fa-solid fa-arrow-right"></i>
                </button>

            </form>

        </section>

    </main>


    <?php if ($playerGender === null): ?>

        <script>
            // No gender on the profile: ask the player to update it first
            if (confirm(
                "Your gender is missing from your profile.\n\n" +
                "Please update your profile before registering, so we can show the right categories for you.\n\n" +
                "Press OK to go to My Profile."
            )) {
                window.location.href = "my_profile.php";
            }
        </script>

    <?php endif; ?>


    <script>
        document.getElementById("registrationForm").addEventListener("submit", function () {

            const button = document.getElementById("submitBtn");

            button.disabled = true;
            button.textContent = "Submitting...";

        });
    </script>


</body>

</html>
