<?php
session_start();

require './db.php';

$error = "";
$login_success = false;
$redirect_url = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $userid = trim($_POST["userid"]);
    $password = $_POST["password"];

    /*
    ==========================================
    CHECK ADMIN
    ==========================================
    */

    $admin_query = "SELECT adminid, admin_name, admin_password
                    FROM admins
                    WHERE adminid = ?";

    $stmt = $conn->prepare($admin_query);

    if (!$stmt) {
        die("Database query failed: " . $conn->error);
    }

    $stmt->bind_param("s", $userid);
    $stmt->execute();

    $admin_result = $stmt->get_result();

    if ($admin_result->num_rows === 1) {

        $admin = $admin_result->fetch_assoc();

        if ($password === $admin["admin_password"]) {

            $_SESSION["userid"] = $admin["adminid"];
            $_SESSION["role"] = "admin";
            $_SESSION["admin_name"] = $admin["admin_name"];

            $redirect_url = "admin/admin_index.php";
            $login_success = true;

        } else {

            $error = "Incorrect password.";

        }

    } else {

        /*
        ==========================================
        CHECK PLAYER
        ==========================================
        */

        $player_query = "SELECT playerid, player_first_name, player_last_name, player_password
                         FROM players
                         WHERE playerid = ?";

        $stmt = $conn->prepare($player_query);

        if (!$stmt) {
            die("Database query failed: " . $conn->error);
        }

        $stmt->bind_param("s", $userid);
        $stmt->execute();

        $player_result = $stmt->get_result();

        if ($player_result->num_rows === 1) {

            $player = $player_result->fetch_assoc();

            if ($password === $player["player_password"]) {

                $_SESSION["userid"] = $player["playerid"];
                $_SESSION["role"] = "player";
                $_SESSION["playerid"] = $player["playerid"];
                $_SESSION["firstname"] = $player["player_first_name"];
                $_SESSION["lastname"] = $player["player_last_name"];

                $redirect_url = "player/player_index.php";
                $login_success = true;

            } else {

                $error = "Incorrect password.";

            }

        } else {

            $error = "User ID not found.";

        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In | Tournament Management System</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <link href="./assets/css/style.css" rel="stylesheet" type="text/css" />
</head>
<body>

    <?php if ($login_success): ?>

        <div class="loading-screen">
            <div class="loader"></div>
            <h2>Logging you in...</h2>
            <p>Please wait a moment</p>
        </div>

        <script>
            setTimeout(function () {

                window.location.href =
                    <?= json_encode($redirect_url) ?>;

            }, 1500);
        </script>

    <?php else: ?>

        <!-- =========================
            LOGIN PAGE
        ========================== -->

        <div class="login">

            <div class="login_wrapper">
                <!-- <img src="images/logo4.png" alt="Tournament Logo" > -->
                <h3>LOG IN</h3>

                <!-- ERROR -->
                <?php if ($error !== ""): ?>

                    <div class="error-message">

                        <?= htmlspecialchars($error) ?>

                    </div>

                <?php endif; ?>


                <!-- LOGIN FORM -->
                <form action="login.php" method="POST" >
                    <p> USER ID <span>*</span> </p>

                    <input type="text" name="userid" placeholder="Enter your User ID" required >


                    <!-- PASSWORD -->

                    <p>PASSWORD <span>*</span> </p>

                    <div class="password-container">
                        <input type="password" name="password" id="password" placeholder="Enter your password" minlength="6" required >
                        <i class="fas fa-eye-slash" id="show-password" ></i>
                    </div>
                    <br>

                    <!-- LOGIN BUTTON -->

                    <button class="btn" type="submit" name="sub">
                        Log In
                    </button>
                </form>


                <!-- FORGOT PASSWORD -->

                <a href="password_reset.php" target="_blank" class="forgot-password" >
                    Forgot Password?
                </a>


                <!-- FOOTER -->
                <div class="login-footer">
                    Dynamic Tournament Management System
                </div>
            </div>
        </div>


    <?php endif; ?>


    <script>

        /* =========================
        SHOW / HIDE PASSWORD
        ========================= */

        const showPassword =
            document.querySelector("#show-password");

        const passwordField =
            document.querySelector("#password");


        if (showPassword && passwordField) {

            showPassword.addEventListener(
                "click",
                function () {

                    this.classList.toggle(
                        "fa-eye-slash"
                    );

                    this.classList.toggle(
                        "fa-eye"
                    );


                    const type =
                        passwordField.getAttribute(
                            "type"
                        ) === "password"
                            ? "text"
                            : "password";


                    passwordField.setAttribute(
                        "type",
                        type
                    );

                }
            );

        }

    </script>


</body>

</html>