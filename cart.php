<?php
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/config/functions.php";

if (!isset($_SESSION["cart"])) {
    $_SESSION["cart"] = [];
}

$flash = get_flash();

// Handle cart actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf($_POST["csrf_token"] ?? null)) {
        flash("error", "Your session expired. Please try again.");
        header("Location: cart.php");
        exit;
    }

    $action = $_POST["action"] ?? "";

    if ($action === "add_to_cart") {
        $productId = filter_input(INPUT_POST, "product_id", FILTER_VALIDATE_INT);
        $qty = max(1, filter_input(INPUT_POST, "quantity", FILTER_VALIDATE_INT) ?: 1);

        if ($productId) {
            $_SESSION["cart"][$productId] = ($_SESSION["cart"][$productId] ?? 0) + $qty;
            flash("success", "Item added to your shopping cart!");
        }
    } elseif ($action === "update_cart") {
        $productId = filter_input(INPUT_POST, "product_id", FILTER_VALIDATE_INT);
        $qty = filter_input(INPUT_POST, "quantity", FILTER_VALIDATE_INT);

        if ($productId) {
            if ($qty <= 0) {
                unset($_SESSION["cart"][$productId]);
                flash("success", "Item removed from cart.");
            } else {
                $_SESSION["cart"][$productId] = min(20, $qty);
                flash("success", "Cart quantity updated.");
            }
        }
    } elseif ($action === "remove") {
        $productId = filter_input(INPUT_POST, "product_id", FILTER_VALIDATE_INT);
        if ($productId && isset($_SESSION["cart"][$productId])) {
            unset($_SESSION["cart"][$productId]);
            flash("success", "Item removed from cart.");
        }
    } elseif ($action === "clear") {
        $_SESSION["cart"] = [];
        flash("success", "Cart emptied.");
    }

    header("Location: cart.php");
    exit;
}

// Fetch details for items in cart
$cartItems = [];
$grandTotal = 0;

if (!empty($_SESSION["cart"])) {
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
        $subtotal = $qty * (float)$row["price"];
        $grandTotal += $subtotal;

        $cartItems[] = [
            "product_id" => $pid,
            "product_name" => $row["product_name"],
            "price" => (float)$row["price"],
            "image" => $row["image"],
            "quantity" => $qty,
            "subtotal" => $subtotal
        ];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HM Café | Shopping Cart</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="innovation.css">
</head>
<body>

<div class="page">
    <?php include __DIR__ . "/layout/navigation.php"; ?>

    <section class="order-page" style="padding-top: 110px; min-height: 80vh; align-items: flex-start;">
        <div style="max-width: 900px; width: 100%; margin: 0 auto;">
            <div class="section-title">
                <span></span>
                <h2>YOUR <strong>SHOPPING CART</strong></h2>
                <span></span>
            </div>

            <?php if ($flash): ?>
                <div class="form-message <?= e($flash["type"]) ?>"><?= e($flash["message"]) ?></div>
            <?php endif; ?>

            <?php if (empty($cartItems)): ?>
                <div class="admin-panel-card" style="text-align: center; padding: 60px 20px;">
                    <div style="font-size: 50px; margin-bottom: 15px;">🛒</div>
                    <h3 style="margin-bottom: 10px; color: #fff;">Your cart is currently empty</h3>
                    <p style="color: #888; margin-bottom: 25px;">Check out our specialty coffee, pastries, and treats!</p>
                    <a href="shop.php" class="hero-btn green-btn">BROWSE SHOP MENU</a>
                </div>
            <?php else: ?>
                <div class="admin-panel-card" style="padding: 0; overflow: hidden; margin-bottom: 25px;">
                    <div style="overflow-x: auto;">
                        <table class="user-table">
                            <thead>
                                <tr>
                                    <th>ITEM</th>
                                    <th>PRICE</th>
                                    <th>QTY</th>
                                    <th>SUBTOTAL</th>
                                    <th style="text-align: right;">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cartItems as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="user-info">
                                                <img src="assets/<?= e($item["image"] ?: 'coffee-mug.png') ?>" alt="<?= e($item["product_name"]) ?>" style="width: 45px; height: 45px; object-fit: cover; border-radius: 8px;" onerror="this.src='assets/logo.png'">
                                                <strong style="color: #fff;"><?= e($item["product_name"]) ?></strong>
                                            </div>
                                        </td>
                                        <td style="color: #aaa;">₱<?= number_format($item["price"], 2) ?></td>
                                        <td>
                                            <form method="post" action="cart.php" style="display: inline-flex; align-items: center; gap: 6px; margin: 0;">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="update_cart">
                                                <input type="hidden" name="product_id" value="<?= $item["product_id"] ?>">
                                                <input type="number" name="quantity" min="1" max="20" value="<?= $item["quantity"] ?>" style="width: 50px; padding: 6px; background:#0d0d0d; border:1px solid #333; color:#fff; border-radius:6px;">
                                                <button type="submit" class="btn-admin-outline" style="padding: 5px 8px; font-size: 11px;">Update</button>
                                            </form>
                                        </td>
                                        <td style="font-weight: 800; color: #ffd700;">₱<?= number_format($item["subtotal"], 2) ?></td>
                                        <td style="text-align: right;">
                                            <form method="post" action="cart.php" style="display: inline; margin: 0;">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="product_id" value="<?= $item["product_id"] ?>">
                                                <button type="submit" class="btn-admin-danger" style="padding: 6px 10px;">🗑️ Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="padding: 20px 24px; background: #111; border-top: 1px solid #222; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                        <div>
                            <form method="post" action="cart.php" style="margin: 0;" onsubmit="return confirm('Empty the entire cart?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="clear">
                                <button type="submit" class="btn-admin-danger">🗑️ CLEAR CART</button>
                            </form>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size: 13px; color: #888;">TOTAL AMOUNT:</span>
                            <strong style="font-size: 24px; color: #ffd700; margin-left: 10px;">₱<?= number_format($grandTotal, 2) ?></strong>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <a href="shop.php" class="hero-btn outline-btn">← CONTINUE SHOPPING</a>
                    <a href="checkout.php" class="hero-btn green-btn">PROCEED TO CHECKOUT ⚡</a>
                </div>
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
