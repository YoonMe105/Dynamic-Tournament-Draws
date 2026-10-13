<?php

require_once '../db.php';
require_once 'admin_auth.php';
require_once '../platform_fee.php';
require_once 'payment_config.php';


/*
|--------------------------------------------------------------------------
| Pay the T_Software subscription
|--------------------------------------------------------------------------
| ?id=<tournamentID>  a tournament that isn't paid yet: the organizer picks
|                     "Per tournament" (just this one) or "Annual" (this one
|                     and every tournament for a year)
| no id               the Annual plan on its own (from admin_my_subscription.php)
|
| With a Stripe key (payment_config.php): Pay -> Stripe Checkout -> back here
| with ?session_id=..., which is checked with Stripe before it's recorded.
| Without a key: TEST MODE - Pay opens admin_platform_checkout_test.php.
*/

$tournamentID = isset($_GET['id']) ? intval($_GET['id']) : 0;
$tournament = null;
$payment = null;

if ($tournamentID > 0) {

    // Platform Admins, or the organizer who created this tournament
    requireTournamentAccess($tournamentID);

    $stmt = $conn->prepare("SELECT tournamentID, tournament_name, creatorID FROM tournament WHERE tournamentID = ?");
    $stmt->bind_param("i", $tournamentID);
    $stmt->execute();

    $tournament = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$tournament) {
        die("Tournament not found.");
    }

    $payment = tournamentPlatformPayment($conn, $tournamentID);
}

// The Annual plan on its own is only for Tournament Organizers
if (!$tournament && isPlatformAdmin()) {
    header("Location: admin_subscriptions.php");
    exit;
}

// Only Tournament Organizers pay; Platform Admins can look
$payerID = $payment ? $payment['adminID'] : $_SESSION['userid'];
$canPay = !isPlatformAdmin() && (string)$payerID === (string)$_SESSION['userid'];

$prices = subscriptionPrices($conn);
$annualNow = $canPay ? activeAnnualSubscription($conn, $payerID) : null;
$nextPeriod = nextAnnualPeriod($conn, $payerID);

$testMode = trim($stripeSecretKey) === '';
$error = '';

$pageUrl = "admin_platform_payment.php" . ($tournamentID > 0 ? "?id=" . $tournamentID : "?annual=1");


// What each plan costs right now for this payer
function planAmount($plan, $payment, $prices)
{
    if ($plan === 'per_tournament') {
        return $payment ? ['amount' => (float)$payment['amount'], 'currency' => $payment['currency']] : null;
    }

    return ['amount' => $prices['annual'], 'currency' => $prices['currency']];
}


// Where to go once paid
function afterPaymentUrl($tournamentID, $plan)
{
    return $tournamentID > 0
        ? "admin_platform_payment.php?id=" . $tournamentID . "&paid=" . $plan
        : "admin_my_subscription.php?paid=" . $plan;
}


/*
|--------------------------------------------------------------------------
| Stripe helpers
|--------------------------------------------------------------------------
*/

function stripeRequest($secretKey, $method, $path, $fields = [])
{
    $ch = curl_init('https://api.stripe.com/v1/' . $path);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $secretKey . ':',
        CURLOPT_TIMEOUT => 30,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }

    $body = curl_exec($ch);
    $error = curl_error($ch);

    curl_close($ch);

    if ($body === false) {
        return ['error' => ['message' => 'Could not reach Stripe: ' . $error]];
    }

    return json_decode($body, true) ?: ['error' => ['message' => 'Unexpected reply from Stripe.']];
}


// Full URL of this folder, for Stripe's success / cancel links
function adminBaseUrl()
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

    return $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/';
}


/*
|--------------------------------------------------------------------------
| Back from Stripe: check the session really was paid, then record it once
|--------------------------------------------------------------------------
*/

if ($canPay && !$testMode && !empty($_GET['session_id'])) {

    $session = stripeRequest($stripeSecretKey, 'GET', 'checkout/sessions/' . rawurlencode($_GET['session_id']));
    $plan = $session['metadata']['plan'] ?? '';
    $expectedReference = $payerID . ':' . $plan . ':' . $tournamentID;

    if (($session['payment_status'] ?? '') === 'paid' && (string)($session['client_reference_id'] ?? '') === $expectedReference) {

        if ($plan === 'annual') {

            // A refresh of this page must not add a second year
            $stmt = $conn->prepare("SELECT 1 FROM organizer_subscriptions WHERE gateway_reference = ?");
            $stmt->bind_param("s", $session['id']);
            $stmt->execute();
            $alreadySaved = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if (!$alreadySaved) {
                recordAnnualSubscription($conn, $payerID, $session['amount_total'] / 100, strtoupper($session['currency']), 'stripe', $session['id']);
            }

        } elseif ($plan === 'per_tournament' && $tournamentID > 0) {
            markPlatformFeePaid($conn, $tournamentID, 'stripe', $session['id']);
        }

        header("Location: " . afterPaymentUrl($tournamentID, $plan));
        exit;
    }

    $error = 'The payment was not completed. Please try again.';
}


/*
|--------------------------------------------------------------------------
| Pay
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay' && $canPay) {

    $plan = $_POST['plan'] ?? '';

    // Per tournament only for an unpaid tournament; Annual any time
    $allowed = $plan === 'annual'
        || ($plan === 'per_tournament' && $payment && $payment['status'] !== 'paid');

    $price = $allowed ? planAmount($plan, $payment, $prices) : null;

    if (!$price) {

        $error = 'Please choose a plan.';

    } elseif ($testMode) {

        // One-time token so the test checkout can't be reused for something else
        $_SESSION['platform_test_checkout'] = [
            'token' => bin2hex(random_bytes(16)),
            'plan' => $plan,
            'tournamentID' => $tournamentID,
            'adminID' => $payerID,
            'amount' => $price['amount'],
            'currency' => $price['currency'],
        ];

        header("Location: admin_platform_checkout_test.php?token=" . $_SESSION['platform_test_checkout']['token']);
        exit;

    } else {

        $name = $plan === 'annual'
            ? 'T_Software annual subscription (' . date('d M Y', strtotime($nextPeriod['start'])) . ' - ' . date('d M Y', strtotime($nextPeriod['end'])) . ')'
            : 'T_Software subscription: ' . $tournament['tournament_name'];

        $separator = strpos($pageUrl, '?') === false ? '?' : '&';

        $fields = [
            'mode' => 'payment',
            'client_reference_id' => $payerID . ':' . $plan . ':' . $tournamentID,
            'success_url' => adminBaseUrl() . $pageUrl . $separator . 'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => adminBaseUrl() . $pageUrl . $separator . 'cancelled=1',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($price['currency']),
                    'unit_amount' => (int)round($price['amount'] * 100),
                    'product_data' => ['name' => $name],
                ],
            ]],
            'payment_method_types' => $stripePaymentMethods,
            'metadata' => ['plan' => $plan, 'tournamentID' => (string)$tournamentID, 'adminID' => (string)$payerID],
        ];

        $session = stripeRequest($stripeSecretKey, 'POST', 'checkout/sessions', $fields);

        if (!empty($session['url'])) {
            header("Location: " . $session['url']);
            exit;
        }

        $error = 'Stripe could not start the payment: ' . ($session['error']['message'] ?? 'unknown error');
    }
}


$isPaid = $payment && $payment['status'] === 'paid';
$perTournamentPrice = planAmount('per_tournament', $payment, $prices);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Subscription<?= $tournament ? ' - ' . htmlspecialchars($tournament['tournament_name']) : '' ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

    <link href="./assets/style.css" rel="stylesheet" type="text/css">
    <link href="./assets/platform_payment.css?v=<?= filemtime(__DIR__ . '/assets/platform_payment.css') ?>" rel="stylesheet" type="text/css">

</head>

<body>

    <?php require_once 'admin_navbar.php'; ?>


    <main class="payment-page">

        <?php if ($tournament): ?>
            <a href="admin_each_tournament.php?id=<?= $tournamentID ?>" class="back-link">&larr; Back to Tournament</a>
        <?php else: ?>
            <a href="admin_my_subscription.php" class="back-link">&larr; Back to Subscription</a>
        <?php endif; ?>


        <?php if (isset($_GET['created'])): ?>
            <div class="success-message">
                Tournament created. Choose how to pay your T_Software subscription to open registration to players.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['paid'])): ?>
            <div class="success-message">
                Payment received &ndash; thank you!
                <?= $_GET['paid'] === 'annual'
                    ? 'Your annual subscription is active, and registration is open for all your tournaments.'
                    : 'Registration for this tournament is now open to players.' ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['cancelled'])): ?>
            <div class="error-message">The payment was cancelled. You can try again any time.</div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>


        <section class="pay-card">

            <p class="eyebrow">T_Software subscription</p>

            <h1><?= $tournament ? htmlspecialchars($tournament['tournament_name']) : 'Annual subscription' ?></h1>


            <?php if ($tournament && !$payment): ?>

                <p class="muted">This tournament was created by a Platform Admin &ndash; no subscription needed.</p>

            <?php elseif ($isPaid): ?>

                <!-- ============ Already paid ============ -->

                <div class="amount-row">
                    <span class="amount">
                        <?= $payment['plan'] === 'annual' ? 'Annual plan' : htmlspecialchars(formatPlatformFee($payment['amount'], $payment['currency'])) ?>
                    </span>
                    <span class="status status-paid">Paid</span>
                </div>

                <ul class="paid-details">
                    <li><span>Plan</span> <?= SUBSCRIPTION_PLANS[$payment['plan']] ?? 'Per tournament' ?></li>
                    <li><span>Paid on</span> <?= date('d M Y, h:i A', strtotime($payment['paid_at'])) ?></li>
                    <?php if ($payment['plan'] !== 'annual'): ?>
                        <li><span>Method</span> <?= htmlspecialchars(ucfirst((string)$payment['gateway'])) ?></li>
                    <?php endif; ?>
                    <li><span>Reference</span> <?= htmlspecialchars((string)$payment['gateway_reference']) ?></li>
                </ul>

                <p class="muted">Registration for this tournament is open to players.</p>

            <?php elseif (!$canPay): ?>

                <!-- ============ Platform Admin looking ============ -->

                <div class="amount-row">
                    <span class="amount"><?= $payment ? htmlspecialchars(formatPlatformFee($payment['amount'], $payment['currency'])) : '-' ?></span>
                    <span class="status status-unpaid">Unpaid</span>
                </div>

                <p class="muted">
                    Waiting for the Tournament Organizer to pay (per tournament or the annual plan).
                    Registration stays closed until then. You can mark it as paid on
                    <a href="admin_subscriptions.php">Subscriptions</a>.
                </p>

            <?php else: ?>

                <!-- ============ Choose a plan and pay ============ -->

                <?php if ($tournament): ?>
                    <p class="explain">
                        Players can already see this tournament, but <strong>registration stays closed until it's paid</strong>.
                        Choose a plan &ndash; pay online by <strong>card</strong> (Visa / Mastercard).
                    </p>
                <?php else: ?>
                    <p class="explain">
                        One payment covers <strong>every tournament you create for a year</strong>, and opens registration for
                        any of your tournaments that aren't paid yet. Pay online by <strong>card</strong> (Visa / Mastercard).
                    </p>
                <?php endif; ?>

                <?php if ($annualNow): ?>
                    <p class="test-note">
                        Your annual subscription runs until <strong><?= date('d M Y', strtotime($annualNow['end_date'])) ?></strong>.
                        Renewing now adds a year from <?= date('d M Y', strtotime($nextPeriod['start'])) ?>.
                    </p>
                <?php endif; ?>

                <form method="POST" action="<?= htmlspecialchars($pageUrl) ?>" class="plan-form">

                    <input type="hidden" name="action" value="pay">

                    <div class="plan-options">

                        <?php if ($tournament && $perTournamentPrice): ?>
                            <label class="plan-option">
                                <input type="radio" name="plan" value="per_tournament" checked>
                                <span class="plan-body">
                                    <span class="plan-name"><i class="fa-solid fa-ticket"></i> Per tournament</span>
                                    <span class="plan-price"><?= htmlspecialchars(formatPlatformFee($perTournamentPrice['amount'], $perTournamentPrice['currency'])) ?></span>
                                    <span class="plan-note">Just this tournament.</span>
                                </span>
                            </label>
                        <?php endif; ?>

                        <label class="plan-option">
                            <input type="radio" name="plan" value="annual" <?= $tournament ? '' : 'checked' ?>>
                            <span class="plan-body">
                                <span class="plan-name"><i class="fa-solid fa-calendar-check"></i> Annual</span>
                                <span class="plan-price"><?= htmlspecialchars(formatPlatformFee($prices['annual'], $prices['currency'])) ?> <small>/ year</small></span>
                                <span class="plan-note">
                                    Every tournament you create from
                                    <?= date('d M Y', strtotime($nextPeriod['start'])) ?> to <?= date('d M Y', strtotime($nextPeriod['end'])) ?><?= $tournament ? ', including this one' : '' ?>.
                                </span>
                            </span>
                        </label>

                    </div>

                    <?php if ($testMode): ?>
                        <p class="test-note">
                            <strong>Test mode:</strong> no Stripe key is set up yet, so this opens a pretend checkout &ndash;
                            no real money is taken. Add the key in <code>admin/payment_config.php</code> to take real payments.
                        </p>
                    <?php endif; ?>

                    <button type="submit" class="pay-btn">Continue to payment</button>

                </form>

            <?php endif; ?>

        </section>

    </main>

</body>

</html>
