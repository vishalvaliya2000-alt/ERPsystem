<?php

namespace App\Http\Controllers;

use App\Models\FixedAsset;
use App\Models\ServiceReminder;
use App\Models\User;
use App\Services\CompanyFirmContext;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ServiceReminderController extends Controller
{
    /**
     * Lists machinery & equipment maintenance reminders.
     */
    public function index(Request $request)
    {
        $firmId = CompanyFirmContext::getCurrentFirmId();
        $status = $request->query('status', 'ACTIVE');

        $query = ServiceReminder::with(['fixedAsset', 'assignedUser', 'firm'])
            ->orderBy('next_due_date', 'asc');

        if ($firmId) {
            $query->where('firm_id', $firmId);
        }

        if ($status === 'ACTIVE') {
            $query->where('status', '!=', 'COMPLETED');
        } elseif ($status && $status !== 'ALL') {
            $query->where('status', strtoupper($status));
        }

        $reminders = $query->paginate(20)->withQueryString();

        // Metrics
        $baseQuery = ServiceReminder::query();
        if ($firmId) {
            $baseQuery->where('firm_id', $firmId);
        }

        $totalActive = (clone $baseQuery)->where('status', '!=', 'COMPLETED')->count();
        $overdueCount = (clone $baseQuery)->where('status', '!=', 'COMPLETED')->whereDate('next_due_date', '<', now())->count();
        $dueThisWeek = (clone $baseQuery)->where('status', '!=', 'COMPLETED')
            ->whereDate('next_due_date', '>=', now())
            ->whereDate('next_due_date', '<=', now()->addDays(7))
            ->count();
        $completedCount = (clone $baseQuery)->where('status', 'COMPLETED')->count();

        $assets = FixedAsset::active()->orderBy('asset_name')->get();
        $users = User::where('is_active', true)->orderBy('name')->get();

        return view('reminders.service', compact(
            'reminders',
            'assets',
            'users',
            'totalActive',
            'overdueCount',
            'dueThisWeek',
            'completedCount',
            'status'
        ));
    }

    /**
     * Creates a new service reminder schedule.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_name' => 'required|string|max:150',
            'fixed_asset_id' => 'nullable|exists:fixed_assets,id',
            'service_type' => 'required|string|in:PREVENTIVE_MAINTENANCE,CALIBRATION,OIL_GREASE,FILTER_REPLACEMENT,SAFETY_INSPECTION,ELECTRICAL_CHECK,OTHER',
            'frequency_days' => 'required|integer|min:1|max:365',
            'next_due_date' => 'required|date',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'priority' => 'required|string|in:LOW,MEDIUM,HIGH,CRITICAL',
            'estimated_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $dueDate = Carbon::parse($validated['next_due_date']);
        $status = $dueDate->isPast() ? 'OVERDUE' : ($dueDate->isToday() ? 'DUE' : 'UPCOMING');

        $reminder = ServiceReminder::create([
            'firm_id' => CompanyFirmContext::getCurrentFirmId(),
            'fixed_asset_id' => $validated['fixed_asset_id'] ?? null,
            'service_name' => $validated['service_name'],
            'service_type' => $validated['service_type'],
            'frequency_days' => (int) $validated['frequency_days'],
            'next_due_date' => $dueDate,
            'assigned_to_user_id' => $validated['assigned_to_user_id'] ?? null,
            'status' => $status,
            'priority' => $validated['priority'],
            'estimated_cost' => (float) ($validated['estimated_cost'] ?? 0.0),
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('service-reminders.index')
            ->with('success', "✓ Service reminder for {$reminder->service_name} scheduled (Due: {$reminder->next_due_date->format('d M Y')}).");
    }

    /**
     * Marks maintenance task as completed and automatically schedules the next recurrence.
     */
    public function complete(Request $request, int $id)
    {
        $reminder = ServiceReminder::findOrFail($id);
        $notes = $request->input('notes');

        $reminder->markCompleted($notes);

        return redirect()->route('service-reminders.index')
            ->with('success', "✓ Maintenance for {$reminder->service_name} marked COMPLETED. Next service automatically scheduled for {$reminder->next_due_date->format('d M Y')}.");
    }

    /**
     * Deletes a service reminder schedule.
     */
    public function destroy(int $id)
    {
        $reminder = ServiceReminder::findOrFail($id);
        $reminder->delete();

        return redirect()->route('service-reminders.index')->with('success', '✓ Service reminder deleted.');
    }
}
