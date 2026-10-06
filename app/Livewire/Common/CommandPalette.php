<?php

namespace App\Livewire\Common;

use App\Models\CommercialShipment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Services\TenantManager;
use Livewire\Attributes\On;
use Livewire\Component;

class CommandPalette extends Component
{
    public string $query = '';

    public bool $isOpen = false;

    protected $listeners = [
        'open-command-palette' => 'open',
        'close-command-palette' => 'close',
    ];

    #[On('open-command-palette')]
    public function open(): void
    {
        $this->isOpen = true;
    }

    #[On('close-command-palette')]
    public function close(): void
    {
        $this->isOpen = false;
        $this->query = '';
    }

    public function render()
    {
        $tenantId = TenantManager::getTenantId();
        $results = [
            'orders' => [],
            'invoices' => [],
            'shipments' => [],
            'customers' => [],
        ];

        $q = trim($this->query);

        if (strlen($q) >= 2) {
            $results['orders'] = SalesOrder::with('customer')
                ->where('tenant_id', $tenantId)
                ->where(function ($query) use ($q) {
                    $query->where('order_number', 'like', "%{$q}%")
                        ->orWhereHas('customer', function ($cq) use ($q) {
                            $cq->where('company_name', 'like', "%{$q}%");
                        });
                })
                ->take(5)
                ->get();

            $results['invoices'] = Invoice::with('customer')
                ->where('tenant_id', $tenantId)
                ->where(function ($query) use ($q) {
                    $query->where('invoice_number', 'like', "%{$q}%")
                        ->orWhereHas('customer', function ($cq) use ($q) {
                            $cq->where('company_name', 'like', "%{$q}%");
                        });
                })
                ->take(5)
                ->get();

            $results['shipments'] = CommercialShipment::with('customer')
                ->where('tenant_id', $tenantId)
                ->where(function ($query) use ($q) {
                    $query->where('shipment_number', 'like', "%{$q}%")
                        ->orWhere('lr_number', 'like', "%{$q}%")
                        ->orWhere('transporter', 'like', "%{$q}%")
                        ->orWhere('destination', 'like', "%{$q}%");
                })
                ->take(5)
                ->get();

            $results['customers'] = Customer::where('tenant_id', $tenantId)
                ->where(function ($query) use ($q) {
                    $query->where('company_name', 'like', "%{$q}%")
                        ->orWhere('primary_contact_person', 'like', "%{$q}%")
                        ->orWhere('city', 'like', "%{$q}%");
                })
                ->take(5)
                ->get();
        }

        $totalResults = count($results['orders']) + count($results['invoices']) + count($results['shipments']) + count($results['customers']);

        return view('livewire.common.command-palette', [
            'results' => $results,
            'totalResults' => $totalResults,
        ]);
    }
}
