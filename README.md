# FreshMart – Grocery Billing Management System: A Plain-Language Guide

*A beginner-friendly rewrite of the original FreshMart project document. Nothing from the original has been removed. Items marked **Editor's note** point out gaps or unclear points in the original; no details have been invented to fill them.*

## 1. Introduction and Purpose

**FreshMart** is a web application that helps a supermarket or retail store run its daily work. It does three main jobs:

1. **Billing** – ringing up customers at the counter (this is called a *Point of Sale* or *POS* system).
2. **Inventory management** – tracking how much stock of each product the store has.
3. **Reporting** – showing the owner how the business is performing through dashboards, charts and reports.

The original document describes it as a *commercial-grade, responsive, full-stack* system for supermarkets and retail stores. In simple terms:

- **Commercial-grade** – built to be used in a real business (not just a classroom demo).
- **Responsive** – the screens adjust to different screen sizes.
- **Full-stack** – it includes everything: the screens users see (frontend), the logic that runs on the server (backend) and the place where data is stored (database).

> **Editor's note:** "Commercial-grade" is the original document's own description. The document does not provide evidence such as test results or customer deployments to support it.

**Who is this guide for?** Anyone who needs to understand, install, test or use the project, including people with no programming background.

## 2. Technology Used (the "Tech Stack")

A *tech stack* is the set of tools a software product is built with. FreshMart uses the following.

| Layer | Technology | What it means in simple words |
| --- | --- | --- |
| Frontend | HTML5, CSS3, Bootstrap 5, JavaScript | The part users see and click. HTML builds the page structure, CSS styles it, Bootstrap provides ready-made layouts and buttons, and JavaScript makes pages interactive. |
| Backend | Core PHP (OOP & PDO) | The server-side program that does the work. "Core PHP" means plain PHP without a large framework. OOP (Object-Oriented Programming) is a way of organizing code into reusable blocks. PDO (PHP Data Objects) is PHP's safe, standard way to talk to a database. |
| Database | MySQL (InnoDB) | Where all information (products, customers, bills) is stored. InnoDB is the MySQL storage engine that supports *transactions* (explained in Section 5). |
| Charts | Chart.js | A JavaScript library that draws charts and graphs. |
| Server | Apache (XAMPP) | The software that runs the website. XAMPP is a free package that bundles Apache (web server), MySQL and PHP on one computer. |
| Architecture | Full-stack web application | The app runs in a web browser and is backed by a server and database. |

## 3. Complete Explanation of Each Feature Area

### 3.1 POS Billing System

This is the screen used at the checkout counter. It lets staff:

- **Find products by barcode or by search.** A barcode is the striped code printed on a product; scanning it finds the product quickly.
- **Filter products by category** (for example, "Fruits" or "Dairy") to narrow the list.
- **Enter decimal quantities.** This is needed for goods sold by weight, such as 1.5 kg of rice or 0.25 kg of spices.
- **Get automatic calculations** for the subtotal, discount and GST (explained below).
- **Accept payment by Cash, UPI or Card.** UPI (Unified Payments Interface) is the instant mobile payment system widely used in India.
- **Register a customer quickly** during billing, without leaving the screen.

**GST** stands for *Goods and Services Tax*, a tax added to the price of goods and services.

> **Editor's note:** The original does not say how GST rates are set (per product or fixed), how discounts are entered (percentage or amount), or whether discounts apply to a single item or the whole bill.

### 3.2 Invoice Management

An *invoice* is the bill given to the customer. FreshMart supports:

- **A4 tax invoice** – a full-page, formal bill suitable for records and tax purposes.
- **80mm thermal receipt** – the narrow receipt printed by small shop receipt printers (80 mm wide paper).
- **GST breakdown** – the bill shows the tax amount separately.
- **Print-friendly layout** – the page is designed to look right on paper.

### 3.3 Inventory Management

*Inventory* means the goods the store holds. The system provides:

- **Real-time stock tracking** – quantities update as sales and stock changes happen.
- **Stock IN, OUT and ADJUSTMENT** – three kinds of stock movement:
  - **IN:** stock increases (for example, a new delivery arrives).
  - **OUT:** stock decreases (for example, goods leave other than by a normal sale).
  - **ADJUSTMENT:** a correction, such as fixing a count after a physical stock check.
- **Low-stock alerts** – warnings when a product is running low.
- **Negative stock prevention** – the system will not let stock fall below zero.
- **Stock movement history** – a log of every change, so you can see what happened and when.

> **Editor's note:** The original does not define the exact meaning of "OUT" or the quantity that counts as "low stock". The explanations above are the usual meanings in inventory work and should be confirmed against the actual system.

### 3.4 Product Management

Staff can **add, view, edit and delete products**. Each product record includes:

- **SKU** (Stock Keeping Unit) – an internal code that uniquely identifies the product.
- **Barcode** – the scannable code.
- **Purchase price** – what the store pays to buy the item.
- **Selling price** – what the customer pays.
- **Profit margin** – calculated automatically from the two prices.

*Illustration only:* if an item costs the store 40 and sells for 50, the profit is 10, which is 20% of the cost (or 20% of the selling price when measured as a margin on sales). The original does not state which formula the system uses.

### 3.5 Category Management

Categories are groups of products (such as Beverages or Vegetables). The system allows staff to:

- Create and manage categories.
- Add a description to each category.
- Rely on **product assignment validation** – the system checks that a product is correctly linked to a category.

> **Editor's note:** The original does not explain what happens when you try to delete a category that still has products. This is a common rule to check during testing.

### 3.6 Customer Management

- **Customer registration** – store a customer's details.
- **Unique mobile number** – no two customers can share the same mobile number, which prevents duplicates.
- **Purchase history** – a list of what each customer has bought.
- **Lifetime purchase value** – the total amount a customer has spent over time.

### 3.7 Dashboard and Analytics

The dashboard is the home screen for the manager. It shows:

- Total products and total categories.
- Total customers and total invoices.
- Daily and monthly revenue (money earned from sales).
- Low-stock alerts.
- **Weekly sales charts.**
- **Top-selling categories.**

### 3.8 Sales Reports

- **Daily, weekly and monthly reports.**
- **Custom date filtering** – choose any start and end date.
- **Payment method breakdown** – how much came from Cash, UPI and Card.
- **Top 5 selling products.**
- **CSV export** – download report data as a *Comma-Separated Values* file that opens in Excel or similar tools.

### 3.9 Security Features

The original lists these protections (each is explained in Section 4):

- PDO prepared statements.
- Password hashing.
- Secure PHP sessions.
- CSRF protection.
- XSS prevention.
- Database transactions.

## 4. Important Concepts and Definitions

| Concept | Plain-language meaning | How FreshMart uses it |
| --- | --- | --- |
| **Prepared statements (PDO)** | Database questions are sent separately from the user's input, so typed text cannot be mistaken for database commands. This blocks an attack called *SQL injection*. | All database queries use them. |
| **Password hashing** | Passwords are scrambled with a one-way method so the real password is never stored. | Uses PHP's `password_hash()` to store and `password_verify()` to check passwords at login. |
| **Session** | A way for the server to remember that you are logged in as you move between pages. | Uses secure PHP sessions. |
| **Session ID regeneration** | A fresh session identifier is issued at login so an attacker cannot reuse an old one. | Uses `session_regenerate_id(true)`. |
| **CSRF (Cross-Site Request Forgery)** | An attack where a malicious site tricks a logged-in user's browser into sending unwanted requests. | Forms carry a secret token that the server checks. |
| **XSS (Cross-Site Scripting)** | An attack where harmful script code is inserted into a web page. | Output is escaped with `htmlspecialchars()` so it displays as text, not code. |
| **Database transaction / ACID** | A group of database steps that either **all succeed or all fail**. ACID stands for Atomicity, Consistency, Isolation, Durability. | Used for billing and stock deduction so a bill and its stock reduction always stay in step. |
| **Role** | The level of access a user has. | The default login is a Store Manager / Administrator. |

## 5. Processes and Step-by-Step Explanations

### 5.1 System requirements

| Requirement | Version |
| --- | --- |
| Operating system | Windows 10 or 11 |
| Web server | Apache (XAMPP) |
| PHP | 7.4 to 8.3 |
| Database | MySQL 5.7 or later |
| Browser | Chrome, Edge or Firefox |

### 5.2 Installation and setup

**Step 1 – Place the project files.** Copy the project folder to:

`C:\xampp\htdocs\GroceryBillingSystem`

**Step 2 – Start the services.** Open the **XAMPP Control Panel** and start both **Apache** (the web server) and **MySQL** (the database).

**Step 3 – Set up the database.** Use either option:

- **Option A (seed page):** Open `http://localhost/GroceryBillingSystem/database/seed.php` in your browser and click **Create Database & Run Seed**. "Seed" means filling a new database with its starting data.
- **Option B (manual import):** Import the file `database/database.sql` using **phpMyAdmin**, a browser-based tool for managing MySQL.

**Step 4 – Run the application.** Open `http://localhost/GroceryBillingSystem/`.

### 5.3 Logging in

| Field | Value |
| --- | --- |
| Username | admin |
| Password | admin123 |
| Role | Store Manager / Administrator |
| Login page | /login.php |

> **Editor's note:** These are *default* credentials, which are publicly known. The original does not say whether the password should be changed. Changing it before real use is strongly advisable, but the document does not describe how.

### 5.4 How a sale works (typical flow)

The original does not give a numbered billing procedure, but based on the listed features a sale generally follows this order:

1. Log in and open the billing screen.
2. Scan a barcode or search for each product, adjusting quantities (decimals allowed).
3. Optionally register the customer.
4. Review the automatic subtotal, discount and GST.
5. Choose Cash, UPI or Card.
6. Save the bill; stock is reduced automatically inside one database transaction.
7. Print an A4 invoice or an 80mm receipt.

### 5.5 Testing checklist

Use this list to confirm everything works after installation:

1. Admin login and logout.
2. Dashboard metrics and charts display correctly.
3. Product create, read, update, delete (CRUD).
4. Category CRUD operations.
5. Inventory stock updates.
6. Barcode-based billing.
7. Decimal quantity calculation.
8. GST and discount calculation.
9. Cash, UPI and Card payment selection.
10. A4 and thermal invoice printing.
11. Customer purchase history.
12. Sales reports and CSV export.
13. Database transaction and security validation.

*CRUD* means Create, Read, Update, Delete – the four basic data actions.

## 6. Project Structure Explained

The project is organized into folders by purpose. This is the structure given in the original, with a short explanation of each item.

| Path | Purpose |
| --- | --- |
| `config/database.php` | Settings for connecting to the database. |
| `includes/auth.php` | Login and access-checking logic. |
| `includes/header.php`, `sidebar.php`, `footer.php` | Shared page parts reused on every screen. |
| `assets/css/style.css` | Custom styling (colors, layout). |
| `assets/js/script.js` | Custom JavaScript behavior. |
| `assets/images/` | Folder for images. |
| `admin/dashboard.php` | Dashboard screen. |
| `admin/products.php` | Product management. |
| `admin/categories.php` | Category management. |
| `admin/inventory.php` | Stock management. |
| `admin/billing.php` | POS billing screen. |
| `admin/invoice.php` | Invoice generation and printing. |
| `admin/customers.php` | Customer management. |
| `admin/sales.php` | Sales records. |
| `admin/reports.php` | Reports and CSV export. |
| `database/database.sql` | Ready-made database structure for import. |
| `database/seed.php` | Setup page that creates and fills the database. |
| `index.php` | Application entry point. |
| `login.php`, `logout.php` | Sign in and sign out. |
| `README.md` | The project description file. |

> **Editor's note:** The file purposes above are inferred from their names. The original lists the files but does not describe each one. It also does not explain the difference between `sales.php` and `reports.php`.

## 7. Theme and Color Palette

The visual design uses a green, grocery-friendly palette.

| Element | Color | Code |
| --- | --- | --- |
| Primary | Forest Green | #15803D |
| Secondary | Dark Green | #14532D |
| Background | Light Slate | #F8FAFC |
| Cards | White | #FFFFFF |
| Text | Dark Charcoal | #1E293B |
| Accent | Lime Green | #22C55E |

The codes (such as #15803D) are *hex color codes* that computers use to identify exact colors.

## 8. Examples and Practical Applications

These examples are **illustrative** and use made-up numbers; they are not taken from the original document.

**Example 1 – Selling by weight.** A customer buys 1.5 kg of apples at 120 per kg. Because decimal quantities are supported, the line total is 1.5 × 120 = 180.

**Example 2 – Stock safety.** Only 2 packets of butter are left, and a cashier tries to sell 3. Because negative stock is prevented, the system refuses the sale instead of showing −1 in stock.

**Example 3 – Why transactions matter.** While saving a bill, the power fails after the bill is recorded but before stock is reduced. Without transactions, records would disagree. With transactions, the incomplete save is cancelled entirely.

**Example 4 – Owner's review.** At month-end the manager opens Reports, picks custom dates, checks the payment method breakdown and top 5 products, then downloads a CSV file for accounting.

## 9. Important Points to Remember

- FreshMart covers billing, inventory, products, categories, customers, dashboards and reports in one system.
- It is designed for Windows with XAMPP, PHP 7.4–8.3 and MySQL 5.7+.
- Start **both Apache and MySQL** before opening the application.
- The database can be created from `seed.php` or imported from `database.sql`.
- The default login is `admin` / `admin123`.
- Billing and stock deduction use database transactions to keep data consistent.
- Security measures include prepared statements, password hashing, CSRF tokens, output escaping and session protection.

## 10. Gaps, Unclear Points and Inconsistencies in the Original

The following were found while reviewing the document. None have been "fixed" with invented details.

1. **Stray line:** The phrase "Project structure documented" appears after the login table and does not belong to any section.
2. **Role versus users:** A role of "Store Manager / Administrator" is shown, but the document does not say whether other users or roles (for example, cashier) exist.
3. **Two setup methods:** `seed.php` and `database.sql` are both offered, but the document does not say whether they produce identical results or whether sample data is included.
4. **GST and discount rules:** Rates, entry method and calculation formulas are not specified.
5. **Profit margin formula:** Not stated.
6. **Low-stock threshold:** The quantity that triggers an alert is not defined.
7. **Missing operational details:** The document does not mention returns/refunds, backups, multiple users, version numbers for Bootstrap or Chart.js, or software license terms.
8. **Platform scope:** Only Windows 10/11 is listed. The document does not say whether other systems are unsupported or simply untested.
9. **Default credentials:** The document does not address changing the default password.
10. **Unsupported claim:** "Commercial-grade" is stated without supporting evidence.

## 11. Conclusion

FreshMart is a PHP and MySQL web application that brings a store's billing, stock control, customer records and sales reporting into one system. It is straightforward to install locally using XAMPP, includes a testing checklist, and applies several standard security practices. For real-world use, readers should clarify the gaps listed in Section 10 — especially tax and discount rules, user roles and the default password — before relying on it.

*Project: FreshMart – Grocery Billing Management System. © 2026 FreshMart Supermarket. All rights reserved.*

## 12. Glossary of Difficult Terms

| Term | Meaning |
| --- | --- |
| **ACID** | Atomicity, Consistency, Isolation, Durability – rules that keep database transactions reliable. |
| **Apache** | Software that serves web pages to browsers. |
| **Barcode** | A scannable code that identifies a product. |
| **Bootstrap** | A toolkit of ready-made design components for web pages. |
| **Chart.js** | A tool for drawing charts in web pages. |
| **CRUD** | Create, Read, Update, Delete. |
| **CSRF** | Cross-Site Request Forgery, an attack that tricks a user's browser into unwanted actions. |
| **CSV** | Comma-Separated Values, a simple file format for table data. |
| **Dashboard** | A summary screen showing key business numbers. |
| **Frontend / Backend** | The visible part of an app / the server-side logic behind it. |
| **GST** | Goods and Services Tax. |
| **Hashing** | One-way scrambling of data such as passwords. |
| **InnoDB** | A MySQL storage engine that supports transactions. |
| **Inventory** | The goods a store holds for sale. |
| **MySQL** | A popular database system. |
| **OOP** | Object-Oriented Programming. |
| **PDO** | PHP Data Objects, PHP's database access layer. |
| **phpMyAdmin** | A browser tool for managing MySQL databases. |
| **POS** | Point of Sale, the billing counter system. |
| **Seed** | Starting data loaded into a new database. |
| **Session** | The server's memory of a logged-in user across pages. |
| **SKU** | Stock Keeping Unit, a unique internal product code. |
| **Thermal receipt** | A receipt printed with heat on narrow paper rolls. |
| **Transaction** | A set of database steps that all succeed or all fail together. |
| **UPI** | Unified Payments Interface, an instant mobile payment system. |
| **XAMPP** | A bundle of Apache, MySQL and PHP for local use. |
| **XSS** | Cross-Site Scripting, an attack that injects harmful scripts into pages. |
