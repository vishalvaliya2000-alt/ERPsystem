<?php

namespace App\Http\Controllers;

use App\Models\CommercialShipment;
use App\Models\EwayBill;
use App\Services\EwayBill\EwayBillService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EwayBillController extends Controller
{
    public function __construct(
        protected EwayBillService $ewayBillService
    ) {}

    /**
     * Generates a new statutory E-way Bill for a Commercial Shipment.
     */
    public function generate(Request $request, int $shipmentId)
    {
        $shipment = CommercialShipment::with(['firm', 'invoices', 'items'])->findOrFail($shipmentId);

        $validated = $request->validate([
            'distance_km' => 'nullable|integer|min:1|max:4000',
            'destination_pincode' => 'nullable|digits:6',
            'vehicle_number' => 'nullable|string|max:30',
            'transporter' => 'nullable|string|max:100',
        ]);

        if (! empty($validated['vehicle_number'])) {
            $shipment->update(['vehicle_number' => strtoupper(trim($validated['vehicle_number']))]);
        }
        if (! empty($validated['transporter'])) {
            $shipment->update(['transporter' => trim($validated['transporter'])]);
        }

        try {
            $ewayBill = $this->ewayBillService->generateForShipment($shipment, $validated);

            return redirect()->route('eway-bills.show', $ewayBill->id)
                ->with('success', "✓ Official E-way Bill #{$ewayBill->eway_bill_number} generated successfully! Valid until {$ewayBill->valid_until->format('d M Y, h:i A')}.");
        } catch (ValidationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('E-way bill generation failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return back()->withInput()->with('error', 'Failed to generate E-way Bill: '.$e->getMessage());
        }
    }

    /**
     * Displays E-way Bill details and printable document.
     */
    public function show(int $id)
    {
        $ewayBill = EwayBill::with(['shipment.firm', 'shipment.invoices', 'firm'])->findOrFail($id);
        $shipment = $ewayBill->shipment;

        return view('eway-bills.show', compact('ewayBill', 'shipment'));
    }

    /**
     * Cancels an E-way Bill within the statutory 24-hour window.
     */
    public function cancel(Request $request, int $id)
    {
        $ewayBill = EwayBill::findOrFail($id);

        $validated = $request->validate([
            'reason' => 'required|string|max:255',
            'remarks' => 'nullable|string|max:255',
        ]);

        $res = $this->ewayBillService->cancelEwayBill($ewayBill, $validated['reason'], $validated['remarks'] ?? '');

        if ($res['success']) {
            return redirect()->route('eway-bills.show', $ewayBill->id)
                ->with('success', "✓ {$res['message']}");
        }

        return back()->with('error', "🚫 {$res['message']}");
    }

    /**
     * Extends validity of an in-transit E-way Bill.
     */
    public function extend(Request $request, int $id)
    {
        $ewayBill = EwayBill::findOrFail($id);

        $validated = $request->validate([
            'additional_km' => 'required|integer|min:10|max:2000',
            'reason' => 'required|string|max:255',
            'remarks' => 'nullable|string|max:255',
        ]);

        $res = $this->ewayBillService->extendEwayBill($ewayBill, (int) $validated['additional_km'], $validated['reason'], $validated['remarks'] ?? '');

        if ($res['success']) {
            return redirect()->route('eway-bills.show', $ewayBill->id)
                ->with('success', "✓ {$res['message']}");
        }

        return back()->with('error', "🚫 {$res['message']}");
    }
}
