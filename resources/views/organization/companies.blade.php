@extends('layouts.app')

@section('title', 'Companies & Operating Firms')

@section('content')
<div class="max-w-6xl mx-auto space-y-6" x-data="{
    showAddCompanyModal: false,
    showEditCompanyModal: false,
    showAddFirmModal: false,
    showEditFirmModal: false,
    targetCompanyId: null,
    editingCompany: { id: null, legal_name: '', trade_name: '', cin: '', pan: '', website: '' },
    editingFirm: {
        id: null,
        firm_name: '',
        gstin: '',
        state: 'Gujarat',
        state_code: '24',
        pan: '',
        address_line1: '',
        address_line2: '',
        city: '',
        pincode: '',
        email: '',
        phone: '',
        invoice_prefix: 'INV',
        quotation_prefix: 'QUO',
        po_prefix: 'PO',
        shipment_prefix: 'SHP',
        bank_name: '',
        bank_account_number: '',
        bank_ifsc: '',
        bank_branch: ''
    },
    openEditCompany(c) {
        this.editingCompany = { ...c };
        this.showEditCompanyModal = true;
    },
    openAddFirm(companyId) {
        this.targetCompanyId = companyId;
        this.showAddFirmModal = true;
    },
    openEditFirm(f) {
        this.editingFirm = { ...f };
        this.showEditFirmModal = true;
    }
}">
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
        <a href="{{ route('organization.audit-logs') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
            🛡️ Security Audit Logs
        </a>
        <a href="{{ route('organization.companies.index') }}" class="px-4 py-2.5 rounded-xl bg-neutral-900 text-white shadow-xs whitespace-nowrap">
            🏭 Companies & Operating Firms
        </a>
    </div>

    <!-- Header Banner -->
    <div class="bg-white p-4 sm:p-6 rounded-2xl sm:rounded-3xl border border-neutral-200/80 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-neutral-900 text-[#D7FF53] font-black text-xl flex items-center justify-center shadow-md shrink-0">
                🏭
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-neutral-900 tracking-tight font-display">Multiple Companies & Operating Units</h1>
                <p class="text-xs text-neutral-500">Manage multiple legal corporate entities and operating units / GST branches under this workspace</p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            @if($currentFirm)
                <div class="px-3.5 py-1.5 rounded-xl bg-neutral-50 border border-neutral-200/80 text-left">
                    <span class="text-[9px] font-black uppercase tracking-wider text-neutral-400 block font-mono">Current Active Unit</span>
                    <span class="text-xs font-bold text-neutral-800">
                        {{ $currentFirm->firm_name }}
                        @if($currentFirm->gstin)
                            <span class="font-mono text-neutral-500">({{ $currentFirm->gstin }})</span>
                        @endif
                    </span>
                </div>
            @endif

            <button @click="showAddCompanyModal = true" class="px-4 py-2.5 text-xs font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] rounded-xl shadow-xs transition-all flex items-center gap-2 cursor-pointer border border-[#c8f043]">
                <span class="text-sm">+</span>
                <span>Add Company</span>
            </button>
        </div>
    </div>

    <!-- Companies & Nested Firms List -->
    <div class="space-y-6">
        @foreach($companies as $company)
            <div class="bg-white rounded-2xl sm:rounded-3xl border border-neutral-200/80 shadow-xs overflow-hidden">
                <!-- Company Header Card -->
                <div class="p-4 sm:p-6 bg-neutral-50/70 border-b border-neutral-200 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <div class="w-11 h-11 rounded-2xl bg-neutral-900 text-white font-black text-sm flex items-center justify-center shrink-0 font-display">
                            {{ substr($company->legal_name, 0, 2) }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-base font-black text-neutral-900 font-display">{{ $company->legal_name }}</h2>
                                @if($company->trade_name && $company->trade_name !== $company->legal_name)
                                    <span class="text-xs text-neutral-500 italic">({{ $company->trade_name }})</span>
                                @endif

                                @if($company->is_default)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black bg-amber-100 text-amber-900 border border-amber-200 font-mono">
                                        ★ DEFAULT COMPANY
                                    </span>
                                @endif

                                @if($company->is_active)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        ● Active
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                        ✕ Inactive
                                    </span>
                                @endif
                            </div>

                            <div class="flex items-center gap-4 text-[11px] text-neutral-500 font-mono mt-1 flex-wrap">
                                @if($company->pan)
                                    <span>PAN: <strong class="text-neutral-700">{{ $company->pan }}</strong></span>
                                @endif
                                @if($company->cin)
                                    <span>CIN: <strong class="text-neutral-700">{{ $company->cin }}</strong></span>
                                @endif
                                @if($company->website)
                                    <a href="{{ $company->website }}" target="_blank" class="text-indigo-600 hover:underline">{{ $company->website }}</a>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Company Action Buttons -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <button type="button" @click="openAddFirm({{ $company->id }})" class="px-3 py-1.5 text-xs font-bold text-neutral-800 bg-white hover:bg-neutral-100 rounded-xl border border-neutral-200 shadow-2xs transition-colors cursor-pointer">
                            + Add Branch/Firm
                        </button>

                        <button type="button" @click="openEditCompany({{ json_encode($company) }})" class="px-3 py-1.5 text-xs font-bold text-neutral-600 hover:text-neutral-900 hover:bg-white rounded-xl transition-colors cursor-pointer">
                            ✏️ Edit
                        </button>

                        @if(! $company->is_default && $company->is_active)
                            <form action="{{ route('organization.companies.set-default', $company->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-xs font-bold text-neutral-600 hover:text-neutral-900 hover:bg-white rounded-xl transition-colors cursor-pointer">
                                    Set Default
                                </button>
                            </form>
                        @endif

                        <form action="{{ route('organization.companies.toggle-status', $company->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 text-xs font-bold {{ $company->is_active ? 'text-amber-700 hover:bg-amber-50' : 'text-emerald-700 hover:bg-emerald-50' }} rounded-xl transition-colors cursor-pointer">
                                {{ $company->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Operating Firms / Branches Grid -->
                <div class="p-4 sm:p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-black text-neutral-600 uppercase tracking-wider font-mono">
                            Operating Units & GST Branches ({{ $company->firms->count() }})
                        </h3>
                    </div>

                    @if($company->firms->isNotEmpty())
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($company->firms as $firm)
                                @php
                                    $isCurrentActive = $currentFirm && (int)$currentFirm->id === (int)$firm->id;
                                @endphp
                                <div class="rounded-2xl border {{ $isCurrentActive ? 'border-emerald-500 bg-emerald-50/20' : 'border-neutral-200 bg-white' }} p-4 space-y-3 shadow-2xs flex flex-col justify-between">
                                    <div class="space-y-2">
                                        <div class="flex items-start justify-between gap-2">
                                            <div>
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <h4 class="font-bold text-xs text-neutral-900">{{ $firm->firm_name }}</h4>
                                                    @if($isCurrentActive)
                                                        <span class="px-2 py-0.5 rounded text-[9px] font-black bg-emerald-600 text-white font-mono">
                                                            ACTIVE CONTEXT
                                                        </span>
                                                    @endif
                                                    @if($firm->is_default)
                                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-black bg-amber-100 text-amber-800 border border-amber-200">
                                                            DEFAULT
                                                        </span>
                                                    @endif
                                                </div>
                                                <p class="text-[11px] text-neutral-500 font-mono mt-0.5">
                                                    GSTIN: <strong class="text-neutral-800">{{ $firm->gstin ?: 'UNREGISTERED' }}</strong> · State: {{ $firm->state_code }} - {{ $firm->state }}
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Address & Bank Details Strip -->
                                        @if($firm->full_address)
                                            <p class="text-[11px] text-neutral-600 leading-tight">
                                                📍 {{ $firm->full_address }}
                                            </p>
                                        @endif

                                        @if($firm->bank_name || $firm->bank_account_number)
                                            <div class="p-2 rounded-xl bg-neutral-50 text-[10px] text-neutral-600 font-mono space-y-0.5">
                                                <span>🏦 {{ $firm->bank_name ?: 'Bank' }}: A/C {{ $firm->bank_account_number ?: '---' }} (IFSC: {{ $firm->bank_ifsc ?: '---' }})</span>
                                            </div>
                                        @endif

                                        <!-- Document Numbering Prefixes -->
                                        <div class="flex items-center gap-2 text-[10px] font-mono text-neutral-500 flex-wrap">
                                            <span class="bg-neutral-100 px-1.5 py-0.5 rounded">Inv: {{ $firm->invoice_prefix }}</span>
                                            <span class="bg-neutral-100 px-1.5 py-0.5 rounded">Quo: {{ $firm->quotation_prefix }}</span>
                                            <span class="bg-neutral-100 px-1.5 py-0.5 rounded">PO: {{ $firm->po_prefix }}</span>
                                            <span class="bg-neutral-100 px-1.5 py-0.5 rounded">Shp: {{ $firm->shipment_prefix }}</span>
                                        </div>
                                    </div>

                                    <!-- Firm Actions -->
                                    <div class="pt-2 border-t border-neutral-100 flex items-center justify-between gap-2 flex-wrap">
                                        <div class="flex items-center gap-2">
                                            @if(! $isCurrentActive && $firm->is_active && $company->is_active)
                                                <form action="{{ route('organization.switch-context') }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="firm_id" value="{{ $firm->id }}">
                                                    <button type="submit" class="px-2.5 py-1 text-[11px] font-bold text-neutral-900 bg-[#D7FF53] hover:bg-[#c8f043] rounded-lg transition-colors cursor-pointer border border-[#c8f043]">
                                                        Switch to Unit
                                                    </button>
                                                </form>
                                            @endif

                                            <button type="button" @click="openEditFirm({{ json_encode($firm) }})" class="px-2.5 py-1 text-[11px] font-bold text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 rounded-lg transition-colors cursor-pointer">
                                                ✏️ Edit
                                            </button>
                                        </div>

                                        <div class="flex items-center gap-1.5">
                                            @if(! $firm->is_default && $firm->is_active)
                                                <form action="{{ route('organization.firms.set-default', $firm->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="px-2 py-1 text-[10px] font-bold text-neutral-500 hover:text-neutral-800 transition-colors cursor-pointer">
                                                        Make Default
                                                    </button>
                                                </form>
                                            @endif

                                            <form action="{{ route('organization.firms.toggle-status', $firm->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="px-2 py-1 text-[10px] font-bold {{ $firm->is_active ? 'text-amber-700 hover:bg-amber-50' : 'text-emerald-700 hover:bg-emerald-50' }} rounded-lg transition-colors cursor-pointer">
                                                    {{ $firm->is_active ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-6 text-center border border-dashed border-neutral-200 rounded-2xl">
                            <p class="text-xs text-neutral-400">No branches or firms defined for this company yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <!-- Add Company Modal -->
    <template x-teleport="body">
        <div x-show="showAddCompanyModal" x-cloak class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-md flex items-center justify-center p-2 sm:p-4">
            <div @click.outside="showAddCompanyModal = false" class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-neutral-200/80 max-w-lg w-full max-h-[92vh] overflow-hidden flex flex-col">
                <div class="p-4 sm:p-5 bg-[#091315] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-2xl bg-[#D7FF53]/20 border border-[#D7FF53]/30 text-[#D7FF53] flex items-center justify-center font-bold text-sm">
                            🏢
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm tracking-tight text-white font-display">Add Corporate Entity</h3>
                            <p class="text-[11px] text-[#D7FF53] font-mono">Create new legal company under workspace</p>
                        </div>
                    </div>
                    <button @click="showAddCompanyModal = false" class="text-neutral-400 hover:text-white cursor-pointer transition-colors p-1">✕</button>
                </div>

                <form action="{{ route('organization.companies.store') }}" method="POST" class="p-4 sm:p-5 space-y-4 text-xs overflow-y-auto flex-1 touch-scroll">
                    @csrf

                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">Company Legal Name *</label>
                        <input type="text" name="legal_name" required placeholder="e.g. Kisan Agro Industries Pvt Ltd" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">Trade Name / Brand Name</label>
                        <input type="text" name="trade_name" placeholder="e.g. Kisan Agro" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">PAN Number</label>
                            <input type="text" name="pan" maxlength="10" placeholder="AAAAA0000A" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden uppercase font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">CIN / Registration No</label>
                            <input type="text" name="cin" placeholder="U01100GJ2020PTC..." class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden uppercase font-mono">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">Official Website</label>
                        <input type="url" name="website" placeholder="https://..." class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-neutral-100">
                        <button type="button" @click="showAddCompanyModal = false" class="px-5 py-2 rounded-xl text-xs font-bold text-neutral-600 hover:bg-neutral-100 transition-colors cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl text-xs font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] shadow-2xs border border-[#c8f043] transition-all cursor-pointer">
                            Create Company
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Edit Company Modal -->
    <template x-teleport="body">
        <div x-show="showEditCompanyModal" x-cloak class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-md flex items-center justify-center p-2 sm:p-4">
            <div @click.outside="showEditCompanyModal = false" class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-neutral-200/80 max-w-lg w-full max-h-[92vh] overflow-hidden flex flex-col">
                <div class="p-4 sm:p-5 bg-[#091315] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-2xl bg-[#D7FF53]/20 border border-[#D7FF53]/30 text-[#D7FF53] flex items-center justify-center font-bold text-sm">
                            ✏️
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm tracking-tight text-white font-display">Edit Corporate Entity</h3>
                            <p class="text-[11px] text-[#D7FF53] font-mono" x-text="editingCompany.legal_name"></p>
                        </div>
                    </div>
                    <button @click="showEditCompanyModal = false" class="text-neutral-400 hover:text-white cursor-pointer transition-colors p-1">✕</button>
                </div>

                <form :action="'{{ url('/organization/companies') }}/' + editingCompany.id + '/update'" method="POST" class="p-4 sm:p-5 space-y-4 text-xs overflow-y-auto flex-1 touch-scroll">
                    @csrf

                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">Company Legal Name *</label>
                        <input type="text" name="legal_name" x-model="editingCompany.legal_name" required class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">Trade Name / Brand</label>
                        <input type="text" name="trade_name" x-model="editingCompany.trade_name" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">PAN Number</label>
                            <input type="text" name="pan" x-model="editingCompany.pan" maxlength="10" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden uppercase font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">CIN Number</label>
                            <input type="text" name="cin" x-model="editingCompany.cin" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden uppercase font-mono">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">Website</label>
                        <input type="url" name="website" x-model="editingCompany.website" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-neutral-100">
                        <button type="button" @click="showEditCompanyModal = false" class="px-5 py-2 rounded-xl text-xs font-bold text-neutral-600 hover:bg-neutral-100 transition-colors cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl text-xs font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] shadow-2xs border border-[#c8f043] transition-all cursor-pointer">
                            Update Company
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Add Firm Modal -->
    <template x-teleport="body">
        <div x-show="showAddFirmModal" x-cloak class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-md flex items-center justify-center p-2 sm:p-4">
            <div @click.outside="showAddFirmModal = false" class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-neutral-200/80 max-w-xl w-full max-h-[92vh] overflow-hidden flex flex-col">
                <div class="p-4 sm:p-5 bg-[#091315] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-2xl bg-[#D7FF53]/20 border border-[#D7FF53]/30 text-[#D7FF53] flex items-center justify-center font-bold text-sm">
                            🏪
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm tracking-tight text-white font-display">Add Operating Branch / Firm</h3>
                            <p class="text-[11px] text-[#D7FF53] font-mono">Create dedicated GST unit & document prefixes</p>
                        </div>
                    </div>
                    <button @click="showAddFirmModal = false" class="text-neutral-400 hover:text-white cursor-pointer transition-colors p-1">✕</button>
                </div>

                <form :action="'{{ url('/organization/companies') }}/' + targetCompanyId + '/firms'" method="POST" class="p-4 sm:p-5 space-y-4 text-xs overflow-y-auto flex-1 touch-scroll">
                    @csrf

                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">Firm / Unit Name *</label>
                        <input type="text" name="firm_name" required placeholder="e.g. Unit 2 - Dehydration Plant" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">GSTIN</label>
                            <input type="text" name="gstin" maxlength="15" placeholder="24AAACK1234F1Z9" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden uppercase font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">State & Code *</label>
                            <div class="grid grid-cols-3 gap-2">
                                <input type="text" name="state_code" required value="24" placeholder="Code" class="w-full px-2 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden font-mono text-center">
                                <input type="text" name="state" required value="Gujarat" placeholder="State" class="col-span-2 w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">Address Line 1</label>
                        <input type="text" name="address_line1" placeholder="Plot / Survey No, GIDC Area" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">City</label>
                            <input type="text" name="city" placeholder="Mahuva" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">Pincode</label>
                            <input type="text" name="pincode" placeholder="364290" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden font-mono">
                        </div>
                    </div>

                    <!-- Document Prefixes Grid -->
                    <div class="p-3 bg-neutral-50 rounded-2xl border border-neutral-200/70 space-y-2">
                        <span class="text-[10px] font-black uppercase tracking-wider text-neutral-500 font-mono">Document Numbering Prefixes</span>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <div>
                                <label class="block text-[10px] text-neutral-500 font-bold mb-0.5">Invoice</label>
                                <input type="text" name="invoice_prefix" required value="INV" class="w-full px-2 py-1.5 text-xs border border-neutral-200 rounded-lg font-mono uppercase text-center bg-white">
                            </div>
                            <div>
                                <label class="block text-[10px] text-neutral-500 font-bold mb-0.5">Quote</label>
                                <input type="text" name="quotation_prefix" required value="QUO" class="w-full px-2 py-1.5 text-xs border border-neutral-200 rounded-lg font-mono uppercase text-center bg-white">
                            </div>
                            <div>
                                <label class="block text-[10px] text-neutral-500 font-bold mb-0.5">PO</label>
                                <input type="text" name="po_prefix" required value="PO" class="w-full px-2 py-1.5 text-xs border border-neutral-200 rounded-lg font-mono uppercase text-center bg-white">
                            </div>
                            <div>
                                <label class="block text-[10px] text-neutral-500 font-bold mb-0.5">Shipment</label>
                                <input type="text" name="shipment_prefix" required value="SHP" class="w-full px-2 py-1.5 text-xs border border-neutral-200 rounded-lg font-mono uppercase text-center bg-white">
                            </div>
                        </div>
                    </div>

                    <!-- Bank Details -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">Bank Name</label>
                            <input type="text" name="bank_name" placeholder="HDFC Bank" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">A/C Number</label>
                            <input type="text" name="bank_account_number" placeholder="502000..." class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden font-mono">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">Bank IFSC Code</label>
                            <input type="text" name="bank_ifsc" placeholder="HDFC0000..." class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden uppercase font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">Bank Branch</label>
                            <input type="text" name="bank_branch" placeholder="Mahuva Branch" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-neutral-100">
                        <button type="button" @click="showAddFirmModal = false" class="px-5 py-2 rounded-xl text-xs font-bold text-neutral-600 hover:bg-neutral-100 transition-colors cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl text-xs font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] shadow-2xs border border-[#c8f043] transition-all cursor-pointer">
                            Create Branch Unit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Edit Firm Modal -->
    <template x-teleport="body">
        <div x-show="showEditFirmModal" x-cloak class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-md flex items-center justify-center p-2 sm:p-4">
            <div @click.outside="showEditFirmModal = false" class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-neutral-200/80 max-w-xl w-full max-h-[92vh] overflow-hidden flex flex-col">
                <div class="p-4 sm:p-5 bg-[#091315] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-2xl bg-[#D7FF53]/20 border border-[#D7FF53]/30 text-[#D7FF53] flex items-center justify-center font-bold text-sm">
                            ✏️
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm tracking-tight text-white font-display">Edit Operating Branch / Firm</h3>
                            <p class="text-[11px] text-[#D7FF53] font-mono" x-text="editingFirm.firm_name"></p>
                        </div>
                    </div>
                    <button @click="showEditFirmModal = false" class="text-neutral-400 hover:text-white cursor-pointer transition-colors p-1">✕</button>
                </div>

                <form :action="'{{ url('/organization/firms') }}/' + editingFirm.id + '/update'" method="POST" class="p-4 sm:p-5 space-y-4 text-xs overflow-y-auto flex-1 touch-scroll">
                    @csrf

                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">Firm / Unit Name *</label>
                        <input type="text" name="firm_name" x-model="editingFirm.firm_name" required class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">GSTIN</label>
                            <input type="text" name="gstin" x-model="editingFirm.gstin" maxlength="15" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden uppercase font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">State & Code *</label>
                            <div class="grid grid-cols-3 gap-2">
                                <input type="text" name="state_code" x-model="editingFirm.state_code" required class="w-full px-2 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden font-mono text-center">
                                <input type="text" name="state" x-model="editingFirm.state" required class="col-span-2 w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">Address Line 1</label>
                        <input type="text" name="address_line1" x-model="editingFirm.address_line1" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">City</label>
                            <input type="text" name="city" x-model="editingFirm.city" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">Pincode</label>
                            <input type="text" name="pincode" x-model="editingFirm.pincode" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden font-mono">
                        </div>
                    </div>

                    <!-- Prefixes -->
                    <div class="p-3 bg-neutral-50 rounded-2xl border border-neutral-200/70 space-y-2">
                        <span class="text-[10px] font-black uppercase tracking-wider text-neutral-500 font-mono">Document Numbering Prefixes</span>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <div>
                                <label class="block text-[10px] text-neutral-500 font-bold mb-0.5">Invoice</label>
                                <input type="text" name="invoice_prefix" x-model="editingFirm.invoice_prefix" required class="w-full px-2 py-1.5 text-xs border border-neutral-200 rounded-lg font-mono uppercase text-center bg-white">
                            </div>
                            <div>
                                <label class="block text-[10px] text-neutral-500 font-bold mb-0.5">Quote</label>
                                <input type="text" name="quotation_prefix" x-model="editingFirm.quotation_prefix" required class="w-full px-2 py-1.5 text-xs border border-neutral-200 rounded-lg font-mono uppercase text-center bg-white">
                            </div>
                            <div>
                                <label class="block text-[10px] text-neutral-500 font-bold mb-0.5">PO</label>
                                <input type="text" name="po_prefix" x-model="editingFirm.po_prefix" required class="w-full px-2 py-1.5 text-xs border border-neutral-200 rounded-lg font-mono uppercase text-center bg-white">
                            </div>
                            <div>
                                <label class="block text-[10px] text-neutral-500 font-bold mb-0.5">Shipment</label>
                                <input type="text" name="shipment_prefix" x-model="editingFirm.shipment_prefix" required class="w-full px-2 py-1.5 text-xs border border-neutral-200 rounded-lg font-mono uppercase text-center bg-white">
                            </div>
                        </div>
                    </div>

                    <!-- Bank Details -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">Bank Name</label>
                            <input type="text" name="bank_name" x-model="editingFirm.bank_name" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">A/C Number</label>
                            <input type="text" name="bank_account_number" x-model="editingFirm.bank_account_number" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden font-mono">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">Bank IFSC Code</label>
                            <input type="text" name="bank_ifsc" x-model="editingFirm.bank_ifsc" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden uppercase font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">Bank Branch</label>
                            <input type="text" name="bank_branch" x-model="editingFirm.bank_branch" class="w-full px-3 py-2 text-xs border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-neutral-100">
                        <button type="button" @click="showEditFirmModal = false" class="px-5 py-2 rounded-xl text-xs font-bold text-neutral-600 hover:bg-neutral-100 transition-colors cursor-pointer">
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
