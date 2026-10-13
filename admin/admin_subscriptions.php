<?php

require_once '../db.php';
require_once 'admin_auth.php';
require_once '../platform_fee.php';

requirePlatformAdmin();


/*
|--------------------------------------------------------------------------
| Subscriptions
|--------------------------------------------------------------------------
| What Tournament Organizers pay T_Software: the Annual plan (all their
| tournaments for a year) or Per tournament. The Platform Admin sets both
| prices here and sees who has paid. Platform Admins only.
*/

$error = '';

$action = $_POST['action'] ?? '';


/*
| Prices of the two plans
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_prices') {

    $annual = trim($_POST['annual_amount'] ?? '');
    $perTournament = trim($_POST['per_tournament_amount'] ?? '');
    $currency = $_POST['currency'] ?? '';

    $amountPattern = '/^\d{1,6}(\.\d{1,2})?$/';

    if (!preg_match($amountPattern, $annual)) {
        $error = 'Enter the annual price as an amount, e.g. 1000 or 1000.00.';
    } elseif (!preg_match($amountPattern, $perTournament)) {
        $error = 'Enter the per-tournament price as an amount, e.g. 150 or 150.00.';
    } elseif (!isset(PLATFORM_FEE_CURRENCIES[$currency])) {
        $error = 'Choose a currency.';
    } else {
        saveSubscriptionPrices($conn, (float)$annual, (float)$perTournament, $currency);
        header("Location: admin_subscriptions.php?prices_saved=1");
        exit;
    }
}


/*
| Paid outside the website (e.g. bank transfer)
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'mark_paid') {

    $payTournamentID = (int)($_POST['tournamentID'] ?? 0);

    markPlatformFeePaid($conn, $payTournamentID, 'manual', 'Marked paid by ' . $_SESSION['userid']);

    header("Location: admin_subscriptions.php?marked=" . $payTournamentID);
    exit;
}

$prices = subscriptionPrices($conn);

$annualSubscriptions = $conn->query("
    SELECT s.*, a.admin_name, a.admin_organization
    FROM organizer_subscriptions s
    LEFT JOIN admins a ON a.adminID = s.adminID COLLATE utf8mb4_general_ci
    ORDER BY s.end_date DESC, s.subscriptionID DESC
")->fetch_all(MYSQLI_ASSOC);

// Per tournament only - tournaments covered by an annual plan are in the annual list
$payments = $conn->query("
    SELECT pp.*, t.tournament_name, a.admin_name
    FROM tournament_platform_payments pp
    JOIN tournament t ON t.tournamentID = pp.tournamentID
    LEFT JOIN admins a ON a.adminID = pp.adminID COLLATE utf8mb4_general_ci
    WHERE pp.plan = 'per_tournament'
    ORDER BY pp.status = 'paid', pp.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$unpaidCount = count(array_filter($payments, function ($payment) {
    return $payment['status'] !== 'paid';
}));

$today = date('Y-m-d');

$activeAnnual = count(array_filter($annualSubscriptions, function ($row) use ($today) {
    return $row['start_date'] <= $today && $row['end_date'] >= $today;
}));

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Subscriptions</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

    <link href="./assets/style.css" rel="stylesheet" type="text/css">
    <link href="./assets/admins.css?v=<?= filemtime(__DIR__ . '/assets/admins.css') ?>" rel="stylesheet" type="text/css">
    <link href="./assets/subscription.css?v=<?= filemtime(__DIR__ . '/assets/subscription.css') ?>" rel="stylesheet" type="text/css">

</head>

<body>

    <?php require_once 'admin_navbar.php'; ?>


    <main class="admins-page">

        <div class="page-title">
            <h1>Subscriptions</h1>
            <p>
                Tournament Organizers pay T_Software per tournament or once a year.
                <strong><?= $activeAnnual ?></strong> active annual plan<?= $activeAnnual === 1 ? '' : 's' ?>
                <?php if ($unpaidCount > 0): ?>
                    &middot; <strong><?= $unpaidCount ?></strong> unpaid tournament<?= $unpaidCount === 1 ? '' : 's' ?>
                <?php endif; ?>
            </p>
        </div>


        <?php if (isset($_GET['prices_saved'])): ?>
            <div class="success-message">The prices were updated. They apply to payments made from now on.</div>
        <?php endif; ?>

        <?php if (isset($_GET['marked'])): ?>
            <div class="success-message">The tournament was marked as paid &ndash; registration for it is now open.</div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>


        <div class="admins-layout">


            <div class="sub-main">

                <!-- ==========================================
                     ANNUAL SUBSCRIPTIONS
                =========================================== -->

                <section class="card">

                    <h2>Annual subscriptions <span class="count"><?= count($annualSubscriptions) ?></span></h2>

                    <?php if ($annualSubscriptions): ?>

                        <div class="table-wrap">
                            <table class="admins-table">
                                <thead>
                                    <tr>
                                        <th>Organizer</th>
                                        <th>Period</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($annualSubscriptions as $row): ?>
                                        <?php
                                        $state = $row['end_date'] < $today ? 'expired' : ($row['start_date'] > $today ? 'upcoming' : 'active');
                                        ?>
                                        <tr class="<?= $state === 'expired' ? 'row-inactive' : '' ?>">
                                            <td>
                                                <a class="table-link" href="admin_admin_details.php?id=<?= urlencode($row['adminID']) ?>">
                                                    <?= htmlspecialchars($row['admin_name'] ?? $row['adminID']) ?>
                                                </a>
                                                <small><?= htmlspecialchars($row['adminID']) ?><?= $row['admin_organization'] ? ' &middot; ' . htmlspecialchars($row['admin_organization']) : '' ?></small>
                                            </td>
                                            <td>
                                                <?= date('d M Y', strtotime($row['start_date'])) ?> &ndash; <?= date('d M Y', strtotime($row['end_date'])) ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars(formatPlatformFee($row['amount'], $row['currency'])) ?>
                                                <small><?= htmlspecialchars(ucfirst((string)$row['gateway'])) ?> &middot; <?= date('d M Y', strtotime($row['paid_at'])) ?></small>
                                            </td>
                                            <td>
                                                <span class="status-pill <?= $state === 'expired' ? 'inactive' : 'active' ?>">
                                                    <?= ucfirst($state) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php else: ?>

                        <p class="muted-text">No organizer has subscribed annually yet.</p>

                    <?php endif; ?>

                </section>


                <!-- ==========================================
                     TOURNAMENTS
                =========================================== -->

                <section class="card">

                    <h2>Per-tournament payments <span class="count"><?= count($payments) ?></span></h2>

                    <?php if ($payments): ?>

                        <div class="table-wrap">

                            <table class="admins-table">

                                <thead>
                                    <tr>
                                        <th>Tournament</th>
                                        <th>Organizer</th>
                                        <th>Fee</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach ($payments as $payment): ?>

                                        <tr>
                                            <td>
                                                <a class="table-link" href="admin_each_tournament.php?id=<?= (int)$payment['tournamentID'] ?>">
                                                    <?= htmlspecialchars($payment['tournament_name']) ?>
                                                </a>
                                                <small>Created <?= date('d M Y', strtotime($payment['created_at'])) ?></small>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($payment['admin_name'] ?? $payment['adminID']) ?>
                                                <small><?= htmlspecialchars($payment['adminID']) ?></small>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars(formatPlatformFee($payment['amount'], $payment['currency'])) ?>
                                            </td>
                                            <td>
                                                <?php if ($payment['status'] === 'paid'): ?>
                                                    <span class="role role-paid">Paid</span>
                                                    <small><?= date('d M Y', strtotime($payment['paid_at'])) ?> &middot; <?= htmlspecialchars(ucfirst((string)$payment['gateway'])) ?></small>
                                                <?php else: ?>
                                                    <span class="role role-unpaid">Unpaid</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($payment['status'] !== 'paid'): ?>
                                                    <form method="POST" action="admin_subscriptions.php"
                                                          onsubmit="return confirm('Mark this tournament as paid? Registration will open for players.');">
                                                        <input type="hidden" name="action" value="mark_paid">
                                                        <input type="hidden" name="tournamentID" value="<?= (int)$payment['tournamentID'] ?>">
                                                        <button type="submit" class="small-btn">Mark as paid</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php else: ?>

                        <p class="muted-text">No per-tournament payments yet.</p>

                    <?php endif; ?>

                </section>

            </div>


            <div class="sub-side">

                <!-- ==========================================
                     PRICES
                =========================================== -->

                <section class="card">

                    <h2>Subscription prices</h2>

                    <p class="muted-text">
                        Organizers choose a plan. Tournaments created by a Platform Admin are free.
                    </p>

                    <form method="POST" action="admin_subscriptions.php" class="add-form fee-form">

                        <input type="hidden" name="action" value="save_prices">

                        <div class="form-group">
                            <label for="currency">Currency</label>
                            <select id="currency" name="currency">
                                <?php foreach (PLATFORM_FEE_CURRENCIES as $code => $symbol): ?>
                                    <option value="<?= $code ?>" <?= $prices['currency'] === $code ? 'selected' : '' ?>><?= $code ?> (<?= $symbol ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="annual_amount">Annual <small>all tournaments for 12 months</small></label>
                            <input type="text" id="annual_amount" name="annual_amount" inputmode="decimal"
                                   value="<?= number_format($prices['annual'], 2, '.', '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="per_tournament_amount">Per tournament <small>each tournament</small></label>
                            <input type="text" id="per_tournament_amount" name="per_tournament_amount" inputmode="decimal"
                                   value="<?= number_format($prices['per_tournament'], 2, '.', '') ?>">
                        </div>

                        <button type="submit" class="add-btn">Save prices</button>

                    </form>

                </section>

            </div>

        </div>

    </main>

</body>

</html>
