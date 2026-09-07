<?php
session_start();

require '../db.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournament Details | T_Software</title>
    <link href="./assets/style.css" rel="stylesheet" type="text/css">
</head>
<body>
    <?php require_once 'admin_navbar.php'; ?>

    <main>
        <?php if ($tournament): ?>
            <div class="details-topbar">
                <div>
                    <p class="section-kicker">Tournament profile</p>
                    <h1><?= detail_value($tournament['tournament_name']) ?></h1>
                </div>
            </div>

            <?php if (isset($_GET['updated'])): ?>
                <div class="update-message">Tournament updated successfully.</div>
            <?php endif; ?>

            <?php if ($edit_mode): ?>
                <form id="tournament-form" method="post" action="admin_view_tournaments.php?id=<?= (int) $tournament['tournamentid'] ?>&edit=1">
                    <input type="hidden" name="tournament_id" value="<?= (int) $tournament['tournamentid'] ?>">
            <?php endif; ?>

            <section class="details-image-panel">
                <div class="tournament-logo-placeholder">
                    <span>T</span>
                    <small>T_Software</small>
                </div>
                <label class="image-upload">
                    Choose File
                    <input type="file" accept="image/*">
                </label>
            </section>

            <section class="details-panel">
                <h2>Tournament Details</h2>
                <div class="details-grid">
                    <div class="detail-item"><span>Tournament ID</span><strong><?= (int) $tournament['tournamentid'] ?></strong></div>
                    <div class="detail-item detail-wide"><span>Tournament Name</span>
                        <?php if ($edit_mode): ?>
                            <input class="detail-input" name="tournament_name" value="<?= htmlspecialchars($tournament['tournament_name']) ?>" required>
                        <?php else: ?>
                            <strong><?= detail_value($tournament['tournament_name']) ?></strong>
                        <?php endif; ?>
                    </div>
                    <div class="detail-item detail-full"><span>Description</span><?php editable_field($tournament, 'description', 'textarea', $edit_mode); ?></div>
                    <div class="detail-item"><span>Tournament StartDate</span>
                        <?php if ($edit_mode): ?>
                            <input class="detail-input" type="datetime-local" name="tournament_startdate" value="<?= date('Y-m-d\TH:i', strtotime($tournament['tournament_startdate'])) ?>" required>
                        <?php else: ?>
                            <strong><?= detail_date($tournament['tournament_startdate'], 'd/m/Y h:i A') ?></strong>
                        <?php endif; ?>
                    </div>
                    <div class="detail-item"><span>Tournament EndDate</span>
                        <?php if ($edit_mode): ?>
                            <input class="detail-input" type="datetime-local" name="tournament_enddate" value="<?= date('Y-m-d\TH:i', strtotime($tournament['tournament_enddate'])) ?>" required>
                        <?php else: ?>
                            <strong><?= detail_date($tournament['tournament_enddate'], 'd/m/Y h:i A') ?></strong>
                        <?php endif; ?>
                    </div>
                    <div class="detail-item"><span>Registration Deadline</span><?php editable_field($tournament, 'registration_deadline', 'datetime-local', $edit_mode); ?></div>
                    <div class="detail-item"><span>Age Cut Off Date</span><?php editable_field($tournament, 'age_cut_off_date', 'date', $edit_mode); ?></div>
                    <div class="detail-item"><span>Tournament Type</span>
                        <?php if ($edit_mode): ?>
                            <input class="detail-input" name="tournament_type" value="<?= htmlspecialchars($tournament['tournament_type']) ?>" required>
                        <?php else: ?>
                            <strong><?= detail_value($tournament['tournament_type']) ?></strong>
                        <?php endif; ?>
                    </div>
                    <div class="detail-item"><span>Tournament Location</span><?php editable_field($tournament, 'tournament_location', 'text', $edit_mode); ?></div>
                    <div class="detail-item detail-full"><span>Tournament Fee</span><?php editable_field($tournament, 'tournament_fee', 'text', $edit_mode); ?></div>
                    <div class="detail-item detail-wide"><span>Detail Link</span><?php editable_field($tournament, 'detail_link', 'url', $edit_mode); ?></div>
                    <div class="detail-item"><span>T-Shirt Size Option</span><?php editable_field($tournament, 'tshirt_size_option', 'text', $edit_mode); ?></div>
                </div>
            </section>

            <section class="details-panel">
                <h2>Categories</h2>
                <div class="check-grid">
                    <?php foreach (['Boys Under 19 Open Championship', 'Girls Under 19 Open Championship', 'Boys Under 17 Open Championship', 'Girls Under 17 Open Championship', 'Boys Under 15 Open Championship', 'Girls Under 15 Open Championship', 'Boys Under 13 Open Championship', 'Girls Under 13 Open Championship', 'Boys Under 11 Open Championship', 'Girls Under 11 Open Championship', 'Boys Under 09 Open Championship', 'Girls Under 09 Open Championship'] as $category): ?>
                        <label><input type="checkbox" name="categories[]" value="<?= htmlspecialchars($category) ?>" <?= $edit_mode ? '' : 'disabled' ?>> <?= htmlspecialchars($category) ?></label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="details-panel">
                <h2>Registration Details Needed</h2>
                <div class="check-grid">
                    <?php $selected_registration_fields = registration_fields_from_value($tournament['registration_field'] ?? ''); ?>
                    <?php $registration_options = [
                        'player_full_name' => 'Full Name',
                        'player_first_name' => 'First Name',
                        'player_last_name' => 'Last Name',
                        'player_ic' => 'National ID',
                        'player_passport' => 'Passport',
                        'player_dob' => 'Date of Birth',
                        'player_gender' => 'Gender',
                        'player_nationality' => 'Nationality',
                        'player_contact' => 'Contact Number',
                        'national_ranking' => 'National Ranking',
                        'ajss_ranking' => 'AJSS Ranking',
                        'psa_world_ranking' => '(PSA) World Ranking',
                        'player_email' => 'Email',
                        'category_registered' => 'Category Registered',
                        'player_chinese_name' => 'Chinese Name',
                        'player_tshirt' => 'T-Shirt Size',
                        'team_accommodation_transportation' => 'Team Accommodation / Transportation',
                        'player_latest_results' => 'Latest Results',
                        'player_attachments' => 'Other Attachments',
                        'admin_remarks_notes' => 'Admin remarks or notes',
                        'player_asf_membership_no' => 'ASF Membership No',
                        'player_wsf_spin_no' => 'WSF Spin No'
                    ]; ?>
                    <?php foreach ($registration_options as $field => $label): ?>
                        <label><input type="checkbox" name="registration_fields[]" value="<?= htmlspecialchars($field) ?>" <?= in_array($field, $selected_registration_fields, true) ? 'checked' : '' ?> <?= $edit_mode ? '' : 'disabled' ?>> <?= htmlspecialchars($label) ?></label>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php if ($edit_mode): ?>
                <div class="details-bottom-actions">
                    <a class="details-btn" href="admin_view_tournaments.php?id=<?= (int) $tournament['tournamentid'] ?>">Back</a>
                    <button class="details-btn update-btn" type="submit" form="tournament-form">Update</button>
                </div>
                </form>
            <?php else: ?>
                <div class="details-bottom-actions">
                    <a class="details-btn" href="admin_index.php">Back</a>
                    <a class="details-btn update-btn" href="admin_view_tournaments.php?id=<?= (int) $tournament['tournamentid'] ?>&edit=1">Update</a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="dashboard-header">
                <h1>Tournament not found</h1>
                <p>Please select a valid tournament from the dashboard.</p>
            </div>
            <a class="details-btn" href="admin_index.php">Back to Dashboard</a>
        <?php endif; ?>
    </main>
</body>
</html>