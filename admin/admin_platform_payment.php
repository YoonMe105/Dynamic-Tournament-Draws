<?php

require_once '../db.php';
require_once 'admin_auth.php';
require_once '../platform_fee.php';
require_once 'payment_config.php';


/*
|--------------------------------------------------------------------------
| Pay the T_Software platform fee for a tournament
|--------------------------------------------------------------------------
| With a Stripe key (payment_config.php): Pay now -> Stripe Checkout ->
| back here with ?session_id=..., which is checked with Stripe before the
| fee is marked paid.
| Without a key: TEST MODE - Pay now opens admin_platform_checkout_test.php.
*/

$tournamentID = isset($_GET['id']) ? intval($_GET['id']) : 0;

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
$testMode = trim($stripeSecretKey) === '';
$error = '';

$pageUrl = "admin_platform_payment.php?id=" . $tournamentID;


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
| Back from Stripe: check the session really was paid
|--------------------------------------------------------------------------
*/

if ($payment && $payment['status'] !== 'paid' && !$testMode && !empty($_GET['session_id'])) {

    $session = stripeRequest($stripeSecretKey, 'GET', 'checkout/sessions/' . rawurlencode($_GET['session_id']));

    if (($session['payment_status'] ?? '') === 'paid' && (string)($session['client_reference_id'] ?? '') === (string)$tournamentID) {

        markPlatformFeePaid($conn, $tournamentID, 'stripe', $session['id']);

        header("Location: " . $pageUrl . "&paid=1");
        exit;
    }

    $error = 'The payment was not completed. Please try again.';
}


/*
|--------------------------------------------------------------------------
| Pay now
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay' && $payment && $payment['status'] !== 'paid') {

    if ($testMode) {

        // One-time token so the test checkout can't be opened for another tournament
        $_SESSION['platform_test_checkout'] = [
            'tournamentID' => $tournamentID,
            'token' => bin2hex(random_bytes(16)),
        ];

        header("Location: admin_platform_checkout_test.php?id=" . $tournamentID
            . "&token=" . $_SESSION['platform_test_checkout']['token']);
        exit;
    }

    $currency = strtolower($payment['currency']);

    $fields = [
        'mode' => 'payment',
        'client_reference_id' => (string)$tournamentID,
        'success_url' => adminBaseUrl() . $pageUrl . '&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => adminBaseUrl() . $pageUrl . '&cancelled=1',
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => $currency,
                'unit_amount' => (int)round($payment['amount'] * 100),
                'product_data' => ['name' => 'T_Software tournament fee: ' . $tournament['tournament_name']],
            ],
        ]],
        'payment_method_types' => $stripePaymentMethods,
        'metadata' => ['tournamentID' => (string)$tournamentID, 'adminID' => (string)$payment['adminID']],
    ];

    $session = stripeRequest($stripeSecretKey, 'POST', 'checkout/sessions', $fields);

    if (!empty($session['url'])) {
        header("Location: " . $session['url']);
        exit;
    }

    $error = 'Stripe could not start the payment: ' . ($session['error']['message'] ?? 'unknown error');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Platform Fee - <?= htmlspecialchars($tournament['tournament_name']) ?></title>

    <link href="./assets/style.css" rel="stylesheet" type="text/css">
    <link href="./assets/platform_payment.css?v=<?= filemtime(__DIR__ . '/assets/platform_payment.css') ?>" rel="stylesheet" type="text/css">

</head>

<body>

    <?php require_once 'admin_navbar.php'; ?>


    <main class="payment-page">

        <a href="admin_each_tournament.php?id=<?= $tournamentID ?>" class="back-link">&larr; Back to Tournament</a>


        <?php if (isset($_GET['created'])): ?>
            <div class="success-message">
                Tournament created. Pay the T_Software fee below to open registration to players.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['paid'])): ?>
            <div class="success-message">
                Payment received &ndash; thank you! Registration for this tournament is now open to players.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['cancelled'])): ?>
            <div class="error-message">The payment was cancelled. You can try again any time.</div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>


        <section class="pay-card">

            <p class="eyebrow">T_Software platform fee</p>

            <h1><?= htmlspecialchars($tournament['tournament_name']) ?></h1>

            <?php if (!$payment): ?>

                <p class="muted">This tournament has no platform fee.</p>

            <?php else: ?>

                <div class="amount-row">
                    <span class="amount"><?= htmlspecialchars(formatPlatformFee($payment['amount'], $payment['currency'])) ?></span>

                    <span class="status status-<?= $payment['status'] ?>">
                        <?= $payment['status'] === 'paid' ? 'Paid' : 'Unpaid' ?>
                    </span>
                </div>

                <?php if ($payment['status'] === 'paid'): ?>

                    <ul class="paid-details">
                        <li><span>Paid on</span> <?= date('d M Y, h:i A', strtotime($payment['paid_at'])) ?></li>
                        <li><span>Method</span> <?= htmlspecialchars(ucfirst((string)$payment['gateway'])) ?></li>
                        <li><span>Reference</span> <?= htmlspecialchars((string)$payment['gateway_reference']) ?></li>
                    </ul>

                    <p class="muted">Registration for this tournament is open to players.</p>

                <?php else: ?>

                    <p class="explain">
                        Players can already see this tournament, but <strong>registration stays closed until the fee is paid</strong>.
                        Pay online by <strong>card</strong> (Visa / Mastercard).
                        <?php if (!$testMode): ?>You'll enter your card details on Stripe's secure payment page.<?php endif; ?>
                    </p>

                    <?php if ($testMode): ?>
                        <p class="test-note">
                            <strong>Test mode:</strong> no Stripe key is set up yet, so this opens a pretend checkout &ndash;
                            no real money is taken. Add the key in <code>admin/payment_config.php</code> to take real payments.
                        </p>
                    <?php endif; ?>

                    <form method="POST" action="<?= htmlspecialchars($pageUrl) ?>">
                        <input type="hidden" name="action" value="pay">
                        <button type="submit" class="pay-btn">
                            Pay <?= htmlspecialchars(formatPlatformFee($payment['amount'], $payment['currency'])) ?> now
                        </button>
                    </form>

                <?php endif; ?>

            <?php endif; ?>

        </section>

    </main>

</body>

</html>
