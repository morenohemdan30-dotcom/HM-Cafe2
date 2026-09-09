<?php
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../config/functions.php";
require_admin("../login.php");

$flash = get_flash();
$statusFilter = trim($_GET["status"] ?? "all");
$search = trim($_GET["search"] ?? "");

// Handle status updates and deletions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf($_POST["csrf_token"] ?? null)) {
        flash("error", "Your session token expired. Please try again.");
        header("Location: requests.php?status=" . urlencode($statusFilter));
        exit;
    }

    $action = $_POST["action"] ?? "";
    $orderId = filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT);

    if ($action === "update_status") {
        $status = $_POST["status"] ?? "";
        if ($orderId && in_array($status, ["Pending", "Completed", "Cancelled"], true)) {
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
            $stmt->bind_param("si", $status, $orderId);
            if ($stmt->execute()) {
                flash("success", "Request #{$orderId} status updated to '{$status}'.");
            } else {
                flash("error", "Database error: Could not update request status.");
            }
            $stmt->close();
        }
    } elseif ($action === "delete_request") {
        if ($orderId) {
            $stmt = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
            $stmt->bind_param("i", $orderId);
            if ($stmt->execute()) {
                flash("success", "Request #{$orderId} was permanently deleted.");
            } else {
                flash("error", "Database error: Could not delete request.");
            }
            $stmt->close();
        }
    }

    $redirectUrl = "requests.php?status=" . urlencode($statusFilter);
    if ($search !== "") {
        $redirectUrl .= "&search=" . urlencode($search);
    }
    header("Location: " . $redirectUrl);
    exit;
}

// Compute counts for tab badges
$counts = [
    "all" => 0,
    "pending" => 0,
    "completed" => 0,
    "cancelled" => 0
];
$cQuery = $conn->query("SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status");
if ($cQuery) {
    while ($row = $cQuery->fetch_assoc()) {
        $st = strtolower($row["status"]);
        if (isset($counts[$st])) {
            $counts[$st] = (int)$row["cnt"];
        }
        $counts["all"] += (int)$row["cnt"];
    }
}

// Build SQL Query for requests list
$sql = "
    SELECT 
        o.order_id,
        o.user_id,
        o.quantity,
        o.status,
        o.created_at,
        u.username,
        u.email,
        p.product_name,
        p.price,
        p.image,
        (o.quantity * p.price) AS total_amount
    FROM orders o
    JOIN users u ON u.user_id = o.user_id
    JOIN products p ON p.product_id = o.product_id
";

$where = [];
$params = [];
$types = "";

if (in_array($statusFilter, ["Pending", "Completed", "Cancelled"], true)) {
    $where[] = "o.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if ($search !== "") {
    $where[] = "(u.username LIKE ? OR u.email LIKE ? OR p.product_name LIKE ? OR o.order_id = ?)";
    $searchWild = "%" . $search . "%";
    $searchInt = (int)$search;
    $params[] = $searchWild;
    $params[] = $searchWild;
    $params[] = $searchWild;
    $params[] = $searchInt;
    $types .= "sssi";
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY o.created_at DESC, o.order_id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Total revenue in current selection
$selectionTotal = 0;
foreach ($requests as $req) {
    if ($req["status"] !== "Cancelled") {
        $selectionTotal += (float)$req["total_amount"];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HM Café | Requests & Orders</title>
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
        <a href="requests.php" style="border-color:var(--green); color:var(--green); background:rgba(0,212,20,0.08);">📋 REQUESTS</a>
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
    <div class="admin-welcome">
        <h1>📋 Customer Order Requests</h1>
        <p>Monitor incoming customer beverage and food requests, view full ticket details, and update preparation progress.</p>
    </div>

    <?php if ($flash): ?>
        <div class="form-message <?= e($flash["type"]) ?>"><?= e($flash["message"]) ?></div>
    <?php endif; ?>

    <!-- STATUS TABS & SEARCH -->
    <div class="admin-panel-card" style="margin-bottom: 20px;">
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px;">
            <div class="admin-filter-bar" style="margin-bottom: 0;">
                <a href="requests.php?status=all<?= $search !== "" ? "&search=" . urlencode($search) : "" ?>" class="admin-filter-tab <?= ($statusFilter === "all" || $statusFilter === "") ? "active" : "" ?>">
                    ALL REQUESTS (<?= $counts["all"] ?>)
                </a>
                <a href="requests.php?status=Pending<?= $search !== "" ? "&search=" . urlencode($search) : "" ?>" class="admin-filter-tab <?= $statusFilter === "Pending" ? "active" : "" ?>">
                    ⏳ PENDING (<?= $counts["pending"] ?>)
                </a>
                <a href="requests.php?status=Completed<?= $search !== "" ? "&search=" . urlencode($search) : "" ?>" class="admin-filter-tab <?= $statusFilter === "Completed" ? "active" : "" ?>">
                    ✅ COMPLETED (<?= $counts["completed"] ?>)
                </a>
                <a href="requests.php?status=Cancelled<?= $search !== "" ? "&search=" . urlencode($search) : "" ?>" class="admin-filter-tab <?= $statusFilter === "Cancelled" ? "active" : "" ?>">
                    ❌ CANCELLED (<?= $counts["cancelled"] ?>)
                </a>
            </div>

            <form method="get" action="requests.php" style="display: flex; gap: 8px; margin: 0; align-items: center;">
                <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by Client, Item, #ID..." style="background:#0d0d0d; border:1px solid #333; color:#fff; padding:8px 12px; border-radius:8px; font-size:13px; width: 230px;">
                <button type="submit" class="btn-admin-outline" style="padding: 8px 14px;">🔍 SEARCH</button>
                <?php if ($search !== ""): ?>
                    <a href="requests.php?status=<?= urlencode($statusFilter) ?>" class="btn-admin-danger" style="text-decoration:none; padding:8px 12px; display:inline-flex; align-items:center;">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- REQUESTS TABLE -->
    <div class="admin-panel-card" style="padding: 0; overflow: hidden;">
        <div style="padding: 16px 24px; border-bottom: 1px solid #222; display: flex; justify-content: space-between; align-items: center;">
            <strong>Showing <?= count($requests) ?> Request(s)</strong>
            <span style="font-size: 13px; color: #aaa;">Value: <strong style="color: #ffd700;">₱<?= number_format($selectionTotal, 2) ?></strong></span>
        </div>

        <div style="overflow-x: auto;">
            <table class="user-table">
                <thead>
                    <tr>
                        <th>REQUEST #</th>
                        <th>CLIENT / CUSTOMER</th>
                        <th>ITEM / PRODUCT</th>
                        <th>QTY</th>
                        <th>TOTAL (₱)</th>
                        <th>STATUS</th>
                        <th>DATE & TIME</th>
                        <th style="text-align: right;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div class="icon">📦</div>
                                    <p>No customer requests found for the selected filter.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $r): 
                            $statusClass = strtolower($r["status"]);
                        ?>
                            <tr>
                                <td>
                                    <a href="request-details.php?id=<?= (int)$r["order_id"] ?>" style="font-weight: 800; color: var(--green); text-decoration: none;">
                                        #<?= str_pad((string)$r["order_id"], 4, "0", STR_PAD_LEFT) ?>
                                    </a>
                                </td>
                                <td>
                                    <div style="line-height: 1.3;">
                                        <strong style="color:#fff;"><?= e($r["username"]) ?></strong>
                                        <span style="display:block; font-size:12px; color:#666;"><?= e($r["email"]) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <strong style="color:#fff;"><?= e($r["product_name"]) ?></strong>
                                    <span style="display:block; font-size:11px; color:#777;">₱<?= number_format((float)$r["price"], 2) ?> each</span>
                                </td>
                                <td style="font-weight: 700; color: #fff;">
                                    x<?= (int)$r["quantity"] ?>
                                </td>
                                <td style="font-weight: 800; color: #ffd700;">
                                    ₱<?= number_format((float)$r["total_amount"], 2) ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= e($statusClass) ?>">
                                        <?= e($r["status"]) ?>
                                    </span>
                                </td>
                                <td style="font-size: 12px; color: #888;">
                                    <?= date("M d, Y · h:i A", strtotime($r["created_at"])) ?>
                                </td>
                                <td>
                                    <div class="action-cell" style="justify-content: flex-end;">
                                        <!-- VIEW DETAILS BUTTON -->
                                        <a href="request-details.php?id=<?= (int)$r["order_id"] ?>" class="btn-admin-outline" style="padding: 5px 10px; font-size: 11px;">
                                            👁️ Details
                                        </a>

                                        <!-- QUICK STATUS FORM -->
                                        <form method="post" action="requests.php?status=<?= urlencode($statusFilter) ?>" style="display: inline-flex; align-items:center; gap: 4px; margin: 0;">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="order_id" value="<?= (int)$r["order_id"] ?>">
                                            <select name="status" onchange="this.form.submit()" class="admin-select" style="padding: 4px 8px; font-size: 11px; height: 28px;">
                                                <option value="Pending" <?= $r["status"] === "Pending" ? "selected" : "" ?>>Pending</option>
                                                <option value="Completed" <?= $r["status"] === "Completed" ? "selected" : "" ?>>Completed</option>
                                                <option value="Cancelled" <?= $r["status"] === "Cancelled" ? "selected" : "" ?>>Cancelled</option>
                                            </select>
                                        </form>

                                        <!-- DELETE BUTTON -->
                                        <form method="post" action="requests.php?status=<?= urlencode($statusFilter) ?>" style="display:inline; margin:0;" onsubmit="return confirm('Delete Request #<?= (int)$r['order_id'] ?> permanently?')">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete_request">
                                            <input type="hidden" name="order_id" value="<?= (int)$r["order_id"] ?>">
                                            <button type="submit" class="btn-del" title="Delete Request">🗑️</button>
                                        </form>
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
