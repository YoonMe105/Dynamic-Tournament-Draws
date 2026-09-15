<?php
session_start();

require '../db.php';


/*
|--------------------------------------------------------------------------
| Admin Session Check
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["userid"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Tournament ID
|--------------------------------------------------------------------------
*/

$tournamentID = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($tournamentID <= 0) {
    die("Invalid tournament ID.");
}


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
| Get Tournament
|--------------------------------------------------------------------------
*/

$sql = "SELECT *
        FROM tournament
        WHERE tournamentID = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $tournamentID);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Tournament not found.");
}

$tournament = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Get Tournament Categories
|--------------------------------------------------------------------------
*/

$categories = [];

$categorySQL = "SELECT categoryID, category_name, age, gender
                FROM tournament_category
                WHERE tournamentID = ?
                ORDER BY age DESC, gender ASC";

$categoryStmt = $conn->prepare($categorySQL);

if ($categoryStmt) {

    $categoryStmt->bind_param("i", $tournamentID);
    $categoryStmt->execute();

    $categoryResult = $categoryStmt->get_result();

    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }

    $categoryStmt->close();
}


/*
|--------------------------------------------------------------------------
| Get Selected Registration Fields
|--------------------------------------------------------------------------
*/

$selected_registration_fields = [];

if (!empty($tournament['registration_field'])) {

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

    <title>
        View Tournament
    </title>

    <link rel="stylesheet" href="./assets/style.css">
    <link rel="stylesheet" href="./assets/view_tournament.css">


</head>


<body>


<?php require_once 'admin_navbar.php'; ?>


<main class="container">


    <!-- Page Title -->

    <div class="page-title">

        <div>
            <h1>View Tournament</h1>

            <p>
                View tournament information
            </p>
        </div>

        <a href="admin_view_tournaments.php" class="back-btn">
            ← Back
        </a>

    </div>


    <!-- Main Form Card -->

    <div class="form-card">


        <!-- Tournament Name -->

        <div class="form-group">

            <label>
                Tournament Name
            </label>

            <div class="view-input">

                <?= htmlspecialchars(
                    $tournament['tournament_name']
                ) ?>

            </div>

        </div>


        <!-- Start and End Date -->

        <div class="form-row">


            <div class="form-group">

                <label>
                    Start Date & Time
                </label>

                <div class="view-input">

                    <?= date(
                        'd M Y, h:i A',
                        strtotime(
                            $tournament['tournament_startdate']
                        )
                    ) ?>

                </div>

            </div>


            <div class="form-group">

                <label>
                    End Date & Time
                </label>

                <div class="view-input">

                    <?= date(
                        'd M Y, h:i A',
                        strtotime(
                            $tournament['tournament_enddate']
                        )
                    ) ?>

                </div>

            </div>


        </div>


        <!-- Registration Deadline -->

        <div class="form-group">

            <label>
                Registration Deadline
            </label>

            <div class="view-input">

                <?= date(
                    'd M Y, h:i A',
                    strtotime(
                        $tournament['tournament_deadline']
                    )
                ) ?>

            </div>

        </div>


        <!-- Description -->

        <div class="form-group">

            <label>
                Tournament Description
            </label>

            <div class="view-textarea">

                <?= nl2br(
                    htmlspecialchars(
                        $tournament['tournament_description']
                    )
                ) ?>

            </div>

        </div>


        <!-- Location -->

        <div class="form-group">

            <label>
                Location
            </label>

            <div class="view-input">

                <?= htmlspecialchars(
                    $tournament['tournament_location']
                ) ?>

            </div>

        </div>


        <!-- Fee and Type -->

        <div class="form-row">


            <div class="form-group">

                <label>
                    Tournament Fee
                </label>

                <div class="view-input">

                    <?= htmlspecialchars(
                        $tournament['tournament_fee']
                    ) ?>

                </div>

            </div>


            <div class="form-group">

                <label>
                    Tournament Type
                </label>

                <div class="view-input">

                    <?= htmlspecialchars(
                        $tournament['tournament_type']
                    ) ?>

                </div>

            </div>


        </div>


        <!-- Age Cutoff -->

        <div class="form-group">

            <label>
                Age Cutoff Date
            </label>

            <div class="view-input">

                <?php if (!empty($tournament['tournament_age_cutoff'])): ?>

                    <?= date(
                        'd M Y',
                        strtotime(
                            $tournament['tournament_age_cutoff']
                        )
                    ) ?>

                <?php else: ?>

                    Not specified

                <?php endif; ?>

            </div>

        </div>


        <!-- T-Shirt -->

        <div class="form-group">

            <label>
                T-Shirt Size Information
            </label>

            <div class="view-input">

                <?php if (!empty($tournament['tournament_tshirt_size'])): ?>

                    <?= htmlspecialchars(
                        $tournament['tournament_tshirt_size']
                    ) ?>

                <?php else: ?>

                    Not specified

                <?php endif; ?>

            </div>

        </div>


        <!-- Detail Link -->

        <div class="form-group">

            <label>
                Tournament Detail Link
            </label>

            <div class="view-input">

                <?php if (!empty($tournament['tournament_detail_link'])): ?>

                    <a
                        href="<?= htmlspecialchars($tournament['tournament_detail_link']) ?>"
                        target="_blank"
                    >
                        <?= htmlspecialchars($tournament['tournament_detail_link']) ?>
                    </a>

                <?php else: ?>

                    Not specified

                <?php endif; ?>

            </div>

        </div>


        <!-- Tournament Picture -->

        <?php if (!empty($tournament['tournament_picture'])): ?>

            <div class="form-group">

                <label>
                    Tournament Picture
                </label>

                <div class="picture-box">

                    <img
                        src="../uploads/tournaments/<?= htmlspecialchars($tournament['tournament_picture']) ?>"
                        alt="Tournament Picture"
                    >

                </div>

            </div>

        <?php endif; ?>


    </div>


    <!-- Tournament Categories -->

    <div class="form-card">


        <div class="section-title">

            <h2>
                Tournament Categories
            </h2>

        </div>


        <div class="checkbox-grid">


            <?php if (!empty($categories)): ?>


                <?php foreach ($categories as $category): ?>


                    <label class="checkbox-item selected">

                        <input
                            type="checkbox"
                            checked
                            disabled
                        >

                        <span class="custom-checkbox">
                            ✓
                        </span>

                        <span class="checkbox-label">

                            <?= htmlspecialchars(
                                $category['category_name']
                            ) ?>

                        </span>

                    </label>


                <?php endforeach; ?>


            <?php else: ?>


                <div class="empty-message">

                    No categories selected.

                </div>


            <?php endif; ?>


        </div>


    </div>


    <!-- Registration Fields -->

    <div class="form-card">


        <div class="section-title">

            <h2>
                Registration Fields
            </h2>

        </div>


        <div class="checkbox-grid">


            <?php foreach ($registration_options as $field_name => $field_label): ?>


                <?php

                $is_selected = in_array(
                    $field_name,
                    $selected_registration_fields
                );

                ?>


                <label class="checkbox-item <?= $is_selected ? 'selected' : '' ?>">


                    <input
                        type="checkbox"
                        disabled
                        <?= $is_selected ? 'checked' : '' ?>
                    >


                    <span class="custom-checkbox">

                        <?= $is_selected ? '✓' : '' ?>

                    </span>


                    <span class="checkbox-label">

                        <?= htmlspecialchars(
                            $field_label
                        ) ?>

                    </span>


                </label>


            <?php endforeach; ?>


        </div>


    </div>


    <!-- Buttons -->

    <div class="action-buttons">

        <a
            href="admin_view_tournaments.php"
            class="secondary-btn"
        >
            Back
        </a>

        <a
            href="edit_tournament.php?id=<?= (int)$tournament['tournamentID'] ?>"
            class="primary-btn"
        >
            Edit Tournament
        </a>

    </div>


</main>


</body>

</html>