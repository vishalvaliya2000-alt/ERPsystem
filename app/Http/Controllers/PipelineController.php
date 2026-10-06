<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\FollowupTask;
use App\Models\Lead;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PipelineController extends Controller
{
    public function index()
    {
        $leads = Lead::with('followups')->orderByDesc('updated_at')->get();
        $products = Product::where('is_active', true)->get();

        $activeLeads = $leads->whereNotIn('stage', ['WON', 'LOST']);
        $totalPipelineValue = (float) $activeLeads->sum('estimated_value');
        $wonDealsValue = (float) $leads->where('stage', 'WON')->sum('estimated_value');
        $activeDealsCount = $activeLeads->count();
        $wonDealsCount = $leads->where('stage', 'WON')->count();

        // 5 Real B2B Manufacturing Stages
        $stageDefinitions = [
            [
                'id' => 'LEAD',
                'step' => 1,
                'label' => 'New Inquiries & RFQs',
                'description' => 'Initial buyer interest, grades & volume inquiry',
                'icon' => '📥',
                'color' => 'slate',
            ],
            [
                'id' => 'SAMPLE_REQUESTED',
                'step' => 2,
                'label' => 'Sample Trials & Lab COA',
                'description' => '100g-500g sample in transit, sensory & lab trial',
                'icon' => '🧪',
                'color' => 'amber',
            ],
            [
                'id' => 'QUOTATION_SENT',
                'step' => 3,
                'label' => 'Rate Quotation & Proforma',
                'description' => 'Formal rate per KG, GST & delivery terms submitted',
                'icon' => '📄',
                'color' => 'blue',
            ],
            [
                'id' => 'NEGOTIATION',
                'step' => 4,
                'label' => 'Commercial Negotiation',
                'description' => 'Final pricing, 30-day credit / payment terms & PO draft',
                'icon' => '🤝',
                'color' => 'orange',
            ],
            [
                'id' => 'WON',
                'step' => 5,
                'label' => 'Closed Won & Customer',
                'description' => 'Commercial PO confirmed and converted to active account',
                'icon' => '🏆',
                'color' => 'emerald',
            ],
        ];

        return view('pipeline.index', compact(
            'leads',
            'products',
            'totalPipelineValue',
            'wonDealsValue',
            'activeDealsCount',
            'wonDealsCount',
            'stageDefinitions'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'product_name' => 'nullable|string',
            'quantity_mt' => 'nullable|numeric',
            'target_rate_per_kg' => 'nullable|numeric',
            'estimated_value' => 'nullable|numeric',
            'stage' => 'nullable|string',
            'priority' => 'nullable|string',
            'next_action' => 'required|string',
            'next_action_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $qtyMt = ! empty($validated['quantity_mt']) ? (float) $validated['quantity_mt'] : 0;
        $rateKg = ! empty($validated['target_rate_per_kg']) ? (float) $validated['target_rate_per_kg'] : 0;

        $calcValue = $qtyMt > 0 && $rateKg > 0 ? ($qtyMt * 1000 * $rateKg) : (! empty($validated['estimated_value']) ? (float) $validated['estimated_value'] : 0);

        $prodDesc = $validated['product_name'] ?? 'Dehydrated Spices';
        if ($qtyMt > 0) {
            $prodDesc = "{$qtyMt} MT {$prodDesc}".($rateKg > 0 ? " @ ₹{$rateKg}/KG" : '');
        }

        DB::beginTransaction();
        try {
            $lead = Lead::create([
                'company_name' => $validated['company_name'],
                'contact_person' => $validated['contact_person'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'whatsapp' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? 'Gujarat',
                'interested_products' => $prodDesc,
                'estimated_value' => $calcValue,
                'stage' => $validated['stage'] ?? 'LEAD',
                'priority' => $validated['priority'] ?? 'HIGH',
                'next_action' => $validated['next_action'],
                'next_action_date' => ! empty($validated['next_action_date']) ? Carbon::parse($validated['next_action_date']) : Carbon::now()->addDays(2),
                'notes' => $validated['notes'] ?? null,
            ]);

            FollowupTask::create([
                'lead_id' => $lead->id,
                'type' => 'LEAD_NURTURING',
                'reason' => "Deal Follow-up: {$lead->company_name}",
                'next_action' => $validated['next_action'],
                'priority' => $validated['priority'] ?? 'HIGH',
                'due_date' => ! empty($validated['next_action_date']) ? Carbon::parse($validated['next_action_date']) : Carbon::now()->addDays(2),
                'status' => 'PENDING',
            ]);

            DB::commit();

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'lead' => $lead]);
            }

            return back()->with('success', "Commercial prospect {$lead->company_name} registered successfully!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to register pipeline deal: '.$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to create deal: '.$e->getMessage()], 500);
            }

            return back()->withInput()->with('error', 'Failed to register prospect: '.$e->getMessage());
        }
    }

    public function updateStage(Request $request, $id)
    {
        $lead = Lead::findOrFail($id);
        $oldStage = $lead->stage;
        $newStage = $request->input('stage');

        DB::beginTransaction();
        try {
            $lead->stage = $newStage;
            $lead->save();

            if ($request->filled('next_action')) {
                $lead->next_action = $request->input('next_action');
                if ($request->filled('next_action_date')) {
                    $lead->next_action_date = Carbon::parse($request->input('next_action_date'));
                }
                $lead->save();

                FollowupTask::create([
                    'lead_id' => $lead->id,
                    'customer_id' => $lead->converted_customer_id,
                    'type' => 'LEAD_NURTURING',
                    'reason' => "Stage changed from {$oldStage} to {$newStage}",
                    'next_action' => $lead->next_action,
                    'priority' => 'HIGH',
                    'due_date' => $lead->next_action_date ?: Carbon::now()->addDays(2),
                    'status' => 'PENDING',
                ]);
            }

            DB::commit();

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'lead' => $lead]);
            }

            return back()->with('success', "Deal stage updated to {$newStage}");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to update stage for lead #{$id}: ".$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to update deal stage: '.$e->getMessage()], 500);
            }

            return back()->with('error', 'Failed to update deal stage: '.$e->getMessage());
        }
    }

    public function convertToCustomer(Request $request, $id)
    {
        $lead = Lead::findOrFail($id);

        $customerCode = 'CUST-'.strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $lead->company_name), 0, 4)).'-'.rand(100, 999);

        DB::beginTransaction();
        try {
            $customer = Customer::create([
                'customer_code' => $customerCode,
                'company_name' => $lead->company_name,
                'trade_name' => $lead->company_name,
                'primary_contact_person' => $lead->contact_person,
                'primary_phone' => $lead->phone,
                'primary_email' => $lead->email,
                'city' => $lead->city ?: 'Mahuva',
                'state' => $lead->state ?: 'Gujarat',
                'gst_number' => $request->input('gst_number', '24AAACR1234A1Z5'),
                'payment_terms_days' => (int) $request->input('payment_terms_days', 30),
                'stage' => 'FIRST_ORDER',
                'health_score' => 'HEALTHY',
                'status' => 'Active',
                'notes' => 'Converted from CRM Commercial Lead. '.($lead->notes ?? ''),
            ]);

            if ($lead->contact_person || $lead->phone) {
                Contact::create([
                    'customer_id' => $customer->id,
                    'name' => $lead->contact_person ?: $lead->company_name,
                    'phone' => $lead->phone,
                    'email' => $lead->email,
                    'is_primary' => true,
                ]);
            }

            $lead->converted_customer_id = $customer->id;
            $lead->stage = 'WON';
            $lead->save();

            ActivityLog::create([
                'customer_id' => $customer->id,
                'activity_type' => 'DEAL_CONVERSION',
                'title' => "🎉 Won & Converted: {$lead->company_name}",
                'description' => 'Commercial deal of '.formatINR($lead->estimated_value).' converted into active client profile.',
            ]);

            DB::commit();

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'customer_id' => $lead->converted_customer_id]);
            }

            return redirect()->route('customers.show', $lead->converted_customer_id)->with('success', "🎉 Deal Closed Won! {$lead->company_name} is now an active Customer account.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to convert lead #{$id} to customer: ".$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to convert deal to customer: '.$e->getMessage()], 500);
            }

            return back()->with('error', 'Failed to convert lead to customer: '.$e->getMessage());
        }
    }

    public function logActivity(Request $request, $id)
    {
        $lead = Lead::findOrFail($id);

        $validated = $request->validate([
            'notes' => 'required|string',
            'next_action' => 'nullable|string',
            'next_action_date' => 'nullable|date',
        ]);

        DB::beginTransaction();
        try {
            if (! empty($validated['next_action'])) {
                $lead->next_action = $validated['next_action'];
                if (! empty($validated['next_action_date'])) {
                    $lead->next_action_date = Carbon::parse($validated['next_action_date']);
                }
                $lead->save();

                FollowupTask::create([
                    'lead_id' => $lead->id,
                    'customer_id' => $lead->converted_customer_id,
                    'type' => 'LEAD_NURTURING',
                    'reason' => 'Communication note logged: '.substr($validated['notes'], 0, 50),
                    'next_action' => $validated['next_action'],
                    'due_date' => $lead->next_action_date ?: Carbon::now()->addDays(2),
                    'status' => 'PENDING',
                ]);
            }

            DB::commit();

            return back()->with('success', 'Communication note recorded successfully!');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to log activity for lead #{$id}: ".$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return back()->with('error', 'Failed to log note: '.$e->getMessage());
        }
    }
}
