<?php

require_once '../db.php';
require_once 'admin_auth.php';
require_once '../platform_fee.php';

requirePlatformAdmin();


/*
|--------------------------------------------------------------------------
| Admin details
|--------------------------------------------------------------------------
| One admin's profile, status and the tournaments they created. Platform
| Admins can deactivate / activate them here (handled by admin_admins.php).
*/

$adminID = trim($_GET['id'] ?? '');

$stmt = $conn->prepare("SELECT * FROM admins WHERE adminID = ?");
$stmt->bind_param("s", $adminID);
$stmt->execute();

$admin = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$admin) {
    header("Location: admin_admins.php?problem=not_found");
    exit;
}

$isPlatform = $admin['admin_role'] === 'platform';
$isActive = $admin['admin_active'] === 'active';
$isMe = $admin['adminID'] === $_SESSION['userid'];


// Tournaments this admin created, newest first
$stmt = $conn->prepare("
    SELECT t.tournamentID, t.tournament_name, t.tournament_startdate, t.tournament_enddate,
           t.tournament_type, t.tournament_country,
           pp.status AS fee_status, pp.amount AS fee_amount, pp.currency AS fee_currency,
           (SELECT COUNT(*) FROM tournament_register tr WHERE tr.tournamentID = t.tournamentID) AS players
    FROM tournament t
    LEFT JOIN tournament_platform_payments pp ON pp.tournamentID = t.tournamentID
    WHERE t.creatorID = ?
    ORDER BY t.tournamentID DESC
");
$stmt->bind_param("s", $adminID);
$stmt->execute();

$tournaments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();


function detailDate($value, $format = 'd M Y')
{
    if (empty($value) || strpos($value, '0000') === 0) {
        return '-';
    }

    return date($format, strtotime($value));
}


// Saved photo path -> usable path, or null when the file is missing
$photo = null;
$photoPath = trim((string)$admin['admin_profile']);

if ($photoPath !== '' && strtoupper($photoPath) !== 'NULL' && is_file(__DIR__ . '/' . $photoPath)) {
    $photo = $photoPath;
}

$initials = strtoupper(implode('', array_map(function ($word) {
    return substr($word, 0, 1);
}, array_slice(preg_split('/\s+/', trim($admin['admin_name'])), 0, 2))));

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($admin['admin_name']) ?> | Admins</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

    <link href="./assets/style.css" rel="stylesheet" type="text/css">
    <link href="./assets/admins.css?v=<?= filemtime(__DIR__ . '/assets/admins.css') ?>" rel="stylesheet" type="text/css">

</head>

<body>

    <?php require_once 'admin_navbar.php'; ?>


    <main class="admins-page">

        <a href="admin_admins.php" class="back-link">&larr; Back to Admins</a>


        <?php if (isset($_GET['changed'])): ?>
            <div class="success-message">
                <?= ($_GET['status'] ?? '') === 'inactive'
                    ? 'This admin has been deactivated and can no longer log in.'
                    : 'This admin has been activated and can log in again.' ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['problem'])): ?>
            <div class="error-message">
                <?= [
                    'self' => "You can't deactivate your own account.",
                    'last_platform' => "This is the last active Platform Admin - activate or add another Platform Admin first.",
                ][$_GET['problem']] ?? 'The admin could not be changed.' ?>
            </div>
        <?php endif; ?>


        <!-- ==========================================
             PROFILE
        =========================================== -->

        <section class="card detail-header">

            <span class="detail-avatar">
                <?php if ($photo): ?>
                    <img src="<?= htmlspecialchars($photo) ?>" alt="">
                <?php else: ?>
                    <?= htmlspecialchars($initials) ?>
                <?php endif; ?>
            </span>

            <div class="detail-title">

                <h1><?= htmlspecialchars($admin['admin_name']) ?></h1>

                <?php if (!$isPlatform && trim((string)$admin['admin_organization']) !== ''): ?>
                    <p class="detail-organization"><?= htmlspecialchars($admin['admin_organization']) ?></p>
                <?php endif; ?>

                <div class="detail-badges">
                    <span class="role <?= $isPlatform ? 'role-platform' : 'role-organizer' ?>">
                        <?= $isPlatform ? 'Platform Admin' : 'Tournament Organizer' ?>
                    </span>
                    <span class="status-pill <?= $isActive ? 'active' : 'inactive' ?>">
                        <?= $isActive ? 'Active' : 'Inactive' ?>
                    </span>
                    <?php if ($isMe): ?>
                        <span class="you">you</span>
                    <?php endif; ?>
                </div>

            </div>

            <div class="detail-actions">

                <?php if ($isMe): ?>

                    <a class="action-btn" href="admin_my_profile.php">Edit my profile</a>

                <?php else: ?>

                    <form method="POST" action="admin_admins.php"
                          onsubmit="return confirm('<?= $isActive
                              ? 'Deactivate ' . htmlspecialchars(addslashes($admin['admin_name']), ENT_QUOTES) . '? They will not be able to log in.'
                              : 'Activate ' . htmlspecialchars(addslashes($admin['admin_name']), ENT_QUOTES) . '? They will be able to log in again.' ?>');">
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="adminID" value="<?= htmlspecialchars($admin['adminID']) ?>">
                        <input type="hidden" name="return" value="details">
                        <button type="submit" class="action-btn big <?= $isActive ? 'danger' : 'positive' ?>">
                            <i class="fa-solid <?= $isActive ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                            <?= $isActive ? 'Deactivate admin' : 'Activate admin' ?>
                        </button>
                    </form>

                <?php endif; ?>

            </div>

        </section>


        <div class="detail-layout">


            <!-- ==========================================
                 DETAILS
            =========================================== -->

            <section class="card">

                <h2>Details</h2>

                <dl class="detail-list">
                    <div><dt>Admin ID</dt><dd><?= htmlspecialchars($admin['adminID']) ?></dd></div>
                    <div><dt>Email</dt><dd><?= htmlspecialchars($admin['admin_email'] ?: '-') ?></dd></div>
                    <div><dt>Contact no.</dt><dd><?= htmlspecialchars($admin['admin_contact'] ?: '-') ?></dd></div>
                    <div><dt>IC / Passport</dt><dd><?= htmlspecialchars($admin['admin_ic_passport'] ?: '-') ?></dd></div>
                    <div><dt>Gender</dt><dd><?= htmlspecialchars(ucfirst(strtolower(trim((string)$admin['admin_gender']))) ?: '-') ?></dd></div>
                    <div><dt>Country</dt><dd><?= htmlspecialchars(trim((string)$admin['admin_country']) ?: '-') ?></dd></div>
                    <div><dt>Member since</dt><dd><?= detailDate($admin['admin_register']) ?></dd></div>
                    <div>
                        <dt>Can do</dt>
                        <dd><?= $isPlatform
                            ? 'Everything, on all tournaments'
                            : 'Creates tournaments; everything on their own tournaments' ?></dd>
                    </div>
                </dl>

            </section>


            <!-- ==========================================
                 TOURNAMENTS CREATED
            =========================================== -->

            <section class="card">

                <h2>Tournaments created <span class="count"><?= count($tournaments) ?></span></h2>

                <?php if ($tournaments): ?>

                    <div class="table-wrap">

                        <table class="admins-table">

                            <thead>
                                <tr>
                                    <th>Tournament</th>
                                    <th>Dates</th>
                                    <th class="center">Players</th>
                                    <th>T_Software fee</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($tournaments as $tournament): ?>

                                    <tr>
                                        <td>
                                            <a class="table-link" href="admin_each_tournament.php?id=<?= (int)$tournament['tournamentID'] ?>">
                                                <?= htmlspecialchars($tournament['tournament_name']) ?>
                                            </a>
                                            <small>
                                                <?= htmlspecialchars(strtoupper($tournament['tournament_type'])) ?>
                                                <?= trim((string)$tournament['tournament_country']) !== '' ? '&middot; ' . htmlspecialchars(trim($tournament['tournament_country'])) : '' ?>
                                            </small>
                                        </td>
                                        <td><?= detailDate($tournament['tournament_startdate']) ?> &ndash; <?= detailDate($tournament['tournament_enddate']) ?></td>
                                        <td class="center"><?= (int)$tournament['players'] ?></td>
                                        <td>
                                            <?php if ($tournament['fee_status'] === 'paid'): ?>
                                                <span class="role role-paid">Paid</span>
                                            <?php elseif ($tournament['fee_status'] === 'unpaid'): ?>
                                                <span class="role role-unpaid">Unpaid</span>
                                                <small><?= htmlspecialchars(formatPlatformFee($tournament['fee_amount'], $tournament['fee_currency'])) ?></small>
                                            <?php else: ?>
                                                <span class="muted-dash">&ndash;</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <p class="muted-text">No tournaments created yet.</p>

                <?php endif; ?>

            </section>

        </div>

    </main>

</body>

</html>
