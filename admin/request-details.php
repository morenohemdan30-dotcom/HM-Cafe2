<?php
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../config/functions.php";
require_admin("../login.php");

$flash = get_flash();
$orderId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$orderId) {
    flash("error", "No valid request ID was specified.");
    header("Location: requests.php");
    exit;
}

// Handle status change or deletion from details page
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf($_POST["csrf_token"] ?? null)) {
        flash("error", "Your session token expired. Please try again.");
        header("Location: request-details.php?id=" . $orderId);
        exit;
    }

    $action = $_POST["action"] ?? "";

    if ($action === "update_status") {
        $newStatus = $_POST["status"] ?? "";
        if (in_array($newStatus, ["Pending", "Completed", "Cancelled"], true)) {
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
            $stmt->bind_param("si", $newStatus, $orderId);
            if ($stmt->execute()) {
                flash("success", "Request #{$orderId} status was updated to '{$newStatus}'.");
            } else {
                flash("error", "Database error updating request status.");
            }
            $stmt->close();
        }
        header("Location: request-details.php?id=" . $orderId);
        exit;
    } elseif ($action === "delete_request") {
        $stmt = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
        $stmt->bind_param("i", $orderId);
        if ($stmt->execute()) {
            flash("success", "Request #{$orderId} was permanently deleted.");
            header("Location: requests.php");
            exit;
        } else {
            flash("error", "Database error deleting request.");
            header("Location: request-details.php?id=" . $orderId);
            exit;
        }
        $stmt->close();
    }
}

// Fetch complete request and client details
$stmt = $conn->prepare("
    SELECT 
        o.order_id,
        o.user_id,
        o.quantity,
        o.status,
        o.created_at,
        u.username,
        u.email,
        u.role AS user_role,
        u.created_at AS user_since,
        p.product_id,
        p.product_name,
        p.description,
        p.price,
        p.image,
        (o.quantity * p.price) AS total_amount
    FROM orders o
    JOIN users u ON u.user_id = o.user_id
    JOIN products p ON p.product_id = o.product_id
    WHERE o.order_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$req) {
    flash("error", "Request #{$orderId} could not be found in the database.");
    header("Location: requests.php");
    exit;
}

$statusClass = strtolower($req["status"]);
$clientInitials = strtoupper(substr($req["username"], 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HM Café | Request #<?= str_pad((string)$orderId, 4, "0", STR_PAD_LEFT) ?> Details</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="../innovation.css">
    <style>
        .details-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        @media (max-width: 850px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
        }
        .item-card {
            display: flex;
            gap: 20px;
            background: #0d0d0d;
            border: 1px solid #222;
            border-radius: 12px;
            padding: 20px;
            align-items: center;
        }
        .item-img {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid #333;
            background: #151515;
        }
        .breakdown-table {
            width: 100%;
            margin-top: 15px;
            border-collapse: collapse;
        }
        .breakdown-table td {
            padding: 10px 0;
            border-bottom: 1px solid #222;
            color: #aaa;
        }
        .breakdown-table tr:last-child td {
            border-bottom: none;
            font-size: 18px;
            font-weight: 800;
            color: #fff;
            padding-top: 15px;
        }
        @media print {
            .admin-header, .btn-admin-outline, .admin-welcome, .no-print {
                display: none !important;
            }
            body, .admin-page {
                background: #fff !important;
                color: #000 !important;
            }
            .admin-panel-card, .item-card {
                background: #fff !important;
                color: #000 !important;
                border: 1px solid #ccc !important;
                box-shadow: none !important;
            }
            .breakdown-table td {
                color: #000 !important;
            }
        }
    </style>
</head>
<body class="admin-page">

<header class="admin-header no-print">
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
    <div class="no-print" style="margin-bottom: 20px;">
        <a href="requests.php" class="btn-admin-outline">← Back to All Requests</a>
    </div>

    <?php if ($flash): ?>
        <div class="form-message <?= e($flash["type"]) ?> no-print"><?= e($flash["message"]) ?></div>
    <?php endif; ?>

    <!-- HEADER TITLE & STATUS BAR -->
    <div class="admin-panel-card" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px;">
        <div>
            <span style="font-size: 11px; letter-spacing: 2px; color: #888; text-transform: uppercase;">REQUEST TICKET</span>
            <h1 style="margin: 4px 0 0; font-size: 28px;">
                Request #<?= str_pad((string)$req["order_id"], 4, "0", STR_PAD_LEFT) ?>
            </h1>
            <p style="margin: 4px 0 0; color: #888; font-size: 13px;">
                Submitted on <?= date("l, F j, Y · h:i:s A", strtotime($req["created_at"])) ?>
            </p>
        </div>

        <div style="display: flex; align-items: center; gap: 15px;">
            <span class="status-badge <?= e($statusClass) ?>" style="font-size: 14px; padding: 8px 18px;">
                STATUS: <?= strtoupper($req["status"]) ?>
            </span>
            <button type="button" onclick="window.print()" class="btn-admin-outline no-print" style="padding: 9px 15px;">
                🖨️ Print Ticket
            </button>
        </div>
    </div>

    <!-- MAIN TWO-COLUMN DETAILS -->
    <div class="details-grid">
        <!-- LEFT COLUMN: ITEM BREAKDOWN -->
        <div class="admin-panel-card">
            <h2>☕ Requested Item & Breakdown</h2>
            
            <div class="item-card">
                <img src="../assets/<?= e($req["image"] ?: 'coffee-mug.png') ?>" alt="<?= e($req["product_name"]) ?>" class="item-img" onerror="this.src='../assets/logo.png'">
                <div style="flex: 1;">
                    <h3 style="margin: 0 0 6px; font-size: 20px; color: #fff;"><?= e($req["product_name"]) ?></h3>
                    <p style="margin: 0 0 10px; color: #888; font-size: 13px; line-height: 1.5;"><?= e($req["description"]) ?></p>
                    <div style="display: flex; gap: 15px; font-size: 13px;">
                        <span>Price: <strong style="color:var(--green);">₱<?= number_format((float)$req["price"], 2) ?></strong></span>
                        <span>Quantity: <strong style="color:#fff;">x<?= (int)$req["quantity"] ?></strong></span>
                    </div>
                </div>
            </div>

            <table class="breakdown-table">
                <tr>
                    <td>Item Subtotal (<?= (int)$req["quantity"] ?> × ₱<?= number_format((float)$req["price"], 2) ?>)</td>
                    <td style="text-align: right;">₱<?= number_format((float)$req["total_amount"], 2) ?></td>
                </tr>
                <tr>
                    <td>Estimated Service & Tax (Included)</td>
                    <td style="text-align: right;">₱0.00</td>
                </tr>
                <tr>
                    <td>GRAND TOTAL</td>
                    <td style="text-align: right; color: #ffd700;">₱<?= number_format((float)$req["total_amount"], 2) ?></td>
                </tr>
            </table>

            <!-- UPDATE STATUS QUICK BUTTONS -->
            <div class="no-print" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #222;">
                <h3 style="font-size: 14px; margin-bottom: 12px; color: #aaa;">UPDATE ORDER FULFILLMENT STATUS:</h3>
                <form method="post" action="request-details.php?id=<?= (int)$req["order_id"] ?>" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_status">
                    
                    <button type="submit" name="status" value="Pending" class="btn-admin-outline" style="border-color: #ffa500; color: #ffa500; <?= $req["status"] === "Pending" ? "background:rgba(255,165,0,0.15);" : "" ?>">
                        ⏳ Set to Pending
                    </button>
                    <button type="submit" name="status" value="Completed" class="btn-admin-primary" style="<?= $req["status"] === "Completed" ? "box-shadow: 0 0 15px rgba(0,212,20,0.5);" : "" ?>">
                        ✅ Mark as Completed
                    </button>
                    <button type="submit" name="status" value="Cancelled" class="btn-admin-danger" style="<?= $req["status"] === "Cancelled" ? "background:rgba(255,77,77,0.15);" : "" ?>">
                        ❌ Cancel Request
                    </button>
                </form>
            </div>
        </div>

        <!-- RIGHT COLUMN: CLIENT INFORMATION -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <div class="admin-panel-card">
                <h2>👤 Client Information</h2>
                
                <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 20px;">
                    <div class="avatar cust-av" style="width: 50px; height: 50px; font-size: 18px;">
                        <?= e($clientInitials) ?>
                    </div>
                    <div>
                        <strong style="font-size: 18px; color: #fff;"><?= e($req["username"]) ?></strong>
                        <span style="display: block; font-size: 13px; color: #888;"><?= e($req["email"]) ?></span>
                    </div>
                </div>

                <div style="border-top: 1px solid #222; padding-top: 15px; display: flex; flex-direction: column; gap: 10px; font-size: 13px;">
                    <div>
                        <span style="color: #666;">Account ID:</span>
                        <strong style="color: #fff; margin-left: 6px;">#<?= (int)$req["user_id"] ?></strong>
                    </div>
                    <div>
                        <span style="color: #666;">Account Role:</span>
                        <span class="role-badge <?= e($req["user_role"]) ?>" style="margin-left: 6px;">
                            <?= strtoupper($req["user_role"]) ?>
                        </span>
                    </div>
                    <div>
                        <span style="color: #666;">Member Since:</span>
                        <strong style="color: #fff; margin-left: 6px;"><?= date("M d, Y", strtotime($req["user_since"])) ?></strong>
                    </div>
                </div>

                <div class="no-print" style="margin-top: 20px; border-top: 1px solid #222; padding-top: 15px;">
                    <a href="requests.php?search=<?= urlencode($req["username"]) ?>" class="btn-admin-outline" style="width: 100%; justify-content: center; box-sizing: border-box;">
                        🔍 View All Requests from this Client
                    </a>
                </div>
            </div>

            <!-- DANGER ZONE: DELETE -->
            <div class="admin-panel-card no-print" style="border-color: rgba(255, 77, 77, 0.25);">
                <h3 style="margin: 0 0 8px; font-size: 15px; color: #ff4d4d;">⚠️ Administrative Actions</h3>
                <p style="font-size: 12px; color: #888; margin-bottom: 15px;">Permanently delete this order record from the system database.</p>
                <form method="post" action="request-details.php?id=<?= (int)$req["order_id"] ?>" onsubmit="return confirm('Are you certain you wish to delete Request #<?= (int)$req['order_id'] ?>? This action is irreversible.')">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_request">
                    <button type="submit" class="btn-admin-danger" style="width: 100%; padding: 10px;">
                        🗑️ Delete Request Record
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>

</body>
</html>
