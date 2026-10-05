<?php

require_once '../db.php';
require_once 'admin_auth.php';
require_once '../platform_fee.php';

requirePlatformAdmin();


/*
|--------------------------------------------------------------------------
| Tournament Fees
|--------------------------------------------------------------------------
| What Tournament Organizers pay T_Software for each tournament they create,
| and who has paid. Registration for an organizer's tournament opens once
| its fee is paid. Platform Admins only.
*/

$error = '';

$action = $_POST['action'] ?? '';


/*
| T_Software fee for new tournaments (Tournament Organizers)
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_fee') {

    $amount = trim($_POST['fee_amount'] ?? '');
    $currency = $_POST['fee_currency'] ?? '';

    if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $amount)) {
        $error = 'Enter the fee as an amount, e.g. 100 or 100.00.';
    } elseif (!isset(PLATFORM_FEE_CURRENCIES[$currency])) {
        $error = 'Choose a currency for the fee.';
    } else {
        savePlatformFeeSettings($conn, (float)$amount, $currency);
        header("Location: admin_tournament_fees.php?fee_saved=1");
        exit;
    }
}


/*
| Mark a fee as paid by hand (e.g. paid by bank transfer)
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'mark_paid') {

    $payTournamentID = (int)($_POST['tournamentID'] ?? 0);

    markPlatformFeePaid($conn, $payTournamentID, 'manual', 'Marked paid by ' . $_SESSION['userid']);

    header("Location: admin_tournament_fees.php?marked=" . $payTournamentID);
    exit;
}


$fee = platformFeeSettings($conn);

$payments = $conn->query("
    SELECT pp.*, t.tournament_name, a.admin_name
    FROM tournament_platform_payments pp
    JOIN tournament t ON t.tournamentID = pp.tournamentID
    LEFT JOIN admins a ON a.adminID = pp.adminID COLLATE utf8mb4_general_ci
    ORDER BY pp.status = 'paid', pp.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$unpaidCount = count(array_filter($payments, function ($payment) {
    return $payment['status'] !== 'paid';
}));

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tournament Fees</title>

    <link href="./assets/style.css" rel="stylesheet" type="text/css">
    <link href="./assets/admins.css?v=<?= filemtime(__DIR__ . '/assets/admins.css') ?>" rel="stylesheet" type="text/css">

</head>

<body>

    <?php require_once 'admin_navbar.php'; ?>


    <main class="admins-page">

        <div class="page-title">
            <h1>Tournament Fees</h1>
            <p>
                What Tournament Organizers pay T_Software for each tournament they create.
                <?php if ($unpaidCount > 0): ?>
                    <strong><?= $unpaidCount ?></strong> unpaid.
                <?php endif; ?>
            </p>
        </div>


        <?php if (isset($_GET['fee_saved'])): ?>
            <div class="success-message">The tournament fee was updated. It applies to tournaments created from now on.</div>
        <?php endif; ?>

        <?php if (isset($_GET['marked'])): ?>
            <div class="success-message">The fee was marked as paid &ndash; registration for that tournament is now open.</div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>


        <div class="admins-layout" id="payments">

            <section class="card">

                <h2>Tournament fee payments <span class="count"><?= count($payments) ?></span></h2>

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
                                        <td><?= htmlspecialchars(formatPlatformFee($payment['amount'], $payment['currency'])) ?></td>
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
                                                <form method="POST" action="admin_tournament_fees.php"
                                                      onsubmit="return confirm('Mark this fee as paid? Registration will open for players.');">
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

                    <p class="muted-text">No Tournament Organizer has created a tournament yet.</p>

                <?php endif; ?>

            </section>


            <section class="card">

                <h2>Tournament fee</h2>

                <p class="muted-text">
                    What a Tournament Organizer pays T_Software for each tournament they create.
                    Registration for their tournament opens once it's paid. Tournaments created by a Platform Admin are free.
                </p>

                <form method="POST" action="admin_tournament_fees.php" class="add-form fee-form">

                    <input type="hidden" name="action" value="save_fee">

                    <div class="form-row">

                        <div class="form-group">
                            <label for="fee_currency">Currency</label>
                            <select id="fee_currency" name="fee_currency">
                                <?php foreach (PLATFORM_FEE_CURRENCIES as $code => $symbol): ?>
                                    <option value="<?= $code ?>" <?= $fee['currency'] === $code ? 'selected' : '' ?>><?= $code ?> (<?= $symbol ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="fee_amount">Amount</label>
                            <input type="text" id="fee_amount" name="fee_amount" inputmode="decimal"
                                   value="<?= number_format($fee['amount'], 2, '.', '') ?>">
                        </div>

                    </div>

                    <button type="submit" class="add-btn">Save fee</button>

                </form>

            </section>

        </div>

    </main>

</body>

</html>
