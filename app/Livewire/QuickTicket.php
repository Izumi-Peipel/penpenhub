<?php

namespace App\Livewire;

use App\Actions\CreateTicket;
use App\Models\Asset;
use App\Models\Outlet;
use App\Support\ContextSwitcher;
use App\Support\PrioritySuggester;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/** F-304: formulir 3 langkah untuk Outlet Manager (1 outlet+aset, 2 masalah, 3 detail+foto). */
#[Layout('layouts.app')]
class QuickTicket extends Component
{
    use WithFileUploads;

    public int $step = 1;

    // Tanpa tipe: nilai <select> datang sebagai string.
    public $outletId = null;

    public $assetId = null;

    public ?string $category = null;

    public ?string $symptom = null;

    public bool $criticalConfirmed = false;

    public string $description = '';

    public array $photos = [];

    public function mount(ContextSwitcher $switcher): void
    {
        $this->outletId = $switcher->state(Auth::user())['outlet_id'];
    }

    public function updatedOutletId(): void
    {
        $this->assetId = null;
        $this->criticalConfirmed = false;
    }

    public function updatedCategory(): void
    {
        $this->symptom = null;
        $this->criticalConfirmed = false;
    }

    public function updatedSymptom(): void
    {
        $this->criticalConfirmed = false;
    }

    public function next(): void
    {
        if ($this->step === 1) {
            $this->validate([
                'outletId' => ['required'],
                'assetId' => ['required'],
            ], ['outletId.required' => 'Pilih outlet.', 'assetId.required' => 'Pilih aset yang bermasalah.']);
        }

        if ($this->step === 2) {
            $this->validate([
                'category' => ['required', Rule::in(array_keys(config('penpenhub.ticket_categories')))],
                'symptom' => ['required', 'string'],
            ], ['category.required' => 'Pilih kategori.', 'symptom.required' => 'Pilih gejala.']);
        }

        $this->step = min(3, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function submit(CreateTicket $action)
    {
        $this->validate([
            'description' => ['required', 'string', 'min:5', 'max:5000'],
            'photos' => ['array', 'max:'.config('penpenhub.attachments.max_files')],
        ]);

        $outlet = $this->selectedOutlet;
        abort_if($outlet === null, 403);

        $ticket = $action->handle(Auth::user(), [
            'brand_id' => $outlet->brand_id,
            'outlet_id' => $outlet->id,
            'asset_id' => (int) $this->assetId,
            'category' => $this->category,
            'symptom' => $this->symptom,
            'description' => $this->description,
            'critical_confirmed' => $this->criticalConfirmed,
            'attachments' => $this->photos,
        ]);

        session()->flash('status', 'Tiket '.$ticket->ticket_number.' dibuat.');

        return redirect()->route('tickets.show', $ticket);
    }

    #[Computed]
    public function outlets(): Collection
    {
        return Outlet::visibleTo(Auth::user())->orderBy('name')->get();
    }

    #[Computed]
    public function selectedOutlet(): ?Outlet
    {
        return $this->outletId ? $this->outlets->firstWhere('id', (int) $this->outletId) : null;
    }

    /** Aset outlet ini + aset level brand dari brand yang sama. */
    #[Computed]
    public function assets(): Collection
    {
        $outlet = $this->selectedOutlet;

        if ($outlet === null) {
            return collect();
        }

        return Asset::visibleTo(Auth::user())
            ->where(fn ($q) => $q->where('assets.outlet_id', $outlet->id)
                ->orWhere(fn ($q2) => $q2->whereNull('assets.outlet_id')->where('assets.brand_id', $outlet->brand_id)))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function needsConfirmation(): bool
    {
        return $this->category !== null
            && PrioritySuggester::needsCriticalConfirmation($this->category, $this->symptom, $this->selectedOutlet, now());
    }

    #[Computed]
    public function suggestedPriority(): ?string
    {
        if ($this->category === null || $this->symptom === null) {
            return null;
        }

        return PrioritySuggester::suggest($this->category, $this->symptom, $this->selectedOutlet, now(), $this->criticalConfirmed);
    }

    public function render()
    {
        return view('livewire.quick-ticket', ['categories' => config('penpenhub.ticket_categories')]);
    }
}
