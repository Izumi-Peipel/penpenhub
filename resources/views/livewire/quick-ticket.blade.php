<div>
    <div class="card">
        <h1>Lapor masalah <span class="muted">(langkah {{ $step }} dari 3)</span></h1>

        @if ($step === 1)
            <label for="outlet">Outlet</label>
            <select id="outlet" wire:model.live="outletId">
                <option value="">Pilih outlet</option>
                @foreach ($this->outlets as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
            </select>
            @error('outletId')<div class="error">{{ $message }}</div>@enderror

            @if ($this->selectedOutlet)
                <label for="asset">Apa yang bermasalah?</label>
                <select id="asset" wire:model.live="assetId">
                    <option value="">Pilih aset</option>
                    @foreach ($this->assets as $a)<option value="{{ $a->id }}">{{ $a->name }} ({{ $a->typeLabel() }})</option>@endforeach
                </select>
                @error('assetId')<div class="error">{{ $message }}</div>@enderror
            @endif
            <p><button class="btn" type="button" wire:click="next">Lanjut</button></p>
        @endif

        @if ($step === 2)
            <label for="category">Kategori</label>
            <select id="category" wire:model.live="category">
                <option value="">Pilih kategori</option>
                @foreach ($categories as $key => $cat)<option value="{{ $key }}">{{ $cat['label'] }}</option>@endforeach
            </select>
            @error('category')<div class="error">{{ $message }}</div>@enderror

            @if ($category)
                <label for="symptom">Gejala</label>
                <select id="symptom" wire:model.live="symptom">
                    <option value="">Pilih gejala</option>
                    @foreach ($categories[$category]['symptoms'] as $key => $s)<option value="{{ $key }}">{{ $s['label'] }}</option>@endforeach
                </select>
                @error('symptom')<div class="error">{{ $message }}</div>@enderror
            @endif

            @if ($this->needsConfirmation)
                <div class="card internal">
                    <p>Outlet sedang di luar jam operasional. Masalah ini akan dicatat sebagai <strong>P2</strong> kecuali Anda mengonfirmasi dampak kritis.</p>
                    <label><input type="checkbox" wire:model.live="criticalConfirmed"> Ya, dampaknya kritis (perlu penanganan segera)</label>
                </div>
            @endif
            @if ($this->suggestedPriority)
                <p>Prioritas awal: <span class="badge {{ $this->suggestedPriority }}">{{ $this->suggestedPriority }}</span></p>
            @endif
            <p>
                <button class="btn secondary" type="button" wire:click="back">Kembali</button>
                <button class="btn" type="button" wire:click="next">Lanjut</button>
            </p>
        @endif

        @if ($step === 3)
            <label for="description">Ceritakan singkat apa yang terjadi</label>
            <textarea id="description" rows="4" wire:model="description"></textarea>
            @error('description')<div class="error">{{ $message }}</div>@enderror

            <label for="photos">Foto/video/log (opsional)</label>
            <input id="photos" type="file" wire:model="photos" multiple accept="image/*,video/mp4,video/quicktime,.log,.txt,.pdf">
            <div wire:loading wire:target="photos" class="muted">Mengunggah...</div>
            @error('photos')<div class="error">{{ $message }}</div>@enderror

            <p>
                <button class="btn secondary" type="button" wire:click="back">Kembali</button>
                <button class="btn" type="button" wire:click="submit" wire:loading.attr="disabled">Kirim laporan</button>
            </p>
        @endif
    </div>
</div>
