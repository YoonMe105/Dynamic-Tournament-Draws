<?php

/*
|--------------------------------------------------------------------------
| T_Software subscription
|--------------------------------------------------------------------------
| Tournament Organizers pay T_Software a subscription, on one of two plans:
|
|   'annual'         one payment covers every tournament they create for a
|                    year (organizer_subscriptions: start_date - end_date)
|   'per_tournament' one payment for each tournament
|
| Both prices (and the currency) are set by a Platform Admin
| (platform_settings). Tournaments created by a Platform Admin are free.
|
| Every organizer tournament has a row in tournament_platform_payments:
|   - created while an annual subscription is running -> paid, plan 'annual'
|   - otherwise -> unpaid until they pay for it (plan 'per_tournament') or buy
|     the annual plan, which also covers all their unpaid tournaments
| Until it's paid, players can see the tournament but registration is closed.
*/

const PLATFORM_FEE_CURRENCIES = ['MYR' => 'RM', 'USD' => 'USD', 'SGD' => 'SGD'];

const SUBSCRIPTION_PLANS = [
    'annual' => 'Annual',
    'per_tournament' => 'Per tournament',
];


// ['annual' => 1000.0, 'per_tournament' => 150.0, 'currency' => 'MYR']
function subscriptionPrices($conn)
{
    $settings = [
        'subscription_annual_amount' => '1000.00',
        'subscription_per_tournament_amount' => '100.00',
        'subscription_currency' => 'MYR',
    ];

    $result = $conn->query("SELECT setting_key, setting_value FROM platform_settings");

    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    return [
        'annual' => round((float)$settings['subscription_annual_amount'], 2),
        'per_tournament' => round((float)$settings['subscription_per_tournament_amount'], 2),
        'currency' => $settings['subscription_currency'],
    ];
}


function saveSubscriptionPrices($conn, $annual, $perTournament, $currency)
{
    $stmt = $conn->prepare("
        INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");

    $values = [
        'subscription_annual_amount' => number_format($annual, 2, '.', ''),
        'subscription_per_tournament_amount' => number_format($perTournament, 2, '.', ''),
        'subscription_currency' => $currency,
    ];

    foreach ($values as $key => $value) {
        $stmt->bind_param("ss", $key, $value);
        $stmt->execute();
    }

    $stmt->close();
}


// "RM 100.00", "USD 50.00"
function formatPlatformFee($amount, $currency)
{
    return (PLATFORM_FEE_CURRENCIES[$currency] ?? $currency) . ' ' . number_format((float)$amount, 2);
}


/*
|--------------------------------------------------------------------------
| Annual plan
|--------------------------------------------------------------------------
*/

// The organizer's annual subscription running today, or null
function activeAnnualSubscription($conn, $adminID)
{
    $stmt = $conn->prepare("
        SELECT * FROM organizer_subscriptions
        WHERE adminID = ? AND CURDATE() BETWEEN start_date AND end_date
        ORDER BY end_date DESC
        LIMIT 1
    ");
    $stmt->bind_param("s", $adminID);
    $stmt->execute();

    $subscription = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return $subscription ?: null;
}


// The organizer's latest annual subscription (running or expired), or null
function latestAnnualSubscription($conn, $adminID)
{
    $stmt = $conn->prepare("SELECT * FROM organizer_subscriptions WHERE adminID = ? ORDER BY end_date DESC LIMIT 1");
    $stmt->bind_param("s", $adminID);
    $stmt->execute();

    $subscription = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return $subscription ?: null;
}


// A new year starts today, or the day after the running subscription ends (renewing early)
function nextAnnualPeriod($conn, $adminID)
{
    $latest = latestAnnualSubscription($conn, $adminID);

    $start = date('Y-m-d');

    if ($latest && $latest['end_date'] >= $start) {
        $start = date('Y-m-d', strtotime($latest['end_date'] . ' +1 day'));
    }

    return [
        'start' => $start,
        'end' => date('Y-m-d', strtotime($start . ' +1 year -1 day')),
    ];
}


/*
| Annual plan paid: save it, and cover every unpaid tournament of this
| organizer (registration opens for all of them).
*/
function recordAnnualSubscription($conn, $adminID, $amount, $currency, $gateway, $reference)
{
    $period = nextAnnualPeriod($conn, $adminID);

    $stmt = $conn->prepare("
        INSERT INTO organizer_subscriptions (adminID, amount, currency, start_date, end_date, gateway, gateway_reference)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sdsssss", $adminID, $amount, $currency, $period['start'], $period['end'], $gateway, $reference);
    $stmt->execute();

    $subscriptionID = $stmt->insert_id;

    $stmt->close();

    $stmt = $conn->prepare("
        UPDATE tournament_platform_payments
        SET status = 'paid', plan = 'annual', subscriptionID = ?, gateway = ?, gateway_reference = ?, paid_at = NOW()
        WHERE adminID = ? AND status <> 'paid'
    ");
    $stmt->bind_param("isss", $subscriptionID, $gateway, $reference, $adminID);
    $stmt->execute();
    $stmt->close();

    return $subscriptionID;
}


/*
|--------------------------------------------------------------------------
| Per tournament
|--------------------------------------------------------------------------
*/

// The payment row of a tournament, or null when it has none (Platform Admin's tournaments)
function tournamentPlatformPayment($conn, $tournamentID)
{
    $stmt = $conn->prepare("SELECT * FROM tournament_platform_payments WHERE tournamentID = ?");
    $tournamentID = (int)$tournamentID;
    $stmt->bind_param("i", $tournamentID);
    $stmt->execute();

    $payment = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return $payment ?: null;
}


// True when the organizer still has to pay - registration stays closed
function platformFeeUnpaid($conn, $tournamentID)
{
    $payment = tournamentPlatformPayment($conn, $tournamentID);

    return $payment !== null && $payment['status'] !== 'paid';
}


/*
| Called when a Tournament Organizer creates a tournament. Covered straight
| away by a running annual subscription, otherwise unpaid at today's
| per-tournament price. Returns 'annual' / 'unpaid', or false on error.
*/
function createTournamentPlatformFee($conn, $tournamentID, $adminID)
{
    $prices = subscriptionPrices($conn);
    $annual = activeAnnualSubscription($conn, $adminID);
    $tournamentID = (int)$tournamentID;

    if ($annual) {

        $stmt = $conn->prepare("
            INSERT INTO tournament_platform_payments
                (tournamentID, adminID, plan, subscriptionID, amount, currency, status, gateway, gateway_reference, paid_at)
            VALUES (?, ?, 'annual', ?, 0, ?, 'paid', 'annual', ?, NOW())
        ");
        $reference = 'Annual plan until ' . $annual['end_date'];
        $stmt->bind_param("isiss", $tournamentID, $adminID, $annual['subscriptionID'], $annual['currency'], $reference);
        $result = 'annual';

    } else {

        $stmt = $conn->prepare("
            INSERT INTO tournament_platform_payments (tournamentID, adminID, plan, amount, currency)
            VALUES (?, ?, 'per_tournament', ?, ?)
        ");
        $stmt->bind_param("isds", $tournamentID, $adminID, $prices['per_tournament'], $prices['currency']);
        $result = 'unpaid';
    }

    $ok = $stmt->execute();
    $stmt->close();

    return $ok ? $result : false;
}


function markPlatformFeePaid($conn, $tournamentID, $gateway, $reference)
{
    $stmt = $conn->prepare("
        UPDATE tournament_platform_payments
        SET status = 'paid', plan = 'per_tournament', gateway = ?, gateway_reference = ?, paid_at = NOW()
        WHERE tournamentID = ? AND status <> 'paid'
    ");

    $tournamentID = (int)$tournamentID;
    $stmt->bind_param("ssi", $gateway, $reference, $tournamentID);
    $stmt->execute();
    $stmt->close();
}


// Short label for a tournament's payment row: "Annual plan", "Paid", "Unpaid"
function tournamentPaymentLabel($payment)
{
    if (!$payment || empty($payment['status'])) {
        return null;
    }

    if ($payment['status'] !== 'paid') {
        return 'Unpaid';
    }

    return ($payment['plan'] ?? '') === 'annual' ? 'Annual plan' : 'Paid';
}
