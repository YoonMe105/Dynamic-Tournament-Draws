<?php


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
