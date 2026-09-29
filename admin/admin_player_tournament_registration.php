<?php

require_once "../db.php";


/*
|--------------------------------------------------------------------------
| Get Registration ID
|--------------------------------------------------------------------------
*/

$registrationID = isset($_GET['id'])
    ? intval($_GET['id'])
    : 0;


if ($registrationID <= 0) {
    die("Invalid registration ID.");
}


/*
|--------------------------------------------------------------------------
| Get Registration Information
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        tr.registrationID,
        tr.tournamentID,
        tr.category_registered,
        tr.payment_status,
        tr.admin_remark,
        tr.endorsement,
        tr.amount_paid,
        tr.payment_date,
        tr.payment_proof,
        tr.player_attachments,

        p.playerID,
        p.player_first_name,
        p.player_last_name,
        p.player_dob,
        p.player_nationality,

        t.tournament_name,
        t.tournament_fee,
        t.tournament_deadline,
        t.tournament_age_cutoff

    FROM tournament_register tr

    INNER JOIN players p
        ON tr.playerID = p.playerID

    INNER JOIN tournament t
        ON tr.tournamentID = t.tournamentID

    WHERE tr.registrationID = ?
";


$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}


$stmt->bind_param(
    "i",
    $registrationID
);


$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    $stmt->close();

    die("Registration not found.");

}


$registration = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Player Full Name
|--------------------------------------------------------------------------
*/

$playerFullName =
    $registration['player_first_name']
    . ' '
    . $registration['player_last_name'];


/*
|--------------------------------------------------------------------------
| Registration Deadline
|--------------------------------------------------------------------------
*/

$deadline = "-";

if (!empty($registration['tournament_deadline'])) {

    $deadline = date(
        "d/m/Y (h:ia)",
        strtotime($registration['tournament_deadline'])
    );

}


/*
|--------------------------------------------------------------------------
| Registration Fee
|--------------------------------------------------------------------------
*/

$registrationFee = !empty($registration['tournament_fee'])
    ? $registration['tournament_fee']
    : "-";

// International tournaments ("Local: RM120.00, Foreign: USD 60.00"):
// Malaysian players pay the Local fee, foreign players the Foreign fee
require_once "../fees.php";

$playerFee = feeForPlayer($registration['tournament_fee'] ?? '', $registration['player_nationality'] ?? '');

if ($playerFee['label'] !== '') {
    $registrationFee = $playerFee['fee'] . " (" . strtolower($playerFee['label']) . " player)";
}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

$status = !empty($registration['endorsement'])
    ? $registration['endorsement']
    : "NOT ENDORSED";


/*
|--------------------------------------------------------------------------
| Payment Status
|--------------------------------------------------------------------------
*/

// Saved in capitals (PAID, NOT PAID); older rows may be in any case
$paymentStatus = !empty($registration['payment_status'])
    ? strtoupper(trim($registration['payment_status']))
    : "NOT PAID";


/*
|--------------------------------------------------------------------------
| Category
|--------------------------------------------------------------------------
*/

$category = !empty($registration['category_registered'])
    ? $registration['category_registered']
    : "";


/*
|--------------------------------------------------------------------------
| Wrong Age Category
|--------------------------------------------------------------------------
| null when the category fits the player's age on the cut-off date, or
| when the tournament has no cut-off date.
*/

require_once "admin_age_check.php";

$wrongCategory = wrongAgeCategory(
    $category,
    $registration['player_dob'],
    $registration['tournament_age_cutoff']
);

$cutoffText = !empty($registration['tournament_age_cutoff'])
    ? date("d/m/Y", strtotime($registration['tournament_age_cutoff']))
    : "";


/*
|--------------------------------------------------------------------------
| Category Options
|--------------------------------------------------------------------------
| Only the categories saved for this tournament (BU13, GU11, Men, ...).
| Long names like "Boys Under 13 Open Championship" are shown as codes,
| the same codes players register with. The current value is always kept.
*/

$categoryOptions = [];

$categoryStmt = $conn->prepare("
    SELECT category_name
    FROM tournament_category
    WHERE tournamentID = ?
");

$categoryStmt->bind_param("i", $registration['tournamentID']);
$categoryStmt->execute();

$categoryResult = $categoryStmt->get_result();

while ($categoryRow = $categoryResult->fetch_assoc()) {

    $categoryOption = trim($categoryRow['category_name']);

    if (preg_match('/\b(boys?|girls?)\b.*?\bunder\s*(\d+)/i', $categoryOption, $matches)) {
        $categoryOption = (strtolower($matches[1][0]) === 'b' ? 'B' : 'G')
            . 'U' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
    }

    if (!in_array($categoryOption, $categoryOptions, true)) {
        $categoryOptions[] = $categoryOption;
    }
}

$categoryStmt->close();

if ($category !== "" && !in_array($category, $categoryOptions, true)) {
    $categoryOptions[] = $category;
}


/*
|--------------------------------------------------------------------------
| Remarks
|--------------------------------------------------------------------------
*/

$remarks = !empty($registration['admin_remark'])
    ? $registration['admin_remark']
    : "";

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Tournament Registration
    </title>

    <link href="./assets/style.css" rel="stylesheet" type="text/css" />

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #eeeeee;
            color: #777;
        }

        .container {
            padding: 20px 28px 60px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .page-header h2 {
            margin: 0;
            color: #10175f;
            font-family: Georgia, serif;
            font-size: 30px;
        }

        .header-buttons {
            display: flex;
            gap: 20px;
        }

        .header-btn {
            display: inline-block;
            padding: 9px 25px;
            border-radius: 25px;
            border: 3px solid #10175f;
            background-color: white;
            color: #10175f;
            font-size: 15px;
            font-weight: bold;
            text-decoration: none;
        }

        .header-btn.primary {
            background-color: #10175f;
            color: white;
        }

        .registration-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(280px, 0.8fr);
            gap: 25px;
            width: 100%;
            align-items: start;
        }

        .card {
            width: 100%;
            min-width: 0;
            background-color: white;
            border-radius: 22px;
            padding: 24px 30px;
        }

        .left-card {
            min-width: 0;
        }

        .player-info {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            column-gap: 40px;
            row-gap: 20px;
            width: 100%;
        }

        .info-item {
            min-width: 0;
        }

        .info-item label {
            display: block;
            font-size: 17px;
            color: #999;
            margin-bottom: 4px;
        }

        .info-item .value {
            color: #555;
            font-size: 15px;
            font-weight: bold;
            overflow-wrap: break-word;
        }

        .status-select {
            width: 200px;
            max-width: 100%;
            height: 38px;
            padding: 0 10px;
            border: 2px solid #e3e3e3;
            border-radius: 12px;
            background-color: white;
            color: #666;
            font-size: 14px;
            font-weight: bold;
        }

        .attachments {
            width: 350px;
            max-width: 100%;
            height: 230px;
            margin-top: 25px;
            background-color: #eeeeee;
            border-radius: 18px;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 20px;
        }

        .no-attachment {
            color: #ed3022;
            font-size: 17px;
            font-weight: bold;
            line-height: 1.35;
        }

        .attachment-list {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 22px;
            text-align: left;
        }

        .attachment-item label {
            display: block;
            font-size: 15px;
            color: #999;
            margin-bottom: 4px;
        }

        .attachment-item a {
            color: #10175f;
            font-size: 16px;
            font-weight: bold;
        }

        .attachment-meta {
            margin-top: 4px;
            color: #555;
            font-size: 14px;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 18px;
            margin-top: -42px;
        }

        .delete-btn,
        .update-btn {
            border: none;
            padding: 9px 35px;
            border-radius: 23px;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        .delete-btn {
            background-color: #ed3022;
        }

        .update-btn {
            background-color: #10175f;
        }

        .right-card {
            margin-bottom: 20px;
        }

        .card-title {
            margin: 0 0 22px;
            text-align: center;
            color: #10175f;
            font-family: Georgia, serif;
            font-size: 22px;
        }

        .tournament-detail {
            margin-bottom: 28px;
        }

        .tournament-detail:last-child {
            margin-bottom: 0;
        }

        .tournament-detail label {
            display: block;
            margin-bottom: 4px;
            font-size: 17px;
            color: #999;
        }

        .tournament-detail .value {
            color: #555;
            font-size: 15px;
            font-weight: bold;
            line-height: 1.4;
            overflow-wrap: break-word;
        }

        .remarks {
            width: 100%;
        }

        .remarks textarea {
            display: block;
            width: 100%;
            height: 130px;
            resize: vertical;
            border: none;
            outline: none;
            background-color: #eeeeee;
            border-radius: 14px;
            padding: 13px;
            color: #555;
            font-size: 14px;
            font-weight: bold;
        }

        .wrong-category-message {
            background-color: #fdf0ee;
            color: #c0392b;
            border: 1px solid #f3b8b1;
            border-left: 5px solid #c0392b;
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: bold;
            line-height: 1.5;
        }

        .success-message {
            background-color: #e8f7e8;
            color: #287a28;
            border: 1px solid #a8d8a8;
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        @media (max-width: 1000px) {

            .registration-layout {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 700px) {

            .main-content {
                padding: 20px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-buttons {
                width: 100%;
            }

            .player-info {
                grid-template-columns: 1fr;
            }

            .attachments {
                width: 100%;
            }

            .action-buttons {
                margin-top: 25px;
                justify-content: flex-start;
            }

        }

    </style>

</head>

<body>


<?php include "admin_navbar.php"; ?>


<main class="container">


    <!-- =====================================================
         SUCCESS MESSAGE
    ====================================================== -->

    <?php if (
        isset($_GET['updated']) &&
        $_GET['updated'] == '1'
    ): ?>

        <div class="success-message">

            Registration updated successfully.

        </div>

    <?php endif; ?>


    <!-- =====================================================
         WRONG AGE CATEGORY
    ====================================================== -->

    <?php if ($wrongCategory !== null): ?>

        <?php
        // Youngest category in this tournament that fits the player's age
        $wrongCategoryText = wrongCategoryMessage(
            $playerFullName,
            $category,
            correctAgeCategory($category, $wrongCategory['age'], $categoryOptions),
            $wrongCategory['age']
        );
        ?>

        <div class="wrong-category-message" role="alert">

            &#9888; <?php echo htmlspecialchars($wrongCategoryText); ?>

        </div>

        <script>
            window.addEventListener('load', function () {
                alert(<?php echo json_encode(
                    "Wrong category!\n\n" . $wrongCategoryText
                    . "\n\nAge cut-off date: " . $cutoffText
                ); ?>);
            });
        </script>

    <?php endif; ?>


    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <div class="page-header">

        <h2>
            Tournament Registration
        </h2>


        <div class="header-buttons">

            <!-- View Player Profile -->

            <a
                href="admin_player_profile.php?id=<?php echo urlencode($registration['playerID']); ?>&tournamentID=<?php echo urlencode($registration['tournamentID']); ?>"
                class="header-btn">

                View Profile

            </a>

        </div>

    </div>


    <!-- =====================================================
         MAIN LAYOUT
    ====================================================== -->

    <div class="registration-layout">


        <!-- =================================================
             LEFT CARD
        ================================================== -->

        <div class="card left-card">


            <!-- PLAYER INFORMATION -->

            <div class="player-info">


                <!-- PLAYER ID -->

                <div class="info-item">

                    <label>
                        Player ID
                    </label>

                    <div class="value">

                        <?php
                        echo htmlspecialchars(
                            $registration['playerID']
                        );
                        ?>

                    </div>

                </div>


                <!-- REGISTRATION DEADLINE -->

                <div class="info-item">

                    <label>
                        Registration Deadline
                    </label>

                    <div class="value">

                        <?php
                        echo htmlspecialchars(
                            $deadline
                        );
                        ?>

                    </div>

                </div>


                <!-- PLAYER NAME -->

                <div class="info-item">

                    <label>
                        Player Name
                    </label>

                    <div class="value">

                        <?php
                        echo htmlspecialchars(
                            $playerFullName
                        );
                        ?>

                    </div>

                </div>


                <!-- REGISTRATION FEE -->

                <div class="info-item">

                    <label>
                        Registration Fee
                    </label>

                    <div class="value">

                        <?php
                        echo htmlspecialchars(
                            $registrationFee
                        );
                        ?>

                    </div>

                </div>


                <!-- STATUS -->

                <div class="info-item">

                    <label>
                        Status
                    </label>

                    <select
                        class="status-select"
                        id="status">

                        <option
                            value="NOT ENDORSED"
                            <?php echo ($status === "NOT ENDORSED") ? "selected" : ""; ?>>

                            Not Endorsed

                        </option>

                        <option
                            value="ENDORSED"
                            <?php echo ($status === "ENDORSED") ? "selected" : ""; ?>>

                            Endorsed

                        </option>

                        <option
                            value="Banned"
                            <?php echo ($status === "Banned") ? "selected" : ""; ?>>

                            Banned

                        </option>

                        <option
                            value="Withdraw"
                            <?php echo ($status === "Withdraw") ? "selected" : ""; ?>>

                            Withdraw

                        </option>

                        <option
                            value="Walkover"
                            <?php echo ($status === "Walkover") ? "selected" : ""; ?>>

                            Walkover

                        </option>

                    </select>

                </div>


                <!-- PAYMENT STATUS -->

                <div class="info-item">

                    <label>
                        Payment Status
                    </label>

                    <select
                        class="status-select"
                        id="payment_status">

                        <option
                            value="NOT PAID"
                            <?php echo ($paymentStatus === "NOT PAID") ? "selected" : ""; ?>>

                            Not Paid

                        </option>

                        <option
                            value="PENDING"
                            <?php echo ($paymentStatus === "PENDING") ? "selected" : ""; ?>>

                            Pending

                        </option>

                        <option
                            value="PAID"
                            <?php echo ($paymentStatus === "PAID") ? "selected" : ""; ?>>

                            Paid

                        </option>

                        <option
                            value="REFUNDED"
                            <?php echo ($paymentStatus === "REFUNDED") ? "selected" : ""; ?>>

                            Refunded

                        </option>

                    </select>

                </div>


            </div>


            <!-- =================================================
                 ATTACHMENTS
            ================================================== -->

            <div class="attachments">

                <?php if (empty($registration['payment_proof']) && empty($registration['player_attachments'])): ?>

                    <div class="no-attachment">

                        There are no attachments
                        given by player.

                    </div>

                <?php else: ?>

                    <div class="attachment-list">

                        <?php if (!empty($registration['payment_proof'])): ?>

                            <div class="attachment-item">

                                <label>Payment Receipt</label>

                                <a href="../uploads/payments/<?php echo rawurlencode($registration['payment_proof']); ?>" target="_blank">
                                    View Receipt
                                </a>

                                <div class="attachment-meta">
                                    <?php echo htmlspecialchars($registration['amount_paid'] ?? '-'); ?>
                                    <?php if (!empty($registration['payment_date'])): ?>
                                        &middot;
                                        <?php echo date("d/m/Y (h:ia)", strtotime($registration['payment_date'])); ?>
                                    <?php endif; ?>
                                </div>

                            </div>

                        <?php endif; ?>

                        <?php if (!empty($registration['player_attachments'])): ?>

                            <div class="attachment-item">

                                <label>Player Attachment</label>

                                <a href="../uploads/registrations/<?php echo rawurlencode($registration['player_attachments']); ?>" target="_blank">
                                    View Attachment
                                </a>

                            </div>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 ACTION BUTTONS
            ================================================== -->

            <div class="action-buttons">


                <!-- DELETE -->

                <button
                    type="button"
                    class="delete-btn"
                    onclick="deleteRegistration()">

                    Delete

                </button>


                <!-- UPDATE -->

                <button
                    type="button"
                    class="update-btn"
                    onclick="updateRegistration()">

                    Update

                </button>


            </div>


        </div>


        <!-- =================================================
             RIGHT COLUMN
        ================================================= -->

        <div>


            <!-- =================================================
                 TOURNAMENT DETAILS
            ================================================== -->

            <div class="card right-card">


                <h3 class="card-title">

                    Tournament Details

                </h3>


                <!-- TOURNAMENT NAME -->

                <div class="tournament-detail">

                    <label>
                        Tournament Name
                    </label>

                    <div class="value">

                        <?php

                        echo nl2br(
                            htmlspecialchars(
                                $registration['tournament_name']
                            )
                        );

                        ?>

                    </div>

                </div>


                <!-- CATEGORY -->

                <div class="tournament-detail">

                    <label>
                        Category Registered
                    </label>

                    <select
                        class="status-select"
                        id="category_registered">

                        <?php foreach ($categoryOptions as $categoryOption): ?>

                            <option
                                value="<?php echo htmlspecialchars($categoryOption); ?>"
                                <?php echo ($category === $categoryOption) ? "selected" : ""; ?>>

                                <?php echo htmlspecialchars($categoryOption); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


            </div>


            <!-- =================================================
                 REMARKS
            ================================================== -->

            <div class="card remarks">


                <h3 class="card-title">

                    Remarks

                </h3>


                <textarea
                    id="remarks"
                    placeholder="Enter remarks..."><?php echo htmlspecialchars($remarks); ?></textarea>


            </div>


        </div>


    </div>


</main>


<script>


/*
|--------------------------------------------------------------------------
| UPDATE REGISTRATION
|--------------------------------------------------------------------------
*/

function updateRegistration() {


    // Get status

    const status =
        document.getElementById("status").value;


    // Get payment status

    const paymentStatus =
        document.getElementById("payment_status").value;


    // Get category

    const category =
        document.getElementById("category_registered").value;


    // Get remarks

    const remarks =
        document.getElementById("remarks").value;


    /*
    |--------------------------------------------------------------------------
    | Create POST form
    |--------------------------------------------------------------------------
    */

    const form =
        document.createElement("form");


    form.method = "POST";

    form.action =
        "admin_update_registration.php";


    /*
    |--------------------------------------------------------------------------
    | Registration ID
    |--------------------------------------------------------------------------
    */

    const registrationID =
        document.createElement("input");

    registrationID.type = "hidden";

    registrationID.name =
        "registrationID";

    registrationID.value =
        "<?php echo $registrationID; ?>";


    form.appendChild(
        registrationID
    );


    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    const statusInput =
        document.createElement("input");

    statusInput.type = "hidden";

    statusInput.name =
        "status";

    statusInput.value =
        status;


    form.appendChild(
        statusInput
    );


    /*
    |--------------------------------------------------------------------------
    | Payment Status
    |--------------------------------------------------------------------------
    */

    const paymentInput =
        document.createElement("input");

    paymentInput.type = "hidden";

    paymentInput.name =
        "payment_status";

    paymentInput.value =
        paymentStatus;


    form.appendChild(
        paymentInput
    );


    /*
    |--------------------------------------------------------------------------
    | Category
    |--------------------------------------------------------------------------
    */

    const categoryInput =
        document.createElement("input");

    categoryInput.type = "hidden";

    categoryInput.name =
        "category_registered";

    categoryInput.value =
        category;


    form.appendChild(
        categoryInput
    );


    /*
    |--------------------------------------------------------------------------
    | Remarks
    |--------------------------------------------------------------------------
    */

    const remarksInput =
        document.createElement("input");

    remarksInput.type = "hidden";

    remarksInput.name =
        "remarks";

    remarksInput.value =
        remarks;


    form.appendChild(
        remarksInput
    );


    /*
    |--------------------------------------------------------------------------
    | Submit
    |--------------------------------------------------------------------------
    */

    document.body.appendChild(
        form
    );

    form.submit();

}


/*
|--------------------------------------------------------------------------
| DELETE REGISTRATION
|--------------------------------------------------------------------------
*/

function deleteRegistration() {


    const confirmed = confirm(
        "Are you sure you want to delete this registration?"
    );


    if (!confirmed) {

        return;

    }


    window.location.href =
        "admin_delete_registration.php?id=<?php echo $registrationID; ?>";

}


</script>


</body>

</html>