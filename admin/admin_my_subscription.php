<?php

require_once '../db.php';
require_once 'admin_auth.php';
require_once '../platform_fee.php';


/*
|--------------------------------------------------------------------------
| My Subscription (Tournament Organizers)
|--------------------------------------------------------------------------
| The two plans and their prices, the organizer's annual subscription, the
| tournaments still waiting to be paid and what they've paid so far.
| Platform Admins manage everything on admin_subscriptions.php.
*/

if (isPlatformAdmin()) {
    header("Location: admin_subscriptions.php");
    exit;
}

$adminID = $_SESSION['userid'];

$prices = subscriptionPrices($conn);
$annualNow = activeAnnualSubscription($conn, $adminID);
$nextPeriod = nextAnnualPeriod($conn, $adminID);


// Tournaments still waiting to be paid
$stmt = $conn->prepare("
    SELECT t.tournamentID, t.tournament_name, t.tournament_startdate, pp.amount, pp.currency
    FROM tournament_platform_payments pp
    JOIN tournament t ON t.tournamentID = pp.tournamentID
    WHERE pp.adminID = ? AND pp.status <> 'paid'
    ORDER BY t.tournament_startdate ASC
");
$stmt->bind_param("s", $adminID);
$stmt->execute();
$unpaid = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// Everything paid: annual subscriptions + tournaments paid one by one, newest first
$history = [];

$stmt = $conn->prepare("SELECT * FROM organizer_subscriptions WHERE adminID = ?");
$stmt->bind_param("s", $adminID);
$stmt->execute();

foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $history[] = [
        'paid_at' => $row['paid_at'],
        'what' => 'Annual subscription',
        'detail' => date('d M Y', strtotime($row['start_date'])) . ' – ' . date('d M Y', strtotime($row['end_date'])),
        'amount' => formatPlatformFee($row['amount'], $row['currency']),
        'method' => $row['gateway'],
    ];
}

$stmt->close();

$stmt = $conn->prepare("
    SELECT pp.*, t.tournament_name
    FROM tournament_platform_payments pp
    JOIN tournament t ON t.tournamentID = pp.tournamentID
    WHERE pp.adminID = ? AND pp.status = 'paid' AND pp.plan = 'per_tournament'
");
$stmt->bind_param("s", $adminID);
$stmt->execute();

foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $history[] = [
        'paid_at' => $row['paid_at'],
        'what' => 'Per tournament',
        'detail' => $row['tournament_name'],
        'amount' => formatPlatformFee($row['amount'], $row['currency']),
        'method' => $row['gateway'],
    ];
}

$stmt->close();

usort($history, function ($a, $b) {
    return strcmp($b['paid_at'], $a['paid_at']);
});

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Subscription</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

    <link href="./assets/style.css" rel="stylesheet" type="text/css">
    <link href="./assets/admins.css?v=<?= filemtime(__DIR__ . '/assets/admins.css') ?>" rel="stylesheet" type="text/css">
    <link href="./assets/subscription.css?v=<?= filemtime(__DIR__ . '/assets/subscription.css') ?>" rel="stylesheet" type="text/css">

</head>

<body>

    <?php require_once 'admin_navbar.php'; ?>


    <main class="admins-page">

        <div class="page-title">
            <h1>My Subscription</h1>
            <p>Pay T_Software per tournament, or once a year for all your tournaments.</p>
        </div>


        <?php if (($_GET['paid'] ?? '') === 'annual'): ?>
            <div class="success-message">
                Payment received &ndash; thank you! Your annual subscription is active, and registration is open for all your tournaments.
            </div>
        <?php endif; ?>


        <!-- ==========================================
             CURRENT STATUS
        =========================================== -->

        <section class="card sub-status <?= $annualNow ? 'is-active' : '' ?>">

            <span class="sub-status-icon">
                <i class="fa-solid <?= $annualNow ? 'fa-calendar-check' : 'fa-ticket' ?>"></i>
            </span>

            <div>
                <?php if ($annualNow): ?>
                    <h2>Annual plan &ndash; active</h2>
                    <p>
                        Every tournament you create until <strong><?= date('d M Y', strtotime($annualNow['end_date'])) ?></strong>
                        is covered. No payment needed when you create a tournament.
                    </p>
                <?php else: ?>
                    <h2>Paying per tournament</h2>
                    <p>You pay for each tournament you create. Switch to the annual plan to cover all of them for a year.</p>
                <?php endif; ?>
            </div>

        </section>


        <!-- ==========================================
             PLANS
        =========================================== -->

        <div class="plan-grid">

            <section class="card plan-card">

                <p class="plan-label"><i class="fa-solid fa-ticket"></i> Per tournament</p>
                <p class="plan-amount"><?= htmlspecialchars(formatPlatformFee($prices['per_tournament'], $prices['currency'])) ?> <small>/ tournament</small></p>

                <ul class="plan-points">
                    <li><i class="fa-solid fa-check"></i> Pay only when you create a tournament</li>
                    <li><i class="fa-solid fa-check"></i> Registration opens once that tournament is paid</li>
                </ul>

                <p class="plan-hint">You'll be asked to pay after creating a tournament.</p>

            </section>

            <section class="card plan-card featured">

                <p class="plan-label"><i class="fa-solid fa-calendar-check"></i> Annual</p>
                <p class="plan-amount"><?= htmlspecialchars(formatPlatformFee($prices['annual'], $prices['currency'])) ?> <small>/ year</small></p>

                <ul class="plan-points">
                    <li><i class="fa-solid fa-check"></i> Unlimited tournaments for 12 months</li>
                    <li><i class="fa-solid fa-check"></i> Also opens registration for your unpaid tournaments</li>
                </ul>

                <form method="POST" action="admin_platform_payment.php?annual=1">
                    <input type="hidden" name="action" value="pay">
                    <input type="hidden" name="plan" value="annual">
                    <button type="submit" class="add-btn plan-btn">
                        <?= $annualNow ? 'Renew for another year' : 'Subscribe annually' ?>
                    </button>
                </form>

                <p class="plan-hint">
                    <?= date('d M Y', strtotime($nextPeriod['start'])) ?> &ndash; <?= date('d M Y', strtotime($nextPeriod['end'])) ?>
                </p>

            </section>

        </div>


        <!-- ==========================================
             UNPAID TOURNAMENTS
        =========================================== -->

        <?php if ($unpaid): ?>

            <section class="card sub-section">

                <h2>Waiting for payment <span class="count"><?= count($unpaid) ?></span></h2>

                <p class="muted-text">Players can see these tournaments, but registration stays closed until they're paid.</p>

                <div class="table-wrap">
                    <table class="admins-table">
                        <thead>
                            <tr>
                                <th>Tournament</th>
                                <th>Starts</th>
                                <th>Per tournament</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unpaid as $row): ?>
                                <tr>
                                    <td>
                                        <a class="table-link" href="admin_each_tournament.php?id=<?= (int)$row['tournamentID'] ?>">
                                            <?= htmlspecialchars($row['tournament_name']) ?>
                                        </a>
                                    </td>
                                    <td><?= date('d M Y', strtotime($row['tournament_startdate'])) ?></td>
                                    <td><?= htmlspecialchars(formatPlatformFee($row['amount'], $row['currency'])) ?></td>
                                    <td class="row-actions">
                                        <a class="action-btn positive" href="admin_platform_payment.php?id=<?= (int)$row['tournamentID'] ?>">Pay</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </section>

        <?php endif; ?>


        <!-- ==========================================
             PAYMENT HISTORY
        =========================================== -->

        <section class="card sub-section">

            <h2>Payment history <span class="count"><?= count($history) ?></span></h2>

            <?php if ($history): ?>

                <div class="table-wrap">
                    <table class="admins-table">
                        <thead>
                            <tr>
                                <th>Paid on</th>
                                <th>Plan</th>
                                <th>For</th>
                                <th>Amount</th>
                                <th>Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $row): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($row['paid_at'])) ?></td>
                                    <td><?= htmlspecialchars($row['what']) ?></td>
                                    <td><?= htmlspecialchars($row['detail']) ?></td>
                                    <td><?= htmlspecialchars($row['amount']) ?></td>
                                    <td><?= htmlspecialchars(ucfirst((string)$row['method'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>

                <p class="muted-text">No payments yet.</p>

            <?php endif; ?>

        </section>

    </main>

</body>

</html>
