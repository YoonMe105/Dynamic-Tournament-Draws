<?php

/*
|--------------------------------------------------------------------------
| Admin IDs
|--------------------------------------------------------------------------
| Shared by admin_add_admin.php (Platform Admin adds an admin) and
| ../organizer_register.php (an organizer applies on their own).
*/

// Next free ID: AD0001 -> AD0002
function nextAdminID($conn)
{
    $row = $conn->query("
        SELECT MAX(CAST(SUBSTRING(adminID, 3) AS UNSIGNED)) AS last_number
        FROM admins
        WHERE adminID REGEXP '^AD[0-9]+$'
    ")->fetch_assoc();

    return 'AD' . str_pad((string)((int)$row['last_number'] + 1), 4, '0', STR_PAD_LEFT);
}
