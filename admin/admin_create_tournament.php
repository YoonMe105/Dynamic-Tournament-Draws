<?php
session_start();

require '../db.php';

$message = '';

if (!isset($_SESSION["userid"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

$creatorID = $_SESSION["userid"];

echo "Creator ID: " . htmlspecialchars($creatorID) . "<br>";

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
$tournament_fee = '';
$tournament_type = '';
$tournament_age_cutoff = '';
$tournament_tshirt_size = '';
$tournament_detail_link = '';

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

    $tournament_fee = trim(
        $_POST['tournament_fee'] ?? ''
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
            array_keys($categories)
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

    } elseif ($tournament_fee === '') {

        $error_message = 'Tournament fee is required.';

    } elseif ($tournament_type === '') {

        $error_message = 'Tournament type is required.';

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
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                    )
                ";

                $stmt = $conn->prepare($sql);

                if (!$stmt) {
                    throw new Exception(
                        $conn->error
                    );
                }


                $stmt->bind_param(
                    "ssssssssssssss",
                    $tournament_startdate,
                    $tournament_enddate,
                    $tournament_name,
                    $tournament_description,
                    $tournament_detail_link,
                    $tournament_location,
                    $tournament_fee,
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
                        $categories[$categoryCode]['name'];

                    $age =
                        $categories[$categoryCode]['age'];

                    $gender =
                        $categories[$categoryCode]['gender'];


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
                | Commit
                |--------------------------------------------------------------------------
                */

                $conn->commit();


                $message = 'Tournament created successfully!';

                echo "<script>
                    alert('Tournament created successfully!');
                    window.location.href = 'index.php';
                </script>";
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
    <link rel="stylesheet" href="./assets/create_tournament.css">
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

    <div class="page-title">

        <h1>Create Tournament</h1>

        <p>
            Create a new tournament and configure its categories
            and registration requirements.
        </p>

    </div>


    <?php if (!empty($message)): ?>

        <div class="message success-message">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <?php if (!empty($error_message)): ?>

        <div class="message error-message">
            <?= htmlspecialchars($error_message) ?>
        </div>

    <?php endif; ?>


    <form
        method="POST"
        enctype="multipart/form-data"
    >


        <!-- ==========================================
             TOURNAMENT INFORMATION
        =========================================== -->

        <div class="form-section">

            <h2>Tournament Information</h2>


            <div class="form-group">

                <label for="tournament_name">
                    Tournament Name <span>*</span>
                </label>

                <input
                    type="text"
                    id="tournament_name"
                    name="tournament_name"
                    value="<?= htmlspecialchars($tournament_name) ?>"
                    placeholder="Enter tournament name"
                    required
                >

            </div>


            <div class="form-row">


                <div class="form-group">

                    <label for="tournament_startdate">
                        Start Date & Time <span>*</span>
                    </label>

                    <input
                        type="datetime-local"
                        id="tournament_startdate"
                        name="tournament_startdate"
                        value="<?= htmlspecialchars(str_replace(' ', 'T', $tournament_startdate)) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="tournament_enddate">
                        End Date & Time <span>*</span>
                    </label>

                    <input
                        type="datetime-local"
                        id="tournament_enddate"
                        name="tournament_enddate"
                        value="<?= htmlspecialchars(str_replace(' ', 'T', $tournament_enddate)) ?>"
                        required
                    >

                </div>


            </div>


            <div class="form-group">

                <label for="tournament_deadline">
                    Registration Deadline <span>*</span>
                </label>

                <input
                    type="datetime-local"
                    id="tournament_deadline"
                    name="tournament_deadline"
                    value="<?= htmlspecialchars(str_replace(' ', 'T', $tournament_deadline)) ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label for="tournament_description">
                    Tournament Description <span>*</span>
                </label>

                <textarea
                    id="tournament_description"
                    name="tournament_description"
                    placeholder="Enter tournament description..."
                    required
                ><?= htmlspecialchars($tournament_description) ?></textarea>

            </div>


            <div class="form-group">

                <label for="tournament_location">
                    Location <span>*</span>
                </label>

                <input
                    type="text"
                    id="tournament_location"
                    name="tournament_location"
                    value="<?= htmlspecialchars($tournament_location) ?>"
                    placeholder="e.g. Kompleks Astaka, Petaling Jaya"
                    required
                >

            </div>


            <div class="form-row">

                <div class="form-group">

                    <label for="tournament_fee">
                        Tournament Fee <span>*</span>
                    </label>

                    <input
                        type="text"
                        id="tournament_fee"
                        name="tournament_fee"
                        value="<?= htmlspecialchars($tournament_fee) ?>"
                        placeholder="e.g. RM 100.00"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="tournament_type">
                        Tournament Type <span>*</span>
                    </label>

                    <input
                        type="text"
                        id="tournament_type"
                        name="tournament_type"
                        value="<?= htmlspecialchars($tournament_type) ?>"
                        placeholder="e.g. Squash"
                        required
                    >

                </div>


            </div>


            <div class="form-row">


                <div class="form-group">

                    <label for="tournament_age_cutoff">
                        Age Cutoff Date
                    </label>

                    <input
                        type="date"
                        id="tournament_age_cutoff"
                        name="tournament_age_cutoff"
                        value="<?= htmlspecialchars($tournament_age_cutoff) ?>"
                    >

                </div>


                <div class="form-group">

                    <label for="tournament_tshirt_size">
                        T-Shirt Size Information
                    </label>

                    <input
                        type="text"
                        id="tournament_tshirt_size"
                        name="tournament_tshirt_size"
                        value="<?= htmlspecialchars($tournament_tshirt_size) ?>"
                        placeholder="e.g. S, M, L, XL"
                    >

                </div>


            </div>


            <div class="form-group">

                <label for="tournament_detail_link">
                    Tournament Detail Link
                </label>

                <input
                    type="url"
                    id="tournament_detail_link"
                    name="tournament_detail_link"
                    value="<?= htmlspecialchars($tournament_detail_link) ?>"
                    placeholder="https://example.com"
                >

            </div>


            <div class="form-group">

                <label for="tournament_picture">
                    Tournament Picture
                </label>

                <input
                    type="file"
                    id="tournament_picture"
                    name="tournament_picture"
                    accept=".jpg,.jpeg,.png,.webp"
                >

                <small>
                    Allowed formats: JPG, JPEG, PNG, WEBP
                </small>

            </div>

        </div>


        <!-- ==========================================
             CATEGORIES
        =========================================== -->

        <div class="form-section">

            <h2>Categories</h2>

            <p class="section-description">
                Select the categories available for this tournament.
            </p>


            <div class="checkbox-grid">

                <?php foreach ($categories as $code => $category): ?>

                    <label class="checkbox-item">

                        <input
                            type="checkbox"
                            name="categories[]"
                            value="<?= htmlspecialchars($code) ?>"
                            <?= in_array($code, $selected_categories, true) ? 'checked' : '' ?>
                        >

                        <span>
                            <?= htmlspecialchars($category['name']) ?>
                        </span>

                    </label>

                <?php endforeach; ?>

            </div>

        </div>


        <!-- ==========================================
             REGISTRATION DETAILS
        =========================================== -->

        <div class="form-section">

            <h2>Registration Details Needed</h2>

            <p class="section-description">
                Select the information that players must provide
                when registering.
            </p>


            <div class="checkbox-grid">

                <?php foreach ($registration_options as $field_name => $field_label): ?>

                    <label class="checkbox-item">
                        <input type="checkbox" name="registration_fields[]" value="<?= htmlspecialchars($field_name) ?>" <?= in_array($field_name, $selected_registration_fields, true) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($field_label) ?></span>
                    </label>

                <?php endforeach; ?>

            </div>

        </div>


        <!-- ==========================================
             BUTTONS
        =========================================== -->

        <div class="form-actions">

            <a
                href="admin_index.php"
                class="cancel-btn"
            >
                Cancel
            </a>


            <button
                type="submit"
                class="create-btn"
            >
                Create Tournament
            </button>

        </div>


    </form>

</main>

</body>
</html>