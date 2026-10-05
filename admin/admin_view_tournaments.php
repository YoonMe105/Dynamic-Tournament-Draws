<?php
require_once '../db.php';
require_once 'admin_auth.php';
require_once 'admin_fee_banner.php';

// Any admin; Tournament Organizers are limited to their own tournaments below
require_once 'admin_countries.php';

require_once '../fees.php';



/*
|--------------------------------------------------------------------------
| Tournament ID
|--------------------------------------------------------------------------
*/

$tournamentID = isset($_GET['id']) ? intval($_GET['id']) : 0;

$edit_mode = isset($_GET['edit']) && $_GET['edit'] == '1';


/*
|--------------------------------------------------------------------------
| Registration Options
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
    'player_psa_world_ranking' => '(PSA) World Ranking',
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
| All Tournament Categories
|--------------------------------------------------------------------------
*/

$all_categories = [
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


/*
|--------------------------------------------------------------------------
| Saved Category Type
|--------------------------------------------------------------------------
| PSA if the tournament has Men/Women categories, Junior if it has other
| categories, and '' if it has no categories yet. Once a tournament has a
| type it can't be changed on this page.
*/

function getSavedCategoryType($conn, $tournamentID, $psa_categories)
{
    $stmt = $conn->prepare("
        SELECT category_name
        FROM tournament_category
        WHERE tournamentID = ?
    ");
    $stmt->bind_param("i", $tournamentID);
    $stmt->execute();

    $names = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'category_name');

    $stmt->close();

    if (array_intersect(array_column($psa_categories, 'name'), $names)) {
        return 'PSA';
    }

    return empty($names) ? '' : 'Junior';
}


/*
|--------------------------------------------------------------------------
| Get Tournament
|--------------------------------------------------------------------------
*/

$tournament = null;

if ($tournamentID > 0) {

    $sql = "SELECT *
            FROM tournament
            WHERE tournamentID = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $tournamentID);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $tournament = $result->fetch_assoc();
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Access
|--------------------------------------------------------------------------
| Tournament Organizers can open (and edit) only the tournaments they created.
*/

if ($tournament && !canAccessTournament($tournament['creatorID'])) {
    denyAdminAccess("You can only manage tournaments you created.");
}

if (!$tournament && !isPlatformAdmin()) {
    denyAdminAccess("You can only manage tournaments you created.");
}

$can_edit = true;


/*
|--------------------------------------------------------------------------
| Save Tournament Changes
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_changes'])) {

    // Only the tournament opened (and checked) above can be saved
    if (!$can_edit || !$tournament || intval($_POST['tournamentID'] ?? 0) !== $tournamentID) {
        denyAdminAccess("You don't have permission to edit this tournament.");
    }

    $tournamentID = intval($_POST['tournamentID']);

    $tournament_name = trim($_POST['tournament_name']);
    $tournament_description = trim($_POST['tournament_description']);
    $tournament_startdate = $_POST['tournament_startdate'];
    $tournament_enddate = $_POST['tournament_enddate'];
    $tournament_deadline = $_POST['tournament_deadline'];
    $tournament_age_cutoff = !empty($_POST['tournament_age_cutoff'])
        ? $_POST['tournament_age_cutoff']
        : null;

    $tournament_type = trim($_POST['tournament_type']);
    $tournament_location = trim($_POST['tournament_location']);

    $tournament_country = trim($_POST['tournament_country'] ?? '');

    if (!in_array($tournament_country, $tournament_countries, true)) {
        $tournament_country = null;
    }
    $tournament_fee = trim($_POST['tournament_fee']);

    // International: RM only, USD only, or both
    // (both are saved as "Local: RM120.00, Foreign: USD 60.00")
    $tournament_fee_usd = trim($_POST['tournament_fee_usd'] ?? '');

    if (stripos($tournament_type, 'international') !== false) {
        $tournament_fee = combineFeeFields($tournament_fee, $tournament_fee_usd);
    }
    $tournament_tshirt_size = trim($_POST['tournament_tshirt_size']);
    $tournament_detail_link = trim($_POST['tournament_detail_link']);

    $category_type = getSavedCategoryType($conn, $tournamentID, $psa_categories);

    // No type yet: use the one the admin picked
    if ($category_type === '') {
        $category_type = in_array($_POST['category_type'] ?? '', ['Junior', 'PSA'], true)
            ? $_POST['category_type']
            : '';
    }

    $type_categories = [];

    if ($category_type === 'Junior') {
        $type_categories = $all_categories;
    } elseif ($category_type === 'PSA') {
        $type_categories = $psa_categories;
    }

    $selected_categories = isset($_POST['categories']) && is_array($_POST['categories'])
        ? $_POST['categories']
        : [];

    // Only keep categories that belong to the chosen type
    $selected_categories = array_values(array_intersect(
        array_column($type_categories, 'name'),
        $selected_categories
    ));

    $selected_registration_fields = isset($_POST['registration_fields'])
        ? $_POST['registration_fields']
        : [];


    /*
    |--------------------------------------------------------------------------
    | Convert Registration Fields to String
    |--------------------------------------------------------------------------
    */

    $registration_field_string = implode(
        ';',
        $selected_registration_fields
    );


    /*
    |--------------------------------------------------------------------------
    | Start Transaction
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();

    try {

        /*
        | Update Tournament
        */

        $updateSQL = "UPDATE tournament
                      SET tournament_name = ?,
                          tournament_description = ?,
                          tournament_startdate = ?,
                          tournament_enddate = ?,
                          tournament_deadline = ?,
                          tournament_age_cutoff = ?,
                          tournament_type = ?,
                          tournament_location = ?,
                          tournament_country = ?,
                          tournament_fee = ?,
                          tournament_tshirt_size = ?,
                          tournament_detail_link = ?,
                          registration_field = ?
                      WHERE tournamentID = ?";

        $updateStmt = $conn->prepare($updateSQL);

        $updateStmt->bind_param(
            "sssssssssssssi",
            $tournament_name,
            $tournament_description,
            $tournament_startdate,
            $tournament_enddate,
            $tournament_deadline,
            $tournament_age_cutoff,
            $tournament_type,
            $tournament_location,
            $tournament_country,
            $tournament_fee,
            $tournament_tshirt_size,
            $tournament_detail_link,
            $registration_field_string,
            $tournamentID
        );

        if (!$updateStmt->execute()) {
            throw new Exception(
                "Failed to update tournament: " . $updateStmt->error
            );
        }

        $updateStmt->close();


        /*
        |--------------------------------------------------------------------------
        | Delete Existing Categories
        |--------------------------------------------------------------------------
        */

        $deleteCategorySQL = "DELETE FROM tournament_category
                              WHERE tournamentID = ?";

        $deleteCategoryStmt = $conn->prepare($deleteCategorySQL);
        $deleteCategoryStmt->bind_param("i", $tournamentID);

        if (!$deleteCategoryStmt->execute()) {
            throw new Exception(
                "Failed to update categories: " . $deleteCategoryStmt->error
            );
        }

        $deleteCategoryStmt->close();


        /*
        |--------------------------------------------------------------------------
        | Insert Selected Categories
        |--------------------------------------------------------------------------
        */

        if (!empty($selected_categories)) {

            $insertCategorySQL = "INSERT INTO tournament_category
                                  (tournamentID, category_name, age, gender)
                                  VALUES (?, ?, ?, ?)";

            $insertCategoryStmt = $conn->prepare($insertCategorySQL);

            $categories_by_name = array_column($type_categories, null, 'name');

            foreach ($selected_categories as $category_name) {

                $category_age = $categories_by_name[$category_name]['age'];
                $category_gender = $categories_by_name[$category_name]['gender'];

                $insertCategoryStmt->bind_param(
                    "isis",
                    $tournamentID,
                    $category_name,
                    $category_age,
                    $category_gender
                );

                if (!$insertCategoryStmt->execute()) {
                    throw new Exception(
                        "Failed to insert category: " .
                        $insertCategoryStmt->error
                    );
                }
            }

            $insertCategoryStmt->close();
        }


        /*
        |--------------------------------------------------------------------------
        | New Picture (optional)
        |--------------------------------------------------------------------------
        */

        if (
            isset($_FILES['tournament_picture']) &&
            $_FILES['tournament_picture']['error'] === UPLOAD_ERR_OK
        ) {

            $uploadDir = '../uploads/tournaments/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileExtension = strtolower(pathinfo(
                basename($_FILES['tournament_picture']['name']),
                PATHINFO_EXTENSION
            ));

            if (!in_array($fileExtension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                throw new Exception('Invalid picture format. Please upload JPG, JPEG, PNG or WEBP.');
            }

            $newFileName = uniqid('tournament_', true) . '.' . $fileExtension;

            if (!move_uploaded_file($_FILES['tournament_picture']['tmp_name'], $uploadDir . $newFileName)) {
                throw new Exception('Failed to upload tournament picture.');
            }

            $pictureStmt = $conn->prepare("UPDATE tournament SET tournament_picture = ? WHERE tournamentID = ?");
            $pictureStmt->bind_param("si", $newFileName, $tournamentID);
            $pictureStmt->execute();
            $pictureStmt->close();
        }

        $conn->commit();

        header(
            "Location: admin_view_tournaments.php?id=" .
            $tournamentID .
            "&updated=1"
        );

        exit;

    } catch (Exception $e) {

        $conn->rollback();

        $error_message = $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| Reload Tournament After Update
|--------------------------------------------------------------------------
*/

if ($tournamentID > 0) {

    $sql = "SELECT *
            FROM tournament
            WHERE tournamentID = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $tournamentID);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $tournament = $result->fetch_assoc();
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Selected Categories
|--------------------------------------------------------------------------
*/

$selected_categories = [];

if ($tournament) {

    $categorySQL = "SELECT category_name
                    FROM tournament_category
                    WHERE tournamentID = ?";

    $categoryStmt = $conn->prepare($categorySQL);
    $categoryStmt->bind_param("i", $tournamentID);
    $categoryStmt->execute();

    $categoryResult = $categoryStmt->get_result();

    while ($row = $categoryResult->fetch_assoc()) {
        $selected_categories[] = $row['category_name'];
    }

    $categoryStmt->close();
}


/*
|--------------------------------------------------------------------------
| Category Type
|--------------------------------------------------------------------------
*/

$category_type = $tournament
    ? getSavedCategoryType($conn, $tournamentID, $psa_categories)
    : '';

// The type can only be chosen while the tournament has no categories
$category_type_locked = $category_type !== '';


/*
|--------------------------------------------------------------------------
| Selected Registration Fields
|--------------------------------------------------------------------------
*/

$selected_registration_fields = [];

if ($tournament && !empty($tournament['registration_field'])) {

    $selected_registration_fields = explode(
        ';',
        $tournament['registration_field']
    );

    $selected_registration_fields = array_map(
        'trim',
        $selected_registration_fields
    );
}


/*
|--------------------------------------------------------------------------
| Display Helpers
|--------------------------------------------------------------------------
*/

// Formatted date, or "Not specified" when empty / 0000-00-00
function viewDate($value, $format)
{
    if (empty($value) || strpos($value, '0000-00-00') === 0) {
        return 'Not specified';
    }

    return date($format, strtotime($value));
}


$picture_src = './assets/tournament.jpg';
$status = ['label' => '', 'class' => ''];
$selected_category_chips = [];
$registration_selected_count = 0;

if ($tournament) {

    // New uploads store a file name; older tournaments store a path
    $picture = trim($tournament['tournament_picture'] ?? '');

    if ($picture !== '' && strtoupper($picture) !== 'NULL') {

        $path = strpos($picture, '../') === 0 ? $picture : '../uploads/tournaments/' . $picture;

        if (file_exists(__DIR__ . '/' . $path)) {
            $picture_src = $path;
        }
    }


    // Registration open / closed / ongoing / finished
    $now = time();
    $start = strtotime($tournament['tournament_startdate']);
    $end = strtotime($tournament['tournament_enddate']);
    $deadline = strtotime($tournament['tournament_deadline']);

    if ($deadline && $deadline >= $now) {
        $status = ['label' => 'Registration Open', 'class' => 'open'];
    } elseif ($start && $start > $now) {
        $status = ['label' => 'Registration Closed', 'class' => 'closed'];
    } elseif ($end && $end >= $now) {
        $status = ['label' => 'Ongoing', 'class' => 'ongoing'];
    } else {
        $status = ['label' => 'Finished', 'class' => 'finished'];
    }


    // Selected categories as short codes (BU15, GU13, MEN), in list order
    foreach (array_merge($all_categories, $psa_categories) as $code => $category) {
        if (in_array($category['name'], $selected_categories, true)) {
            $selected_category_chips[$code] = $category['name'];
        }
    }

    // Categories saved under another name
    foreach ($selected_categories as $name) {
        if (!in_array($name, $selected_category_chips, true)) {
            $selected_category_chips[$name] = $name;
        }
    }

    $registration_selected_count = count(array_intersect(
        array_keys($registration_options),
        $selected_registration_fields
    ));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tournament Details | T_Software</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

    <link rel="stylesheet" href="./assets/style.css">
    <link rel="stylesheet" href="./assets/view_tournament.css?v=<?= filemtime(__DIR__ . '/assets/view_tournament.css') ?>">

</head>


<body>


    <?php require_once 'admin_navbar.php'; ?>


    <main class="page-container">

        <?php if ($tournament): ?>

            <a href="admin_each_tournament.php?id=<?= (int)$tournamentID ?>" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Tournament
            </a>


            <?php platformFeeBanner($conn, $tournamentID); ?>

            <?php if (isset($_GET['created'])): ?>

                <div class="update-message">
                    <i class="fa-solid fa-circle-check"></i>
                    Tournament created successfully.
                </div>

            <?php endif; ?>


            <?php if (isset($_GET['updated'])): ?>

                <div class="update-message">
                    <i class="fa-solid fa-circle-check"></i>
                    Tournament updated successfully.
                </div>

            <?php endif; ?>


            <?php if (isset($error_message)): ?>

                <div class="error-message">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>

            <?php endif; ?>


            <form
                method="POST"
                action="admin_view_tournaments.php?id=<?= (int)$tournamentID ?><?= $edit_mode ? '&edit=1' : '' ?>"
                enctype="multipart/form-data"
                class="<?= $edit_mode ? 'is-editing' : '' ?>"
            >

                <input type="hidden" name="tournamentID" value="<?= (int)$tournamentID ?>">


                <!-- =========================================================
                    HEADER: picture | name, badges, actions
                ========================================================== -->

                <section class="hero-card">

                    <div class="hero-picture">

                        <img id="picturePreview" src="<?= htmlspecialchars($picture_src) ?>" alt="Tournament picture">

                        <?php if ($edit_mode): ?>

                            <label for="tournament_picture" class="change-picture-btn">
                                <i class="fa-solid fa-camera"></i>
                                Change picture
                            </label>

                            <input
                                type="file"
                                id="tournament_picture"
                                name="tournament_picture"
                                accept=".jpg,.jpeg,.png,.webp"
                                onchange="previewPicture(this)"
                                hidden
                            >

                        <?php endif; ?>

                    </div>


                    <div class="hero-info">

                        <div class="hero-badges">

                            <span class="badge status-<?= $status['class'] ?>">
                                <?= $status['label'] ?>
                            </span>

                            <span class="badge">
                                <?= htmlspecialchars(strtoupper($tournament['tournament_type'])) ?>
                            </span>

                            <?php if ($category_type !== ''): ?>
                                <span class="badge"><?= htmlspecialchars($category_type) ?></span>
                            <?php endif; ?>

                            <?php if (!empty($tournament['tournament_country'])): ?>
                                <span class="badge">
                                    <i class="fa-solid fa-flag"></i>
                                    <?= htmlspecialchars($tournament['tournament_country']) ?>
                                </span>
                            <?php endif; ?>

                        </div>


                        <?php if ($edit_mode): ?>

                            <label class="field-label" for="tournament_name">Tournament Name</label>

                            <input
                                type="text"
                                id="tournament_name"
                                name="tournament_name"
                                class="name-input"
                                value="<?= htmlspecialchars($tournament['tournament_name']) ?>"
                                required
                            >

                        <?php else: ?>

                            <h1><?= htmlspecialchars($tournament['tournament_name']) ?></h1>

                        <?php endif; ?>


                        <p class="hero-meta">
                            <span><i class="fa-solid fa-hashtag"></i> ID <?= (int)$tournament['tournamentID'] ?></span>
                            <span><i class="fa-regular fa-calendar"></i> <?= viewDate($tournament['tournament_startdate'], 'd M Y') ?> &ndash; <?= viewDate($tournament['tournament_enddate'], 'd M Y') ?></span>
                            <?php if (!empty($tournament['tournament_location'])): ?>
                                <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($tournament['tournament_location']) ?></span>
                            <?php endif; ?>
                        </p>


                        <?php if (!$edit_mode): ?>

                            <div class="hero-actions">

                                <a class="primary-btn" href="admin_view_tournaments.php?id=<?= (int)$tournamentID ?>&edit=1">
                                    <i class="fa-solid fa-pen"></i>
                                    Edit Tournament
                                </a>

                                <a class="secondary-btn" href="admin_each_tournament.php?id=<?= (int)$tournamentID ?>">
                                    <i class="fa-solid fa-users"></i>
                                    Registrations
                                </a>

                            </div>

                        <?php else: ?>

                            <p class="editing-note">
                                <i class="fa-solid fa-pen"></i>
                                You are editing this tournament. Save your changes at the bottom of the page.
                            </p>

                        <?php endif; ?>

                    </div>

                </section>



                <div class="details-layout">


                    <!-- =====================================================
                        LEFT: tournament details
                    ====================================================== -->

                    <div class="details-main">


                        <!-- SCHEDULE -->

                        <section class="card">

                            <div class="card-header">
                                <span class="card-icon"><i class="fa-regular fa-calendar"></i></span>
                                <h2>Schedule</h2>
                            </div>

                            <div class="field-grid">

                                <div class="field">
                                    <span class="field-label">Start Date</span>

                                    <?php if ($edit_mode): ?>
                                        <input type="datetime-local" name="tournament_startdate"
                                               value="<?= date('Y-m-d\TH:i', strtotime($tournament['tournament_startdate'])) ?>" required>
                                    <?php else: ?>
                                        <span class="field-value"><?= viewDate($tournament['tournament_startdate'], 'd M Y, h:i A') ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="field">
                                    <span class="field-label">End Date</span>

                                    <?php if ($edit_mode): ?>
                                        <input type="datetime-local" name="tournament_enddate"
                                               value="<?= date('Y-m-d\TH:i', strtotime($tournament['tournament_enddate'])) ?>" required>
                                    <?php else: ?>
                                        <span class="field-value"><?= viewDate($tournament['tournament_enddate'], 'd M Y, h:i A') ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="field">
                                    <span class="field-label">Registration Deadline</span>

                                    <?php if ($edit_mode): ?>
                                        <input type="datetime-local" name="tournament_deadline"
                                               value="<?= date('Y-m-d\TH:i', strtotime($tournament['tournament_deadline'])) ?>" required>
                                    <?php else: ?>
                                        <span class="field-value"><?= viewDate($tournament['tournament_deadline'], 'd M Y, h:i A') ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="field">
                                    <span class="field-label">Age Cut-off Date</span>

                                    <?php if ($edit_mode): ?>
                                        <input type="date" name="tournament_age_cutoff"
                                               value="<?= !empty($tournament['tournament_age_cutoff']) ? date('Y-m-d', strtotime($tournament['tournament_age_cutoff'])) : '' ?>">
                                    <?php else: ?>
                                        <span class="field-value <?= empty($tournament['tournament_age_cutoff']) ? 'empty' : '' ?>">
                                            <?= viewDate($tournament['tournament_age_cutoff'], 'd M Y') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                            </div>

                        </section>


                        <!-- VENUE -->

                        <section class="card">

                            <div class="card-header">
                                <span class="card-icon"><i class="fa-solid fa-location-dot"></i></span>
                                <h2>Venue &amp; Type</h2>
                            </div>

                            <div class="field-grid">

                                <div class="field">
                                    <span class="field-label">Tournament Type</span>

                                    <?php if ($edit_mode): ?>

                                        <input
                                            type="text"
                                            id="tournament_type"
                                            name="tournament_type"
                                            value="<?= htmlspecialchars($tournament['tournament_type']) ?>"
                                            list="tournament_type_options"
                                            oninput="toggleUsdFee()"
                                            required
                                        >

                                        <datalist id="tournament_type_options">
                                            <option value="Local">
                                            <option value="International">
                                            <option value="National">
                                        </datalist>

                                    <?php else: ?>
                                        <span class="field-value"><?= htmlspecialchars($tournament['tournament_type']) ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="field">
                                    <span class="field-label">Country</span>

                                    <?php if ($edit_mode): ?>

                                        <select name="tournament_country" class="styled-select" required>

                                            <option value="">Select country</option>

                                            <?php foreach ($tournament_countries as $country): ?>
                                                <option
                                                    value="<?= htmlspecialchars($country) ?>"
                                                    <?= ($tournament['tournament_country'] ?? '') === $country ? 'selected' : '' ?>
                                                >
                                                    <?= htmlspecialchars($country) ?>
                                                </option>
                                            <?php endforeach; ?>

                                        </select>

                                    <?php else: ?>
                                        <span class="field-value <?= empty($tournament['tournament_country']) ? 'empty' : '' ?>">
                                            <?= htmlspecialchars($tournament['tournament_country'] ?: 'Not specified') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="field field-wide">
                                    <span class="field-label">Location</span>

                                    <?php if ($edit_mode): ?>
                                        <input type="text" name="tournament_location"
                                               value="<?= htmlspecialchars($tournament['tournament_location']) ?>" required>
                                    <?php else: ?>
                                        <span class="field-value"><?= htmlspecialchars($tournament['tournament_location']) ?></span>
                                    <?php endif; ?>
                                </div>

                            </div>

                        </section>


                        <!-- FEES & EXTRAS -->

                        <?php
                        // International: RM fee -> RM field, USD fee -> USD field
                        // (a USD-only fee goes in the USD field).
                        // Other tournaments keep the whole fee text in the RM field.
                        $isInternational = stripos($tournament['tournament_type'] ?? '', 'international') !== false;

                        if ($isInternational) {
                            $fields = feeFields($tournament['tournament_fee'] ?? '');
                            $feeParts = ['local' => $fields['rm'], 'foreign' => $fields['usd'], 'details' => $fields['details']];
                        } else {
                            $feeParts = ['local' => $tournament['tournament_fee'] ?? '', 'foreign' => null, 'details' => null];
                        }
                        ?>

                        <section class="card">

                            <div class="card-header">
                                <span class="card-icon"><i class="fa-solid fa-money-bill-wave"></i></span>
                                <h2>Fees &amp; Extras</h2>
                            </div>

                            <div class="field-grid">

                                <div class="field">
                                    <span class="field-label"><?= $isInternational ? 'Entry Fee (Local, RM)' : 'Entry Fee (RM)' ?></span>

                                    <?php if ($edit_mode): ?>

                                        <input type="text" name="tournament_fee" value="<?= htmlspecialchars($feeParts['local']) ?>">

                                        <?php if ($feeParts['details'] !== null): ?>
                                            <small class="locked-note">
                                                Currently saved as: &ldquo;<?= htmlspecialchars($feeParts['details']) ?>&rdquo;.
                                                Saving will keep only the RM and USD amounts.
                                            </small>
                                        <?php endif; ?>

                                    <?php else: ?>
                                        <span class="field-value fee-value <?= $feeParts['local'] === '' ? 'empty' : '' ?>">
                                            <?= htmlspecialchars($feeParts['local'] !== '' ? $feeParts['local'] : 'Not specified') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>


                                <!-- USD fee: international tournaments only -->

                                <?php if ($edit_mode || $isInternational): ?>

                                    <div class="field usd-fee-row" <?= $isInternational ? '' : 'hidden' ?>>
                                        <span class="field-label">Entry Fee (Foreign, USD)</span>

                                        <?php if ($edit_mode): ?>

                                            <input
                                                type="text"
                                                id="tournament_fee_usd"
                                                name="tournament_fee_usd"
                                                value="<?= htmlspecialchars($feeParts['foreign'] ?? '') ?>"
                                                placeholder="e.g. USD 60.00"
                                                <?= $isInternational ? '' : 'disabled' ?>
                                            >

                                            <small class="locked-note">
                                                Fill in the RM fee, the USD fee, or both. With both, Malaysian players
                                                pay RM and foreign players pay USD.
                                            </small>

                                        <?php else: ?>
                                            <span class="field-value fee-value <?= ($feeParts['foreign'] ?? '') === '' ? 'empty' : '' ?>">
                                                <?= htmlspecialchars(($feeParts['foreign'] ?? '') !== '' ? $feeParts['foreign'] : 'Not specified') ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                <?php endif; ?>


                                <div class="field">
                                    <span class="field-label">T-Shirt Sizes</span>

                                    <?php if ($edit_mode): ?>
                                        <input type="text" name="tournament_tshirt_size"
                                               value="<?= htmlspecialchars($tournament['tournament_tshirt_size']) ?>"
                                               placeholder="e.g. S,M,L,XL">
                                    <?php elseif (!empty($tournament['tournament_tshirt_size'])): ?>
                                        <span class="field-value size-list">
                                            <?php foreach (array_filter(array_map('trim', preg_split('/[,;]/', $tournament['tournament_tshirt_size']))) as $size): ?>
                                                <span class="size-chip"><?= htmlspecialchars($size) ?></span>
                                            <?php endforeach; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="field-value empty">Not specified</span>
                                    <?php endif; ?>
                                </div>

                                <div class="field field-wide">
                                    <span class="field-label">Detail Link</span>

                                    <?php if ($edit_mode): ?>
                                        <input type="url" name="tournament_detail_link"
                                               value="<?= htmlspecialchars($tournament['tournament_detail_link']) ?>"
                                               placeholder="https://">
                                    <?php elseif (!empty($tournament['tournament_detail_link'])): ?>
                                        <a class="field-value detail-link" href="<?= htmlspecialchars($tournament['tournament_detail_link']) ?>" target="_blank">
                                            <?= htmlspecialchars($tournament['tournament_detail_link']) ?>
                                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="field-value empty">Not specified</span>
                                    <?php endif; ?>
                                </div>

                            </div>

                        </section>


                        <!-- ABOUT -->

                        <section class="card">

                            <div class="card-header">
                                <span class="card-icon"><i class="fa-solid fa-align-left"></i></span>
                                <h2>About</h2>
                            </div>

                            <?php if ($edit_mode): ?>

                                <textarea name="tournament_description" rows="6"><?= htmlspecialchars($tournament['tournament_description']) ?></textarea>

                            <?php elseif (trim($tournament['tournament_description']) !== ''): ?>

                                <div class="description"><?= nl2br(htmlspecialchars($tournament['tournament_description'])) ?></div>

                            <?php else: ?>

                                <p class="field-value empty">No description.</p>

                            <?php endif; ?>

                        </section>

                    </div>



                    <!-- =====================================================
                        RIGHT: categories and registration fields
                    ====================================================== -->

                    <div class="details-side">


                        <!-- CATEGORIES -->

                        <section class="card">

                            <div class="card-header">
                                <span class="card-icon"><i class="fa-solid fa-trophy"></i></span>
                                <h2>Categories</h2>
                                <?php if (!$edit_mode): ?>
                                    <span class="card-count"><?= count($selected_categories) ?></span>
                                <?php endif; ?>
                            </div>


                            <!-- CATEGORY TYPE -->

                            <div class="field category-type-group">

                                <span class="field-label">Category Type</span>

                                <?php if ($edit_mode && $category_type_locked): ?>

                                    <span class="field-value locked-value">
                                        <i class="fa-solid fa-lock"></i>
                                        <?= htmlspecialchars($category_type) ?>
                                    </span>

                                    <small class="locked-note">
                                        The category type can't be changed once it is set.
                                    </small>

                                <?php elseif ($edit_mode): ?>

                                    <select id="category_type" name="category_type" class="styled-select" onchange="showCategoryType()" required>
                                        <option value="">Select category type</option>
                                        <option value="Junior" <?= $category_type === 'Junior' ? 'selected' : '' ?>>Junior</option>
                                        <option value="PSA" <?= $category_type === 'PSA' ? 'selected' : '' ?>>PSA</option>
                                    </select>

                                <?php else: ?>

                                    <span class="field-value <?= $category_type === '' ? 'empty' : '' ?>">
                                        <?= htmlspecialchars($category_type ?: 'Not set') ?>
                                    </span>

                                <?php endif; ?>

                            </div>


                            <?php

                            $category_groups = [
                                'Junior' => $all_categories,
                                'PSA' => $psa_categories
                            ];

                            ?>


                            <?php if ($edit_mode): ?>

                                <p class="category-hint" <?= $category_type_locked ? 'hidden' : '' ?>>
                                    Choose a category type to see its categories.
                                </p>

                                <?php foreach ($category_groups as $group_type => $group_categories): ?>

                                    <?php
                                    // Once the type is set, only that type's categories are shown
                                    if ($category_type_locked && $group_type !== $category_type) {
                                        continue;
                                    }
                                    ?>

                                    <div
                                        class="checkbox-list category-group"
                                        data-category-type="<?= $group_type ?>"
                                        <?= $group_type !== $category_type ? 'hidden' : '' ?>
                                    >

                                        <?php foreach ($group_categories as $category_code => $category): ?>

                                            <label class="checkbox-item">

                                                <input
                                                    type="checkbox"
                                                    name="categories[]"
                                                    value="<?= htmlspecialchars($category['name']) ?>"
                                                    <?= in_array($category['name'], $selected_categories, true) ? 'checked' : '' ?>
                                                    <?= $group_type !== $category_type ? 'disabled' : '' ?>
                                                >

                                                <span class="checkbox-code"><?= htmlspecialchars($category_code) ?></span>
                                                <span><?= htmlspecialchars($category['name']) ?></span>

                                            </label>

                                        <?php endforeach; ?>

                                    </div>

                                <?php endforeach; ?>

                            <?php elseif ($selected_category_chips): ?>

                                <div class="chip-list">
                                    <?php foreach ($selected_category_chips as $code => $name): ?>
                                        <span class="chip <?= strpos($code, 'G') === 0 || $code === 'WOMEN' ? 'chip-girls' : 'chip-boys' ?>" title="<?= htmlspecialchars($name) ?>">
                                            <?= htmlspecialchars($code) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>

                            <?php else: ?>

                                <p class="field-value empty">No categories selected.</p>

                            <?php endif; ?>

                        </section>


                        <!-- REGISTRATION FIELDS -->

                        <section class="card">

                            <div class="card-header">
                                <span class="card-icon"><i class="fa-solid fa-clipboard-list"></i></span>
                                <h2>Registration Fields</h2>
                                <?php if (!$edit_mode): ?>
                                    <span class="card-count"><?= $registration_selected_count ?>/<?= count($registration_options) ?></span>
                                <?php endif; ?>
                            </div>

                            <p class="card-description">Information players fill in when they register.</p>

                            <?php if ($edit_mode): ?>

                                <div class="checkbox-list two-columns">

                                    <?php foreach ($registration_options as $field_name => $field_label): ?>

                                        <label class="checkbox-item">

                                            <input
                                                type="checkbox"
                                                name="registration_fields[]"
                                                value="<?= htmlspecialchars($field_name) ?>"
                                                <?= in_array($field_name, $selected_registration_fields, true) ? 'checked' : '' ?>
                                            >

                                            <span><?= htmlspecialchars($field_label) ?></span>

                                        </label>

                                    <?php endforeach; ?>

                                </div>

                            <?php elseif ($registration_selected_count > 0): ?>

                                <div class="chip-list">
                                    <?php foreach ($registration_options as $field_name => $field_label): ?>
                                        <?php if (in_array($field_name, $selected_registration_fields, true)): ?>
                                            <span class="chip chip-field">
                                                <i class="fa-solid fa-check"></i>
                                                <?= htmlspecialchars($field_label) ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>

                            <?php else: ?>

                                <p class="field-value empty">No registration fields selected.</p>

                            <?php endif; ?>

                        </section>

                    </div>

                </div>



                <!-- =========================================================
                    SAVE BAR (edit mode)
                ========================================================== -->

                <?php if ($edit_mode): ?>

                    <div class="save-bar">

                        <span class="save-bar-text">
                            <i class="fa-solid fa-pen"></i>
                            Editing <strong><?= htmlspecialchars($tournament['tournament_name']) ?></strong>
                        </span>

                        <div class="save-bar-actions">

                            <a class="secondary-btn" href="admin_view_tournaments.php?id=<?= (int)$tournamentID ?>">
                                Cancel
                            </a>

                            <button type="submit" name="save_changes" class="primary-btn">
                                <i class="fa-solid fa-floppy-disk"></i>
                                Save Changes
                            </button>

                        </div>

                    </div>

                <?php endif; ?>

            </form>


        <?php else: ?>


            <div class="not-found">

                <h1>Tournament not found</h1>

                <p>Please select a valid tournament from the dashboard.</p>

                <a class="secondary-btn" href="admin_index.php">
                    Back to Dashboard
                </a>

            </div>


        <?php endif; ?>

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

            const typeInput = document.getElementById('tournament_type');
            const row = document.querySelector('.usd-fee-row');
            const input = document.getElementById('tournament_fee_usd');

            if (!typeInput || !row || !input) {
                return;
            }

            const isInternational = typeInput.value.toLowerCase().includes('international');

            row.hidden = !isInternational;
            input.disabled = !isInternational;

            // International: RM only, USD only or both
            input.required = false;
        }


        /*
        |--------------------------------------------------------------------------
        | Picture Preview
        |--------------------------------------------------------------------------
        | Shows the chosen picture straight away; it is saved with the form.
        */

        function previewPicture(input) {

            if (!input.files || !input.files[0]) {
                return;
            }

            const reader = new FileReader();

            reader.onload = function (event) {
                document.getElementById('picturePreview').src = event.target.result;
            };

            reader.readAsDataURL(input.files[0]);
        }


        function showCategoryType() {

            const typeSelect = document.getElementById('category_type');

            if (!typeSelect) {
                return;
            }

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
    </script>

</body>
</html>