<?php

namespace App\Actions;

use App\Models\Asset;
use App\Models\Brand;
use App\Models\Outlet;
use App\Models\Ticket;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\PrioritySuggester;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CreateTicket
{
    /**
     * @param  array{brand_id: int, outlet_id?: ?int, asset_id: int, category: string, symptom?: ?string, title?: ?string,
     *               description: string, critical_confirmed?: bool, attachments?: array}  $input
     */
    public function handle(User $actor, array $input): Ticket
    {
        $categories = config('penpenhub.ticket_categories');

        $data = Validator::make($input, [
            'brand_id' => ['required', 'integer'],
            'outlet_id' => ['nullable', 'integer'],
            'asset_id' => ['required', 'integer'],
            'category' => ['required', Rule::in(array_keys($categories))],
            'symptom' => ['nullable', 'string', 'max:40'],
            'title' => ['nullable', 'string', 'max:160'],
            'description' => ['required', 'string', 'min:5', 'max:5000'],
            'critical_confirmed' => ['boolean'],
            'attachments' => ['array', 'max:'.config('penpenhub.attachments.max_files')],
            'attachments.*' => AttachmentStore::fileRules(),
        ])->validate();

        $symptom = $data['symptom'] ?? null;
        if ($symptom !== null && ! isset($categories[$data['category']]['symptoms'][$symptom])) {
            throw ValidationException::withMessages(['symptom' => 'Gejala tidak sesuai kategori.']);
        }

        // Brand/outlet yang tidak ada diperlakukan sama dengan tidak berhak (tanpa membocorkan keberadaan).
        $brand = Brand::withoutGlobalScopes()->find($data['brand_id']) ?? throw new AuthorizationException();
        $outlet = null;
        if (! empty($data['outlet_id'])) {
            $outlet = Outlet::withoutGlobalScopes()->where('brand_id', $brand->id)->find($data['outlet_id'])
                ?? throw new AuthorizationException();
        }

        Gate::forUser($actor)->authorize('create', [Ticket::class, $brand, $outlet]);

        $asset = Asset::withoutGlobalScopes()->where('brand_id', $brand->id)->find($data['asset_id'])
            ?? throw ValidationException::withMessages(['asset_id' => 'Aset tidak ditemukan pada brand ini.']);

        if ($asset->outlet_id !== null && (int) $asset->outlet_id !== (int) $outlet?->id) {
            throw ValidationException::withMessages(['asset_id' => 'Aset ini milik outlet lain.']);
        }

        $confirmed = (bool) ($data['critical_confirmed'] ?? false);
        $priority = PrioritySuggester::suggest($data['category'], $symptom, $outlet, now(), $confirmed);

        $title = $data['title'] ?? null;
        if ($title === null || trim($title) === '') {
            $cat = $categories[$data['category']];
            $title = $cat['label'].($symptom ? ' - '.$cat['symptoms'][$symptom]['label'] : '');
        }

        $now = now();
        $responseDue = \App\Support\SlaCalculator::calculateResponseDue($priority, $now, $outlet);
        $resolutionDue = \App\Support\SlaCalculator::calculateResolutionDue($priority, $now, $outlet);

        $ticket = DB::transaction(function () use ($data, $brand, $outlet, $asset, $actor, $priority, $title, $symptom, $confirmed, $responseDue, $resolutionDue) {
            $ticket = Ticket::create([
                'company_id' => $brand->company_id,
                'brand_id' => $brand->id,
                'outlet_id' => $outlet?->id,
                'asset_id' => $asset->id,
                'reported_by' => $actor->getKey(),
                'category' => $data['category'],
                'symptom' => $symptom,
                'priority' => $priority,
                'status' => 'baru',
                'title' => $title,
                'description' => $data['description'],
                'critical_confirmed' => $confirmed,
                'sla_response_due_at' => $responseDue,
                'sla_resolution_due_at' => $resolutionDue,
            ]);


            if (! empty($data['attachments'])) {
                app(AttachmentStore::class)->store($data['attachments'], $ticket, $actor, (int) $brand->company_id);
            }

            return $ticket;
        });

        AuditLogger::log('ticket.created', $ticket, [
            'priority' => $priority,
            'category' => $data['category'],
            'critical_confirmed' => $confirmed,
        ], $brand->company_id, $actor);

        return $ticket;
    }
}
