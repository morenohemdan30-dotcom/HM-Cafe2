<?php
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../config/functions.php";
require_admin("../login.php");

$flash = get_flash();
$search = trim($_GET["search"] ?? "");
$roleFilter = trim($_GET["role"] ?? "all");

// Handle POST actions: add user, change role, delete user
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf($_POST["csrf_token"] ?? null)) {
        flash("error", "Your session token expired. Please try again.");
        header("Location: clients.php");
        exit;
    }

    $action = $_POST["action"] ?? "";

    // Add New Client / User
    if ($action === "create_user") {
        $username = trim($_POST["username"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";
        $role = $_POST["role"] ?? "customer";

        if ($username === "" || $email === "" || $password === "") {
            flash("error", "Please fill in all required fields (username, email, and password).");
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash("error", "Please enter a valid email address.");
        } elseif (strlen($password) < 6) {
            flash("error", "Password must be at least 6 characters long.");
        } elseif (!in_array($role, ["customer", "admin"], true)) {
            flash("error", "Invalid role selected.");
        } else {
            // Check for uniqueness
            $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ? LIMIT 1");
            $checkStmt->bind_param("ss", $username, $email);
            $checkStmt->execute();
            if ($checkStmt->get_result()->fetch_assoc()) {
                flash("error", "Username or email is already registered.");
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $insStmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $insStmt->bind_param("ssss", $username, $email, $hashed, $role);
                if ($insStmt->execute()) {
                    flash("success", "Client account '{$username}' created successfully as {$role}.");
                } else {
                    flash("error", "Database error creating client account.");
                }
                $insStmt->close();
            }
            $checkStmt->close();
        }
    }

    // Toggle Role (Customer <-> Admin)
    elseif ($action === "toggle_role") {
        $userId = filter_input(INPUT_POST, "user_id", FILTER_VALIDATE_INT);
        $newRole = $_POST["new_role"] ?? "";

        if (!$userId || !in_array($newRole, ["customer", "admin"], true)) {
            flash("error", "Invalid user or role specified.");
        } elseif ($userId === (int)$_SESSION["user_id"] && $newRole !== "admin") {
            flash("error", "You cannot revoke your own administrator privileges.");
        } else {
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
            $stmt->bind_param("si", $newRole, $userId);
            if ($stmt->execute()) {
                flash("success", "User #{$userId} role updated to {$newRole}.");
            } else {
                flash("error", "Could not update user role.");
            }
            $stmt->close();
        }
    }

    // Delete Client / User
    elseif ($action === "delete_user") {
        $userId = filter_input(INPUT_POST, "user_id", FILTER_VALIDATE_INT);

        if (!$userId) {
            flash("error", "Invalid user ID.");
        } elseif ($userId === (int)$_SESSION["user_id"]) {
            flash("error", "You cannot delete your own logged-in account.");
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            if ($stmt->execute()) {
                flash("success", "Client account #{$userId} deleted successfully.");
            } else {
                flash("error", "Database error: Could not delete user account.");
            }
            $stmt->close();
        }
    }

    header("Location: clients.php" . ($search !== "" ? "?search=" . urlencode($search) : ""));
    exit;
}

// Summary Statistics
$totalUsers = (int)($conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()["total"] ?? 0);
$totalAdmins = (int)($conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin'")->fetch_assoc()["total"] ?? 0);
$totalCusts = (int)($conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'customer'")->fetch_assoc()["total"] ?? 0);
$totalOrdersPlaced = (int)($conn->query("SELECT COUNT(*) AS total FROM orders")->fetch_assoc()["total"] ?? 0);

// Fetch Clients Query with Order counts
$sql = "
    SELECT 
        u.user_id,
        u.username,
        u.email,
        u.role,
        u.created_at,
        COUNT(o.order_id) AS total_orders,
        COALESCE(SUM(o.quantity * p.price), 0) AS total_spent
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.user_id
    LEFT JOIN products p ON p.product_id = o.product_id
";

$whereClauses = [];
$params = [];
$types = "";

if ($search !== "") {
    $whereClauses[] = "(u.username LIKE ? OR u.email LIKE ?)";
    $searchWild = "%" . $search . "%";
    $params[] = $searchWild;
    $params[] = $searchWild;
    $types .= "ss";
}

if ($roleFilter === "admin" || $roleFilter === "customer") {
    $whereClauses[] = "u.role = ?";
    $params[] = $roleFilter;
    $types .= "s";
}

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}

$sql .= " GROUP BY u.user_id ORDER BY u.user_id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$clients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HM Café | Clients & Users</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="../innovation.css">
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
        <a href="clients.php" style="border-color:var(--green); color:var(--green); background:rgba(0,212,20,0.08);">👥 CLIENTS</a>
        <a href="settings.php">⚙️ SETTINGS</a>
        <a href="../shop.php" target="_blank">🛒 STOREFRONT</a>
        <form method="post" action="../logout.php" class="logout-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit">LOGOUT</button>
        </form>
    </div>
</header>

<main class="admin-main">
    <div class="admin-welcome">
        <h1>👥 Client & User Management</h1>
        <p>Manage registered customer accounts, view client order history, modify account roles, and register new staff members.</p>
    </div>

    <?php if ($flash): ?>
        <div class="form-message <?= e($flash["type"]) ?>"><?= e($flash["message"]) ?></div>
    <?php endif; ?>

    <!-- STATS STRIP -->
    <div class="user-stat-strip">
        <div class="user-stat-box">
            <div class="user-stat-icon total">👥</div>
            <div class="user-stat-text">
                <span>TOTAL CLIENTS</span>
                <strong><?= $totalUsers ?></strong>
            </div>
        </div>
        <div class="user-stat-box">
            <div class="user-stat-icon cust">☕</div>
            <div class="user-stat-text">
                <span>CUSTOMERS</span>
                <strong><?= $totalCusts ?></strong>
            </div>
        </div>
        <div class="user-stat-box">
            <div class="user-stat-icon admin">🛡️</div>
            <div class="user-stat-text">
                <span>ADMIN STAFF</span>
                <strong><?= $totalAdmins ?></strong>
            </div>
        </div>
        <div class="user-stat-box">
            <div class="user-stat-icon total">📦</div>
            <div class="user-stat-text">
                <span>ORDERS GENERATED</span>
                <strong><?= $totalOrdersPlaced ?></strong>
            </div>
        </div>
    </div>

    <!-- CREATE NEW CLIENT / USER FORM -->
    <div class="admin-panel-card">
        <h2>➕ Register New Client or Staff</h2>
        <form method="post" action="clients.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create_user">
            
            <div class="create-form-grid">
                <div class="field-group">
                    <label>Username *</label>
                    <input type="text" name="username" placeholder="e.g. maria_santos" required autocomplete="off">
                </div>
                <div class="field-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" placeholder="e.g. maria@example.com" required autocomplete="off">
                </div>
                <div class="field-group">
                    <label>Password * (min 6 characters)</label>
                    <div class="pw-wrap">
                        <input type="password" id="new_pw" name="password" placeholder="••••••••" required autocomplete="new-password">
                        <button type="button" class="eye" onclick="var i=document.getElementById('new_pw');i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'👁':'🙈';">👁</button>
                    </div>
                </div>
                <div class="field-group">
                    <label>Account Role *</label>
                    <select name="role" required>
                        <option value="customer">Customer (Standard Client)</option>
                        <option value="admin">Administrator (Staff Access)</option>
                    </select>
                </div>
            </div>

            <div style="margin-top: 18px; display: flex; justify-content: flex-end;">
                <button type="submit" class="btn-admin-primary">+ ADD CLIENT ACCOUNT</button>
            </div>
        </form>
    </div>

    <!-- CLIENTS TABLE & FILTER -->
    <div class="admin-panel-card" style="padding: 0; overflow: hidden;">
        <div style="padding: 20px 24px; border-bottom: 1px solid #222; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px;">
            <h2 style="margin: 0;">Registered Clients Directory (<?= count($clients) ?>)</h2>
            
            <form method="get" action="clients.php" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin: 0;">
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search username or email..." style="background:#0d0d0d; border:1px solid #333; color:#fff; padding:8px 12px; border-radius:8px; font-size:13px; width: 220px;">
                
                <select name="role" onchange="this.form.submit()" style="background:#0d0d0d; border:1px solid #333; color:#fff; padding:8px 12px; border-radius:8px; font-size:13px;">
                    <option value="all" <?= $roleFilter === "all" ? "selected" : "" ?>>All Roles</option>
                    <option value="customer" <?= $roleFilter === "customer" ? "selected" : "" ?>>Customers Only</option>
                    <option value="admin" <?= $roleFilter === "admin" ? "selected" : "" ?>>Admins Only</option>
                </select>

                <button type="submit" class="btn-admin-outline" style="padding: 8px 14px;">🔍 FILTER</button>
                <?php if ($search !== "" || $roleFilter !== "all"): ?>
                    <a href="clients.php" class="btn-admin-danger" style="text-decoration:none; padding:8px 12px; display:inline-flex; align-items:center;">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div style="overflow-x: auto;">
            <table class="user-table">
                <thead>
                    <tr>
                        <th>CLIENT / USER</th>
                        <th>ROLE</th>
                        <th>REQUESTS / ORDERS</th>
                        <th>TOTAL SPENT</th>
                        <th>JOINED DATE</th>
                        <th style="text-align: right;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clients)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="icon">🔍</div>
                                    <p>No client accounts found matching your search criteria.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clients as $c): 
                            $isSelf = ((int)$c["user_id"] === (int)$_SESSION["user_id"]);
                            $initials = strtoupper(substr($c["username"], 0, 2));
                        ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="avatar <?= $c["role"] === "admin" ? "admin-av" : "cust-av" ?>">
                                            <?= e($initials) ?>
                                        </div>
                                        <div class="user-info-text">
                                            <strong>
                                                <?= e($c["username"]) ?>
                                                <?php if ($isSelf): ?>
                                                    <span class="self-tag">(You)</span>
                                                <?php endif; ?>
                                            </strong>
                                            <span><?= e($c["email"]) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge <?= e($c["role"]) ?>">
                                        <?= $c["role"] === "admin" ? "🛡️ ADMIN" : "☕ CUSTOMER" ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="requests.php?search=<?= urlencode($c["username"]) ?>" style="color:var(--green); text-decoration:none; font-weight:700;">
                                        📦 <?= (int)$c["total_orders"] ?> Request(s)
                                    </a>
                                </td>
                                <td style="font-weight: 700; color: #ffd700;">
                                    ₱<?= number_format((float)$c["total_spent"], 2) ?>
                                </td>
                                <td style="font-size: 12px; color: #888;">
                                    <?= date("M d, Y", strtotime($c["created_at"])) ?>
                                </td>
                                <td>
                                    <div class="action-cell" style="justify-content: flex-end;">
                                        <!-- TOGGLE ROLE BUTTON -->
                                        <?php if (!$isSelf): ?>
                                            <form method="post" action="clients.php" style="display:inline; margin:0;">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="toggle_role">
                                                <input type="hidden" name="user_id" value="<?= (int)$c["user_id"] ?>">
                                                <?php if ($c["role"] === "customer"): ?>
                                                    <input type="hidden" name="new_role" value="admin">
                                                    <button type="submit" class="btn-promote" title="Promote to Administrator" onclick="return confirm('Promote <?= e($c['username']) ?> to Administrator?')">
                                                        🛡️ Make Admin
                                                    </button>
                                                <?php else: ?>
                                                    <input type="hidden" name="new_role" value="customer">
                                                    <button type="submit" class="btn-demote" title="Change to Customer" onclick="return confirm('Change <?= e($c['username']) ?> to Customer role?')">
                                                        ☕ Set Customer
                                                    </button>
                                                <?php endif; ?>
                                            </form>

                                            <!-- DELETE BUTTON -->
                                            <form method="post" action="clients.php" style="display:inline; margin:0;" onsubmit="return confirm('Are you sure you want to delete client <?= e($c['username']) ?>? All associated records will be removed.')">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= (int)$c["user_id"] ?>">
                                                <button type="submit" class="btn-del" title="Delete Client Account">🗑️</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="session-pill"><span class="dot"></span> Active Session</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

</body>
</html>
