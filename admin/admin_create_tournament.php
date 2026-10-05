<?php
require_once '../db.php';
require_once 'admin_auth.php';

// Platform Admins and Tournament Organizers can both create tournaments
require_once '../fees.php';
require_once '../platform_fee.php';

$message = '';


$creatorID = $_SESSION["userid"];

/*
|--------------------------------------------------------------------------
| Category List
|--------------------------------------------------------------------------
*/

$categories = [
    'BU19' => [
        'name' => 'Boys Under 19 Open Championship',
        'age' => 19,
        'gender' => 'Boys'
    ],
    'GU19' => [
        'name' => 'Girls Under 19 Open Championship',
        'age' => 19,
        'gender' => 'Girls'
    ],
    'BU17' => [
        'name' => 'Boys Under 17 Open Championship',
        'age' => 17,
        'gender' => 'Boys'
    ],
    'GU17' => [
        'name' => 'Girls Under 17 Open Championship',
        'age' => 17,
        'gender' => 'Girls'
    ],
    'BU15' => [
        'name' => 'Boys Under 15 Open Championship',
        'age' => 15,
        'gender' => 'Boys'
    ],
    'GU15' => [
        'name' => 'Girls Under 15 Open Championship',
        'age' => 15,
        'gender' => 'Girls'
    ],
    'BU13' => [
        'name' => 'Boys Under 13 Open Championship',
        'age' => 13,
        'gender' => 'Boys'
    ],
    'GU13' => [
        'name' => 'Girls Under 13 Open Championship',
        'age' => 13,
        'gender' => 'Girls'
    ],
    'BU11' => [
        'name' => 'Boys Under 11 Open Championship',
        'age' => 11,
        'gender' => 'Boys'
    ],
    'GU11' => [
        'name' => 'Girls Under 11 Open Championship',
        'age' => 11,
        'gender' => 'Girls'
    ],
    'BU09' => [
        'name' => 'Boys Under 09 Open Championship',
        'age' => 9,
        'gender' => 'Boys'
    ],
    'GU09' => [
        'name' => 'Girls Under 09 Open Championship',
        'age' => 9,
        'gender' => 'Girls'
    ]
];


/*
|--------------------------------------------------------------------------
| PSA Categories
|--------------------------------------------------------------------------
| A tournament is either Junior (the categories above) or PSA (Men/Women).
*/

$psa_categories = [
    'MEN' => [
        'name' => 'Men',
        'age' => 0,
        'gender' => 'Men'
    ],
    'WOMEN' => [
        'name' => 'Women',
        'age' => 0,
        'gender' => 'Women'
    ]
];


require_once 'admin_countries.php';


/*
|--------------------------------------------------------------------------
| Registration Fields
|--------------------------------------------------------------------------
*/
$registration_options = [
    'player_full_name' => 'Full Name',
    'player_first_name' => 'First Name',
    'player_last_name' => 'Last Name',
    'player_national_id' => 'National ID',
    'player_passport' => 'Passport',
    'player_date_of_birth' => 'Date of Birth',
    'player_gender' => 'Gender',
    'player_nationality' => 'Nationality',
    'player_contact_number' => 'Contact Number',
    'player_national_ranking' => 'National Ranking',
    'player_ajss_ranking' => 'AJSS Ranking',
    'player_world_ranking' => 'World Ranking',
    'player_email' => 'Email',
    'player_category' => 'Category Registered',
    'player_chinese_name' => 'Chinese Name',
    'player_tshirt_size' => 'T-Shirt Size',
    'player_accommodation_transportation' => 'Team Accommodation / Transportation',
    'player_latest_results' => 'Latest Results',
    'player_other_attachments' => 'Other Attachments',
    'admin_remarks' => 'Admin Remarks or Notes',
    'player_asf_membership_no' => 'ASF Membership No',
    'player_wsf_spin_no' => 'WSF Spin No'
];


/*
|--------------------------------------------------------------------------
| Default Values
|--------------------------------------------------------------------------
*/

$tournament_name = '';
$tournament_startdate = '';
$tournament_enddate = '';
$tournament_deadline = '';
$tournament_description = '';
$tournament_location = '';
// Starts with the organizer's own country (set when their admin account was added)
$tournament_country = $currentAdmin['admin_country'] ?? '';
$tournament_fee = '';
$tournament_fee_usd = '';
$tournament_type = '';
$tournament_age_cutoff = '';
$tournament_tshirt_size = '';
$tournament_detail_link = '';

$category_type = '';
$selected_categories = [];
$selected_registration_fields = [];

$error_message = '';


/*
|--------------------------------------------------------------------------
| Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Get Form Values
    |--------------------------------------------------------------------------
    */

    $tournament_name = trim($_POST['tournament_name'] ?? '');

    $tournament_startdate = $_POST['tournament_startdate'] ?? '';

    $tournament_enddate = $_POST['tournament_enddate'] ?? '';

    $tournament_deadline = $_POST['tournament_deadline'] ?? '';

    $tournament_description = trim(
        $_POST['tournament_description'] ?? ''
    );

    $tournament_location = trim(
        $_POST['tournament_location'] ?? ''
    );

    $tournament_country = trim(
        $_POST['tournament_country'] ?? ''
    );

    $tournament_fee = trim(
        $_POST['tournament_fee'] ?? ''
    );

    $tournament_fee_usd = trim(
        $_POST['tournament_fee_usd'] ?? ''
    );

    $tournament_type = trim(
        $_POST['tournament_type'] ?? ''
    );

    $tournament_age_cutoff = $_POST['tournament_age_cutoff'] ?? '';

    $tournament_tshirt_size = trim(
        $_POST['tournament_tshirt_size'] ?? ''
    );

    $tournament_detail_link = trim(
        $_POST['tournament_detail_link'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    */

    $category_type = in_array($_POST['category_type'] ?? '', ['Junior', 'PSA'], true)
        ? $_POST['category_type']
        : '';

    $type_categories = [];

    if ($category_type === 'Junior') {
        $type_categories = $categories;
    } elseif ($category_type === 'PSA') {
        $type_categories = $psa_categories;
    }

    $selected_categories = $_POST['categories'] ?? [];

    if (!is_array($selected_categories)) {
        $selected_categories = [];
    }

    /*
    | Only allow valid category codes
    */

    $selected_categories = array_values(
        array_intersect(
            $selected_categories,
            array_keys($type_categories)
        )
    );


    /*
    |--------------------------------------------------------------------------
    | Registration Fields
    |--------------------------------------------------------------------------
    */

    $selected_registration_fields =
        $_POST['registration_fields'] ?? [];

    if (!is_array($selected_registration_fields)) {
        $selected_registration_fields = [];
    }

    /*
    | Only allow valid registration fields
    */

    $selected_registration_fields = array_values(
        array_intersect(
            $selected_registration_fields,
            array_keys($registration_options)
        )
    );


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($tournament_name === '') {

        $error_message = 'Tournament name is required.';

    } elseif ($tournament_startdate === '') {

        $error_message = 'Tournament start date is required.';

    } elseif ($tournament_enddate === '') {

        $error_message = 'Tournament end date is required.';

    } elseif ($tournament_deadline === '') {

        $error_message = 'Registration deadline is required.';

    } elseif ($tournament_description === '') {

        $error_message = 'Tournament description is required.';

    } elseif ($tournament_location === '') {

        $error_message = 'Tournament location is required.';

    } elseif (!in_array($tournament_country, $tournament_countries, true)) {

        $error_message = 'Please select the country hosting the tournament.';

    } elseif ($tournament_type === '') {

        $error_message = 'Tournament type is required.';

    } elseif (stripos($tournament_type, 'international') === false && $tournament_fee === '') {

        $error_message = 'Tournament fee (RM) is required.';

    } elseif (stripos($tournament_type, 'international') !== false && $tournament_fee === '' && $tournament_fee_usd === '') {

        $error_message = 'Please enter the RM fee, the USD fee, or both.';

    } elseif ($category_type === '') {

        $error_message = 'Please select a category type.';

    } elseif (empty($selected_categories)) {

        $error_message = 'Please select at least one category.';
    }


    /*
    |--------------------------------------------------------------------------
    | Registration Fields
    |--------------------------------------------------------------------------
    */

    $registration_field = implode(
        ';',
        $selected_registration_fields
    );


    /*
    |--------------------------------------------------------------------------
    | Create Tournament
    |--------------------------------------------------------------------------
    */

    if ($error_message === '') {

        /*
        | Convert datetime-local to MySQL DATETIME
        */

        $tournament_startdate = str_replace(
            'T',
            ' ',
            $tournament_startdate
        );

        $tournament_enddate = str_replace(
            'T',
            ' ',
            $tournament_enddate
        );

        $tournament_deadline = str_replace(
            'T',
            ' ',
            $tournament_deadline
        );


        /*
        |--------------------------------------------------------------------------
        | Optional Values
        |--------------------------------------------------------------------------
        */

        // One column: "Local: RM120.00, Foreign: USD 60.00" for international
        // RM only, USD only, or both for international tournaments
        $tournament_fee_saved =
            stripos($tournament_type, 'international') !== false
                ? combineFeeFields($tournament_fee, $tournament_fee_usd)
                : $tournament_fee;

        $tournament_age_cutoff =
            $tournament_age_cutoff !== ''
                ? $tournament_age_cutoff
                : null;

        $tournament_tshirt_size =
            $tournament_tshirt_size !== ''
                ? $tournament_tshirt_size
                : null;


        /*
        |--------------------------------------------------------------------------
        | Tournament Picture
        |--------------------------------------------------------------------------
        */

        $tournament_picture = null;

        if (
            isset($_FILES['tournament_picture']) &&
            $_FILES['tournament_picture']['error'] === UPLOAD_ERR_OK
        ) {

            $uploadDir = '../uploads/tournaments/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = basename(
                $_FILES['tournament_picture']['name']
            );

            $fileExtension = strtolower(
                pathinfo(
                    $fileName,
                    PATHINFO_EXTENSION
                )
            );

            $allowedExtensions = [
                'jpg',
                'jpeg',
                'png',
                'webp'
            ];

            if (
                !in_array(
                    $fileExtension,
                    $allowedExtensions
                )
            ) {

                $error_message =
                    'Invalid picture format. Please upload JPG, JPEG, PNG or WEBP.';

            } else {

                $newFileName =
                    uniqid('tournament_', true)
                    . '.'
                    . $fileExtension;

                if (
                    move_uploaded_file(
                        $_FILES['tournament_picture']['tmp_name'],
                        $uploadDir . $newFileName
                    )
                ) {

                    $tournament_picture =
                        $newFileName;

                } else {

                    $error_message =
                        'Failed to upload tournament picture.';
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Insert Tournament
        |--------------------------------------------------------------------------
        */

        if ($error_message === '') {

            $conn->begin_transaction();

            try {

                $sql = "
                    INSERT INTO tournament
                    (
                        tournament_startdate,
                        tournament_enddate,
                        tournament_name,
                        tournament_description,
                        tournament_detail_link,
                        tournament_location,
                        tournament_country,
                        tournament_fee,
                        tournament_deadline,
                        tournament_age_cutoff,
                        tournament_type,
                        tournament_tshirt_size,
                        tournament_picture,
                        registration_field,
                        creatorID
                    )
                    VALUES
                    (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                    )
                ";

                $stmt = $conn->prepare($sql);

                if (!$stmt) {
                    throw new Exception(
                        $conn->error
                    );
                }


                $stmt->bind_param(
                    "sssssssssssssss",
                    $tournament_startdate,
                    $tournament_enddate,
                    $tournament_name,
                    $tournament_description,
                    $tournament_detail_link,
                    $tournament_location,
                    $tournament_country,
                    $tournament_fee_saved,
                    $tournament_deadline,
                    $tournament_age_cutoff,
                    $tournament_type,
                    $tournament_tshirt_size,
                    $tournament_picture,
                    $registration_field,
                    $creatorID
                );


                if (!$stmt->execute()) {
                    throw new Exception(
                        $stmt->error
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Get New Tournament ID
                |--------------------------------------------------------------------------
                */

                $tournamentID = $conn->insert_id;

                $stmt->close();


                /*
                |--------------------------------------------------------------------------
                | Insert Categories
                |--------------------------------------------------------------------------
                */

                $categorySQL = "
                    INSERT INTO tournament_category
                    (
                        category_name,
                        age,
                        gender,
                        tournamentID
                    )
                    VALUES (?, ?, ?, ?)
                ";

                $categoryStmt =
                    $conn->prepare($categorySQL);

                if (!$categoryStmt) {
                    throw new Exception(
                        $conn->error
                    );
                }


                foreach ($selected_categories as $categoryCode) {

                    $categoryName =
                        $type_categories[$categoryCode]['name'];

                    $age =
                        $type_categories[$categoryCode]['age'];

                    $gender =
                        $type_categories[$categoryCode]['gender'];


                    $categoryStmt->bind_param(
                        "sisi",
                        $categoryName,
                        $age,
                        $gender,
                        $tournamentID
                    );


                    if (!$categoryStmt->execute()) {
                        throw new Exception(
                            $categoryStmt->error
                        );
                    }
                }


                $categoryStmt->close();


                /*
                |--------------------------------------------------------------------------
                | T_Software fee (Tournament Organizers only)
                |--------------------------------------------------------------------------
                | Registration stays closed until it's paid.
                */

                $needsPlatformFee = !isPlatformAdmin();

                if ($needsPlatformFee && !createTournamentPlatformFee($conn, $tournamentID, $creatorID)) {
                    throw new Exception('the platform fee could not be saved');
                }


                /*
                |--------------------------------------------------------------------------
                | Commit
                |--------------------------------------------------------------------------
                */

                $conn->commit();


                $message = 'Tournament created successfully!';

                header($needsPlatformFee
                    ? "Location: admin_platform_payment.php?id=" . $tournamentID . "&created=1"
                    : "Location: admin_view_tournaments.php?id=" . $tournamentID . "&created=1");
                exit;


            } catch (Exception $e) {

                $conn->rollback();

                /*
                | Delete uploaded image if database insert failed
                */

                if (
                    !empty($tournament_picture) &&
                    file_exists(
                        '../uploads/tournaments/'
                        . $tournament_picture
                    )
                ) {

                    unlink(
                        '../uploads/tournaments/'
                        . $tournament_picture
                    );
                }

                $error_message =
                    'Error creating tournament: '
                    . $e->getMessage();
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
    <title>Create Tournament</title>
    <link href="./assets/style.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="./assets/create_tournament.css?v=<?= filemtime(__DIR__ . '/assets/create_tournament.css') ?>">
</head>

<body>


<!-- ==========================================
     ADMIN NAVBAR
========================================== -->

<?php require_once 'admin_navbar.php'; ?>


<!-- ==========================================
     MAIN CONTENT
========================================== -->

<main class="page-container">

    <div class="create-wrapper">

        <a href="admin_index.php" class="back-link">&larr; Back to Tournaments</a>

        <div class="page-title">
            <h1>Create Tournament</h1>
            <p>Fill in the details below. Fields marked <span class="required">*</span> are required.</p>
        </div>


        <?php if (!empty($error_message)): ?>

            <div class="error-message">
                <?= htmlspecialchars($error_message) ?>
            </div>

        <?php endif; ?>


        <form method="POST" enctype="multipart/form-data" id="createForm">


            <!-- ==========================================
                 1. BASIC INFORMATION
            =========================================== -->

            <section class="form-section">

                <h2><span class="step">1</span> Basic Information</h2>

                <div class="form-group">
                    <label for="tournament_name">Tournament Name <span class="required">*</span></label>
                    <input type="text" id="tournament_name" name="tournament_name"
                           value="<?= htmlspecialchars($tournament_name) ?>"
                           placeholder="Enter tournament name" required>
                </div>

                <div class="form-group">
                    <label for="tournament_description">Description <span class="required">*</span></label>
                    <textarea id="tournament_description" name="tournament_description" rows="4"
                              placeholder="Enter tournament description..." required><?= htmlspecialchars($tournament_description) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="tournament_picture">Tournament Picture</label>
                    <input type="file" id="tournament_picture" name="tournament_picture"
                           accept=".jpg,.jpeg,.png,.webp" onchange="previewPicture(this)">
                    <small>JPG, JPEG, PNG or WEBP</small>
                    <img id="picturePreview" class="picture-preview" src="" alt="Picture preview" hidden>
                </div>

            </section>


            <!-- ==========================================
                 2. DATES
            =========================================== -->

            <section class="form-section">

                <h2><span class="step">2</span> Dates</h2>

                <div class="form-row">

                    <div class="form-group">
                        <label for="tournament_startdate">Start Date &amp; Time <span class="required">*</span></label>
                        <input type="datetime-local" id="tournament_startdate" name="tournament_startdate"
                               value="<?= htmlspecialchars(str_replace(' ', 'T', $tournament_startdate)) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="tournament_enddate">End Date &amp; Time <span class="required">*</span></label>
                        <input type="datetime-local" id="tournament_enddate" name="tournament_enddate"
                               value="<?= htmlspecialchars(str_replace(' ', 'T', $tournament_enddate)) ?>" required>
                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group">
                        <label for="tournament_deadline">Registration Deadline <span class="required">*</span></label>
                        <input type="datetime-local" id="tournament_deadline" name="tournament_deadline"
                               value="<?= htmlspecialchars(str_replace(' ', 'T', $tournament_deadline)) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="tournament_age_cutoff">Age Cut-off Date</label>
                        <input type="date" id="tournament_age_cutoff" name="tournament_age_cutoff"
                               value="<?= htmlspecialchars($tournament_age_cutoff) ?>">
                    </div>

                </div>

            </section>


            <!-- ==========================================
                 3. LOCATION & TYPE
            =========================================== -->

            <section class="form-section">

                <h2><span class="step">3</span> Location &amp; Type</h2>

                <div class="form-row">

                    <div class="form-group">
                        <label for="tournament_type">Tournament Type <span class="required">*</span></label>
                        <input type="text" id="tournament_type" name="tournament_type"
                               value="<?= htmlspecialchars($tournament_type) ?>"
                               placeholder="e.g. Local or International"
                               list="tournament_type_options" oninput="toggleUsdFee()" required>
                        <datalist id="tournament_type_options">
                            <option value="Local">
                            <option value="International">
                            <option value="National">
                        </datalist>
                    </div>

                    <div class="form-group">
                        <label for="tournament_country">Country <span class="required">*</span></label>
                        <select id="tournament_country" name="tournament_country" class="styled-select" required>
                            <option value="">Select country</option>
                            <?php foreach ($tournament_countries as $country): ?>
                                <option value="<?= htmlspecialchars($country) ?>" <?= $tournament_country === $country ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($country) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>

                <div class="form-group">
                    <label for="tournament_location">Location <span class="required">*</span></label>
                    <input type="text" id="tournament_location" name="tournament_location"
                           value="<?= htmlspecialchars($tournament_location) ?>"
                           placeholder="e.g. Kompleks Astaka, Petaling Jaya" required>
                </div>

            </section>


            <!-- ==========================================
                 4. FEES & EXTRAS
            =========================================== -->

            <section class="form-section">

                <h2><span class="step">4</span> Fees &amp; Extras</h2>

                <div class="form-row">

                    <div class="form-group">
                        <label for="tournament_fee">Entry Fee (RM) <span class="required rm-required">*</span></label>
                        <input type="text" id="tournament_fee" name="tournament_fee"
                               value="<?= htmlspecialchars($tournament_fee) ?>"
                               placeholder="e.g. RM 100.00" required>
                    </div>

                    <div class="form-group usd-fee-row" <?= stripos($tournament_type, 'international') === false ? 'hidden' : '' ?>>
                        <label for="tournament_fee_usd">Entry Fee (USD)</label>
                        <input type="text" id="tournament_fee_usd" name="tournament_fee_usd"
                               value="<?= htmlspecialchars($tournament_fee_usd) ?>"
                               placeholder="e.g. USD 60.00">
                    </div>

                </div>

                <small class="usd-fee-row form-note" <?= stripos($tournament_type, 'international') === false ? 'hidden' : '' ?>>
                    International tournament: fill in the RM fee, the USD fee, or both.
                    Malaysian players pay RM and foreign players pay USD.
                </small>

                <div class="form-row">

                    <div class="form-group">
                        <label for="tournament_tshirt_size">T-Shirt Sizes</label>
                        <input type="text" id="tournament_tshirt_size" name="tournament_tshirt_size"
                               value="<?= htmlspecialchars($tournament_tshirt_size) ?>"
                               placeholder="e.g. S, M, L, XL">
                    </div>

                    <div class="form-group">
                        <label for="tournament_detail_link">Detail Link</label>
                        <input type="url" id="tournament_detail_link" name="tournament_detail_link"
                               value="<?= htmlspecialchars($tournament_detail_link) ?>"
                               placeholder="https://example.com">
                    </div>

                </div>

            </section>


            <!-- ==========================================
                 5. CATEGORIES
            =========================================== -->

            <section class="form-section">

                <h2><span class="step">5</span> Categories <span class="required">*</span></h2>

                <div class="form-group">
                    <label for="category_type">Category Type <span class="required">*</span></label>
                    <select id="category_type" name="category_type" class="styled-select" onchange="showCategoryType()" required>
                        <option value="">Select category type</option>
                        <option value="Junior" <?= $category_type === 'Junior' ? 'selected' : '' ?>>Junior</option>
                        <option value="PSA" <?= $category_type === 'PSA' ? 'selected' : '' ?>>PSA</option>
                    </select>
                </div>

                <p class="category-hint" <?= $category_type !== '' ? 'hidden' : '' ?>>
                    Choose a category type to see its categories.
                </p>

                <?php foreach (['Junior' => $categories, 'PSA' => $psa_categories] as $group_type => $group_categories): ?>

                    <div class="checkbox-grid category-group"
                         data-category-type="<?= $group_type ?>"
                         <?= $group_type !== $category_type ? 'hidden' : '' ?>>

                        <?php foreach ($group_categories as $code => $category): ?>

                            <label class="checkbox-item">
                                <input type="checkbox" name="categories[]" value="<?= htmlspecialchars($code) ?>"
                                    <?= $group_type === $category_type && in_array($code, $selected_categories, true) ? 'checked' : '' ?>
                                    <?= $group_type !== $category_type ? 'disabled' : '' ?>>
                                <span><?= htmlspecialchars($category['name']) ?></span>
                            </label>

                        <?php endforeach; ?>

                    </div>

                <?php endforeach; ?>

            </section>


            <!-- ==========================================
                 6. REGISTRATION DETAILS
            =========================================== -->

            <section class="form-section">

                <h2><span class="step">6</span> Registration Details Needed</h2>

                <p class="section-description">Tick the information players must give when they register.</p>

                <div class="checkbox-grid">

                    <?php foreach ($registration_options as $field_name => $field_label): ?>

                        <label class="checkbox-item">
                            <input type="checkbox" name="registration_fields[]" value="<?= htmlspecialchars($field_name) ?>"
                                <?= in_array($field_name, $selected_registration_fields, true) ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($field_label) ?></span>
                        </label>

                    <?php endforeach; ?>

                </div>

            </section>


            <!-- ==========================================
                 BUTTONS
            =========================================== -->

            <div class="form-actions">
                <a href="admin_index.php" class="cancel-btn">Cancel</a>
                <button type="submit" class="create-btn" id="createBtn">Create Tournament</button>
            </div>

        </form>

    </div>

</main>


<script>
    /*
    |--------------------------------------------------------------------------
    | Category Type
    |--------------------------------------------------------------------------
    | Show the Junior (BU/GU) or PSA (Men/Women) categories. Checkboxes of
    | the hidden type are disabled so they are not submitted.
    */

    /*
    |--------------------------------------------------------------------------
    | USD Fee
    |--------------------------------------------------------------------------
    | Only international tournaments have a USD fee.
    */

    function toggleUsdFee() {

        const isInternational = document.getElementById('tournament_type')
            .value.toLowerCase().includes('international');

        const input = document.getElementById('tournament_fee_usd');

        document.querySelectorAll('.usd-fee-row').forEach(function (row) {
            row.hidden = !isInternational;
        });

        input.disabled = !isInternational;

        // International: RM only, USD only or both (checked when saving)
        input.required = false;
        document.getElementById('tournament_fee').required = !isInternational;
        document.querySelector('.rm-required').hidden = isInternational;
    }

    document.addEventListener('DOMContentLoaded', toggleUsdFee);


    function showCategoryType() {

        const typeSelect = document.getElementById('category_type');

        const hint = document.querySelector('.category-hint');

        if (hint) {
            hint.hidden = typeSelect.value !== '';
        }

        document.querySelectorAll('.category-group').forEach(function (group) {

            const isActive = group.dataset.categoryType === typeSelect.value;

            group.hidden = !isActive;

            group.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
                checkbox.disabled = !isActive;
            });

        });
    }

    document.addEventListener('DOMContentLoaded', showCategoryType);


    /*
    |--------------------------------------------------------------------------
    | Picture Preview
    |--------------------------------------------------------------------------
    */

    function previewPicture(input) {

        if (!input.files || !input.files[0]) {
            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {

            const preview = document.getElementById('picturePreview');

            preview.src = event.target.result;
            preview.hidden = false;
        };

        reader.readAsDataURL(input.files[0]);
    }


    // Stop double submits
    document.getElementById('createForm').addEventListener('submit', function () {

        const button = document.getElementById('createBtn');

        button.disabled = true;
        button.textContent = 'Creating...';
    });
</script>

</body>
</html>