<?php

/*
|--------------------------------------------------------------------------
| T_Software platform fee
|--------------------------------------------------------------------------
| Tournament Organizers pay T_Software a fixed fee for every tournament
| they create. Until it is paid, players can see the tournament but
| registration stays closed. Tournaments created by a Platform Admin have
| no fee (no row in tournament_platform_payments).
|
| The fee amount/currency are set by a Platform Admin (platform_settings).
*/

const PLATFORM_FEE_CURRENCIES = ['MYR' => 'RM', 'USD' => 'USD', 'SGD' => 'SGD'];


function platformFeeSettings($conn)
{
    $settings = ['tournament_fee_amount' => '100.00', 'tournament_fee_currency' => 'MYR'];

    $result = $conn->query("SELECT setting_key, setting_value FROM platform_settings");

    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    return [
        'amount' => round((float)$settings['tournament_fee_amount'], 2),
        'currency' => $settings['tournament_fee_currency'],
    ];
}


function savePlatformFeeSettings($conn, $amount, $currency)
{
    $stmt = $conn->prepare("
        INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");

    foreach (['tournament_fee_amount' => number_format($amount, 2, '.', ''), 'tournament_fee_currency' => $currency] as $key => $value) {
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


// The fee row of a tournament, or null when it has none (Platform Admin's tournaments)
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


// Called when a Tournament Organizer creates a tournament (uses today's fee)
function createTournamentPlatformFee($conn, $tournamentID, $adminID)
{
    $fee = platformFeeSettings($conn);

    $stmt = $conn->prepare("
        INSERT INTO tournament_platform_payments (tournamentID, adminID, amount, currency)
        VALUES (?, ?, ?, ?)
    ");

    $tournamentID = (int)$tournamentID;
    $stmt->bind_param("isds", $tournamentID, $adminID, $fee['amount'], $fee['currency']);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}


function markPlatformFeePaid($conn, $tournamentID, $gateway, $reference)
{
    $stmt = $conn->prepare("
        UPDATE tournament_platform_payments
        SET status = 'paid', gateway = ?, gateway_reference = ?, paid_at = NOW()
        WHERE tournamentID = ? AND status <> 'paid'
    ");

    $tournamentID = (int)$tournamentID;
    $stmt->bind_param("ssi", $gateway, $reference, $tournamentID);
    $stmt->execute();
    $stmt->close();
}
