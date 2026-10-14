@extends('layouts.app')
@section('title', $ticket->ticket_number.' - PenpenHub')
@section('content')
    <div class="card">
        <h1>{{ $ticket->ticket_number }} - {{ $ticket->title }}</h1>
        <p>
            <span class="badge {{ $ticket->priority }}">{{ $ticket->priority }}</span>
            <span class="badge">{{ \App\Support\TicketStatus::label($ticket->status) }}</span>
        </p>
        <p class="muted">
            {{ $ticket->brand->name }} @if ($ticket->outlet) / {{ $ticket->outlet->name }} @endif
            &middot; Aset: {{ $ticket->asset->name }} ({{ $ticket->asset->typeLabel() }})
            &middot; Pelapor: {{ $ticket->reporter->name }}
            &middot; {{ $ticket->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
        </p>
        <p>{!! nl2br(e($ticket->description)) !!}</p>
        @foreach ($attachments as $a)
            <p><a href="{{ $a->signedUrl() }}" target="_blank" rel="noopener">{{ $a->original_name }}</a></p>
        @endforeach
    </div>

    <div class="card">
        <strong>Target SLA</strong>
        <div class="row" style="margin-top: 8px">
            <div>
                <div class="muted">SLA Respon</div>
                <div>
                    @if ($ticket->sla_response_due_at)
                        {{ $ticket->sla_response_due_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                        @if ($ticket->responded_at)
                            &middot; Direspon {{ $ticket->responded_at->timezone('Asia/Jakarta')->format('H:i') }}
                            <span class="badge {{ $ticket->isResponseBreached() ? 'breached' : 'met' }}">
                                {{ $ticket->isResponseBreached() ? 'Lewat SLA' : 'Tepat Waktu' }}
                            </span>
                        @else
                            <span class="badge {{ $ticket->isResponseBreached() ? 'breached' : 'pending' }}">
                                {{ $ticket->isResponseBreached() ? 'Lewat SLA' : 'Menunggu respon' }}
                            </span>
                        @endif
                    @else
                        <span class="muted">-</span>
                    @endif
                </div>
            </div>
            <div>
                <div class="muted">SLA Resolusi</div>
                <div>
                    @if ($ticket->sla_resolution_due_at)
                        {{ $ticket->sla_resolution_due_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                        @if ($ticket->resolved_at)
                            &middot; Selesai {{ $ticket->resolved_at->timezone('Asia/Jakarta')->format('H:i') }}
                            <span class="badge {{ $ticket->isResolutionBreached() ? 'breached' : 'met' }}">
                                {{ $ticket->isResolutionBreached() ? 'Lewat SLA' : 'Tepat Waktu' }}
                            </span>
                        @elseif ($ticket->isSlaPaused())
                            <span class="badge paused">Dijeda (Menunggu klien)</span>
                        @else
                            <span class="badge {{ $ticket->isResolutionBreached() ? 'breached' : 'pending' }}">
                                {{ $ticket->isResolutionBreached() ? 'Lewat SLA' : 'Dalam pengerjaan' }}
                            </span>
                        @endif
                    @else
                        <span class="muted">-</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if (count($transitions))
        <div class="card">

            <strong>Ubah status</strong>
            @foreach ($transitions as $to)
                <form method="POST" action="{{ route('tickets.status', $ticket) }}" class="row" style="margin-top:8px">
                    @csrf
                    <input type="hidden" name="to" value="{{ $to }}">
                    @if ($ticket->status === 'terselesaikan' && $to === 'dikerjakan')
                        <input name="reason" placeholder="Alasan menolak solusi (wajib)" required>
                    @endif
                    <button class="btn secondary" type="submit">
                        {{ $ticket->status === 'terselesaikan' ? ($to === 'ditutup' ? 'Konfirmasi selesai & tutup' : 'Tolak solusi') : \App\Support\TicketStatus::label($to) }}
                    </button>
                </form>
            @endforeach
        </div>
    @endif

    @if ($canManage)
        <div class="card">
            <form method="POST" action="{{ route('tickets.priority', $ticket) }}" class="row">
                @csrf
                <select name="priority">
                    @foreach (\App\Models\Ticket::PRIORITIES as $p)<option value="{{ $p }}" @selected($ticket->priority === $p)>{{ $p }}</option>@endforeach
                </select>
                <input name="reason" placeholder="Alasan (opsional, tercatat di audit log)">
                <button class="btn secondary" type="submit">Ubah prioritas</button>
            </form>
        </div>
    @endif

    <div class="card">
        <strong>Percakapan</strong>
        @forelse ($comments as $c)
            <div class="{{ $c->is_internal ? 'internal' : '' }}" style="padding:8px; margin-top:8px">
                <div class="muted">{{ $c->user->name }} &middot; {{ $c->created_at->timezone('Asia/Jakarta')->format('d M H:i') }}
                    @if ($c->is_internal) &middot; <strong>Catatan internal</strong>@endif</div>
                <div>{!! nl2br(e($c->body)) !!}</div>
                @foreach ($commentAttachments->get($c->id, collect()) as $a)
                    <div><a href="{{ $a->signedUrl() }}" target="_blank" rel="noopener">{{ $a->original_name }}</a></div>
                @endforeach
            </div>
        @empty
            <p class="muted">Belum ada komentar.</p>
        @endforelse

        @if ($ticket->isOpen())
            <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" enctype="multipart/form-data">
                @csrf
                <label for="body">Tulis komentar</label>
                <textarea id="body" name="body" rows="3" required></textarea>
                <label for="attachments">Lampiran (maks. {{ config('penpenhub.attachments.max_files') }})</label>
                <input id="attachments" type="file" name="attachments[]" multiple>
                @if ($canInternal)
                    <label><input type="checkbox" name="internal" value="1"> Catatan internal (tidak terlihat klien)</label>
                @endif
                <p><button class="btn" type="submit">Kirim</button></p>
            </form>
        @endif
    </div>

    <div class="card">
        <strong>Riwayat</strong>
        <ul>
            @foreach ($history as $h)
                <li class="muted">
                    {{ $h->created_at->timezone('Asia/Jakarta')->format('d M H:i') }} -
                    {{ $h->user?->name ?? 'Sistem' }}:
                    @if ($h->action === 'ticket.created') membuat tiket ({{ $h->meta['priority'] ?? '' }})
                    @elseif ($h->action === 'ticket.status_changed') status {{ \App\Support\TicketStatus::label($h->meta['from'] ?? '') }} &rarr; {{ \App\Support\TicketStatus::label($h->meta['to'] ?? '') }}
                    @else prioritas {{ $h->meta['from'] ?? '' }} &rarr; {{ $h->meta['to'] ?? '' }} @endif
                </li>
            @endforeach
        </ul>
    </div>
@endsection
