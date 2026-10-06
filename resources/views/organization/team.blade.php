@extends('layouts.app')

@section('title', 'Team & Access Management')

@section('content')
<div class="max-w-6xl mx-auto space-y-6" x-data="{
    showInviteModal: false,
    showEditModal: false,
    editingMember: {
        id: null,
        name: '',
        email: '',
        phone: '',
        role: 'staff',
        department_id: ''
    },
    openEdit(m) {
        this.editingMember = {
            id: m.id,
            name: m.name,
            email: m.email,
            phone: m.phone || '',
            role: m.role ? m.role.toLowerCase() : 'staff',
            department_id: m.department_id || ''
        };
        this.showEditModal = true;
    }
}">
    <!-- Top Navigation Tabs (Organization Hub) -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs font-bold border-b border-neutral-200">
        <a href="{{ route('organization.settings') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
            🏢 Organization Profile
        </a>
        <a href="{{ route('organization.team') }}" class="px-4 py-2.5 rounded-xl bg-neutral-900 text-white shadow-xs whitespace-nowrap">
            👥 Team & Staff Access
        </a>
        <a href="{{ route('organization.departments') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
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
                👥
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-neutral-900 tracking-tight font-display">{{ $tenant?->name ?? 'Organization' }} Team & Permissions</h1>
                <p class="text-xs text-neutral-500">Role-based access control, departmental assignments, and granular operations permissions</p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <button @click="showInviteModal = true" class="px-4 py-2.5 text-xs font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] rounded-xl shadow-xs transition-all flex items-center gap-2 cursor-pointer border border-[#c8f043]">
                <span class="text-sm">+</span>
                <span>Invite Team Member</span>
            </button>
        </div>
    </div>

    <!-- Department Managers Quick Bento Strip -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach($departments as $dept)
            <div class="bg-white p-3.5 rounded-2xl border border-neutral-200/80 shadow-2xs space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-black tracking-wider uppercase text-neutral-400 font-mono">{{ $dept->code }}</span>
                    <span class="w-2 h-2 rounded-full {{ $dept->manager ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>
                </div>
                <div>
                    <h3 class="font-bold text-xs text-neutral-900">{{ $dept->name }}</h3>
                    <p class="text-[11px] text-neutral-500 truncate">
                        @if($dept->manager)
                            👤 {{ $dept->manager->name }}
                        @else
                            <span class="italic text-amber-600 font-medium">No Manager Assigned</span>
                        @endif
                    </p>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Team Members Table -->
    <div class="bg-white rounded-2xl sm:rounded-3xl border border-neutral-200/80 shadow-xs overflow-hidden">
        <div class="p-4 bg-neutral-50/80 border-b border-neutral-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <span class="text-xs font-black text-neutral-800 uppercase tracking-wider font-mono">Workspace Roster</span>
                <span class="ml-2 text-xs font-bold text-neutral-500">({{ $members->count() }} members)</span>
            </div>
            <span class="text-[11px] text-neutral-400 font-mono">Dedicated Database: {{ config('database.connections.tenant.database') ?? 'Tenant DB' }}</span>
        </div>

        <div class="divide-y divide-neutral-100">
            @foreach($members as $m)
                @php
                    $roleKey = strtolower($m->role ?? 'staff');
                    $isSelf = (int) auth()->id() === (int) $m->id;
                @endphp
                <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-neutral-50/50 transition-colors">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-neutral-900 to-neutral-800 text-[#D7FF53] font-bold text-xs flex items-center justify-center shadow-2xs shrink-0 font-mono">
                            {{ $m->initials }}
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-xs text-neutral-900">{{ $m->name }}</span>
                                @if($isSelf)
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-black bg-neutral-100 text-neutral-700 border border-neutral-200">
                                        YOU
                                    </span>
                                @endif

                                <!-- Role Badges -->
                                @if($roleKey === 'owner')
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-amber-100 text-amber-900 border border-amber-200">
                                        👑 OWNER
                                    </span>
                                @elseif($roleKey === 'admin')
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-indigo-100 text-indigo-900 border border-indigo-200">
                                        🛡️ ADMIN
                                    </span>
                                @elseif($roleKey === 'department_manager')
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-emerald-100 text-emerald-900 border border-emerald-200">
                                        ⭐ MANAGER
                                    </span>
                                @elseif($roleKey === 'staff')
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-100 text-blue-900 border border-blue-200">
                                        👷 STAFF
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-neutral-100 text-neutral-700 border border-neutral-200">
                                        👁️ VIEWER
                                    </span>
                                @endif

                                <!-- Department Badge -->
                                @if($m->department)
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-neutral-100 text-neutral-700 border border-neutral-200">
                                        🏛️ {{ $m->department->name }}
                                    </span>
                                @endif

                                <!-- Status Badge -->
                                @if($m->is_active)
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        ● Active
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                        ✕ Deactivated
                                    </span>
                                @endif
                            </div>
                            <div class="text-[11px] text-neutral-500 font-mono truncate mt-0.5">
                                {{ $m->email }} · {{ $m->phone ?: 'No phone' }}
                            </div>
                        </div>
                    </div>

                    <!-- Action Controls -->
                    <div class="flex items-center gap-1.5 shrink-0 self-end sm:self-auto">
                        @if(\App\Services\DepartmentPermissionService::canManage(auth()->user(), $m) || ($isSelf && auth()->user()->isOwner()))
                            <button type="button" @click="openEdit({{ json_encode($m) }})" class="px-3 py-1.5 text-xs font-bold text-neutral-700 hover:text-neutral-900 hover:bg-neutral-100 rounded-lg transition-colors cursor-pointer">
                                ✏️ Edit
                            </button>
                        @endif

                        @if(! $isSelf && \App\Services\DepartmentPermissionService::canManage(auth()->user(), $m))
                            <form action="{{ route('organization.team.toggle-status', $m->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-xs font-bold {{ $m->is_active ? 'text-amber-700 hover:bg-amber-50' : 'text-emerald-700 hover:bg-emerald-50' }} rounded-lg transition-colors cursor-pointer">
                                    {{ $m->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>

                            @if($roleKey !== 'owner')
                                <form action="{{ route('organization.team.remove', $m->id) }}" method="POST" onsubmit="return confirm('Permanently remove {{ $m->name }} from {{ $tenant?->name ?? 'this organization' }}?');">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 text-xs font-bold text-rose-600 hover:text-rose-800 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer">
                                        Remove
                                    </button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Invite Team Member Modal -->
    <template x-teleport="body">
        <div x-show="showInviteModal" x-cloak class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-md flex items-center justify-center p-2 sm:p-4">
            <div @click.outside="showInviteModal = false" class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-neutral-200/80 max-w-lg w-full max-h-[92vh] overflow-hidden flex flex-col">
                <div class="p-4 sm:p-5 bg-[#091315] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-2xl bg-[#D7FF53]/20 border border-[#D7FF53]/30 text-[#D7FF53] flex items-center justify-center font-bold text-sm">
                            👥
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm tracking-tight text-white font-display">Invite Team Member</h3>
                            <p class="text-[11px] text-[#D7FF53] font-mono">Add account with department and role assignment</p>
                        </div>
                    </div>
                    <button @click="showInviteModal = false" class="text-neutral-400 hover:text-white cursor-pointer transition-colors p-1">✕</button>
                </div>

                <form action="{{ route('organization.team.invite') }}" method="POST" class="p-4 sm:p-5 space-y-4 text-xs overflow-y-auto flex-1 touch-scroll">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">First Name *</label>
                            <input type="text" name="first_name" required class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">Last Name *</label>
                            <input type="text" name="last_name" required class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">Email Address *</label>
                        <input type="email" name="email" required class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">Phone Number</label>
                        <input type="text" name="phone" placeholder="+91..." class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">Department</label>
                            <select name="department_id" class="w-full px-3 py-2 text-xs border border-neutral-200 bg-white rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden font-bold">
                                <option value="">-- No Department --</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">🏛️ {{ $dept->name }} ({{ $dept->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">Role / Access Level *</label>
                            <select name="role" required class="w-full px-3 py-2 text-xs border border-neutral-200 bg-white rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden font-bold">
                                <option value="staff">👷 Staff (Operational Access)</option>
                                <option value="department_manager">⭐ Department Manager (Department Head)</option>
                                <option value="admin">🛡️ Admin (Workspace Administrator)</option>
                                <option value="viewer">👁️ Viewer (Read Only)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">Initial Password *</label>
                        <input type="password" name="password" required minlength="6" placeholder="Min 6 characters" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden font-mono">
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-neutral-100">
                        <button type="button" @click="showInviteModal = false" class="px-5 py-2 rounded-xl text-xs font-bold text-neutral-600 hover:bg-neutral-100 transition-colors cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl text-xs font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] shadow-2xs border border-[#c8f043] transition-all cursor-pointer">
                            Add Member
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Edit Team Member Modal -->
    <template x-teleport="body">
        <div x-show="showEditModal" x-cloak class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-md flex items-center justify-center p-2 sm:p-4">
            <div @click.outside="showEditModal = false" class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-neutral-200/80 max-w-lg w-full max-h-[92vh] overflow-hidden flex flex-col">
                <div class="p-4 sm:p-5 bg-[#091315] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-2xl bg-[#D7FF53]/20 border border-[#D7FF53]/30 text-[#D7FF53] flex items-center justify-center font-bold text-sm">
                            ✏️
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm tracking-tight text-white font-display">Edit Member & Role</h3>
                            <p class="text-[11px] text-[#D7FF53] font-mono" x-text="editingMember.email"></p>
                        </div>
                    </div>
                    <button @click="showEditModal = false" class="text-neutral-400 hover:text-white cursor-pointer transition-colors p-1">✕</button>
                </div>

                <form :action="'{{ url('/organization/team') }}/' + editingMember.id + '/update'" method="POST" class="p-4 sm:p-5 space-y-4 text-xs overflow-y-auto flex-1 touch-scroll">
                    @csrf

                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">Full Name *</label>
                        <input type="text" name="name" x-model="editingMember.name" required class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">Phone Number</label>
                        <input type="text" name="phone" x-model="editingMember.phone" placeholder="+91..." class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">Department</label>
                            <select name="department_id" x-model="editingMember.department_id" class="w-full px-3 py-2 text-xs border border-neutral-200 bg-white rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden font-bold">
                                <option value="">-- No Department --</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">🏛️ {{ $dept->name }} ({{ $dept->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">Role *</label>
                            <select name="role" x-model="editingMember.role" required class="w-full px-3 py-2 text-xs border border-neutral-200 bg-white rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden font-bold">
                                <option value="staff">👷 Staff (Operational Access)</option>
                                <option value="department_manager">⭐ Department Manager</option>
                                <option value="admin">🛡️ Admin</option>
                                <option value="owner">👑 Owner</option>
                                <option value="viewer">👁️ Viewer</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">New Password (leave blank to keep current)</label>
                        <input type="password" name="password" minlength="6" placeholder="Leave blank to keep current" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden font-mono">
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-neutral-100">
                        <button type="button" @click="showEditModal = false" class="px-5 py-2 rounded-xl text-xs font-bold text-neutral-600 hover:bg-neutral-100 transition-colors cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl text-xs font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] shadow-2xs border border-[#c8f043] transition-all cursor-pointer">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
@endsection