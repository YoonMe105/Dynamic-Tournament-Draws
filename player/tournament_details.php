<?php
require_once "player_functions.php";

$tournamentID = isset($_GET['tournamentID']) ? intval($_GET['tournamentID']) : 0;

$tournament = getTournament($conn, $tournamentID);

if (!$tournament) {
    die("Tournament not found.");
}

$registrationOpen = isRegistrationOpen($tournament);

$categories = getCategories($conn, $tournamentID);

$registration = getRegistration($conn, $tournamentID, $playerID);

// The fee this player pays (RM for Malaysians, USD for foreign players)
$myFee = playerFee($tournament, getPlayer($conn, $playerID) ?? []);
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($tournament['tournament_name']) ?></title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />

    <!-- Navbar CSS -->
    <link rel="stylesheet" href="./assets/css/navbar.css?v=<?= filemtime(__DIR__ . '/assets/css/navbar.css') ?>" type="text/css" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="./assets/css/tournament_details.css?v=<?= filemtime(__DIR__ . '/assets/css/tournament_details.css') ?>" type="text/css" />
</head>


<body>


    <?php include "player_navbar.php"; ?>


    <main class="container">

        <a href="./player_index.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Tournaments
        </a>


        <!-- =====================================================
             TOURNAMENT HEADER
        ====================================================== -->

        <section class="tournament-hero">

            <div class="hero-image">

                <?php if (!empty($tournament['tournament_picture'])): ?>

                    <img
                        src="../uploads/tournaments/<?= htmlspecialchars($tournament['tournament_picture']) ?>"
                        alt="<?= htmlspecialchars($tournament['tournament_name']) ?>"
                    >

                <?php else: ?>

                    <div class="no-image">
                        <i class="fa-solid fa-trophy"></i>
                    </div>

                <?php endif; ?>

            </div>


            <div class="hero-content">

                <?php if (!empty($tournament['tournament_type'])): ?>

                    <span class="tournament-type">
                        <?= htmlspecialchars($tournament['tournament_type']) ?>
                    </span>

                <?php endif; ?>


                <h1><?= htmlspecialchars($tournament['tournament_name']) ?></h1>


                <?php if ($registrationOpen): ?>

                    <span class="badge badge-open">Registration Open</span>

                <?php else: ?>

                    <span class="badge badge-closed">Registration Closed</span>

                <?php endif; ?>


                <div class="detail-grid">

                    <div class="detail-item">
                        <span class="detail-label"><i class="fa-solid fa-location-dot"></i> Location</span>
                        <span class="detail-value"><?= htmlspecialchars($tournament['tournament_location'] ?: '-') ?></span>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label"><i class="fa-solid fa-flag"></i> Country</span>
                        <span class="detail-value"><?= htmlspecialchars($tournament['tournament_country'] ?: '-') ?></span>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label"><i class="fa-solid fa-calendar-days"></i> Tournament Dates</span>
                        <span class="detail-value">
                            <?= formatDate($tournament['tournament_startdate']) ?>
                            &ndash;
                            <?= formatDate($tournament['tournament_enddate']) ?>
                        </span>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label"><i class="fa-solid fa-clock"></i> Registration Deadline</span>
                        <span class="detail-value"><?= formatDate($tournament['tournament_deadline']) ?></span>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label"><i class="fa-solid fa-cake-candles"></i> Age Cut-off Date</span>
                        <span class="detail-value"><?= formatCutoffDate($tournament['tournament_age_cutoff']) ?></span>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label"><i class="fa-solid fa-money-bill"></i> Entry Fee</span>
                        <span class="detail-value">
                            <?php foreach (tournamentFees($tournament) as $currency => $fee): ?>
                                <span class="fee-line">
                                    <?php if (count(tournamentFees($tournament)) > 1): ?>
                                        <span class="fee-currency"><?= $currency === 'RM' ? 'Local' : 'Foreign' ?>:</span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($fee ?: '-') ?>
                                </span>
                            <?php endforeach; ?>
                        </span>
                    </div>

                    <?php if (!empty($tournament['tournament_detail_link'])): ?>

                        <div class="detail-item">
                            <span class="detail-label"><i class="fa-solid fa-link"></i> More Information</span>
                            <a
                                class="detail-value detail-link"
                                href="<?= htmlspecialchars($tournament['tournament_detail_link']) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                View tournament info
                            </a>
                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </section>


        <div class="details-layout">


            <!-- =================================================
                 DESCRIPTION + CATEGORIES
            ================================================== -->

            <section class="card">

                <h2>About this Tournament</h2>

                <div class="description">
                    <?= !empty($tournament['tournament_description'])
                        ? nl2br(htmlspecialchars($tournament['tournament_description']))
                        : '<span class="muted">No description provided.</span>' ?>
                </div>


                <?php if (!empty($categories)): ?>

                    <h3>Categories</h3>

                    <div class="category-list">

                        <?php foreach ($categories as $code => $name): ?>

                            <span class="category-chip" title="<?= htmlspecialchars($name) ?>">
                                <?= htmlspecialchars($code) ?>
                            </span>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>


            <!-- =================================================
                 REGISTRATION
            ================================================== -->

            <section class="card registration-card">

                <?php if ($registration): ?>


                    <!-- ALREADY REGISTERED -->

                    <?php if (isset($_GET['paid']) && $_GET['paid'] == '1'): ?>

                        <div class="message success">
                            <i class="fa-solid fa-circle-check"></i>
                            Payment receipt submitted. The organiser will verify your payment.
                        </div>

                    <?php endif; ?>


                    <h2>Your Registration</h2>

                    <div class="registration-summary">

                        <div class="summary-row">
                            <span>Category</span>
                            <strong><?= htmlspecialchars($registration['category_registered']) ?></strong>
                        </div>

                        <?php if (!empty($registration['player_tshirt'])): ?>

                            <div class="summary-row">
                                <span>T-Shirt Size</span>
                                <strong><?= htmlspecialchars($registration['player_tshirt']) ?></strong>
                            </div>

                        <?php endif; ?>

                        <div class="summary-row">
                            <span>Payment</span>
                            <strong class="status <?= statusClass($registration['payment_status']) ?>">
                                <?= htmlspecialchars($registration['payment_status'] ?: 'Not Paid') ?>
                            </strong>
                        </div>

                        <div class="summary-row">
                            <span>Endorsement</span>
                            <strong class="status <?= statusClass($registration['endorsement']) ?>">
                                <?= htmlspecialchars($registration['endorsement'] ?: 'NOT ENDORSED') ?>
                            </strong>
                        </div>

                        <?php if (!empty($registration['seed_number'])): ?>

                            <div class="summary-row">
                                <span>Seed</span>
                                <strong><?= (int) $registration['seed_number'] ?></strong>
                            </div>

                        <?php endif; ?>

                        <?php if (!empty($registration['admin_remark'])): ?>

                            <div class="summary-remark">
                                <span>Remarks from organiser</span>
                                <p><?= nl2br(htmlspecialchars($registration['admin_remark'])) ?></p>
                            </div>

                        <?php endif; ?>

                    </div>


                    <?php if (canPay($registration)): ?>

                        <a
                            href="tournament_payment.php?registrationID=<?= (int) $registration['registrationID'] ?>"
                            class="primary-btn"
                        >
                            <?= empty($registration['payment_proof']) ? 'Pay Entry Fee' : 'Re-upload Payment Receipt' ?>
                        </a>

                    <?php endif; ?>


                <?php elseif (!$registrationOpen): ?>


                    <!-- CLOSED -->

                    <h2>Registration</h2>

                    <p class="muted">
                        Registration for this tournament closed on
                        <?= formatDate($tournament['tournament_deadline']) ?>.
                    </p>


                <?php elseif (empty($categories)): ?>


                    <!-- NO CATEGORIES -->

                    <h2>Registration</h2>

                    <p class="muted">
                        There are no categories available in this tournament yet.
                    </p>


                <?php else: ?>


                    <!-- REGISTER BUTTON -->

                    <h2>Registration</h2>

                    <div class="registration-summary">

                        <div class="summary-row">
                            <span>Deadline</span>
                            <strong><?= formatDate($tournament['tournament_deadline']) ?></strong>
                        </div>

                        <div class="summary-row">
                            <span>Age Cut-off Date</span>
                            <strong><?= formatCutoffDate($tournament['tournament_age_cutoff']) ?></strong>
                        </div>

                        <div class="summary-row">
                            <span>Your Entry Fee<?= $myFee['currency'] !== 'OTHER' ? ' (' . $myFee['currency'] . ')' : '' ?></span>
                            <strong><?= htmlspecialchars($myFee['fee'] ?: '-') ?></strong>
                        </div>

                    </div>

                    <a
                        href="tournament_register.php?tournamentID=<?= $tournamentID ?>"
                        class="primary-btn"
                    >
                        Register
                    </a>


                <?php endif; ?>

            </section>

        </div>

    </main>


</body>

</html>
