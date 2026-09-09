<?php
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/config/functions.php";
require_login("my-orders.php");

$flash = get_flash();
$userId = (int)$_SESSION["user_id"];

// Handle cancel order
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf($_POST["csrf_token"] ?? null)) {
        flash("error", "Your session expired. Please refresh and try again.");
        header("Location: my-orders.php");
        exit;
    }

    $action = $_POST["action"] ?? "";
    $orderId = filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT);

    if ($action === "cancel_order" && $orderId) {
        $stmt = $conn->prepare("UPDATE orders SET status = 'Cancelled' WHERE order_id = ? AND user_id = ? AND status = 'Pending'");
        $stmt->bind_param("ii", $orderId, $userId);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            flash("success", "Order #{$orderId} was cancelled.");
        } else {
            flash("error", "Unable to cancel order (it may already be completed or cancelled).");
        }
        $stmt->close();
    }
    header("Location: my-orders.php");
    exit;
}

// Fetch user orders
$stmt = $conn->prepare("
    SELECT o.order_id, o.quantity, o.dining_option, o.payment_method, o.status, o.created_at, p.product_name, p.price, (o.quantity * p.price) AS total_amount
    FROM orders o
    JOIN products p ON p.product_id = o.product_id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC, o.order_id DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HM Café | My Orders</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="innovation.css">
</head>
<body>

<div class="page">
    <?php include __DIR__ . "/layout/navigation.php"; ?>

    <section class="order-records" style="padding-top: 110px; min-height: 80vh;">
        <div class="section-title">
            <span></span>
            <h2>MY <strong>ORDER HISTORY</strong></h2>
            <span></span>
        </div>

        <?php if ($flash): ?>
            <div class="form-message <?= e($flash["type"]) ?>" style="max-width: 900px; margin: 0 auto 25px;"><?= e($flash["message"]) ?></div>
        <?php endif; ?>

        <div class="orders-table-wrap" style="max-width: 1000px; margin: 0 auto;">
            <div class="admin-panel-card" style="padding: 0; overflow: hidden;">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>ORDER #</th>
                            <th>PRODUCT</th>
                            <th>QTY</th>
                            <th>DINING / PAYMENT</th>
                            <th>TOTAL</th>
                            <th>STATUS</th>
                            <th>DATE</th>
                            <th style="text-align: right;">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <div class="icon">📦</div>
                                        <p>You have not placed any orders yet.</p>
                                        <a href="shop.php" class="hero-btn green-btn" style="margin-top: 15px; display: inline-block;">ORDER FROM MENU</a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $o): ?>
                                <tr>
                                    <td><strong style="color: var(--green);">#<?= str_pad((string)$o["order_id"], 4, "0", STR_PAD_LEFT) ?></strong></td>
                                    <td><strong style="color: #fff;"><?= e($o["product_name"]) ?></strong></td>
                                    <td>x<?= (int)$o["quantity"] ?></td>
                                    <td style="font-size: 12px; color: #aaa;">
                                        <?= e($o["dining_option"] ?? 'Dine-in') ?> • <span style="color:#777;"><?= e($o["payment_method"] ?? 'Cash') ?></span>
                                    </td>
                                    <td style="font-weight: 800; color: #ffd700;">₱<?= number_format((float)$o["total_amount"], 2) ?></td>
                                    <td>
                                        <span class="status-badge <?= strtolower(e($o["status"])) ?>">
                                            <?= e($o["status"]) ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 12px; color: #888;"><?= date("M d, Y h:i A", strtotime($o["created_at"])) ?></td>
                                    <td style="text-align: right;">
                                        <?php if ($o["status"] === "Pending"): ?>
                                            <form method="post" action="my-orders.php" style="display:inline; margin:0;" onsubmit="return confirm('Cancel this order?');">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="cancel_order">
                                                <input type="hidden" name="order_id" value="<?= (int)$o["order_id"] ?>">
                                                <button type="submit" class="btn-admin-danger">Cancel Order</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #666;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
