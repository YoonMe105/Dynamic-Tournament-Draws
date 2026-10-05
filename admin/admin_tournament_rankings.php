<?php

/*
|--------------------------------------------------------------------------
| tournament_rankings sync
|--------------------------------------------------------------------------
| tournament_rankings keeps one row per registration: the player's rankings
| as they were when they registered, plus the seed. The rankings are never
| changed here - only the seed and the category follow tournament_register
| (after seeding, or when the admin moves a player to another category).
*/

function syncTournamentRankings($conn, $tournamentID)
{
    $stmt = $conn->prepare("
        UPDATE tournament_rankings r
        JOIN tournament_register tr ON tr.registrationID = r.registrationID
        SET r.seed_number = tr.seed_number,
            r.category_registered = tr.category_registered
        WHERE tr.tournamentID = ?
    ");

    $stmt->bind_param("i", $tournamentID);
    $stmt->execute();
    $stmt->close();
}


// Same as above for one registration
function syncRegistrationRanking($conn, $registrationID)
{
    $stmt = $conn->prepare("
        UPDATE tournament_rankings r
        JOIN tournament_register tr ON tr.registrationID = r.registrationID
        SET r.seed_number = tr.seed_number,
            r.category_registered = tr.category_registered
        WHERE r.registrationID = ?
    ");

    $stmt->bind_param("i", $registrationID);
    $stmt->execute();
    $stmt->close();
}
