<?php

namespace App\Livewire\Dashboard;

use App\Services\DashboardAnalyticsService;
use App\Services\TenantManager;
use Livewire\Component;

class ExecutivePulse extends Component
{
    public string $period = 'this_fy';

    public string $selectedFy = '';

    public string $selectedCustomer = 'all';

    public string $selectedProduct = 'all';

    public function mount(DashboardAnalyticsService $service): void
    {
        $this->selectedFy = $service->getCurrentFY();
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period;
        $this->dispatch('analytics-filter-changed', period: $period);
    }

    public function updatedSelectedFy(): void
    {
        $this->dispatch('analytics-filter-changed', fy: $this->selectedFy);
    }

    public function updatedSelectedCustomer(): void
    {
        $this->dispatch('analytics-filter-changed', customer_id: $this->selectedCustomer);
    }

    public function updatedSelectedProduct(): void
    {
        $this->dispatch('analytics-filter-changed', product_id: $this->selectedProduct);
    }

    public function resetFilters(DashboardAnalyticsService $service): void
    {
        $this->period = 'this_fy';
        $this->selectedFy = $service->getCurrentFY();
        $this->selectedCustomer = 'all';
        $this->selectedProduct = 'all';
        $this->dispatch('analytics-filter-changed', period: 'this_fy');
    }

    public function render(DashboardAnalyticsService $service)
    {
        $tenantId = TenantManager::getTenantId();

        $filters = [
            'period' => $this->period,
            'fy' => $this->selectedFy,
            'customer_id' => $this->selectedCustomer,
            'product_id' => $this->selectedProduct,
        ];

        $analytics = $service->getAllAnalytics($tenantId, $filters);

        return view('livewire.dashboard.executive-pulse', [
            'analytics' => $analytics,
        ]);
    }
}
