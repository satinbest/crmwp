# Changelog

All notable changes to the CRMWP platform will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.1.0] - 2026-10-06

### Added
- **Redesigned Modern WooCommerce Management Dashboard**: Re-architected the main CRM dashboard into an operational store command center featuring live sales and order metrics, operational status cards (pending, processing, completed, low stock), interactive daily sales trend chart, order status distribution bar, customer widgets (recent and top customers), inventory low-stock alerts, and recent store activity feeds.
- **Centralized & Extensible Section Help System**: Added contextual `HelpButton` and popovers to all primary widgets and KPI sections backed by a centralized repository (`resources/js/config/helpContent.js`). Popovers dynamically calculate screen bounds, render via body teleportation to avoid parent container overflow clipping, and handle RTL alignment, Escape key, and click-outside dismissal.
- **Secure Developer Donation & Redesigned Modern Modal (Donate)**: Integrated a polite, minimalist donation button in the top navigation header adjacent to notifications. Completely redesigned the Donate modal into a premium, trustworthy SaaS experience featuring a dedicated Heart badge container, standard Vazirmatn typography without `font-mono`, 4-digit grouped Persian card number (`۶۲۱۹   ۸۶۱۹   ۳۱۹۶   ۵۴۰۳`), interactive copy button with `copy` / `copy-success` states and local toast feedback, clickable Email and GitHub repository links, and complete Light/Dark mode contrast calibration. Connected to a secure backend endpoint (`GET /api/v1/system/donate`) that reads configuration (`DONATE_RECIPIENT_NAME`, `DONATE_CARD_NUMBER`, `DONATE_EMAIL`, `DONATE_GITHUB`) from the environment, completely eliminating hardcoded financial secrets from frontend bundles and Git history.
- **Enhanced Iconsax SVG Icon Set**: Added native local SVG vector definitions for `heart`, `heart-add`, `card`, `wallet`, `copy`, `copy-success`, `github`, `tick-circle`, `profile-circle`, `calendar-2`, `clock-1`, `bag-2`, and `money-recive`.

### Changed & Improved
- **Header Layout & Alignment Overhaul**: Reorganized header control hierarchy (`[Donate] [Notifications] [User/Avatar]`), normalized icon sizes, padding, and vertical centering across desktop and mobile screens.
- **Removed Intrusive Vertical Divider**: Eliminated the unwanted vertical border beside the user profile section in `AppLayout.vue`.
- **Avatar & Font Isolation**: Guaranteed 100% internal avatar handling with local Iconsax `profile-circle` fallback, completely removing any possibility of external Gravatar requests or font CDN calls.
- **Live Jalali Calendar & Clock**: Integrated real-time Jalali date display and live clock synchronized to the store/application timezone (`Asia/Tehran`).
- **Object Cache TCP & Unix Socket Reliability**: Enhanced Memcached connection testing to perform full set/get/verify/delete lifecycles, accurately handle Unix domain sockets, and present genuine Hit Ratio calculations (showing "بدون داده" for zero hits/misses).

---

## [1.0.1] - 2026-10-05

### Fixed
- **Customer Order Count & Total Spent Aggregation**: Corrected customer metrics computation to accurately aggregate order count and total spent from orders when direct customer stats are zero or WooCommerce synchronization returns partial metadata.
- **Memcached TCP & Unix Socket Support**: Added robust support for both TCP/IP (`host:port`) and Unix domain sockets (`/var/run/memcached/memcached.sock`) in `MemcachedAdapter` with proper fallback and socket timeout handling.
- **Product Image Content Security Policy (CSP)**: Updated CSP `img-src` header in `public/index.php` and `Csp.php` to allow external WooCommerce store media domains (`https:`, `data:`, `blob:`) without triggering browser console violations.
- **HPOS (High-Performance Order Storage) Detection**: Refined HPOS detection logic in WooCommerce adapter and system health checks to reliably query WooCommerce custom order tables (`wc_orders`) and settings across diverse WooCommerce versions (8.x through 11.x).
- **Persian (Jalali) Date Formatting**: Fixed date parser and Jalali conversion edge cases across customer profiles, order history, and activity timestamps to ensure correct leap year calculation and localized month names.
- **Installer Version Inconsistency**: Replaced hardcoded version fallbacks with centralized `CRM_APP_VERSION` so the web wizard and generated environment lock files display the active version.

### Improved
- **Production Release Packaging**: Enhanced `build_release.php` to generate clean, verified standalone archives (`CRM-Production-Release-1.0.1.zip`), automatically calculate SHA-256 checksums, and duplicate production artifacts directly into the workspace root.
- **Asset Bundle Cleanup**: Streamlined the frontend build pipeline by clearing obsolete hashed asset files and maintaining only the active production chunks and local WOFF2 font files.
- **Version Consistency**: Established `config/app.php` (`CRM_APP_VERSION`) as the single central source of truth for the application version across the backend API (`/health`, `/system/about`), installer, frontend UI, package definitions, and release manifests.
- **System Health & Info API**: Added `app_version` directly to the `/api/v1/health` payload for standardized observability and monitoring tools.
- **Release Documentation & Metadata**: Synchronized `RELEASE-MANIFEST.md`, `RELEASE_MANIFEST.json`, `README.md`, and technical documentation to reflect the 1.0.1 production stable release.

---

## [1.0.0] - 2026-09-29

### Initial Production Release
- Complete WooCommerce Integration with support for Products, Categories, Orders, Customers, and Variations.
- Independent multi-store architecture with strict store isolation.
- Advanced Bulk Operations engine with preview, execution, undo, and batch chunking.
- Granular Role-Based Access Control (RBAC) with 6 pre-configured system roles and custom role management.
- Real-time Notifications and Activity Center with customizable event preferences.
- Workflow Automations engine with condition trees and execution history.
- 100% self-contained frontend with local Vazirmatn fonts and Iconsax vector system (zero external CDN dependencies).
- 4-step interactive Web Installer at `/install` with automated environment checks and installation locks.
- Secure token encryption (AES-256-CBC with HMAC verification), CSRF protection, and rate limiting.
