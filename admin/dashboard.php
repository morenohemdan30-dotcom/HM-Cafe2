<?php
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../config/functions.php";
require_admin("../login.php");

$flash = get_flash();

// Quick status update from dashboard
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "update_order_status") {
    if (!verify_csrf($_POST["csrf_token"] ?? null)) {
        flash("error", "Your session expired. Please try again.");
    } else {
        $orderId = filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT);
        $newStatus = $_POST["status"] ?? "";
        if ($orderId && in_array($newStatus, ["Pending", "Completed", "Cancelled"], true)) {
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
            $stmt->bind_param("si", $newStatus, $orderId);
            $stmt->execute();
            $stmt->close();
            flash("success", "Request #{$orderId} updated to {$newStatus}.");
        }
    }
    header("Location: dashboard.php");
    exit;
}

// Financial and operational metrics
$revQuery = $conn->query("
    SELECT COALESCE(SUM(o.quantity * p.price), 0) AS gross_revenue
    FROM orders o
    JOIN products p ON p.product_id = o.product_id
    WHERE o.status != 'Cancelled'
");
$revenue = $revQuery ? (float)$revQuery->fetch_assoc()["gross_revenue"] : 0.0;

$orderCounts = [
    "total" => 0,
    "pending" => 0,
    "completed" => 0,
    "cancelled" => 0
];
$ocQuery = $conn->query("SELECT status, COUNT(*) AS count FROM orders GROUP BY status");
if ($ocQuery) {
    while ($r = $ocQuery->fetch_assoc()) {
        $st = strtolower($r["status"]);
        if (isset($orderCounts[$st])) {
            $orderCounts[$st] = (int)$r["count"];
        }
        $orderCounts["total"] += (int)$r["count"];
    }
}

$prodCount = (int)($conn->query("SELECT COUNT(*) AS total FROM products WHERE is_active = 1")->fetch_assoc()["total"] ?? 0);
$userCount = (int)($conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()["total"] ?? 0);
$msgCount = (int)($conn->query("SELECT COUNT(*) AS total FROM contact_messages")->fetch_assoc()["total"] ?? 0);

// Recent orders (latest 8)
$recentOrders = $conn->query("
    SELECT o.order_id, o.quantity, o.status, o.created_at, u.username, p.product_name, p.price
    FROM orders o
    JOIN users u ON u.user_id = o.user_id
    JOIN products p ON p.product_id = o.product_id
    ORDER BY o.created_at DESC, o.order_id DESC
    LIMIT 8
");

// Time-based greeting
$hour = (int)date("G");
if ($hour < 12) $greeting = "Good Morning";
elseif ($hour < 18) $greeting = "Good Afternoon";
else $greeting = "Good Evening";

// Today's date
$todayDate = date("l, F j, Y");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HM Café | Administrator Dashboard</title>
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
        <a href="dashboard.php" style="border-color:var(--green); color:var(--green); background:rgba(0,212,20,0.08);">⚡ DASHBOARD</a>
        <a href="requests.php">📋 REQUESTS</a>
        <a href="products.php">🍵 PRODUCTS</a>
        <a href="clients.php">👥 CLIENTS</a>
        <a href="settings.php">⚙️ SETTINGS</a>
        <a href="../shop.php" target="_blank">🛒 STOREFRONT</a>
        <form method="post" action="../logout.php" class="logout-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit">LOGOUT</button>
        </form>
    </div>
</header>

<main class="admin-main">
    <!-- WELCOME SECTION -->
    <div class="admin-welcome">
        <p class="admin-date"><?= $todayDate ?></p>
        <h1><?= $greeting ?>, <span><?= e($_SESSION["username"]) ?></span> 👋</h1>
        <p>Live administrative controls for HM Café — track revenue, fulfill customer requests, manage menu products, and configure clients.</p>
    </div>

    <?php if ($flash): ?>
        <div class="form-message <?= e($flash["type"]) ?>"><?= e($flash["message"]) ?></div>
    <?php endif; ?>

    <!-- STATS STRIP -->
    <div class="admin-stat-banner">
        <div class="admin-stat-card revenue">
            <span>TOTAL REVENUE</span>
            <strong>₱<?= number_format($revenue, 2) ?></strong>
            <small>Active & completed orders</small>
        </div>
        <div class="admin-stat-card pending">
            <span>PENDING REQUESTS</span>
            <strong><?= $orderCounts["pending"] ?></strong>
            <small>Requires barista preparation</small>
        </div>
        <div class="admin-stat-card completed">
            <span>COMPLETED REQUESTS</span>
            <strong><?= $orderCounts["completed"] ?></strong>
            <small><?= $orderCounts["total"] ?> total requests placed</small>
        </div>
        <div class="admin-stat-card">
            <span>REGISTERED CLIENTS</span>
            <strong><?= $userCount ?></strong>
            <small>Active customer accounts</small>
        </div>
    </div>

    <!-- MAIN SECTIONS GRID -->
    <div class="admin-cards" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
        <a href="requests.php" class="admin-card-link">
            <strong><?= $orderCounts["total"] ?></strong>
            <span>REQUESTS & ORDERS</span>
            <p>View tickets, update status, and manage receipts</p>
        </a>
        <a href="products.php" class="admin-card-link">
            <strong><?= $prodCount ?></strong>
            <span>MENU &amp; PRODUCTS</span>
            <p>Add new drinks/items, edit prices &amp; remove products</p>
        </a>
        <a href="clients.php" class="admin-card-link">
            <strong><?= $userCount ?></strong>
            <span>CLIENTS DIRECTORY</span>
            <p>Manage customer accounts, roles & staff</p>
        </a>
        <a href="settings.php" class="admin-card-link">
            <strong style="font-size: 32px;">⚙️</strong>
            <span>SYSTEM SETTINGS</span>
            <p>Admin security, store details & server info</p>
        </a>
    </div>

    <!-- QUICK ACTIONS -->
    <h2 style="font-size: 18px; margin: 35px 0 15px; color: #fff;">⚡ Quick Actions</h2>
    <div class="admin-links" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
        <a href="products.php" class="admin-card-link">
            <div>
                <strong>🍵 Add / Remove Products</strong>
                <p><?= $prodCount ?> active catalog items in store</p>
            </div>
        </a>
        <a href="requests.php?status=Pending" class="admin-card-link">
            <div>
                <strong>☕ Process Pending Requests</strong>
                <p><?= $orderCounts["pending"] ?> requests awaiting barista action</p>
            </div>
        </a>
        <a href="clients.php" class="admin-card-link">
            <div>
                <strong>👥 Manage Clients & Staff</strong>
                <p><?= $userCount ?> registered client accounts</p>
            </div>
        </a>
        <a href="settings.php" class="admin-card-link">
            <div>
                <strong>🔒 Security & Preferences</strong>
                <p>Change admin password & store options</p>
            </div>
        </a>
    </div>

    <!-- RECENT ACTIVITY TABLE -->
    <div class="admin-panel-card" style="margin-top:35px; padding:0; overflow:hidden;">
        <div style="padding: 18px 24px; border-bottom: 1px solid #222; display:flex; justify-content:space-between; align-items:center;">
            <h2 style="margin:0; font-size:16px;">📋 Recent Customer Requests</h2>
            <a href="requests.php" class="btn-admin-outline" style="font-size:11px; padding:6px 12px;">View All Requests →</a>
        </div>
        <div class="orders-table-wrap">
            <table class="user-table">
                <thead>
                    <tr>
                        <th>REQUEST #</th>
                        <th>CLIENT</th>
                        <th>ITEM</th>
                        <th>QTY</th>
                        <th>TOTAL</th>
                        <th>STATUS</th>
                        <th>TIME</th>
                        <th style="text-align: right;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recentOrders->num_rows === 0): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding:40px; color:#555;">
                                <div style="font-size:32px; margin-bottom:10px;">📭</div>
                                No requests placed yet. Orders will appear here once customers place requests.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php while ($o = $recentOrders->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <a href="request-details.php?id=<?= (int)$o["order_id"] ?>" style="color:var(--green); font-weight:800; text-decoration:none;">
                                        #<?= str_pad((string)$o["order_id"], 4, "0", STR_PAD_LEFT) ?>
                                    </a>
                                </td>
                                <td><strong style="color:#fff;"><?= e($o["username"]) ?></strong></td>
                                <td><?= e($o["product_name"]) ?></td>
                                <td>x<?= (int)$o["quantity"] ?></td>
                                <td><strong style="color:#ffd700;">₱<?= number_format((float)$o["price"] * (int)$o["quantity"], 2) ?></strong></td>
                                <td>
                                    <span class="status-badge <?= strtolower(e($o["status"])) ?>">
                                        <?= e($o["status"]) ?>
                                    </span>
                                </td>
                                <td style="font-size:12px; color:#888;"><?= e(date("M d, g:i A", strtotime($o["created_at"]))) ?></td>
                                <td>
                                    <div class="action-cell" style="justify-content:flex-end;">
                                        <a href="request-details.php?id=<?= (int)$o["order_id"] ?>" class="btn-admin-outline" style="padding:4px 8px; font-size:11px;">
                                            👁️ Details
                                        </a>
                                        <?php if ($o["status"] === "Pending"): ?>
                                            <form method="post" action="dashboard.php" class="inline-form" style="margin:0;">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="update_order_status">
                                                <input type="hidden" name="order_id" value="<?= (int)$o["order_id"] ?>">
                                                <input type="hidden" name="status" value="Completed">
                                                <button type="submit" class="btn-admin-primary" style="padding:5px 8px; font-size:10px;">✓ Complete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FOOTER -->
    <div style="text-align:center; margin-top:40px; color:#666; font-size:12px;">
        <p>HM Café Admin Panel • &copy; <?= date("Y") ?> All Rights Reserved</p>
    </div>
</main>

</body>
</html>
