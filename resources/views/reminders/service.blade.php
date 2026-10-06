@extends('layouts.app')

@section('title', 'Service & Maintenance Reminders')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="{
    showAddScheduleModal: false,
    showCompleteModal: false,
    selectedReminder: null,
    completionNotes: '',
    openComplete(r) {
        this.selectedReminder = r;
        this.completionNotes = '';
        this.showCompleteModal = true;
    }
}">
    <!-- Sub-nav -->
    <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
        <div class="flex items-center gap-2 overflow-x-auto text-xs font-bold">
            <a href="{{ route('fixed-assets.index') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                🏭 Fixed Assets Register
            </a>
            <a href="{{ route('service-reminders.index') }}" class="px-4 py-2.5 rounded-xl bg-neutral-900 text-white shadow-sm">
                ⚙️ Service & Maintenance
            </a>
        </div>
        <button @click="showAddScheduleModal = true" class="px-4 py-2.5 rounded-xl bg-neutral-900 hover:bg-neutral-800 text-white text-xs font-bold transition-colors flex items-center gap-1.5 shadow-sm">
            <span>+</span> Schedule Service
        </button>
    </div>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Active Schedules</span>
            <div class="text-lg font-black text-neutral-900 mt-1">{{ $totalActive }}</div>
            <div class="text-[10px] text-neutral-400 mt-0.5">Recurring checks</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Due This Week</span>
            <div class="text-lg font-black text-amber-600 mt-1">{{ $dueThisWeek }}</div>
            <div class="text-[10px] text-amber-600 font-semibold mt-0.5">Upcoming maintenance</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Overdue Service</span>
            <div class="text-lg font-black text-rose-600 mt-1">{{ $overdueCount }}</div>
            <div class="text-[10px] text-rose-500 font-semibold mt-0.5">Urgent attention needed</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Completed Logs</span>
            <div class="text-lg font-black text-emerald-600 mt-1">{{ $completedCount }}</div>
            <div class="text-[10px] text-neutral-400 mt-0.5">History recorded</div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="bg-white border border-neutral-200 p-4 rounded-2xl shadow-xs">
        <form method="GET" action="{{ route('service-reminders.index') }}" class="flex items-center gap-3 text-xs">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500 mb-1">Status</label>
                <select name="status" onchange="this.form.submit()" class="px-3 py-1.5 rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                    <option value="ACTIVE" {{ $status === 'ACTIVE' ? 'selected' : '' }}>Active (All Pending)</option>
                    <option value="UPCOMING" {{ $status === 'UPCOMING' ? 'selected' : '' }}>Upcoming</option>
                    <option value="DUE" {{ $status === 'DUE' ? 'selected' : '' }}>Due</option>
                    <option value="OVERDUE" {{ $status === 'OVERDUE' ? 'selected' : '' }}>Overdue</option>
                    <option value="COMPLETED" {{ $status === 'COMPLETED' ? 'selected' : '' }}>Completed</option>
                    <option value="ALL" {{ $status === 'ALL' ? 'selected' : '' }}>All Tasks</option>
                </select>
            </div>
        </form>
    </div>

    <!-- Schedules Table -->
    <div class="bg-white border border-neutral-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-neutral-50 border-b border-neutral-200 text-neutral-500 uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-3 px-4 font-bold">Maintenance Task</th>
                        <th class="py-3 px-4 font-bold">Asset / Machine</th>
                        <th class="py-3 px-4 font-bold">Type</th>
                        <th class="py-3 px-4 font-bold text-center">Frequency</th>
                        <th class="py-3 px-4 font-bold">Next Due Date</th>
                        <th class="py-3 px-4 font-bold text-center">Priority</th>
                        <th class="py-3 px-4 font-bold">Assigned To</th>
                        <th class="py-3 px-4 font-bold text-center">Status</th>
                        <th class="py-3 px-4 font-bold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse($reminders as $r)
                        <tr class="hover:bg-neutral-50/60 transition-colors">
                            <td class="py-3 px-4 font-bold text-neutral-900">{{ $r->service_name }}</td>
                            <td class="py-3 px-4 text-neutral-700">
                                @if($r->fixedAsset)
                                    <span class="font-mono text-[11px] text-neutral-500">[{{ $r->fixedAsset->asset_code }}]</span>
                                    {{ $r->fixedAsset->asset_name }}
                                @else
                                    <span class="text-neutral-400">General Facility</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-neutral-600 text-[11px]">
                                <span class="px-2 py-0.5 rounded-md bg-neutral-100 font-mono">
                                    {{ str_replace('_', ' ', $r->service_type) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center font-mono text-neutral-600">Every {{ $r->frequency_days }} days</td>
                            <td class="py-3 px-4 font-mono whitespace-nowrap {{ $r->isOverdue() ? 'font-bold text-rose-600' : 'text-neutral-800' }}">
                                {{ $r->next_due_date->format('d M Y') }}
                                @if($r->isOverdue())
                                    <span class="text-[10px] text-rose-500 font-bold block">(Overdue)</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                @php
                                    $prioClasses = [
                                        'CRITICAL' => 'bg-rose-50 text-rose-700',
                                        'HIGH' => 'bg-amber-50 text-amber-700',
                                        'MEDIUM' => 'bg-blue-50 text-blue-700',
                                        'LOW' => 'bg-neutral-100 text-neutral-600',
                                    ];
                                @endphp
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-md {{ $prioClasses[$r->priority] ?? 'bg-neutral-100' }}">
                                    {{ $r->priority }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-neutral-600">{{ $r->assignedUser?->name ?: 'Unassigned' }}</td>
                            <td class="py-3 px-4 text-center">
                                @if($r->status === 'COMPLETED')
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-emerald-50 text-emerald-700">COMPLETED</span>
                                @elseif($r->isOverdue())
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-rose-50 text-rose-700">OVERDUE</span>
                                @else
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-neutral-100 text-neutral-800">{{ $r->status }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                @if($r->status !== 'COMPLETED')
                                    <button @click="openComplete({{ $r->toJson() }})" class="inline-flex items-center px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-[11px] font-bold transition-colors">
                                        ✓ Mark Done
                                    </button>
                                @else
                                    <span class="text-neutral-400 text-[11px]">Serviced</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-neutral-400">No service schedules found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($reminders->hasPages())
            <div class="px-5 py-3 border-t border-neutral-100">
                {{ $reminders->links() }}
            </div>
        @endif
    </div>

    <!-- Modal: Schedule Service -->
    <div x-show="showAddScheduleModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-neutral-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showAddScheduleModal = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-neutral-100 space-y-4">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                <h3 class="font-black text-neutral-900 text-base">Schedule Maintenance Reminder</h3>
                <button @click="showAddScheduleModal = false" class="text-neutral-400 hover:text-neutral-600 text-lg">&times;</button>
            </div>

            <form method="POST" action="{{ route('service-reminders.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Service Task Name *</label>
                    <input type="text" name="service_name" required placeholder="e.g. Monthly Motor Bearing Lubrication & Grease" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Fixed Asset / Equipment</label>
                        <select name="fixed_asset_id" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                            <option value="">General Plant Maintenance</option>
                            @foreach($assets as $a)
                                <option value="{{ $a->id }}">[{{ $a->asset_code }}] {{ $a->asset_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Service Type *</label>
                        <select name="service_type" required class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                            <option value="PREVENTIVE_MAINTENANCE">Preventive Maintenance</option>
                            <option value="OIL_GREASE">Oil & Greasing</option>
                            <option value="CALIBRATION">Sensor & Scale Calibration</option>
                            <option value="FILTER_REPLACEMENT">Air / Liquid Filter Replacement</option>
                            <option value="SAFETY_INSPECTION">Safety & Fire Inspection</option>
                            <option value="ELECTRICAL_CHECK">Electrical Motor / Panel Check</option>
                            <option value="OTHER">Other Routine Service</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Frequency (Days) *</label>
                        <input type="number" name="frequency_days" required value="30" min="1" max="365" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Next Due Date *</label>
                        <input type="date" name="next_due_date" required value="{{ date('Y-m-d', strtotime('+30 days')) }}" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Priority *</label>
                        <select name="priority" required class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                            <option value="MEDIUM">MEDIUM</option>
                            <option value="HIGH">HIGH</option>
                            <option value="CRITICAL">CRITICAL</option>
                            <option value="LOW">LOW</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Assigned Operator / Engineer</label>
                        <select name="assigned_to_user_id" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                            <option value="">Unassigned</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->role }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Estimated Cost (₹)</label>
                        <input type="number" step="0.01" name="estimated_cost" value="0.00" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 font-mono">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Instructions / Notes</label>
                    <textarea name="notes" rows="2" placeholder="e.g. Use food-grade lubricant for dryer rollers" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-neutral-100">
                    <button type="button" @click="showAddScheduleModal = false" class="px-4 py-2 text-xs font-bold text-neutral-600 hover:bg-neutral-100 rounded-xl transition-colors">Cancel</button>
                    <button type="submit" class="px-5 py-2 text-xs font-bold bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl shadow-xs transition-colors">Create Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Mark Service Completed -->
    <div x-show="showCompleteModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-neutral-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showCompleteModal = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-neutral-100 space-y-4">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                <h3 class="font-black text-neutral-900 text-base">Complete Maintenance Task</h3>
                <button @click="showCompleteModal = false" class="text-neutral-400 hover:text-neutral-600 text-lg">&times;</button>
            </div>

            <p class="text-xs text-neutral-600">
                Marking this maintenance as complete will log completion today and automatically schedule the next due date forward by <span class="font-bold" x-text="selectedReminder?.frequency_days"></span> days.
            </p>

            <form :action="'{{ url('/service-reminders') }}/' + (selectedReminder?.id || '') + '/complete'" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Completion Notes / Servicing Report</label>
                    <textarea name="notes" rows="3" placeholder="e.g. Bearings replaced with SKF 6205, greased, motor running at 45 deg C" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-neutral-100">
                    <button type="button" @click="showCompleteModal = false" class="px-4 py-2 text-xs font-bold text-neutral-600 hover:bg-neutral-100 rounded-xl transition-colors">Cancel</button>
                    <button type="submit" class="px-5 py-2 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl shadow-xs transition-colors">Confirm Completion</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
