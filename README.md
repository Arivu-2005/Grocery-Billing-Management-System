# FreshMart - Grocery Billing Management System

A commercial-grade, responsive, full-stack Web POS (Point of Sale) and Inventory Management System designed specifically for modern grocery supermarkets and retail stores.

Developed using **Core PHP (OOP & PDO)**, **MySQL (InnoDB with Foreign Keys & Transactions)**, **Bootstrap 5**, and **Chart.js**.

---

## 🌟 Key Features

### 1. Modern POS Billing Terminal
- **Fast Barcode & Keyword Search**: Add products instantly by scanning barcode/SKU and hitting Enter, or searching product title.
- **Category Filter Pills**: Fast 1-click filtering across grocery departments (Dairy, Grains, Produce, Snacks, Spices, Household).
- **Fractional/Decimal Quantity Support**: Seamlessly bill weighted items (e.g. 1.5 KG apples, 0.25 KG cumin seeds).
- **Dynamic Calculation**: Instant real-time calculation of subtotal, percentage discounts, and GST tax (0%, 5%, 12%, 18%).
- **Multi-Channel Payments**: Supports **Cash**, **UPI** (with instant QR code modal scan), and **Card**.
- **Quick Customer Registration**: Add walk-in customers to the database without leaving the POS register screen.
- **Server-Side Transaction Safety**: Recalculates all prices and quantities on the server, verifies live stock before billing, and executes atomic stock deduction in a database transaction (`BEGIN TRANSACTION` ... `COMMIT`).

### 2. Dual-Format Invoice Generation & Printing
- **A4 Tax Invoice Layout**: Corporate invoice format with store details, GSTIN, customer details, tax breakdown, and terms & conditions.
- **80mm Thermal Receipt Layout**: Compact receipt layout for standard thermal POS receipt printers.
- **Print Optimization**: Clean `@media print` rules hide sidebars, buttons, and navigation for crisp printing.

### 3. Inventory & Stock Control
- **Real-Time Stock Tracking**: Stock levels auto-decrement immediately upon successful sale.
- **Multi-Type Stock Movements**:
  - `IN`: Supplier inward restock.
  - `OUT`: Damage, expiry, or return to vendor.
  - `ADJUSTMENT`: Manual audit count override.
- **Negative Stock Prevention**: Strictly prohibits selling or deducting more than current stock.
- **Audit History**: Complete `stock_history` log recording previous stock, new stock, movement type, operator, and reference invoice numbers.
- **Stock Status Badges**: Visual indicators for *In Stock*, *Low Stock*, and *Out of Stock*.

### 4. Product Catalog Management
- Full CRUD operations with unique SKU/Barcode constraint.
- Cost Price vs Selling Price with automatic profit margin calculation.
- Category relationship with cascade protection (cannot delete categories in active use).

### 5. Category Management
- Categorize grocery items with descriptions.
- Deletion safeguard: blocks deletion if products are currently assigned to the category.

### 6. Customer Management
- Complete customer profiles with mobile number uniqueness.
- Lifetime order history tracking and aggregate lifetime purchase valuation.

### 7. Interactive Dashboard & Analytics
- **Live Metric Cards**: Total Products, Total Categories, Total Customers, Total Invoices, Today's Revenue, Monthly Revenue, Low Stock Alerts.
- **Weekly Revenue Trend Chart**: 7-day sales line chart using Chart.js.
- **Top Categories Chart**: Sales volume distribution doughnut chart.
- **Recent Billing Transactions**: Quick preview of recently completed customer invoices.

### 8. Financial Reports & CSV Export
- Time filtering: Today, Yesterday, Last 7 Days, This Month, Last Month, and Custom Date Range.
- Payment method breakdown (Cash vs UPI vs Card).
- Top 5 performing grocery items by units and revenue.
- **1-Click CSV Export**: Download sales data in CSV format for Excel/accounting.

### 9. High-Security Architecture
- PDO prepared statements everywhere to prevent SQL Injection.
- Password hashing using `password_hash()` and verification via `password_verify()`.
- Session fixation protection via `session_regenerate_id(true)`.
- Anti-CSRF token verification on all state-changing POST requests.
- Input sanitization and XSS prevention with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

---

## 🎨 Theme & Color Palette

- **Primary Color**: Forest Green (`#15803D`)
- **Secondary Color**: Dark Green (`#14532D`)
- **Background**: Light Slate Gray (`#F8FAFC`)
- **Cards**: Pure White (`#FFFFFF`)
- **Text**: Dark Charcoal (`#1E293B`)
- **Accent**: Fresh Lime Green (`#22C55E` / `#84CC16`)

---

## 📁 Project Directory Structure

```
GroceryBillingSystem/
│
├── config/
│   └── database.php           # PDO connection, base URL calculator, store constants
│
├── includes/
│   ├── auth.php               # Session security, CSRF helpers, auth gates, currency formatters
│   ├── header.php             # HTML head, topbar, live clock, stock alerts dropdown
│   ├── sidebar.php            # Responsive navigation drawer
│   └── footer.php             # HTML close, JS scripts, modal initializers
│
├── assets/
│   ├── css/
│   │   └── style.css          # Professional green theme, responsive layouts, print CSS
│   ├── js/
│   │   └── script.js          # Live clock, search filters, confirm delete helpers
│   └── images/                # Product and brand assets
│
├── admin/
│   ├── dashboard.php          # 7 key metrics, Chart.js trends, recent sales, low stock alerts
│   ├── products.php           # Product catalog CRUD, unique SKU, price margin modal
│   ├── categories.php         # Category CRUD with deletion constraint
│   ├── inventory.php          # Stock IN/OUT/ADJUSTMENT, audit history log
│   ├── billing.php            # Interactive POS billing register, barcode scanning, UPI modal
│   ├── invoice.php            # A4 & 80mm thermal receipt generator with print view
│   ├── customers.php          # Customer directory and purchase history ledger
│   ├── sales.php              # Invoices list with date/payment filters
│   └── reports.php            # Analytics, payment mode charts, and 1-click CSV export
│
├── database/
│   ├── database.sql           # Full schema (7 tables, foreign keys, indexes) + grocery seed data
│   └── seed.php               # Web-based 1-click database installer & seeder tool
│
├── index.php                  # Entry point (redirects to dashboard or login)
├── login.php                  # Secure admin sign-in with CSRF and password_verify()
├── logout.php                 # Safe session destruction
└── README.md                  # Complete documentation
```

---

## 💻 System Requirements & Prerequisites

- **OS**: Windows 10 or 11
- **Web Server**: Apache (via XAMPP)
- **PHP**: Core PHP 7.4, 8.0, 8.1, 8.2, or 8.3
- **Database**: MySQL 5.7+ or MariaDB 10.4+
- **Browser**: Modern Chrome, Edge, Firefox, or Safari

---

## 🚀 Setup & Installation Instructions (XAMPP on Windows)

### Step 1: Place Files in XAMPP `htdocs`
1. Copy or move this project folder into your XAMPP web directory:
   ```
   C:\xampp\htdocs\GroceryBillingSystem
   ```
   *(Or keep it in your workspace and configure a virtual host or symlink).*

### Step 2: Start Apache and MySQL
1. Launch **XAMPP Control Panel**.
2. Click **Start** for **Apache**.
3. Click **Start** for **MySQL**.

### Step 3: Install Database (Two Options)

#### Option A (Recommended: 1-Click Web Installer):
1. Open your web browser and navigate to:
   ```
   http://localhost/GroceryBillingSystem/database/seed.php
   ```
2. Click the green button **"Create Database & Run Seed"**.
3. The script will automatically create `grocery_billing_db`, generate all 7 tables with foreign keys and indexes, insert realistic grocery catalog items, categories, customers, and seed the default admin account.

#### Option B (Manual Import via phpMyAdmin):
1. Open phpMyAdmin at:
   ```
   http://localhost/phpmyadmin/
   ```
2. Click **Databases** > Create database named: `grocery_billing_db` with collation `utf8mb4_unicode_ci`.
3. Select `grocery_billing_db` and go to the **Import** tab.
4. Choose the file `database/database.sql` from the project folder and click **Import**.

---

## 🔑 Default Administrator Credentials

| Field | Credential |
|---|---|
| **Username** | `admin` |
| **Password** | `admin123` |
| **Role** | Store Manager / Administrator |
| **Login URL** | `http://localhost/GroceryBillingSystem/login.php` |

---

## 🧪 Testing Checklist & Verification Guide

Follow this step-by-step checklist to test the entire application:

- [x] **Authentication**:
  - Visit `http://localhost/GroceryBillingSystem/login.php`.
  - Try logging in with invalid credentials (verify error message).
  - Log in with `admin` / `admin123` (verify redirect to Dashboard).
  - Test the **Sign Out** button in the top right / sidebar.
- [x] **Dashboard**:
  - Verify all 7 metric cards load data from MySQL.
  - Verify the Weekly Sales Trend line chart and Category doughnut chart render properly.
  - Check that low-stock items appear in the alert list.
- [x] **Category Management**:
  - Add a new category (e.g., "Frozen Desserts").
  - Edit the category description.
  - Attempt to delete a category assigned to products (verify that deletion is prevented with an informative error).
- [x] **Product Management**:
  - Add a new grocery product with unique SKU, purchase price, selling price, unit, and threshold.
  - Attempt to add a duplicate SKU (verify unique constraint validation).
  - Click the **Eye icon** on a product row to view the detail modal and profit margin calculation.
- [x] **Inventory Management**:
  - Open **Inventory** > click **Adjust / Inward Stock**.
  - Add inward stock (`IN`) for an item and verify the stock count increases.
  - Try to deduct (`OUT`) more items than available (verify negative stock restriction).
  - Switch to the **Stock Movement Audit History** tab to view the timestamped audit trail.
- [x] **POS Billing Terminal**:
  - Navigate to **POS Billing**.
  - Filter by category pills or type in the search bar.
  - Click products to add to cart or enter an SKU and press Enter.
  - Adjust quantity using `+` / `-` or enter decimal values (e.g., `1.5` KG).
  - Apply a discount percentage (e.g. `5%`) and select GST rate (`5%`).
  - Select an existing customer or click **New** to quick-add a customer.
  - Choose payment mode:
    - If **UPI**, verify the QR code payment prompt appears.
    - If **Cash**, click **Complete & Print Bill**.
  - Verify that the transaction commits, stock decrements automatically in the database, and you are redirected to the invoice preview.
- [x] **Invoice & Printing**:
  - Toggle between **Standard A4** and **80mm Thermal Receipt**.
  - Click **Print Invoice** (verify clean print styling).
- [x] **Sales History & Reports**:
  - Go to **Sales & Invoices** to see your newly generated invoice.
  - Open **Reports & Analytics** > select "This Month".
  - Click **Export to CSV** to verify downloaded CSV report.

---

## 🔒 Security Summary

1. **Prepared Statements**: All database operations use PDO prepared statements with parameter binding to prevent SQL Injection.
2. **Password Security**: Passwords are saved as bcrypt hashes using PHP's native `password_hash()` and authenticated with `password_verify()`.
3. **CSRF Tokens**: Form submissions and AJAX checkout endpoints validate cryptographically secure random session tokens.
4. **Transaction Integrity**: The billing and inventory engines use ACID transactions (`beginTransaction()`, `commit()`, `rollBack()`) to ensure consistent stock balances.
5. **Output Escaping**: All user and database data rendered in HTML templates is safely escaped using `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

---

© 2026 FreshMart Supermarket. All rights reserved.
#   G r o c e r y - B i l l i n g - M a n a g e m e n t - S y s t e m  
 