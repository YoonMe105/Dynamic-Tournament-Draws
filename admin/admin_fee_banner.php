<?php

require_once __DIR__ . '/../platform_fee.php';

/*
|--------------------------------------------------------------------------
| "Subscription unpaid" banner for a tournament's admin pages
|--------------------------------------------------------------------------
| Shows nothing when the tournament needs no subscription or it's paid
| (per tournament or by the annual plan). The organizer gets a Pay now
| button; a Platform Admin sees it's waiting.
*/

function platformFeeBanner($conn, $tournamentID)
{
    $payment = tournamentPlatformPayment($conn, $tournamentID);

    if (!$payment || $payment['status'] === 'paid') {
        return;
    }


    ?>
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;
                margin:0 0 18px;padding:14px 18px;border:1px solid #f1d7a1;border-radius:10px;
                background:#fff7e6;color:#8a5a00;font-size:14px;">
        <span>
            <strong>T_Software subscription unpaid</strong> &ndash;
            players can see this tournament, but registration stays closed until it's paid
            (per tournament or with the annual plan).
        </span>
        <?php if (isPlatformAdmin()): ?>
            <a href="admin_subscriptions.php"
               style="padding:8px 14px;border-radius:7px;background:#fff;border:1px solid #993D86;color:#993D86;font-weight:bold;text-decoration:none;">
                View payments
            </a>
        <?php else: ?>
            <a href="admin_platform_payment.php?id=<?= (int)$tournamentID ?>"
               style="padding:8px 16px;border-radius:7px;background:#993D86;color:#fff;font-weight:bold;text-decoration:none;">
                Pay now
            </a>
        <?php endif; ?>
    </div>
    <?php
}
