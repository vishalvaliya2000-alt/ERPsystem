@extends('layouts.app')

@section('title', 'Security Audit Trail & Activity Logs')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <!-- Top Navigation Tabs (Organization Hub) -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs font-bold border-b border-neutral-200">
        <a href="{{ route('organization.settings') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
            🏢 Organization Profile
        </a>
        <a href="{{ route('organization.team') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
            👥 Team & Staff Access
        </a>
        <a href="{{ route('organization.departments') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
            🏛️ 4 Fixed Departments
        </a>
        <a href="{{ route('organization.audit-logs') }}" class="px-4 py-2.5 rounded-xl bg-neutral-900 text-white shadow-xs whitespace-nowrap">
            🛡️ Security Audit Logs
        </a>
        <a href="{{ route('organization.companies.index') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
            🏭 Companies & Operating Firms
        </a>
    </div>

    <!-- Header Banner -->
    <div class="bg-white p-4 sm:p-6 rounded-2xl sm:rounded-3xl border border-neutral-200/80 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-neutral-900 text-[#D7FF53] font-black text-xl flex items-center justify-center shadow-md shrink-0">
                🛡️
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-neutral-900 tracking-tight font-display">Security & Operational Audit Trail</h1>
                <p class="text-xs text-neutral-500">Immutable chronological record of user invitations, role updates, manager assignments and key actions</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <span class="px-3 py-1.5 rounded-xl text-xs font-mono font-bold bg-neutral-100 text-neutral-700 border border-neutral-200">
                Total Events: {{ $activities->total() }}
            </span>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="bg-white p-4 rounded-2xl border border-neutral-200/80 shadow-xs">
        <form action="{{ route('organization.audit-logs') }}" method="GET" class="flex flex-col sm:flex-row items-center gap-3">
            <div class="flex-1 w-full">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search audit trail by description or keyword..." class="w-full px-3.5 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto">
                <select name="log_name" class="px-3 py-2 text-xs border border-neutral-200 bg-white rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden font-bold">
                    <option value="">All Channels</option>
                    @foreach($logNames as $name)
                        <option value="{{ $name }}" {{ request('log_name') === $name ? 'selected' : '' }}>
                            {{ ucfirst($name) }}
                        </option>
                    @endforeach
                </select>

                <button type="submit" class="px-4 py-2 text-xs font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] rounded-xl border border-[#c8f043] shadow-2xs transition-all cursor-pointer whitespace-nowrap">
                    Filter
                </button>

                @if(request()->hasAny(['search', 'log_name', 'event']))
                    <a href="{{ route('organization.audit-logs') }}" class="px-3 py-2 text-xs font-bold text-neutral-600 hover:text-neutral-900 rounded-xl hover:bg-neutral-100 transition-colors whitespace-nowrap">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Audit Events List -->
    <div class="bg-white rounded-2xl sm:rounded-3xl border border-neutral-200/80 shadow-xs overflow-hidden">
        <div class="p-4 bg-neutral-50/80 border-b border-neutral-200 flex items-center justify-between">
            <span class="text-xs font-black text-neutral-800 uppercase tracking-wider font-mono">Activity Log Records</span>
            <span class="text-[11px] text-neutral-500 font-mono">Stored in tenant dedicated database</span>
        </div>

        @if($activities->count() > 0)
            <div class="divide-y divide-neutral-100">
                @foreach($activities as $act)
                    <div class="p-4 sm:p-5 flex flex-col md:flex-row md:items-start justify-between gap-3 hover:bg-neutral-50/50 transition-colors">
                        <div class="flex items-start gap-3.5">
                            <div class="w-9 h-9 rounded-xl bg-neutral-900 text-[#D7FF53] font-black text-xs flex items-center justify-center shrink-0 font-mono mt-0.5">
                                {{ $act->causer ? $act->causer->initials : 'SYS' }}
                            </div>

                            <div class="space-y-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-xs text-neutral-900">
                                        {{ $act->causer?->name ?? 'System Action' }}
                                    </span>

                                    @if($act->log_name)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black bg-neutral-100 text-neutral-700 border border-neutral-200 font-mono">
                                            {{ strtoupper($act->log_name) }}
                                        </span>
                                    @endif

                                    @if($act->event)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                            {{ strtoupper($act->event) }}
                                        </span>
                                    @endif
                                </div>

                                <p class="text-xs text-neutral-700 font-medium">
                                    {{ $act->description }}
                                </p>

                                @if($act->properties && $act->properties->isNotEmpty())
                                    <div class="mt-2 p-2.5 rounded-xl bg-neutral-50 border border-neutral-200/60 text-[11px] font-mono text-neutral-600 overflow-x-auto max-w-2xl">
                                        @if(isset($act->properties['attributes']))
                                            <span class="font-bold text-neutral-800">Changes: </span>
                                            {{ json_encode($act->properties['attributes'], JSON_UNESCAPED_SLASHES) }}
                                        @else
                                            {{ json_encode($act->properties, JSON_UNESCAPED_SLASHES) }}
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="text-[11px] text-neutral-400 font-mono whitespace-nowrap self-start md:self-auto shrink-0">
                            {{ $act->created_at->format('d M Y, h:i A') }}
                        </div>
                    </div>
                @endforeach
            </div>

            @if($activities->hasPages())
                <div class="p-4 border-t border-neutral-200">
                    {{ $activities->links() }}
                </div>
            @endif
        @else
            <div class="p-12 text-center space-y-2">
                <span class="text-3xl">🛡️</span>
                <p class="font-bold text-sm text-neutral-800">No activity records found</p>
                <p class="text-xs text-neutral-400">Security actions and operational events will automatically appear here as team members perform tasks.</p>
            </div>
        @endif
    </div>
</div>
@endsection
