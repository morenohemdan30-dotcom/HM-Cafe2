-- ============================================================
-- HM CAFÉ DATABASE INITIALIZATION SCHEMA (mysql.sql)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `hm_cafe` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hm_cafe`;

-- 1. USERS TABLE
CREATE TABLE IF NOT EXISTS `users` (
    `user_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(120) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. PRODUCTS TABLE
CREATE TABLE IF NOT EXISTS `products` (
    `product_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_name` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `image` VARCHAR(255) NOT NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'Coffee',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ORDERS TABLE
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. CONTACT MESSAGES TABLE
CREATE TABLE IF NOT EXISTS `contact_messages` (
    `message_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(120) NOT NULL,
    `subject` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. REVIEWS TABLE
CREATE TABLE IF NOT EXISTS `reviews` (
    `review_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `rating` INT NOT NULL DEFAULT 5,
    `comment` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. APPOINTMENTS & RESERVATIONS TABLE
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. SEED PRODUCTS
INSERT INTO `products` (`product_name`, `description`, `price`, `image`, `category`, `is_active`) VALUES
('Iced Coffee', 'Smooth and creamy coffee over ice.', 120.00, 'menu-iced-coffee.png', 'Coffee', 1),
('Matcha', 'Rich matcha with a creamy twist.', 130.00, 'menu-matcha.png', 'Non-Coffee', 1),
('Chocolate Cake', 'Moist, rich, and perfectly sweet.', 110.00, 'menu-cake.png', 'Pastries', 1),
('Creamy Pasta', 'Creamy, savory, and satisfying.', 150.00, 'menu-pasta.png', 'Meals', 1)
ON DUPLICATE KEY UPDATE `description`=VALUES(`description`), `price`=VALUES(`price`), `image`=VALUES(`image`), `is_active`=1;

-- 8. DEFAULT ADMINISTRATOR (admin / admin123)
INSERT INTO `users` (`username`, `email`, `password`, `role`)
VALUES ('admin', 'admin@hmcafe.local', '$2y$10$ZvU509wvzch.oVnea737iuzG8DKt1KHdRl2HOLkz3L0yCOevQZVp.', 'admin')
ON DUPLICATE KEY UPDATE `role`='admin', `email`='admin@hmcafe.local';
