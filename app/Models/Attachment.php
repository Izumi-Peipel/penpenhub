<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\URL;

class Attachment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'attachable_type', 'attachable_id', 'disk', 'path',
        'original_name', 'mime', 'size', 'uploaded_by',
    ];

    protected $casts = ['size' => 'integer'];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Tautan sementara bertanda tangan; akses tetap dicek Policy saat dibuka. */
    public function signedUrl(): string
    {
        return URL::temporarySignedRoute(
            'attachments.show',
            now()->addMinutes((int) config('penpenhub.attachments.url_ttl_minutes', 10)),
            ['attachment' => $this->getKey()],
        );
    }

    public function isInline(): bool
    {
        return str_starts_with((string) $this->mime, 'image/') || str_starts_with((string) $this->mime, 'video/');
    }
}
