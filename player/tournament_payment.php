<?php
require_once "player_functions.php";

$registrationID = isset($_GET['registrationID']) ? intval($_GET['registrationID']) : 0;


/*
|--------------------------------------------------------------------------
| Get Registration (must belong to the logged-in player)
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM tournament_register
    WHERE registrationID = ?
      AND playerID = ?
");
$stmt->bind_param("is", $registrationID, $playerID);
$stmt->execute();

$registration = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$registration) {
    die("Registration not found.");
}

$tournamentID = (int) $registration['tournamentID'];

$tournament = getTournament($conn, $tournamentID);

if (!$tournament) {
    die("Tournament not found.");
}

if (!canPay($registration)) {
    header("Location: tournament_details.php?tournamentID=" . $tournamentID);
    exit();
}


/*
|--------------------------------------------------------------------------
| Form Submission
|--------------------------------------------------------------------------
*/

$error_message = '';

$form = [
    'amount_paid' => $registration['amount_paid'] ?? '',
    'payment_date' => date('Y-m-d\TH:i')
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $form['amount_paid'] = trim($_POST['amount_paid'] ?? '');
    $form['payment_date'] = trim($_POST['payment_date'] ?? '');

    $paymentTime = strtotime($form['payment_date']);

    if ($form['amount_paid'] === '') {

        $error_message = 'Please enter the amount you paid.';

    } elseif ($paymentTime === false) {

        $error_message = 'Please enter a valid payment date.';

    } elseif ($paymentTime > time() + 60) {

        $error_message = 'Payment date cannot be in the future.';

    } elseif (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] === UPLOAD_ERR_NO_FILE) {

        $error_message = 'Please upload your payment receipt.';
    }


    /*
    | Upload Receipt
    */

    $paymentProof = null;

    if ($error_message === '') {
        [$paymentProof, $error_message] = uploadFile('payment_proof', 'payments', 'payment');
    }


    /*
    | Update Registration
    */

    if ($error_message === '') {

        $paymentDate = date('Y-m-d H:i:s', $paymentTime);
        $paymentStatus = 'PENDING';

        $stmt = $conn->prepare("
            UPDATE tournament_register
            SET amount_paid = ?,
                payment_date = ?,
                payment_proof = ?,
                payment_status = ?
            WHERE registrationID = ?
              AND playerID = ?
        ");

        $stmt->bind_param(
            "ssssis",
            $form['amount_paid'],
            $paymentDate,
            $paymentProof,
            $paymentStatus,
            $registrationID,
            $playerID
        );

        if ($stmt->execute()) {

            $stmt->close();

            // Replace the previous receipt
            if (!empty($registration['payment_proof'])) {
                @unlink('../uploads/payments/' . basename($registration['payment_proof']));
            }

            header("Location: tournament_details.php?tournamentID=" . $tournamentID . "&paid=1");
            exit();
        }

        $error_message = 'Something went wrong. Please try again.';

        $stmt->close();

        @unlink('../uploads/payments/' . $paymentProof);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Payment | <?= htmlspecialchars($tournament['tournament_name']) ?></title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />

    <!-- Navbar CSS -->
    <link rel="stylesheet" href="./assets/css/navbar.css?v=<?= filemtime(__DIR__ . '/assets/css/navbar.css') ?>" type="text/css" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="./assets/css/tournament_details.css?v=<?= filemtime(__DIR__ . '/assets/css/tournament_details.css') ?>" type="text/css" />
</head>


<body>


    <?php include "player_navbar.php"; ?>


    <main class="container narrow">

        <a href="tournament_details.php?tournamentID=<?= $tournamentID ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Tournament
        </a>


        <!-- STEPS -->

        <div class="steps">
            <div class="step done"><span><i class="fa-solid fa-check"></i></span> Registration Details</div>
            <div class="step-line"></div>
            <div class="step active"><span>2</span> Pay Entry Fee</div>
        </div>


        <?php if (isset($_GET['registered']) && $_GET['registered'] == '1'): ?>

            <div class="message success">
                <i class="fa-solid fa-circle-check"></i>
                Registration saved. Please pay the entry fee to complete your registration.
            </div>

        <?php endif; ?>


        <section class="card">

            <h1 class="page-title"><?= htmlspecialchars($tournament['tournament_name']) ?></h1>


            <!-- PAYMENT DETAILS -->

            <div class="registration-summary tournament-summary">

                <div class="summary-row">
                    <span>Category</span>
                    <strong><?= htmlspecialchars($registration['category_registered']) ?></strong>
                </div>

                <div class="summary-row">
                    <span>Payment Status</span>
                    <strong class="status <?= statusClass($registration['payment_status']) ?>">
                        <?= htmlspecialchars($registration['payment_status'] ?: 'Not Paid') ?>
                    </strong>
                </div>

            </div>


            <div class="fee-box">

                <?php
                $allFees = tournamentFees($tournament);
                $myFee = playerFee($tournament, getPlayer($conn, $playerID) ?? []);
                ?>

                <div class="fee-label">
                    Your Entry Fee<?= count($allFees) > 1
                        ? ' &ndash; ' . htmlspecialchars($myFee['label'])
                        : ($myFee['label'] !== '' ? ' (' . htmlspecialchars($myFee['label']) . ')' : '') ?>
                    &amp; Payment Instructions
                </div>

                <div class="fee-value">
                    <?= nl2br(htmlspecialchars($myFee['fee'] ?: '-')) ?>
                </div>

                <?php if (count($allFees) > 1): ?>

                    <p class="hint">
                        <?= $myFee['currency'] === 'USD' ? 'Malaysian players (RM)' : 'Foreign players (USD)' ?>:
                        <?= htmlspecialchars($allFees[$myFee['currency'] === 'USD' ? 'RM' : 'USD']) ?>
                    </p>

                <?php endif; ?>

                <?php $feeDetails = splitFee($tournament['tournament_fee'] ?? '')['details']; ?>

                <?php if ($feeDetails !== null): ?>

                    <p class="hint">
                        <strong>Payment details:</strong>
                        <?= nl2br(htmlspecialchars($feeDetails)) ?>
                    </p>

                <?php endif; ?>

                <p class="hint">
                    Pay the entry fee as instructed above, then upload your receipt below.
                    The organiser will verify it and mark your registration as paid.
                </p>

            </div>


            <?php if (!empty($registration['payment_proof'])): ?>

                <div class="message info">
                    <i class="fa-solid fa-circle-info"></i>
                    You already uploaded a receipt. Uploading a new one will replace it.
                </div>

            <?php endif; ?>


            <?php if ($error_message !== ''): ?>

                <div class="message error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>

            <?php endif; ?>


            <!-- PAYMENT FORM -->

            <form
                method="POST"
                action="tournament_payment.php?registrationID=<?= $registrationID ?>"
                enctype="multipart/form-data"
                id="paymentForm"
            >

                <div class="form-group">

                    <label for="amount_paid">Amount Paid <span class="required">*</span></label>

                    <input
                        type="text"
                        id="amount_paid"
                        name="amount_paid"
                        maxlength="255"
                        value="<?= htmlspecialchars($form['amount_paid']) ?>"
                        placeholder="e.g. RM 120.00"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="payment_date">Payment Date <span class="required">*</span></label>

                    <input
                        type="datetime-local"
                        id="payment_date"
                        name="payment_date"
                        value="<?= htmlspecialchars($form['payment_date']) ?>"
                        max="<?= date('Y-m-d\TH:i') ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="payment_proof">Payment Receipt <span class="required">*</span></label>

                    <input
                        type="file"
                        id="payment_proof"
                        name="payment_proof"
                        accept=".pdf,.jpg,.jpeg,.png,.webp"
                        required
                    >

                    <small class="hint">PDF, JPG, PNG or WEBP. Max 5MB.</small>

                </div>


                <button type="submit" class="primary-btn" id="submitBtn">
                    Submit Payment
                </button>

                <a href="tournament_details.php?tournamentID=<?= $tournamentID ?>" class="secondary-btn">
                    Pay Later
                </a>

            </form>

        </section>

    </main>


    <script>
        document.getElementById("paymentForm").addEventListener("submit", function () {

            const button = document.getElementById("submitBtn");

            button.disabled = true;
            button.textContent = "Submitting...";

        });
    </script>


</body>

</html>
