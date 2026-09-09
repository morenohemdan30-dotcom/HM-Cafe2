<?php
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/config/functions.php";

$flash = get_flash();
$category = trim($_GET["category"] ?? "all");
$search = trim($_GET["search"] ?? "");

// Build products query
$sql = "SELECT product_id, product_name, description, price, image, category FROM products WHERE is_active = 1";
$params = [];
$types = "";

if ($category !== "all" && $category !== "") {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

if ($search !== "") {
    $sql .= " AND (product_name LIKE ? OR description LIKE ?)";
    $searchWild = "%" . $search . "%";
    $params[] = $searchWild;
    $params[] = $searchWild;
    $types .= "ss";
}

$sql .= " ORDER BY product_id ASC";

$products = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $products = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
} else {
    // Fallback if category column is missing during initial load
    $fallbackQuery = $conn->query("SELECT product_id, product_name, description, price, image, 'Coffee' AS category FROM products WHERE is_active = 1");
    if ($fallbackQuery) {
        $products = $fallbackQuery->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HM Café | Shop & Menu Catalog</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="innovation.css">
</head>
<body>

<div class="page">
    <?php include __DIR__ . "/layout/navigation.php"; ?>

    <section class="menu-section" style="padding-top: 110px; min-height: 85vh;">
        <div class="section-title">
            <span></span>
            <h2>CAFÉ <strong>MENU &amp; SHOP</strong></h2>
            <span></span>
        </div>
        <p style="text-align: center; color: #888; max-width: 600px; margin: -10px auto 35px; font-size: 14px;">
            Freshly brewed espresso drinks, iced refreshments, signature snacks, and artisanal sweet pastries.
        </p>

        <?php if ($flash): ?>
            <div class="form-message <?= e($flash["type"]) ?>" style="max-width: 600px; margin: 0 auto 25px;"><?= e($flash["message"]) ?></div>
        <?php endif; ?>

        <!-- FILTER TABS & SEARCH -->
        <div style="max-width: 1100px; margin: 0 auto 35px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px;">
            <div class="admin-filter-bar" style="margin-bottom: 0;">
                <a href="shop.php?category=all<?= $search !== "" ? "&search=" . urlencode($search) : "" ?>" class="admin-filter-tab <?= ($category === "all" || $category === "") ? "active" : "" ?>">ALL ITEMS</a>
                <a href="shop.php?category=Coffee<?= $search !== "" ? "&search=" . urlencode($search) : "" ?>" class="admin-filter-tab <?= $category === "Coffee" ? "active" : "" ?>">☕ COFFEE</a>
                <a href="shop.php?category=Non-Coffee<?= $search !== "" ? "&search=" . urlencode($search) : "" ?>" class="admin-filter-tab <?= $category === "Non-Coffee" ? "active" : "" ?>">🍵 NON-COFFEE</a>
                <a href="shop.php?category=Pastries<?= $search !== "" ? "&search=" . urlencode($search) : "" ?>" class="admin-filter-tab <?= $category === "Pastries" ? "active" : "" ?>">🍰 PASTRIES</a>
                <a href="shop.php?category=Meals<?= $search !== "" ? "&search=" . urlencode($search) : "" ?>" class="admin-filter-tab <?= $category === "Meals" ? "active" : "" ?>">🍝 MEALS</a>
            </div>

            <form method="get" action="shop.php" style="display: flex; gap: 8px; margin: 0;">
                <input type="hidden" name="category" value="<?= e($category) ?>">
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search drinks or food..." style="background:#0d0d0d; border:1px solid #333; color:#fff; padding:8px 12px; border-radius:8px; font-size:13px; width: 220px;">
                <button type="submit" class="btn-admin-outline" style="padding: 8px 14px;">🔍 SEARCH</button>
            </form>
        </div>

        <!-- PRODUCTS GRID -->
        <div class="menu-grid" style="max-width: 1100px; margin: 0 auto;">
            <?php if (empty($products)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 60px 20px; color: #666;">
                    <div style="font-size: 40px; margin-bottom: 10px;">🔍</div>
                    <p>No menu items found for your selection.</p>
                </div>
            <?php else: ?>
                <?php foreach ($products as $p): ?>
                    <div class="menu-card" style="display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <img src="assets/<?= e($p["image"] ?: "coffee-mug.png") ?>" alt="<?= e($p["product_name"]) ?>" onerror="this.src='assets/logo.png'">
                            <div class="menu-content">
                                <span style="display: inline-block; font-size: 10px; font-weight: 700; color: var(--green); letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 4px;"><?= e($p["category"]) ?></span>
                                <h3><?= e($p["product_name"]) ?></h3>
                                <p><?= e($p["description"]) ?></p>
                                <div class="price">₱<?= number_format((float)$p["price"], 2) ?></div>
                            </div>
                        </div>

                        <div style="padding: 0 20px 20px; display: flex; gap: 8px;">
                            <a href="checkout.php?product=<?= (int)$p["product_id"] ?>" class="hero-btn green-btn" style="flex: 1; text-align: center; padding: 10px; font-size: 11px;">
                                ⚡ ORDER NOW
                            </a>
                            <form method="post" action="cart.php" style="margin: 0;">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="add_to_cart">
                                <input type="hidden" name="product_id" value="<?= (int)$p["product_id"] ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn-admin-outline" style="height: 100%; padding: 10px 14px;" title="Add to Cart">
                                    🛒
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
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
