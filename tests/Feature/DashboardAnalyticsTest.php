<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;

beforeEach(function () {
    $this->tenant = Tenant::first() ?? Tenant::create([
        'name' => 'Acme Analytics Tenant',
        'slug' => 'acme-analytics-tenant',
        'industry' => 'Food Processing & Exports',
        'plan' => 'Enterprise',
        'currency_code' => 'INR',
        'currency_symbol' => '₹',
        'invoice_prefix' => 'INV-',
        'quotation_prefix' => 'QTN-',
        'po_prefix' => 'PO-',
        'shipment_prefix' => 'SHP-',
    ]);

    $this->user = User::first() ?? User::factory()->create(['tenant_id' => $this->tenant->id]);
    if (! $this->user->tenant_id) {
        $this->user->tenant_id = $this->tenant->id;
        $this->user->save();
    }

    TenantManager::setTenant($this->tenant);
});

test('dashboard analytics endpoint returns complete 11-component intelligence JSON', function () {
    $response = $this->actingAs($this->user)->getJson(route('dashboard.analytics'));

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'date_range' => ['period', 'fy', 'start_date', 'end_date', 'label'],
        'kpis' => [
            'total_sales',
            'all_time_sales',
            'this_month_sales',
            'last_month_sales',
            'mom_growth_pct',
            'outstanding_ar',
            'overdue_ar',
            'total_collections',
            'all_time_collections',
            'collection_rate_pct',
            'active_orders_count',
            'active_orders_value',
            'open_order_lines',
            'pending_dispatch_qty',
            'active_leads_count',
            'pipeline_value',
            'samples_in_trial_count',
        ],
        'yearly_sales' => ['labels', 'values', 'counts'],
        'monthly_sales' => ['labels', 'keys', 'values', 'counts', 'current_month_index', 'fy_label'],
        'product_monthly_qty' => ['labels', 'keys', 'total_monthly_qty', 'datasets', 'products_list'],
        'top_products' => ['labels', 'values', 'quantities', 'items', 'total_revenue'],
        'top_customers' => ['labels', 'values', 'items', 'total_billed'],
        'collections_vs_outstanding' => ['labels', 'keys', 'invoiced', 'collected', 'outstanding'],
        'pipeline_funnel' => ['stages', 'total_leads_count', 'total_pipeline_value'],
        'receivables_aging' => ['buckets', 'total_outstanding', 'total_invoices_count'],
        'operational_status' => ['orders', 'shipments', 'total_orders', 'total_shipments'],
        'filter_options' => ['financial_years', 'current_fy', 'customers', 'products'],
    ]);
});

test('dashboard page renders all charts, filter pills, aging buckets, and funnel', function () {
    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertSee('Monthly Sales Performance');
    $response->assertSee('Yearly Sales Trend');
    $response->assertSee('Product-wise Monthly Sales Quantity');
    $response->assertSee('Top Products by Revenue');
    $response->assertSee('Collections vs Outstanding Cash Flow');
    $response->assertSee('Receivables Aging Analysis');
    $response->assertSee('Top Customers by Sales Turnover');
    $response->assertSee('Sales Pipeline Funnel');
    $response->assertSee('Sales Orders Fulfillment Status');
    $response->assertSee('Commercial Shipments Logistics');
    $response->assertSee('Priority Decision Queue');
    $response->assertSee('monthlySalesChart');
    $response->assertSee('yearlySalesChart');
    $response->assertSee('productQtyChart');
    $response->assertSee('cashFlowChart');
});
