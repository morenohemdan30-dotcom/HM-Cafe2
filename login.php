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
    $password = $_POST["password"] ?? "";

    if (!verify_csrf($_POST["csrf_token"] ?? null)) {
        $error = "Your session expired. Please refresh and try again.";
    } elseif ($username === "" || $password === "") {
        $error = "Please enter both username and password.";
    } elseif (strlen($username) > 50) {
        $error = "Username is too long.";
    } else {
        $stmt = $conn->prepare("SELECT user_id, username, email, password, role FROM users WHERE username = ? LIMIT 1");
        if (!$stmt) {
            $error = "Login is temporarily unavailable. Please try again.";
        } else {
            $stmt->bind_param("s", $username);
            if ($stmt->execute()) {
                $user = $stmt->get_result()->fetch_assoc();
                if ($user && password_verify($password, $user["password"])) {
                    session_regenerate_id(true);
                    $_SESSION["user_id"] = (int)$user["user_id"];
                    $_SESSION["username"] = $user["username"];
                    $_SESSION["email"] = $user["email"];
                    $_SESSION["role"] = $user["role"];
                    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

                    if ($user["role"] === "admin") {
                        header("Location: admin/dashboard.php");
                    } else {
                        header("Location: " . $redirect);
                    }
                    exit;
                }
            }
            $error = "Invalid username or password.";
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HM Café | Login</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="innovation.css">
</head>
<body class="login-page">
<div class="login-container">
    <img src="assets/logo.png" class="login-logo" alt="HM Café Logo">
    <h1>Welcome <span>Back</span></h1>
    <p class="login-subtitle">COFFEE CONNECT ENJOY</p>

    <?php if ($error): ?>
        <div class="login-error"><?= e($error) ?></div>
    <?php endif; ?>


    <form method="POST" action="login.php" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

        <div class="login-group">
            <input type="text" id="username" name="username" placeholder="Enter username" value="<?= e($_POST["username"] ?? "") ?>" required autocomplete="username">
        </div>

        <div class="login-group">
            <div class="password-wrap">
                <input type="password" id="password" name="password" placeholder="Enter password" required autocomplete="current-password">
                <button type="button" class="toggle-pw" onclick="var i=document.getElementById('password');i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'👁':'🙈';" title="Show/hide password">👁</button>
            </div>
        </div>

        <button type="submit">LOGIN</button>
    </form>

    <p class="account-text">Don't have an account?</p>
    <a href="register.php?redirect=<?= urlencode($redirect) ?>" class="account-link">CREATE ACCOUNT</a><br><br>

    <a href="index.php" class="back-home">← Back to Home</a>
</div>
</body>
</html>
