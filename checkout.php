<?php
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/config/functions.php";
require_login("checkout.php");

$flash = get_flash();
$singleProductId = filter_input(INPUT_GET, "product", FILTER_VALIDATE_INT);
$userId = (int)$_SESSION["user_id"];

$itemsToCheckout = [];
$totalAmount = 0;

if ($singleProductId) {
    $stmt = $conn->prepare("SELECT product_id, product_name, price, image FROM products WHERE product_id = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("i", $singleProductId);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($p) {
        $itemsToCheckout[] = [
            "product_id" => (int)$p["product_id"],
            "product_name" => $p["product_name"],
            "price" => (float)$p["price"],
            "image" => $p["image"],
            "quantity" => 1,
            "subtotal" => (float)$p["price"]
        ];
        $totalAmount += (float)$p["price"];
    }
} elseif (!empty($_SESSION["cart"])) {
    $ids = array_map("intval", array_keys($_SESSION["cart"]));
    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    $types = str_repeat("i", count($ids));

    $stmt = $conn->prepare("SELECT product_id, product_name, price, image FROM products WHERE product_id IN ({$placeholders})");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $pid = (int)$row["product_id"];
        $qty = (int)$_SESSION["cart"][$pid];
        $sub = $qty * (float)$row["price"];
        $totalAmount += $sub;

        $itemsToCheckout[] = [
            "product_id" => $pid,
            "product_name" => $row["product_name"],
            "price" => (float)$row["price"],
            "image" => $row["image"],
            "quantity" => $qty,
            "subtotal" => $sub
        ];
    }
    $stmt->close();
}

// Handle Order Placement
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf($_POST["csrf_token"] ?? null)) {
        flash("error", "Your session expired. Please refresh and try again.");
        header("Location: checkout.php" . ($singleProductId ? "?product=" . $singleProductId : ""));
        exit;
    }

    $diningOption = $_POST["dining_option"] ?? "Dine-in";
    $paymentMethod = $_POST["payment_method"] ?? "Cash on Counter";

    if (empty($itemsToCheckout)) {
        flash("error", "Your order has no items to process.");
        header("Location: shop.php");
        exit;
    }

    $conn->begin_transaction();
    try {
        $insStmt = $conn->prepare("INSERT INTO orders (user_id, product_id, quantity, dining_option, payment_method, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        
        foreach ($itemsToCheckout as $item) {
            $pid = (int)$item["product_id"];
            $qty = (int)$item["quantity"];
            $insStmt->bind_param("iiiss", $userId, $pid, $qty, $diningOption, $paymentMethod);
            $insStmt->execute();
        }
        $insStmt->close();
        $conn->commit();

        // Clear cart if multi-item checkout
        if (!$singleProductId) {
            $_SESSION["cart"] = [];
        }

        flash("success", "Your order has been placed successfully! Our baristas are preparing your items.");
        header("Location: my-orders.php");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        flash("error", "An error occurred while placing your order. Please try again.");
        header("Location: checkout.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HM Café | Checkout & Place Order</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="innovation.css">
</head>
<body>

<div class="page">
    <?php include __DIR__ . "/layout/navigation.php"; ?>

    <section class="order-page" style="padding-top: 110px; min-height: 80vh; align-items: flex-start;">
        <div style="max-width: 850px; width: 100%; margin: 0 auto;">
            <div class="section-title">
                <span></span>
                <h2>CONFIRM <strong>CHECKOUT</strong></h2>
                <span></span>
            </div>

            <?php if ($flash): ?>
                <div class="form-message <?= e($flash["type"]) ?>"><?= e($flash["message"]) ?></div>
            <?php endif; ?>

            <?php if (empty($itemsToCheckout)): ?>
                <div class="admin-panel-card" style="text-align: center; padding: 50px;">
                    <h3>No items selected for checkout</h3>
                    <p style="color: #888; margin-bottom: 20px;">Please browse our shop menu to pick delicious drinks and food.</p>
                    <a href="shop.php" class="hero-btn green-btn">BROWSE MENU</a>
                </div>
            <?php else: ?>
                <form method="post" action="checkout.php<?= $singleProductId ? "?product=" . $singleProductId : "" ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <div class="admin-panel-card" style="margin-bottom: 25px;">
                        <h2>📋 Order Summary</h2>
                        <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 15px;">
                            <?php foreach ($itemsToCheckout as $it): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #222; padding-bottom: 10px;">
                                    <div>
                                        <strong style="color: #fff;"><?= e($it["product_name"]) ?></strong>
                                        <span style="color: #888; font-size: 12px; display: block;">x<?= (int)$it["quantity"] ?> @ ₱<?= number_format($it["price"], 2) ?> each</span>
                                    </div>
                                    <strong style="color: #ffd700;">₱<?= number_format($it["subtotal"], 2) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 18px; padding-top: 15px; border-top: 1px solid #333; font-size: 18px;">
                            <strong>GRAND TOTAL:</strong>
                            <strong style="color: #ffd700; font-size: 24px;">₱<?= number_format($totalAmount, 2) ?></strong>
                        </div>
                    </div>

                    <div class="admin-panel-card" style="margin-bottom: 25px;">
                        <h2>☕ Dining &amp; Payment Preferences</h2>
                        <div class="create-form-grid">
                            <div class="field-group">
                                <label>Dining Option *</label>
                                <select name="dining_option" required>
                                    <option value="Dine-in">Dine-in (At HM Café table)</option>
                                    <option value="Takeout">Takeout / To-Go</option>
                                    <option value="Delivery">Pickup Counter</option>
                                </select>
                            </div>
                            <div class="field-group">
                                <label>Payment Method *</label>
                                <select name="payment_method" required>
                                    <option value="Cash on Counter">Cash on Counter</option>
                                    <option value="GCash / E-Wallet">GCash / QR E-Wallet</option>
                                    <option value="Credit / Debit Card">Credit / Debit Card</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                        <a href="cart.php" class="hero-btn outline-btn">← BACK TO CART</a>
                        <button type="submit" class="hero-btn green-btn" style="cursor: pointer; border: none; font-size: 13px;">
                            ⚡ PLACE ORDER (₱<?= number_format($totalAmount, 2) ?>)
                        </button>
                    </div>
                </form>
            <?php endif; ?>
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
