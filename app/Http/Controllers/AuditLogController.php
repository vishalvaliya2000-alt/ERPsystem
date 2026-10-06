<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $actor = auth()->user();

        if (! $actor->isAdmin()) {
            abort(403, 'Only organization owners and administrators have access to security audit logs.');
        }

        $tenant = tenant();

        $query = Activity::with(['causer', 'subject'])->latest();

        if ($request->filled('log_name')) {
            $query->where('log_name', $request->input('log_name'));
        }

        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', $search)
                    ->orWhere('properties', 'like', $search);
            });
        }

        $activities = $query->paginate(25)->withQueryString();

        $logNames = Activity::distinct()->pluck('log_name')->filter()->values();

        return view('organization.audit-logs', compact('tenant', 'activities', 'logNames'));
    }
}
