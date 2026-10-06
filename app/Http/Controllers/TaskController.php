<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\FollowupTask;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\SalesOrder;
use App\Models\Sample;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'PENDING');
        $priority = $request->query('priority', 'ALL');

        $query = FollowupTask::with(['customer', 'lead'])->orderBy('due_date');

        if ($status !== 'ALL') {
            $query->where('status', $status);
        }

        if ($priority !== 'ALL') {
            $query->where('priority', $priority);
        }

        $tasks = $query->get();

        return view('tasks.index', compact('tasks', 'status', 'priority'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'nullable|string',
            'customer_id' => 'nullable|integer',
            'lead_id' => 'nullable|integer',
            'reason' => 'required|string',
            'next_action' => 'required|string',
            'priority' => 'nullable|string',
            'due_date' => 'required|date',
            'type' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $custId = $validated['customer_id'] ?? null;
            if (! $custId && ! empty($validated['customer_name'])) {
                $found = Customer::where('company_name', 'like', "%{$validated['customer_name']}%")->first();
                if ($found) {
                    $custId = $found->id;
                }
            }

            $task = FollowupTask::create([
                'customer_id' => $custId,
                'lead_id' => $validated['lead_id'] ?? null,
                'reason' => $validated['reason'],
                'next_action' => $validated['next_action'],
                'priority' => $validated['priority'] ?? 'HIGH',
                'due_date' => Carbon::parse($validated['due_date']),
                'type' => $validated['type'] ?? 'SALES_FOLLOWUP',
                'status' => 'PENDING',
            ]);

            if ($custId) {
                ActivityLog::create([
                    'customer_id' => $custId,
                    'activity_type' => 'NOTE',
                    'title' => "Task Scheduled: {$task->next_action}",
                    'description' => "Reason: {$task->reason} | Due: ".$task->due_date->format('d M Y'),
                ]);
            }

            DB::commit();

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'task' => $task]);
            }

            return back()->with('success', 'Follow-up task scheduled successfully!');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to create task: '.$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to schedule task: '.$e->getMessage()], 500);
            }

            return back()->withInput()->with('error', 'Failed to schedule task: '.$e->getMessage());
        }
    }

    public function complete(Request $request, $id)
    {
        $outcome = $request->input('outcome_notes', 'Action completed');
        $needsNext = $request->boolean('needs_next_action', true);
        $nextAction = $request->input('next_action');
        $nextDueDate = $request->input('next_due_date');
        $priority = $request->input('priority', 'HIGH');
        $nextDue = $nextDueDate ? Carbon::parse($nextDueDate) : Carbon::now()->addDays(3);

        $customerId = null;
        $leadId = null;
        $taskType = 'SALES_FOLLOWUP';
        $entityType = null;
        $entityId = null;
        $titleDesc = 'Follow-up Action Completed';

        DB::beginTransaction();
        try {
            if (is_string($id) && str_starts_with($id, 'inv-')) {
                $invId = (int) substr($id, 4);
                $inv = Invoice::with('customer')->findOrFail($invId);
                $customerId = $inv->customer_id;
                $taskType = 'PAYMENT_FOLLOWUP';
                $entityType = 'INVOICE';
                $entityId = $inv->id;
                $titleDesc = "Payment follow-up for Invoice {$inv->invoice_number}";

                // Mark any pending tasks for this invoice as completed
                FollowupTask::where('related_entity_type', 'INVOICE')
                    ->where('related_entity_id', $inv->id)
                    ->where('status', 'PENDING')
                    ->update([
                        'status' => 'COMPLETED',
                        'outcome_notes' => $outcome,
                        'completed_at' => Carbon::now(),
                    ]);
            } elseif (is_string($id) && str_starts_with($id, 'lead-')) {
                $lId = (int) substr($id, 5);
                $lead = Lead::findOrFail($lId);
                $leadId = $lead->id;
                $taskType = 'LEAD_NURTURING';
                $entityType = 'LEAD';
                $entityId = $lead->id;
                $titleDesc = "Prospect follow-up for {$lead->company_name}";

                FollowupTask::where('lead_id', $lead->id)
                    ->where('status', 'PENDING')
                    ->update([
                        'status' => 'COMPLETED',
                        'outcome_notes' => $outcome,
                        'completed_at' => Carbon::now(),
                    ]);
            } elseif (is_string($id) && str_starts_with($id, 'sample-')) {
                $sId = (int) substr($id, 7);
                $sample = Sample::with('customer')->findOrFail($sId);
                $customerId = $sample->customer_id;
                $taskType = 'SAMPLE_TRIAL';
                $entityType = 'SAMPLE';
                $entityId = $sample->id;
                $titleDesc = 'Sample evaluation follow-up';

                FollowupTask::where('related_entity_type', 'SAMPLE')
                    ->where('related_entity_id', $sample->id)
                    ->where('status', 'PENDING')
                    ->update([
                        'status' => 'COMPLETED',
                        'outcome_notes' => $outcome,
                        'completed_at' => Carbon::now(),
                    ]);
            } else {
                $cleanId = is_string($id) && str_starts_with($id, 'task-') ? (int) substr($id, 5) : (int) $id;
                $task = FollowupTask::with(['customer', 'lead'])->find($cleanId);

                if ($task) {
                    $customerId = $task->customer_id;
                    $leadId = $task->lead_id;
                    $taskType = $task->type;
                    $entityType = $task->related_entity_type;
                    $entityId = $task->related_entity_id;
                    $titleDesc = $task->next_action ?: $task->reason;

                    $task->update([
                        'status' => 'COMPLETED',
                        'outcome_notes' => $outcome,
                        'completed_at' => Carbon::now(),
                    ]);

                    // If this is a Sales Order task and outcome remarks indicate client will update / dispatch as per requirement,
                    // update the Sales Order notes so subsequent daily crons won't recreate the same follow-up!
                    if ($task->related_entity_type === 'SALES_ORDER' && $task->related_entity_id) {
                        $so = SalesOrder::find($task->related_entity_id);
                        if ($so) {
                            $existingNotes = $so->notes ? ($so->notes.' | ') : '';
                            $so->update(['notes' => $existingNotes.$outcome]);
                        }
                    }
                }
            }

            // Log Activity Note on Customer Timeline
            if ($customerId) {
                ActivityLog::create([
                    'customer_id' => $customerId,
                    'activity_type' => 'NOTE',
                    'title' => "Completed: {$titleDesc}",
                    'description' => "Outcome: {$outcome}",
                ]);
            }

            // Schedule Next Follow-up Task if requested
            if ($needsNext && $nextAction) {
                FollowupTask::create([
                    'customer_id' => $customerId,
                    'lead_id' => $leadId,
                    'related_entity_type' => $entityType,
                    'related_entity_id' => $entityId,
                    'type' => $taskType,
                    'reason' => "Follow-up to: {$titleDesc}",
                    'priority' => $priority,
                    'due_date' => $nextDue,
                    'status' => 'PENDING',
                    'next_action' => $nextAction,
                ]);

                if ($customerId) {
                    ActivityLog::create([
                        'customer_id' => $customerId,
                        'activity_type' => 'NOTE',
                        'title' => "Next Action Scheduled: {$nextAction}",
                        'description' => 'Due on '.$nextDue->format('d M Y'),
                    ]);
                }
            }

            DB::commit();

            if ($request->wantsJson()) {
                return response()->json(['success' => true]);
            }

            return back()->with('success', 'Action completed and next timeline step updated successfully!');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to complete task #{$id}: ".$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to complete task: '.$e->getMessage()], 500);
            }

            return back()->with('error', 'Failed to complete task: '.$e->getMessage());
        }
    }
}
