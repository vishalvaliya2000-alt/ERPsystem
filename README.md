# ERPSaaS — Multi-Tenant SaaS ERP for Indian Manufacturing & Global Export

[![Laravel](https://img.shields.io/badge/Laravel-11%20%2F%2012-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.4%20%2F%208.5-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![stancl/tenancy](https://img.shields.io/badge/Multi--Tenancy-stancl%2Ftenancy%20v3-4F46E5?style=for-the-badge)](https://tenancyforlaravel.com)
[![MySQL](https://img.shields.io/badge/Database-MySQL%208.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![TailwindCSS](https://img.shields.io/badge/Tailwind-CSS%20v4-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![PWA](https://img.shields.io/badge/PWA-Offline%20Ready-5A0FC8?style=for-the-badge&logo=pwa&logoColor=white)](https://web.dev/progressive-web-apps/)
[![Pest](https://img.shields.io/badge/Tests-Pest%20PHP-blueviolet?style=for-the-badge)](https://pestphp.com)

**ERPSaaS** is an enterprise-grade, multi-tenant Cloud ERP engineered specifically for Indian manufacturing enterprises, chemical and textile processors, engineering firms, agricultural exporters, and wholesale distributors. 

Architected with **database-per-tenant isolation** powered by `stancl/tenancy` v3, ERPSaaS guarantees complete cryptographic and physical data segregation between customer workspaces, statutory Indian GST compliance (HSN/SAC, TCS u/s 206C(1H), TDS u/s 194Q, NIC E-Way bills), automated double-entry accounting, PWA offline draft synchronization, and multi-entity firm management.

---

## 🌟 Architectural Highlights

- **Database-Per-Tenant Isolation**: Central database (`saas_erp_central`) manages tenant registries, subdomains, central admin users, subscription packages, and tenant creation logs. Each tenant operates inside an entirely isolated database (`saas_erp_tenant_{tenant_id}`) containing their own users, companies, firms, ledgers, inventory, dispatches, and invoices.
- **Dual-Domain Routing Architecture**:
  - **Central Landing & Administration**: Hosted on the primary domain (e.g. `app.erpsaas.local` or `localhost`), providing workspace discovery, tenant self-registration, central billing, and super-admin command center.
  - **Tenant Workspaces**: Scoped to subdomains (e.g. `acme.erpsaas.local`), dynamically resolved by `InitializeTenancyBySubdomain` middleware.
- **Super-Admin Central Command Center & Impersonation**: Central portal allows platform administrators to oversee active tenants, monitor storage usage, activate/suspend workspaces, and securely impersonate tenant sessions via signed short-lived single-use cryptographic tokens.
- **Multi-Company & Multi-Firm Hierarchy**: A single tenant workspace can host multiple holding companies, each owning multiple registered legal operating entities (firms) with distinct GSTINs, PANs, bank accounts, and digital signatures.
- **Sequential Document Numbering**: Strictly enforced per-firm document counters with financial year prefixes (e.g. `INV/26-27/0001`, `SO/26-27/0001`, `QUO/26-27/0001`) preventing gaps and concurrency collision.
- **Indian Statutory Compliance Engine**:
  - Automated Intra-state (CGST + SGST) vs Inter-state (IGST) determination based on Place of Supply (POS) and Firm State Code.
  - Granular HSN/SAC summary aggregation on all tax invoices.
  - Section 206C(1H) TCS computation when cumulative financial year customer turnover crosses ₹50 Lakhs.
  - Section 194Q TDS ledger adjustments.
  - NIC E-Way Bill JSON generation conforming to government schemas with 12-digit mock verification.
- **Automated Communication Hub**: Integrated WhatsApp notifications with deep links for order confirmation, dispatch LR tracking, and overdue payment alerts.
- **PWA Offline Mode & Cross-Device Sync**: Progressive Web App with service worker caching, network-first fallbacks, client-side draft queues in `localStorage` for field sales orders, leads, and expenses, and automated background sync upon reconnection.
- **Enterprise Reporting & Batch Traceability**: Balance Sheet ($\text{Assets} = \text{Liabilities} + \text{Equity}$), Bill-wise Profitability, Party-wise Margin Contribution, Accounts Receivable Aging (Current, 1-30, 31-60, 61-90, 90+ days), and end-to-end Batch Traceability (Raw Material Lot $\rightarrow$ Production Batch $\rightarrow$ Quality Inspection $\rightarrow$ Dispatch LR $\rightarrow$ Tax Invoice $\rightarrow$ E-Way Bill).

---

## 🏗️ Technology Stack

| Layer | Technology |
| :--- | :--- |
| **Backend Framework** | Laravel 11 / 12 (PHP 8.4+ / PHP 8.5) |
| **Multi-Tenancy** | `stancl/tenancy` v3 (Database-per-tenant with tenancy lifecycle hooks) |
| **Central Database** | MySQL 8.0 (`saas_erp_central`) |
| **Tenant Databases** | MySQL 8.0 (`saas_erp_tenant_{tenant_id}`) |
| **Frontend UI** | Blade Templates + Alpine.js v3 + Tailwind CSS v4 |
| **PWA & Offline** | Web App Manifest, Service Worker (`sw.js`), Draft Queue Engine |
| **Document Generation** | Barryvdh Laravel DomPDF (Vector-sharp printable invoices & reports) |
| **Spreadsheet Exports** | Maatwebsite Laravel Excel / PhpSpreadsheet |
| **Authentication & RBAC** | Session-based multi-guard authentication + Spatie Laravel-Permission |
| **Testing Harness** | Pest PHP + PHPUnit with Strict Production Database Safety Guard |
| **Code Quality** | Laravel Pint + PHPStan (Level 6+) |

---

## 📂 Project Directory Structure

```text
├── app/
│   ├── Actions/Fortify/               # Auth workflows & security
│   ├── Console/Commands/              # Scheduled CLI commands & maintenance
│   ├── Exports/                       # Multi-sheet Excel exports (Maatwebsite)
│   │   ├── ArAgingExport.php
│   │   ├── BalanceSheetExport.php
│   │   ├── BatchTraceExport.php
│   │   ├── BillProfitExport.php
│   │   └── PartyProfitExport.php
│   ├── Helpers/                       # INR currency formatting & utility helpers
│   ├── Http/
│   │   ├── Controllers/               # Tenant-scoped RESTful domain controllers
│   │   │   ├── AccountingController.php
│   │   │   ├── AuthController.php
│   │   │   ├── Central/               # Central portal & super-admin controllers
│   │   │   │   ├── LandingController.php
│   │   │   │   ├── RegistrationController.php
│   │   │   │   └── SuperAdminController.php
│   │   │   ├── CompanyController.php
│   │   │   ├── CustomerController.php
│   │   │   ├── DispatchController.php
│   │   │   ├── EWayBillController.php
│   │   │   ├── FirmController.php
│   │   │   ├── InvoiceController.php
│   │   │   ├── LeadController.php
│   │   │   ├── QuotationController.php
│   │   │   ├── ReportController.php
│   │   │   ├── SalesOrderController.php
│   │   │   └── SyncController.php     # PWA offline draft synchronization engine
│   │   └── Middleware/                # Tenancy & RBAC middleware
│   ├── Models/                        # Domain Eloquent models (Tenant and Central)
│   │   ├── Central/                   # CentralTenant, CentralUser, ImpersonationToken
│   │   ├── Company.php
│   │   ├── Firm.php
│   │   ├── Invoice.php
│   │   ├── InvoiceItem.php
│   │   ├── LedgerAccount.php
│   │   ├── LedgerEntry.php
│   │   ├── SalesOrder.php
│   │   └── ...
│   └── Services/                      # Domain services (EWayBill, WhatsApp, Tax, Report, Sync)
├── config/
│   ├── tenancy.php                    # stancl/tenancy v3 configuration
│   ├── database.php                   # Central, tenant, and test database connections
│   └── ...
├── database/
│   ├── migrations/                    # Central database migrations
│   ├── migrations/tenant/             # Tenant workspace database migrations
│   └── seeders/                       # Central and Tenant database seeders
├── public/
│   ├── manifest.json                  # PWA Web App Manifest
│   ├── sw.js                          # Service Worker for offline fallback & asset caching
│   └── js/
│       └── pwa-offline-sync.js        # Offline draft queue & background synchronization
├── resources/
│   └── views/
│       ├── central/                   # Central landing, signup, super-admin dashboard
│       ├── layouts/                   # Responsive Bento UI app layouts
│       ├── reports/                   # Interactive financial & operational reports
│       ├── offline.blade.php          # PWA offline fallback UI
│       └── ...
├── routes/
│   ├── central.php                    # Central domain routes (landing, auth, admin)
│   ├── tenant.php                     # Subdomain tenant routes (ERP operations & sync API)
│   └── console.php                    # Scheduled maintenance tasks
└── tests/
    ├── Feature/                       # 9 comprehensive multi-tenant feature test suites
    │   ├── TenantProvisioningTest.php
    │   ├── TenantIsolationTest.php
    │   ├── DepartmentAndRbacTest.php
    │   ├── CompanyAndFirmTest.php
    │   ├── CoreSalesAndInvoicingTest.php
    │   ├── AccountingAndComplianceTest.php
    │   ├── ComplianceAndAutomationsTest.php
    │   ├── FinancialAndOperationalReportsTest.php
    │   └── PwaAndCentralPlatformTest.php
    └── TestCase.php                   # Automated test harness with prohibited DB safety guard
```

---

## ⚙️ Environment Configuration

In `.env`, configure the central database and dual domain routing:

```env
APP_NAME=ERPSaaS
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://app.erpsaas.local

# Central and Tenant Domain Configuration
APP_CENTRAL_DOMAIN=app.erpsaas.local
APP_TENANT_DOMAIN=erpsaas.local

# Central Database Connection
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=saas_erp_central
DB_USERNAME=root
DB_PASSWORD=

# Multi-Tenant Database Prefix
TENANT_DB_PREFIX=saas_erp_tenant_
```

> [!NOTE]
> For local development with subdomain routing, map the following entries in your hosts file (`C:\Windows\System32\drivers\etc\hosts` or `/etc/hosts`):
> ```text
> 127.0.0.1 app.erpsaas.local
> 127.0.0.1 erpsaas.local
> 127.0.0.1 acme.erpsaas.local
> 127.0.0.1 demo.erpsaas.local
> ```

---

## 🚀 Installation & Provisioning

1. **Clone repository & install dependencies**:
   ```bash
   git clone https://github.com/vishalvaliya2000-alt/ERPSaaS.git
   cd ERPSaaS
   composer install
   npm install
   ```

2. **Configure environment & generate key**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Migrate central database**:
   ```bash
   php artisan migrate --database=mysql
   ```

4. **Seed central database with initial super-admin**:
   ```bash
   php artisan db:seed --class=CentralDatabaseSeeder
   ```

5. **Compile frontend assets**:
   ```bash
   npm run build
   ```

6. **Serve application**:
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```

---

## 🛡️ Database Safety Guard in Tests

To guarantee zero risk of data loss or accidental mutation of production ERP databases during testing, `tests/TestCase.php` enforces a strict runtime barrier:

```php
$prohibitedDatabases = ['saas_erp', 'saas_erp_central'];

if ($connection === 'mysql' && in_array(strtolower($database), $prohibitedDatabases, true)) {
    throw new RuntimeException(
        "SAFETY ERROR: Tests are attempting to run against production database [{$database}]."
    );
}
```

All automated tests execute exclusively against the dedicated test database (`saas_erp_test`), and dynamically create isolated temporary tenant databases (`saas_erp_test_tenant_*`) that are automatically cleaned up when tests complete.

---

## 🧪 Automated Test Suite

Run the complete 9-suite multi-tenant feature test battery with Pest PHP:

```bash
php vendor/bin/pest tests/Feature/TenantProvisioningTest.php \
                    tests/Feature/TenantIsolationTest.php \
                    tests/Feature/DepartmentAndRbacTest.php \
                    tests/Feature/CompanyAndFirmTest.php \
                    tests/Feature/CoreSalesAndInvoicingTest.php \
                    tests/Feature/AccountingAndComplianceTest.php \
                    tests/Feature/ComplianceAndAutomationsTest.php \
                    tests/Feature/FinancialAndOperationalReportsTest.php \
                    tests/Feature/PwaAndCentralPlatformTest.php
```

To run static analysis and code style verification:

```bash
# Laravel Pint
php vendor/bin/pint --test

# PHPStan Level 6+
php vendor/bin/phpstan analyse --memory-limit=2G
```

---

## 📱 PWA Offline & Cross-Device Sync

ERPSaaS includes a native Progressive Web App (PWA) client engine:
- **Offline Draft Queue**: Sales reps on factory floors or remote sites can create **Sales Orders**, **Leads**, and **Expenses** without internet connectivity. Drafts are safely indexed in `localStorage` under `erpsaas_offline_drafts`.
- **Automatic Background Synchronization**: When connectivity returns, `pwa-offline-sync.js` sends queued drafts via `POST /api/sync/offline-drafts`.
- **Server Pulse**: A periodic lightweight pulse ping (`GET /api/sync/pulse`) monitors server status, active firm counts, and pending order tallies.
- **Visual Connection Indicators**: Real-time toast notifications and sticky status banners alert the user whenever their device goes offline or completes a batch sync.

---

## 📄 License & Intellectual Property

Copyright © 2026 ERPSaaS Platform. All rights reserved. Commercial distribution subject to SaaS software license agreements.
