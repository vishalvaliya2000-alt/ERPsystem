<?php

declare(strict_types=1);

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BulkItemUpdateController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EwayBillController;
use App\Http\Controllers\ExcelController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FixedAssetController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductDocumentController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ProductPricingController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\RecycleBinController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SampleController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServiceReminderController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TransporterController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\WhatsAppController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Subdomain Routes ({tenant}.erpsaas.com / {tenant}.localhost)
|--------------------------------------------------------------------------
| These routes execute exclusively in the context of an identified tenant.
| Queries execute against the tenant's dedicated database.
*/

Route::middleware([
    'web',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    // Tenant Guest Authentication
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
        Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
        Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
        Route::get('/two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'create'])->name('two-factor.login');
        Route::post('/two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'store'])->name('two-factor.login.store');
    });

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Authenticated Tenant ERP Workspace
    Route::middleware('auth')->group(function () {

        // User Profile & Security
        Route::get('/profile', [AuthController::class, 'profile'])->name('profile');
        Route::post('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
        Route::post('/profile/password', [AuthController::class, 'updatePassword'])->name('profile.password');

        // 1. Dashboard
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/analytics', [DashboardController::class, 'analytics'])->name('dashboard.analytics');
        Route::post('/dashboard/generate-actions', [DashboardController::class, 'generateActions'])->name('dashboard.generate-actions');
        Route::get('/dashboard/daily-digest', [DashboardController::class, 'dailyDigest'])->name('dashboard.daily-digest');

        // 2. Customers Master (Customer 360)
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/{id}', [CustomerController::class, 'show'])->name('customers.show');
        Route::post('/customers/{id}/update', [CustomerController::class, 'update'])->name('customers.update');
        Route::post('/customers/{id}/delete', [CustomerController::class, 'destroy'])->name('customers.destroy');

        // 3. CRM / Sales Pipeline
        Route::get('/pipeline', [PipelineController::class, 'index'])->name('pipeline.index');
        Route::post('/pipeline', [PipelineController::class, 'store'])->name('pipeline.store');
        Route::post('/pipeline/{id}/stage', [PipelineController::class, 'updateStage'])->name('pipeline.stage');
        Route::post('/pipeline/{id}/activity', [PipelineController::class, 'logActivity'])->name('pipeline.activity');
        Route::post('/pipeline/{id}/convert', [PipelineController::class, 'convertToCustomer'])->name('pipeline.convert');

        // 4. Samples Evaluation
        Route::get('/samples', [SampleController::class, 'index'])->name('samples.index');
        Route::post('/samples', [SampleController::class, 'store'])->name('samples.store');
        Route::post('/samples/{id}/status', [SampleController::class, 'updateStatus'])->name('samples.status');
        Route::post('/samples/{id}/update', [SampleController::class, 'update'])->name('samples.update');
        Route::post('/samples/{id}/delete', [SampleController::class, 'destroy'])->name('samples.destroy');

        // 5. Quotations
        Route::get('/quotations', [QuotationController::class, 'index'])->name('quotations.index');
        Route::post('/quotations', [QuotationController::class, 'store'])->name('quotations.store');
        Route::get('/quotations/{id}', [QuotationController::class, 'show'])->name('quotations.show');
        Route::post('/quotations/{id}/update', [QuotationController::class, 'update'])->name('quotations.update');
        Route::post('/quotations/{id}/delete', [QuotationController::class, 'destroy'])->name('quotations.destroy');
        Route::post('/quotations/{id}/convert', [QuotationController::class, 'convert'])->name('quotations.convert');

        // 6. Products Master & Documents
        Route::get('/api/hsn-lookup', [ProductController::class, 'hsnLookup'])->name('api.hsn.lookup');
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::get('/products/bulk-update', [BulkItemUpdateController::class, 'index'])->name('products.bulk-update.index');
        Route::get('/products/bulk-update/export', [BulkItemUpdateController::class, 'export'])->name('products.bulk-update.export');
        Route::post('/products/bulk-update/preview', [BulkItemUpdateController::class, 'preview'])->name('products.bulk-update.preview');
        Route::post('/products/bulk-update/confirm', [BulkItemUpdateController::class, 'confirm'])->name('products.bulk-update.confirm');
        Route::post('/products/bulk-update/cancel', [BulkItemUpdateController::class, 'cancelPreview'])->name('products.bulk-update.cancel');
        Route::post('/products/bulk-update/grid', [BulkItemUpdateController::class, 'gridUpdate'])->name('products.bulk-update.grid');
        Route::post('/products/bulk-update/grid-update', [BulkItemUpdateController::class, 'gridUpdate'])->name('products.bulk-update.grid-update');

        // Product Pricing Slabs, Party Overrides & Custom Fields
        Route::post('/products/{product}/price-slabs', [ProductPricingController::class, 'storeSlab'])->name('products.price-slabs.store');
        Route::delete('/products/{product}/price-slabs/{slab}', [ProductPricingController::class, 'destroySlab'])->name('products.price-slabs.destroy');
        Route::post('/customers/{customer}/price-overrides', [ProductPricingController::class, 'storePartyOverride'])->name('customers.price-overrides.store');
        Route::delete('/customers/{customer}/price-overrides/{override}', [ProductPricingController::class, 'destroyPartyOverride'])->name('customers.price-overrides.destroy');
        Route::post('/products/custom-fields', [ProductPricingController::class, 'storeCustomField'])->name('products.custom-fields.store');
        Route::delete('/products/custom-fields/{field}', [ProductPricingController::class, 'destroyCustomField'])->name('products.custom-fields.destroy');
        Route::post('/products/{product}/custom-values', [ProductPricingController::class, 'updateCustomValues'])->name('products.custom-values.update');

        Route::get('/products/{id}', [ProductController::class, 'show'])->name('products.show');
        Route::post('/products/{id}/update', [ProductController::class, 'update'])->name('products.update');
        Route::post('/products/{id}/delete', [ProductController::class, 'destroy'])->name('products.destroy');

        // Product Documents & Media Management
        Route::get('/api/products/{id}/documents', [ProductDocumentController::class, 'getDocuments'])->name('products.documents.api');
        Route::post('/products/{id}/photos/upload', [ProductDocumentController::class, 'uploadPhotos'])->name('products.photos.upload');
        Route::post('/products/{id}/documents/upload', [ProductDocumentController::class, 'uploadDocument'])->name('products.documents.upload');
        Route::post('/products/{id}/photos/{docId}/set-primary', [ProductDocumentController::class, 'setPrimaryPhoto'])->name('products.photos.set-primary');
        Route::post('/products/{id}/photos/reorder', [ProductDocumentController::class, 'reorderPhotos'])->name('products.photos.reorder');
        Route::post('/products/{id}/documents/{docId}/toggle-latest', [ProductDocumentController::class, 'toggleLatest'])->name('products.documents.toggle-latest');
        Route::get('/products/{productId}/documents/{docId}/preview', [ProductDocumentController::class, 'preview'])->name('products.documents.preview');
        Route::get('/products/{productId}/documents/{docId}/download', [ProductDocumentController::class, 'download'])->name('products.documents.download');
        Route::get('/products/{productId}/documents/download-all', [ProductDocumentController::class, 'downloadAllZip'])->name('products.documents.download-all');
        Route::post('/products/{productId}/documents/{docId}/delete', [ProductDocumentController::class, 'destroy'])->name('products.documents.destroy');
        Route::delete('/products/{productId}/documents/{docId}', [ProductDocumentController::class, 'destroy'])->name('products.documents.delete');

        // Product Document Sharing
        Route::post('/products/{productId}/documents/share', [ProductDocumentController::class, 'createShare'])->name('products.documents.share.create');
        Route::delete('/products/{productId}/documents/share/{shareId}', [ProductDocumentController::class, 'deleteShare'])->name('products.documents.share.delete');

        // 7. Sales Orders & Revisions
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{id}/revisions', [OrderController::class, 'revisions'])->name('orders.revisions');
        Route::post('/orders/{id}/update', [OrderController::class, 'update'])->name('orders.update');
        Route::post('/orders/{id}/advance', [OrderController::class, 'recordAdvance'])->name('orders.advance');
        Route::post('/orders/{id}/delete', [OrderController::class, 'destroy'])->name('orders.destroy');

        // 8. Commercial Shipments & LR Tracking
        Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');
        Route::post('/shipments', [ShipmentController::class, 'store'])->name('shipments.store');
        Route::get('/shipments/{id}/live-tracking', [ShipmentController::class, 'liveTracking'])->name('shipments.live-tracking');
        Route::get('/api/transporter/tracking', [ShipmentController::class, 'trackConsignmentApi'])->name('shipments.track-api');
        Route::post('/shipments/{id}/update', [ShipmentController::class, 'update'])->name('shipments.update');
        Route::post('/shipments/{id}/status', [ShipmentController::class, 'updateStatus'])->name('shipments.status');
        Route::post('/shipments/{id}/delete', [ShipmentController::class, 'destroy'])->name('shipments.destroy');

        // 9. Invoices & AR Ledger
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::post('/invoices/combine-orders', [InvoiceController::class, 'combineOrders'])->name('invoices.combine-orders');
        Route::get('/invoices/{id}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
        Route::post('/invoices/{id}/update', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::post('/invoices/{id}/delete', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
        Route::post('/invoices/receipt', [InvoiceController::class, 'storeReceipt'])->name('invoices.receipt');

        // Documents
        Route::redirect('/documents', '/')->name('documents.index');
        Route::get('/documents/check-duplicate', [DocumentController::class, 'checkDuplicate'])->name('documents.check-duplicate');
        Route::get('/documents/{id}', [DocumentController::class, 'show'])->name('documents.show');
        Route::get('/documents/{id}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
        Route::get('/documents/{id}/download', [DocumentController::class, 'download'])->name('documents.download');
        Route::post('/documents/{id}/delete', [DocumentController::class, 'destroy'])->name('documents.destroy');

        // 10. Vendors & Suppliers
        Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
        Route::post('/vendors', [VendorController::class, 'store'])->name('vendors.store');
        Route::post('/vendors/{id}/update', [VendorController::class, 'update'])->name('vendors.update');
        Route::post('/vendors/{id}/delete', [VendorController::class, 'destroy'])->name('vendors.destroy');

        // 11. Transporters
        Route::get('/transporters', [TransporterController::class, 'index'])->name('transporters.index');
        Route::post('/transporters', [TransporterController::class, 'store'])->name('transporters.store');
        Route::post('/transporters/{id}/update', [TransporterController::class, 'update'])->name('transporters.update');
        Route::post('/transporters/{id}/delete', [TransporterController::class, 'destroy'])->name('transporters.destroy');

        // 12. Tasks
        Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
        Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::post('/tasks/{id}/complete', [TaskController::class, 'complete'])->name('tasks.complete');

        // 13. Production & Batches
        Route::get('/production', [ProductionController::class, 'index'])->name('production.index');
        Route::post('/production/batch', [ProductionController::class, 'storeBatch'])->name('production.batch.store');

        // 14. Assistant
        Route::get('/assistant', [AssistantController::class, 'index'])->name('assistant.index');
        Route::post('/assistant/query', [AssistantController::class, 'query'])->name('assistant.query');

        // 15. Excel Migration Hub
        Route::get('/excel', [ExcelController::class, 'index'])->name('excel.index');
        Route::get('/excel/export', [ExcelController::class, 'export'])->name('excel.export');

        // 16. Organization, Team & Departments
        Route::get('/organization/settings', [TenantController::class, 'settings'])->name('organization.settings');
        Route::post('/organization/settings', [TenantController::class, 'updateSettings'])->name('organization.settings.update');

        // Team Management
        Route::get('/organization/team', [TeamController::class, 'index'])->name('organization.team');
        Route::post('/organization/team/invite', [TeamController::class, 'invite'])->name('organization.team.invite');
        Route::post('/organization/team/{userId}/update', [TeamController::class, 'update'])->name('organization.team.update');
        Route::post('/organization/team/{userId}/toggle-status', [TeamController::class, 'toggleStatus'])->name('organization.team.toggle-status');
        Route::post('/organization/team/{userId}/remove', [TeamController::class, 'destroy'])->name('organization.team.remove');

        // Department Management
        Route::get('/organization/departments', [DepartmentController::class, 'index'])->name('organization.departments');
        Route::post('/organization/departments/{department}/assign-manager', [DepartmentController::class, 'assignManager'])->name('organization.departments.assign-manager');

        // Security Audit Logs
        Route::get('/organization/audit-logs', [AuditLogController::class, 'index'])->name('organization.audit-logs');

        // 17. Multiple Companies, Firms & Context Switcher
        Route::post('/organization/switch-context', [CompanyController::class, 'switchContext'])->name('organization.switch-context');
        Route::get('/organization/companies', [CompanyController::class, 'index'])->name('organization.companies.index');
        Route::post('/organization/companies', [CompanyController::class, 'storeCompany'])->name('organization.companies.store');
        Route::post('/organization/companies/{company}/update', [CompanyController::class, 'updateCompany'])->name('organization.companies.update');
        Route::post('/organization/companies/{company}/set-default', [CompanyController::class, 'setDefaultCompany'])->name('organization.companies.set-default');
        Route::post('/organization/companies/{company}/toggle-status', [CompanyController::class, 'toggleCompanyStatus'])->name('organization.companies.toggle-status');

        Route::post('/organization/companies/{company}/firms', [CompanyController::class, 'storeFirm'])->name('organization.firms.store');
        Route::post('/organization/firms/{firm}/update', [CompanyController::class, 'updateFirm'])->name('organization.firms.update');
        Route::post('/organization/firms/{firm}/set-default', [CompanyController::class, 'setDefaultFirm'])->name('organization.firms.set-default');
        Route::post('/organization/firms/{firm}/toggle-status', [CompanyController::class, 'toggleFirmStatus'])->name('organization.firms.toggle-status');

        // 18. Recycle Bin & Data Recovery
        Route::get('/organization/recycle-bin', [RecycleBinController::class, 'index'])->name('recycle-bin.index');
        Route::post('/organization/recycle-bin/restore', [RecycleBinController::class, 'restore'])->name('recycle-bin.restore');
        Route::post('/organization/recycle-bin/force-delete', [RecycleBinController::class, 'forceDelete'])->name('recycle-bin.force-delete');

        // 19. Accounting & Double-Entry General Ledger
        Route::get('/accounting/chart', [AccountingController::class, 'chart'])->name('accounting.chart');
        Route::post('/accounting/accounts', [AccountingController::class, 'storeAccount'])->name('accounting.accounts.store');
        Route::get('/accounting/vouchers', [AccountingController::class, 'vouchers'])->name('accounting.vouchers');
        Route::post('/accounting/vouchers', [AccountingController::class, 'storeVoucher'])->name('accounting.vouchers.store');
        Route::get('/accounting/ledger/{id}', [AccountingController::class, 'ledger'])->name('accounting.ledger');
        Route::get('/accounting/trial-balance', [AccountingController::class, 'trialBalance'])->name('accounting.trial-balance');

        // 20. Expenses with Input Tax Credit (ITC)
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::get('/expenses/itc-register', [ExpenseController::class, 'itcRegister'])->name('expenses.itc-register');
        Route::post('/expenses/{id}/delete', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

        // 21. Fixed Assets Register & Depreciation
        Route::get('/fixed-assets', [FixedAssetController::class, 'index'])->name('fixed-assets.index');
        Route::post('/fixed-assets', [FixedAssetController::class, 'store'])->name('fixed-assets.store');
        Route::post('/fixed-assets/batch-depreciation', [FixedAssetController::class, 'batchDepreciation'])->name('fixed-assets.batch-depreciation');
        Route::get('/fixed-assets/{id}', [FixedAssetController::class, 'show'])->name('fixed-assets.show');
        Route::post('/fixed-assets/{id}/depreciation', [FixedAssetController::class, 'runDepreciation'])->name('fixed-assets.depreciate');
        Route::post('/fixed-assets/{id}/dispose', [FixedAssetController::class, 'dispose'])->name('fixed-assets.dispose');

        // 22. E-way Bills (Compliance)
        Route::post('/shipments/{id}/eway', [EwayBillController::class, 'generate'])->name('shipments.eway.generate');
        Route::get('/eway-bills/{id}', [EwayBillController::class, 'show'])->name('eway-bills.show');
        Route::post('/eway-bills/{id}/cancel', [EwayBillController::class, 'cancel'])->name('eway-bills.cancel');
        Route::post('/eway-bills/{id}/extend', [EwayBillController::class, 'extend'])->name('eway-bills.extend');

        // 23. WhatsApp Connect Hub
        Route::post('/invoices/{id}/whatsapp', [WhatsAppController::class, 'sendInvoice'])->name('invoices.whatsapp');
        Route::post('/shipments/{id}/whatsapp', [WhatsAppController::class, 'sendShipment'])->name('shipments.whatsapp');
        Route::get('/organization/whatsapp', [WhatsAppController::class, 'settings'])->name('whatsapp.settings');
        Route::post('/organization/whatsapp', [WhatsAppController::class, 'updateSettings'])->name('whatsapp.settings.update');

        // 24. Machinery & Asset Service Reminders
        Route::get('/service-reminders', [ServiceReminderController::class, 'index'])->name('service-reminders.index');
        Route::post('/service-reminders', [ServiceReminderController::class, 'store'])->name('service-reminders.store');
        Route::post('/service-reminders/{id}/complete', [ServiceReminderController::class, 'complete'])->name('service-reminders.complete');
        Route::post('/service-reminders/{id}/delete', [ServiceReminderController::class, 'destroy'])->name('service-reminders.destroy');

        // 25. Financial & Operational Reports
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
        Route::get('/reports/balance-sheet/pdf', [ReportController::class, 'balanceSheetPdf'])->name('reports.balance-sheet.pdf');
        Route::get('/reports/balance-sheet/excel', [ReportController::class, 'balanceSheetExcel'])->name('reports.balance-sheet.excel');

        Route::get('/reports/bill-profit', [ReportController::class, 'billProfit'])->name('reports.bill-profit');
        Route::get('/reports/bill-profit/pdf', [ReportController::class, 'billProfitPdf'])->name('reports.bill-profit.pdf');
        Route::get('/reports/bill-profit/excel', [ReportController::class, 'billProfitExcel'])->name('reports.bill-profit.excel');

        Route::get('/reports/party-profit', [ReportController::class, 'partyProfit'])->name('reports.party-profit');
        Route::get('/reports/party-profit/pdf', [ReportController::class, 'partyProfitPdf'])->name('reports.party-profit.pdf');
        Route::get('/reports/party-profit/excel', [ReportController::class, 'partyProfitExcel'])->name('reports.party-profit.excel');

        Route::get('/reports/ar-aging', [ReportController::class, 'arAging'])->name('reports.ar-aging');
        Route::get('/reports/ar-aging/pdf', [ReportController::class, 'arAgingPdf'])->name('reports.ar-aging.pdf');
        Route::get('/reports/ar-aging/excel', [ReportController::class, 'arAgingExcel'])->name('reports.ar-aging.excel');

        Route::get('/reports/batch-trace', [ReportController::class, 'batchTrace'])->name('reports.batch-trace');
        Route::get('/reports/batch-trace/pdf', [ReportController::class, 'batchTracePdf'])->name('reports.batch-trace.pdf');
        Route::get('/reports/batch-trace/excel', [ReportController::class, 'batchTraceExcel'])->name('reports.batch-trace.excel');

        // 26. PWA Offline Sync & Cross-Device Pulse
        Route::post('/api/sync/offline-drafts', [SyncController::class, 'syncDrafts'])->name('sync.drafts');
        Route::get('/api/sync/pulse', [SyncController::class, 'pulse'])->name('sync.pulse');

        // Global Search API
        Route::get('/search', [SearchController::class, 'search'])->name('search');
    });

    // Offline Fallback View (available offline/without auth)
    Route::get('/offline', function () {
        return view('offline');
    })->name('offline');

    // Public Product Document Sharing (token-based, no auth required)
    Route::get('/shared/products/{shareToken}', [ProductDocumentController::class, 'viewShared'])->name('products.documents.shared');
    Route::post('/shared/products/{shareToken}/verify', [ProductDocumentController::class, 'verifySharePassword'])->name('products.documents.shared.verify');
    Route::get('/shared/products/{shareToken}/download/{docId}', [ProductDocumentController::class, 'downloadSharedDocument'])->name('products.documents.shared.download');
    Route::get('/shared/products/{shareToken}/preview/{docId}', [ProductDocumentController::class, 'previewSharedDocument'])->name('products.documents.shared.preview');
    Route::get('/shared/products/{shareToken}/download-all', [ProductDocumentController::class, 'downloadSharedZip'])->name('products.documents.shared.download-all');
});
