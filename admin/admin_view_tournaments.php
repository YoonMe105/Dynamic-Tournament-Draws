<?php
session_start();

require '../db.php';

require_once 'admin_countries.php';

require_once '../fees.php';

if (!isset($_SESSION["userid"]) || !isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}


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
| Save Tournament Changes
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_changes'])) {

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

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tournament Details | T_Software</title>

    <link rel="stylesheet" href="./assets/style.css">
    <link rel="stylesheet" href="./assets/view_tournament.css">

</head>


<body>


    <?php require_once 'admin_navbar.php'; ?>


    <main class="page-container">

        <?php if ($tournament): ?>

            <div class="page-header">

                <div>

                    <h1>
                        <?= htmlspecialchars($tournament['tournament_name']) ?>
                    </h1>
                </div>

            </div>


            <?php if (isset($_GET['updated'])): ?>

                <div class="update-message">
                    Tournament updated successfully.
                </div>

            <?php endif; ?>


            <?php if (isset($error_message)): ?>

                <div class="error-message">
                    <?= htmlspecialchars($error_message) ?>
                </div>

            <?php endif; ?>


            <form method="POST" action="admin_view_tournaments.php?id=<?= (int)$tournamentID ?>">

                <input
                    type="hidden"
                    name="tournamentID"
                    value="<?= (int)$tournamentID ?>"
                >

                <section class="form-section">
                    <div class="form-group">

                        <div class="picture-upload-box">
                            <div class="picture-preview">
                                <img id="picturePreview"
                                    src="<?= !empty($tournament['tournament_picture']) ? htmlspecialchars($tournament['tournament_picture']) : '../images/default-tournament.jpg' ?>"
                                    alt="Tournament Picture">
                            </div>

                            <div class="picture-upload">
                                <label for="tournament_picture" class="choose-file-btn">Choose File</label>
                                <input type="file" id="tournament_picture" name="tournament_picture" accept="image/*" onchange="previewPicture(this)">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <div class="form-row">


                        <div class="form-group">

                            <label>Tournament ID</label>

                            <div class="view-value">
                                <?= (int)$tournament['tournamentID'] ?>
                            </div>

                        </div>


                        <div class="form-group">

                            <label>Tournament Name</label>

                            <?php if ($edit_mode): ?>

                                <input
                                    type="text"
                                    name="tournament_name"
                                    value="<?= htmlspecialchars($tournament['tournament_name']) ?>"
                                    required
                                >

                            <?php else: ?>

                                <div class="view-value">
                                    <?= htmlspecialchars($tournament['tournament_name']) ?>
                                </div>

                            <?php endif; ?>

                        </div>


                    </div>


                    <div class="form-group">

                        <label>Tournament Description</label>

                        <?php if ($edit_mode): ?>

                            <textarea
                                name="tournament_description"
                                rows="5"
                            ><?= htmlspecialchars($tournament['tournament_description']) ?></textarea>

                        <?php else: ?>

                            <div class="view-description">
                                <?= nl2br(htmlspecialchars($tournament['tournament_description'])) ?>
                            </div>

                        <?php endif; ?>

                    </div>


                    <div class="form-row">


                        <div class="form-group">

                            <label>Tournament Start Date</label>

                            <?php if ($edit_mode): ?>

                                <input
                                    type="datetime-local"
                                    name="tournament_startdate"
                                    value="<?= date('Y-m-d\TH:i', strtotime($tournament['tournament_startdate'])) ?>"
                                    required
                                >

                            <?php else: ?>

                                <div class="view-value">
                                    <?= date('d/m/Y h:i A', strtotime($tournament['tournament_startdate'])) ?>
                                </div>

                            <?php endif; ?>

                        </div>


                        <div class="form-group">

                            <label>Tournament End Date</label>

                            <?php if ($edit_mode): ?>

                                <input
                                    type="datetime-local"
                                    name="tournament_enddate"
                                    value="<?= date('Y-m-d\TH:i', strtotime($tournament['tournament_enddate'])) ?>"
                                    required
                                >

                            <?php else: ?>

                                <div class="view-value">
                                    <?= date('d/m/Y h:i A', strtotime($tournament['tournament_enddate'])) ?>
                                </div>

                            <?php endif; ?>

                        </div>


                    </div>


                    <div class="form-row">


                        <div class="form-group">

                            <label>Registration Deadline</label>

                            <?php if ($edit_mode): ?>

                                <input
                                    type="datetime-local"
                                    name="tournament_deadline"
                                    value="<?= date('Y-m-d\TH:i', strtotime($tournament['tournament_deadline'])) ?>"
                                    required
                                >

                            <?php else: ?>

                                <div class="view-value">
                                    <?= date('d/m/Y h:i A', strtotime($tournament['tournament_deadline'])) ?>
                                </div>

                            <?php endif; ?>

                        </div>


                        <div class="form-group">

                            <label>Age Cut Off Date</label>

                            <?php if ($edit_mode): ?>

                                <input
                                    type="date"
                                    name="tournament_age_cutoff"
                                    value="<?= !empty($tournament['tournament_age_cutoff']) ? date('Y-m-d', strtotime($tournament['tournament_age_cutoff'])) : '' ?>"
                                >

                            <?php else: ?>

                                <div class="view-value">

                                    <?php if (!empty($tournament['tournament_age_cutoff'])): ?>

                                        <?= date('d/m/Y', strtotime($tournament['tournament_age_cutoff'])) ?>

                                    <?php else: ?>

                                        Not specified

                                    <?php endif; ?>

                                </div>

                            <?php endif; ?>

                        </div>


                    </div>


                    <div class="form-row">


                        <div class="form-group">

                            <label>Tournament Type</label>

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

                                <div class="view-value">
                                    <?= htmlspecialchars($tournament['tournament_type']) ?>
                                </div>

                            <?php endif; ?>

                        </div>


                        <div class="form-group">

                            <label>Tournament Location</label>

                            <?php if ($edit_mode): ?>

                                <input
                                    type="text"
                                    name="tournament_location"
                                    value="<?= htmlspecialchars($tournament['tournament_location']) ?>"
                                    required
                                >

                            <?php else: ?>

                                <div class="view-value">
                                    <?= htmlspecialchars($tournament['tournament_location']) ?>
                                </div>

                            <?php endif; ?>

                        </div>


                    </div>


                    <div class="form-row">


                        <div class="form-group">

                            <label>Country</label>

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

                                <div class="view-value">
                                    <?= htmlspecialchars($tournament['tournament_country'] ?: '-') ?>
                                </div>

                            <?php endif; ?>

                        </div>


                    </div>


                    <?php
                    // International: RM fee -> RM field, USD fee -> USD field
                    // (a USD-only fee goes in the USD field).
                    // Other tournaments keep the whole fee text in the RM field.
                    if (stripos($tournament['tournament_type'] ?? '', 'international') !== false) {
                        $fields = feeFields($tournament['tournament_fee'] ?? '');
                        $feeParts = ['local' => $fields['rm'], 'foreign' => $fields['usd'], 'details' => $fields['details']];
                    } else {
                        $feeParts = ['local' => $tournament['tournament_fee'] ?? '', 'foreign' => null, 'details' => null];
                    }
                    ?>

                    <div class="form-row">


                        <div class="form-group">

                            <label>Entry Fee (RM)</label>

                            <?php if ($edit_mode): ?>

                                <input
                                    type="text"
                                    name="tournament_fee"
                                    value="<?= htmlspecialchars($feeParts['local']) ?>"
                                >

                                <?php if ($feeParts['details'] !== null): ?>

                                    <small class="locked-note">
                                        Currently saved as: &ldquo;<?= htmlspecialchars($feeParts['details']) ?>&rdquo;.
                                        Saving will keep only the RM and USD amounts.
                                    </small>

                                <?php endif; ?>

                            <?php else: ?>

                                <div class="view-value">
                                    <?= htmlspecialchars($feeParts['local'] !== '' ? $feeParts['local'] : '-') ?>
                                </div>

                            <?php endif; ?>

                        </div>


                        <div class="form-group">

                            <label>T-Shirt Size Option</label>

                            <?php if ($edit_mode): ?>

                                <input
                                    type="text"
                                    name="tournament_tshirt_size"
                                    value="<?= htmlspecialchars($tournament['tournament_tshirt_size']) ?>"
                                >

                            <?php else: ?>

                                <div class="view-value">

                                    <?php if (!empty($tournament['tournament_tshirt_size'])): ?>

                                        <?= htmlspecialchars($tournament['tournament_tshirt_size']) ?>

                                    <?php else: ?>

                                        Not specified

                                    <?php endif; ?>

                                </div>

                            <?php endif; ?>

                        </div>


                    </div>


                    <!-- USD fee: international tournaments only -->

                    <?php $isInternational = stripos($tournament['tournament_type'] ?? '', 'international') !== false; ?>

                    <?php if ($edit_mode || $isInternational): ?>

                        <div class="form-row usd-fee-row" <?= $isInternational ? '' : 'hidden' ?>>

                            <div class="form-group">

                                <label>Entry Fee (USD)</label>

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
                                        pay RM and foreign players pay USD (saved as &ldquo;Local: RM120.00, Foreign: USD 60.00&rdquo;).
                                    </small>

                                <?php else: ?>

                                    <div class="view-value">
                                        <?= htmlspecialchars(($feeParts['foreign'] ?? '') !== '' ? $feeParts['foreign'] : '-') ?>
                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endif; ?>


                    <div class="form-group">

                        <label>Detail Link</label>

                        <?php if ($edit_mode): ?>

                            <input
                                type="url"
                                name="tournament_detail_link"
                                value="<?= htmlspecialchars($tournament['tournament_detail_link']) ?>"
                            >

                        <?php else: ?>

                            <div class="view-value">

                                <?php if (!empty($tournament['tournament_detail_link'])): ?>

                                    <a
                                        href="<?= htmlspecialchars($tournament['tournament_detail_link']) ?>"
                                        target="_blank" class="detail"
                                    >
                                        <?= htmlspecialchars($tournament['tournament_detail_link']) ?>
                                    </a>

                                <?php else: ?>

                                    Not specified

                                <?php endif; ?>

                            </div>

                        <?php endif; ?>

                    </div>


                    <?php if (!empty($tournament['tournament_picture'])): ?>

                        <div class="form-group">

                            <label>Tournament Picture</label>

                            <div class="tournament-picture">

                                <img
                                    src="<?= htmlspecialchars($tournament['tournament_picture']) ?>"
                                    alt="Tournament Picture"
                                >

                            </div>

                        </div>

                    <?php endif; ?>


                </section>


                <!-- =========================================================
                    CATEGORIES
                ========================================================== -->

                <section class="form-section">

                    <h2>Tournament Categories</h2>

                    <p class="section-description">
                        Categories available for this tournament.
                    </p>


                    <!-- CATEGORY TYPE -->

                    <div class="form-group category-type-group">

                        <label for="category_type">Category Type</label>

                        <?php if ($edit_mode && $category_type_locked): ?>

                            <div class="view-value locked-value">
                                <?= htmlspecialchars($category_type) ?>
                            </div>

                            <small class="locked-note">
                                The category type can't be changed once it is set.
                            </small>

                        <?php elseif ($edit_mode): ?>

                            <select id="category_type" name="category_type" class="styled-select" onchange="showCategoryType()" required>

                                <option value="">Select category type</option>

                                <option value="Junior" <?= $category_type === 'Junior' ? 'selected' : '' ?>>
                                    Junior
                                </option>

                                <option value="PSA" <?= $category_type === 'PSA' ? 'selected' : '' ?>>
                                    PSA
                                </option>

                            </select>

                        <?php else: ?>

                            <div class="view-value">
                                <?= htmlspecialchars($category_type ?: '-') ?>
                            </div>

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

                    <?php endif; ?>


                    <?php foreach ($category_groups as $group_type => $group_categories): ?>


                        <?php
                        // Once the type is set, only that type's categories are shown
                        if ((!$edit_mode || $category_type_locked) && $group_type !== $category_type) {
                            continue;
                        }
                        ?>


                        <div
                            class="checkbox-grid category-group"
                            data-category-type="<?= $group_type ?>"
                            <?= $group_type !== $category_type ? 'hidden' : '' ?>
                        >


                            <?php foreach ($group_categories as $category_code => $category): ?>


                                <?php

                                $is_selected = in_array(
                                    $category['name'],
                                    $selected_categories,
                                    true
                                );

                                ?>


                                <?php if ($edit_mode): ?>


                                    <label class="checkbox-item">

                                        <input
                                            type="checkbox"
                                            name="categories[]"
                                            value="<?= htmlspecialchars($category['name']) ?>"
                                            <?= $is_selected ? 'checked' : '' ?>
                                            <?= $group_type !== $category_type ? 'disabled' : '' ?>
                                        >

                                        <span>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </span>

                                    </label>


                                <?php else: ?>


                                    <label class="checkbox-item <?= $is_selected ? '' : 'unselected' ?>">

                                        <input
                                            type="checkbox"
                                            disabled
                                            <?= $is_selected ? 'checked' : '' ?>
                                        >

                                        <span>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </span>

                                    </label>


                                <?php endif; ?>


                            <?php endforeach; ?>


                        </div>


                    <?php endforeach; ?>

                </section>


                <!-- =========================================================
                    REGISTRATION FIELDS
                ========================================================== -->

                <section class="form-section">

                    <h2>Registration Details Needed</h2>

                    <p class="section-description">
                        Information required from players during registration.
                    </p>


                    <div class="checkbox-grid">


                        <?php foreach ($registration_options as $field_name => $field_label): ?>


                            <?php

                            $is_selected = in_array(
                                $field_name,
                                $selected_registration_fields,
                                true
                            );

                            ?>


                            <?php if ($edit_mode): ?>


                                <label class="checkbox-item">

                                    <input
                                        type="checkbox"
                                        name="registration_fields[]"
                                        value="<?= htmlspecialchars($field_name) ?>"
                                        <?= $is_selected ? 'checked' : '' ?>
                                    >

                                    <span>
                                        <?= htmlspecialchars($field_label) ?>
                                    </span>

                                </label>


                            <?php else: ?>


                                <label class="checkbox-item <?= $is_selected ? '' : 'unselected' ?>">

                                    <input
                                        type="checkbox"
                                        disabled
                                        <?= $is_selected ? 'checked' : '' ?>
                                    >

                                    <span>
                                        <?= htmlspecialchars($field_label) ?>
                                    </span>

                                </label>


                            <?php endif; ?>


                        <?php endforeach; ?>


                    </div>

                </section>


                <!-- =========================================================
                    ACTIONS
                ========================================================== -->

                <div class="form-actions">


                    <?php if ($edit_mode): ?>


                        <a
                            class="cancel-btn"
                            href="admin_view_tournaments.php?id=<?= (int)$tournamentID ?>"
                        >
                            Cancel
                        </a>


                        <button
                            type="submit"
                            name="save_changes"
                            class="update-btn"
                        >
                            Save Changes
                        </button>


                    <?php else: ?>


                        <a
                            class="cancel-btn"
                            href="admin_index.php"
                        >
                            Back
                        </a>


                        <a
                            class="update-btn"
                            href="admin_view_tournaments.php?id=<?= (int)$tournament['tournamentID'] ?>&edit=1"
                        >
                            Update
                        </a>


                    <?php endif; ?>


                </div>


            </form>


            <?php else: ?>


                <div class="not-found">

                    <h1>Tournament not found</h1>

                    <p>
                        Please select a valid tournament from the dashboard.
                    </p>

                    <a
                        class="cancel-btn"
                        href="admin_index.php"
                    >
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