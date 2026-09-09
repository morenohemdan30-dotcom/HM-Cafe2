<?php
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/config/functions.php";
require_login("profile.php");

$flash = get_flash();
$userId = (int)$_SESSION["user_id"];

// Fetch user profile
$stmt = $conn->prepare("SELECT user_id, username, email, role, created_at FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf($_POST["csrf_token"] ?? null)) {
        flash("error", "Your session expired. Please refresh and try again.");
        header("Location: profile.php");
        exit;
    }

    $action = $_POST["action"] ?? "";

    if ($action === "update_profile") {
        $email = trim($_POST["email"] ?? "");
        if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash("error", "Please provide a valid email address.");
        } else {
            $check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ? LIMIT 1");
            $check->bind_param("si", $email, $userId);
            $check->execute();
            if ($check->get_result()->fetch_assoc()) {
                flash("error", "That email is already in use by another account.");
            } else {
                $upd = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ?");
                $upd->bind_param("si", $email, $userId);
                if ($upd->execute()) {
                    $_SESSION["email"] = $email;
                    flash("success", "Profile email updated successfully.");
                }
                $upd->close();
            }
            $check->close();
        }
    } elseif ($action === "change_password") {
        $current = $_POST["current_password"] ?? "";
        $new = $_POST["new_password"] ?? "";
        $confirm = $_POST["confirm_password"] ?? "";

        if ($current === "" || $new === "" || $confirm === "") {
            flash("error", "Please fill in all password fields.");
        } elseif ($new !== $confirm) {
            flash("error", "New passwords do not match.");
        } elseif (strlen($new) < 6) {
            flash("error", "Password must be at least 6 characters.");
        } else {
            $pStmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
            $pStmt->bind_param("i", $userId);
            $pStmt->execute();
            $currPass = $pStmt->get_result()->fetch_assoc()["password"] ?? "";
            $pStmt->close();

            if (!password_verify($current, $currPass)) {
                flash("error", "Incorrect current password.");
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $updPw = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $updPw->bind_param("si", $hash, $userId);
                if ($updPw->execute()) {
                    flash("success", "Your password has been changed.");
                }
                $updPw->close();
            }
        }
    }
    header("Location: profile.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HM Café | Customer Profile</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="innovation.css">
</head>
<body>

<div class="page">
    <?php include __DIR__ . "/layout/navigation.php"; ?>

    <section class="order-page" style="padding-top: 110px; min-height: 80vh; align-items: flex-start;">
        <div style="max-width: 800px; width: 100%; margin: 0 auto;">
            <div class="section-title">
                <span></span>
                <h2>MY <strong>PROFILE</strong></h2>
                <span></span>
            </div>

            <?php if ($flash): ?>
                <div class="form-message <?= e($flash["type"]) ?>"><?= e($flash["message"]) ?></div>
            <?php endif; ?>

            <div class="admin-panel-card" style="margin-bottom: 25px;">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                    <div class="avatar cust-av" style="width: 50px; height: 50px; font-size: 18px;">
                        <?= strtoupper(substr($user["username"], 0, 2)) ?>
                    </div>
                    <div>
                        <strong style="font-size: 20px; color: #fff;"><?= e($user["username"]) ?></strong>
                        <span style="display: block; font-size: 13px; color: #888;">Member since <?= date("F j, Y", strtotime($user["created_at"])) ?></span>
                    </div>
                </div>

                <form method="post" action="profile.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="create-form-grid">
                        <div class="field-group">
                            <label>Username (Fixed)</label>
                            <input type="text" value="<?= e($user["username"]) ?>" disabled style="opacity: 0.6;">
                        </div>
                        <div class="field-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?= e($user["email"]) ?>" required>
                        </div>
                    </div>

                    <div style="margin-top: 15px; display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn-admin-primary">SAVE EMAIL</button>
                    </div>
                </form>
            </div>

            <div class="admin-panel-card">
                <h2>🔒 Change Password</h2>
                <form method="post" action="profile.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="change_password">

                    <div class="create-form-grid">
                        <div class="field-group" style="grid-column: span 2;">
                            <label>Current Password</label>
                            <input type="password" name="current_password" required placeholder="••••••••">
                        </div>
                        <div class="field-group">
                            <label>New Password (min 6 chars)</label>
                            <input type="password" name="new_password" required placeholder="••••••••">
                        </div>
                        <div class="field-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" required placeholder="••••••••">
                        </div>
                    </div>

                    <div style="margin-top: 15px; display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn-admin-primary">UPDATE PASSWORD</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer style="padding: 40px 7%; background: #070707; border-top: 1px solid #1a1a1a; text-align: center; color: #777; font-size: 13px;">
        <p>&copy; <?= date("Y") ?> HM Café. All rights reserved. • Coffee • Connect • Enjoy</p>
    </footer>
</div>

<script src="script.js"></script>
</body>
</html>
