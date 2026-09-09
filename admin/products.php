<?php
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../config/functions.php";
require_admin("../login.php");

$flash = get_flash();
$search = trim($_GET["search"] ?? "");
$categoryFilter = trim($_GET["category"] ?? "all");
$statusFilter = trim($_GET["status"] ?? "all");

// Available asset images for dropdown helper
$availableImages = [
    "menu-iced-coffee.png",
    "menu-matcha.png",
    "menu-cake.png",
    "menu-pasta.png",
    "gallery-01.png",
    "gallery-02.png",
    "gallery-03.png",
    "gallery-04.png",
    "gallery-05.png",
    "gallery-06.png",
    "gallery-07.png",
    "hero-coffee.png",
    "logo.png"
];

// Handle POST actions: create_product, update_product, delete_product, toggle_status
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf($_POST["csrf_token"] ?? null)) {
        flash("error", "Your session token expired. Please try again.");
        header("Location: products.php");
        exit;
    }

    $action = $_POST["action"] ?? "";

    // 1. ADD NEW PRODUCT
    if ($action === "create_product") {
        $name = trim($_POST["product_name"] ?? "");
        $category = trim($_POST["category"] ?? "Coffee");
        $price = filter_input(INPUT_POST, "price", FILTER_VALIDATE_FLOAT);
        $description = trim($_POST["description"] ?? "");
        $image = trim($_POST["image"] ?? "menu-iced-coffee.png");
        $isActive = isset($_POST["is_active"]) ? 1 : 0;

        if ($name === "" || $description === "" || $price === false || $price <= 0) {
            flash("error", "Please provide a valid product name, description, and price greater than 0.");
        } else {
            // Check for duplicate name
            $checkStmt = $conn->prepare("SELECT product_id FROM products WHERE product_name = ? LIMIT 1");
            $checkStmt->bind_param("s", $name);
            $checkStmt->execute();
            if ($checkStmt->get_result()->fetch_assoc()) {
                flash("error", "A product named '{$name}' already exists.");
            } else {
                $insStmt = $conn->prepare("INSERT INTO products (product_name, category, price, description, image, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                $insStmt->bind_param("ssdssi", $name, $category, $price, $description, $image, $isActive);
                if ($insStmt->execute()) {
                    flash("success", "Product '{$name}' added to catalog successfully!");
                } else {
                    flash("error", "Database error: Could not add product.");
                }
                $insStmt->close();
            }
            $checkStmt->close();
        }
    }

    // 2. UPDATE PRODUCT
    elseif ($action === "update_product") {
        $productId = filter_input(INPUT_POST, "product_id", FILTER_VALIDATE_INT);
        $name = trim($_POST["product_name"] ?? "");
        $category = trim($_POST["category"] ?? "Coffee");
        $price = filter_input(INPUT_POST, "price", FILTER_VALIDATE_FLOAT);
        $description = trim($_POST["description"] ?? "");
        $image = trim($_POST["image"] ?? "menu-iced-coffee.png");
        $isActive = isset($_POST["is_active"]) ? 1 : 0;

        if (!$productId || $name === "" || $description === "" || $price === false || $price <= 0) {
            flash("error", "Please provide valid product details and price.");
        } else {
            // Check name uniqueness on other products
            $checkStmt = $conn->prepare("SELECT product_id FROM products WHERE product_name = ? AND product_id != ? LIMIT 1");
            $checkStmt->bind_param("si", $name, $productId);
            $checkStmt->execute();
            if ($checkStmt->get_result()->fetch_assoc()) {
                flash("error", "Another product already uses the name '{$name}'.");
            } else {
                $updStmt = $conn->prepare("UPDATE products SET product_name = ?, category = ?, price = ?, description = ?, image = ?, is_active = ? WHERE product_id = ?");
                $updStmt->bind_param("ssdssii", $name, $category, $price, $description, $image, $isActive, $productId);
                if ($updStmt->execute()) {
                    flash("success", "Product #{$productId} ('{$name}') updated successfully.");
                } else {
                    flash("error", "Database error: Could not update product.");
                }
                $updStmt->close();
            }
            $checkStmt->close();
        }
    }

    // 3. TOGGLE ACTIVE / INACTIVE STATUS
    elseif ($action === "toggle_status") {
        $productId = filter_input(INPUT_POST, "product_id", FILTER_VALIDATE_INT);
        $newStatus = filter_input(INPUT_POST, "new_status", FILTER_VALIDATE_INT);

        if ($productId && ($newStatus === 0 || $newStatus === 1)) {
            $stmt = $conn->prepare("UPDATE products SET is_active = ? WHERE product_id = ?");
            $stmt->bind_param("ii", $newStatus, $productId);
            if ($stmt->execute()) {
                $statusLabel = $newStatus === 1 ? "Active in Storefront" : "Inactive / Hidden";
                flash("success", "Product #{$productId} status changed to {$statusLabel}.");
            } else {
                flash("error", "Could not toggle product status.");
            }
            $stmt->close();
        }
    }

    // 4. DELETE / REMOVE PRODUCT
    elseif ($action === "delete_product") {
        $productId = filter_input(INPUT_POST, "product_id", FILTER_VALIDATE_INT);

        if (!$productId) {
            flash("error", "Invalid product ID.");
        } else {
            // Check if product is referenced in existing orders
            $orderCheck = $conn->prepare("SELECT COUNT(*) AS c FROM orders WHERE product_id = ?");
            $orderCheck->bind_param("i", $productId);
            $orderCheck->execute();
            $orderCount = (int)($orderCheck->get_result()->fetch_assoc()["c"] ?? 0);
            $orderCheck->close();

            if ($orderCount > 0) {
                // If it has existing orders, soft-delete to maintain order history integrity
                $stmt = $conn->prepare("UPDATE products SET is_active = 0 WHERE product_id = ?");
                $stmt->bind_param("i", $productId);
                if ($stmt->execute()) {
                    flash("success", "Product #{$productId} is referenced in {$orderCount} past order(s). It was deactivated and hidden from the storefront instead of deleting history.");
                } else {
                    flash("error", "Could not deactivate product.");
                }
                $stmt->close();
            } else {
                // Hard delete if never ordered
                $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
                $stmt->bind_param("i", $productId);
                if ($stmt->execute()) {
                    flash("success", "Product #{$productId} has been completely removed from the catalog.");
                } else {
                    flash("error", "Database error: Could not remove product.");
                }
                $stmt->close();
            }
        }
    }

    header("Location: products.php" . ($search !== "" ? "?search=" . urlencode($search) : ""));
    exit;
}

// Build query for catalog items
$whereClauses = [];
$params = [];
$types = "";

if ($search !== "") {
    $whereClauses[] = "(p.product_name LIKE ? OR p.description LIKE ?)";
    $searchWild = "%" . $search . "%";
    $params[] = $searchWild;
    $params[] = $searchWild;
    $types .= "ss";
}

if ($categoryFilter !== "all" && $categoryFilter !== "") {
    $whereClauses[] = "p.category = ?";
    $params[] = $categoryFilter;
    $types .= "s";
}

if ($statusFilter === "active") {
    $whereClauses[] = "p.is_active = 1";
} elseif ($statusFilter === "inactive") {
    $whereClauses[] = "p.is_active = 0";
}

$sql = "
    SELECT p.product_id, p.product_name, p.category, p.price, p.description, p.image, p.is_active,
           COUNT(o.order_id) AS times_ordered
    FROM products p
    LEFT JOIN orders o ON o.product_id = p.product_id
";

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}

$sql .= " GROUP BY p.product_id ORDER BY p.product_id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Statistics counts
$totalProducts = (int)($conn->query("SELECT COUNT(*) AS c FROM products")->fetch_assoc()["c"] ?? 0);
$activeProducts = (int)($conn->query("SELECT COUNT(*) AS c FROM products WHERE is_active = 1")->fetch_assoc()["c"] ?? 0);
$inactiveProducts = $totalProducts - $activeProducts;
$categoryCounts = $conn->query("SELECT category, COUNT(*) AS c FROM products GROUP BY category");
$catStats = [];
if ($categoryCounts) {
    while ($row = $categoryCounts->fetch_assoc()) {
        $catStats[$row["category"]] = (int)$row["c"];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HM Café | Product & Menu Management</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="../innovation.css">
    <style>
        .product-thumbnail {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            object-fit: cover;
            background: #111;
            border: 1px solid #333;
            display: inline-block;
            vertical-align: middle;
            margin-right: 10px;
        }
        .category-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            background: rgba(255, 255, 255, 0.05);
            color: #ddd;
            border: 1px solid #333;
        }
        .category-pill.coffee {
            background: rgba(0, 212, 20, 0.1);
            color: var(--green);
            border-color: rgba(0, 212, 20, 0.25);
        }
        .category-pill.pastries {
            background: rgba(255, 215, 0, 0.1);
            color: #ffd700;
            border-color: rgba(255, 215, 0, 0.25);
        }
        .category-pill.meals {
            background: rgba(255, 107, 107, 0.1);
            color: #ff6b6b;
            border-color: rgba(255, 107, 107, 0.25);
        }
        .category-pill.non-coffee {
            background: rgba(100, 200, 255, 0.1);
            color: #64c8ff;
            border-color: rgba(100, 200, 255, 0.25);
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
        <a href="products.php" style="border-color:var(--green); color:var(--green); background:rgba(0,212,20,0.08);">🍵 PRODUCTS</a>
        <a href="clients.php">👥 CLIENTS</a>
        <a href="settings.php">⚙️ SETTINGS</a>
        <a href="../shop.php" target="_blank">🛒 STOREFRONT</a>
        <form method="post" action="../logout.php" class="logout-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="admin-logout-btn">LOGOUT</button>
        </form>
    </div>
</header>

<main class="admin-main">
    <?php if ($flash): ?>
        <div class="admin-flash <?= e($flash["type"]) ?>">
            <?= e($flash["message"]) ?>
        </div>
    <?php endif; ?>

    <!-- TOP HEADER -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px;">
        <div>
            <h1 class="admin-page-title" style="margin-bottom: 4px;">🍵 Product &amp; <span>Menu Catalog</span></h1>
            <p style="color: #888; font-size: 13px; margin: 0;">Add new drinks &amp; pastries, edit pricing, or remove menu items from the storefront.</p>
        </div>
        <button type="button" class="btn-admin-primary" onclick="openAddModal()">
            ➕ ADD NEW PRODUCT
        </button>
    </div>

    <!-- STATS STRIP -->
    <div class="admin-stats-grid" style="margin-bottom: 30px;">
        <div class="stat-card">
            <span class="stat-label">Total Catalog Items</span>
            <div class="stat-value"><?= number_format($totalProducts) ?></div>
            <span class="stat-sub">Across all categories</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Active in Storefront</span>
            <div class="stat-value" style="color: var(--green);"><?= number_format($activeProducts) ?></div>
            <span class="stat-sub">Available for customer checkout</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Hidden / Inactive</span>
            <div class="stat-value" style="color: #aaa;"><?= number_format($inactiveProducts) ?></div>
            <span class="stat-sub">Drafts or archived items</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Category Breakdown</span>
            <div style="font-size: 13px; color: #bbb; margin-top: 6px; line-height: 1.5;">
                ☕ <?= $catStats["Coffee"] ?? 0 ?> Coffee · 🍵 <?= $catStats["Non-Coffee"] ?? 0 ?> Tea<br>
                🍰 <?= $catStats["Pastries"] ?? 0 ?> Pastries · 🍝 <?= $catStats["Meals"] ?? 0 ?> Meals
            </div>
        </div>
    </div>

    <!-- SEARCH & FILTER BAR -->
    <div class="admin-table-card">
        <div class="admin-filter-bar">
            <a href="products.php?category=all&status=<?= urlencode($statusFilter) ?><?= $search !== "" ? "&search=" . urlencode($search) : "" ?>" class="admin-filter-tab <?= ($categoryFilter === "all" || $categoryFilter === "") ? "active" : "" ?>">ALL CATEGORIES</a>
            <a href="products.php?category=Coffee&status=<?= urlencode($statusFilter) ?><?= $search !== "" ? "&search=" . urlencode($search) : "" ?>" class="admin-filter-tab <?= $categoryFilter === "Coffee" ? "active" : "" ?>">☕ COFFEE</a>
            <a href="products.php?category=Non-Coffee&status=<?= urlencode($statusFilter) ?><?= $search !== "" ? "&search=" . urlencode($search) : "" ?>" class="admin-filter-tab <?= $categoryFilter === "Non-Coffee" ? "active" : "" ?>">🍵 NON-COFFEE</a>
            <a href="products.php?category=Pastries&status=<?= urlencode($statusFilter) ?><?= $search !== "" ? "&search=" . urlencode($search) : "" ?>" class="admin-filter-tab <?= $categoryFilter === "Pastries" ? "active" : "" ?>">🍰 PASTRIES</a>
            <a href="products.php?category=Meals&status=<?= urlencode($statusFilter) ?><?= $search !== "" ? "&search=" . urlencode($search) : "" ?>" class="admin-filter-tab <?= $categoryFilter === "Meals" ? "active" : "" ?>">🍝 MEALS</a>

            <form method="get" action="products.php" class="admin-search-form">
                <input type="hidden" name="category" value="<?= e($categoryFilter) ?>">
                <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search product name or desc..." class="admin-search-input">
                <button type="submit" class="btn-admin-outline" style="padding: 8px 14px;">🔍</button>
                <?php if ($search !== "" || $categoryFilter !== "all" || $statusFilter !== "all"): ?>
                    <a href="products.php" class="btn-admin-outline" style="padding: 8px 12px; color: #aaa;" title="Reset Filters">✕</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- PRODUCTS TABLE -->
        <div style="overflow-x: auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>PRODUCT</th>
                        <th>CATEGORY</th>
                        <th>PRICE</th>
                        <th>POPULARITY</th>
                        <th>STATUS</th>
                        <th style="text-align: right;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 45px 20px; color: #777;">
                                <div style="font-size: 36px; margin-bottom: 10px;">🔍</div>
                                <strong>No products found matching your search.</strong><br>
                                <span style="font-size: 12px;">Click "+ ADD NEW PRODUCT" to create your first menu item.</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $prod): ?>
                            <?php
                                $catClass = strtolower(str_replace(" ", "-", $prod["category"]));
                            ?>
                            <tr>
                                <td>
                                    <strong style="color: var(--green);">#<?= str_pad((string)$prod["product_id"], 4, "0", STR_PAD_LEFT) ?></strong>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center;">
                                        <img src="../assets/<?= e($prod["image"] ?: "logo.png") ?>" alt="<?= e($prod["product_name"]) ?>" class="product-thumbnail" onerror="this.src='../assets/logo.png'">
                                        <div>
                                            <strong style="color: #fff; font-size: 14px; display: block;"><?= e($prod["product_name"]) ?></strong>
                                            <span style="color: #888; font-size: 12px;"><?= e($prod["description"]) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="category-pill <?= e($catClass) ?>"><?= e($prod["category"]) ?></span>
                                </td>
                                <td>
                                    <strong style="color: #ffd700; font-size: 14px;">₱<?= number_format((float)$prod["price"], 2) ?></strong>
                                </td>
                                <td>
                                    <span style="color: #aaa; font-size: 12.5px;">
                                        <?= (int)$prod["times_ordered"] ?> orders
                                    </span>
                                </td>
                                <td>
                                    <form method="post" action="products.php" style="margin: 0; display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="product_id" value="<?= (int)$prod["product_id"] ?>">
                                        <input type="hidden" name="new_status" value="<?= (int)$prod["is_active"] === 1 ? 0 : 1 ?>">
                                        <button type="submit" class="status-badge <?= (int)$prod["is_active"] === 1 ? 'completed' : 'cancelled' ?>" style="cursor: pointer; border: none;" title="Click to toggle active status">
                                            <?= (int)$prod["is_active"] === 1 ? '● Active' : '○ Hidden' ?>
                                        </button>
                                    </form>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <!-- EDIT BUTTON -->
                                    <button type="button" class="btn-admin-outline" style="padding: 6px 12px; font-size: 11.5px; margin-right: 6px;" onclick='openEditModal(<?= json_encode($prod, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                        ✏️ Edit
                                    </button>

                                    <!-- DELETE / REMOVE BUTTON -->
                                    <form method="post" action="products.php" style="display: inline; margin: 0;" onsubmit="return confirm('Are you sure you want to remove \'<?= addslashes(e($prod["product_name"])) ?>\' from the menu?');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="product_id" value="<?= (int)$prod["product_id"] ?>">
                                        <button type="submit" class="btn-admin-danger" style="padding: 6px 12px; font-size: 11.5px;">
                                            🗑️ Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- ADD PRODUCT MODAL -->
<div id="addModal" class="admin-modal">
    <div class="admin-modal-card" style="max-width: 540px;">
        <div class="admin-modal-header">
            <h3>➕ Add New <span>Menu Product</span></h3>
            <button type="button" class="admin-modal-close" onclick="closeModal('addModal')">✕</button>
        </div>

        <form method="post" action="products.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create_product">

            <div class="field-group">
                <label>Product Name *</label>
                <input type="text" name="product_name" required placeholder="e.g. Caramel Macchiato" style="width: 100%;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div class="field-group">
                    <label>Category *</label>
                    <select name="category" required style="width: 100%;">
                        <option value="Coffee">☕ Coffee</option>
                        <option value="Non-Coffee">🍵 Non-Coffee / Tea</option>
                        <option value="Pastries">🍰 Pastries &amp; Cakes</option>
                        <option value="Meals">🍝 Meals &amp; Pasta</option>
                    </select>
                </div>

                <div class="field-group">
                    <label>Price (PHP ₱) *</label>
                    <input type="number" step="0.01" min="1" name="price" required placeholder="120.00" style="width: 100%;">
                </div>
            </div>

            <div class="field-group">
                <label>Description *</label>
                <textarea name="description" rows="3" required placeholder="Brief appetizing description of ingredients and flavor notes..." style="width: 100%;"></textarea>
            </div>

            <div class="field-group">
                <label>Product Image File</label>
                <select name="image" style="width: 100%;">
                    <?php foreach ($availableImages as $img): ?>
                        <option value="<?= e($img) ?>"><?= e($img) ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #777; font-size: 11px; margin-top: 4px; display: block;">Located in the <code>assets/</code> folder</small>
            </div>

            <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" id="add_is_active" name="is_active" value="1" checked style="width: 18px; height: 18px; accent-color: var(--green);">
                <label for="add_is_active" style="color: #ddd; font-size: 13px; cursor: pointer; margin: 0;">Publish to storefront immediately</label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn-admin-outline" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn-admin-primary">SAVE PRODUCT</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT PRODUCT MODAL -->
<div id="editModal" class="admin-modal">
    <div class="admin-modal-card" style="max-width: 540px;">
        <div class="admin-modal-header">
            <h3>✏️ Edit <span>Menu Product</span></h3>
            <button type="button" class="admin-modal-close" onclick="closeModal('editModal')">✕</button>
        </div>

        <form method="post" action="products.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="update_product">
            <input type="hidden" name="product_id" id="edit_product_id">

            <div class="field-group">
                <label>Product Name *</label>
                <input type="text" name="product_name" id="edit_product_name" required style="width: 100%;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div class="field-group">
                    <label>Category *</label>
                    <select name="category" id="edit_category" required style="width: 100%;">
                        <option value="Coffee">☕ Coffee</option>
                        <option value="Non-Coffee">🍵 Non-Coffee / Tea</option>
                        <option value="Pastries">🍰 Pastries &amp; Cakes</option>
                        <option value="Meals">🍝 Meals &amp; Pasta</option>
                    </select>
                </div>

                <div class="field-group">
                    <label>Price (PHP ₱) *</label>
                    <input type="number" step="0.01" min="1" name="price" id="edit_price" required style="width: 100%;">
                </div>
            </div>

            <div class="field-group">
                <label>Description *</label>
                <textarea name="description" id="edit_description" rows="3" required style="width: 100%;"></textarea>
            </div>

            <div class="field-group">
                <label>Product Image File</label>
                <select name="image" id="edit_image" style="width: 100%;">
                    <?php foreach ($availableImages as $img): ?>
                        <option value="<?= e($img) ?>"><?= e($img) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" id="edit_is_active" name="is_active" value="1" style="width: 18px; height: 18px; accent-color: var(--green);">
                <label for="edit_is_active" style="color: #ddd; font-size: 13px; cursor: pointer; margin: 0;">Active &amp; visible in storefront</label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn-admin-outline" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn-admin-primary">UPDATE PRODUCT</button>
            </div>
        </form>
    </div>
</div>

<footer style="padding: 30px; text-align: center; color: #666; font-size: 12px; border-top: 1px solid #1a1a1a;">
    HM Café Admin Panel &copy; <?= date("Y") ?> All Rights Reserved
</footer>

<script>
    function openAddModal() {
        document.getElementById('addModal').classList.add('active');
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }
    function openEditModal(prod) {
        document.getElementById('edit_product_id').value = prod.product_id;
        document.getElementById('edit_product_name').value = prod.product_name;
        document.getElementById('edit_category').value = prod.category;
        document.getElementById('edit_price').value = parseFloat(prod.price).toFixed(2);
        document.getElementById('edit_description').value = prod.description;
        document.getElementById('edit_image').value = prod.image || 'menu-iced-coffee.png';
        document.getElementById('edit_is_active').checked = parseInt(prod.is_active, 10) === 1;
        document.getElementById('editModal').classList.add('active');
    }

    // Close modal when clicking outside card
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('admin-modal')) {
            e.target.classList.remove('active');
        }
    });
</script>
</body>
</html>
