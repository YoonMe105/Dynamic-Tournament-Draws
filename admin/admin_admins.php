<?php

require_once '../db.php';
require_once 'admin_auth.php';

requirePlatformAdmin();


/*
|--------------------------------------------------------------------------
| Admins
|--------------------------------------------------------------------------
| All admins and what they can do. New admins are added on
| admin_add_admin.php.
*/

/*
|--------------------------------------------------------------------------
| Deactivate / activate an admin
|--------------------------------------------------------------------------
| A deactivated admin can't log in (and is logged out at once). Nobody can
| deactivate themselves, or the last active Platform Admin.
*/

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {

    $targetID = trim($_POST['adminID'] ?? '');
    $backTo = ($_POST['return'] ?? '') === 'details'
        ? 'admin_admin_details.php?id=' . urlencode($targetID) . '&'
        : 'admin_admins.php?';

    $stmt = $conn->prepare("SELECT adminID, admin_role, admin_active FROM admins WHERE adminID = ?");
    $stmt->bind_param("s", $targetID);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $activePlatformAdmins = (int)$conn->query("
        SELECT COUNT(*) AS total FROM admins WHERE admin_role = 'platform' AND admin_active = 'active'
    ")->fetch_assoc()['total'];

    if (!$target) {
        $problem = 'not_found';
    } elseif ($target['adminID'] === $_SESSION['userid']) {
        $problem = 'self';
    } elseif ($target['admin_active'] === 'active' && $target['admin_role'] === 'platform' && $activePlatformAdmins <= 1) {
        $problem = 'last_platform';
    } else {
        $problem = '';
    }

    if ($problem !== '') {
        header("Location: " . $backTo . "problem=" . $problem);
        exit;
    }

    $newStatus = $target['admin_active'] === 'active' ? 'inactive' : 'active';

    $stmt = $conn->prepare("UPDATE admins SET admin_active = ? WHERE adminID = ?");
    $stmt->bind_param("ss", $newStatus, $targetID);
    $stmt->execute();
    $stmt->close();

    header("Location: " . $backTo . "changed=" . urlencode($targetID) . "&status=" . $newStatus);
    exit;
}


/*
|--------------------------------------------------------------------------
| Admin list
|--------------------------------------------------------------------------
*/

$admins = $conn->query("
    SELECT adminID, admin_name, admin_organization, admin_email, admin_contact, admin_country, admin_register, admin_role, admin_active,
           (SELECT COUNT(*) FROM tournament t WHERE t.creatorID = a.adminID COLLATE utf8mb4_unicode_ci) AS tournaments_created
    FROM admins a
    ORDER BY adminID ASC
")->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admins</title>

    <link href="./assets/style.css" rel="stylesheet" type="text/css">
    <link href="./assets/admins.css?v=<?= filemtime(__DIR__ . '/assets/admins.css') ?>" rel="stylesheet" type="text/css">

</head>

<body>

    <?php require_once 'admin_navbar.php'; ?>


    <main class="admins-page">

        <div class="page-title page-title-row">

            <div>
                <h1>Admins</h1>
                <p>Tournament Organizers manage only the tournaments they create.</p>
            </div>

            <a class="add-admin-btn" href="admin_add_admin.php">+ Add Admin</a>

        </div>


        <?php if (isset($_GET['changed'])): ?>
            <div class="success-message">
                Admin <strong><?= htmlspecialchars($_GET['changed']) ?></strong>
                <?= ($_GET['status'] ?? '') === 'inactive'
                    ? 'has been deactivated and can no longer log in.'
                    : 'has been activated and can log in again.' ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['problem'])): ?>
            <div class="error-message">
                <?= [
                    'self' => "You can't deactivate your own account.",
                    'last_platform' => "This is the last active Platform Admin - activate or add another Platform Admin first.",
                    'not_found' => 'That admin was not found.',
                ][$_GET['problem']] ?? 'The admin could not be changed.' ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['added'])): ?>
            <div class="success-message">
                Admin <strong><?= htmlspecialchars($_GET['added']) ?></strong> added.
                <?php if (isset($_GET['default_password'])): ?>
                    They can log in with user ID <strong><?= htmlspecialchars($_GET['added']) ?></strong>
                    and password <strong><?= htmlspecialchars($_GET['added']) ?></strong>,
                    then change the password on My Profile.
                <?php else: ?>
                    They can now log in with this ID and the password you set.
                <?php endif; ?>
            </div>
        <?php endif; ?>


        <div class="list-page">


            <!-- ==========================================
                 ADMIN LIST
            =========================================== -->

            <section class="card">

                <h2>All admins <span class="count"><?= count($admins) ?></span></h2>

                <div class="table-wrap">

                    <table class="admins-table">

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Country</th>
                                <th class="center">Tournaments</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($admins as $admin): ?>

                                <?php
                                $isPlatform = $admin['admin_role'] === 'platform';
                                $isActive = $admin['admin_active'] === 'active';
                                $isMe = $admin['adminID'] === $_SESSION['userid'];
                                ?>

                                <tr class="<?= $isActive ? '' : 'row-inactive' ?>">
                                    <td class="admin-id">
                                        <?= htmlspecialchars($admin['adminID']) ?>
                                        <?php if ($admin['adminID'] === $_SESSION['userid']): ?>
                                            <span class="you">you</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($admin['admin_name']) ?>
                                        <?php if (trim((string)$admin['admin_organization']) !== ''): ?>
                                            <small class="organization"><?= htmlspecialchars($admin['admin_organization']) ?></small>
                                        <?php endif; ?>
                                        <small><?= htmlspecialchars($admin['admin_email'] ?: $admin['admin_contact']) ?></small>
                                    </td>
                                    <td>
                                        <span class="role <?= $isPlatform ? 'role-platform' : 'role-organizer' ?>">
                                            <?= $isPlatform ? 'Platform Admin' : 'Tournament Organizer' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($admin['admin_country'] ?: '-') ?></td>
                                    <td class="center"><?= (int)$admin['tournaments_created'] ?></td>
                                    <td>
                                        <span class="status-pill <?= $isActive ? 'active' : 'inactive' ?>">
                                            <?= $isActive ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td class="row-actions">

                                        <a class="action-btn" href="admin_admin_details.php?id=<?= urlencode($admin['adminID']) ?>">View</a>

                                        <?php if (!$isMe): ?>
                                            <form method="POST" action="admin_admins.php"
                                                  onsubmit="return confirm('<?= $isActive
                                                      ? 'Deactivate ' . htmlspecialchars(addslashes($admin['admin_name']), ENT_QUOTES) . '? They will not be able to log in.'
                                                      : 'Activate ' . htmlspecialchars(addslashes($admin['admin_name']), ENT_QUOTES) . '? They will be able to log in again.' ?>');">
                                                <input type="hidden" name="action" value="toggle_active">
                                                <input type="hidden" name="adminID" value="<?= htmlspecialchars($admin['adminID']) ?>">
                                                <button type="submit" class="action-btn <?= $isActive ? 'danger' : 'positive' ?>">
                                                    <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </section>

        </div>


    </main>

</body>

</html>
