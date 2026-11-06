-- ========================================================
-- Production Migration Schema: Grocery Billing Management System
-- Database Name: grocery_billing_db
-- Engine: MySQL 5.7+ / 8.0+ / MariaDB (InnoDB)
-- ========================================================

CREATE DATABASE IF NOT EXISTS `grocery_billing_db` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `grocery_billing_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `stock_history`;
DROP TABLE IF EXISTS `sale_items`;
DROP TABLE IF EXISTS `sales`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `admin`;
SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- 1. admin
-- --------------------------------------------------------
CREATE TABLE `admin` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'admin',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. categories
-- --------------------------------------------------------
CREATE TABLE `categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. products
-- --------------------------------------------------------
CREATE TABLE `products` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_name` VARCHAR(150) NOT NULL,
  `category_id` INT(11) NOT NULL,
  `product_code` VARCHAR(50) NOT NULL UNIQUE,
  `purchase_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `available_stock` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `unit` VARCHAR(20) NOT NULL DEFAULT 'Piece',
  `low_stock_threshold` DECIMAL(10,2) NOT NULL DEFAULT 10.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_product_category` (`category_id`),
  INDEX `idx_product_code` (`product_code`),
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. customers
-- --------------------------------------------------------
CREATE TABLE `customers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `customer_name` VARCHAR(100) NOT NULL,
  `mobile_number` VARCHAR(20) NOT NULL UNIQUE,
  `email` VARCHAR(100) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_customer_mobile` (`mobile_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. sales
-- --------------------------------------------------------
CREATE TABLE `sales` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `invoice_no` VARCHAR(50) NOT NULL UNIQUE,
  `customer_id` INT(11) DEFAULT NULL,
  `customer_name` VARCHAR(100) NOT NULL DEFAULT 'Walk-in Customer',
  `customer_phone` VARCHAR(20) DEFAULT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tax_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `grand_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_mode` ENUM('Cash', 'UPI', 'Card') NOT NULL DEFAULT 'Cash',
  `payment_status` VARCHAR(20) NOT NULL DEFAULT 'Paid',
  `notes` TEXT DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_sales_invoice` (`invoice_no`),
  INDEX `idx_sales_customer` (`customer_id`),
  INDEX `idx_sales_created_at` (`created_at`),
  CONSTRAINT `fk_sales_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sales_admin` FOREIGN KEY (`created_by`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. sale_items
-- --------------------------------------------------------
CREATE TABLE `sale_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `sale_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `product_code` VARCHAR(50) NOT NULL,
  `unit` VARCHAR(20) NOT NULL DEFAULT 'Piece',
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_sale_items_sale` (`sale_id`),
  INDEX `idx_sale_items_product` (`product_id`),
  CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sale_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. stock_history
-- --------------------------------------------------------
CREATE TABLE `stock_history` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `type` ENUM('IN', 'OUT', 'ADJUSTMENT', 'SALE') NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL,
  `previous_stock` DECIMAL(10,2) NOT NULL,
  `new_stock` DECIMAL(10,2) NOT NULL,
  `reference_id` VARCHAR(50) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_stock_product` (`product_id`),
  INDEX `idx_stock_created_at` (`created_at`),
  CONSTRAINT `fk_stock_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_stock_admin` FOREIGN KEY (`created_by`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Initial Production Seed: Default Admin & Default Walk-in Customer
-- Admin user: admin / admin123
-- --------------------------------------------------------
INSERT INTO `admin` (`id`, `username`, `password`, `full_name`, `email`, `role`) VALUES
(1, 'admin', '$2y$12$ZSBNLJ9j7pexl0DopzNltePNpi3CnfPzpnL0QidoYNOpcDTvXRm52', 'Store Manager', 'admin@freshmart.com', 'admin')
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

INSERT INTO `customers` (`id`, `customer_name`, `mobile_number`, `email`, `address`) VALUES
(1, 'Walk-in Customer', '9999999999', 'walkin@freshmart.com', 'Store Counter')
ON DUPLICATE KEY UPDATE `customer_name` = VALUES(`customer_name`);

-- Core Grocery Categories
INSERT INTO `categories` (`id`, `category_name`, `description`) VALUES
(1, 'Fruits & Vegetables', 'Fresh farm produce, fruits and vegetables'),
(2, 'Dairy & Breakfast', 'Milk, butter, bread, curd, paneer and eggs'),
(3, 'Grains, Pulses & Flours', 'Rice, wheat flour, lentils, cooking staples'),
(4, 'Oils & Ghee', 'Cooking oils, pure cow ghee, mustard oil'),
(5, 'Snacks & Beverages', 'Biscuits, chips, tea, coffee, juices'),
(6, 'Spices & Seasonings', 'Whole and powdered spices, cooking masala'),
(7, 'Household & Personal Care', 'Detergents, soaps, toiletries and cleaners')
ON DUPLICATE KEY UPDATE `category_name` = VALUES(`category_name`);
