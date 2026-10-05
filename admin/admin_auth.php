<?php

/*
|--------------------------------------------------------------------------
| Admin access
|--------------------------------------------------------------------------
| Every admin page starts with:   require_once 'admin_auth.php';
| then, depending on the page:    requirePlatformAdmin();
|                                 requireTournamentAccess($tournamentID);
|
| admins.admin_role says what an admin may do:
|   'platform'  = Platform Admin: everything, including the dashboard and
|                 adding admins
|   'organizer' = Tournament Organizer: creates tournaments and can do
|                 everything on the tournaments they created (registrations,
|                 seeding, draws, rankings, editing) - nothing on anyone else's
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['role'], $_SESSION['userid']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}


/*
| Read the admin fresh on every page, so changes to their responsibilities
| apply straight away.
*/

$authStmt = $conn->prepare("SELECT adminID, admin_name, admin_country, admin_profile, admin_role FROM admins WHERE adminID = ?");
$authStmt->bind_param("s", $_SESSION['userid']);
$authStmt->execute();

$currentAdmin = $authStmt->get_result()->fetch_assoc();

$authStmt->close();

if (!$currentAdmin) {
    session_unset();
    session_destroy();
    header('Location: ../login.php');
    exit;
}

$currentAdminRole = $currentAdmin['admin_role'];


function isPlatformAdmin()
{
    global $currentAdminRole;

    return $currentAdminRole === 'platform';
}


// Platform Admins see every tournament; Tournament Organizers only the ones they created
function canAccessTournament($creatorID)
{
    return isPlatformAdmin() || (string)$creatorID === (string)$_SESSION['userid'];
}


/*
| Tournament pages: Tournament Organizers can do everything on a tournament they
| created (registrations, seeding, draws, rankings, editing...), nothing on
| anyone else's. Platform Admins pass every check.
*/

function requireTournamentAccess($tournamentID)
{
    global $conn;

    if (isPlatformAdmin()) {
        return;
    }

    $stmt = $conn->prepare("SELECT creatorID FROM tournament WHERE tournamentID = ?");
    $tournamentID = (int)$tournamentID;
    $stmt->bind_param("i", $tournamentID);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$row || !canAccessTournament($row['creatorID'])) {
        denyAdminAccess("You can only manage tournaments you created.");
    }
}


// Same check, starting from a registration
function requireRegistrationAccess($registrationID)
{
    global $conn;

    if (isPlatformAdmin()) {
        return;
    }

    $stmt = $conn->prepare("SELECT tournamentID FROM tournament_register WHERE registrationID = ?");
    $registrationID = (int)$registrationID;
    $stmt->bind_param("i", $registrationID);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$row) {
        denyAdminAccess("You can only manage tournaments you created.");
    }

    requireTournamentAccess($row['tournamentID']);
}


// A player's profile: Tournament Organizers only for players registered in one of their tournaments
function requirePlayerAccess($playerID, $tournamentID)
{
    global $conn;

    if (isPlatformAdmin()) {
        return;
    }

    requireTournamentAccess($tournamentID);

    $stmt = $conn->prepare("SELECT 1 FROM tournament_register WHERE playerID = ? AND tournamentID = ? LIMIT 1");
    $tournamentID = (int)$tournamentID;
    $stmt->bind_param("si", $playerID, $tournamentID);
    $stmt->execute();

    $registered = $stmt->get_result()->num_rows > 0;

    $stmt->close();

    if (!$registered) {
        denyAdminAccess("You can only open players registered in your tournaments.");
    }
}


function requirePlatformAdmin()
{
    if (!isPlatformAdmin()) {
        denyAdminAccess();
    }
}


function denyAdminAccess($message = "You don't have permission to open this page.")
{
    http_response_code(403);

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>No access</title>
        <style>
            body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
                   background: #f5f7fb; font-family: Arial, Helvetica, sans-serif; color: #333; }
            .box { max-width: 420px; padding: 32px; background: #fff; border: 1px solid #e5e7ef;
                   border-radius: 14px; text-align: center; }
            h1 { margin: 0 0 10px; font-size: 22px; color: #993D86; }
            p { margin: 0 0 20px; color: #666; }
            a { display: inline-block; padding: 10px 18px; border-radius: 8px; background: #993D86;
                color: #fff; text-decoration: none; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>No access</h1>
            <p><?= htmlspecialchars($message) ?></p>
            <a href="admin_index.php">Back to Tournaments</a>
        </div>
    </body>
    </html>
    <?php

    exit;
}
