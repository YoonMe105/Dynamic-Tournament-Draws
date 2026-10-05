<?php

require_once '../db.php';
require_once 'admin_auth.php';
require_once '../platform_fee.php';
require_once 'payment_config.php';


/*
|--------------------------------------------------------------------------
| TEST MODE card checkout (no Stripe key set up)
|--------------------------------------------------------------------------
| Stands in for Stripe's card page so the payment flow can be tried. Only
| works while $stripeSecretKey is empty, and only with the one-time token
| made on the payment page.
|
| The card number / expiry / CVC inputs have no "name", so the browser never
| sends them - they are only checked in the browser. Only the result and the
| last 4 digits come back to the server. Use Stripe's test cards:
|   4242 4242 4242 4242  -> paid
|   4000 0000 0000 0002  -> declined
*/

$tournamentID = isset($_GET['id']) ? intval($_GET['id']) : 0;

requireTournamentAccess($tournamentID);

$checkout = $_SESSION['platform_test_checkout'] ?? null;
$validToken = trim($stripeSecretKey) === ''
    && $checkout
    && (int)$checkout['tournamentID'] === $tournamentID
    && hash_equals($checkout['token'], (string)($_GET['token'] ?? ''));

if (!$validToken) {
    header("Location: admin_platform_payment.php?id=" . $tournamentID);
    exit;
}

$payment = tournamentPlatformPayment($conn, $tournamentID);

if (!$payment || $payment['status'] === 'paid') {
    header("Location: admin_platform_payment.php?id=" . $tournamentID);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    unset($_SESSION['platform_test_checkout']);

    $last4 = preg_match('/^\d{4}$/', $_POST['card_last4'] ?? '') ? $_POST['card_last4'] : '0000';
    $brand = in_array($_POST['card_brand'] ?? '', ['Visa', 'Mastercard', 'Amex', 'Card'], true) ? $_POST['card_brand'] : 'Card';

    if (($_POST['result'] ?? '') === 'pay') {
        markPlatformFeePaid($conn, $tournamentID, 'card (test)', $brand . ' **** ' . $last4 . ' / TEST-' . date('YmdHis'));
        header("Location: admin_platform_payment.php?id=" . $tournamentID . "&paid=1");
    } else {
        header("Location: admin_platform_payment.php?id=" . $tournamentID . "&cancelled=1");
    }

    exit;
}

$tournamentName = $conn->query("SELECT tournament_name FROM tournament WHERE tournamentID = " . (int)$tournamentID)->fetch_assoc()['tournament_name'] ?? '';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Card Payment (Test)</title>

    <link href="./assets/platform_payment.css?v=<?= filemtime(__DIR__ . '/assets/platform_payment.css') ?>" rel="stylesheet" type="text/css">

</head>

<body class="checkout-body">

    <section class="checkout-card">

        <p class="test-banner">TEST MODE &ndash; no real payment. Don't enter a real card.</p>

        <h1>T_Software</h1>

        <p class="muted"><?= htmlspecialchars($tournamentName) ?></p>

        <p class="checkout-amount"><?= htmlspecialchars(formatPlatformFee($payment['amount'], $payment['currency'])) ?></p>

        <form method="POST" id="cardForm" class="card-form" novalidate
              action="admin_platform_checkout_test.php?id=<?= $tournamentID ?>&token=<?= htmlspecialchars($checkout['token']) ?>">

            <!-- Only these go to the server -->
            <input type="hidden" name="result" value="pay">
            <input type="hidden" name="card_last4" id="cardLast4">
            <input type="hidden" name="card_brand" id="cardBrand">

            <label class="card-label" for="cardName">Name on card</label>
            <input type="text" id="cardName" class="card-input" autocomplete="off" placeholder="e.g. Tan Wei Ming">

            <label class="card-label" for="cardNumber">Card number <span class="card-brand" id="brandBadge"></span></label>
            <input type="text" id="cardNumber" class="card-input" inputmode="numeric" autocomplete="off"
                   placeholder="1234 5678 9012 3456" maxlength="23">

            <div class="card-row">
                <div>
                    <label class="card-label" for="cardExpiry">Expiry</label>
                    <input type="text" id="cardExpiry" class="card-input" inputmode="numeric" autocomplete="off"
                           placeholder="MM / YY" maxlength="7">
                </div>
                <div>
                    <label class="card-label" for="cardCvc">CVC</label>
                    <input type="text" id="cardCvc" class="card-input" inputmode="numeric" autocomplete="off"
                           placeholder="123" maxlength="4">
                </div>
            </div>

            <p class="card-error" id="cardError" hidden></p>

            <button type="submit" class="pay-btn" id="payBtn">
                Pay <?= htmlspecialchars(formatPlatformFee($payment['amount'], $payment['currency'])) ?>
            </button>

        </form>

        <form method="POST" action="admin_platform_checkout_test.php?id=<?= $tournamentID ?>&token=<?= htmlspecialchars($checkout['token']) ?>">
            <input type="hidden" name="result" value="cancel">
            <button type="submit" class="cancel-btn">Cancel</button>
        </form>

        <p class="test-cards">
            Test cards: <b>4242 4242 4242 4242</b> (paid) &middot; <b>4000 0000 0000 0002</b> (declined) &middot;
            any future expiry, any CVC
        </p>

    </section>


    <script>
        (function () {

            const number = document.getElementById('cardNumber');
            const expiry = document.getElementById('cardExpiry');
            const cvc = document.getElementById('cardCvc');
            const name = document.getElementById('cardName');
            const error = document.getElementById('cardError');
            const badge = document.getElementById('brandBadge');

            const digits = value => value.replace(/\D/g, '');

            function brandOf(cardNumber) {
                if (/^4/.test(cardNumber)) return 'Visa';
                if (/^(5[1-5]|2[2-7])/.test(cardNumber)) return 'Mastercard';
                if (/^3[47]/.test(cardNumber)) return 'Amex';
                return 'Card';
            }

            // Standard card number checksum
            function luhnOk(cardNumber) {
                let sum = 0;
                let double = false;

                for (let i = cardNumber.length - 1; i >= 0; i--) {
                    let d = parseInt(cardNumber[i], 10);
                    if (double) {
                        d *= 2;
                        if (d > 9) d -= 9;
                    }
                    sum += d;
                    double = !double;
                }

                return sum % 10 === 0;
            }

            // 4242424242424242 -> "4242 4242 4242 4242"
            number.addEventListener('input', function () {
                const d = digits(number.value).slice(0, 19);
                number.value = d.replace(/(.{4})/g, '$1 ').trim();
                badge.textContent = d.length ? brandOf(d) : '';
            });

            // 1228 -> "12 / 28"
            expiry.addEventListener('input', function () {
                const d = digits(expiry.value).slice(0, 4);
                expiry.value = d.length > 2 ? d.slice(0, 2) + ' / ' + d.slice(2) : d;
            });

            cvc.addEventListener('input', function () {
                cvc.value = digits(cvc.value).slice(0, 4);
            });

            function fail(message) {
                error.textContent = message;
                error.hidden = false;
                return false;
            }

            document.getElementById('cardForm').addEventListener('submit', function (event) {

                event.preventDefault();
                error.hidden = true;

                const cardNumber = digits(number.value);
                const exp = digits(expiry.value);

                if (name.value.trim() === '') {
                    return fail('Enter the name on the card.');
                }

                if (cardNumber.length < 13 || !luhnOk(cardNumber)) {
                    return fail('Your card number is not valid.');
                }

                if (exp.length !== 4) {
                    return fail('Enter the expiry date as MM / YY.');
                }

                const month = parseInt(exp.slice(0, 2), 10);
                const year = 2000 + parseInt(exp.slice(2), 10);
                const now = new Date();

                if (month < 1 || month > 12) {
                    return fail('The expiry month is not valid.');
                }

                if (year < now.getFullYear() || (year === now.getFullYear() && month < now.getMonth() + 1)) {
                    return fail('Your card has expired.');
                }

                if (cvc.value.length < 3) {
                    return fail('Enter the 3 or 4 digit CVC.');
                }

                // Stripe's "declined" test card
                if (cardNumber === '4000000000000002') {
                    return fail('Your card was declined. Try another card.');
                }

                document.getElementById('cardLast4').value = cardNumber.slice(-4);
                document.getElementById('cardBrand').value = brandOf(cardNumber);

                const button = document.getElementById('payBtn');
                button.disabled = true;
                button.textContent = 'Processing...';

                this.submit();
            });

        })();
    </script>

</body>

</html>
