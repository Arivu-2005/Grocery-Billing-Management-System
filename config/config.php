<?php
/**
 * Application & Brand Configuration
 * GROCERY BILLING SYSTEM - HISTORY MAKER TECH
 */

// Application Branding
defined('APP_NAME') or define('APP_NAME', 'GROCERY BILLING SYSTEM');
defined('DEVELOPED_BY') or define('DEVELOPED_BY', 'HISTORY MAKER TECH');
defined('APP_VERSION') or define('APP_VERSION', '2.0.0');

// Store Profile Constants
defined('STORE_NAME') or define('STORE_NAME', 'FreshMart Supermarket');
defined('STORE_TAGLINE') or define('STORE_TAGLINE', 'Quality Groceries & Daily Essentials');
defined('STORE_ADDRESS') or define('STORE_ADDRESS', '#45 Market Plaza, MG Road, Bengaluru, Karnataka 560001');
defined('STORE_PHONE') or define('STORE_PHONE', '+91 98765 43210');
defined('STORE_EMAIL') or define('STORE_EMAIL', 'billing@freshmart.com');
defined('STORE_GSTIN') or define('STORE_GSTIN', '29AAAAA0000A1Z5');
defined('CURRENCY_SYMBOL') or define('CURRENCY_SYMBOL', '₹');
defined('CURRENCY_CODE') or define('CURRENCY_CODE', 'INR');
defined('DEFAULT_TAX_RATE') or define('DEFAULT_TAX_RATE', 5.00); // 5% GST

// Session & Security Settings
defined('SESSION_LIFETIME') or define('SESSION_LIFETIME', 86400); // 24 hours
