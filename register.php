<?php
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/config/functions.php";

$error = "";
$redirect = safe_redirect($_GET["redirect"] ?? $_POST["redirect"] ?? "index.php");

// If already logged in, redirect away
if (is_logged_in()) {
    header("Location: " . (is_admin() ? "admin/dashboard.php" : $redirect));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm = $_POST["confirm_password"] ?? "";

    if (!verify_csrf($_POST["csrf_token"] ?? null)) {
        $error = "Your session expired. Please refresh and try again.";
    } elseif ($username === "" || $email === "" || $password === "" || $confirm === "") {
        $error = "Please fill in all fields.";
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = "Username must be between 3 and 50 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Check uniqueness
        $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ? LIMIT 1");
        $checkStmt->bind_param("ss", $username, $email);
        $checkStmt->execute();
        if ($checkStmt->get_result()->fetch_assoc()) {
            $error = "That username or email is already registered.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $insStmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'customer')");
            $insStmt->bind_param("sss", $username, $email, $hashed);
            if ($insStmt->execute()) {
                $newId = (int)$insStmt->insert_id;
                session_regenerate_id(true);
                $_SESSION["user_id"] = $newId;
                $_SESSION["username"] = $username;
                $_SESSION["email"] = $email;
                $_SESSION["role"] = "customer";
                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

                flash("success", "Welcome to HM Café, {$username}!");
                header("Location: " . $redirect);
                exit;
            } else {
                $error = "Registration failed. Please try again.";
            }
            $insStmt->close();
        }
        $checkStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HM Café | Sign Up &amp; Register</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="innovation.css">
</head>
<body class="login-page">
<div class="login-container">
    <img src="assets/logo.png" class="login-logo" alt="HM Café Logo">
    <h1>Create <span>Account</span></h1>
    <p class="login-subtitle">JOIN OUR COFFEE COMMUNITY</p>

    <?php if ($error): ?>
        <div class="login-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="register.php" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

        <div class="login-group">
            <input type="text" id="username" name="username" placeholder="Choose a username" value="<?= e($_POST["username"] ?? "") ?>" required autocomplete="username">
        </div>

        <div class="login-group">
            <input type="email" id="email" name="email" placeholder="Email address (you@example.com)" value="<?= e($_POST["email"] ?? "") ?>" required autocomplete="email">
        </div>

        <div class="login-group">
            <div class="password-wrap">
                <input type="password" id="password" name="password" placeholder="Password (min 6 characters)" required autocomplete="new-password">
                <button type="button" class="toggle-pw" onclick="var i=document.getElementById('password');i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'👁':'🙈';" title="Show/hide password">👁</button>
            </div>
        </div>

        <div class="login-group">
            <div class="password-wrap">
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" required autocomplete="new-password">
                <button type="button" class="toggle-pw" onclick="var i=document.getElementById('confirm_password');i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'👁':'🙈';" title="Show/hide password">👁</button>
            </div>
        </div>

        <button type="submit">CREATE ACCOUNT</button>
    </form>

    <p class="account-text">Already have an account?</p>
    <a href="login.php?redirect=<?= urlencode($redirect) ?>" class="account-link">LOG IN HERE</a><br><br>

    <a href="index.php" class="back-home">← Back to Home</a>
</div>
</body>
</html>
