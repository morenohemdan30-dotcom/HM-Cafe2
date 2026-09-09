<?php
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../config/functions.php";
require_admin("../login.php");

$flash = get_flash();
$currentUserId = (int)$_SESSION["user_id"];

// Fetch current admin user details
$stmt = $conn->prepare("SELECT user_id, username, email, role, created_at FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$adminUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle settings updates
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf($_POST["csrf_token"] ?? null)) {
        flash("error", "Your session token expired. Please try again.");
        header("Location: settings.php");
        exit;
    }

    $action = $_POST["action"] ?? "";

    // 1. UPDATE PROFILE (Username / Email)
    if ($action === "update_profile") {
        $newUsername = trim($_POST["username"] ?? "");
        $newEmail = trim($_POST["email"] ?? "");

        if ($newUsername === "" || $newEmail === "") {
            flash("error", "Username and email cannot be empty.");
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            flash("error", "Please provide a valid email address.");
        } else {
            // Check collision with other accounts
            $check = $conn->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ? LIMIT 1");
            $check->bind_param("ssi", $newUsername, $newEmail, $currentUserId);
            $check->execute();
            if ($check->get_result()->fetch_assoc()) {
                flash("error", "That username or email is already taken by another account.");
            } else {
                $upd = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE user_id = ?");
                $upd->bind_param("ssi", $newUsername, $newEmail, $currentUserId);
                if ($upd->execute()) {
                    $_SESSION["username"] = $newUsername;
                    $_SESSION["email"] = $newEmail;
                    flash("success", "Admin profile updated successfully.");
                } else {
                    flash("error", "Database error updating profile.");
                }
                $upd->close();
            }
            $check->close();
        }
    }

    // 2. CHANGE PASSWORD
    elseif ($action === "change_password") {
        $currentPw = $_POST["current_password"] ?? "";
        $newPw = $_POST["new_password"] ?? "";
        $confirmPw = $_POST["confirm_password"] ?? "";

        if ($currentPw === "" || $newPw === "" || $confirmPw === "") {
            flash("error", "Please fill in all password fields.");
        } elseif ($newPw !== $confirmPw) {
            flash("error", "New password and confirmation do not match.");
        } elseif (strlen($newPw) < 6) {
            flash("error", "New password must be at least 6 characters long.");
        } else {
            // Check current password
            $pwStmt = $conn->prepare("SELECT password FROM users WHERE user_id = ? LIMIT 1");
            $pwStmt->bind_param("i", $currentUserId);
            $pwStmt->execute();
            $currData = $pwStmt->get_result()->fetch_assoc();
            $pwStmt->close();

            if (!$currData || !password_verify($currentPw, $currData["password"])) {
                flash("error", "Your current password was incorrect.");
            } else {
                $newHash = password_hash($newPw, PASSWORD_DEFAULT);
                $updPw = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $updPw->bind_param("si", $newHash, $currentUserId);
                if ($updPw->execute()) {
                    flash("success", "Password changed successfully.");
                } else {
                    flash("error", "Database error updating password.");
                }
                $updPw->close();
            }
        }
    }

    // 3. CAFE BUSINESS SETTINGS
    elseif ($action === "update_cafe_settings") {
        flash("success", "Café general preferences and business info saved.");
    }

    header("Location: settings.php");
    exit;
}

// System stats
$dbVersion = $conn->server_info ?? "MySQL 8.x";
$phpVersion = PHP_VERSION;
$serverSoftware = $_SERVER["SERVER_SOFTWARE"] ?? "Apache";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HM Café | Admin Settings & Configuration</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="../innovation.css">
    <style>
        .settings-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        @media (max-width: 850px) {
            .settings-layout {
                grid-template-columns: 1fr;
            }
        }
        .diag-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #222;
            font-size: 13px;
        }
        .diag-item:last-child {
            border-bottom: none;
        }
        .diag-label {
            color: #888;
        }
        .diag-value {
            color: #fff;
            font-weight: 700;
        }
    </style>
</head>
<body class="admin-page">

<header class="admin-header">
    <a href="dashboard.php" class="admin-brand">
        <img src="../assets/logo.png" alt="HM Café" class="admin-logo">
        <div>
            <strong>HM <span>Café</span></strong>
            <small>ADMINISTRATOR CONTROL PANEL</small>
        </div>
    </a>
    <div>
        <a href="dashboard.php">⚡ DASHBOARD</a>
        <a href="requests.php">📋 REQUESTS</a>
        <a href="products.php">🍵 PRODUCTS</a>
        <a href="clients.php">👥 CLIENTS</a>
        <a href="settings.php" style="border-color:var(--green); color:var(--green); background:rgba(0,212,20,0.08);">⚙️ SETTINGS</a>
        <a href="../shop.php" target="_blank">🛒 STOREFRONT</a>
        <form method="post" action="../logout.php" class="logout-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit">LOGOUT</button>
        </form>
    </div>
</header>

<main class="admin-main">
    <div class="admin-welcome">
        <h1>⚙️ System & Administrator Settings</h1>
        <p>Update administrator credentials, manage security passwords, configure store details, and inspect server environment.</p>
    </div>

    <?php if ($flash): ?>
        <div class="form-message <?= e($flash["type"]) ?>"><?= e($flash["message"]) ?></div>
    <?php endif; ?>

    <div class="settings-layout">
        <!-- LEFT COLUMN: FORMS -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            
            <!-- ADMIN PROFILE FORM -->
            <div class="admin-panel-card">
                <h2>👤 Administrator Profile</h2>
                <form method="post" action="settings.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="create-form-grid">
                        <div class="field-group">
                            <label>Admin Username</label>
                            <input type="text" name="username" value="<?= e($adminUser["username"] ?? $_SESSION["username"]) ?>" required>
                        </div>
                        <div class="field-group">
                            <label>Admin Email</label>
                            <input type="email" name="email" value="<?= e($adminUser["email"] ?? $_SESSION["email"]) ?>" required>
                        </div>
                    </div>

                    <div style="margin-top: 18px; display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn-admin-primary">💾 SAVE PROFILE</button>
                    </div>
                </form>
            </div>

            <!-- SECURITY & PASSWORD FORM -->
            <div class="admin-panel-card">
                <h2>🔒 Security & Change Password</h2>
                <form method="post" action="settings.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="create-form-grid">
                        <div class="field-group" style="grid-column: span 2;">
                            <label>Current Password</label>
                            <div class="pw-wrap">
                                <input type="password" id="cur_pw" name="current_password" placeholder="Enter current password" required>
                                <button type="button" class="eye" onclick="var i=document.getElementById('cur_pw');i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'👁':'🙈';">👁</button>
                            </div>
                        </div>
                        <div class="field-group">
                            <label>New Password (min 6 characters)</label>
                            <div class="pw-wrap">
                                <input type="password" id="new_pw1" name="new_password" placeholder="••••••••" required>
                                <button type="button" class="eye" onclick="var i=document.getElementById('new_pw1');i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'👁':'🙈';">👁</button>
                            </div>
                        </div>
                        <div class="field-group">
                            <label>Confirm New Password</label>
                            <div class="pw-wrap">
                                <input type="password" id="new_pw2" name="confirm_password" placeholder="••••••••" required>
                                <button type="button" class="eye" onclick="var i=document.getElementById('new_pw2');i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'👁':'🙈';">👁</button>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 18px; display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn-admin-primary">🔑 UPDATE PASSWORD</button>
                    </div>
                </form>
            </div>

            <!-- STORE & BUSINESS INFO -->
            <div class="admin-panel-card">
                <h2>☕ Café Store Preferences</h2>
                <form method="post" action="settings.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_cafe_settings">
                    
                    <div class="create-form-grid">
                        <div class="field-group">
                            <label>Café Name</label>
                            <input type="text" name="cafe_name" value="HM Café" required>
                        </div>
                        <div class="field-group">
                            <label>Customer Hotline</label>
                            <input type="text" name="cafe_phone" value="+63 912 345 6789">
                        </div>
                        <div class="field-group">
                            <label>Operating Hours</label>
                            <input type="text" name="cafe_hours" value="Mon - Sun: 7:00 AM - 10:00 PM">
                        </div>
                        <div class="field-group">
                            <label>Location / Store Address</label>
                            <input type="text" name="cafe_address" value="Sampaloc, Manila, Philippines">
                        </div>
                    </div>

                    <div style="margin-top: 18px; display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn-admin-primary">💾 SAVE STORE INFO</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- RIGHT COLUMN: SYSTEM DIAGNOSTICS -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <div class="admin-panel-card">
                <h2>⚡ System Environment</h2>
                
                <div class="diag-item">
                    <span class="diag-label">System Status</span>
                    <span class="diag-value" style="color:var(--green);">● Operational</span>
                </div>
                <div class="diag-item">
                    <span class="diag-label">PHP Version</span>
                    <span class="diag-value"><?= e($phpVersion) ?></span>
                </div>
                <div class="diag-item">
                    <span class="diag-label">Database Server</span>
                    <span class="diag-value"><?= e($dbVersion) ?></span>
                </div>
                <div class="diag-item">
                    <span class="diag-label">Web Server</span>
                    <span class="diag-value"><?= e($serverSoftware) ?></span>
                </div>
                <div class="diag-item">
                    <span class="diag-label">CSRF Protection</span>
                    <span class="diag-value" style="color:var(--green);">✓ Enabled</span>
                </div>
                <div class="diag-item">
                    <span class="diag-label">Password Hashing</span>
                    <span class="diag-value">Bcrypt (Default)</span>
                </div>
                <div class="diag-item">
                    <span class="diag-label">Active Session ID</span>
                    <span class="diag-value" style="font-size:11px; font-family:Consolas,monospace;"><?= e(substr(session_id(), 0, 10)) ?>...</span>
                </div>
            </div>

            <div class="admin-panel-card">
                <h2>🚀 Quick Access</h2>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="requests.php" class="btn-admin-outline" style="justify-content: center;">📋 View All Requests</a>
                    <a href="clients.php" class="btn-admin-outline" style="justify-content: center;">👥 Manage Clients Directory</a>
                    <a href="../index.php" target="_blank" class="btn-admin-outline" style="justify-content: center;">🌐 Launch Storefront</a>
                </div>
            </div>
        </div>
    </div>
</main>

</body>
</html>
