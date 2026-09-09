<?php
mysqli_report(MYSQLI_REPORT_OFF);

$host = "127.0.0.1";   // Use IP directly to avoid IPv6/DNS timeout
$username = "root";
$password = "";
$database = "hm_cafe";
$port = 3306;

// Step 1: Connect to MySQL server and ensure the database exists
$serverConn = @new mysqli();
$serverConn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
@$serverConn->real_connect($host, $username, $password, "", $port);
if ($serverConn && !$serverConn->connect_errno) {
    $serverConn->query("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $serverConn->close();
}

// Step 2: Connect to the hm_cafe database
$conn = new mysqli();
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
@$conn->real_connect($host, $username, $password, $database, $port);

if ($conn->connect_errno) {
    http_response_code(503);
    die("<!DOCTYPE html><html><head><title>HM Café - Maintenance</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#0d0d0d;color:#fff;}
    .box{text-align:center;padding:40px;} h1{color:#c8a96e;} p{color:#aaa;}</style></head>
    <body><div class='box'><h1>☕ HM Café</h1><h2>We'll be right back!</h2>
    <p>Our database is temporarily unavailable. Please try again in a moment.</p></div></body></html>");
}

$conn->set_charset("utf8mb4");

// Step 3: Directly ensure all tables and column migrations exist
function ensure_hm_cafe_tables(mysqli $conn): void {
    // 1. Users Table
    $conn->query("
        CREATE TABLE IF NOT EXISTS `users` (
            `user_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `email` VARCHAR(120) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `role` ENUM('customer','admin') NOT NULL DEFAULT 'customer',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Migration fallbacks for users table
    $colCheck = $conn->query("SHOW COLUMNS FROM `users` LIKE 'email'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE `users` ADD COLUMN `email` VARCHAR(120) NULL UNIQUE AFTER `username`");
        $conn->query("UPDATE `users` SET `email` = CONCAT(`username`, '@hmcafe.local') WHERE `email` IS NULL OR `email` = ''");
        $conn->query("ALTER TABLE `users` MODIFY COLUMN `email` VARCHAR(120) NOT NULL");
    }

    $colCheck2 = $conn->query("SHOW COLUMNS FROM `users` LIKE 'role'");
    if ($colCheck2 && $colCheck2->num_rows === 0) {
        $conn->query("ALTER TABLE `users` ADD COLUMN `role` ENUM('customer','admin') NOT NULL DEFAULT 'customer' AFTER `password`");
    }

    // 2. Products Table
    $conn->query("
        CREATE TABLE IF NOT EXISTS `products` (
            `product_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `product_name` VARCHAR(100) NOT NULL UNIQUE,
            `description` VARCHAR(255) NOT NULL,
            `price` DECIMAL(10,2) NOT NULL,
            `image` VARCHAR(255) NOT NULL,
            `category` VARCHAR(50) NOT NULL DEFAULT 'Coffee',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Migration fallback for products category column
    $prodCatCheck = $conn->query("SHOW COLUMNS FROM `products` LIKE 'category'");
    if ($prodCatCheck && $prodCatCheck->num_rows === 0) {
        $conn->query("ALTER TABLE `products` ADD COLUMN `category` VARCHAR(50) NOT NULL DEFAULT 'Coffee' AFTER `image`");
        $conn->query("UPDATE `products` SET `category` = 'Coffee' WHERE `product_name` LIKE '%Coffee%' OR `product_name` LIKE '%Espresso%'");
        $conn->query("UPDATE `products` SET `category` = 'Non-Coffee' WHERE `product_name` LIKE '%Matcha%' OR `product_name` LIKE '%Tea%'");
        $conn->query("UPDATE `products` SET `category` = 'Pastries' WHERE `product_name` LIKE '%Cake%' OR `product_name` LIKE '%Bread%' OR `product_name` LIKE '%Pastry%'");
        $conn->query("UPDATE `products` SET `category` = 'Meals' WHERE `product_name` LIKE '%Pasta%' OR `product_name` LIKE '%Meal%'");
    }

    // 3. Orders Table
    $conn->query("
        CREATE TABLE IF NOT EXISTS `orders` (
            `order_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `product_id` INT UNSIGNED NOT NULL,
            `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
            `dining_option` VARCHAR(50) NOT NULL DEFAULT 'Dine-in',
            `payment_method` VARCHAR(50) NOT NULL DEFAULT 'Cash on Counter',
            `status` ENUM('Pending','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_orders_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Migration fallback for orders dining_option and payment_method
    $ordDiningCheck = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'dining_option'");
    if ($ordDiningCheck && $ordDiningCheck->num_rows === 0) {
        $conn->query("ALTER TABLE `orders` ADD COLUMN `dining_option` VARCHAR(50) NOT NULL DEFAULT 'Dine-in' AFTER `quantity`");
    }

    $ordPayCheck = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'payment_method'");
    if ($ordPayCheck && $ordPayCheck->num_rows === 0) {
        $conn->query("ALTER TABLE `orders` ADD COLUMN `payment_method` VARCHAR(50) NOT NULL DEFAULT 'Cash on Counter' AFTER `dining_option`");
    }

    // 4. Contact Messages Table
    $conn->query("
        CREATE TABLE IF NOT EXISTS `contact_messages` (
            `message_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(120) NOT NULL,
            `subject` VARCHAR(150) NOT NULL,
            `message` TEXT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 5. Reviews Table
    $conn->query("
        CREATE TABLE IF NOT EXISTS `reviews` (
            `review_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `rating` INT NOT NULL DEFAULT 5,
            `comment` TEXT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 6. Appointments & Bookings Table
    $conn->query("
        CREATE TABLE IF NOT EXISTS `appointments` (
            `appointment_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `guests` INT NOT NULL DEFAULT 2,
            `booking_date` DATE NOT NULL,
            `booking_time` TIME NOT NULL,
            `notes` VARCHAR(255) NULL,
            `status` ENUM('Pending','Confirmed','Cancelled') NOT NULL DEFAULT 'Pending',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_appointments_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 7. Seed default products if empty
    $prodCheck = $conn->query("SELECT COUNT(*) AS total FROM `products`");
    if ($prodCheck && (int)$prodCheck->fetch_assoc()["total"] === 0) {
        $conn->query("
            INSERT INTO `products` (`product_name`, `description`, `price`, `image`, `category`, `is_active`) VALUES
            ('Iced Coffee', 'Smooth and creamy coffee over ice.', 120.00, 'menu-iced-coffee.png', 'Coffee', 1),
            ('Matcha', 'Rich matcha with a creamy twist.', 130.00, 'menu-matcha.png', 'Non-Coffee', 1),
            ('Chocolate Cake', 'Moist, rich, and perfectly sweet.', 110.00, 'menu-cake.png', 'Pastries', 1),
            ('Creamy Pasta', 'Creamy, savory, and satisfying.', 150.00, 'menu-pasta.png', 'Meals', 1)
        ");
    }

    // 8. Seed Default Admin Account (admin / admin123)
    $adminCheck = $conn->query("SELECT user_id FROM `users` WHERE `username` = 'admin' LIMIT 1");
    if ($adminCheck && $adminCheck->num_rows === 0) {
        $defaultAdminPassword = password_hash("admin123", PASSWORD_DEFAULT);
        $conn->query("
            INSERT INTO `users` (`username`, `email`, `password`, `role`)
            VALUES ('admin', 'admin@hmcafe.local', '{$defaultAdminPassword}', 'admin')
        ");
    }
}

// Automatically ensure tables and column migrations exist
ensure_hm_cafe_tables($conn);
