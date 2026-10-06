@extends('layouts.app')

@section('title', '4 Fixed Departments & Department Managers')

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
        <a href="{{ route('organization.departments') }}" class="px-4 py-2.5 rounded-xl bg-neutral-900 text-white shadow-xs whitespace-nowrap">
            🏛️ 4 Fixed Departments
        </a>
        <a href="{{ route('organization.audit-logs') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
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
                🏛️
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-neutral-900 tracking-tight font-display">Organizational Departments</h1>
                <p class="text-xs text-neutral-500">Fixed 4-department structure with strictly one designated Department Manager per department</p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('organization.team') }}" class="px-4 py-2.5 text-xs font-bold text-neutral-700 bg-neutral-100 hover:bg-neutral-200 rounded-xl transition-all">
                ← Manage Team Members
            </a>
        </div>
    </div>

    <!-- 4 Fixed Departments Bento Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        @foreach($departments as $dept)
            @php
                $code = strtoupper($dept->code);
                $deptTheme = match($code) {
                    'SALES' => ['icon' => '💼', 'accent' => 'border-emerald-200 bg-emerald-500/10 text-emerald-800', 'badge' => 'bg-emerald-100 text-emerald-800'],
                    'ACC' => ['icon' => '💳', 'accent' => 'border-indigo-200 bg-indigo-500/10 text-indigo-800', 'badge' => 'bg-indigo-100 text-indigo-800'],
                    'QC' => ['icon' => '🔬', 'accent' => 'border-amber-200 bg-amber-500/10 text-amber-800', 'badge' => 'bg-amber-100 text-amber-800'],
                    'DISPATCH' => ['icon' => '🚚', 'accent' => 'border-blue-200 bg-blue-500/10 text-blue-800', 'badge' => 'bg-blue-100 text-blue-800'],
                    default => ['icon' => '🏢', 'accent' => 'border-neutral-200 bg-neutral-500/10 text-neutral-800', 'badge' => 'bg-neutral-100 text-neutral-800'],
                };
                $staffMembers = $dept->users->where('id', '!=', $dept->manager_user_id);
            @endphp
            <div class="bg-white rounded-2xl sm:rounded-3xl border border-neutral-200/80 shadow-xs p-5 sm:p-6 flex flex-col justify-between space-y-5">
                <div>
                    <!-- Department Title & Code -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-2xl {{ $deptTheme['accent'] }} flex items-center justify-center text-xl shrink-0 font-bold">
                                {{ $deptTheme['icon'] }}
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-base font-black text-neutral-900 font-display">{{ $dept->name }}</h2>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black {{ $deptTheme['badge'] }} font-mono">
                                        {{ $dept->code }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-neutral-500 mt-0.5">{{ $dept->description }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Current Department Manager Section -->
                    <div class="mt-4 p-3.5 rounded-2xl bg-neutral-50 border border-neutral-200/70 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-black tracking-wider uppercase text-neutral-400 font-mono">Department Manager (1 Max)</span>
                            <span class="text-[10px] font-bold {{ $dept->manager ? 'text-emerald-700' : 'text-amber-700' }}">
                                {{ $dept->manager ? '✓ Appointed' : '⚠️ Vacant' }}
                            </span>
                        </div>

                        @if($dept->manager)
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-neutral-900 text-[#D7FF53] font-black text-xs flex items-center justify-center shrink-0 font-mono">
                                    {{ $dept->manager->initials }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-xs text-neutral-900 truncate">{{ $dept->manager->name }}</p>
                                    <p class="text-[11px] text-neutral-500 font-mono truncate">{{ $dept->manager->email }} · {{ $dept->manager->phone ?: 'No phone' }}</p>
                                </div>
                            </div>
                        @else
                            <p class="text-xs text-amber-700 italic">No manager currently appointed. Select a staff member below to assign as manager.</p>
                        @endif
                    </div>

                    <!-- Department Staff Overview -->
                    <div class="mt-4 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-neutral-700">Department Staff</span>
                            <span class="text-neutral-500 font-mono text-[11px]">({{ $dept->users->count() }} total members)</span>
                        </div>

                        @if($dept->users->isNotEmpty())
                            <div class="flex flex-wrap gap-1.5 pt-1">
                                @foreach($dept->users as $u)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-[11px] font-medium bg-neutral-100 text-neutral-800 border border-neutral-200/60">
                                        <span class="w-1.5 h-1.5 rounded-full {{ (int)$u->id === (int)$dept->manager_user_id ? 'bg-emerald-500' : 'bg-neutral-400' }}"></span>
                                        <span>{{ $u->name }}</span>
                                        @if((int)$u->id === (int)$dept->manager_user_id)
                                            <span class="text-[9px] font-black text-emerald-800 uppercase">Head</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <p class="text-[11px] text-neutral-400 italic">No users currently assigned to this department.</p>
                        @endif
                    </div>
                </div>

                <!-- Manager Assignment Form (Only Admin / Owner) -->
                @if(auth()->user()->isAdmin())
                    <div class="pt-4 border-t border-neutral-100">
                        <form action="{{ route('organization.departments.assign-manager', $dept->id) }}" method="POST" class="space-y-2">
                            @csrf
                            <label class="block text-[11px] font-black text-neutral-600 uppercase tracking-wider font-mono">
                                {{ $dept->manager ? 'Transfer Manager Role' : 'Appoint Department Manager' }}
                            </label>
                            <div class="flex items-center gap-2">
                                <select name="manager_user_id" required class="flex-1 px-3 py-2 text-xs border border-neutral-200 bg-white rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden font-bold">
                                    <option value="">-- Choose Member --</option>
                                    @foreach($allUsers as $candidate)
                                        <option value="{{ $candidate->id }}" {{ (int)$candidate->id === (int)$dept->manager_user_id ? 'selected' : '' }}>
                                            {{ $candidate->name }} ({{ $candidate->email }})
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="px-4 py-2 text-xs font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] rounded-xl border border-[#c8f043] shadow-2xs transition-all cursor-pointer whitespace-nowrap">
                                    Assign
                                </button>
                            </div>
                            <p class="text-[10px] text-neutral-400 leading-tight">
                                * Appointing a new manager automatically transitions any previous manager of this department to staff.
                            </p>
                        </form>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection
