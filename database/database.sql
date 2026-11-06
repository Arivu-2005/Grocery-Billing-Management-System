-- ======================================================
-- Grocery Billing Management System Database Schema
-- Database Name: grocery_billing_db
-- Technology: MySQL / MariaDB (InnoDB Engine)
-- ======================================================

CREATE DATABASE IF NOT EXISTS `grocery_billing_db` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `grocery_billing_db`;

-- Set SQL Modes and Timezone
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
-- Table 1: admin
-- Admin users for authentication and store management
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
-- Table 2: categories
-- Product categories for grocery inventory
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
-- Table 3: products
-- Inventory items with pricing, unit, SKU, and threshold
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
-- Table 4: customers
-- Registered store customers with contact details
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
-- Table 5: sales
-- Billing headers containing invoice details and totals
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
-- Table 6: sale_items
-- Individual products billed in an invoice
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
-- Table 7: stock_history
-- Audit log for stock movements (Purchases, Adjustments, Sales)
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

-- ========================================================
-- SEED DATA
-- Default Admin: admin / admin123 (bcrypt hash)
-- ========================================================
INSERT INTO `admin` (`id`, `username`, `password`, `full_name`, `email`, `role`) VALUES
(1, 'admin', '$2y$12$ZSBNLJ9j7pexl0DopzNltePNpi3CnfPzpnL0QidoYNOpcDTvXRm52', 'Store Manager', 'admin@freshmart.com', 'admin');

-- --------------------------------------------------------
-- Seed Categories
-- --------------------------------------------------------
INSERT INTO `categories` (`id`, `category_name`, `description`) VALUES
(1, 'Fruits & Vegetables', 'Fresh farm produce, fresh fruits and organic vegetables'),
(2, 'Dairy & Breakfast', 'Milk, butter, cheese, bread, cereals and eggs'),
(3, 'Grains, Pulses & Flours', 'Basmati rice, wheat flour, lentils and cooking staples'),
(4, 'Oils & Ghee', 'Edible cooking oils, pure cow ghee and mustard oil'),
(5, 'Snacks & Beverages', 'Chips, biscuits, soft drinks, green tea and coffee'),
(6, 'Spices & Seasonings', 'Turmeric, chili powder, garam masala and culinary herbs'),
(7, 'Household & Personal Care', 'Soaps, detergents, dishwash liquids and cleaners');

-- --------------------------------------------------------
-- Seed Products
-- --------------------------------------------------------
INSERT INTO `products` (`id`, `product_name`, `category_id`, `product_code`, `purchase_price`, `selling_price`, `available_stock`, `unit`, `low_stock_threshold`) VALUES
(1, 'Royal Basmati Rice 5kg', 3, 'GR-RICE-001', 380.00, 475.00, 45.00, 'Pack', 10.00),
(2, 'Aashirvaad Shudh Chakki Atta 5kg', 3, 'GR-ATTA-002', 210.00, 255.00, 30.00, 'Pack', 8.00),
(3, 'Tata Sampann Toor Dal 1kg', 3, 'GR-DAL-003', 130.00, 160.00, 28.00, 'KG', 10.00),
(4, 'Fortune Sunlite Refined Oil 1L', 4, 'GR-OIL-004', 115.00, 140.00, 50.00, 'Litre', 15.00),
(5, 'Amul Pure Cow Ghee 1L', 4, 'GR-GHEE-005', 520.00, 620.00, 18.00, 'Litre', 5.00),
(6, 'Amul Taaza Homogenised Toned Milk 1L', 2, 'DR-MILK-006', 58.00, 72.00, 40.00, 'Litre', 12.00),
(7, 'Amul Salted Butter 500g', 2, 'DR-BTR-007', 220.00, 275.00, 22.00, 'Piece', 6.00),
(8, 'Britannia 100% Whole Wheat Bread', 2, 'DR-BRD-008', 35.00, 48.00, 25.00, 'Piece', 5.00),
(9, 'Shimla Fresh Royal Gala Apple 1kg', 1, 'FV-APL-009', 140.00, 190.00, 35.00, 'KG', 10.00),
(10, 'Fresh Farm Red Onions 1kg', 1, 'FV-ONI-010', 25.00, 38.00, 80.00, 'KG', 20.00),
(11, 'Fresh Organic Potatoes 1kg', 1, 'FV-POT-011', 20.00, 32.00, 6.00, 'KG', 15.00), -- Low Stock!
(12, 'Tata Salt Vacuum Evaporated 1kg', 6, 'SP-SLT-012', 20.00, 28.00, 60.00, 'KG', 15.00),
(13, 'Catch Turmeric Powder 200g', 6, 'SP-TRM-013', 42.00, 58.00, 35.00, 'Piece', 8.00),
(14, 'Lay\'s Classic Salted Potato Chips 50g', 5, 'SN-LAYS-014', 15.00, 20.00, 4.00, 'Piece', 10.00), -- Low Stock!
(15, 'Red Label Natural Care Tea 500g', 5, 'SN-TEA-015', 240.00, 290.00, 15.00, 'Pack', 5.00),
(16, 'Surf Excel Quick Wash Detergent 1kg', 7, 'HC-DET-016', 155.00, 195.00, 25.00, 'KG', 8.00),
(17, 'Dettol Original Liquid Handwash Refill 750ml', 7, 'HC-HW-017', 95.00, 125.00, 20.00, 'Piece', 5.00),
(18, 'Cadbury Dairy Milk Silk 60g', 5, 'SN-CHOC-018', 65.00, 85.00, 0.00, 'Piece', 5.00); -- Out of Stock!

-- --------------------------------------------------------
-- Seed Customers
-- --------------------------------------------------------
INSERT INTO `customers` (`id`, `customer_name`, `mobile_number`, `email`, `address`) VALUES
(1, 'Walk-in Customer', '9999999999', 'walkin@freshmart.com', 'Store Counter'),
(2, 'Rajesh Sharma', '9876543210', 'rajesh.sharma@example.com', 'Flat 402, Green Valley Apartments, MG Road'),
(3, 'Priya Venkatesh', '9845012345', 'priya.v@example.com', 'No 12, 4th Cross, Indiranagar'),
(4, 'Amit Patel', '9723456789', 'amit.patel@example.com', 'B-14, Shanti Nagar, Sector 5'),
(5, 'Sneha Mukherjee', '9123456780', 'sneha.m@example.com', '32/B, Lake View Residency, Park Street');

-- --------------------------------------------------------
-- Seed Initial Stock History for Products
-- --------------------------------------------------------
INSERT INTO `stock_history` (`product_id`, `type`, `quantity`, `previous_stock`, `new_stock`, `reference_id`, `notes`, `created_by`) VALUES
(1, 'IN', 45.00, 0.00, 45.00, 'PO-2026-001', 'Initial inventory intake', 1),
(2, 'IN', 30.00, 0.00, 30.00, 'PO-2026-001', 'Initial inventory intake', 1),
(3, 'IN', 28.00, 0.00, 28.00, 'PO-2026-001', 'Initial inventory intake', 1),
(4, 'IN', 50.00, 0.00, 50.00, 'PO-2026-001', 'Initial inventory intake', 1),
(5, 'IN', 18.00, 0.00, 18.00, 'PO-2026-001', 'Initial inventory intake', 1),
(6, 'IN', 40.00, 0.00, 40.00, 'PO-2026-001', 'Initial inventory intake', 1),
(11, 'IN', 6.00, 0.00, 6.00, 'PO-2026-001', 'Initial stock low threshold test', 1),
(14, 'IN', 4.00, 0.00, 4.00, 'PO-2026-001', 'Initial stock low threshold test', 1);

-- --------------------------------------------------------
-- Seed Initial Sample Sales & Invoices
-- --------------------------------------------------------
INSERT INTO `sales` (`id`, `invoice_no`, `customer_id`, `customer_name`, `customer_phone`, `subtotal`, `discount_percentage`, `discount_amount`, `tax_percentage`, `tax_amount`, `grand_total`, `payment_mode`, `payment_status`, `notes`, `created_by`, `created_at`) VALUES
(1, 'INV-20261101-0001', 2, 'Rajesh Sharma', '9876543210', 970.00, 5.00, 48.50, 5.00, 46.08, 967.58, 'UPI', 'Paid', 'Quick checkout via PhonePe', 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2, 'INV-20261103-0002', 3, 'Priya Venkatesh', '9845012345', 1335.00, 0.00, 0.00, 5.00, 66.75, 1401.75, 'Card', 'Paid', 'HDFC Credit Card', 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(3, 'INV-20261105-0003', 1, 'Walk-in Customer', '9999999999', 435.00, 0.00, 0.00, 0.00, 0.00, 435.00, 'Cash', 'Paid', 'Exact cash received', 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(4, 'INV-20261106-0004', 4, 'Amit Patel', '9723456789', 730.00, 10.00, 73.00, 5.00, 32.85, 689.85, 'Cash', 'Paid', 'Loyalty discount applied', 1, NOW());

-- Sale Items for Seed Sales
INSERT INTO `sale_items` (`sale_id`, `product_id`, `product_name`, `product_code`, `unit`, `unit_price`, `quantity`, `total_price`) VALUES
(1, 1, 'Royal Basmati Rice 5kg', 'GR-RICE-001', 'Pack', 475.00, 1.00, 475.00),
(1, 2, 'Aashirvaad Shudh Chakki Atta 5kg', 'GR-ATTA-002', 'Pack', 255.00, 1.00, 255.00),
(1, 4, 'Fortune Sunlite Refined Oil 1L', 'GR-OIL-004', 'Litre', 140.00, 1.00, 140.00),
(1, 8, 'Britannia 100% Whole Wheat Bread', 'DR-BRD-008', 'Piece', 48.00, 2.00, 96.00),

(2, 5, 'Amul Pure Cow Ghee 1L', 'GR-GHEE-005', 'Litre', 620.00, 1.00, 620.00),
(2, 7, 'Amul Salted Butter 500g', 'DR-BTR-007', 'Piece', 275.00, 1.00, 275.00),
(2, 15, 'Red Label Natural Care Tea 500g', 'SN-TEA-015', 'Pack', 290.00, 1.00, 290.00),
(2, 16, 'Surf Excel Quick Wash Detergent 1kg', 'HC-DET-016', 'KG', 195.00, 1.00, 195.00),

(3, 9, 'Shimla Fresh Royal Gala Apple 1kg', 'FV-APL-009', 'KG', 190.00, 1.50, 285.00),
(3, 10, 'Fresh Farm Red Onions 1kg', 'FV-ONI-010', 'KG', 38.00, 2.00, 76.00),
(3, 6, 'Amul Taaza Homogenised Toned Milk 1L', 'DR-MILK-006', 'Litre', 72.00, 1.00, 72.00),

(4, 1, 'Royal Basmati Rice 5kg', 'GR-RICE-001', 'Pack', 475.00, 1.00, 475.00),
(4, 2, 'Aashirvaad Shudh Chakki Atta 5kg', 'GR-ATTA-002', 'Pack', 255.00, 1.00, 255.00);
