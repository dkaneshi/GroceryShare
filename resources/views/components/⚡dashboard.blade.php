<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use App\Models\Sister;
use App\Models\GroceryWeek;
use App\Models\GroceryShare;
use App\Mail\ShareNotification;
use Illuminate\Support\Facades\Mail;

new class extends Component {
    public string $activeTab = 'dashboard';

    // Add Week form
    public bool $showAddForm = false;
    public string $totalAmount = '';
    public string $weekDate = '';
    public string $notes = '';

    // Edit Week form
    public bool $showEditForm = false;
    public ?int $editingWeekId = null;
    public string $editTotalAmount = '';
    public string $editWeekDate = '';
    public string $editNotes = '';

    // Paid weeks toggle
    public bool $showPaidWeeks = false;

    // Sister form
    public bool $showSisterForm = false;
    public ?int $editingSisterId = null;
    public string $sisterName = '';
    public string $sisterEmail = '';

    public function mount(): void
    {
        // Default to the most recent Sunday
        $sunday = now()->startOfWeek(\Carbon\Carbon::SUNDAY);
        if ($sunday->isAfter(now())) {
            $sunday->subWeek();
        }
        $this->weekDate = $sunday->format('Y-m-d');
    }

    #[Computed]
    public function sisters()
    {
        return Sister::all();
    }

    #[Computed]
    public function recentOutstandingWeeks()
    {
        return GroceryWeek::with(['shares.sister'])
            ->whereHas('shares', fn ($q) => $q->where('is_paid', false))
            ->orderByDesc('week_date')
            ->get();
    }

    #[Computed]
    public function paidWeeks()
    {
        return GroceryWeek::with(['shares.sister'])
            ->whereDoesntHave('shares', fn ($q) => $q->where('is_paid', false))
            ->orderByDesc('week_date')
            ->get();
    }

    public function addWeek(): void
    {
        $this->validate([
            'totalAmount' => ['required', 'numeric', 'min:0.01'],
            'weekDate'    => ['required', 'date'],
        ]);

        $sisters = Sister::all();

        if ($sisters->count() < 2) {
            $this->addError('sisters', 'Please add at least 2 sisters in the Sisters tab first.');
            return;
        }

        $total = (float) $this->totalAmount;
        $share = (int) floor($total / 3);

        $week = GroceryWeek::create([
            'week_date'    => $this->weekDate,
            'total_amount' => $total,
            'share_amount' => $share,
            'notes'        => $this->notes ?: null,
        ]);

        foreach ($sisters as $sister) {
            GroceryShare::create([
                'grocery_week_id' => $week->id,
                'sister_id'       => $sister->id,
                'amount'          => $share,
                'is_paid'         => false,
            ]);

            try {
                Mail::to($sister->email)->send(new ShareNotification($sister, $week, $share));
            } catch (\Exception $e) {
                // Email failed silently; the record is still saved
            }
        }

        $this->showAddForm = false;
        $this->totalAmount = '';
        $this->notes = '';
        unset($this->recentOutstandingWeeks);

        session()->flash('success', 'Week added! Sisters have been notified by email.');
    }

    public function markPaid(int $shareId): void
    {
        GroceryShare::findOrFail($shareId)->update(['is_paid' => true, 'paid_at' => now()]);
        unset($this->recentOutstandingWeeks);
        unset($this->sisters);
    }

    public function markUnpaid(int $shareId): void
    {
        GroceryShare::findOrFail($shareId)->update(['is_paid' => false, 'paid_at' => null]);
        unset($this->recentOutstandingWeeks);
        unset($this->sisters);
    }


    public function openEditWeek(int $weekId): void
    {
        $week = GroceryWeek::findOrFail($weekId);
        $this->editingWeekId  = $weekId;
        $this->editTotalAmount = (string) $week->total_amount;
        $this->editWeekDate   = $week->week_date->format('Y-m-d');
        $this->editNotes      = $week->notes ?? '';
        $this->showEditForm   = true;
    }

    public function updateWeek(): void
    {
        $this->validate([
            'editTotalAmount' => ['required', 'numeric', 'min:0.01'],
            'editWeekDate'    => ['required', 'date'],
        ]);

        $week  = GroceryWeek::findOrFail($this->editingWeekId);
        $total = (float) $this->editTotalAmount;
        $share = (int) floor($total / 3);

        $week->update([
            'week_date'    => $this->editWeekDate,
            'total_amount' => $total,
            'share_amount' => $share,
            'notes'        => $this->editNotes ?: null,
        ]);

        $week->shares()->update(['amount' => $share]);

        $this->showEditForm    = false;
        $this->editingWeekId   = null;
        $this->editTotalAmount = '';
        $this->editWeekDate    = '';
        $this->editNotes       = '';
        $this->resetValidation();
        unset($this->recentOutstandingWeeks);
        unset($this->sisters);

        session()->flash('success', 'Week updated successfully.');
    }

    public function saveSister(): void
    {
        $this->validate([
            'sisterName'  => ['required', 'string', 'max:255'],
            'sisterEmail' => ['required', 'email', 'max:255'],
        ]);

        if ($this->editingSisterId) {
            Sister::findOrFail($this->editingSisterId)->update([
                'name'  => $this->sisterName,
                'email' => $this->sisterEmail,
            ]);
        } else {
            Sister::create([
                'name'  => $this->sisterName,
                'email' => $this->sisterEmail,
            ]);
        }

        $this->cancelSisterForm();
        unset($this->sisters);
    }

    public function editSister(int $id): void
    {
        $sister = Sister::findOrFail($id);
        $this->editingSisterId = $id;
        $this->sisterName = $sister->name;
        $this->sisterEmail = $sister->email;
        $this->showSisterForm = true;
    }

    public function deleteSister(int $id): void
    {
        Sister::findOrFail($id)->delete();
        unset($this->sisters);
    }

    public function cancelSisterForm(): void
    {
        $this->showSisterForm = false;
        $this->editingSisterId = null;
        $this->sisterName = '';
        $this->sisterEmail = '';
        $this->resetValidation();
    }
};
?>

<div>
    {{-- Flash Message --}}
    @if (session()->has('success'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 4000)"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
            class="mb-5 flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm font-medium"
        >
            <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- Tab Navigation --}}
    <div class="flex gap-1 bg-white rounded-2xl shadow-sm border border-gray-100 p-1 mb-6">
        <button
            wire:click="$set('activeTab', 'dashboard')"
            class="{{ $activeTab === 'dashboard' ? 'bg-indigo-600 text-white shadow-md' : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50' }} flex-1 py-2.5 px-4 rounded-xl font-medium text-sm transition-all duration-200"
        >
            Dashboard
        </button>
        <button
            wire:click="$set('activeTab', 'sisters')"
            class="{{ $activeTab === 'sisters' ? 'bg-indigo-600 text-white shadow-md' : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50' }} flex-1 py-2.5 px-4 rounded-xl font-medium text-sm transition-all duration-200 flex items-center justify-center gap-2"
        >
            Sisters
            @if ($this->sisters->count() < 2)
                <span class="bg-amber-400 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center font-bold leading-none">!</span>
            @else
                <span class="bg-emerald-100 text-emerald-700 text-xs px-1.5 py-0.5 rounded-full">{{ $this->sisters->count() }}</span>
            @endif
        </button>
    </div>

    {{-- =================== DASHBOARD TAB =================== --}}
    @if ($activeTab === 'dashboard')

        {{-- Outstanding Totals --}}
        @if ($this->sisters->count() > 0)
            <div class="grid grid-cols-2 gap-4 mb-6">
                @foreach ($this->sisters as $sister)
                    @php $outstanding = $sister->outstandingTotal(); @endphp
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 relative overflow-hidden">
                        <div class="absolute inset-0 {{ $outstanding > 0 ? 'bg-gradient-to-br from-rose-50 to-transparent' : 'bg-gradient-to-br from-emerald-50 to-transparent' }} pointer-events-none"></div>
                        <p class="text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">{{ $sister->name }}</p>
                        <p class="text-3xl font-bold {{ $outstanding > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                            ${{ number_format($outstanding, 2) }}
                        </p>
                        <p class="text-xs text-gray-600 mt-1 font-medium">
                            {{ $outstanding > 0 ? 'outstanding' : 'all paid up ✓' }}
                        </p>
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 mb-6 text-center">
                <p class="text-amber-700 font-semibold">Set up your sisters first</p>
                <p class="text-amber-600 text-sm mt-1">Go to the <button wire:click="$set('activeTab', 'sisters')" class="underline font-medium">Sisters tab</button> to add your sisters' names and emails.</p>
            </div>
        @endif

        {{-- Section Header + Add Button --}}
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-base font-bold text-gray-800">Outstanding Weeks</h2>
                <p class="text-xs text-gray-600">All weeks with unpaid shares</p>
            </div>
            <button
                wire:click="$set('showAddForm', true)"
                class="bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-colors shadow-sm flex items-center gap-1.5"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                Add Week's Bill
            </button>
        </div>

        {{-- Outstanding Weeks List --}}
        @if ($this->recentOutstandingWeeks->isEmpty())
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
                <div class="text-4xl mb-3">🎉</div>
                <p class="text-gray-600 font-semibold">All caught up!</p>
                <p class="text-gray-600 text-sm mt-1">No outstanding shares. Add a new week's bill above.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($this->recentOutstandingWeeks as $week)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        {{-- Week Header --}}
                        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-50">
                            <div>
                                <p class="font-bold text-gray-800">{{ $week->week_date->format('F j, Y') }}</p>
                                @if ($week->notes)
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $week->notes }}</p>
                                @endif
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="text-right">
                                    <p class="text-xs text-gray-600 font-medium uppercase tracking-wide">Total Bill</p>
                                    <p class="text-xl font-bold text-gray-800">${{ number_format($week->total_amount, 2) }}</p>
                                    <p class="text-xs text-gray-600">each: ${{ number_format($week->share_amount, 0) }}</p>
                                </div>
                                <button
                                    wire:click="openEditWeek({{ $week->id }})"
                                    class="text-gray-500 hover:text-indigo-700 transition-colors mt-0.5"
                                    title="Edit week"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        {{-- Sister Shares --}}
                        <div class="grid grid-cols-2 divide-x divide-gray-50">
                            @foreach ($week->shares as $share)
                                <div class="p-4 {{ $share->is_paid ? 'bg-emerald-50/50' : 'bg-white' }}">
                                    <p class="text-xs font-semibold text-gray-500 mb-1">{{ $share->sister->name }}</p>
                                    <p class="text-lg font-bold {{ $share->is_paid ? 'text-emerald-600' : 'text-rose-600' }} mb-2">
                                        ${{ number_format($share->amount, 2) }}
                                    </p>
                                    @if ($share->is_paid)
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <span class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-700 text-xs px-2 py-1 rounded-full font-semibold">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                                                    Paid
                                                </span>
                                                <p class="text-xs text-gray-600 mt-1">{{ $share->paid_at->format('M j') }}</p>
                                            </div>
                                            <button wire:click="markUnpaid({{ $share->id }})" class="text-xs text-gray-600 hover:text-gray-800 underline transition-colors">
                                                Undo
                                            </button>
                                        </div>
                                    @else
                                        <button
                                            wire:click="markPaid({{ $share->id }})"
                                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-2 rounded-lg font-semibold transition-colors"
                                        >
                                            Mark as Paid
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Paid Weeks Toggle --}}
        @if ($this->paidWeeks->isNotEmpty())
            <div class="mt-6">
                <button
                    wire:click="$toggle('showPaidWeeks')"
                    class="flex items-center gap-2 text-sm font-semibold text-gray-700 hover:text-gray-900 transition-colors group"
                >
                    <svg
                        class="w-4 h-4 transition-transform duration-200 {{ $showPaidWeeks ? 'rotate-90' : '' }}"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                    Paid in Full
                    <span class="bg-emerald-100 text-emerald-800 text-xs font-bold px-2 py-0.5 rounded-full">
                        {{ $this->paidWeeks->count() }}
                    </span>
                </button>

                @if ($showPaidWeeks)
                    <div class="mt-3 space-y-3">
                        @foreach ($this->paidWeeks as $week)
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden opacity-80">
                                {{-- Week Header --}}
                                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-50">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="font-bold text-gray-800">{{ $week->week_date->format('F j, Y') }}</p>
                                            <span class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-800 text-xs px-2 py-0.5 rounded-full font-semibold">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                                                Paid in Full
                                            </span>
                                        </div>
                                        @if ($week->notes)
                                            <p class="text-xs text-gray-500 mt-0.5">{{ $week->notes }}</p>
                                        @endif
                                    </div>
                                    <div class="flex items-start gap-4">
                                        <div class="text-right">
                                            <p class="text-xs text-gray-600 font-medium uppercase tracking-wide">Total Bill</p>
                                            <p class="text-xl font-bold text-gray-800">${{ number_format($week->total_amount, 2) }}</p>
                                            <p class="text-xs text-gray-600">each: ${{ number_format($week->share_amount, 0) }}</p>
                                        </div>
                                        <button
                                            wire:click="openEditWeek({{ $week->id }})"
                                            class="text-gray-500 hover:text-indigo-700 transition-colors mt-0.5"
                                            title="Edit week"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                {{-- Sister Shares --}}
                                <div class="grid grid-cols-2 divide-x divide-gray-50">
                                    @foreach ($week->shares as $share)
                                        <div class="p-4 bg-emerald-50/40">
                                            <p class="text-xs font-semibold text-gray-500 mb-1">{{ $share->sister->name }}</p>
                                            <p class="text-lg font-bold text-emerald-600 mb-2">${{ number_format($share->amount, 0) }}</p>
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <span class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-700 text-xs px-2 py-1 rounded-full font-semibold">
                                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                                                        Paid
                                                    </span>
                                                    <p class="text-xs text-gray-600 mt-1">{{ $share->paid_at->format('M j') }}</p>
                                                </div>
                                                <button wire:click="markUnpaid({{ $share->id }})" class="text-xs text-gray-600 hover:text-gray-800 underline transition-colors">
                                                    Undo
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

    @endif

    {{-- =================== SISTERS TAB =================== --}}
    @if ($activeTab === 'sisters')
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-4">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-50">
                <div>
                    <h2 class="font-bold text-gray-800">Your Sisters</h2>
                    <p class="text-xs text-gray-600 mt-0.5">Manage names and email addresses</p>
                </div>
                @if ($this->sisters->count() < 2 && !$showSisterForm)
                    <button
                        wire:click="$set('showSisterForm', true)"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-xl text-sm font-semibold transition-colors flex items-center gap-1"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Sister
                    </button>
                @endif
            </div>

            @forelse ($this->sisters as $sister)
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-sm">
                            {{ strtoupper(substr($sister->name, 0, 1)) }}
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800 text-sm">{{ $sister->name }}</p>
                            <p class="text-xs text-gray-600">{{ $sister->email }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <button wire:click="editSister({{ $sister->id }})" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium transition-colors">
                            Edit
                        </button>
                        <button
                            wire:click="deleteSister({{ $sister->id }})"
                            wire:confirm="Remove {{ $sister->name }}? Their outstanding shares will also be deleted."
                            class="text-rose-700 hover:text-rose-900 text-sm font-medium transition-colors"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            @empty
                <div class="px-6 py-10 text-center">
                    <p class="text-gray-600 text-sm">No sisters added yet. Add your first sister below.</p>
                </div>
            @endforelse
        </div>

        {{-- Sister Form --}}
        @if ($showSisterForm)
            <div class="bg-white rounded-2xl shadow-sm border border-indigo-100 p-6">
                <h3 class="font-bold text-gray-800 mb-4 text-sm">{{ $editingSisterId ? 'Edit Sister' : 'Add New Sister' }}</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Name</label>
                        <input
                            wire:model="sisterName"
                            type="text"
                            placeholder="e.g. Sarah"
                            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition"
                        >
                        @error('sisterName') <p class="text-rose-700 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Email</label>
                        <input
                            wire:model="sisterEmail"
                            type="email"
                            placeholder="sarah@example.com"
                            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition"
                        >
                        @error('sisterEmail') <p class="text-rose-700 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex gap-3 pt-1">
                        <button
                            wire:click="saveSister"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition-colors"
                        >
                            {{ $editingSisterId ? 'Save Changes' : 'Add Sister' }}
                        </button>
                        <button
                            wire:click="cancelSisterForm"
                            class="border border-gray-200 text-gray-600 hover:text-gray-800 hover:border-gray-300 px-5 py-2.5 rounded-xl text-sm font-medium transition-colors"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        @elseif ($this->sisters->count() >= 2)
            <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 text-center">
                <p class="text-emerald-700 font-semibold">Both sisters are set up!</p>
                <p class="text-emerald-600 text-sm mt-1">You're ready to track grocery bills. Head back to the Dashboard.</p>
                <button wire:click="$set('activeTab', 'dashboard')" class="mt-3 text-indigo-600 hover:text-indigo-800 text-sm font-semibold underline transition-colors">
                    Go to Dashboard →
                </button>
            </div>
        @endif
    @endif

    {{-- =================== ADD WEEK MODAL =================== --}}
    @if ($showAddForm)
        <div
            class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-end sm:items-center justify-center z-50 p-4"
            wire:click.self="$set('showAddForm', false)"
        >
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 sm:p-7">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Add Week's Grocery Bill</h2>
                        <p class="text-sm text-gray-600 mt-0.5">Split equally 3 ways</p>
                    </div>
                    <button wire:click="$set('showAddForm', false)" class="text-gray-600 hover:text-gray-900 transition-colors p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                @error('sisters')
                    <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl text-sm mb-4">
                        {{ $message }}
                    </div>
                @enderror

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Week of (Sunday)</label>
                        <input
                            wire:model="weekDate"
                            type="date"
                            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition"
                        >
                        @error('weekDate') <p class="text-rose-700 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Total Grocery Bill</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-semibold text-sm">$</span>
                            <input
                                wire:model.live="totalAmount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                placeholder="0.00"
                                class="w-full border border-gray-200 rounded-xl pl-8 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition"
                            >
                        </div>
                        @error('totalAmount') <p class="text-rose-700 text-xs mt-1">{{ $message }}</p> @enderror

                        @if ($totalAmount && is_numeric($totalAmount) && (float)$totalAmount > 0)
                            <div class="mt-2 bg-indigo-50 border border-indigo-100 rounded-xl p-3">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="text-xs text-indigo-800 font-medium">Each sister owes</p>
                                        <p class="text-2xl font-bold text-indigo-700">${{ number_format(floor((float)$totalAmount / 3), 0) }}</p>
                                    </div>
                                    <div class="text-right text-xs text-indigo-800">
                                        <p>Your share:</p>
                                        <p class="font-semibold text-indigo-600">${{ number_format(floor((float)$totalAmount / 3), 0) }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                            Notes <span class="text-gray-600 font-normal normal-case">(optional)</span>
                        </label>
                        <input
                            wire:model="notes"
                            type="text"
                            placeholder="e.g. Costco run, weekend groceries..."
                            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition"
                        >
                    </div>
                </div>

                <div class="mt-6 flex gap-3">
                    <button
                        wire:click="addWeek"
                        wire:loading.attr="disabled"
                        class="flex-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white px-4 py-3 rounded-xl font-semibold text-sm transition-colors flex items-center justify-center gap-2"
                    >
                        <span wire:loading.remove wire:target="addWeek">
                            <svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            Save & Notify Sisters
                        </span>
                        <span wire:loading wire:target="addWeek">Saving...</span>
                    </button>
                    <button
                        wire:click="$set('showAddForm', false)"
                        class="px-4 py-3 rounded-xl font-medium text-sm border border-gray-200 text-gray-600 hover:text-gray-800 hover:border-gray-300 transition-colors"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- =================== EDIT WEEK MODAL =================== --}}
    @if ($showEditForm)
        <div
            class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-end sm:items-center justify-center z-50 p-4"
            wire:click.self="$set('showEditForm', false)"
        >
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 sm:p-7">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Edit Week's Bill</h2>
                        <p class="text-sm text-gray-600 mt-0.5">Update the amount or date</p>
                    </div>
                    <button wire:click="$set('showEditForm', false)" class="text-gray-600 hover:text-gray-900 transition-colors p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Week of (Sunday)</label>
                        <input
                            wire:model="editWeekDate"
                            type="date"
                            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition"
                        >
                        @error('editWeekDate') <p class="text-rose-700 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Total Grocery Bill</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-semibold text-sm">$</span>
                            <input
                                wire:model.live="editTotalAmount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                class="w-full border border-gray-200 rounded-xl pl-8 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition"
                            >
                        </div>
                        @error('editTotalAmount') <p class="text-rose-700 text-xs mt-1">{{ $message }}</p> @enderror

                        @if ($editTotalAmount && is_numeric($editTotalAmount) && (float)$editTotalAmount > 0)
                            <div class="mt-2 bg-indigo-50 border border-indigo-100 rounded-xl p-3">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="text-xs text-indigo-800 font-medium">Each sister owes</p>
                                        <p class="text-2xl font-bold text-indigo-700">${{ number_format(floor((float)$editTotalAmount / 3), 0) }}</p>
                                    </div>
                                    <div class="text-right text-xs text-indigo-800">
                                        <p>Your share:</p>
                                        <p class="font-semibold text-indigo-600">${{ number_format(floor((float)$editTotalAmount / 3), 0) }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                            Notes <span class="text-gray-600 font-normal normal-case">(optional)</span>
                        </label>
                        <input
                            wire:model="editNotes"
                            type="text"
                            placeholder="e.g. Costco run, weekend groceries..."
                            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition"
                        >
                    </div>
                </div>

                <div class="mt-6 flex gap-3">
                    <button
                        wire:click="updateWeek"
                        wire:loading.attr="disabled"
                        class="flex-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white px-4 py-3 rounded-xl font-semibold text-sm transition-colors flex items-center justify-center gap-2"
                    >
                        <span wire:loading.remove wire:target="updateWeek">Save Changes</span>
                        <span wire:loading wire:target="updateWeek">Saving...</span>
                    </button>
                    <button
                        wire:click="$set('showEditForm', false)"
                        class="px-4 py-3 rounded-xl font-medium text-sm border border-gray-200 text-gray-600 hover:text-gray-800 hover:border-gray-300 transition-colors"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>