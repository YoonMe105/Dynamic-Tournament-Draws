<?php
require_once "../db.php";

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $firstname = trim($_POST["firstname"] ?? "");
    $lastname = trim($_POST["lastname"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $dob = $_POST["dob"] ?? "";
    $nationality = $_POST["nationality"] ?? "";
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if ($password !== $confirm_password) {

        $message = "Passwords do not match.";
        $message_type = "error";

    } else {

        // Check if email already exists
        $check_query = "SELECT playerID FROM players WHERE player_email = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();

        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {

            $message = "This email is already registered.";
            $message_type = "error";

        } else {

            $id_query = "SELECT playerID FROM players ORDER BY CAST(SUBSTRING(playerID, 2) AS UNSIGNED) DESC LIMIT 1";

            $id_result = $conn->query($id_query);

            if ($id_result && $id_result->num_rows > 0) {

                $last_player = $id_result->fetch_assoc();
                $last_id = $last_player['playerID'];

                $last_number = (int) substr($last_id, 1);
                $next_number = $last_number + 1;

            } else {

                $next_number = 1;
            }

            $player_id = 'P' . str_pad($next_number, 4, '0', STR_PAD_LEFT);

            $fullname = $firstname . ' ' . $lastname;

            $sql = "INSERT INTO players (playerID, player_full_name, player_first_name, player_last_name, player_email, player_dob, player_nationality, player_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            if ($stmt) {

                $stmt->bind_param("ssssssss", $player_id, $fullname, $firstname, $lastname, $email, $dob, $nationality, $password);
                
                if ($stmt->execute()) {

                    $message = "Registration successful! Your Player ID is " . $player_id;
                    $message_type = "success";

                } else {

                    $message = "Something went wrong. Please try again.";
                    $message_type = "error";
                }

                $stmt->close();

            } else {

                $message = "Database error: " . $conn->error;
                $message_type = "error";
            }
        }

        $check_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In</title>
    <link href="./assets/css/signin.css" rel="stylesheet" type="text/css">
</head>
<body>

    <div class="signin-container">
        <div class="signin-card">

            <h2>Sign In</h2>
            <p class="subtitle">Create your account to continue</p>

            <?php if ($message != ""): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form action="" method="post" id="signinForm">

                <div class="form-group">
                    <label for="firstname">First Name</label>
                    <input type="text" id="firstname" name="firstname" placeholder="Enter your first name" required>
                </div>

                <div class="form-group">
                    <label for="lastname">Last Name</label>
                    <input type="text" id="lastname" name="lastname" placeholder="Enter your last name" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required>
                </div>

                <div class="form-group">
                    <label for="dob">Date of Birth</label>
                    <input type="date" id="dob" name="dob" required>
                </div>

                <div class="form-group">
                    <label for="nationality">Nationality</label>
                    <select id="nationality" name="nationality" required>
                        <option value="">Select nationality</option>
                        <option value="Afghan">Afghan</option>
                        <option value="Albanian">Albanian</option>
                        <option value="Algerian">Algerian</option>
                        <option value="American">American</option>
                        <option value="Australian">Australian</option>
                        <option value="Bangladeshi">Bangladeshi</option>
                        <option value="British">British</option>
                        <option value="Bruneian">Bruneian</option>
                        <option value="Cambodian">Cambodian</option>
                        <option value="Canadian">Canadian</option>
                        <option value="Chinese">Chinese</option>
                        <option value="Filipino">Filipino</option>
                        <option value="French">French</option>
                        <option value="German">German</option>
                        <option value="Indian">Indian</option>
                        <option value="Indonesian">Indonesian</option>
                        <option value="Japanese">Japanese</option>
                        <option value="Korean">Korean</option>
                        <option value="Malaysian">Malaysian</option>
                        <option value="Myanmar">Myanmar</option>
                        <option value="Nepalese">Nepalese</option>
                        <option value="New Zealander">New Zealander</option>
                        <option value="Pakistani">Pakistani</option>
                        <option value="Singaporean">Singaporean</option>
                        <option value="Sri Lankan">Sri Lankan</option>
                        <option value="Thai">Thai</option>
                        <option value="Vietnamese">Vietnamese</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <button type="button" class="eye-btn" id="togglePassword" aria-label="Show password">👁</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                        <button type="button" class="eye-btn" id="toggleConfirmPassword" aria-label="Show confirm password">👁</button>
                    </div>
                    <small id="passwordMessage"></small>
                </div>

                <button type="submit" class="signin-btn">Sign In</button>

            </form>
        </div>
    </div>

    <script src="./assets/js/signin.js"></script>

</body>
</html>